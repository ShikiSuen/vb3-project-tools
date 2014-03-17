<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Resolve the grouping of search results for displaying them.
*
* @param	string	Grouping method
* @param	string	(Output) Column to pull the group title from
* @param	string	(Output) Extra join for the title to be retrieved properly
*
* @return	boolean	True if the grouping is applied
*/
function resolve_grouping($groupby, &$group_title_col, &$group_title_join)
{
	global $vbulletin, $db, $vbphrase;

	if (!$groupby)
	{
		return false;
	}

	$group_title_col = '';
	$group_title_join = '';
	$found = true;

	switch ($groupby)
	{
		case 'assignment':
			$group_title_col = 'user.username';
			$group_title_join = "INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = CAST(issuesearchresult.groupid AS UNSIGNED))";
			break;

		case 'tag':
			$group_title_col = 'tag.tagtext';
			$group_title_join = "INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (tag.tagid = CAST(issuesearchresult.groupid AS UNSIGNED))";
			break;

		case 'projectid':
			$group_title_col = 'project.title_clean';
			$group_title_join = "INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = CAST(issuesearchresult.groupid AS UNSIGNED))";
			break;

		case 'projectcategoryid':
			$group_title_col = "IF(issuesearchresult.groupid = '0', '" . $db->escape_string($vbphrase['unknown']) . "', projectcategory.title)";
			$group_title_join = "LEFT JOIN " . TABLE_PREFIX . "pt_projectcategory AS projectcategory ON (projectcategory.projectcategoryid = CAST(issuesearchresult.groupid AS UNSIGNED))";
			break;

		case 'issuetypeid':
			$group_title_col = "1 AS phraseme, 'issuetype_*_singular'";
			$group_title_join = '';
			break;

		case 'issuestatusid':
			$group_title_col = "1 AS phraseme, 'issuestatus*'";
			$group_title_join = '';
			break;

		case 'appliesversionid':
			$group_title_col = "
				IF(issue.appliesversionid = 0, '" . $db->escape_string($vbphrase['unknown']) . "', projectversion.versionname)
			";
			$group_title_join = "
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesearchresult.issueid)
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS projectversion ON (projectversion.projectversionid = CAST(issuesearchresult.groupid AS UNSIGNED))
			";
			break;

		case 'addressedversionid':
			$group_title_col = "
				IF(issue.isaddressed = 0, '" . $db->escape_string($vbphrase['unaddressed']) . "',
					IF(issue.addressedversionid = 0, '" . $db->escape_string($vbphrase['next_release']) . "', projectversion.versionname)
				)
			";
			$group_title_join = "
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesearchresult.issueid)
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS projectversion ON (projectversion.projectversionid = CAST(issuesearchresult.groupid AS UNSIGNED))
			";
			break;

		default:
			$found = false;
			($hook = vBulletinHook::fetch_hook('projectsearch_results_grouping')) ? eval($hook) : false;
	}

	return $found;
}

