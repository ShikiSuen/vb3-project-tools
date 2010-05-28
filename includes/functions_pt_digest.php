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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_projecttools.php');

/**
* Sends the selected PT digest (daily or weekly)
*
* @param	string	Digest type (daily or weekly)
*/
function exec_pt_digest($type = 'daily')
{
	global $vbulletin;

	if (empty($vbulletin->pt_permissions))
	{
		$vbulletin->datastore->do_db_fetch("'pt_bitfields','pt_permissions'");
	}

	$lastdate = mktime(0, 0); // midnight today
	if ($type == 'daily')
	{
		// daily, yesterday midnight
		$lastdate -= 24 * 60 * 60;
	}
	else
	{
		// weekly, last week midnight
		$type = 'weekly';
		$lastdate -= 7 * 24 * 60 * 60;
	}

	require_once(DIR . '/includes/functions_misc.php');
	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser = new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());

	vbmail_start();

	// get new issues
	$issues = $vbulletin->db->query_read_slave("
		SELECT user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			user.timezoneoffset, IF(user.options & " . $vbulletin->bf_misc_useroptions['dstonoff'] . ", 1, 0) AS dstonoff,
			issue.*, language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride
		FROM " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe
		INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesubscribe.issueid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (issuesubscribe.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid))
		WHERE issuesubscribe.subscribetype = '$type'
			AND issue.lastpost > $lastdate
			AND issue.visible = 'visible'
			AND user.usergroupid <> 3
			AND (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	while ($issue = $vbulletin->db->fetch_array($issues))
	{
		// check that this user has the correct permissions to view
		$issueperms = fetch_project_permissions($issue, $issue['projectid'], $issue['issuetypeid']);

		if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
			OR ($issue['userid'] != $issue['submituserid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
		)
		{
			// can't view or can't view others' issues
			continue;
		}

		$notebits = '';

		$hourdiff = (date('Z', TIMENOW) / 3600 - ($issue['timezoneoffset'] + ($issue['dstonoff'] ? 1 : 0))) * 3600;
		$lastpost_adjusted = max(0, $issue['lastpost'] - $hourdiff);

		$issue['lastreplydate'] = date($vbulletin->options['dateformat'], $lastpost_adjusted);
		$issue['lastreplytime'] = date($vbulletin->options['timeformat'], $lastpost_adjusted);
		$issue['title'] = unhtmlspecialchars($issue['title']);
		$issue['username'] = unhtmlspecialchars($issue['username']);
		$issue['submitusername'] = unhtmlspecialchars($issue['submitusername']);
		$issue['lastpostusername'] = unhtmlspecialchars($issue['lastpostusername']);
		$issue['newposts'] = 0;

		// get posts
		$notes = $vbulletin->db->query_read_slave("
			SELECT issuenote.*
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
			WHERE issuenote.issueid = $issue[issueid]
				AND issuenote.visible = 'visible'
				AND issuenote.dateline > $lastdate
			ORDER BY issuenote.dateline
		");

		// compile
		$haveothers = false;
		while ($note = $vbulletin->db->fetch_array($notes))
		{
			if ($note['userid'] != $issue['userid'])
			{
				$haveothers = true;
			}
			$issue['newposts']++;

			$dateline_adjusted = max(0, $note['dateline'] - $hourdiff);

			$note['postdate'] = date($vbulletin->options['dateformat'], $dateline_adjusted);
			$note['posttime'] = date($vbulletin->options['timeformat'], $dateline_adjusted);
			$note['username'] = unhtmlspecialchars($note['username']);

			$plaintext_parser->set_parsing_language($issue['languageid']);
			$note['message'] = $plaintext_parser->parse($note['pagetext'], 'pt');

			eval(fetch_email_phrases('pt_digestnotebit', $issue['languageid']));
			$notebits .= $message;
		}

		// Don't send an update if the subscriber is the only one who posted in the issue.
		if ($haveothers)
		{
			// make email
			eval(fetch_email_phrases('pt_digestissue', $issue['languageid']));

			vbmail($issue['email'], $subject, $message);
		}
	}

	unset($plaintext_parser);

	// get new projects
	$projects = $vbulletin->db->query_read_slave("
		SELECT user.userid, user.salt, user.username, user.email, user.languageid, user.usergroupid, user.membergroupids,
			user.timezoneoffset, IF(user.options & " . $vbulletin->bf_misc_useroptions['dstonoff'] . ", 1, 0) AS dstonoff,
			IF(user.options & " . $vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask,
			project.*, projecttype.issuetypeid,
			language.dateoverride AS lang_dateoverride, language.timeoverride AS lang_timeoverride, language.locale AS lang_locale
		FROM " . TABLE_PREFIX . "pt_projecttypesubscribe AS projecttypesubscribe
		INNER JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON (projecttype.projectid = projecttypesubscribe.projectid AND projecttype.issuetypeid = projecttypesubscribe.issuetypeid)
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = projecttypesubscribe.projectid)
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = projecttypesubscribe.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid))
		WHERE projecttypesubscribe.subscribetype = '$type'
			AND projecttype.lastpost > $lastdate
			AND user.usergroupid <> 3
			AND (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	while ($project = $vbulletin->db->fetch_array($projects))
	{
		$userinfo = array(
			'lang_locale'    => $project['lang_locale'],
			'dstonoff'       => $project['dstonoff'],
			'timezoneoffset' => $project['timezoneoffset'],
		);

		$newissuebits = '';
		$newissues = 0;

		$updatedissuebits = '';
		$updatedissues = 0;

		$project['username_clean'] = unhtmlspecialchars($project['username']);
		$project['title_clean'] = unhtmlspecialchars($project['title_clean']);
		$project['issuetype_plural'] = fetch_phrase("issuetype_$project[issuetypeid]_plural", 'projecttools', '', false, true, $project['languageid'], false);

		$issues = $vbulletin->db->query_read_slave("
			SELECT issue.*
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			WHERE issue.projectid = $project[projectid]
				AND issue.issuetypeid = '$project[issuetypeid]'
				AND issue.visible = 'visible'
				AND issue.lastpost > $lastdate
		");

		while ($issue = $vbulletin->db->fetch_array($issues))
		{
			$issueperms = fetch_project_permissions($project, $issue['projectid'], $issue['issuetypeid']);

			if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
				OR ($issue['userid'] != $issue['submituserid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
			)
			{
				// can't view or can't view others' issues
				continue;
			}

			$issue['lastreplydate'] = vbdate($project['lang_dateoverride'] ? $project['lang_dateoverride'] : $vbulletin->options['default_dateformat'], $issue['lastpost'], false, true, true, false, $userinfo);
			$issue['lastreplytime'] = vbdate($project['lang_timeoverride'] ? $project['lang_timeoverride'] : $vbulletin->options['default_timeformat'], $issue['lastpost'], false, true, true, false, $userinfo);

			$issue['title_clean'] = unhtmlspecialchars($issue['title']);
			$issue['submitusername_clean'] = unhtmlspecialchars($issue['submitusername']);
			$issue['lastposter_clean'] = unhtmlspecialchars($issue['lastposter']);

			eval(fetch_email_phrases('pt_digestissuebit', $project['languageid']));

			if ($issue['submitdate'] > $lastdate)
			{
				// new issue
				$newissues++;
				$newissuebits .= $message;
			}
			else
			{
				$updatedissues++;
				$updatedissuebits .= $message;
			}

		}

		if ($newissues OR $updatedissues)
		{
			// make email
			eval(fetch_email_phrases('pt_digestproject', $project['languageid']));

			vbmail($project['email'], $subject, $message);
		}
	}

	vbmail_end();
}
?>
