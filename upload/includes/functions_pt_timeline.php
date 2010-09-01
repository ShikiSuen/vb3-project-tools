<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.0.1                   # ||
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

require_once(DIR . '/includes/functions_misc.php');

/**
* Prepares a timeline entry for display, including grouping the entries based on time periods.
*
* @param	array	Timeline data (data from issuenote table)
* @param	string	Method of grouping: none, hourly, daily, monthly
* @param	string	(By ref) The group this entry belongs to
*
* @return	array	Timeline data, formatted
*/
function prepare_timeline_entry($timeline, $grouping_format, &$groupid)
{
	global $vbulletin, $vbphrase, $stylevar;

	// general prep
	$timeline['title'] = fetch_censored_text(fetch_word_wrapped_string($timeline['title']));
	$timeline['issuetype'] = $vbphrase["issuetype_$timeline[issuetypeid]_singular"];
	if ($typeicon = $vbulletin->pt_issuetype["$timeline[issuetypeid]"]['iconfile'])
	{
		$timeline['typeicon'] = $typeicon;
	}
	$timeline['changetime'] = vbdate($vbulletin->options['timeformat'], $timeline['dateline']);

	// phrase selection
	switch ($timeline['notetype'])
	{
		case 'user':
			if ($timeline['isfirstnote'])
			{
				$vbphrase['timeline_entry_phrase'] = $vbphrase['timeline_entry_issue'];
			}
			else
			{
				$vbphrase['timeline_entry_phrase'] = $vbphrase['timeline_entry_reply'];
			}
			break;
		case 'petition':
			$vbphrase['timeline_entry_phrase'] = $vbphrase['timeline_entry_petition'];
			break;
		case 'system':
			$changes = unserialize($timeline['pagetext']);
			if (is_array($changes))
			{
				$vbphrase['timeline_entry_phrase'] = $vbphrase['timeline_entry_system_specific'];

				$change_text = array();
				foreach ($changes AS $entry)
				{
					if (isset($vbphrase["field_$entry[field]"]))
					{
						$change_text[] = $vbphrase["field_$entry[field]"];
					}
				}
				$timeline['details'] = implode(', ', $change_text);
			}

			if (!$timeline['details'])
			{
				$vbphrase['timeline_entry_phrase'] = $vbphrase['timeline_entry_system'];
				$timeline['details'] = '';
			}
			break;
		case 'deleted':
		case 'moderation':
		default:
			return false; // NOTE: maybe consider permissions in the future
	}

	// grouping
	switch ($grouping_format)
	{
		case 'monthly':
			$groupid = vbdate('Ym', $timeline['dateline'], false, false);
			break;

		case 'daily':
			$groupid = vbdate('Ymd', $timeline['dateline'], false, false);
			break;

		case 'hourly':
			$groupid = vbdate('YmdH', $timeline['dateline'], false, false);
			break;

		case 'none':
		default:
			$groupid = '0';
			break;
	}

	($hook = vBulletinHook::fetch_hook('project_timeline_prepare')) ? eval($hook) : false;

	return $timeline;
}

/**
* Fetches activity list for the given criteria.
*
* @param	string		Arbitrary where clause to limit results
* @param	integer		Number of results to limit to
* @param	integer		Offset row ID
* @param	boolean		Whether to get the total matched row count
*
* @return	resource	Query result set
*/
function &fetch_activity_list($criteria, $limit = 50, $offset = 0, $get_row_count = true)
{
	global $db, $vbulletin;

	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);
	return $db->query_read("
		SELECT " . ($get_row_count ? 'SQL_CALC_FOUND_ROWS' : '') . "
			issue.*,
			issuenote.issuenoteid, issuenote.dateline,
			IF(user.username IS NOT NULL, user.username, issuenote.username) AS username, issuenote.userid,
			issuenote.type AS notetype, issuenote.isfirstnote, issuenote.pagetext, issuenote.visible As notevisible,
			project.title_clean AS projecttitle_clean
			" . ($vbulletin->userinfo['userid'] ? ", IF(issueassign.issueid IS NULL, 0, 1) AS isassigned" : '') . "
			" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issuenote.issueid = issue.issueid)
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
		" . ($vbulletin->userinfo['userid'] ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON
				(issueassign.issueid = issue.issueid AND issueassign.userid = " . $vbulletin->userinfo['userid'] . ")
		" : '') . "
		" . ($marking ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
		" : '') . "
		WHERE issuenote.visible IN ('visible', 'private')
			AND issue.visible IN ('visible', 'private')
			" . ($criteria ? "AND ($criteria)" : '') . "
		ORDER BY issuenote.dateline DESC
		" . ($limit ? "LIMIT $offset, $limit" : '') . "
	");
}

/**
* Prepares an activity list for display using daily grouping.
*
* @param	resource	Query result to work through
*
* @return	array		Array of grouped activities
*/
function prepare_activity_list(&$results)
{
	global $vbulletin, $db, $show, $stylevar, $vbphrase;

	$activity_groups = array();

	while ($activity = $db->fetch_array($results))
	{
		$activity = prepare_timeline_entry($activity, 'daily', $groupid);
		if (!$activity)
		{
			continue;
		}

		if (!isset($activity_groups["$groupid"]))
		{
			$activity_groups["$groupid"] = '';
		}

		if ($activity['notetype'] != 'system' AND $activity['dateline'] > issue_lastview($activity))
		{
			$activity['newflag'] = true;
		}

		($hook = vBulletinHook::fetch_hook('project_timeline_item')) ? eval($hook) : false;

		eval('$activity_groups["$groupid"] .= "' . fetch_template('pt_timeline_item') . '";');
	}

	// do we need to show the timeline fix for less than 3.6.8?
	$show['js_timeline_fix'] = ($vbulletin->options['templateversion'] < '3.6.8');

	return $activity_groups;
}

/**
* Makes a printable date from a daily-formatted group date (yyyymmdd).
*
* @param	string	Group date (yyyymmdd)
*
* @return	string	Printable version of that date
*/
function make_group_date($groupid)
{
	global $vbulletin;

	preg_match('#^(\d{4})(\d{2})(\d{2})$#', $groupid, $match);

	// use yesterday/today option if they chose to use that (otherwise just show dates)
	return vbdate($vbulletin->options['dateformat'], vbmktime(0, 0, 0, $match[2], $match[3], $match[1]), $vbulletin->options['yestoday'] == 1);
}

?>