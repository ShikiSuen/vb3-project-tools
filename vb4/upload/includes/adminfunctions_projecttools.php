<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Builds the cache of issue statuses. Placed in $vbulletin->pt_issuestatus.
* Accessed as [issuestatusid] => <info>
*
* @return	array	Status cache
*/
function build_issue_status_cache()
{
	global $db, $vbulletin;

	$cache = array();
	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		ORDER BY issuetypeid, displayorder
	");
	while ($status = $db->fetch_array($status_data))
	{
		$cache["$status[issuestatusid]"] = $status;
	}

	build_datastore('pt_issuestatus', serialize($cache), 1);
	$vbulletin->pt_issuestatus = $cache;

	return $cache;
}

/**
* Builds the cache of issue types. Placed in $vbulletin->pt_issuetype.
* Accessed as [issuetypeid] => <info, including [statuses] array>.
* Also builds the issue status cache automatically.
*
* @return	array	Type cache
*/
function build_issue_type_cache()
{
	global $db, $vbulletin;

	$cache = array();
	$type_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		ORDER BY displayorder
	");
	while ($type = $db->fetch_array($type_data))
	{
		$type['statuses'] = array();
		$status_data = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuetypeid = '$type[issuetypeid]'
			ORDER BY displayorder
		");
		while ($status = $db->fetch_array($status_data))
		{
			$type['statuses']["$status[issuestatusid]"] = $status;
		}

		$cache["$type[issuetypeid]"] = $type;
	}

	build_datastore('pt_issuetype', serialize($cache), 1);
	$vbulletin->pt_issuetype = $cache;

	build_issue_status_cache();

	return $cache;
}

/**
* Builds the cache of project categories into $vbulletin->pt_categories.
* Accessed as [projectcategoryid] => <info>
*
* @return	array	Category cache
*/
function build_project_category_cache()
{
	global $db, $vbulletin;

	$cache = array();
	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		ORDER BY projectid, displayorder
	");
	while ($category = $db->fetch_array($category_data))
	{
		$cache["$category[projectcategoryid]"] = $category;
	}

	build_datastore('pt_categories', serialize($cache), 1);
	$vbulletin->pt_categories = $cache;

	return $cache;
}

/**
* Builds the cache of project priorities into $vbulletin->pt_priorities.
* Accessed as [projectpriorityid] => <info>
*
* @return	array	Priority cache
*/
function build_project_priority_cache()
{
	global $db, $vbulletin;

	$cache = array();
	$priority_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		ORDER BY projectid, displayorder
	");
	while ($priority = $db->fetch_array($priority_data))
	{
		$cache["$priority[projectpriorityid]"] = $priority;
	}

	build_datastore('pt_priorities', serialize($cache), 1);
	$vbulletin->pt_priorities = $cache;

	return $cache;
}

