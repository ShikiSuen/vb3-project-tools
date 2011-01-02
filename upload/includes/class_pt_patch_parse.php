<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Parse Patch File into Usable array
*
* This class will take a patch in unified diff form and parse into a more user friendly format
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*
*/
class vB_PatchParser
{
	/**
	* Output. Consists of the patch broken down by file and then by chunks (and then by line)
	*
	* @var	array
	*/
	var $files = array();

	/**
	* The patch that is being parsed.
	*
	* @var	string
	*/
	var $data = '';

	/**
	* Length of the data.
	*
	* @var	integer
	*/
	var $data_len = 0;

	/**
	* Current position, used while parsing.
	*
	* @var	integer
	*/
	var $position = 0;

	/**
	* Text of the last error that occurred, if there is one.
	*
	* @var	string
	*/
	var $error = '';

	/**
	* Initiates the parsing of a patch file.
	*
	* @param	string	Text that is the patch
	*
	* @return	boolean	True on successful parsing, false otherwise (see $this->error)
	*/
	function parse(&$text)
	{
		// need to tidy up the nasty extra newlines we might get
		$this->data = preg_replace("#(\r\n|\r|\n)#s", "\n", $text);
		$this->data_len = strlen($this->data);
		$this->position = 0;

		while (!$this->is_end())
		{
			if (!$this->parse_patch_start())
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Processes the beginning of a patch file entry
	*
	* @return	boolean	True on success
	*/
	function parse_patch_start()
	{
		// read stuff before old file name
		$this->read_until_string('---');
		if ($this->is_end())
		{
			// we didn't get a ---, EOF
			return (sizeof($this->files) > 0); // if we didn't match any files then this isn't a patch
		}

		// skip ---, then read old file name
		$this->step_forward(3);
		$old_file = trim($this->read_until_string("\n"));

		// next we should have the new file name
		$this->read_until_string('+++');
		$this->step_forward(3);
		$new_file = trim($this->read_until_string("\n"));

		if ($old_file === ''  OR $new_file === '')
		{
			// malformed
			$this->error = "Malformed: missing old or new file (old: $old_file / new: $new_file)";
			return false;
		}

		$old_file_bits = explode("\t", $old_file);
		if (sizeof($old_file_bits) == 1)
		{
			$old_file_bits = preg_split('#\s+#', $old_file);
		}
		$new_file_bits = explode("\t", $new_file);
		if (sizeof($new_file_bits) == 1)
		{
			$new_file_bits = preg_split('#\s+#', $new_file);
		}

		// create holder for this file's chunks
		$this->files[] = array(
			'old' => array(
				'filename' => $old_file_bits[0],
				'date' => $old_file_bits[1],
				'revision' => $old_file_bits[2]
			),
			'new' => array(
				'filename' => $new_file_bits[0],
				'date' => $new_file_bits[1],
				'revision' => $new_file_bits[2]
			),
			'chunks' => array()
		);
		end($this->files);
		$fileid = key($this->files);

		// parse chunks
		return $this->parse_patch_chunks($fileid);
	}

	/**
	* Parses the chunks of a patch file entry.
	*
	* @return	boolean	True on success
	*/
	function parse_patch_chunks($fileid)
	{
		// parse through each chunk in the file until we get to the end of the file or we determine that we're at a new file
		do
		{
			$chunk = $this->parse_patch_chunk();
			if (is_array($chunk))
			{
				$this->files["$fileid"]['chunks'][] = $chunk;
			}
			else
			{
				return $chunk;
			}
		}
		while (!$this->is_end());

		return true;
	}

	/**
	* Parses an individual chunk out of a patch
	*
	* @return	boolean|array	Boolean: stop parsing, return to start state; array: chunk info
	*/
	function parse_patch_chunk()
	{
		$start_pos = $this->position;
		$this->skip_whitespace();

		// read chunk header
		$junk = $this->read_until_string('@@');
		if ($junk !== '')
		{
			if (strpos($junk, "\n---") !== false)
			{
				// we found --- on a new line before a @@, start a new patch
				$this->position = $start_pos;
				return true;
			}
			else
			{
				// malformed, something before the chunk header
				$this->error = "Malformed: junk before chunk header ($junk)";
				return false;
			}
		}

		$this->step_forward(2);

		$chunk_header = $this->read_until_string('@@');
		$this->step_forward(2);

		if ($this->read_current() == "\n")
		{
			// straight into a new line -- no context identifier
			$this->step_forward();
			$context_id = '';
		}
		else
		{
			// top level function identifier
			$context_id = trim($this->read_until_string("\n"));
			$this->step_forward();
		}

		// now start the actual lines of the chunk
		$lines = array();

		do
		{
			$line_pos = $this->position;

			$line = $this->read_until_character("\n");
			if ($line === '')
			{
				// empty patch line, that shouldn't happen?
				/*$this->error = "Empty patch line ({$this->position})";
				return false;*/

				// alt behavior - consider it a context line, because it's possible trailing spaces were trimmed
				$line = ' ';
			}
			if (!$this->is_end())
			{
				$this->step_forward();
			}

			// first character of the line is...
			if ($line[0] == '\\')
			{
				// no new line at end of file -- ignore this for the "pretty" patch
				continue;
			}

			switch ($line[0])
			{
				// ...a normal patch file line
				case ' ': $line_type = 'context'; break;
				case '+': $line_type = 'added'; break;
				case '-': $line_type = 'removed'; break;

				default:
					// something else, which means this patch entry is finished
					$this->position = $line_pos;
					break 2;
			}

			// kill the line identifier
			$line = substr($line, 1);
			if (!is_string($line))
			{
				$line = '';
			}
			$lines[] = array('type' => $line_type, 'text' => $line);
		}
		while (!$this->is_end());

		if ($lines)
		{
			preg_match('#-(\d+)(,(\d+))?\s+\+(\d+)(,(\d+))#siU', trim($chunk_header), $match);
			return array(
				'header' => array(
					'old_start' => isset($match[1]) ? $match[1] : -1,
					'old_length' => isset($match[3]) ? $match[3] : -1,
					'new_start' => isset($match[4]) ? $match[4] : -1,
					'new_length' => isset($match[6]) ? $match[6] : -1
				),
				'context' => $context_id,
				'lines' => $lines
			);
		}
		else
		{
			return true;
		}
	}

	/**
	* Returns true if the parser is at the end of the string to parse.
	*
	* @return	boolean
	*/
	function is_end()
	{
		return ($this->position >= $this->data_len);
	}

	/**
	* Reads the current character from the string.
	*
	* @return	string
	*/
	function read_current()
	{
		return $this->data[$this->position];
	}

	/**
	* Returns the next character from the string. Moves the pointer forward.
	*
	* @return	string
	*/
	function read_next()
	{
		++$this->position;
		return $this->data[$this->position];
	}

	/**
	* Peeks at the next character in the string. Does not move the pointer.
	*
	* @return string
	*/
	function peek()
	{
		return $this->data[$this->position + 1];
	}

	/**
	* Moves the pointer forward 1 or more characters character.
	*
	* @param	int	Amount to move forward, defaults to 1
	*/
	function step_forward($amount = 1)
	{
		$this->position += $amount;
		if ($this->position > $this->data_len)
		{
			$this->position = $this->data_len;
		}
	}

	/**
	* Moves the pointer back a character.
	*
	* @param	int	Amount to move forward, defaults to 1
	*/
	function step_backwards($amount = 1)
	{
		$this->position -= $amount;
		if ($this->position < 0)
		{
			$this->position = 0;
		}
	}

	/**
	* Reads until a character from the list is found.
	*
	* @param	string	A list of characters to stop when found. Each byte is treated as a character.
	*
	* @return	string
	*/
	function read_until_character($character_list)
	{
		$read_until = $this->data_len;

		$strlen = strlen($character_list);
		for ($i = 0; $i < $strlen; ++$i)
		{
			// step through each character in the list and find the first occurance
			// after the current position
			$char_pos = strpos($this->data, $character_list[$i], $this->position);

			// if that occurred earlier than the previous first occurance, only read until there
			if ($char_pos !== false AND $char_pos < $read_until)
			{
				$read_until = $char_pos;
			}
		}

		$text = strval(substr($this->data, $this->position, $read_until - $this->position));
		$this->position = $read_until;

		return $text;
	}

	/**
	* Reads until the exact string is found.
	*
	* @param	string	When this string is encountered, reading is stopped.
	*
	* @return	string
	*/
	function read_until_string($string)
	{
		$string_pos = strpos($this->data, $string, $this->position);
		if ($string_pos === false)
		{
			$string_pos = $this->data_len;
		}

		$text = substr($this->data, $this->position, $string_pos - $this->position);
		$this->position = $string_pos;
		return $text;
	}

	/**
	* Reads until the current character is *not* found in the list.
	*
	* @param	string	A list of characters to read while matched.
	*
	* @return	string
	*/
	function read_while_character($character_list)
	{
		$length = strspn(substr($this->data, $this->position), $character_list);

		$text = substr($this->data, $this->position, $this->position + $length);
		$this->position += $length;
		return $text;
	}

	/**
	* Reads until the end of the string.
	*
	* @return	string
	*/
	function read_until_end()
	{
		$text = substr($this->data, $this->position);
		$this->position = $this->data_len;
		return $text;
	}

	/**
	* Skips past any whitespace (spaces, carriage returns, new lines, tabs).
	*/
	function skip_whitespace()
	{
		$this->read_while_character(" \r\n\t");
	}
}
?>
