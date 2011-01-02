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

require_once(DIR . '/includes/functions_projecttools.php');

/**
* Get a list of projects and their issue types the current user is allowed to create issues in
*
* @return	array	A list of the projects and its issue types
*/
function ptimporter_get_allowed_projects()
{
	global $vbulletin, $db;
	static $cache;
	
	if (is_array($cache))
	{
		return $cache;
	}
	
	$cache = array();
	
	if (empty($vbulletin->pt_projects))
	{
		return $cache;
	}
	
	foreach ($vbulletin->pt_projects AS $project)
	{
		$types = ptimporter_get_allowed_issuetypes($project);
		if (count($types) >= 1)
		{
			$cache[] = array('projectid' => $project['projectid'], 'projectinfo' => $project, 'types' => $types);
		}
	}
	
	return $cache;
}

/**
* Get a list of issue types for the given project the current user is allowed to create issues in
*
* @param	array	Project information
*
* @return	array	A list of the issue types
*/
function ptimporter_get_allowed_issuetypes($project)
{
	global $vbulletin, $db;
	static $cache;
	
	if (is_array($cache["$project[projectid]"]))
	{
		return $cache["$project[projectid]"];
	}
	
	$cache["$project[projectid]"] = array();
	
	if (!$project)
	{
		return $cache["$project[projectid]"];
	}
	
	if (!is_array($project['types']) OR $project['displayorder'] == 0)
	{
		return $cache["$project[projectid]"];
	}
	
	$project_permissions = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
	
	foreach ($project['types'] AS $issuetype => $issuetypeid)
	{
		if ($project_permissions[$issuetype]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew'] AND $project_permissions[$issuetype]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
		{
			$cache["$project[projectid]"][] = $issuetype;
		}
	}
	
	return $cache["$project[projectid]"];
}

/**
* Fetches project info and throws an error if it's not valid
*
* This function returns different project info (which includes the valid issue types) than the verify_project function!
*
* @param	integer	Project ID
*
* @return	array	Project info
*/
function ptimporter_verify_project($projectid)
{
	global $vbulletin, $vbphrase;
	
	$projectid = intval($projectid);
	if ($projectid < 1)
	{
		standard_error(fetch_error('invalidid', $vbphrase['project'], $vbulletin->options['contactuslink']));
	}
	
	$projects = ptimporter_get_allowed_projects();
	
	foreach ($projects AS $project)
	{
		$project['options'] = $project['projectinfo']['options'];

		if ($project['projectinfo']['projectid'] == $projectid)
		{
			return $project;
		}
	}
	
	standard_error(fetch_error('invalidid', $vbphrase['project'], $vbulletin->options['contactuslink']));
}

/**
* Verifies that an issue type is valid. Errors if not.
*
* This function automatically checks if the project id is valid, no need to call both!
*
* @param	string	Issue type ID
* @param	integer	Project ID
*
* @return	array	Project info
*/
function ptimporter_verify_issuetypeid($issuetypeid, $projectid)
{
	global $vbulletin, $vbphrase;
	
	$project = ptimporter_verify_project($projectid);
	
	foreach ($project['types'] AS $type)
	{
		if ($type == $issuetypeid)
		{
			return $project;
		}
	}
	
	standard_error(fetch_error('invalidid', $vbphrase['issue_type'], $vbulletin->options['contactuslink']));
}

/**
* Verifies that an issue status id is valid. Errors if not.
*
* @param	integer	Issue status id
* @param	string	Issue type id
*/
function ptimporter_verify_issuestatusid($issuestatusid, $issuetypeid)
{
	global $vbulletin, $vbphrase;
	
	$status = $vbulletin->pt_issuestatus[$issuestatusid];
	if (!$status)
	{
		// The issue status id does not exist
		standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
	}
	
	if ($issuetypeid != $status['issuetypeid'])
	{
		// The issue status id is for a different issue type
		standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
	}
}

/**
* Get the issue posting permissions
*
* @param	integer	Project id
* @param	string	Issue type id
*
* @return	array	Permissions
*/
function ptimporter_prepare_issue_posting_pemissions($projectid, $issuetypeid)
{
	global $vbulletin, $vbphrase;
	
	$issue = array(
		'issueid' => 0,
		'projectid' => $projectid,
		'issuestatusid' => $vbulletin->pt_projects[$projectid]['types'][$issuetypeid],
		'issuetypeid' => $issuetypeid,
		'issuetype' => $vbphrase['issuetype_' . $issuetypeid . '_singular'],
		'projectcategoryid' => 0,
		'title' => '',
		'summary' => '',
		'pagetext' => '',
		'priority' => 0
	);
	
	$issue_perms = fetch_project_permissions($vbulletin->userinfo, $projectid, $issuetypeid);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issue_perms);
	
	return $posting_perms;
}

?>