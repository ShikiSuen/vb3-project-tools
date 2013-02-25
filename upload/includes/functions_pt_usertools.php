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

/**
* Merges PT-related data. Used in useradmin_merge hook.
*
* @param	array	Array of destination user info
* @param	array	Array of source user info
*/
function process_pt_user_merge($destinfo, $sourceinfo)
{
	global $vbulletin, $db;

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			submituserid = $destinfo[userid],
			submitusername = '" . $db->escape_string($destinfo['username']) . "'
		WHERE submituserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			lastpostuserid = $destinfo[userid],
			lastpostusername = '" . $db->escape_string($destinfo['username']) . "'
		WHERE lastpostuserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_issueassign SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");


	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issueattach SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuechange SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuedeletionlog SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuenote SET
			userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuenotehistory SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuepetition SET
			resolveuserid = $destinfo[userid]
		WHERE resolveuserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issueprivatelastpost SET
			lastpostuserid = $destinfo[userid],
			lastpostusername = '" . $db->escape_string($destinfo['username']) . "'
		WHERE lastpostuserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_issueread SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuereport SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_issuereportsubscribe SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_issuesubscribe SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_projectread SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_projecttype SET
			lastpostuserid = $destinfo[userid],
			lastpostusername = '" . $db->escape_string($destinfo['username']) . "'
		WHERE lastpostuserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_projecttypeprivatelastpost SET
			lastpostuserid = $destinfo[userid],
			lastpostusername = '" . $db->escape_string($destinfo['username']) . "'
		WHERE lastpostuserid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "pt_projecttypesubscribe SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_user SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
}

/**
* Deletes PT-related user data. Used in the userdata_delete hook.
*
* @param	vB_DataManager_User	User DM
*/
function process_pt_user_delete(&$dataman)
{
	global $vbulletin, $db;

	// deleting a user, so update the issues and notes appropriately
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			submitusername = '" . $db->escape_string($dataman->existing['username']) . "',
			submituserid = 0
		WHERE submituserid = " . $dataman->existing['userid'] . "
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issuenote SET
			username = '" . $db->escape_string($dataman->existing['username']) . "',
			userid = 0
		WHERE userid = " . $dataman->existing['userid'] . "
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			lastpostuserid = 0
		WHERE lastpostuserid = " . $dataman->existing['userid'] . "
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issueassign
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issueprivatelastpost SET
			lastpostuserid = 0
		WHERE lastpostuserid = " . $dataman->existing['userid'] . "
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issueread
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issuereport
		WHERE userid = " . $dataman->existing['userid'] . "
			AND public = 1
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issuereportsubscribe
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issuesubscribe
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectread
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projecttypesubscribe
		WHERE userid = " . $dataman->existing['userid']
	);

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_user
		WHERE userid = " . $dataman->existing['userid'] . "
	");

	require_once(DIR . '/includes/adminfunctions_projecttools.php');
	build_assignable_users();
	build_pt_user_list('pt_report_users', 'pt_report_user_cache');
}

?>