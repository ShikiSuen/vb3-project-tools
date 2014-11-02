<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Fetches the note history for a selected note.
*
* @param	integer		Issue note to find history for (assumed to already be cleaned!)
*
* @return	resource	Database result set
*/
function &fetch_note_history($issuenoteid)
{
	global $db;

	return $db->query_read_slave("
		SELECT issuenotehistory.*, user.username
		FROM " . TABLE_PREFIX . "pt_issuenotehistory AS issuenotehistory
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenotehistory.userid)
		WHERE issuenotehistory.issuenoteid = $issuenoteid
		ORDER BY issuenotehistory.dateline DESC
	");
}

/**
* Builds the history bit for a selected history point
*
* @param	array	Array of information for this histoy point
* @param	object	BB code parser
*
* @return	string	History bit HTML
*/
function build_history_bit($history, &$bbcode)
{
	global $vbulletin, $vbphrase, $show;

	$history['editdate'] = vbdate($vbulletin->options['dateformat'], $history['dateline'], true);
	$history['edittime'] = vbdate($vbulletin->options['timeformat'], $history['dateline']);
	$history['message'] = $bbcode->parse($history['pagetext'], 'pt');
	if ($history['reason'] === '')
	{
		$history['reason'] = $vbphrase['n_a'];
	}

	($hook = vBulletinHook::fetch_hook('project_historybit')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_historybit');
		$templater->register('history', $history);
	$edit_history = $templater->render();
	return $edit_history;
}

?>