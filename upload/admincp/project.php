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

print_cp_header($vbphrase['project_tools'], iif(in_array($_REQUEST['do'], array('statusedit', 'statusadd', 'projectpriorityedit', 'projectpriorityadd')) , 'init_color_preview()'));

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
if ($_REQUEST['do'] == 'counters')
{
	print_form_header('project', 'issuecounters');
	print_table_header($vbphrase['rebuild_issue_counters']);
	print_description_row($vbphrase['rebuilding_issue_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('project', 'projectcounters');
	print_table_header($vbphrase['rebuild_project_counters']);
	print_description_row($vbphrase['rebuilding_project_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('project', 'milestonecounters');
	print_table_header($vbphrase['rebuild_milestone_counters']);
	print_description_row($vbphrase['rebuilding_milestone_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('project', 'profileissuecounters');
	print_table_header($vbphrase['rebuild_profile_issue_counters']);
	print_description_row($vbphrase['rebuilding_profile_issue_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');
}

// ########################################################################
if ($_REQUEST['do'] == 'issuecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	$vbulletin->input->clean_gpc('r', 'start', TYPE_UINT);
	$perpage = 250;

	$issues = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issue
		LIMIT " . $vbulletin->GPC['start'] . ", $perpage
	");

	$haveissues = false;

	while ($issue = $db->fetch_array($issues))
	{
		$haveissues = true;
		$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_SILENT);
		$issuedata->set_existing($issue);
		$issuedata->rebuild_issue_counters();
		$issuedata->save();
		unset($issuedata);

		echo ' . '; vbflush();
	}

	if ($haveissues)
	{
		print_cp_redirect('project.php?do=issuecounters&start=' . ($vbulletin->GPC['start'] + $perpage));
	}
	else
	{
		define('CP_REDIRECT', 'project.php?do=counters');
		print_stop_message('counters_rebuilt');
	}
}

// ########################################################################
if ($_POST['do'] == 'projectcounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_project_counters(true);

	define('CP_REDIRECT', 'project.php?do=counters');
	print_stop_message('counters_rebuilt');
}

// ########################################################################
if ($_REQUEST['do'] == 'milestonecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_milestone_counters(true);

	define('CP_REDIRECT', 'project.php?do=counters');
	print_stop_message('counters_rebuilt');
}

// ########################################################################
if ($_REQUEST['do'] == 'profileissuecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_profile_issue_counters();

	define('CP_REDIRECT', 'project.php?do=counters');
	print_stop_message('counters_rebuilt');
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
// ########################### TAG MANAGEMENT #############################
// ########################################################################
if ($_POST['do'] == 'taginsert')
{
	$vbulletin->input->clean_gpc('p', 'tagtext', TYPE_STR);

	if ($db->query_first("
		SELECT tagid
		FROM " . TABLE_PREFIX . "pt_tag
		WHERE tagtext = '" . $db->escape_string($vbulletin->GPC['tagtext']) . "'
	"))
	{
		print_stop_message('tag_exists');
	}

	$db->query_write("
		INSERT IGNORE INTO " . TABLE_PREFIX . "pt_tag
			(tagtext)
		VALUES
			('" . $db->escape_string($vbulletin->GPC['tagtext']) . "')
	");

	define('CP_REDIRECT', 'project.php?do=taglist');
	print_stop_message('tag_saved');
}

// ########################################################################
if ($_POST['do'] == 'tagkill')
{
	$vbulletin->input->clean_gpc('p', 'tag', TYPE_ARRAY_KEYS_INT);

	if ($vbulletin->GPC['tag'])
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_tag
			WHERE tagid IN (" . implode(',', $vbulletin->GPC['tag']) . ")
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issuetag
			WHERE tagid IN (" . implode(',', $vbulletin->GPC['tag']) . ")
		");
	}

	define('CP_REDIRECT', 'project.php?do=taglist');
	print_stop_message('tags_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'taglist')
{
	$vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);

	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	$column_count = 3;
	$max_per_column = 15;

	$perpage = $column_count * $max_per_column;
	$start = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

	$tags = $db->query_read("
		SELECT SQL_CALC_FOUND_ROWS *
		FROM " . TABLE_PREFIX . "pt_tag
		ORDER BY tagtext
		LIMIT $start, $perpage
	");

	list($tag_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

	print_form_header('project', 'tagkill');
	print_table_header($vbphrase['tag_list'], 3);

	if ($db->num_rows($tags))
	{
		$columns = array();
		$counter = 0;

		// build page navigation
		$total_pages = ceil($tag_count / $perpage);

		if ($total_pages > 1)
		{
			$pagenav = '<strong>' . $vbphrase['go_to_page'] . '</strong>';

			for ($thispage = 1; $thispage <= $total_pages; $thispage++)
			{
				if ($thispage == $vbulletin->GPC['pagenumber'])
				{
					$pagenav .= " <strong>[$thispage]</strong> ";
				}
				else
				{
					$pagenav .= " <a href=\"project.php?$session[sessionurl]do=taglist&amp;page=$thispage\" class=\"normal\">$thispage</a> ";
				}
			}

			print_description_row($pagenav, false, 3, 'thead', 'right');
		}

		// build columns
		while ($tag = $db->fetch_array($tags))
		{
			$columnid = floor($counter++ / $max_per_column);
			$columns["$columnid"][] = '<input type="checkbox" name="tag[' . $tag['tagid'] . ']" id="tag' . $tag['tagid'] . '_1" value="1" tabindex="1" /> ' . $tag['tagtext'];
		}

		// make column values printable
		$cells = array();

		for ($i = 0; $i < $column_count; $i++)
		{
			if ($columns["$i"])
			{
				$cells[] = implode("<br />\n", $columns["$i"]);
			}
			else
			{
				$cells[] = '&nbsp;';
			}
		}

		print_column_style_code(array(
			'width: 33%',
			'width: 33%',
			'width: 34%'
		));
		print_cells_row($cells, false, false, -3);
		print_submit_row($vbphrase['delete_selected'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_tags_defined'], false, 3, '', 'center');
		print_table_footer();
	}

	print_form_header('project', 'taginsert');
	print_input_row($vbphrase['add_tag'], 'tagtext');
	print_submit_row();
}

// ########################################################################
// ######################## MILESTONE MANAGEMENT ##########################
// ########################################################################
if ($_POST['do'] == 'projectmilestoneupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'milestoneid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'title' => TYPE_STR,
		'description' => TYPE_STR,
		'targetdate' => TYPE_UNIXTIME,
		'completeddate' => TYPE_UNIXTIME
	));

	if ($vbulletin->GPC['milestoneid'])
	{
		$milestone = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
		);

		$vbulletin->GPC['projectid'] = $milestone['projectid'];
	}
	else
	{
		$milestone = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_CP);

	if ($milestone['milestoneid'])
	{
		$milestonedata->set_existing($milestone);
	}
	else
	{
		$milestonedata->set('projectid', $project['projectid']);
	}

	$milestonedata->set_info('title', $vbulletin->GPC['title']);
	$milestonedata->set_info('description', $vbulletin->GPC['description']);
	$milestonedata->set('targetdate', $vbulletin->GPC['targetdate']);
	$milestonedata->set('completeddate', $vbulletin->GPC['completeddate']);
	$milestonedata->save();	

	define('CP_REDIRECT', 'project.php?do=projectmilestone&projectid=' . $project['projectid']);
	print_stop_message('project_milestone_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestoneadd' OR $_REQUEST['do'] == 'projectmilestoneedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'milestoneid' => TYPE_UINT
	));

	if ($vbulletin->GPC['milestoneid'])
	{
		$milestone = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
		);

		$vbulletin->GPC['projectid'] = $milestone['projectid'];
	}
	else
	{
		$milestone = array(
			'milestoneid' => 0,
			'title' => '',
			'description' => '',
			'targetdate' => 0,
			'completeddate' => 0
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('project', 'projectmilestoneupdate');

	if ($milestone['milestoneid'])
	{
		print_table_header($vbphrase['edit_milestone']);
		$trans_link_base = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=milestone_"; // has ID appended
		$trans_link1 = "_name";
		$trans_link2 = "_description"; // has ID appended
	}
	else
	{
		print_table_header($vbphrase['add_milestone']);
		$trans_link_base = '';
		$trans_link1 = '';
		$trans_link2 = '';
	}

	print_input_row("$vbphrase[title]<dfn>$vbphrase[html_is_allowed]</dfn>" . ($trans_link_base ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link_base . $milestone['milestoneid'] . $trans_link_1, true) . '</dfn>' : '') . "", 'title', $vbphrase['milestone_' . $milestone['milestoneid'] . '_name']);
	print_textarea_row("$vbphrase[description]<dfn>$vbphrase[html_is_allowed]</dfn>" . ($trans_link_base ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link_base . $milestone['milestoneid'] . $trans_link_2, true) . '</dfn>' : '') . "", 'description', $vbphrase['milestone_' . $milestone['milestoneid'] . '_description']);
	print_time_row("$vbphrase[target_date]<dfn>$vbphrase[target_date_desc]</dfn>", 'targetdate', $milestone['targetdate'], false);
	print_time_row("$vbphrase[completed_date]<dfn>$vbphrase[completed_date_desc]</dfn>", 'completeddate', $milestone['completeddate'], false);

	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('milestoneid', $milestone['milestoneid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectmilestonekill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'milestoneid' => TYPE_UINT,
		'destmilestoneid' => TYPE_UINT
	));

	$milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
	);

	$project = fetch_project_info($milestone['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			milestoneid = " . $vbulletin->GPC['destmilestoneid'] . "
		WHERE milestoneid = $milestone[milestoneid]
	");

	$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_CP);
	$milestonedata->set_existing($milestone);
	$milestonedata->delete();

	// rebuild the counters for the target milestone
	$dest_milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['destmilestoneid']
	);

	if ($dest_milestone)
	{
		$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_SILENT);
		$milestonedata->set_existing($dest_milestone);
		$milestonedata->rebuild_milestone_counters();
		$milestonedata->save();
	}

	define('CP_REDIRECT', 'project.php?do=projectmilestone&projectid=' . $project['projectid']);
	print_stop_message('project_milestone_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestonedelete')
{
	$vbulletin->input->clean_gpc('r', 'milestoneid', TYPE_UINT);

	$milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
	);

	$project = fetch_project_info($milestone['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	require_once(DIR . '/includes/functions_pt_posting.php');
	$milestones = fetch_milestone_select_list($project['projectid'], array($milestone['milestoneid']));

	print_delete_confirmation(
		'pt_milestone',
		$milestone['milestoneid'],
		'project', 'projectmilestonekill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_milestone'] .
			'<select name="destmilestoneid">' . construct_select_options($milestones) . '</select>',
		'title'
	);
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestone')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$milestones = array();

	$milestone_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE projectid = $project[projectid]
		ORDER BY completeddate, targetdate DESC
	");

	while ($milestone = $db->fetch_array($milestone_data))
	{
		$milestones["$milestone[milestoneid]"] = $milestone;
	}

	$lastcompleted = null;

	print_form_header();
	print_table_header(construct_phrase($vbphrase['milestones_for_x_html'], $project['title_clean']), 3);

	if ($milestones)
	{
		foreach ($milestones AS $milestone)
		{
			if ($lastcompleted !== $milestone['completeddate'])
			{
				if ($milestone['completeddate'] == 0)
				{
					print_cells_row(array(
						$vbphrase['active_milestones'],
						$vbphrase['target_date'],
						'&nbsp;'
					), true);
				}
				else if ($lastcompleted == 0 AND $milestone['completeddate'] != 0)
				{
					print_cells_row(array(
						$vbphrase['completed_milestones'],
						$vbphrase['completed_date'],
						'&nbsp;'
					), true);
				}

				$lastcompleted = $milestone['completeddate'];
			}

			if ($milestone['completeddate'])
			{
				$formatted_date = vbdate($vbulletin->options['dateformat'], $milestone['completeddate']);
			}
			else if ($milestone['targetdate'])
			{
				$formatted_date = vbdate($vbulletin->options['dateformat'], $milestone['targetdate']);
			}
			else
			{
				$formatted_date = $vbphrase['n_a'];
			}

			print_cells_row(array(
				$vbphrase['milestone_' . $milestone['milestoneid'] . '_name'],
				$formatted_date,
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'project.php?do=projectmilestoneedit&amp;milestoneid=' . $milestone['milestoneid']) .
					construct_link_code($vbphrase['delete'], 'project.php?do=projectmilestonedelete&amp;milestoneid=' . $milestone['milestoneid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
	}
	else
	{
		print_description_row($vbphrase['no_milestones_defined_for_this_project'], false, 3, '', 'center');
	}

	print_table_footer();

	echo '<p align="center">' . construct_link_code($vbphrase['add_milestone'], 'project.php?do=projectmilestoneadd&amp;projectid=' . $project['projectid']) . '</p>';

}

// ########################################################################
// ##################### ISSUE STATUS MANAGEMENT ##########################
// ########################################################################
if ($_POST['do'] == 'statusupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuestatusid' => TYPE_UINT,
		'title' => TYPE_STR,
		'issuetypeid' => TYPE_STR,
		'displayorder' => TYPE_UINT,
		'canpetitionfrom' => TYPE_UINT,
		'issuecompleted' => TYPE_UINT,
		'statuscolor' => TYPE_STR,
		'statuscolor2' => TYPE_STR,
		'projectset' => TYPE_ARRAY_UINT
	));

	if (empty($vbulletin->GPC['title']) OR empty($vbulletin->GPC['issuetypeid']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$statusdata =& datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['issuestatusid'])
	{
		$issuestatus = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
		);

		if (!$issuestatus)
		{
			print_stop_message('invalid_action_specified');
		}

		$statusdata->set_existing($issuestatus);
	}
	else
	{
		$statusdata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	}

	$statusdata->set('displayorder', $vbulletin->GPC['displayorder']);
	$statusdata->set('canpetitionfrom', $vbulletin->GPC['canpetitionfrom']);
	$statusdata->set('issuecompleted', $vbulletin->GPC['issuecompleted']);
	$statusdata->set('statuscolor', $vbulletin->GPC['statuscolor']);
	$statusdata->set('statuscolor2', $vbulletin->GPC['statuscolor2']);
	$statusdata->set_info('title', $vbulletin->GPC['title']);
	$issuestatusid = $statusdata->save();

	if (!$vbulletin->GPC['issuestatusid'])
	{
		$vbulletin->GPC['issuestatusid'] = $issuestatusid;
	}

	$add_projectsets = array();

	// Delete all projects for this issue status
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issuestatusprojectset
		WHERE issuestatusid = " . intval($vbulletin->GPC['issuestatusid']) . "
	");

	foreach ($vbulletin->GPC['projectset'] AS $projectsetid)
	{
		$add_projectsets[] = "(" . intval($vbulletin->GPC['issuestatusid']) . ", " . intval($projectsetid) . ")";
	}

	// Now add all checked projects
	if ($add_projectsets)
	{
		$db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "pt_issuestatusprojectset
				(issuestatusid, projectid)
			VALUES
				" . implode(',', $add_projectsets)
		);
	}

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('issue_status_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'statusadd' OR $_REQUEST['do'] == 'statusedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuestatusid' => TYPE_UINT,
		'type' => TYPE_STR
	));

	if ($vbulletin->GPC['issuestatusid'])
	{
		$issuestatus = $db->query_first("
			SELECT issuestatus.*, phrase.text AS title
			FROM " . TABLE_PREFIX . "pt_issuestatus AS issuestatus
			LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase ON
				(phrase.languageid = 0 AND phrase.fieldname = 'projecttools' AND phrase.varname = 'issuestatus" . $vbulletin->GPC['issuestatusid'] . "')
			WHERE issuestatus.issuestatusid = " . $vbulletin->GPC['issuestatusid']
		);
	}

	if (empty($issuestatus))
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['type']) . "'
		");

		$issuestatus = array(
			'issuestatusid' => 0,
			'issuetypeid' => $vbulletin->GPC['type'],
			'displayorder' => $maxorder['maxorder'] + 10,
			'canpetitionfrom' => 1,
			'issuecompleted' => 0,
			'title' => '',
			'statuscolor' => '',
			'statuscolor2' => '',
			'projectset' => '',
		);
	}

	echo '<script type="text/javascript" src="../clientscript/vbulletin_cpcolorpicker.js"></script>';

	print_form_header('project', 'statusupdate');

	if ($issuestatus['issuestatusid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_status_x'], $issuestatus['title']));
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=issuestatus"; // has ID appended
	}
	else
	{
		print_table_header($vbphrase['add_issue_status']);
		$trans_link = '';
	}

	print_input_row(
		$vbphrase['title'] . ($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $issuestatus['issuestatusid'], true) . '</dfn>' : ''),
		'title',
		$issuestatus['title']
	);

	if (isset($issuetype_options["$issuestatus[issuetypeid]"]))
	{
		print_label_row($vbphrase['issue_type'], $issuetype_options["$issuestatus[issuetypeid]"]);
		construct_hidden_code('issuetypeid', $issuestatus['issuetypeid']);
	}
	else
	{
		print_select_row($vbphrase['issue_type'], 'issuetypeid', $issuetype_options);
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $issuestatus['displayorder'], true, 5);
	print_yes_no_row($vbphrase['status_represents_completed_issue'], 'issuecompleted', $issuestatus['issuecompleted']);
	print_yes_no_row($vbphrase['can_create_petitions_from_this_status'], 'canpetitionfrom', $issuestatus['canpetitionfrom']);

	require_once(DIR . '/includes/adminfunctions_template.php');
	$colorPicker = construct_color_picker(11);

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt2\">" . $vbphrase['status_color_dark_styles'] . "</td>
		<td class=\"alt2\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td><input type=\"text\" class=\"bginput\" name=\"statuscolor\" id=\"color_0\" value=\"{$issuestatus['statuscolor']}\" title=\"statuscolor\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(0)\" dir=\"ltr\" />&nbsp;</td>
				<td><div id=\"preview_0\" class=\"colorpreview\" onclick=\"open_color_picker(0, event)\"></div></td>
			</tr>
			</table>
		</td>
	</tr>\n";

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt1\">" . $vbphrase['status_color_light_styles'] . "</td>
		<td class=\"alt1\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td><input type=\"text\" class=\"bginput\" name=\"statuscolor2\" id=\"color_1\" value=\"{$issuestatus['statuscolor2']}\" title=\"statuscolor2\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(1)\" dir=\"ltr\" />&nbsp;</td>
				<td><div id=\"preview_1\" class=\"colorpreview\" onclick=\"open_color_picker(1, event)\"></div></td>
			</tr>
			</table>
		</td>
	</tr>\n";

	$projectsets = '';

	$projectsets_sql = $vbulletin->db->query_read("
		SELECT pt.projectid, pt.title_clean, IF(ptset.projectid IS NULL, 0, 1) AS selected
		FROM " . TABLE_PREFIX . "pt_project AS pt
		LEFT JOIN " . TABLE_PREFIX . "pt_issuestatusprojectset AS ptset ON
			(ptset.projectid = pt.projectid AND ptset.issuestatusid = " . intval($issuestatus['issuestatusid']) . ")
		ORDER BY pt.displayorder
	");

	while ($projectset = $vbulletin->db->fetch_array($projectsets_sql))
	{
		$projectsets .= "<div class=\"smallfont\"><label>"
			. "<input type=\"checkbox\" name=\"projectset[]\" value=\"$projectset[projectid]\" tabindex=\"1\"" . ($projectset['selected'] ? ' checked="checked"' : '') . " />"
			. htmlspecialchars_uni($projectset['title_clean']) . "</label></div>";
	}

	if ($projectsets)
	{
		print_label_row($vbphrase['use_selected_project_sets'], $projectsets, 'alt2', 'top', 'projectset');
	}

	construct_hidden_code('issuestatusid', $issuestatus['issuestatusid']);
	print_submit_row();

	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = 2;
	var colorPickerWidth = 253;
	var colorPickerType = 0;

	//-->
	</script>
	<?php
}

// ########################################################################
if ($_POST['do'] == 'statuskill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuestatusid' => TYPE_UINT,
		'deststatusid' => TYPE_UINT,
	));

	$issuestatus = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
	);

	if (!$issuestatus)
	{
		print_stop_message('invalid_action_specified');
	}

	$statusdata =& datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);
	$statusdata->set_existing($issuestatus);
	$statusdata->set_info('delete_deststatusid', $vbulletin->GPC['deststatusid']);
	$statusdata->delete();

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('issue_status_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'statusdelete')
{
	$vbulletin->input->clean_gpc('r', 'issuestatusid', TYPE_UINT);

	$issuestatus = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
	);

	if (!$issuestatus)
	{
		print_stop_message('invalid_action_specified');
	}

	$statusdata =& datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);
	$statusdata->set_existing($issuestatus);
	$statusdata->pre_delete();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuetypeid = '" . $db->escape_string($issuestatus['issuetypeid']) . "'
			AND issuestatusid <> $issuestatus[issuestatusid]
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	print_delete_confirmation(
		'pt_issuestatus',
		$vbulletin->GPC['issuestatusid'],
		'project',
		'statuskill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_status'] .
			'<select name="deststatusid">' . construct_select_options($statuses) . '</select>'
	);
}