/**
* Builds the cache of project permissions into $vbulletin->pt_permissions.
* Accessed as [usergroupid][projectid][issuetypeid] => <info>.
* This handles inheritance, so that only actual projectids will be listed,
* with the actual permissions for the group in question.
*
* @return	array	Project permission cache
*/
function build_project_permissions()
{
	global $vbulletin, $db;

	// figure out what the permission columns are,
	// so we can put in entries for any groups that don't have permissions
	$default_perms = array();
	$perm_fields = $db->query_read("
		SHOW COLUMNS FROM " . TABLE_PREFIX . "pt_projectpermission
		LIKE '%permissions'
	");
	while ($perm_field = $db->fetch_array($perm_fields))
	{
		$default_perms["$perm_field[Field]"] = 0;
	}

	$cache = array(); // [usergroupid][projectid][issuetypeid]

	// fetch global permissions
	$global_permissions = array();
	$usergroup_info = array();

	$usergroup_data = $db->query_read("
		SELECT projectpermission.*,
			usergroup.usergroupid
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		LEFT JOIN " . TABLE_PREFIX . "pt_projectpermission AS projectpermission ON
			(usergroup.usergroupid = projectpermission.usergroupid AND projectpermission.projectid = 0)
		ORDER BY usergroup.title
	");
	while ($usergroup = $db->fetch_array($usergroup_data))
	{
		$perms = $usergroup;
		unset($perms['usergroupid'], $perms['projectid'], $perms['issuetypeid'], $perms['title']);

		$global_permissions["$usergroup[usergroupid]"]["$usergroup[issuetypeid]"] = $perms;
		$usergroup_info["$usergroup[usergroupid]"] = array(
			'title' => $usergroup['title'],
			'usergroupid' => $usergroup['usergroupid']
		);
	}

	// find permission info for each project, for each usergroup
	$projects = $db->query_read("
		SELECT projectid
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY projectid
	");
	while ($project = $db->fetch_array($projects))
	{
		$project_permissions = array();
		$usergroup_data = $db->query_read("
			SELECT projectpermission.*
			FROM " . TABLE_PREFIX . "pt_projectpermission AS projectpermission
			WHERE projectpermission.projectid = $project[projectid]
		");
		while ($usergroup = $db->fetch_array($usergroup_data))
		{
			$project_permissions["$usergroup[usergroupid]"]["$usergroup[issuetypeid]"] = $usergroup;
		}

		$project_types = array();
		$project_types_query = $db->query_read("
			SELECT projecttype.issuetypeid
			FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
			WHERE projecttype.projectid = $project[projectid]
		");
		while ($project_type = $db->fetch_array($project_types_query))
		{
			$project_types[] = $project_type['issuetypeid'];
		}

		// loop through the usergroups
		foreach ($usergroup_info AS $usergroup)
		{
			// fetch the types
			foreach ($project_types AS $issuetypeid)
			{
				// take custom permissions over global
				if (isset($project_permissions["$usergroup[usergroupid]"]["$issuetypeid"]))
				{
					$perms = $project_permissions["$usergroup[usergroupid]"]["$issuetypeid"];
				}
				else
				{
					$perms = $global_permissions["$usergroup[usergroupid]"]["$issuetypeid"];
				}

				if (!is_array($perms))
				{
					// no global perms, take the default (all 0s)
					$perms = $default_perms;
				}
				else
				{
					// ensure they come out as ints
					foreach ($perms AS $id => $value)
					{
						$perms["$id"] = intval($value);
					}
				}

				$cache["$usergroup[usergroupid]"]["$project[projectid]"]["$issuetypeid"] = $perms;
			}
		}
	}

	build_datastore('pt_permissions', serialize($cache), 1);
	$vbulletin->pt_permissions = $cache;

	return $cache;
}

/**
* Builds the cache of projects into $vbulletin->pt_projects.
* Accessed as [projectid] => <info, including [types]>.
* Automatically builds categories, permissions, and assignable users.
*
* @return	array	Project cache
*/
function build_project_cache()
{
	global $vbulletin, $db;

	$cache = array();

	$projectgroups = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectgroup
		ORDER BY displayorder
	");
	while ($projectgroup = $db->fetch_array($projectgroups))
	{
		$projects = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_project
			WHERE projectgroupid = " . $projectgroup['projectgroupid'] . "
			ORDER BY displayorder
		");
		while ($project = $db->fetch_array($projects))
		{
			$project_types = array();
			$project_types_query = $db->query_read("
				SELECT issuetypeid, startstatusid
				FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
				WHERE projecttype.projectid = $project[projectid]
			");
			while ($project_type = $db->fetch_array($project_types_query))
			{
				$project_types["$project_type[issuetypeid]"] = $project_type['startstatusid'];
			}
	
			$project['types'] = $project_types;
			$projectgroup['projects']["$project[projectid]"] = $project;
		}

		$cache["$projectgroup[projectgroupid]"] = $projectgroup;
	}

	build_datastore('pt_projects', serialize($cache), 1);
	$vbulletin->pt_projects = $cache;

	build_project_category_cache();
	build_project_permissions();
	build_assignable_users();
	build_pt_user_list('pt_report_users', 'pt_report_user_cache');

	return $cache;
}

/**
* Builds the cache of project bitfields (for perms) into $vbulletin->pt_bitfields.
* Accessed as [groupid][bitname] => value
*
* @return	array	Bitfield cache
*/
function build_project_bitfields()
{
	global $vbulletin;

	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::build(false);
	$builder =& vB_Bitfield_Builder::init();

	$bits = array();
	if ($builder->data['pt_permissions'])
	{
		foreach ($builder->data['pt_permissions'] AS $groupid => $permission_group)
		{
			foreach ($permission_group AS $bitname => $permvalue)
			{
				$bits["$groupid"]["$bitname"] = intval(is_array($permvalue) ? $permvalue['value'] : $permvalue);
			}
		}
	}

	build_datastore('pt_bitfields', serialize($bits), 1);
	$vbulletin->pt_bitfields = $bits;

	return $bits;
}

/**
* Builds the cache of assignable users into $vbulletin->pt_assignable.
* Accessed as [projectid][issuetypeid][userid] => username.
*
* @return	array	Assignable users cache
*/
function build_assignable_users()
{
	return build_pt_user_list('pt_assignable', 'pt_assignable_user_cache');
}

/**
* Builds the cache of version cache into $vbulletin->pt_versions.
* Accessed as [projectversionid] => <info>.
*
* @return	array	Version cache
*/
function build_version_cache()
{
	global $db, $vbulletin;

	$versions = array();

	$version_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		ORDER BY projectid, effectiveorder DESC
	");
	while ($version = $db->fetch_array($version_data))
	{
		$versions["$version[projectversionid]"] = $version;
	}

	build_datastore('pt_versions', serialize($versions), 1);

	return $versions;
}

/**
* Builds the cache of magic select cache into $vbulletin->pt_magicselects.
* Accessed as [projectmagicselectid] => <info>.
*
* @return	array	Magic select cache
*/
function build_magicselect_cache()
{
	global $db, $vbulletin;

	$magicselects = array();

	$magicselect_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		ORDER BY projectid, displayorder DESC
	");
	while ($magicselect = $db->fetch_array($magicselect_data))
	{
		$magicselects["$magicselect[projectmagicselectid]"] = $magicselect;
	}

	build_datastore('pt_magicselects', serialize($magicselects), 1);

	return $magicselects;
}


/**
* Rebuilds all project counters.
*
* @param	boolean	True if you want to echo a "." for each project
*/
function rebuild_project_counters($echo = false)
{
	global $vbulletin, $db;

	$projects = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_project
	");
	while ($project = $db->fetch_array($projects))
	{
		$projectdata = datamanager_init('Pt_Project', $vbulletin, ERRTYPE_SILENT);
		$projectdata->set_existing($project);
		$projectdata->rebuild_project_counters();
		$projectdata->save();
		unset($projectdata);

		if ($echo)
		{
			echo ' . ';
			vbflush();
		}
	}

}

/**
* Rebuilds all milestone counters.
*
* @param	boolean	True if you want to echo a "." for each milestone
*/
function rebuild_milestone_counters($echo = false)
{
	global $vbulletin, $db;

	$milestones = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
	");
	while ($milestone = $db->fetch_array($milestones))
	{
		$milestonedata = datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_SILENT);
		$milestonedata->set_existing($milestone);
		$milestonedata->rebuild_milestone_counters();
		$milestonedata->save();

		if ($echo)
		{
			echo ' . ';
			vbflush();
		}
	}
}

/**
* Rebuilds all profile issue counters.
*
* @return	mixed	Up to date counters
*/
function rebuild_profile_issue_counters()
{
	global $vbulletin, $db;

	$userarray = array();

	$users = $db->query_read("
		SELECT submituserid AS userid
		FROM " . TABLE_PREFIX . "pt_issue
		ORDER BY issueid ASC
	");

	while ($user = $db->fetch_array($users))
	{
		// Create an array with userid and counter for each
		++$userarray["$user[userid]"];
	}

	// Save the counter for each user
	foreach ($userarray AS $userid => $counter)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_user SET
				totalissues = $counter
			WHERE userid = $userid
		");
	}
}

/**
* Callback to verify if a usergroup would be assignable to an issue
*
* @param	Integer	Usergroup ID
* @param	Integer	Project ID
* @param	Integer	Issue Type ID
*
* @return	boolean This should be true if a usergroup is assignable
*/
function pt_assignable_user_cache($usergroupid, $projectid, $issuetypeid)
{
	global $vbulletin;
	return (intval($vbulletin->pt_permissions["$usergroupid"]["$projectid"]["$issuetypeid"]['generalpermissions']) & intval($vbulletin->pt_bitfields['general']['canassigned']));
}

/**
* Callback to verify if a usergroup could deal with a reported issue note
*
* @param	Integer	Usergroup ID
* @param	Integer	Project ID
* @param	Integer	Issue Type ID
*
* @return	boolean This should be true if a usergroup can deal with a reported issue note
*/
function pt_report_user_cache($usergroupid, $projectid, $issuetypeid)
{
	global $vbulletin;

	$checks = $vbulletin->pt_bitfields['post']['caneditissueothers'] | $vbulletin->pt_bitfields['post']['caneditnoteothers'] | $vbulletin->pt_bitfields['post']['candeleteissueothers'] | $vbulletin->pt_bitfields['post']['candeletenoteothers'];

	return (intval($vbulletin->pt_permissions["$usergroupid"]["$projectid"]["$issuetypeid"]['postpermissions']) & intval($checks));
}

/**
* Builds a cache of users into the datastore who meet a criteria.
* Accessed as [projectid][issuetypeid][userid] => username.
*
* @param	String	Name of the datastore item to update
* @param	String	Function to callback to see if a particular usergroup matches
*
* @return	array	Users who met the criteria
*/
function build_pt_user_list($name, $callback)
{
	global $db, $vbulletin;

	build_project_permissions();
	build_project_bitfields();

	$userlist = array();

	$usergroups = array();
	$usergroup_list = $db->query_read("
		SELECT usergroupid
		FROM " . TABLE_PREFIX . "usergroup
	");
	while ($usergroup = $db->fetch_array($usergroup_list))
	{
		$usergroups[] = $usergroup['usergroupid'];
	}

	$projects = $db->query_read("
		SELECT projectid
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY projectid
	");
	while ($project = $db->fetch_array($projects))
	{
		$projectid = $project['projectid'];
		$userlist["$projectid"] = array();

		$project_types = array();
		$project_types_query = $db->query_read("
			SELECT projecttype.issuetypeid
			FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
			WHERE projecttype.projectid = $project[projectid]
		");
		while ($project_type = $db->fetch_array($project_types_query))
		{
			$project_types[] = $project_type['issuetypeid'];
		}

		foreach ($project_types AS $issuetypeid)
		{
			$userlisttype = array();

			foreach ($usergroups AS $usergroupid)
			{
				if (function_exists($callback) AND call_user_func($callback, $usergroupid, $projectid, $issuetypeid))
				{
					$userlisttype[] = $usergroupid;
				}
			}

			$userlist["$projectid"]["$issuetypeid"] = array();

			if (!$userlisttype)
			{
				continue;
			}

			$users = $db->query_read("
				SELECT user.userid, user.username
				FROM " . TABLE_PREFIX . "user AS user
				INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (user.usergroupid = usergroup.usergroupid)
				WHERE (user.usergroupid IN (" . implode(',', $userlisttype) . ")
					OR FIND_IN_SET(" . implode(', user.membergroupids) OR FIND_IN_SET(', $userlisttype) . ", user.membergroupids))
					AND (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
				ORDER BY user.username
			");
			while ($user = $db->fetch_array($users))
			{
				$userlist["$projectid"]["$issuetypeid"]["$user[userid]"] = $user['username'];
			}
		}
	}

	build_datastore($name, serialize($userlist), 1);

	return $userlist;
}

/**
* Returns a row containing an input and a color picker widget
*
* @param	string	Item varname
* @param	string	Item value
* @param	string	CSS class to display with
* @param	integer	Size of input box
* @param	boolean	Surround code with <tr> ... </tr> ?
*
* @return	string
*/
function construct_status_color_row($name, $value, $class = 'bginput', $size = 22, $printtr = true)
{
	global $numcolors;

	$value = htmlspecialchars_uni($value);

	$html = '';

	if ($printtr)
	{
		$html .= "
		<tr>\n";
	}

	$html .= '
			<table cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td><input type="text" class="' . $class . '" name="' . $name . '" id="color_' . $numcolors . '" value="' . $value . '" title="$' . $name . '" tabindex="1" size="' . $size . '" onchange="preview_color('. $numcolors . ')" dir="ltr" />&nbsp;</td>
				<td><div id="preview_' . $numcolors . '" class="colorpreview" onclick="open_color_picker(' . $numcolors . ', event)"></div></td>
			</tr>
			</table>
	';

	if ($printtr)
	{
		$html .= "	</tr>\n";
	}

	$numcolors ++;

	return $html;
}



/**
* Fetches the 'scriptpath' variable - ie: the URI of the current page
*
* @return	string
*/
function fetch_scriptpath()
{
	global $vbulletin;

	if ($vbulletin->scriptpath != '')
	{
		return $vbulletin->scriptpath;
	}
	else
	{
		if ($_SERVER['REQUEST_URI'] OR $_ENV['REQUEST_URI'])
		{
			$scriptpath = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_ENV['REQUEST_URI'];
		}
		else
		{
			if ($_SERVER['PATH_INFO'] OR $_ENV['PATH_INFO'])
			{
				$scriptpath = $_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO'] : $_ENV['PATH_INFO'];
			}
			else if ($_SERVER['REDIRECT_URL'] OR $_ENV['REDIRECT_URL'])
			{
				$scriptpath = $_SERVER['REDIRECT_URL'] ? $_SERVER['REDIRECT_URL'] : $_ENV['REDIRECT_URL'];
			}
			else
			{
				$scriptpath = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
			}

			if ($_SERVER['QUERY_STRING'] OR $_ENV['QUERY_STRING'])
			{
				$scriptpath .= '?' . ($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'] : $_ENV['QUERY_STRING']);
			}
		}

		// in the future we should set $registry->script here too
		$quest_pos = strpos($scriptpath, '?');
		if ($quest_pos !== false)
		{
			$script = urldecode(substr($scriptpath, 0, $quest_pos));
			$scriptpath = $script . substr($scriptpath, $quest_pos);
		}
		else
		{
			$scriptpath = urldecode($scriptpath);
		}

		// store a version that includes the sessionhash
		$vbulletin->reloadurl = $vbulletin->input->xss_clean($scriptpath);

		$scriptpath = $vbulletin->input->strip_sessionhash($scriptpath);
		$scriptpath = $vbulletin->input->xss_clean($scriptpath);
		$vbulletin->scriptpath = $scriptpath;

		return $scriptpath;
	}
}

?>