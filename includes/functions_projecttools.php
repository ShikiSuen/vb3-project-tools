<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| #  This is file is subject to the vBulletin Open Source License.   # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Prepares the project permissions for a user, taking into account primary and
* secondary groups.
*
* @param	array	(In/Out) User information
*
* @return	array	Project permissions (also in $user['projectpermissions'])
*/
function prepare_project_permissions(&$user)
{
	global $vbulletin;

	$membergroupids = fetch_membergroupids_array($user);

	// build usergroup permissions
	if (sizeof($membergroupids) == 1 OR !($vbulletin->usergroupcache["$user[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['allowmembergroups']))
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($user['usergroupid']);

		// just return the permissions for the user's primary group (user is only a member of a single group)
		$user['projectpermissions'] = $vbulletin->pt_permissions["$user[usergroupid]"];
		if (!is_array($user['projectpermissions']))
		{
			$user['projectpermissions'] = array();
		}
	}
	else
	{
		$user['projectpermissions'] = array();

		// return the merged array of all user's membergroup permissions (user has additional member groups)
		foreach ($membergroupids AS $usergroupid)
		{
			if (!is_array($vbulletin->pt_permissions["$usergroupid"]))
			{
				continue;
			}
			if (!($vbulletin->usergroupcache["$usergroupid"]['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
			{
				// group's access is globally disabled, skip counting their permissions
				continue;
			}

			foreach ($vbulletin->pt_permissions["$usergroupid"] AS $projectid => $types)
			{
				foreach ($types AS $type => $value)
				{
					foreach ($value AS $key => $val)
					{
						$user['projectpermissions']["$projectid"]["$type"]["$key"] |= intval($val);
					}
				}
			}
		}
	}

	if ($user['infractiongroupids'])
	{
		foreach (explode(',', str_replace(' ', '', $user['infractiongroupids'])) AS $usergroupid)
		{
			foreach ($vbulletin->pt_permissions["$usergroupid"] AS $projectid => $types)
			{
				foreach ($types AS $type => $value)
				{
					foreach ($value AS $key => $val)
					{
						$user['projectpermissions']["$projectid"]["$type"]["$key"] &= intval($val);
					}
				}
			}
		}
	}

	return $user['projectpermissions'];
}

/**
* Fetch the project permissions for a specified project and optionally type.
* Prepares the permissions if necessary.
*
* @param	array	(In/Out) User information
* @param	integer	Project ID
* @param	string	Issue Type ID
*
* @return	array	Permissions for specified permutation
*/
function fetch_project_permissions(&$user, $projectid, $type = '')
{
	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	if ($type)
	{
		return $user['projectpermissions']["$projectid"]["$type"];
	}
	else
	{
		return $user['projectpermissions']["$projectid"];
	}
}

/**
* Fetches information about the selected project.
*
* @param	integer	The project we want info about
* @param	boolean	Whether to perform a permission check in prepare_project()
* @param	boolean	Whether to use the project cache (only skip it if necessary/data may have been changed)
*
* @return	array|false	Array of information about the project or false if it doesn't exist
*/
function fetch_project_info($projectid, $perm_check = true, $use_cache = true)
{
	global $db, $vbulletin;
	static $cache;

	$projectid = intval($projectid);
	if (!$projectid)
	{
		return false;
	}

	if ($use_cache AND isset($cache["$projectid"]))
	{
		$project = $cache["$projectid"];
	}
	else if ($use_cache AND isset($vbulletin->pt_projects["$projectid"]))
	{
		return $vbulletin->pt_projects["$projectid"];
	}
	else
	{
		$project = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_project
			WHERE projectid = $projectid
		");
		$cache["$projectid"] = $project;
	}

	if (!$project)
	{
		return false;
	}

	return $project;
}

/**
* Fetches project info and throws an error if it's not valid
*
* @param	integer	Project ID
*
* @return	array	Project info
*/
function verify_project($projectid)
{
	global $vbulletin, $vbphrase;

	$project = fetch_project_info($projectid);
	if (!$project)
	{
		standard_error(fetch_error('invalidid', $vbphrase['project'], $vbulletin->options['contactuslink']));
	}

	($hook = vBulletinHook::fetch_hook('project_project_verify')) ? eval($hook) : false;

	return $project;
}

/**
* Fetches information about the selected issue.
*
* @param	integer	The issue we want info about
* @param	array	A list of extra data to fetch
*
* @return	array|false	Array of information about the issue or false if it doesn't exist
*/
function fetch_issue_info($issueid, $extra_info = array())
{
	global $db, $vbulletin;

	$version_join = empty($vbulletin->pt_versions);
	$category_join = empty($vbulletin->pt_categories);
	$browsing_user_joins = ($vbulletin->userinfo['userid'] > 0);
	$avatar_join = ($vbulletin->options['avatarenabled'] AND in_array('avatar', $extra_info));
	$vote_join = ($vbulletin->userinfo['userid'] > 0 AND in_array('vote', $extra_info));
	$milestone_join = in_array('milestone', $extra_info);
	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('project_issue_fetch')) ? eval($hook) : false;

	$issue = $db->query_first("
		SELECT issuenote.*, issue.*, issuenote.username AS noteusername, issuenote.ipaddress AS noteipaddress,
			" . ($version_join ? "appliesversion.versionname AS appliesversion, addressedversion.versionname AS addressedversion," : '') . "
			" . ($category_join ? "projectcategory.title AS categorytitle," : '') . "
			" . ($avatar_join ? 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,' : '') . "
			user.*, userfield.*, usertextfield.*,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, user.infractiongroupid,
			" . ($browsing_user_joins ? "issuesubscribe.subscribetype, IF(issueassign.issueid IS NULL, 0, 1) AS isassigned," : '') . "
			" . ($vote_join ? "issuevote.vote," : '') . "
			issue.visible, issue.lastactivity, issue.lastpost,
			user.lastactivity AS user_lastactivity
			" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
			" . ($milestone_join ? ", milestone.title_clean AS milestonetitle" : '') . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "pt_issue AS issue
		INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON
			(issuenote.issuenoteid = issue.firstnoteid)
		" . ($version_join ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS appliesversion ON
				(appliesversion.projectversionid = issue.appliesversionid)
			LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS addressedversion ON
				(addressedversion.projectversionid = issue.addressedversionid)
		" : '') . "
		" . ($category_join ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_projectcategory AS projectcategory ON
				(projectcategory.projectcategoryid = issue.projectcategoryid)
		" : '') . "
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		" . ($avatar_join ? "
			LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid)
			LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : '') . "
		" . ($browsing_user_joins ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON
				(issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON
				(issueassign.issueid = issue.issueid AND issueassign.userid = " . $vbulletin->userinfo['userid'] . ")
		" : '') . "
		" . ($vote_join ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issuevote AS issuevote ON (issue.issueid = issuevote.issueid AND issuevote.userid = " . $vbulletin->userinfo['userid'] . ")
		" : '') . "
		" . ($marking ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
		" : '') . "
		" . ($milestone_join ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_milestone AS milestone ON (issue.milestoneid = milestone.milestoneid)
		" : '') . "
		$hook_query_joins
		WHERE issue.issueid = " . intval($issueid) . "
		 $hook_query_where
	");
	if (!$issue)
	{
		return false;
	}

	if (!$version_join)
	{
		$issue['appliesversion'] = ($issue['appliesversionid'] ? $vbulletin->pt_versions["$issue[appliesversionid]"]['versionname'] : '');
		$issue['addressedversion'] = ($issue['addressedversionid'] ? $vbulletin->pt_versions["$issue[addressedversionid]"]['versionname'] : '');
	}

	if (!$category_join)
	{
		$issue['categorytitle'] = ($issue['projectcategoryid'] ? $vbulletin->pt_categories["$issue[projectcategoryid]"]['title'] : '');
	}

	if (!$browsing_user_joins)
	{
		$issue['subscribetype'] = '';
		$issue['isassigned'] = 0;
	}

	return prepare_issue($issue);
}

/**
* Fetches issue info and throws an error if it's not valid
*
* @param	integer	Issue ID
* @param	boolean	Do additional perm check for browsing user?
* @param	array	Array of extra info to fetch. See fetch_issue_info() for more information.
*
* @return	array	Issue info
*/
function verify_issue($issueid, $perm_check = true, $extra_fetch_info = array())
{
	global $vbulletin, $vbphrase;

	$issue = fetch_issue_info($issueid, $extra_fetch_info);
	if (!$issue)
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue'], $vbulletin->options['contactuslink']));
	}

	if ($perm_check)
	{
		if (verify_issue_perms($issue, $vbulletin->userinfo) === false)
		{
			print_no_permission();
		}
	}

	($hook = vBulletinHook::fetch_hook('project_issue_verify')) ? eval($hook) : false;

	return $issue;
}

/**
* Verifies permissions for an issue
*
* @param	array	Array of issue information
* @param	array	Array of user information
*
* @return	array	true|false	true if the user has permissions
*/
function verify_issue_perms($issue, $userinfo)
{
	global $vbulletin;
	$issueperms = fetch_project_permissions($userinfo, $issue['projectid'], $issue['issuetypeid']);

	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
		OR ($userinfo['userid'] != $issue['submituserid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
	)
	{
		// can't view or can't view others' issues
		return false;
	}

	if ($issue['visible'] == 'private' AND
		(($issue['submituserid'] == $userinfo['userid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown'])) OR
		($issue['submituserid'] != $userinfo['userid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])))
	)
	{
		// can't view a private issue
		return false;
	}
	else if (($issue['visible'] == 'moderation' OR $issue['visible'] == 'deleted') AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']))
	{
		// issue awaiting moderation/deleted and can't manage
		return false;
	}

	return true;
}

/**
* Verifies permissions for an issue note
*
* @param	array	Array of issue information
* @param	array	Array of issue note information
* @param	array	Array of user information
*
* @return	array	true|false	true if the user has permissions
*/
function verify_issue_note_perms($issue, $issuenote, $userinfo)
{
	global $vbulletin;
	$issueperms = fetch_project_permissions($userinfo, $issue['projectid'], $issue['issuetypeid']);

	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
		OR ($userinfo['userid'] != $issuenote['userid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
	)
	{
		// can't view or can't view others' issues
		return false;
	}

	if ($issuenote['visible'] == 'private' AND
		(($issuenote['userid'] == $userinfo['userid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown'])) OR
		($issuenote['userid'] != $userinfo['userid'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])))
	)
	{
		// can't view a private issue
		return false;
	}
	else if (($issuenote['visible'] == 'moderation' OR $issuenote['visible'] == 'deleted') AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']))
	{
		// issue awaiting moderation/deleted and can't manage
		return false;
	}

	return true;
}

/**
* Prepares issue data for display.
*
* @param	array	Issue data without any processing
*
* @return	array	Processed issue data
*/
function prepare_issue($issue)
{
	global $vbulletin, $vbphrase;

	if ($vbulletin->options['wordwrap'] != 0)
	{
		$issue['title'] = fetch_word_wrapped_string($issue['title']);
		$issue['summary'] = fetch_word_wrapped_string($issue['summary']);
	}

	$issue['title'] = fetch_censored_text($issue['title']);
	$issue['summary'] = fetch_censored_text($issue['summary']);

	$issue['lastposttime'] = vbdate($vbulletin->options['timeformat'], $issue['lastpost']);
	$issue['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $issue['lastpost'], true);

	// post reply date/time (for search results as posts mainly)
	if ($issue['submitdate'])
	{
		$issue['submittime'] = vbdate($vbulletin->options['timeformat'], $issue['submitdate']);
		$issue['submitdate'] = vbdate($vbulletin->options['dateformat'], $issue['submitdate'], true);
	}
	else
	{
		$issue['submitdate'] = '';
		$issue['submittime'] = '';
	}

	$issue['replycount'] = vb_number_format($issue['replycount']);
	$issue['attachcount'] = vb_number_format($issue['attachcount']);

	if ($typeicon = $vbulletin->pt_issuetype["$issue[issuetypeid]"]['iconfile'])
	{
		$issue['typeicon'] = $typeicon;
	}

	$issue['issuetype'] = $vbphrase["issuetype_$issue[issuetypeid]_singular"];
	$issue['status'] = $vbphrase["issuestatus$issue[issuestatusid]"];
	$issue = fetch_issue_version_text($issue);

	if (!$issue['projectcategoryid'])
	{
		$issue['categorytitle'] = $vbphrase['unknown'];
	}

	$issue['priority_text'] = $vbphrase["priority_$issue[priority]"];

	if (!$issue['milestoneid'])
	{
		$issue['milestonetitle'] = $vbphrase['none_meta'];
	}

	$issue['lastread'] = issue_lastview($issue);
	$issue['newflag'] = ($issue['lastpost'] > $issue['lastread']);

	($hook = vBulletinHook::fetch_hook('project_issue_prepare')) ? eval($hook) : false;

	return $issue;
}

/**
* Fetch the phrased version of an issue's versions.
*
* @param	array	Issue information
*
* @return	array	Issue information with appliesversion and addressedversion set
*/
function fetch_issue_version_text($issue)
{
	global $vbulletin, $vbphrase;

	if (!$issue['appliesversionid'])
	{
		$issue['appliesversion'] = $vbphrase['unknown'];
	}

	if (!$issue['isaddressed'])
	{
		$issue['addressedversion'] = $vbphrase['none_meta'];
	}
	else if ($issue['addressedversionid'] == 0)
	{
		$issue['addressedversion'] = $vbphrase['next_release'];
	}

	return $issue;
}

/**
* Verifies that an issue type is valid. Errors if not.
*
* @param	string	Issue type ID
* @param	integer	Project ID.
*/
function verify_issuetypeid($issuetypeid, $projectid)
{
	global $vbulletin, $vbphrase;

	$project = fetch_project_info($projectid);
	if (!$project)
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue_type'], $vbulletin->options['contactuslink']));
	}

	$types = $vbulletin->pt_projects["$project[projectid]"]['types'];
	if (!isset($types["$issuetypeid"]))
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue_type'], $vbulletin->options['contactuslink']));
	}

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $projectid, $issuetypeid);
	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
	{
		print_no_permission();
	}

	return true;
}

/**
* Translates a system note into an array of displayable pieces.
*
* @param	array	Array of issue changes; may either be 1D (with 1 change) or 2D (with 1+ changes) or a serialized string
*
* @return	array|string	Displayable versions of the changes. If 1D array passed in, returns a string; if 2D, array
*/
function translate_system_note($data)
{
	global $vbphrase, $vbulletin;

	if (is_string($data))
	{
		$data = unserialize($data);
	}

	$count = sizeof($data);
	$output = array();

	foreach ($data AS $entry)
	{
		$fieldname = isset($vbphrase["field_$entry[field]"]) ? $vbphrase["field_$entry[field]"] : $entry['field'];
		$phrase = (isset($vbphrase["system_message_$entry[field]"]) ? "system_message_$entry[field]" : 'system_message_default');

		switch ($entry['field'])
		{
			case 'issuestatusid':
				$entry['oldvalue'] = $vbphrase["issuestatus$entry[oldvalue]"];
				if (empty($entry['oldvalue']))
				{
					$entry['oldvalue'] = $vbphrase['unknown'];
				}
				$entry['newvalue'] = $vbphrase["issuestatus$entry[newvalue]"];
				if (empty($entry['newvalue']))
				{
					$entry['newvalue'] = $vbphrase['unknown'];
				}
				break;

			case 'priority':
				$entry['oldvalue'] = $vbphrase["priority_$entry[oldvalue]"];
				$entry['newvalue'] = $vbphrase["priority_$entry[newvalue]"];
				break;

			case 'issuetypeid':
				$entry['oldvalue'] = $vbphrase["issuetype_$entry[oldvalue]_singular"];
				$entry['newvalue'] = $vbphrase["issuetype_$entry[newvalue]_singular"];
				break;

			case 'isaddressed':
				$phrase = ($entry['newvalue'] ? 'system_message_addressed' : 'system_message_unaddressed');
				break;

			case 'appliesversionid':
			case 'addressedversionid':
				$entry['oldvalue'] = $vbulletin->pt_versions["$entry[oldvalue]"]['versionname'];
				if (empty($entry['oldvalue']))
				{
					$entry['oldvalue'] = $vbphrase['unknown'];
				}
				$entry['newvalue'] = $vbulletin->pt_versions["$entry[newvalue]"]['versionname'];
				if (empty($entry['newvalue']))
				{
					$entry['newvalue'] = $vbphrase['unknown'];
				}
				break;

			case 'projectcategoryid':
				$entry['oldvalue'] = $vbulletin->pt_categories["$entry[oldvalue]"]['title'];
				if (empty($entry['oldvalue']))
				{
					$entry['oldvalue'] = $vbphrase['unknown'];
				}
				$entry['newvalue'] = $vbulletin->pt_categories["$entry[newvalue]"]['title'];
				if (empty($entry['newvalue']))
				{
					$entry['newvalue'] = $vbphrase['unknown'];
				}
				break;

			case 'milestoneid':
				// note: if this is changed to show more information, permission data must be available
				break;

			default:
				($hook = vBulletinHook::fetch_hook('project_system_note_translate')) ? eval($hook) : false;
		}

		$output[] = construct_phrase($vbphrase["$phrase"], $fieldname, $entry['oldvalue'], $entry['newvalue']);
	}

	return $output;
}

/**
* Build array of SQL for a where clause to limit matched results to the user's permissions.
*
* @param	array	User information
* @param	string	Extra general permission to check for before returning (eg, cansearch)
*
* @return	array	[projectid][issuetypeid] => SQL for type only
*/
function build_issue_permissions_sql(&$user, $extra_general_perm = '')
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$clause = array();

	foreach ($user['projectpermissions'] AS $projectid => $types)
	{
		$type_options = array();
		foreach ($types AS $typeid => $perms)
		{
			if ($extra_general_perm AND !($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']["$extra_general_perm"]))
			{
				continue;
			}
			if (!($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
			{
				continue;
			}

			$private_text = '';

			$options = array("'visible'");
			if (($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown']) AND ($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers']))
			{
				$options[] = "'private'";
			}
			else
			{
				if ($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown'])
				{
					$private_text = " OR (issue.visible = 'private' AND issue.submituserid = $user[userid])";
				}
				else if ($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
				{
					$private_text = " OR (issue.visible = 'private' AND issue.submituserid <> $user[userid])";
				}
			}

			if ($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage'])
			{
				$options[] = "'moderation'";
				$options[] = "'deleted'";
			}

			$text = "issue.issuetypeid = '$typeid' AND (issue.visible IN (" . implode(',', $options) . ")$private_text)";
			if (!($perms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
			{
				$text .= " AND issue.submituserid = $user[userid]";
			}

			$type_options["$typeid"] = "($text)";
		}

		if (!$type_options)
		{
			continue;
		}

		$clause["$projectid"] = $type_options;
	}

	return $clause;
}

/**
* Returns array of SQL for each project that limits matched results to
* this user's permissions. Takes build_issue_permissions_sql() one step further.
*
* @param	array	User information
* @param	string	Extra general permission to check for before returning (eg, cansearch)
*
* @return	array	SQL, [projectid] => SQL, including projectid in text
*/
function build_issue_permissions_query($user, $extra_general_perm = '')
{
	$clause = build_issue_permissions_sql($user, $extra_general_perm);

	$return = array();
	foreach ($clause AS $projectid => $type_options)
	{
		$return["$projectid"] = "issue.projectid = $projectid AND (" . implode(' OR ', $type_options) . ")";
	}

	return $return;
}

/**
* Builds the left join and fields for private issue lastpost detection for the
* specified user in the specified project.
*
* @param	array	Array of user info
* @param	integer	Project ID to build for
* @param	string	(out) Output of the private last post join (empty if unneeded)
* @param	string	(out) Fields for the last post information. No leading or trailing commas!
*
* @return	boolean	True if a join is needed
*/
function build_issue_private_lastpost_sql_project(&$user, $projectid, &$private_lastpost_join, &$private_lastpost_fields)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$canviewprivate = array();

	foreach ($user['projectpermissions']["$projectid"] AS $type => $type_option)
	{
		// note: the way this is implemented, "others" means "any"
		if ($type_option['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
		{
			$canviewprivate[] = $type;
		}
	}

	if (!empty($canviewprivate))
	{
		$private_lastpost_join = "LEFT JOIN " . TABLE_PREFIX . "pt_issueprivatelastpost AS issueprivatelastpost ON
			(issueprivatelastpost.issueid = issue.issueid
				AND issueprivatelastpost.lastpost >= issue.lastpost
				AND issue.issuetypeid IN ('" . implode("', '", $canviewprivate) . "'))
		";

		$private_lastpost_fields = "
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost) AS lastpost,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpostusername, issue.lastpostusername) AS lastpostusername,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpostuserid, issue.lastpostuserid) AS lastpostuserid,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastnoteid, issue.lastnoteid) AS lastnoteid
		";
	}
	else
	{
		$private_lastpost_join = '';
		$private_lastpost_fields = '';
	}

	return ($private_lastpost_join !== '');
}

/**
* Builds the left join and fields for private issue lastpost detection for the
* specified user across all projects.
*
* @param	array	Array of user info
* @param	string	(out) Output of the private last post join (empty if unneeded)
* @param	string	(out) Fields for the last post information. No leading or trailing commas!
*
* @return	boolean	True if a join is needed
*/
function build_issue_private_lastpost_sql_all(&$user, &$private_lastpost_join, &$private_lastpost_fields)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$canviewprivate_combo = array(); // [issuetype combination] => array([projectids])

	foreach ($user['projectpermissions'] AS $projectid => $projectpermissions)
	{
		$project_canviewprivate = array();

		foreach ($projectpermissions AS $type => $type_option)
		{
			// note: the way this is implemented, "others" implies "own"
			if ($type_option['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
			{
				$project_canviewprivate[] = $type;
			}
		}

		if ($project_canviewprivate)
		{
			$canviewprivate_combo["'" . implode("', '", $project_canviewprivate) . "'"][] = $projectid;
		}
	}

	$canviewprivate = array();
	foreach ($canviewprivate_combo AS $issuetypelist => $projects)
	{
		$canviewprivate[] = "(issue.issuetypeid IN ($issuetypelist) AND issue.projectid IN (" . implode(',', $projects) . "))";
	}

	if (!empty($canviewprivate))
	{
		$private_lastpost_join = "LEFT JOIN " . TABLE_PREFIX . "pt_issueprivatelastpost AS issueprivatelastpost ON
			(issueprivatelastpost.issueid = issue.issueid
				AND issueprivatelastpost.lastpost >= issue.lastpost
				AND (" . implode(' OR ', $canviewprivate) . "))
		";

		$private_lastpost_fields = "
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost) AS lastpost,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpostusername, issue.lastpostusername) AS lastpostusername,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpostuserid, issue.lastpostuserid) AS lastpostuserid,
			IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastnoteid, issue.lastnoteid) AS lastnoteid
		";
	}
	else
	{
		$private_lastpost_join = '';
		$private_lastpost_fields = '';
	}

	return ($private_lastpost_join !== '');
}

/**
* Builds the left join and fields for private project lastpost detection for the
* specified user in the specified project.
*
* @param	array	Array of user info
* @param	integer	Project ID to build for
* @param	string	(out) Output of the private last post join (empty if unneeded)
* @param	string	(out) Fields for the last post information. No leading or trailing commas!
*
* @return	boolean	True if a join is needed
*/
function build_project_private_lastpost_sql_project(&$user, $projectid, &$private_lastpost_join, &$private_lastpost_fields)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$canviewprivate = array();

	foreach ($user['projectpermissions']["$projectid"] AS $type => $type_option)
	{
		// note: the way this is implemented, "others" means "any"
		if ($type_option['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
		{
			$canviewprivate[] = $type;
		}
	}

	if (!empty($canviewprivate))
	{
		$private_lastpost_join = "LEFT JOIN " . TABLE_PREFIX . "pt_projecttypeprivatelastpost AS projecttypeprivatelastpost ON
			(projecttypeprivatelastpost.projectid = projecttype.projectid
				AND projecttype.issuetypeid IN ('" . implode("', '", $canviewprivate) . "')
				AND projecttypeprivatelastpost.issuetypeid = projecttype.issuetypeid
				AND projecttypeprivatelastpost.lastpost >= projecttype.lastpost)
		";

		$private_lastpost_fields = "
			IF(projecttypeprivatelastpost.lastpost IS NOT NULL, projecttypeprivatelastpost.lastpost, projecttype.lastpost) AS lastpost,
			IF(projecttypeprivatelastpost.lastpostuserid IS NOT NULL, projecttypeprivatelastpost.lastpostuserid, projecttype.lastpostuserid) AS lastpostuserid,
			IF(projecttypeprivatelastpost.lastpostusername IS NOT NULL, projecttypeprivatelastpost.lastpostusername, projecttype.lastpostusername) AS lastpostusername,
			IF(projecttypeprivatelastpost.lastpostid IS NOT NULL, projecttypeprivatelastpost.lastpostid, projecttype.lastpostid) AS lastpostid,
			IF(projecttypeprivatelastpost.lastissueid IS NOT NULL, projecttypeprivatelastpost.lastissueid, projecttype.lastissueid) AS lastissueid,
			IF(projecttypeprivatelastpost.lastissuetitle IS NOT NULL, projecttypeprivatelastpost.lastissuetitle, projecttype.lastissuetitle) AS lastissuetitle
		";
	}
	else
	{
		$private_lastpost_join = '';
		$private_lastpost_fields = '';
	}

	return ($private_lastpost_join !== '');
}

/**
* Builds the left join and fields for private project lastpost detection for the
* specified user across all projects.
*
* @param	array	Array of user info
* @param	string	(out) Output of the private last post join (empty if unneeded)
* @param	string	(out) Fields for the last post information. No leading or trailing commas!
*
* @return	boolean	True if a join is needed
*/
function build_project_private_lastpost_sql_all(&$user, &$private_lastpost_join, &$private_lastpost_fields)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$canviewprivate_combo = array(); // [issuetype combination] => array([projectids])

	foreach ($user['projectpermissions'] AS $projectid => $projectpermissions)
	{
		$project_canviewprivate = array();

		foreach ($projectpermissions AS $type => $type_option)
		{
			// note: the way this is implemented, "others" implies "own"
			if ($type_option['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
			{
				$project_canviewprivate[] = $type;
			}
		}

		if ($project_canviewprivate)
		{
			$canviewprivate_combo["'" . implode("', '", $project_canviewprivate) . "'"][] = $projectid;
		}
	}

	$canviewprivate = array();
	foreach ($canviewprivate_combo AS $issuetypelist => $projects)
	{
		$canviewprivate[] = "(projecttype.issuetypeid IN ($issuetypelist) AND projecttype.projectid IN (" . implode(',', $projects) . "))";
	}

	if (!empty($canviewprivate))
	{
		$private_lastpost_join = "LEFT JOIN " . TABLE_PREFIX . "pt_projecttypeprivatelastpost AS projecttypeprivatelastpost ON
			(projecttypeprivatelastpost.projectid = projecttype.projectid
				AND projecttypeprivatelastpost.issuetypeid = projecttype.issuetypeid
				AND projecttypeprivatelastpost.lastpost >= projecttype.lastpost
				AND (" . implode(' OR ', $canviewprivate) . "))
		";

		$private_lastpost_fields = "
			IF(projecttypeprivatelastpost.lastpost IS NOT NULL, projecttypeprivatelastpost.lastpost, projecttype.lastpost) AS lastpost,
			IF(projecttypeprivatelastpost.lastpostuserid IS NOT NULL, projecttypeprivatelastpost.lastpostuserid, projecttype.lastpostuserid) AS lastpostuserid,
			IF(projecttypeprivatelastpost.lastpostusername IS NOT NULL, projecttypeprivatelastpost.lastpostusername, projecttype.lastpostusername) AS lastpostusername,
			IF(projecttypeprivatelastpost.lastpostid IS NOT NULL, projecttypeprivatelastpost.lastpostid, projecttype.lastpostid) AS lastpostid,
			IF(projecttypeprivatelastpost.lastissueid IS NOT NULL, projecttypeprivatelastpost.lastissueid, projecttype.lastissueid) AS lastissueid,
			IF(projecttypeprivatelastpost.lastissuetitle IS NOT NULL, projecttypeprivatelastpost.lastissuetitle, projecttype.lastissuetitle) AS lastissuetitle
		";
	}
	else
	{
		$private_lastpost_join = '';
		$private_lastpost_fields = '';
	}

	return ($private_lastpost_join !== '');
}

/**
* Fetches the clause that determines whether we can include private replies
* in the reply count.
*
* @param	array	Array of user info
* @param	integer	Optionally, filter to a single project
*
* @return	string	The clause if it is needed
*/
function fetch_private_replycount_clause(&$user, $only_projectid = 0)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	if ($only_projectid)
	{
		$perm_container = array($only_projectid => $user['projectpermissions']["$only_projectid"]);
	}
	else
	{
		$perm_container = $user['projectpermissions'];
	}

	$canviewprivate_combo = array(); // [issuetype combination] => array([projectids])

	foreach ($perm_container AS $projectid => $projectpermissions)
	{
		$project_canviewprivate = array();

		foreach ($projectpermissions AS $type => $type_option)
		{
			// note: the way this is implemented, "others" implies "own"
			if ($type_option['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
			{
				$project_canviewprivate[] = $type;
			}
		}

		if ($project_canviewprivate)
		{
			$canviewprivate_combo["'" . implode("', '", $project_canviewprivate) . "'"][] = $projectid;
		}
	}

	$canviewprivate = array();
	foreach ($canviewprivate_combo AS $issuetypelist => $projects)
	{
		$canviewprivate[] = "(issue.issuetypeid IN ($issuetypelist) AND issue.projectid IN (" . implode(',', $projects) . "))";
	}

	if (!empty($canviewprivate))
	{
		return "IF(" . implode(' OR ', $canviewprivate) . ", issue.replycount + issue.privatecount, issue.replycount)";
	}
	else
	{
		return '';
	}
}

/**
* Prepare permissions related to posting/editing an issue based on the state
* of the issue and the user's permissions.
*
* @param	array	Issue information
* @param	array	Issue permissions
*
* @return	array	Effective permissions
*/
function prepare_issue_posting_pemissions($issue, $issueperms)
{
	global $vbulletin;

	$return = array(
		'can_assign_self' => false,
		'assign_checkbox' => false,
		'assign_checkbox_checked' => '',
		'assign_dropdown' => false,
		'status_edit' => false,
		'tags_edit' => false,
		'private_edit' => false,
		'issue_edit' => false,
		'milestone_edit' => false,
		'issue_close' => false,
		'can_custom_tag' => false,
		'can_reply' => false
	);

	if (is_issue_closed($issue, $issueperms))
	{
		return $return;
	}

	$return['can_assign_self'] = (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canassigned']) AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canassignedit']));

	// can the user edit his/her own assignment only?
	$return['assign_checkbox'] = ($return['can_assign_self'] AND !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canassigneditothers']));
	$return['assign_checkbox_checked'] = '';
	$return['assign_dropdown'] = (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canassignedit']) AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canassigneditothers']));

	if ($return['can_assign_self'])
	{
		// we can assign our self to this issue, so it's like posting to a closed thread -- don't make them open it to change it
		$return['status_edit'] = (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canstatusassigned']) OR ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canstatusunassigned']));
		$return['tags_edit'] = (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['cantagsassigned']) OR ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['cantagsunassigned']));
		$return['assign_checkbox_checked'] = (!empty($issue['isassigned']) ? ' checked="checked"' : '');
	}
	else if (!empty($issue['isassigned']))
	{
		// assigned to the issue
		$return['status_edit'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canstatusassigned']);
		$return['tags_edit'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['cantagsassigned']);
	}
	else
	{
		// unassigned
		$return['status_edit'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canstatusunassigned']);
		$return['tags_edit'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['cantagsunassigned']);
	}

	if ($issue['issueid'] == 0)
	{
		$return['private_edit'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancreateprivate']);
	}
	else
	{
		$return['private_edit'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditprivate']);
	}

	$return['issue_edit'] = ($vbulletin->userinfo['userid']
		AND ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditissue'])
		AND ($issue['submituserid'] == $vbulletin->userinfo['userid']
			OR ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditissueothers'])
		)
	);

	$return['milestone_edit'] = (
		$issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone']
		AND $issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canchangemilestone']
	);

	$return['issue_close'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancloseissue']);

	$return['can_custom_tag'] = ($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['cancustomtag']);

	$return['can_reply'] = (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canreply']) AND
		($issue['submituserid'] == $vbulletin->userinfo['userid'] OR ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canreplyothers'])));

	return $return;
}

/**
* Determines if the note can be edited based on the issue and permissions.
*
* @param	array	Array of issue info
* @param	array	Array of note info
* @param	array	Array of issue permissions
*
* @return	boolean
*/
function can_edit_issue_note($issue, $issuenote, $issueperms)
{
	global $vbulletin;

	if (is_issue_closed($issue, $issueperms))
	{
		return false;
	}

	return ($vbulletin->userinfo['userid']
		AND ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditnote'])
		AND (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditnoteothers']) OR $issuenote['userid'] == $vbulletin->userinfo['userid'])
	);
}

/**
* Determines if an issue is closed and not openable. If it's closed but the
* user can open it, this will return false (acting as if it's not closed).
*
* @param	array	Array of issue info
* @param	array	Array of issue permissions
*
* @return	boolean
*/
function is_issue_closed($issue, $issueperms)
{
	global $vbulletin;
	return ($issue['state'] == 'closed' AND !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancloseissue']));
}

/**
* Builds an issue bit for an issue list
*
* @param	array	Issue information
* @param	array	Project information
* @param	array	Array of issue permissions
*
* @return	string	Issue bit HTML
*/
function build_issue_bit($issue, $project, $issueperms)
{
	global $vbulletin, $vbphrase, $show, $template_hook;

	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);
	$show['edit_issue'] = $posting_perms['issue_edit'];
	$show['status_edit'] = $posting_perms['status_edit'];

	$issue = prepare_issue($issue);

	$template_name = ($issue['visible'] == 'deleted' ? 'pt_issuebit_deleted' : 'pt_issuebit');

	($hook = vBulletinHook::fetch_hook('project_issuebit')) ? eval($hook) : false;

	$templater = vB_Template::create($template_name);
		$templater->register('issue', $issue);
	return $templater->render();
}

/**
* Builds the options for a <select> box listing issue statuses
*
* @param	array	List of statuses
* @param	integer	Selected status ID
* @param	array	List of IDs to skip
*
* @return	string	Options HTML
*/
function build_issuestatus_select($statuses, $selectedid = 0, $skipids = array())
{
	global $vbulletin, $vbphrase, $show;

	$options = '';
	$optionclass = '';
	foreach ($statuses AS $status)
	{
		if (in_array($status['issuestatusid'], $skipids))
		{
			continue;
		}

		$optionvalue = $status['issuestatusid'];
		$optiontitle = $vbphrase["issuestatus$status[issuestatusid]"];
		$optionselected = ($selectedid == $status['issuestatusid'] ? ' selected="selected"' : '');
		$options .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
	}

	return $options;
}

/**
* Build <options> for issue type
*
* @param	array	Project permissions
* @param	array	Array of types
* @param	string	Selected type ID
*
* @return	string	Options HTML
*/
function build_issuetype_select($projectperms, $types, $selectedid = '')
{
	global $vbulletin, $vbphrase, $show;

	$options = '';
	$optionclass = '';
	foreach ($types AS $type)
	{
		if (!($projectperms["$type"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			continue;
		}

		$optionvalue = $type;
		$optiontitle = $vbphrase["issuetype_{$type}_singular"];
		$optionselected = ($selectedid == $type ? ' selected="selected"' : '');
		$options .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
	}

	return $options;
}

/**
* Prepare a list of subscribed reports for the specified user.
*
* @param	integer	Project to limit to (0 if none)
* @param	integer	User ID to fet (-1 means browsing user)
*
* @return	string	Report menu bits
*/
function prepare_subscribed_reports($projectid_limit = 0, $userid = -1)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	else if ($userid == 0)
	{
		return '';
	}
	else
	{
		$userid = intval($userid);
	}

	$projectid_limit = intval($projectid_limit);

	$reportbits = '';

	$subscribed_reports = $db->query_read_slave("
		SELECT issuereport.issuereportid, issuereport.title, issuereport.projectlist, issuereport.issuetypelist
		FROM " . TABLE_PREFIX . "pt_issuereportsubscribe AS issuereportsubscribe
		INNER JOIN " . TABLE_PREFIX . "pt_issuereport AS issuereport ON
			(issuereport.issuereportid = issuereportsubscribe.issuereportid)
		WHERE issuereportsubscribe.userid = $userid
			" . ($projectid_limit ? "AND issuereport.projectlist LIKE '%$projectid_limit%'" : '') . "
			AND (issuereport.public = 1 OR (issuereport.public = 0 AND issuereport.userid = $userid))
		ORDER BY issuereport.title
	");
	while ($report = $db->fetch_array($subscribed_reports))
	{
		if ($projectid_limit)
		{
			$projects = explode(',', $report['projectlist']);
			if (in_array($projectid_limit, $projects))
			{
				$templater = vB_Template::create('pt_reportmenubit');
					$templater->register('report', $report);
				$reportbits .= $templater->render();
			}
		}
		else
		{
			$templater = vB_Template::create('pt_reportmenubit');
				$templater->register('report', $report);
			$reportbits .= $templater->render();
		}
	}

	return $reportbits;
}

/**
* Fetch an array of viewable note types for the selected issue
*
* @param	array	Permissions for selected issue
* @param	string	(Output) SQL for how to fetch private notes
*
* @return	array	Array of note types that can be seen
*/
function fetch_viewable_note_types($issueperms, &$private_text)
{
	global $vbulletin;

	$private_text = '';

	$viewable_note_types = array("'visible'");
	if (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown']) AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers']))
	{
		$viewable_note_types[] = "'private'";
	}
	else
	{
		if ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateown'])
		{
			$private_text = " OR (issuenote.visible = 'private' AND issuenote.userid = " . $vbulletin->userinfo['userid'] . ")";
		}
		else if ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
		{
			$private_text = " OR (issuenote.visible = 'private' AND issuenote.userid <> " . $vbulletin->userinfo['userid'] . ")";
		}
	}

	$can_see_deleted = false;
	if ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage'])
	{
		$viewable_note_types[] = "'moderation'";
		$viewable_note_types[] = "'deleted'";
		$can_see_deleted = true;
	}

	return $viewable_note_types;
}

/**
* Build array of SQL for a where clause to limit matched results to the user's permissions.
*
* @param	array	User information
*
* @return	array	[projectid] => SQL for project only
*/
function build_issuenote_permissions_query(&$user)
{
	global $vbulletin;

	if (!isset($user['projectpermissions']))
	{
		prepare_project_permissions($user);
	}

	$clause = array();

	foreach ($user['projectpermissions'] AS $projectid => $types)
	{
		$type_options = array();

		foreach ($types AS $typeid => $perms)
		{
			$perms = fetch_viewable_note_types($perms, $private_text);
			$type_options["$typeid"] = "(issue.issuetypeid = '" . $typeid . "' AND (issuenote.visible IN (" . implode(", ", $perms) . ")$private_text))";
		}

		if ($type_options)
		{
			$clause["$projectid"] = "(issue.projectid = " . $projectid . " AND (" . implode(" OR ", $type_options) . "))";
		}
	}

	return $clause;
}

/**
* Marks a issue as read using the appropriate method.
*
* @param	array	Array of data for the issue being marked
* @param	integer	Unix timestamp that the issue is being marked read
*/
function mark_issue_read($issueinfo, $time)
{
	global $vbulletin, $db;

	$userid = $vbulletin->userinfo['userid'];
	$time = intval($time);

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "pt_issueread
				(issueid, userid, readtime)
			VALUES
				($issueinfo[issueid], " . $vbulletin->userinfo['userid'] . ", $time)
		");

		// in case of automatic project marking
		if ($vbulletin->options['threadmarking'] == 2)
		{
			$perms_sql = build_issue_permissions_sql($vbulletin->userinfo);
			if (!empty($perms_sql["$issueinfo[projectid]"]["$issueinfo[issuetypeid]"]))
			{
				// TODO: be aware of private replies
				$unread = $db->query_first("
					SELECT COUNT(*) AS count
					FROM " . TABLE_PREFIX . "pt_issue AS issue
					LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
					WHERE issue.projectid = $issueinfo[projectid]
						AND " . $perms_sql["$issueinfo[projectid]"]["$issueinfo[issuetypeid]"] . "
						AND issue.lastpost > " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . "
						AND issue.lastpost > IF(issueread.readtime IS NOT NULL, issueread.readtime, " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . ")
						AND issue.lastpost > IF(projectread.readtime IS NOT NULL, projectread.readtime, " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . ")
				");

				if ($unread['count'] == 0)
				{
					mark_project_read($issueinfo['projectid'], $issueinfo['issuetypeid'], TIMENOW);
				}
			}
		}
	}
	else
	{
		set_bbarray_cookie('issue_lastview', $issueinfo['issueid'], $time);
	}
}

/**
* Marks a issue as read using the appropriate method.
*
* @param	integer	Project id for the project being marked
* @param	string	The issue type that is being marked as read
* @param	integer	Unix timestamp that the project is being marked read
*/
function mark_project_read($projectid, $issuetypeid, $time)
{
	global $vbulletin, $db;

	$projectid = intval($projectid);
	$issuetypeid = $db->escape_string($issuetypeid);
	$time = intval($time);

	if (!$projectid)
	{
		// sanity check -- wouldn't work anyway
		return false;
	}

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "pt_projectread
				(projectid, issuetypeid, userid, readtime)
			VALUES
				($projectid, '$issuetypeid', " . $vbulletin->userinfo['userid'] . ", $time)
		");
	}
	else
	{
		set_bbarray_cookie('project_lastview', $projectid . $issuetypeid, $time);
	}
}

/**
* Return the current issue_lastview for the issue using the appropriate method.
*
* @param	array	Array of data for the issue
*
* @return	integer	unix timestamp as issue_lastview
*/
function issue_lastview($issue)
{
	global $vbulletin;

	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
	{
		$issueview = max(
			$issue['issueread'],
			$issue['projectread'],
			TIMENOW - ($vbulletin->options['markinglimit'] * 86400)
		);
	}
	else
	{
		$issueview = max(
			intval(fetch_bbarray_cookie('issue_lastview', $issue['issueid'])),
			intval(fetch_bbarray_cookie('project_lastview', $issue['projectid'] . $issue['issuetypeid'])),
			$vbulletin->userinfo['lastvisit']
		);
	}

	return intval($issueview);
}

/**
* Fetches the list of assignable users for a particular project
* and formats them into <option> tags for a select.
*
* @param	integer	Project ID
*
* @return	string	Outputable HTML
*/
function fetch_assignable_users_select($projectid)
{
	global $vbulletin, $vbphrase, $show;

	if (empty($vbulletin->pt_assignable["$projectid"]))
	{
		return '';
	}

	$assignable_users = '';
	$assignable = array();

	// loop through the array once to remove duplicates
	foreach ($vbulletin->pt_assignable["$projectid"] AS $assign)
	{
		$assignable += $assign;
	}

	foreach ($assignable AS $optionvalue => $optiontitle)
	{
		$assignable_users .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
	}

	return $assignable_users;
}

/**
* Fetches the HTML for an issue status select used in a project-specific
* search box. Contains options/optgroup HTML.
*
* @param	array	Array of project permissions for this project
*
* @return	strgin	Outputtable HTML
*/
function fetch_issue_status_search_select($projectperms)
{
	global $vbulletin, $vbphrase;

	$status_options = '';
	foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
	{
		if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
			OR !($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['cansearch']))
		{
			continue;
		}

		$optgroup_options = build_issuestatus_select($typeinfo['statuses'], $issue['issuestatusid']);
		$status_options .= "<optgroup label=\"" . $vbphrase["issuetype_{$issuetypeid}_singular"] . "\" id=\"issuestatus_group_$issuetypeid\">$optgroup_options</optgroup>";
	}

	return $status_options;
}