/**
* Check whether this search is flooding
*/
function check_pt_search_floodcheck()
{
	global $vbulletin, $db;

	if ($prevsearch = $db->query_first("
		SELECT issuesearchid, dateline
		FROM " . TABLE_PREFIX . "pt_issuesearch AS issuesearch
		WHERE " . (!$vbulletin->userinfo['userid'] ?
			"ipaddress = '" . $db->escape_string(IPADDRESS) . "'" :
			"userid = " . $vbulletin->userinfo['userid']) . "
		ORDER BY dateline DESC LIMIT 1
	"))
	{
		if ($vbulletin->options['searchfloodtime'] > 0)
		{
			$timepassed = TIMENOW - $prevsearch['dateline'];
			$is_special_user = (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate());

			if ($timepassed < $vbulletin->options['searchfloodtime'] AND !$is_special_user)
			{
				standard_error(fetch_error('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed)));
			}
		}
	}
}

/**
* Prepare the version fields for display
*
* @param	string	(Output) Applies version options
* @param	string	(Output) Addressed version options
* @param	array	Array of project names (key: projectid, value: title)
*/
function fetch_pt_search_versions(&$appliesversion_options, &$addressedversion_options, $project_names)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	$version_groups = array();
	$version_group_names = array();
	$version_query = $db->query_read("
		SELECT projectversiongroup.projectversiongroupid, projectversion.projectversionid,
			project.title_clean, project.projectid
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
		INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON
			(project.projectid = projectversiongroup.projectid)
		ORDER BY project.displayorder, projectversion.effectiveorder DESC
	");
	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[projectid]"]["$version[projectversiongroupid]"]["$version[projectversionid]"] = $vbphrase['version' . $version['projectversionid'] . ''];
		$version_group_names["$version[projectversiongroupid]"] = $vbphrase['versiongroup' . $version['projectversiongroupid'] . ''];
	}

	$appliesversion_options = '';
	$optionclass = '';
	$optionselected = '';
	foreach ($version_groups AS $projectid => $groups)
	{
		if (!isset($project_names["$projectid"]))
		{
			continue;
		}

		$project_options = '';

		foreach ($groups AS $versiongroupid => $versions)
		{
			$groupname = $version_group_names["$versiongroupid"];

			$optgroup_options = '';
			$optionname = 'appliesversion[]';
			foreach ($versions AS $optionvalue => $optiontitle)
			{
				$optionid = "version_{$optionvalue}_appliesversions";
				$templater = vB_Template::create('pt_checkbox_option');
					$templater->register('optionchecked', $optionchecked);
					$templater->register('optionid', $optionid);
					$templater->register('optionname', $optionname);
					$templater->register('optiontitle', $optiontitle);
					$templater->register('optionvalue', $optionvalue);
				$optgroup_options .= $templater->render();
			}


			$show['optgroup_checkbox'] = true;
			$optgroup_value = $versiongroupid;
			$optgroup_name = 'appliesgroup[]';
			$optgroup_label = fetch_trimmed_title($groupname, 40);
			$optgroup_id = "versiongroup_{$versiongroupid}_appliesversions";
			$templater = vB_Template::create('pt_checkbox_optgroup');
				$templater->register('optgroup_id', $optgroup_id);
				$templater->register('optgroup_label', $optgroup_label);
				$templater->register('optgroup_name', $optgroup_name);
				$templater->register('optgroup_options', $optgroup_options);
				$templater->register('optgroup_value', $optgroup_value);
				$templater->register('optionchecked', $optionchecked);
			$project_options .= $templater->render();
		}

		$optgroup_options = $project_options;
		$optgroup_value = '';
		$optgroup_name = '';
		$optgroup_label = $project_names["$projectid"];
		$optgroup_extra = '';
		$optgroup_id = "project_{$projectid}_appliesversions";
		$show['optgroup_checkbox'] = false;
		$templater = vB_Template::create('pt_checkbox_optgroup');
			$templater->register('optgroup_id', $optgroup_id);
			$templater->register('optgroup_label', $optgroup_label);
			$templater->register('optgroup_name', $optgroup_name);
			$templater->register('optgroup_options', $optgroup_options);
			$templater->register('optgroup_value', $optgroup_value);
			$templater->register('optionchecked', $optionchecked);
		$appliesversion_options .= $templater->render();
	}

	$addressedversion_options = str_replace(
		array('appliesversion[]', 'appliesgroup[]', '_appliesversions'),
		array('addressedversion[]', 'addressedgroup[]', '_addressedversions'),
		$appliesversion_options
	);
}

/**
* Prepare the project priorities for display.
*
* @param	array	Array of project names
*
* @return	string	Priorities prepared
*/
function fetch_pt_search_priorities($project_names)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	$priorities = array();
	$priority_query = $db->query_read("
		SELECT projectpriority.projectpriorityid, project.title_clean, project.projectid
		FROM " . TABLE_PREFIX . "pt_projectpriority AS projectpriority
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON
			(project.projectid = projectpriority.projectid)
		ORDER BY project.displayorder, projectpriority.displayorder
	");
	while ($priority = $db->fetch_array($priority_query))
	{
		$priorities["$priority[projectid]"]["$priority[projectpriorityid]"] = $vbphrase['priority' . $priority['projectpriorityid']];
	}

	$priority_options = '';
	$optionclass = '';
	$optionselected = '';
	foreach ($priorities AS $projectid => $project_priorities)
	{
		if (!isset($project_names["$projectid"]))
		{
			continue;
		}

		$optgroup_options = '';
		foreach ($project_priorities AS $optionvalue => $optiontitle)
		{
			$optionname = 'projectpriorityid[]';
			$optionid = "projectpriority_$optionvalue";
			$templater = vB_Template::create('pt_checkbox_option');
				$templater->register('optionchecked', $optionchecked);
				$templater->register('optionid', $optionid);
				$templater->register('optionname', $optionname);
				$templater->register('optiontitle', $optiontitle);
				$templater->register('optionvalue', $optionvalue);
			$optgroup_options .= $templater->render();
		}

		$show['optgroup_checkbox'] = false;
		$optgroup_value = '';
		$optgroup_name = '';
		$optgroup_id = "project_{$projectid}_priorities";
		$optgroup_label = $project_names["$projectid"];
		$optgroup_extra = " id=\"projectpriorityid,$projectid\"";
		$templater = vB_Template::create('pt_checkbox_optgroup');
			$templater->register('optgroup_id', $optgroup_id);
			$templater->register('optgroup_label', $optgroup_label);
			$templater->register('optgroup_name', $optgroup_name);
			$templater->register('optgroup_options', $optgroup_options);
			$templater->register('optgroup_value', $optgroup_value);
			$templater->register('optionchecked', $optionchecked);
		$priority_options .= $templater->render();
	}

	return $priority_options;
}

/**
* Prepare the project categories for display.
*
* @param	array	Array of project names
*
* @return	string	Categories prepared
*/
function fetch_pt_search_categories($project_names)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	$categories = array();
	$category_query = $db->query_read("
		SELECT projectcategory.projectcategoryid, project.title_clean, project.projectid
		FROM " . TABLE_PREFIX . "pt_projectcategory AS projectcategory
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON
			(project.projectid = projectcategory.projectid)
		ORDER BY project.displayorder, projectcategory.displayorder
	");
	while ($category = $db->fetch_array($category_query))
	{
		$categories["$category[projectid]"]["$category[projectcategoryid]"] = $vbphrase['category' . $category['projectcategoryid']];
	}

	$category_options = '';
	$optionclass = '';
	$optionselected = '';
	foreach ($categories AS $projectid => $project_categories)
	{
		if (!isset($project_names["$projectid"]))
		{
			continue;
		}

		$optgroup_options = '';
		foreach ($project_categories AS $optionvalue => $optiontitle)
		{
			$optionname = 'projectcategoryid[]';
			$optionid = "projectcategory_$optionvalue";
			$templater = vB_Template::create('pt_checkbox_option');
				$templater->register('optionchecked', $optionchecked);
				$templater->register('optionid', $optionid);
				$templater->register('optionname', $optionname);
				$templater->register('optiontitle', $optiontitle);
				$templater->register('optionvalue', $optionvalue);
			$optgroup_options .= $templater->render();
		}

		$show['optgroup_checkbox'] = false;
		$optgroup_value = '';
		$optgroup_name = '';
		$optgroup_id = "project_{$projectid}_categories";
		$optgroup_label = $project_names["$projectid"];
		$optgroup_extra = " id=\"projectcategoryid,$projectid\"";
		$templater = vB_Template::create('pt_checkbox_optgroup');
			$templater->register('optgroup_id', $optgroup_id);
			$templater->register('optgroup_label', $optgroup_label);
			$templater->register('optgroup_name', $optgroup_name);
			$templater->register('optgroup_options', $optgroup_options);
			$templater->register('optgroup_value', $optgroup_value);
			$templater->register('optionchecked', $optionchecked);
		$category_options .= $templater->render();
	}

	return $category_options;
}

/**
* Prepare the issue status options for display
*
* @param	array	Array of statuses
* @param	integer	Status to select
* @param	array	Array of status ids to skip
*
* @return	string	Prepared options
*/
function fetch_pt_search_issuestatus_options($statuses, $selectedid = 0, $skipids = array())
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

		$optionname = 'issuestatusid[]';
		$optionvalue = $status['issuestatusid'];
		$optiontitle = $vbphrase["issuestatus$status[issuestatusid]"];
		$optionid = "issuestatus_$optionvalue";
		$optionselected = ($selectedid == $status['issuestatusid'] ? ' selected="selected"' : '');
		$templater = vB_Template::create('pt_checkbox_option');
			$templater->register('optionchecked', $optionchecked);
			$templater->register('optionid', $optionid);
			$templater->register('optionname', $optionname);
			$templater->register('optiontitle', $optiontitle);
			$templater->register('optionvalue', $optionvalue);
		$options .= $templater->render();
	}

	return $options;
}

