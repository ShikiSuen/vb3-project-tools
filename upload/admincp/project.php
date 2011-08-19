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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Revision$');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'projecttools',
	'projecttoolsadmin'
);

$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
);

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

if (empty($vbulletin->products['vbprojecttools']))
{
	print_stop_message('product_not_installed_disabled');
}

require_once(DIR . '/includes/adminfunctions_projecttools.php');
require_once(DIR . '/includes/functions_projecttools.php');

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

$full_product_info = fetch_product_list(true);

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canpt'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'issuestatusid' => TYPE_UINT,
));

log_admin_action(
	(!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') .
	(!empty($vbulletin->GPC['issuestatusid']) ? ' status id = ' . $vbulletin->GPC['issuestatusid'] : '')
);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'projectlist';
}

$issuetype_options = array();

$types = $db->query_read("
	SELECT *
	FROM " . TABLE_PREFIX . "pt_issuetype
	ORDER BY displayorder
");

while ($type = $db->fetch_array($types))
{
	$issuetype_options["$type[issuetypeid]"] = $vbphrase["issuetype_$type[issuetypeid]_singular"];
}

$helpcache['project']['projectadd']['afterforumids[]'] = 1;
$helpcache['project']['projectedit']['afterforumids[]'] = 1;

// ########################################################################
// ######################### GENERAL MANAGEMENT ###########################
// ########################################################################
if ($_REQUEST['do'] == 'install')
{
	$vbulletin->input->clean_gpc('r', 'installed_version', TYPE_NOHTML);

	$full_product_info = fetch_product_list(true);
	print_form_header('', '');

	if (!$vbulletin->GPC['installed_version'])
	{
		print_table_header($vbphrase['project_tools_installed_successfully']);
		print_description_row(construct_phrase($vbphrase['project_tools_install_info'], htmlspecialchars_uni($full_product_info['vbprojecttools']['version'])));
	}
	else
	{
		print_table_header($vbphrase['project_tools_upgraded_successfully']);
		print_description_row(construct_phrase($vbphrase['project_tools_upgrade_info'], $vbulletin->GPC['installed_version'], htmlspecialchars_uni($full_product_info['vbprojecttools']['version'])));

		if ($vbulletin->GPC['installed_version'][0] == '1' AND $full_product_info['vbprojecttools']['version'][0] == '2')
		{
			// upgrade from version 1 to 2
			print_description_row($vbphrase['project_tools_upgrade_info_1_2']);
		}
	}

	print_table_footer();

	$_REQUEST['do'] = 'projectlist';
}

// ########################################################################
if ($_REQUEST['do'] == 'issue')
{
	print_form_header('project', 'editissue1');
	print_table_header($vbphrase['edit_issue']);
	print_description_row($vbphrase['some_issue_fields_not_editable_frontend']);
	print_input_row($vbphrase['id_of_issue'], 'issueid');
	print_submit_row($vbphrase['find'], '');
}

// ########################################################################
if ($_POST['do'] == 'editissue1')
{
	$vbulletin->input->clean_gpc('p', 'issueid', TYPE_UINT);

	$issue = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issue
		WHERE issueid = " . $vbulletin->GPC['issueid']
	);

	if (!$issue)
	{
		print_stop_message('invalid_issue_specified');
	}

	$project_options = array();

	$projects = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY displayorder
	");

	while ($project = $db->fetch_array($projects))
	{
		$project_options["$project[projectid]"] = $project['title_clean'];
	}

	print_form_header('project', 'editissue2');
	print_table_header($vbphrase['edit_issue']);

	print_label_row($vbphrase['title'], $issue['title']);
	print_label_row($vbphrase['summary'], $issue['summary']);

	print_select_row($vbphrase['project'], 'projectid', $project_options, $issue['projectid']);
	print_select_row($vbphrase['issue_type'], 'issuetypeid', $issuetype_options, $issue['issuetypeid']);

	construct_hidden_code('issueid', $issue['issueid']);

	print_submit_row($vbphrase['continue'], '');
}

