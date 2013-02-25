<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_pt_patch_parse.php');

/**
* Returns the colored patch bits in HTML from a parsed patch file.
*
* @param	vB_PatchParser	A patch object, with the patch already parsed
*
* @return	string			The colored patch, ready for output in HTML
*/
function build_colored_patch(&$patch_parser)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	$patchbits = '';

	// loop through each file...
	foreach ($patch_parser->files AS $file)
	{
		$chunkbits = '';
		$filename = htmlspecialchars_uni($file['new']['filename']);

		// ...and each chunk in the file...
		foreach ($file['chunks'] AS $chunk)
		{
			$linebits = '';
			$context = htmlspecialchars_uni($chunk['context']);

			$show['line_counts'] = false;
			$chunk_header = $chunk['header'];
			if ($chunk_header['old_start'] >= 0)
			{
				// chunk header gives line numbers and amounts of lines in the old and new file
				if ($chunk_header['old_length'] >= 0)
				{
					$lines = construct_phrase($vbphrase['patch_lines_x_to_y'], $chunk_header['old_start'], ($chunk_header['old_start'] + $chunk_header['old_length'] + 1));
				}
				else
				{
					$lines = construct_phrase($vbphrase['patch_lines_x_on'], $chunk_header['old_start']);
				}

				// if we have an old and a new start, we can do line numbering down the left
				if ($chunk_header['new_start'] >= 0)
				{
					// we can show the line counts
					$show['line_counts'] = true;
					$old_line_num = $chunk_header['old_start'];
					$new_line_num = $chunk_header['new_start'];
				}
			}
			else
			{
				$lines = '&nbsp;';
			}

			// ...and each line in a chunk
			foreach ($chunk['lines'] AS $line)
			{
				switch ($line['type'])
				{
					case 'added':
					case 'removed':
					case 'context':
						break;
					default:
						$line['type'] = 'context';
				}

				$text = htmlspecialchars($line['text']);
				if (trim($text) === '')
				{
					$text = '&nbsp;';
				}

				$templater = vB_Template::create('pt_patchbit_line_' . $line['type']);
					$templater->register('new_line_num', $new_line_num);
					$templater->register('old_line_num', $old_line_num);
					$templater->register('text', $text);
				$linebits .= $templater->render();


				if ($show['line_counts'])
				{
					switch ($line['type'])
					{
						case 'added': $new_line_num++; break;
						case 'removed': $old_line_num++; break;
						case 'context': $old_line_num++; $new_line_num++; break;
					}
				}
			}

			$templater = vB_Template::create('pt_patchbit_chunk_header');
				$templater->register('context', $context);
				$templater->register('linebits', $linebits);
				$templater->register('lines', $lines);
			$chunkbits .= $templater->render();
		}

		$templater = vB_Template::create('pt_patchbit_file_header');
			$templater->register('chunkbits', $chunkbits);
			$templater->register('filename', $filename);
		$patchbits .= $templater->render();
	}

	return $patchbits;
}

?>