<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* vBulletin Project Tools BB Code Parser
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/

require_once ( DIR . '/includes/class_bbcode.php' );

class vB_BbCodeParser_Pt extends vB_BbCodeParser
{
	public function __construct (&$registry, $tag_list = array (), $append_custom_tags = TRUE)
	{
		parent::vB_BbCodeParser ($registry, $tag_list, $append_custom_tags);
	}

	public function do_word_wrap ($text)
	{
		if ( $this->registry->options['pt_wordwrap'] != 0)
		{
			$text = fetch_word_wrapped_string ($text, $this->registry->options['pt_wordwrap']);
		}
		return $text;
	}
}

?>