/**
* Prepare auxiliary search cases, changing the meaning of fields
* based on other fields.
*
* @param	array	Array of source input (probably GPC). Needs both aux and non-aux fields
*/
function process_aux_search_cases(&$source_array)
{
	global $vbulletin, $db;

	// choose where to search for text
	switch ($source_array['textlocation'])
	{
		case 'issue': // issue info only (title, summary)
			$source_array['issuetext'] = $source_array['text'];
			$source_array['text'] = '';
			break;

		case 'first': // first post only
			$source_array['firsttext'] = $source_array['text'];
			$source_array['text'] = '';
			break;

		default: // anywhere, leave as is
	}

	// priority
	switch ($source_array['priority_type'])
	{
		case 'gteq':
			$source_array['priority_gteq'] = $source_array['priority'];
			$source_array['priority'] = 0;
			break;

		case 'lteq':
			$source_array['priority_lteq'] = $source_array['priority'];
			$source_array['priority'] = 0;
			break;
	}

	// search date
	switch ($source_array['searchdate_type'])
	{
		case 'gteq':
			$source_array['searchdate_gteq'] = $source_array['searchdate'];
			$source_array['searchdate'] = 0;
			break;

		case 'lteq':
			$source_array['searchdate_lteq'] = $source_array['searchdate'];
			$source_array['searchdate'] = 0;
			break;
	}

	// reply count
	switch ($source_array['replycount_type'])
	{
		case 'gteq':
			$source_array['replycount_gteq'] = $source_array['replycount'];
			$source_array['replycount_lteq'] = -1;
			$source_array['replycount'] = 0;
			break;

		case 'lteq':
			$source_array['replycount_lteq'] = $source_array['replycount'];
			$source_array['replycount'] = 0;
			break;

		default:
			$source_array['replycount_lteq'] = -1;
			break;
	}

	// vote count (both types)
	switch ($source_array['votecount_type'])
	{
		case 'gteq':
			if ($source_array['votecount_posneg'] == 'positive')
			{
				$source_array['votecount_pos_gteq'] = $source_array['votecount'];
			}
			else
			{
				$source_array['votecount_neg_gteq'] = $source_array['votecount'];
			}
			$source_array['votecount_pos_lteq'] = -1;
			$source_array['votecount_neg_lteq'] = -1;
			$source_array['votecount'] = 0;
			break;

		case 'lteq':
			if ($source_array['votecount_posneg'] == 'positive')
			{
				$source_array['votecount_pos_lteq'] = $source_array['votecount'];
				$source_array['votecount_neg_lteq'] = -1;
			}
			else
			{
				$source_array['votecount_neg_lteq'] = $source_array['votecount'];
				$source_array['votecount_pos_lteq'] = -1;
			}
			$source_array['votecount'] = 0;
			break;

		default:
			$source_array['votecount_pos_lteq'] = -1;
			$source_array['votecount_neg_lteq'] = -1;
			break;
	}

	// posted by
	if ($source_array['user'] AND $source_array['userissuesonly'])
	{
		$source_array['user_issue'] = $source_array['user'];
		$source_array['user'] = '';
	}

	// type and status handling
	$source_array['issuetypeid'] = array_unique($source_array['issuetypeid']);
	if ($source_array['issuetypeid'] AND $source_array['issuestatusid'])
	{
		$sel_statuses = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuestatusid IN (" . implode(',', $source_array['issuestatusid']) . ")
		");
		while ($status = $db->fetch_array($sel_statuses))
		{
			if (($key = array_search($status['issuetypeid'], $source_array['issuetypeid'])) !== false)
			{
				// we selected a status for a type that we selected - ignore the type and just grab the status
				unset($source_array['issuetypeid']["$key"]);
			}
		}

		// now mix the 2 (so we can OR them together), and unset the individual options
		$source_array['typestatusmix'] = array(
			'issuetypeid' => $source_array['issuetypeid'],
			'issuestatusid' => $source_array['issuestatusid']
		);
		$source_array['issuetypeid'] = array();
		$source_array['issuestatusid'] = array();
	}

	$source_array['projectid'] = array_unique($source_array['projectid']);

	// categories
	if ($source_array['projectid'] AND $source_array['projectcategoryid'])
	{
		$categories = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectcategoryid IN (" . implode(',', $source_array['projectcategoryid']) . ")
				AND projectid NOT IN (" . implode(',', $source_array['projectid']) . ")
		");
		while ($category = $db->fetch_array($categories))
		{
			// we selected a category but not that project, so ignore it...
			if (($key = array_search($category['projectcategoryid'], $source_array['projectcategoryid'])) !== false)
			{
				unset($source_array['projectcategoryid']["$key"]);
			}
		}
	}

	$source_array['appliesgroup'] = array_unique($source_array['appliesgroup']);
	$source_array['addressedgroup'] = array_unique($source_array['addressedgroup']);

	if ($source_array['appliesversion'])
	{
		$versions = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid IN (" . implode(',', $source_array['appliesversion']) . ")
		");
		while ($version = $db->fetch_array($versions))
		{
			if ($source_array['projectid'] AND !in_array($version['projectid'], $source_array['projectid']))
			{
				// we selected a version but not that project, so ignore it...
				if (($key = array_search($version['projectversionid'], $source_array['appliesversion'])) !== false)
				{
					unset($source_array['appliesversion']["$key"]);
				}
			}

			// regardless of whether we didn't match the project, get rid of the group selector - we have a subversion selected
			if (($key = array_search($version['projectversiongroupid'], $source_array['appliesgroup'])) !== false)
			{
				unset($source_array['appliesgroup']["$key"]);
			}
		}
	}

	if ($source_array['addressedversion'])
	{
		$versions = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid IN (" . implode(',', $source_array['addressedversion']) . ")
		");
		while ($version = $db->fetch_array($versions))
		{
			if ($source_array['projectid'] AND !in_array($version['projectid'], $source_array['projectid']))
			{
				// we selected a version but not that project, so ignore it...
				if (($key = array_search($version['projectversionid'], $source_array['addressedversion'])) !== false)
				{
					unset($source_array['addressedversion']["$key"]);
				}
			}

			// regardless of whether we didn't match the project, get rid of the group selector - we have a subversion selected
			if (($key = array_search($version['projectversiongroupid'], $source_array['addressedgroup'])) !== false)
			{
				unset($source_array['addressedgroup']["$key"]);
			}
		}
	}


	if ($source_array['projectid'] AND ($source_array['appliesgroup'] OR $source_array['addressedgroup']))
	{
		$groups = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE (projectversiongroupid IN (" . implode(',', array(0) + $source_array['appliesgroup']) . ")
				OR projectversiongroupid IN (" . implode(',', array(0) + $source_array['addressedgroup']) . "))
				AND projectid NOT IN (" . implode(',', $source_array['projectid']) . ")
		");
		while ($group = $db->fetch_array($groups))
		{
			// we selected a group but not that project, so ignore it...
			if (($key = array_search($group['projectversiongroupid'], $source_array['appliesgroup'])) !== false)
			{
				unset($source_array['appliesgroup']["$key"]);
			}
			if (($key = array_search($group['projectversiongroupid'], $source_array['addressedgroup'])) !== false)
			{
				unset($source_array['addressedgroup']["$key"]);
			}
		}
	}

	if (!$source_array['appliesmix'])
	{
		$source_array['appliesmix'] = array(
			'appliesversion' => $source_array['appliesversion'],
			'appliesgroup' => $source_array['appliesgroup']
		);
		$source_array['appliesversion'] = array();
		$source_array['appliesgroup'] = array();
	}

	if (!$source_array['addressedmix'])
	{
		$source_array['addressedmix'] = array(
			'addressedversion' => $source_array['addressedversion'],
			'addressedgroup' => $source_array['addressedgroup']
		);
		$source_array['addressedversion'] = array();
		$source_array['addressedgroup'] = array();
	}

	($hook = vBulletinHook::fetch_hook('projectsearch_dosearch_aux')) ? eval($hook) : false;
}

