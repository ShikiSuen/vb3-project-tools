<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

require_once(DIR . '/includes/class_bbcode.php');

/**
* vBulletin Project Tools BB Code Parser
*
* @package 		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_BbCodeParser_Pt extends vB_BbCodeParser
{
	/**
	 * vB_BbCodeParser_Pt::__construct()
	 * 
	 * Class Constructor. Initializes the core vBulletin BB Code Parser.
	 * 
	 * @param	vB_Registry	The vBulletin Registry.
	 * @param	array		List of tags for use within the parser.
	 * @param	bool		Determine if custom tags should be used or not.
	 */
	public function __construct(&$registry, $tag_list = array (), $append_custom_tags = true)
	{
		parent::__construct($registry, $tag_list, $append_custom_tags);
	}

	/**
	 * vB_BbCodeParser_Pt::do_word_wrap()
	 * 
	 * Overrides vB_BbCodeParser::do_word_wrap() for use within Project Tools.
	 * 
	 * @param	string	The text to wrap.
	 * @return	string	The wrapped text.
	 */
	public function do_word_wrap($text)
	{
		if ($this->registry->options['pt_wordwrap'] != 0)
		{
			$text = fetch_word_wrapped_string($text, $this->registry->options['pt_wordwrap']);
		}
		return $text;
	}
}

?>