// ########################################################################
if ($_POST['do'] == 'editissue2')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issueid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML
	));

	$issue = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issue
		WHERE issueid = " . $vbulletin->GPC['issueid']
	);

	if (!$issue)
	{
		print_stop_message('invalid_action_specified');
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (!isset($issuetype_options[$vbulletin->GPC['issuetypeid']]))
	{
		print_stop_message('invalid_action_specified');
	}

	$categories = array(0 => $vbphrase['unknown']);

	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectid = $project[projectid]
		ORDER BY displayorder
	");

	while ($category = $db->fetch_array($category_data))
	{
		$categories["$category[projectcategoryid]"] = $category['title'];
	}

	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
			INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = $project[projectid]
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$appliesversion_options = array(0 => $vbphrase['unknown']) + $version_groups;
	$addressedversion_options = array(0 => $vbphrase['none_meta'], '-1' => $vbphrase['next_release']) + $version_groups;

	if ($issue['isaddressed'] AND $issue['addressedversionid'] == 0)
	{
		$issue['addressedversionid'] = -1;
	}

	$issuestatuses = array();

	$issuestatus_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
		ORDER BY displayorder
	");

	while ($issuestatus = $db->fetch_array($issuestatus_data))
	{
		$issuestatuses["$issuestatus[issuestatusid]"] = $vbphrase["issuestatus$issuestatus[issuestatusid]"];
	}

	require_once(DIR . '/includes/functions_pt_posting.php');
	$milestones = fetch_milestone_select_list($project['projectid']);

	print_form_header('project', 'updateissue');
	print_table_header($vbphrase['edit_issue']);

	print_label_row($vbphrase['title'], $issue['title']);
	print_label_row($vbphrase['summary'], $issue['summary']);
	print_label_row($vbphrase['project'], $project['title_clean']);
	print_label_row($vbphrase['issue_type'], $issuetype_options[$vbulletin->GPC['issuetypeid']]);

	print_select_row($vbphrase['category'], 'projectcategoryid', $categories, $issue['projectcategoryid']);
	print_select_row($vbphrase['applicable_version'], 'appliesversionid', $appliesversion_options, $issue['appliesversionid']);
	print_select_row($vbphrase['addressed_version'], 'addressedversionid', $addressedversion_options, $issue['addressedversionid']);
	print_select_row($vbphrase['status'], 'issuestatusid', $issuestatuses, $issue['issuestatusid']);
	print_select_row($vbphrase['milestone'], 'milestoneid', $milestones, $issue['milestoneid']);

	construct_hidden_code('issueid', $issue['issueid']);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('issuetypeid', $vbulletin->GPC['issuetypeid']);

	print_submit_row($vbphrase['continue'], '');
}

// ########################################################################
if ($_POST['do'] == 'updateissue')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issueid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'projectcategoryid' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT,
		'issuestatusid' => TYPE_UINT,
		'milestoneid' => TYPE_UINT
	));

	$issue = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issue
		WHERE issueid = " . $vbulletin->GPC['issueid']
	);

	if (!$issue)
	{
		print_stop_message('invalid_action_specified');
	}

	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_CP);
	$issuedata->set_existing($issue);
	$issuedata->set_info('perform_activity_updates', false);
	$issuedata->set_info('insert_change_log', false);

	$issuedata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	$issuedata->set('issuestatusid', $vbulletin->GPC['issuestatusid']);
	$issuedata->set('projectid', $vbulletin->GPC['projectid']);
	$issuedata->set('projectcategoryid', $vbulletin->GPC['projectcategoryid']);
	$issuedata->set('appliesversionid', $vbulletin->GPC['appliesversionid']);

	switch ($vbulletin->GPC['addressedversionid'])
	{
		case -1:
			$issuedata->set('isaddressed', 1);
			$issuedata->set('addressedversionid', 0);
			break;

		case 0:
			$issuedata->set('isaddressed', 0);
			$issuedata->set('addressedversionid', 0);
			break;

		default:
			$issuedata->set('isaddressed', 1);
			$issuedata->set('addressedversionid', $vbulletin->GPC['addressedversionid']);
			break;
	}

	$issuedata->set('milestoneid', $vbulletin->GPC['milestoneid']);

	$issuedata->save();

	define('CP_BACKURL', '');
	print_stop_message('issue_saved');
}