// ########################################################################
/*if ($_POST['do'] == 'statusdisplayorder')
{
	$vbulletin->input->clean_gpc('p', 'order', TYPE_ARRAY_UINT);

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $statusid => $displayorder)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				displayorder = CASE issuestatusid $case ELSE displayorder END
		");
	}

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('saved_display_order_successfully');
}*/

// ########################################################################
// ##################### ISSUE TYPE/STATUS MANAGEMENT #####################
// ########################################################################
if ($_POST['do'] == 'typeupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuetypeid' => TYPE_NOHTML,
		'exists' => TYPE_BOOL,

		'title_singular' => TYPE_STR,
		'title_plural' => TYPE_STR,
		'vote_question' => TYPE_STR,
		'vote_count_positive' => TYPE_STR,
		'vote_count_negative' => TYPE_STR,
		'applies_version' => TYPE_STR,
		'addressed_version' => TYPE_STR,
		'post_new_issue' => TYPE_STR,

		'displayorder' => TYPE_UINT,
		'iconfile' => TYPE_NOHTML,
		'permissionbase' => TYPE_NOHTML
	));

	$vbulletin->GPC['issuetypeid'] = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['issuetypeid']);

	if (empty($vbulletin->GPC['title_singular']) OR empty($vbulletin->GPC['title_plural']) OR empty($vbulletin->GPC['issuetypeid']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$typedata =& datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['exists'])
	{
		$issuetype = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuetype
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
		");

		if (!$issuetype)
		{
			print_stop_message('invalid_action_specified');
		}

		$typedata->set_existing($issuetype);
	}
	else
	{
		$typedata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	}

	$typedata->set('displayorder', $vbulletin->GPC['displayorder']);
	$typedata->set('iconfile', $vbulletin->GPC['iconfile']);

	$typedata->set_info('title_singular', $vbulletin->GPC['title_singular']);
	$typedata->set_info('title_plural', $vbulletin->GPC['title_plural']);
	$typedata->set_info('vote_question', $vbulletin->GPC['vote_question']);
	$typedata->set_info('vote_count_positive', $vbulletin->GPC['vote_count_positive']);
	$typedata->set_info('vote_count_negative', $vbulletin->GPC['vote_count_negative']);
	$typedata->set_info('applies_version', $vbulletin->GPC['applies_version']);
	$typedata->set_info('addressed_version', $vbulletin->GPC['addressed_version']);
	$typedata->set_info('post_new_issue', $vbulletin->GPC['post_new_issue']);

	$typedata->save();

	if (!$vbulletin->GPC['exists'] AND $vbulletin->GPC['permissionbase'])
	{
		$permissions = array();

		$permission_query = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpermission
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['permissionbase']) . "'
		");

		while ($permission = $db->fetch_array($permission_query))
		{
			$permissions[] = "
				($permission[usergroupid], $permission[projectid], '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "',
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

	build_assignable_users();
	build_pt_user_list('pt_report_users', 'pt_report_user_cache');

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('issue_type_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'typeadd' OR $_REQUEST['do'] == 'typeedit')
{
	$vbulletin->input->clean_gpc('r', 'issuetypeid', TYPE_NOHTML);

	if ($vbulletin->GPC['issuetypeid'])
	{
		$issuetype = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuetype
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
		");

		$phrases = array();

		$phrase_data = $db->query_read("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = 0
				AND varname IN (
					'issuetype_$issuetype[issuetypeid]_singular',
					'issuetype_$issuetype[issuetypeid]_plural',
					'vote_question_$issuetype[issuetypeid]',
					'vote_count_positive_$issuetype[issuetypeid]',
					'vote_count_negative_$issuetype[issuetypeid]',
					'applies_version_$issuetype[issuetypeid]',
					'addressed_version_$issuetype[issuetypeid]',
					'post_new_issue_$issuetype[issuetypeid]'
				)
		");

		while ($phrase = $db->fetch_array($phrase_data))
		{
			$phrases["$phrase[varname]"] = $phrase['text'];
		}

		$issuetype['title_singular'] = $phrases["issuetype_$issuetype[issuetypeid]_singular"];
	}

	if (empty($issuetype))
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_issuetype
		");

		$issuetype = array(
			'issuetypeid' => '',
			'displayorder' => $maxorder['maxorder'] + 10,
			'title_singular' => '',
			'title_plural' => '',
		);

		$phrases = array(
			'issuetype__singular' => '',
			'issuetype__plural' => '',
			'vote_question_' => '',
			'vote_count_positive_' => '',
			'vote_count_negative_' => '',
			'post_new_issue_' =>''
		);
	}

	print_form_header('project', 'typeupdate');

	if ($issuetype['issuetypeid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_type_x'], $issuetype['title_singular']));
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname="; // has ID appended

		print_label_row($vbphrase['issue_type_key_alphanumeric_only'], $issuetype['issuetypeid']);
		construct_hidden_code('issuetypeid', $issuetype['issuetypeid']);
		construct_hidden_code('exists', 1);
	}
	else
	{
		print_table_header($vbphrase['add_issue_type']);
		$trans_link = '';

		print_input_row($vbphrase['issue_type_key_alphanumeric_only'], 'issuetypeid');
		construct_hidden_code('exists', 0);
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $issuetype['displayorder'], true, 5);
	print_input_row($vbphrase['filename_for_icon'], 'iconfile', $issuetype['iconfile'], false);

	if (!$issuetype['issuetypeid'])
	{
		$types = array();
		$type_query = $db->query_read("
			SELECT issuetypeid
			FROM " . TABLE_PREFIX . "pt_issuetype
			ORDER BY displayorder
		");

		while ($type = $db->fetch_array($type_query))
		{
			$types["$type[issuetypeid]"] = $vbphrase["issuetype_$type[issuetypeid]_singular"];
		}

		print_select_row($vbphrase['bass_permissions_off_existing_type'], 'permissionbase', array('' => $vbphrase['none_meta']) + $types);
	}

	print_description_row($vbphrase['phrases'], false, 2, 'thead');

	print_input_row(
		$vbphrase['singular_form_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "issuetype_$issuetype[issuetypeid]_singular", true) . '</dfn>' : ''),
		'title_singular',
		$phrases["issuetype_$issuetype[issuetypeid]_singular"]
	);

	print_input_row(
		$vbphrase['plural_form_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "issuetype_$issuetype[issuetypeid]_plural", true) . '</dfn>' : ''),
		'title_plural',
		$phrases["issuetype_$issuetype[issuetypeid]_plural"]
	);

	print_input_row(
		$vbphrase['vote_question_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_question_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_question',
		$phrases["vote_question_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['positive_vote_count_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_count_positive_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_count_positive',
		$phrases["vote_count_positive_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['negative_vote_count_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_count_negative_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_count_negative',
		$phrases["vote_count_negative_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['applicable_version_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "applies_version_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'applies_version',
		$phrases["applies_version_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['addressed_version_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "addressed_version_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'addressed_version',
		$phrases["addressed_version_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['post_new_issue_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "post_new_issue_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'post_new_issue',
		$phrases["post_new_issue_$issuetype[issuetypeid]"]
	);

	print_submit_row();

	if (!$issuetype['issuetypeid'])
	{
		echo '<p align="center" class="smallfont">' . $vbphrase['need_manually_select_projects_type'] . '</p>';
	}

}

// ########################################################################
if ($_POST['do'] == 'typekill')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuetypeid' => TYPE_NOHTML,
		'deststatusid' => TYPE_UINT
	));

	$issuetype = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
	");

	if (!$issuetype)
	{
		print_stop_message('invalid_action_specified');
	}

	$typedata =& datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);
	$typedata->set_existing($issuetype);
	$typedata->set_info('delete_deststatusid', $vbulletin->GPC['deststatusid']);
	$typedata->delete();

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('issue_type_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'typedelete')
{
	$vbulletin->input->clean_gpc('r', 'issuetypeid', TYPE_NOHTML);

	$issuetype = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
	");

	if (!$issuetype)
	{
		print_stop_message('invalid_action_specified');
	}

	$typedata =& datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);
	$typedata->set_existing($issuetype);
	$typedata->pre_delete();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuetypeid <> '" . $db->escape_string($issuetype['issuetypeid']) . "'
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses[$vbphrase["issuetype_$status[issuetypeid]_singular"]]["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	print_delete_confirmation(
		'pt_issuetype',
		$vbulletin->GPC['issuetypeid'],
		'project',
		'typekill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_status'] .
			'<select name="deststatusid">' . construct_select_options($statuses) . '</select>'
	);
}

// ########################################################################
if ($_POST['do'] == 'typedisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => TYPE_ARRAY_UINT,
		'issuecompleted' => TYPE_ARRAY_BOOL
	));

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $statusid => $displayorder)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				displayorder = CASE issuestatusid $case ELSE displayorder END
		");
	}

	$case = '';

	foreach ($vbulletin->GPC['issuecompleted'] AS $statusid => $issuecompleted)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . ($issuecompleted ? 1 : 0);
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				issuecompleted = CASE issuestatusid $case ELSE 0 END
		");
	}

	build_issue_type_cache();
	rebuild_project_counters(false);
	rebuild_milestone_counters(false);

	define('CP_REDIRECT', 'project.php?do=typelist');
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'typelist')
{
	print_form_header('', '');
	print_table_header($vbphrase['issue_type_manager']);
	print_description_row(
		'<a href="#" onclick="js_open_help(\'project\', \'typelist\', \'\'); return false;">[' . $vbphrase['help'] . ']</a> | ' . construct_link_code($vbphrase['add_issue_type'], 'project.php?do=typeadd'),
		false, 2, '', 'center'
	);
	print_table_footer();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses["$status[issuetypeid]"][] = $status;
	}

	$types = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		ORDER BY displayorder
	");

	print_form_header('project', 'typedisplayorder');

	$firstpass = true;

	while ($type = $db->fetch_array($types))
	{
		print_cells_row(array(
			$vbphrase['issue_type'] . ' <b>' . $vbphrase["issuetype_$type[issuetypeid]_plural"] . '</b>',
			'&nbsp;',
			'&nbsp;',
			'<b>' .
				construct_link_code($vbphrase['edit'], 'project.php?do=typeedit&amp;issuetypeid=' . $type['issuetypeid']) .
				construct_link_code($vbphrase['delete'], 'project.php?do=typedelete&amp;issuetypeid=' . $type['issuetypeid']) .
			'</b>'
		), false, 'tcat');

		print_cells_row(array(
			'<span class="normal">' . $vbphrase['status'] . '</span>',
			'<span class="normal">' . $vbphrase['display_order'] . '</span>',
			'<span class="normal">' . $vbphrase['issue_completed'] . '</span>',
			'<b>' . construct_link_code($vbphrase['add_status'], 'project.php?do=statusadd&amp;type=' . $type['issuetypeid']) . '</b>'
		), true);

		if (!empty($statuses["$type[issuetypeid]"]))
		{
			foreach ($statuses["$type[issuetypeid]"] AS $status)
			{
				print_cells_row(array(
					$vbphrase["issuestatus$status[issuestatusid]"],
					"<input type=\"text\" class=\"bginput\" name=\"order[$status[issuestatusid]]\" value=\"$status[displayorder]\" tabindex=\"1\" size=\"3\" />",
					'<input type="checkbox" name="issuecompleted[' . $status['issuestatusid'] . ']" value="1" ' . ($status['issuecompleted'] ? 'checked="checked"' : '') . ' />',
					"<div align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" class=\"smallfont\">" .
						construct_link_code($vbphrase['edit'], 'project.php?do=statusedit&amp;issuestatusid=' . $status['issuestatusid']) .
						construct_link_code($vbphrase['delete'], 'project.php?do=statusdelete&amp;issuestatusid=' . $status['issuestatusid']) .
					'</div>'
				));
			}
		}
		else
		{
			print_description_row(
				construct_phrase($vbphrase['no_statuses_of_this_type_defined_click_here_to_add'], $type['issuetypeid']),
				false,
				4,
				'',
				'center'
			);
		}
	}

	print_submit_row($vbphrase['save_changes'], '', 4);
}

// ########################################################################
// ################### PROJECT VERSION MANAGEMENT #########################
// ########################################################################
if ($_POST['do'] == 'projectversionupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectversionid' => TYPE_UINT,
		'projectversiongroupid' => TYPE_UINT,
		'versionname' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT,
		'nextversion' => TYPE_BOOL
	));

	if ($vbulletin->GPC['projectversionid'])
	{
		$projectversion = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
		);

		$vbulletin->GPC['projectversiongroupid'] = $projectversion['projectversiongroupid'];
	}
	else
	{
		$projectversion = array();
	}

	$projectversiongroup = $db->query_first("
		SELECT pt_projectversiongroup.*
		FROM " . TABLE_PREFIX . "pt_projectversiongroup AS pt_projectversiongroup
		INNER JOIN " . TABLE_PREFIX . "pt_project AS pt_project ON (pt_project.projectid = pt_projectversiongroup.projectid)
		WHERE pt_projectversiongroup.projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	if (!$projectversiongroup)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['versionname']))
	{
		print_stop_message('please_complete_required_fields');
	}

	// effective order means that sorting just the version table will return versions ordered by group first
	if ($projectversion['projectversionid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversion SET
				versionname = '" . $db->escape_string($vbulletin->GPC['versionname']) . "',
				displayorder = " . $vbulletin->GPC['displayorder'] . ",
				effectiveorder = " . ($vbulletin->GPC['displayorder'] + $projectversiongroup['displayorder'] * 100000) . "
			WHERE projectversionid = $projectversion[projectversionid]
		");
	}
	else
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectversion
				(projectid, versionname, projectversiongroupid, displayorder, effectiveorder)
			VALUES
				($projectversiongroup[projectid],
				'" . $db->escape_string($vbulletin->GPC['versionname']) . "',
				$projectversiongroup[projectversiongroupid],
				" . $vbulletin->GPC['displayorder'] . ",
				" . ($vbulletin->GPC['displayorder'] + $projectversiongroup['displayorder'] * 100000) . ")
		");

		$projectversionid = $db->insert_id();

		if ($vbulletin->GPC['nextversion'])
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = $projectversionid
				WHERE projectid = $projectversiongroup[projectid]
					AND isaddressed = 1
					AND addressedversionid = 0
			");
		}
	}

	build_version_cache();

	define('CP_REDIRECT', 'project.php?do=projectversion&projectid=' . $projectversiongroup['projectid']);
	print_stop_message('project_version_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversionadd' OR $_REQUEST['do'] == 'projectversionedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectversiongroupid' => TYPE_UINT,
		'projectversionid' => TYPE_UINT,
	));

	if ($vbulletin->GPC['projectversionid'])
	{
		$projectversion = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
		);

		$vbulletin->GPC['projectversiongroupid'] = $projectversion['projectversiongroupid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$projectversion = array(
			'projectversionid' => 0,
			'displayorder' => $maxorder['maxorder'] + 10
		);
	}

	$projectversiongroup = $db->query_first("
		SELECT pt_projectversiongroup.*
		FROM " . TABLE_PREFIX . "pt_projectversiongroup AS pt_projectversiongroup
		INNER JOIN " . TABLE_PREFIX . "pt_project AS pt_project ON (pt_project.projectid = pt_projectversiongroup.projectid)
		WHERE pt_projectversiongroup.projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	if (!$projectversiongroup)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('project', 'projectversionupdate');

	if ($projectversion['projectversionid'])
	{
		print_table_header($vbphrase['edit_project_version']);
	}
	else
	{
		print_table_header($vbphrase['add_project_version']);
	}

	print_label_row($vbphrase['version_group'], $projectversiongroup['groupname']);
	print_input_row($vbphrase['title'], 'versionname', $projectversion['versionname'], false);
	print_input_row($vbphrase['display_order'] . '<dfn>' . $vbphrase['note_a_larger_value_will_be_displayed_first'] . '</dfn>', 'displayorder', $projectversion['displayorder'], true, 5);

	if (!$projectversion['projectversionid'])
	{
		print_yes_no_row($vbphrase['denote_as_next_version'], 'nextversion', 0);
	}

	construct_hidden_code('projectversionid', $projectversion['projectversionid']);
	construct_hidden_code('projectversiongroupid', $projectversiongroup['projectversiongroupid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectversionkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectversionid' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT
	));

	$projectversion = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
	);

	$project = fetch_project_info($projectversion['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = $projectversion[projectversionid]
	");

	// updated applies version
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			appliesversionid = " . $vbulletin->GPC['appliesversionid'] . "
		WHERE appliesversionid = $projectversion[projectversionid]
	");

	// update addressed version
	if ($vbulletin->GPC['addressedversionid'] == -1)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = 0,
				isaddressed = 1
			WHERE addressedversionid = $projectversion[projectversionid]
		");
	}
	else if ($vbulletin->GPC['addressedversionid'] == 0)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = 0,
				isaddressed = 0
			WHERE addressedversionid = $projectversion[projectversionid]
		");
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = " . $vbulletin->GPC['addressedversionid'] . ",
				isaddressed = 1
			WHERE addressedversionid = $projectversion[projectversionid]
		");
	}

	build_version_cache();

	define('CP_REDIRECT', 'project.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiondelete')
{
	$vbulletin->input->clean_gpc('r', 'projectversionid', TYPE_UINT);

	$projectversion = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
	);

	$project = fetch_project_info($projectversion['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
		INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = $project[projectid]
			AND projectversion.projectversionid <> " . $vbulletin->GPC['projectversionid'] . "
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$applies_version = array(0 => $vbphrase['unknown']) + $version_groups;
	$addressed_version = array(0 => $vbphrase['none_meta'], '-1' => $vbphrase['next_release']) + $version_groups;

	print_delete_confirmation(
		'pt_projectversion',
		$projectversion['projectversionid'],
		'project', 'projectversionkill',
		'',
		0,
		construct_phrase($vbphrase['existing_affected_issues_updated_delete_select_versions_x_y'],
			'<select name="appliesversionid">' . construct_select_options($applies_version, 0) . '</select>',
			'<select name="addressedversionid">' . construct_select_options($addressed_version, -1) . '</select>'
		),
		'versionname'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectversiongroupupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectversiongroupid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'groupname' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectversiongroupid'])
	{
		$projectversiongroup = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);
		$vbulletin->GPC['projectid'] = $projectversiongroup['projectid'];
	}
	else
	{
		$projectversiongroup = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['groupname']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($projectversiongroup['projectversiongroupid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversiongroup SET
				groupname = '" . $db->escape_string($vbulletin->GPC['groupname']) . "',
				displayorder = " . $vbulletin->GPC['displayorder'] . "
			WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
		");
	}
	else
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectversiongroup
				(projectid, groupname, displayorder)
			VALUES
				($project[projectid],
				'" . $db->escape_string($vbulletin->GPC['groupname']) . "',
				" . $vbulletin->GPC['displayorder'] . ")
		");
	}

	build_version_cache();

	define('CP_REDIRECT', 'project.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiongroupadd' OR $_REQUEST['do'] == 'projectversiongroupedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'projectversiongroupid' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectversiongroupid'])
	{
		$projectversiongroup = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$vbulletin->GPC['projectid'] = $projectversiongroup['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectversiongroup = array(
			'projectversiongroupid' => 0,
			'displayorder' => $maxorder['maxorder'] + 10
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('project', 'projectversiongroupupdate');

	if ($projectversiongroup['projectversiongroupid'])
	{
		print_table_header($vbphrase['edit_project_version_group']);
	}
	else
	{
		print_table_header($vbphrase['add_project_version_group']);
	}

	print_input_row($vbphrase['title'], 'groupname', $projectversiongroup['groupname'], false);
	print_input_row($vbphrase['display_order'] . '<dfn>' . $vbphrase['note_a_larger_value_will_be_displayed_first'] . '</dfn>', 'displayorder', $projectversiongroup['displayorder'], true, 5);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectversiongroupid', $projectversiongroup['projectversiongroupid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectversiongroupkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectversiongroupid' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT
	));

	$projectversiongroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	$project = fetch_project_info($projectversiongroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$group_versions = array();

	$group_version_data = $db->query_read("
		SELECT projectversionid
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	while ($group_version = $db->fetch_array($group_version_data))
	{
		$group_versions[] = $group_version['projectversionid'];
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	if ($group_versions)
	{
		// updated applies version
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				appliesversionid = " . $vbulletin->GPC['appliesversionid'] . "
			WHERE appliesversionid IN (" . implode(',', $group_versions) . ")
		");

		// update addressed version
		if ($vbulletin->GPC['addressedversionid'] == -1)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = 0,
					isaddressed = 1
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
		else if ($vbulletin->GPC['addressedversionid'] == 0)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = 0,
					isaddressed = 0
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = " . $vbulletin->GPC['addressedversionid'] . ",
					isaddressed = 1
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
	}

	build_version_cache();

	define('CP_REDIRECT', 'project.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiongroupdelete')
{
	$vbulletin->input->clean_gpc('r', 'projectversiongroupid', TYPE_UINT);

	$projectversiongroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	$project = fetch_project_info($projectversiongroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
		INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = $project[projectid]
			AND projectversiongroup.projectversiongroupid <> " . $vbulletin->GPC['projectversiongroupid'] . "
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$applies_version = array(0 => $vbphrase['unknown']) + $version_groups;
	$addressed_version = array(0 => $vbphrase['none_meta'], '-1' => $vbphrase['next_release']) + $version_groups;

	print_delete_confirmation(
		'pt_projectversiongroup',
		$projectversiongroup['projectversiongroupid'],
		'project', 'projectversiongroupkill',
		'',
		0,
		construct_phrase($vbphrase['existing_affected_issues_updated_delete_select_versions_x_y'],
			'<select name="appliesversionid">' . construct_select_options($applies_version, 0) . '</select>',
			'<select name="addressedversionid">' . construct_select_options($addressed_version, -1) . '</select>'
		),
		'groupname'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectversiondisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'versionorder' => TYPE_ARRAY_UINT,
		'grouporder' => TYPE_ARRAY_UINT,
		'projectid' => TYPE_UINT
	));

	$groupcase = '';
	$grouporder = array();

	foreach ($vbulletin->GPC['grouporder'] AS $id => $displayorder)
	{
		$grouporder[intval($id)] = $displayorder;
		$groupcase .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($groupcase)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversiongroup SET
				displayorder = CASE projectversiongroupid $groupcase ELSE displayorder END
		");
	}

	$versioncase_display = '';

	foreach ($vbulletin->GPC['versionorder'] AS $id => $displayorder)
	{
		$versioncase_display .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($versioncase_display)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversion AS projectversion
			INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
				(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
			SET
				projectversion.displayorder = CASE projectversion.projectversionid $versioncase_display ELSE projectversion.displayorder END,
				projectversion.effectiveorder = projectversion.displayorder + (projectversiongroup.displayorder * 100000)
		");
	}

	define('CP_REDIRECT', 'project.php?do=projectversion&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversion')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$groups = array();

	$group_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectid = $project[projectid]
		ORDER BY displayorder DESC
	");

	while ($group = $db->fetch_array($group_data))
	{
		$groups["$group[projectversiongroupid]"] = $group;
	}

	$versions = array();

	$version_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectid = $project[projectid]
		ORDER BY displayorder DESC
	");

	while ($version = $db->fetch_array($version_data))
	{
		$versions["$version[projectversiongroupid]"][] = $version;
	}

	print_form_header('project', 'projectversiondisplayorder');
	print_table_header(construct_phrase($vbphrase['project_versions_for_x'], $project['title_clean']), 3);

	if ($groups)
	{
		foreach ($groups AS $group)
		{
			print_cells_row(array(
				$group['groupname'],
				"<input type=\"text\" class=\"bginput\" name=\"grouporder[$group[projectversiongroupid]]\" value=\"$group[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="normal smallfont">' .
					construct_link_code($vbphrase['edit'], 'project.php?do=projectversiongroupedit&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					construct_link_code($vbphrase['delete'], 'project.php?do=projectversiongroupdelete&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					construct_link_code($vbphrase['add_version'], 'project.php?do=projectversionadd&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					'</div>',
			), 'thead');

			if (is_array($versions["$group[projectversiongroupid]"]))
			{
				foreach ($versions["$group[projectversiongroupid]"] AS $version)
				{
					print_cells_row(array(
						$version['versionname'],
						"<input type=\"text\" class=\"bginput\" name=\"versionorder[$version[projectversionid]]\" value=\"$version[displayorder]\" tabindex=\"1\" size=\"3\" />",
						'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
							construct_link_code($vbphrase['edit'], 'project.php?do=projectversionedit&amp;projectversionid=' . $version['projectversionid']) .
							construct_link_code($vbphrase['delete'], 'project.php?do=projectversiondelete&amp;projectversionid=' . $version['projectversionid']) .
						'</div>'
					));
				}
			}
			else
			{
				print_description_row(
					$vbphrase['no_versions_defined_in_this_group'],
					false,
					3,
					'',
					'center'
				);
			}
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_versions_groups_defined_project'], false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_version_group'], 'project.php?do=projectversiongroupadd&amp;projectid=' . $project['projectid']) . '</p>';
	echo '<p align="center" class="smallfont">' . $vbphrase['note_higer_display_orders_first'] . '</p>';
}

// ########################################################################
// ################### PROJECT PRIORITY MANAGEMENT ########################
// ########################################################################
if ($_POST['do'] == 'projectpriorityupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectpriorityid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'title' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT,
		'statuscolor' => TYPE_STR,
		'statuscolor2' => TYPE_STR,
	));

	if ($vbulletin->GPC['projectpriorityid'])
	{
		$projectpriority = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
		);
		$vbulletin->GPC['projectid'] = $projectpriority['projectid'];
	}
	else
	{
		$projectpriority = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($projectpriority['projectpriorityid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
				displayorder = " . $vbulletin->GPC['displayorder'] . "
				" . ($vbulletin->GPC['statuscolor'] ? ", statuscolor = '" . $db->escape_string($vbulletin->GPC['statuscolor']) . "'" : '') . "
				" . ($vbulletin->GPC['statuscolor2'] ? ", statuscolor2 = '" . $db->escape_string($vbulletin->GPC['statuscolor2']) . "'" : '') . "
			WHERE projectpriorityid = " . $projectpriority['projectpriorityid'] . "
		");

	// Phrase the category
	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, fieldname, varname, text, product, username, dateline, version)
		VALUES
			(
				0,
				'projecttools',
				'priority" . intval($vbulletin->GPC['projectpriorityid']) . "',
				'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
				'vbprojecttools',
				'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
				" . TIMENOW . ",
				'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
			)
	");
	}
	else
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectpriority
				(projectid, displayorder)
			VALUES
				($project[projectid],
				" . $vbulletin->GPC['displayorder'] . ")
		");

		$priorityid = $db->insert_id();

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'priority" . intval($priorityid) . "',
					'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
					'vbprojecttools',
					'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
				)
		");
	}

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_project_priority_cache();

	define('CP_REDIRECT', 'project.php?do=projectpriority&projectid=' . $project['projectid']);
	print_stop_message('project_priority_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectpriorityadd' OR $_REQUEST['do'] == 'projectpriorityedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'projectpriorityid' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectpriorityid'])
	{
		$projectpriority = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
		);
		$vbulletin->GPC['projectid'] = $projectpriority['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectpriority = array(
			'projectpriorityid' => 0,
			'title' => '',
			'displayorder' => $maxorder['maxorder'] + 10,
			'statuscolor' => ''
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	echo '<script type="text/javascript" src="../clientscript/vbulletin_cpcolorpicker.js"></script>';

	print_form_header('project', 'projectpriorityupdate');

	if ($projectpriority['projectpriorityid'])
	{
		print_table_header($vbphrase['edit_project_priority']);
		print_input_row($vbphrase['title'] . '<dfn>' . construct_link_code($vbphrase['translations'], 'phrase.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&amp;fieldname=projecttools&amp;t=1&amp;varname=priority' . $projectpriority['projectpriorityid'], true) . '</dfn>', 'title', $vbphrase['priority' . $projectpriority['projectpriorityid'] . ''], false);
	}
	else
	{
		print_table_header($vbphrase['add_project_priority']);
		print_input_row($vbphrase['title'], 'title', '', false);
	}


	print_input_row($vbphrase['display_order'], 'displayorder', $projectpriority['displayorder'], true, 5);

	require_once(DIR . '/includes/adminfunctions_template.php');
	$colorPicker = construct_color_picker(11);

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt1\">" . $vbphrase['severitycolor_darkstyles'] . "</td>
		<td class=\"alt1\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td><input type=\"text\" class=\"bginput\" name=\"statuscolor\" id=\"color_0\" value=\"{$projectpriority['statuscolor']}\" title=\"statuscolor\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(0)\" dir=\"ltr\" />&nbsp;</td>
				<td><div id=\"preview_0\" class=\"colorpreview\" onclick=\"open_color_picker(0, event)\"></div></td>
			</tr>
			</table>
		</td>
	</tr>\n";

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt2\">" . $vbphrase['severitycolor_lightstyles'] . "</td>
		<td class=\"alt2\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td><input type=\"text\" class=\"bginput\" name=\"statuscolor2\" id=\"color_1\" value=\"{$projectpriority['statuscolor2']}\" title=\"statuscolor2\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(1)\" dir=\"ltr\" />&nbsp;</td>
				<td><div id=\"preview_1\" class=\"colorpreview\" onclick=\"open_color_picker(1, event)\"></div></td>
			</tr>
			</table>
		</td>
	</tr>\n";

	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectpriorityid', $projectpriority['projectpriorityid']);
	print_submit_row();

	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = 2;
	var colorPickerWidth = 253;
	var colorPickerType = 0;

	//-->
	</script>
	<?php
}

// ########################################################################
if ($_POST['do'] == 'projectprioritykill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectpriorityid' => TYPE_UINT,
		'destpriorityid' => TYPE_UINT
	));

	$projectpriority = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
	);

	$project = fetch_project_info($projectpriority['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = $projectpriority[projectpriorityid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = 'priority" . $projectpriority['projectpriorityid'] . "'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			projectpriorityid = " . $vbulletin->GPC['destpriorityid'] . "
		WHERE projectpriorityid = $projectpriority[projectpriorityid]
	");

	build_project_priority_cache();

	define('CP_REDIRECT', 'project.php?do=projectpriority&projectid=' . $project['projectid']);
	print_stop_message('project_priority_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectprioritydelete')
{
	$vbulletin->input->clean_gpc('r', 'projectpriorityid', TYPE_UINT);

	$projectpriority = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
	);

	$project = fetch_project_info($projectpriority['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$priorities = array();

	$priority_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = $project[projectid]
			AND projectpriorityid <> $projectpriority[projectpriorityid]
		ORDER BY displayorder
	");

	while ($priority = $db->fetch_array($priority_data))
	{
		$priorities["$priority[projectpriorityid]"] = $vbphrase['priority' . $priority['projectpriorityid'] . ''];
	}

	$priorities = array(0 => $vbphrase['unknown']) + $priorities;

	print_delete_confirmation(
		'pt_projectpriority',
		$projectpriority['projectpriorityid'],
		'project', 'projectpriorirykill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_priority'] .
			'&nbsp;<select name="destpriorityid">' . construct_select_options($priorities) . '</select>',
		'title'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectprioritydisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => TYPE_ARRAY_UINT,
		'projectid' => TYPE_UINT
	));

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $id => $displayorder)
	{
		$case .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
				displayorder = CASE projectpriorityid $case ELSE displayorder END
		");
	}

	build_project_category_cache();

	define('CP_REDIRECT', 'project.php?do=projectpriority&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectpriority')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], true);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$priorities = array();

	$priority_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = $project[projectid]
		ORDER BY displayorder
	");

	while ($priority = $db->fetch_array($priority_data))
	{
		$priorities["$priority[projectpriorityid]"] = $priority;
	}

	print_form_header('project', 'projectprioritydisplayorder');
	print_table_header(construct_phrase($vbphrase['priorities_for_x'], $project['title_clean']), 3);

	if ($priorities)
	{
		print_cells_row(array(
			$vbphrase['priority'],
			$vbphrase['display_order'],
			'&nbsp;'
		), true);

		foreach ($priorities AS $priority)
		{
			print_cells_row(array(
				$vbphrase['priority' . $priority['projectpriorityid'] . ''],
				"<input type=\"text\" class=\"bginput\" name=\"order[$priority[projectpriorityid]]\" value=\"$priority[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'project.php?do=projectpriorityedit&amp;projectpriorityid=' . $priority['projectpriorityid']) .
					construct_link_code($vbphrase['delete'], 'project.php?do=projectprioritydelete&amp;projectpriorityid=' . $priority['projectpriorityid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_priorities_defined_project'], false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_priority'], 'project.php?do=projectpriorityadd&amp;projectid=' . $project['projectid']) . '</p>';
}

// ########################################################################
// ################### PROJECT CATEGORY MANAGEMENT ########################
// ########################################################################
if ($_POST['do'] == 'projectcategoryupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectcategoryid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'title' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectcategoryid'])
	{
		$projectcategory = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
		);
		$vbulletin->GPC['projectid'] = $projectcategory['projectid'];
	}
	else
	{
		$projectcategory = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($projectcategory['projectcategoryid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
				displayorder = " . $vbulletin->GPC['displayorder'] . "
			WHERE projectcategoryid = $projectcategory[projectcategoryid]
		");

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'category" . intval($vbulletin->GPC['projectcategoryid']) . "',
					'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
					'vbprojecttools',
					'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
				)
		");
	}
	else
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectcategory
				(projectid, displayorder)
			VALUES
				($project[projectid],
				" . $vbulletin->GPC['displayorder'] . ")
		");

		$categoryid = $db->insert_id();

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'category" . intval($categoryid) . "',
					'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
					'vbprojecttools',
					'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
				)
		");
	}

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_project_category_cache();

	define('CP_REDIRECT', 'project.php?do=projectcategory&projectid=' . $project['projectid']);
	print_stop_message('project_category_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectcategoryadd' OR $_REQUEST['do'] == 'projectcategoryedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'projectcategoryid' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectcategoryid'])
	{
		$projectcategory = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
		);
		$vbulletin->GPC['projectid'] = $projectcategory['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectcategory = array(
			'projectcategoryid' => 0,
			'title' => '',
			'displayorder' => $maxorder['maxorder'] + 10
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('project', 'projectcategoryupdate');

	if ($projectcategory['projectcategoryid'])
	{
		print_table_header($vbphrase['edit_project_category']);
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=category";
	}
	else
	{
		print_table_header($vbphrase['add_project_category']);
		$trans_link = '';
	}

	print_input_row($vbphrase['title'] . ($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $projectcategory['projectcategoryid'], true) . '</dfn>' : ''), 'title', $vbphrase['category' . $projectcategory['projectcategoryid'] . ''], false);
	print_input_row($vbphrase['display_order'], 'displayorder', $projectcategory['displayorder'], true, 5);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectcategoryid', $projectcategory['projectcategoryid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectcategorykill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectcategoryid' => TYPE_UINT,
		'destcategoryid' => TYPE_UINT
	));

	$projectcategory = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
	);

	$project = fetch_project_info($projectcategory['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = $projectcategory[projectcategoryid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = 'category" . $projectcategory['projectcategoryid'] . "'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			projectcategoryid = " . $vbulletin->GPC['destcategoryid'] . "
		WHERE projectcategoryid = $projectcategory[projectcategoryid]
	");

	build_project_category_cache();

	define('CP_REDIRECT', 'project.php?do=projectcategory&projectid=' . $project['projectid']);
	print_stop_message('project_category_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectcategorydelete')
{
	$vbulletin->input->clean_gpc('r', 'projectcategoryid', TYPE_UINT);

	$projectcategory = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
	);

	$project = fetch_project_info($projectcategory['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$categories = array();

	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectid = $project[projectid]
			AND projectcategoryid <> $projectcategory[projectcategoryid]
		ORDER BY displayorder
	");

	while ($category = $db->fetch_array($category_data))
	{
		$categories["$category[projectcategoryid]"] = $vbphrase['category' . $category['projectcategoryid'] . ''];
	}

	$categories = array(0 => $vbphrase['unknown']) + $categories;

	print_delete_confirmation(
		'pt_projectcategory',
		$projectcategory['projectcategoryid'],
		'project', 'projectcategorykill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_category'] .
			'<select name="destcategoryid">' . construct_select_options($categories) . '</select>',
		'title'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectcategorydisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => TYPE_ARRAY_UINT,
		'projectid' => TYPE_UINT
	));

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $id => $displayorder)
	{
		$case .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
				displayorder = CASE projectcategoryid $case ELSE displayorder END
		");
	}

	build_project_category_cache();

	define('CP_REDIRECT', 'project.php?do=projectcategory&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectcategory')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
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
		$categories["$category[projectcategoryid]"] = $category;
	}

	print_form_header('project', 'projectcategorydisplayorder');
	print_table_header(construct_phrase($vbphrase['categories_for_x'], $project['title_clean']), 3);

	if ($categories)
	{
		print_cells_row(array(
			$vbphrase['category'],
			$vbphrase['display_order'],
			'&nbsp;'
		), true);

		foreach ($categories AS $category)
		{
			print_cells_row(array(
				$vbphrase['category' . $category['projectcategoryid'] . ''],
				"<input type=\"text\" class=\"bginput\" name=\"order[$category[projectcategoryid]]\" value=\"$category[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'project.php?do=projectcategoryedit&amp;projectcategoryid=' . $category['projectcategoryid']) .
					construct_link_code($vbphrase['delete'], 'project.php?do=projectcategorydelete&amp;projectcategoryid=' . $category['projectcategoryid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_categories_defined_project'], false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_category'], 'project.php?do=projectcategoryadd&amp;projectid=' . $project['projectid']) . '</p>';
}

// ########################################################################
// ##################### PROJECT IMPEX MANAGEMENT #########################
// ########################################################################
if ($_POST['do'] == 'projectimpexkill')
{
	// Delete the content type
}

// ########################################################################
if ($_REQUEST['do'] == 'projectimpexdelete')
{
	// Confirmation message of deletion of a content type
}

// ########################################################################
if ($_POST['do'] == 'projectimpexupdate')
{
	// Update the content type
	$vbulletin->input->clean_array_gpc('p', array(
		'projectimpexid' => TYPE_UINT,
		'contenttypeid' => TYPE_UINT,
		'phpcode' => TYPE_STR
	));

	if ($vbulletin->GPC['projectimpexid'])
	{
		// Edit
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectimpex SET
				contenttypeid = " . $vbulletin->GPC['contenttypeid'] . ",
				phpcode = '" . $vbulletin->GPC['phpcode'] . "'
			WHERE pt_projectimpexid = " . $vbulletin->GPC['projectimpexid'] . "
		");
	}
	else
	{
		// Add
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectimpex
				(contenttypeid, phpcode)
			VALUES
				(" . intval($vbulletin->GPC['contenttypeid']) . ",
				'" . $db->escape_string($vbulletin->GPC['phpcode']) . "')
		");
	}

	define('CP_REDIRECT', 'project.php?do=projectimpex');
	print_stop_message('saved_projectimpex_contenttype_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectimpexadd' OR $_REQUEST['do'] == 'projectimpexedit')
{
	$vbulletin->input->clean_gpc('r', 'projectimpexid', TYPE_UINT);

	print_form_header('project', 'projectimpexupdate');

	if ($vbulletin->GPC['projectimpexid'])
	{
		// Edit
		$projectimpex = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectimpex
			WHERE pt_projectimpexid = " . $vbulletin->GPC['projectimpexid'] . "
		");

		print_table_header($vbphrase['edit_content_type']);
		construct_hidden_code('projectimpexid', $projectimpex['pt_projectimpexid']);

		$ctarray = array();
		$selectarray = array();
		$ctvalues = '';

		// List all existing content type with php code
		$existingct = $db->query_read("
			SELECT contenttypeid
			FROM " . TABLE_PREFIX . "pt_projectimpex
		");

		while ($ct = $db->fetch_array($existingct))
		{
			$ctarray[] = $ct['contenttypeid'];
		}

		// Remove every contenttype already used
		$ctarray = array_diff($ctarray, array($projectimpex['contenttypeid']));

		$ctvalues = implode(',', $ctarray);

		// Create the content type menu
		$contenttypes = $db->query_read("
			SELECT c.contenttypeid, c.class AS cclass, p.productid, p.class AS pclass
			FROM " . TABLE_PREFIX . "contenttype AS c
				LEFT JOIN " . TABLE_PREFIX . "package AS p ON (p.packageid = c.packageid)
			WHERE c.contenttypeid NOT IN ('" . $ctvalues . "')
		");

		while ($contenttype = $db->fetch_array($contenttypes))
		{
			$selectarray[$contenttype['contenttypeid']] = $contenttype['pclass'] . '_' . $contenttype['cclass'];
		}
	}
	else
	{
		// Add
		print_table_header($vbphrase['add_content_type']);

		$ctarray = array();
		$selectarray = array();
		$ctvalues = '';

		// List all existing content type with php code
		$existingct = $db->query_read("
			SELECT contenttypeid
			FROM " . TABLE_PREFIX . "pt_projectimpex
		");

		if ($db->num_rows($existingct) > 0)
		{
			while ($ct = $db->fetch_array($existingct))
			{
				$ctarray[] = $ct['contenttypeid'];
			}

			$ctvalues = implode(',', $ctarray);
		}
		else
		{
			$ctvalues = '';
		}

		// Create the content type menu and remove contenttype already used
		$contenttypes = $db->query_read("
			SELECT c.contenttypeid, c.class AS cclass, p.productid, p.class AS pclass
			FROM " . TABLE_PREFIX . "contenttype AS c
				LEFT JOIN " . TABLE_PREFIX . "package AS p ON (p.packageid = c.packageid)
			" . ($ctvalues != '' ? "WHERE c.contenttypeid NOT IN (" . $ctvalues . ")" : '') . "
		");

		while ($contenttype = $db->fetch_array($contenttypes))
		{
			$selectarray[$contenttype['contenttypeid']] = $contenttype['pclass'] . '_' . $contenttype['cclass'];
		}
	}

	print_select_row($vbphrase['contenttype'], 'contenttypeid', $selectarray, $projectimpex['contenttypeid']);

	print_textarea_row(
		"$vbphrase[php_code] <dfn>$vbphrase[php_code_desc]</dfn>",
		'phpcode',
		htmlspecialchars($projectimpex['phpcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_submit_row();
}

// ########################################################################
if ($_REQUEST['do'] == 'projectimpex')
{
	print_form_header('project', 'projectimpexorder');
	print_table_header($vbphrase['projectimpex_list'], 2);
	// List of content types
	$contenttypes = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectimpex
	");

	if ($db->num_rows($contenttypes) > 0)
	{
		print_submit_row();
	}
	else
	{
		print_description_row('<div align="center">' . $vbphrase['projectimpex_no_content_type'] . '</div>');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_content_type'], 'project.php?do=projectimpexadd') . '</p>';
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
		'forumtitle' => TYPE_STR
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

	$required_fields = '';

	foreach ($vbulletin->bf_misc['pt_projectoptions'] AS $bitname => $value)
	{
		if (substr($bitname, 0, 7) == 'require')
		{
			$required_fields .= "<div class=\"smallfont\"><label><input type=\"checkbox\" name=\"options[$bitname]\" value=\"$value\" tabindex=\"1\"" . (intval($project['options']) & $value ? ' checked="checked"' : '') . " />" . $vbphrase["required_$bitname"] . "</label></div>";
		}
	}

	print_label_row($vbphrase['required_fields_issue_submit'], $required_fields, '', 'top', 'options');

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
					construct_link_code($vbphrase['priorities'], 'project.php?do=projectpriority&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['categories'], 'project.php?do=projectcategory&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['versions'], 'project.php?do=projectversion&amp;projectid=' . $project['projectid']) .
					construct_link_code($vbphrase['milestones'], 'project.php?do=projectmilestone&amp;projectid=' . $project['projectid']) .
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