/**
* Handle search errors
*
* @param	array	Array of errors
*/
function handle_pt_search_errors($errors)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	if (sizeof($errors) == 0)
	{
		return;
	}
	else if (sizeof($errors) > 1)
	{
		$errorbits = '';
		foreach ($errors AS $error)
		{
			$templater = vB_Template::create('pt_searcherrorbit');
				$templater->register('error', $error);
			$errorbits .= $templater->render();
		}
		$templater = vB_Template::create('pt_searcherror');
			$templater->register('errorbits', $errorbits);
		$searcherror = $templater->render();
		standard_error($searcherror);
	}
	else
	{
		standard_error(reset($errors));
	}
}

/**
* Verify that the selected search is accessible by the selecting user
*
* @param	integer	Search ID
* @param	integer	Selecting user (-1 is browsing user)
*
* @return	array	Search info if no error
*/
function verify_pt_search($searchid, $userid = -1)
{
	global $vbulletin, $vbphrase;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	$userid = intval($userid);

	$search = $vbulletin->db->query_first("
		SELECT issuesearch.*, issuereport.title AS reporttitle, issuereport.public AS reportpublic
		FROM " . TABLE_PREFIX . "pt_issuesearch AS issuesearch
		LEFT JOIN " . TABLE_PREFIX . "pt_issuereport AS issuereport ON (issuesearch.issuereportid = issuereport.issuereportid)
		WHERE issuesearch.issuesearchid = " . intval($searchid) . "
			AND issuesearch.userid IN ($userid, 0)
			AND issuesearch.dateline >= " . (TIMENOW - 3600) . "
			AND issuesearch.completed = 1
	");
	if (!$search)
	{
		standard_error(fetch_error('invalidid', $vbphrase['search'], $vbulletin->options['contactuslink']));
	}

	return $search;
}

/**
* Prepare searches for group filters, group titles, etc
*
* @param	array	(In/Out) Search info
* @param	string	(In/Out) Selected group
* @param	integer	(Output) Per page setting
*
* @return	array	Group information
*/
function prepare_group_filter(&$search, &$selected_group, &$perpage)
{
	global $vbulletin, $db;

	$groups = array();
	if ($search['groupby'] AND resolve_grouping($search['groupby'], $group_title_col, $group_title_join))
	{
		$group_query = $db->query_read("
			SELECT issuesearchresult.groupid, $group_title_col AS grouptitle, COUNT(*) AS count
			FROM " . TABLE_PREFIX . "pt_issuesearchresult AS issuesearchresult
			$group_title_join
			WHERE issuesearchresult.issuesearchid = $search[issuesearchid]
			GROUP BY issuesearchresult.groupid
		");
		while ($group = $db->fetch_array($group_query))
		{
			$groups["$group[groupid]"] = $group;
		}

		if ($selected_group AND isset($groups["$selected_group"]))
		{
			// filter to one group -- a bit of a hack
			$groups = array($vbulletin->GPC['groupid'] => $groups["$selected_group"]);
			$perpage = $vbulletin->options['pt_issuesperpage'];
		}
		else
		{
			$perpage = $vbulletin->options['pt_groupsearchissuespergroup'];
			$selected_group = '';
		}
	}
	else
	{
		$selected_group = '';
		$search['groupby'] = '';
		$groups = array(-1 => array('groupttitle' => '', 'count' => $search['resultcount']));
		$perpage = $vbulletin->options['pt_issuesperpage'];
	}

	return $groups;
}

/**
* Builds a search result bit
*
* @param	array	Issue info
*
* @return	string	Search result bit HTML
*/
function build_pt_search_resultbit($issue)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	static $projectperms = array();
	if (!isset($projectperms["$issue[projectid]"]))
	{
		$projectperms["$issue[projectid]"] = fetch_project_permissions($vbulletin->userinfo, $issue['projectid']);
	}

	// Do a query for adding the project group
	$projectgroup = $vbulletin->db->query_first("
		SELECT projectgroupid
		FROM " . TABLE_PREFIX . "pt_project
		WHERE projectid = " . $issue['projectid'] . "
	");

	$project = $vbulletin->pt_projects[$projectgroup['projectgroupid']]['projects'][$issue['projectid']];
	$issueperms = $projectperms[$issue['projectid']][$issue['issuetypeid']];
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	$show['edit_issue'] = $posting_perms['issue_edit'];
	$show['status_edit'] = $posting_perms['status_edit'];

	$issue = prepare_issue($issue);

	$show['statuscolor'] = false;

	$projectstatusset = $vbulletin->db->query_first("
		SELECT issuestatusid, projectid
		FROM " . TABLE_PREFIX . "pt_issuestatusprojectset
		WHERE projectid = " . $project['projectid'] . "
			AND issuestatusid = " . $issue['issuestatusid'] . "
	");

	if ($issue['statuscolor'] AND $vbulletin->options['pt_statuscolor'] AND (isset($projectstatusset['issuestatusid']) AND $issue['issuestatusid'] == $projectstatusset['issuestatusid'] AND $project['projectid'] == $projectstatusset['projectid']))
	{
		$show['statuscolor'] = true;
	}

	($hook = vBulletinHook::fetch_hook('projectsearch_results_bit')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_searchresultbit');
		$templater->register('issue', $issue);
		$templater->register('project', $project);
	$resultbits .= $templater->render();
	return $resultbits;
}

/**
* Determine whether a report is visible to the specific user
*
* @param	array	Report info
* @param	array	Array of viewable projects. (key: projectid, value: array of viewable types)
*
* @return	boolean
*/
function can_view_report($report, $viewable_projects)
{
	if ($report['issubscribed'] OR (!$report['projectlist'] AND !$report['issuetypelist']))
	{
		// no specific project/type list to limit to
		// or we're already subscribed
		$do_display = true;
	}
	else
	{
		$projects = (!$report['projectlist'] ? array() : explode(',', $report['projectlist']));
		$types = (!$report['issuetypelist'] ? array() : explode(',', $report['issuetypelist']));

		foreach ($viewable_projects AS $projectid => $viewable_types)
		{
			// we aren't limiting to any project or this is a project we need to be able to see
			if (!$projects OR in_array($projectid, $projects))
			{

				if (!$types)
				{
					// project only limit met, we're good
					$do_display = true;
					break;
				}

				foreach ($viewable_types AS $type => $null)
				{
					if (in_array($type, $types))
					{
						// type limit met (and project, if necessary) - we're good
						$do_display = true;
						break;
					}
				}
			}
		}
	}

	return $do_display;
}

/**
* Generates a link to repeat this search. Useful for linking to searches.
*
* @param	string|array	Array of criteria (may be in serialized form)
* @param	string			Sort by field
* @param	string			Sort order
* @param	string			Grouping field
*
* @param	string			Returns a link to repeat the search
*/
function generate_repeat_search_link($criteria, $sortby = 'lastpost', $sortorder = 'desc', $groupby = '')
{
	if (!is_array($criteria))
	{
		$criteria = unserialize($criteria);
	}

	$repeat_search = 'projectsearch.php?' . $vbulletin->session->var['sessionurl'] . 'do=dosearch';
	foreach ($criteria AS $crit_name => $crit_value)
	{
		$repeat_search .= generate_repeat_search_field(urlencode($crit_name), $crit_value);
	}

	$repeat_search .= '&amp;sort=' . urlencode($sortby);
	$repeat_search .= '&amp;sortorder=' . urlencode($sortorder);
	if ($groupby)
	{
		$repeat_search .= '&amp;groupby=' . urlencode($groupby);
	}

	return $repeat_search;
}

/**
* Generates an individual field of the repeat search links. Handles multi-dimensional arrays.
*
* @param	string	Criteria name. Expected to be urlencoded already.
* @param	mixed	Criteria value
*
* @return	strgin	Field for link
*/
function generate_repeat_search_field($crit_name, $crit_value)
{
	if (is_array($crit_value))
	{
		$repeat = '';
		foreach ($crit_value AS $subkey => $subval)
		{
			$repeat .= generate_repeat_search_field($crit_name . '[' . urlencode($subkey) . ']', $subval);
		}

		return $repeat;
	}
	else
	{
		return '&amp;' . $crit_name . '=' . urlencode($crit_value);
	}
}

function stripslashes_callback($matches)
{
	return stripslashes(str_replace(' ' , '*', $matches[0]));
}

?>