// ########################################################################
// ####################### PROJECT MANAGEMENT #############################
// ########################################################################
if ($_POST['do'] == 'projecttypedel_commit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectid' => TYPE_UINT,
		'delstatus' => TYPE_ARRAY_UINT
	));

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	foreach ($vbulletin->GPC['delstatus'] AS $issuetypeid => $newstatusid)
	{
		if (!$newstatusid)
		{
			// do not change
			continue;
		}

		$status = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuestatusid = $newstatusid
		");

		if (!$status)
		{
			continue;
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				issuestatusid = $status[issuestatusid],
				issuetypeid = '" . $db->escape_string($status['issuetypeid']) . "'
			WHERE projectid = $project[projectid]
				AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
		");
	}

	define('CP_REDIRECT', 'project.php?do=projectlist');
	print_stop_message('project_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projecttypedel')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'issuetypeids' => TYPE_ARRAY_NOHTML
	));

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('project', 'projecttypedel_commit');
	print_table_header(construct_phrase($vbphrase['issue_types_deleted_for_project_x'], $project['title_clean']));
	print_description_row($vbphrase['chose_delete_types_project']);

	print_cells_row(array(
		$vbphrase['deleted_issue_type'],
		$vbphrase['move_issues_into']
	), true);

	$del_types = array();

	$type_sql = $db->query_read("
		SELECT issuetype.*
		FROM " . TABLE_PREFIX . "pt_issuetype AS issuetype
		WHERE issuetypeid IN ('" . implode("', '", array_map(array(&$db, 'escape_string'), $vbulletin->GPC['issuetypeids'])) . "')
		ORDER BY issuetype.displayorder
	");

	while ($type = $db->fetch_array($type_sql))
	{
		$del_types["$type[issuetypeid]"] = $type;
	}

	$statuses = array(0 => $vbphrase['do_not_change_meta']);

	$status_sql = $db->query_read("
		SELECT issuestatus.*
		FROM " . TABLE_PREFIX . "pt_issuestatus AS issuestatus
		INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuestatus.issuetypeid = issuetype.issuetypeid)
		INNER JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON (issuestatus.issuetypeid = projecttype.issuetypeid AND projecttype.projectid = $project[projectid])
		ORDER BY issuetype.displayorder, issuestatus.displayorder
	");

	while ($status = $db->fetch_array($status_sql))
	{
		$statuses[$vbphrase["issuetype_$status[issuetypeid]_singular"]]["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	foreach ($del_types AS $issuetypeid => $type)
	{
		print_select_row($vbphrase["issuetype_{$issuetypeid}_singular"], "delstatus[$issuetypeid]", $statuses);
	}

	construct_hidden_code('projectid', $project['projectid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectid' => TYPE_UINT,
		'displayorder' => TYPE_UINT,
		'title' => TYPE_STR,
		'summary' => TYPE_STR,
		'description' => TYPE_STR,
		'startstatus' => TYPE_ARRAY_UINT,
		'permissionbase' => TYPE_UINT,
		'options' => TYPE_ARRAY_UINT,
		'afterforumids' => TYPE_ARRAY_UINT,
		'forumtitle' => TYPE_STR,
		'requireappliesversion' => TYPE_UINT,
		'requirecategry' => TYPE_UINT,
		'requirepriority' => TYPE_UINT
	));

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['projectid'])
	{
		$project = fetch_project_info($vbulletin->GPC['projectid'], false);
	}

	$havestart = false;

	foreach ($vbulletin->GPC['startstatus'] AS $issuetypeid => $startstatusid)
	{
		if ($startstatusid)
		{
			$havestart = true;
			break;
		}
	}

	if (!$havestart)
	{
		print_stop_message('one_type_must_be_available');
	}

	$projectdata =& datamanager_init('Pt_Project', $vbulletin, ERRTYPE_CP);

	if ($project)
	{
		$projectdata->set_existing($project);
	}

	$projectdata->set('displayorder', $vbulletin->GPC['displayorder']);
	$projectdata->set('title', $vbulletin->GPC['title']);
	$projectdata->set('summary', $vbulletin->GPC['summary']);
	$projectdata->set('description', $vbulletin->GPC['description']);
	$projectdata->set('afterforumids', implode(',', $vbulletin->GPC['afterforumids']));
	$projectdata->set('forumtitle', $vbulletin->GPC['forumtitle']);

	$options = 0;

	foreach ($vbulletin->GPC['options'] AS $bitname => $bitvalue)
	{
		if ($bitvalue > 0)
		{
			$options += $vbulletin->bf_misc['pt_projectoptions']["$bitname"];
		}
	}

	$projectdata->set('options', $options);
	$projectdata->set('requireappliesversion', $vbulletin->GPC['requireappliesversion']);
	$projectdata->set('requirecategory', $vbulletin->GPC['requirecategory']);
	$projectdata->set('requirepriority', $vbulletin->GPC['requirepriority']);

	if (!$project['projectid'])
	{
		$project['projectid'] = $projectid = $projectdata->save();

		if ($vbulletin->GPC['permissionbase'])
		{
			$permissions = array();
			$permission_query = $db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_projectpermission
				WHERE projectid = " . $vbulletin->GPC['permissionbase']
			);

			while ($permission = $db->fetch_array($permission_query))
			{
				$permissions[] = "
					($permission[usergroupid], $project[projectid], '" . $db->escape_string($permission['issuetypeid']) . "',
					$permission[generalpermissions], $permission[postpermissions], $permission[attachpermissions])
				";
			}

			if ($permissions)
			{
				$db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "pt_projectpermission
						(usergroupid, projectid, issuetypeid, generalpermissions, postpermissions, attachpermissions)
					VALUES
						" . implode(',', $permissions)
				);
			}
		}
	}
	else
	{
		$projectdata->save();
	}

	// setup the usable issue types for this project
	$del_types = array();

	foreach ($vbulletin->GPC['startstatus'] AS $issuetypeid => $startstatusid)
	{
		if ($startstatusid)
		{
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "pt_projecttype
					(projectid, issuetypeid, startstatusid)
				VALUES
					('$project[projectid]', '" . $db->escape_string($issuetypeid) . "', " . intval($startstatusid) . ")
			");

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projecttype SET
					startstatusid = " . intval($startstatusid) . "
				WHERE projectid = $project[projectid]
					AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
			");
		}
		else
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "pt_projecttype
				WHERE projectid = $project[projectid]
					AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
			");

			if ($db->affected_rows())
			{
				$del_types[] = urlencode($issuetypeid);

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "pt_projecttypeprivatelastpost
					WHERE projectid = $project[projectid]
						AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
				");
			}
		}
	}

	build_project_cache();

	if ($del_types)
	{
		define('CP_REDIRECT', 'project.php?do=projecttypedel&projectid=' . $project['projectid'] . '&issuetypeids[]=' . implode('&issuetypeids[]=', $del_types));
	}
	else
	{
		define('CP_REDIRECT', 'project.php?do=projectlist');
	}

	print_stop_message('project_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectadd' OR $_REQUEST['do'] == 'projectedit')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	if ($vbulletin->GPC['projectid'])
	{
		$project = fetch_project_info($vbulletin->GPC['projectid'], false);
	}

	if (empty($project))
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_project
		");

		$project = array(
			'projectid' => 0,
			'displayorder' => $maxorder['maxorder'] + 10,
			'options' => ''
		);
	}

	$issuestatus_options = array();

	$issuestatus_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		ORDER BY displayorder
	");

	while ($issuestatus = $db->fetch_array($issuestatus_data))
	{
		$issuestatus_options["$issuestatus[issuetypeid]"]["$issuestatus[issuestatusid]"] = $vbphrase["issuestatus$issuestatus[issuestatusid]"];
	}

	$categories = array();

	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectid = $project[projectid]
		ORDER BY displayorder
	");

	while ($category = $db->fetch_array($category_data))
	{
		$categories["$category[projectcategoryid]"] = $category['title'];
	}

	print_form_header('project', 'projectupdate');

	if ($project['projectid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_project_x'], $project['title_clean']));
	}
	else
	{
		print_table_header($vbphrase['add_project']);
	}

	print_input_row("$vbphrase[title]<dfn>$vbphrase[html_is_allowed]</dfn>", 'title', $project['title']);
	print_input_row("$vbphrase[summary]<dfn>$vbphrase[html_is_allowed]</dfn>", 'summary', $project['summary']);
	print_textarea_row("$vbphrase[description]<dfn>$vbphrase[html_is_allowed]</dfn>", 'description', $project['description'], 6, 60);
	print_yes_no_row($vbphrase['send_email_on_issueassignment'], 'options[emailonassignment]', (intval($project['options']) & $vbulletin->bf_misc['pt_projectoptions']['emailonassignment'] ? 1 : 0));
	print_yes_no_row($vbphrase['send_pm_on_issueassignment'], 'options[pmonassignment]', (intval($project['options']) & $vbulletin->bf_misc['pt_projectoptions']['pmonassignment'] ? 1 : 0));
	print_input_row($vbphrase['display_order'], 'displayorder', $project['displayorder'], true, 5);

	$required = array(
		0 => $vbphrase['required_not_use'],
		1 => $vbphrase['required_default_not_user_value'],
		2 => $vbphrase['required_not_required'],
		3 => $vbphrase['required_required']
	);

	print_select_row($vbphrase['required_requireappliesversion'], 'requireappliesversion', $required, $project['requireappliesversion']);
	print_select_row($vbphrase['required_requirecategory'], 'requirecategory', $required, $project['requirecategory']);
	print_select_row($vbphrase['required_requirepriority'], 'requirepriority', $required, $project['requirepriority']);

	$afterforumids = explode(',', $project['afterforumids']);

	if ($project['afterforumids'] === '' OR !$afterforumids OR in_array(-1, $afterforumids))
	{
		$afterforumids = array(-1);
	}

	print_forum_chooser($vbphrase['display_after_forums'], 'afterforumids[]', $afterforumids, $vbphrase['none'], false, true);
	print_input_row($vbphrase['title_in_forum_list'], 'forumtitle', $project['forumtitle']);

	if (!$project['projectid'])
	{
		// base permissions on an existing project
		$projects = array();

		$project_query = $db->query_read("
			SELECT projectid, title_clean
			FROM " . TABLE_PREFIX . "pt_project
			ORDER BY displayorder
		");

		while ($proj = $db->fetch_array($project_query))
		{
			$projects["$proj[projectid]"] = $proj['title_clean'];
		}

		print_select_row($vbphrase['base_permissions_off_existing_project'], 'permissionbase', array('0' => $vbphrase['none_meta']) + $projects);
	}

	// available issue types
	print_description_row($vbphrase['available_issue_types'], false, 2, 'thead', 'center', 'available_issue_types');
	print_description_row($vbphrase['select_start_status_for_types_available']);

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses["$status[issuetypeid]"]["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	$types = $db->query_read("
		SELECT issuetype.*, projecttype.startstatusid
		FROM " . TABLE_PREFIX . "pt_issuetype AS issuetype
		LEFT JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON (projecttype.projectid = $project[projectid] AND projecttype.issuetypeid = issuetype.issuetypeid)
		ORDER BY issuetype.displayorder
	");

	while ($type = $db->fetch_array($types))
	{
		$typestatus = array(0 => $vbphrase['do_not_use_meta']);

		if (is_array($statuses["$type[issuetypeid]"]))
		{
			$typestatus += $statuses["$type[issuetypeid]"];
		}

		print_select_row(
			$vbphrase["issuetype_$type[issuetypeid]_plural"],
			"startstatus[$type[issuetypeid]]",
			$typestatus,
			$type['startstatusid']
		);
	}

	construct_hidden_code('projectid', $project['projectid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectkill')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$projectdata =& datamanager_init('Pt_Project', $vbulletin, ERRTYPE_CP);
	$projectdata->set_existing($project);
	$projectdata->delete();

	define('CP_REDIRECT', 'project.php?do=projectlist');
	print_stop_message('project_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectdelete')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_delete_confirmation('pt_project', $project['projectid'], 'project', 'projectkill');
}

// ########################################################################
if ($_POST['do'] == 'projectdisplayorder')
{
	$vbulletin->input->clean_gpc('p', 'order', TYPE_ARRAY_UINT);

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $projectid => $displayorder)
	{
		$case .= "\nWHEN " . intval($projectid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_project SET
				displayorder = CASE projectid $case ELSE displayorder END
		");
	}

	build_project_cache();

	define('CP_REDIRECT', 'project.php?do=projectlist');
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectlist')
{
	$projects = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY displayorder
	");

	print_form_header('project', 'projectdisplayorder');
	print_table_header($vbphrase['project_list'], 3);

	print_cells_row(array(
		$vbphrase['project'],
		$vbphrase['display_order'],
		'&nbsp;'
	), true);

	if ($db->num_rows($projects))
	{
		while ($project = $db->fetch_array($projects))
		{
			print_cells_row(array(
				$project['title'],
				"<input type=\"text\" class=\"bginput\" name=\"order[$project[projectid]]\" value=\"$project[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'project.php?do=projectedit&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['delete'], 'project.php?do=projectdelete&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['priorities'], 'projectpriority.php?do=projectpriority&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['categories'], 'projectcategory.php?do=projectcategory&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['versions'], 'projectversion.php?do=projectversion&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['milestones'], 'projectmilestone.php?do=projectmilestone&amp;projectid=' . $project['projectid']) .
				'</div>'
			));
		}
	}
	else
	{
		print_description_row(
			$vbphrase['no_projects_defined_click_here_to_add_one'],
			false,
			3,
			'',
			'center'
		);
	}

	print_submit_row($vbphrase['save_display_order'], '', 3);

	echo '<p align="center">' . construct_link_code($vbphrase['add_project'], 'project.php?do=projectadd') . ' | ' . construct_link_code($vbphrase['project_tools_options'], 'options.php?do=options&amp;dogroup=projecttools') . '</p>';
}

print_cp_footer();

?>