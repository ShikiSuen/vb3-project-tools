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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'milestoneissuelist');
define('FRIENDLY_URL_LINK', 'msissuelist');
define('CSRF_PROTECTION', true);
define('PROJECT_SCRIPT', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('projecttools', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
	'pt_issuestatus',
	'pt_issuetype',
	'pt_projects',
	'pt_categories',
	'pt_priorities',
	'pt_assignable',
	'pt_versions',
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'pt_issuebit',
	'pt_issuebit_pagelink',
	'pt_issuelist_arrow',
	'pt_milestone_issuelist'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

if ($vbulletin->options['pt_maintenancemode'] AND !$show['admincplink'])
{
	standard_error(fetch_error('pt_in_maintenance_mode'));
}

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/includes/functions_pt_milestone.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('milestoneissuelist_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'milestoneid' => TYPE_UINT,
	'filter' => TYPE_NOHTML,
	'pagenumber' => TYPE_UINT,
	'sortfield' => TYPE_NOHTML,
	'sortorder' => TYPE_NOHTML
));

$milestone = verify_milestone($vbulletin->GPC['milestoneid']);
$project = verify_project($milestone['projectid']);
$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

$perms_query = build_issue_permissions_query($vbulletin->userinfo);

if (empty($perms_query["$project[projectid]"]))
{
	print_no_permission();
}

$milestone_types = fetch_viewable_milestone_types($projectperms);

if (!$milestone_types)
{
	print_no_permission();
}

// Definition to display selected columns
$columns = fetch_issuelist_columns($vbulletin->options['issuelist_columns'], $project);

// issues per page = 0 means "unlmiited"
if (!$vbulletin->options['pt_issuesperpage'])
{
	$vbulletin->options['pt_issuesperpage'] = 999999;
}

switch ($vbulletin->GPC['filter'])
{
	case 'active':
		$status_flag_value = 0;
		break;

	case 'completed':
		$status_flag_value = 1;
		break;

	default:
		$vbulletin->GPC['filter'] = '';
		$status_flag_value = null;
}

$filter_value = $vbulletin->GPC['filter'];

$status_limit = array();

if ($vbulletin->GPC['filter'])
{
	foreach ($vbulletin->pt_issuestatus AS $issuestatus)
	{
		if ($issuestatus['issuecompleted'] == $status_flag_value)
		{
			$status_limit[] = $issuestatus['issuestatusid'];
		}
	}

	if (!$status_limit)
	{
		standard_error(fetch_error('pt_no_issue_statues_represent_this_state'));
	}
}

require_once(DIR . '/includes/class_pt_issuelist.php');
$issue_list = new vB_Pt_IssueList($project, $vbulletin);
$issue_list->set_sort($vbulletin->GPC['sortfield'], $vbulletin->GPC['sortorder']);

$list_criteria = $perms_query["$project[projectid]"] . "
	AND issue.milestoneid = $milestone[milestoneid]
	AND issue.issuetypeid IN ('" . implode("','", $milestone_types) . "')
	" . ($status_limit ? "AND issue.issuestatusid IN (" . implode(',', $status_limit) . ")" : '') . "
	AND issue.visible IN ('visible', 'private')
";

$issue_list->exec_query($list_criteria, $vbulletin->GPC['pagenumber'], $vbulletin->options['pt_issuesperpage']);

$pageinfo = array();

if ($vbulletin->GPC['filter'])
{
	$pageinfo['filter'] = $vbulletin->GPC['filter'];
}

$oppositesort = $vbulletin->GPC['sortorder'] == 'asc' ? 'desc' : 'asc';

$pageinfo_title = $pageinfo + array('sort' => 'title', 'order' => ('title' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_username = $pageinfo + array('sort' => 'submitusername', 'order' => ('submitusername' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_applyversion = $pageinfo + array('sort' => 'applyversion', 'order' => ('applyversion' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_addressversion = $pageinfo + array('sort' => 'addressversion', 'order' => ('addressversion' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_category = $pageinfo + array('sort' => 'category', 'order' => ('projectcategoryid' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_issuestatus = $pageinfo + array('sort' => 'issuestatusid', 'order' => ('issuestatusid' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_priority = $pageinfo + array('sort' => 'priority', 'order' => ('priority' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_replies = $pageinfo + array('sort' => 'replycount', 'order' => ('replycount' == $issue_list->sort_field) ? $oppositesort : 'asc');
$pageinfo_lastpost = $pageinfo + array('sort' => 'lastpost', 'order' => ('lastpost' == $issue_list->sort_field) ? $oppositesort : 'asc');

$sort_arrow = $issue_list->fetch_sort_arrow_array();

if ($issue_list->sort_field != 'lastpost')
{
	$pageinfo['sort'] = urlencode($issue_list->sort_field);
}

if ($issue_list->sort_order != 'desc')
{
	$pageinfo['order'] = 'asc';
}

$pagenav = construct_page_nav(
	$issue_list->real_pagenumber,
	$vbulletin->options['pt_issuesperpage'],
	$issue_list->total_rows,
	'',
	'',
	'',
	'msissuelist',
	$milestone,
	$pageinfo
);

verify_seo_url('msissuelist', $milestone, $pageinfo + array('pagenumber' => $vbulletin->GPC['pagenumber']));

$issuebits = '';

while ($issue = $db->fetch_array($issue_list->result))
{
	$issuebits .= build_issue_bit($issue, $project, $projectperms["$issue[issuetypeid]"]);
}

// issue state filter
$filter_options = array(
	'active'   => '',
	'completed' => '',
	'any'      => ''
);

$filter_options[$vbulletin->GPC['filter'] ? $vbulletin->GPC['filter'] : 'any'] = ' selected="selected"';

// search box data
$show['search_options'] = false;

foreach ($milestone_types AS $milestone_typeid)
{
	if ($projectperms["$milestone_typeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['cansearch'])
	{
		$show['search_options'] = true;
		break;
	}
}

if ($show['search_options'])
{
	$assignable_users = fetch_assignable_users_select($project['projectid']);
	$search_status_options = fetch_issue_status_search_select($projectperms);
}

($hook = vBulletinHook::fetch_hook('milestoneissuelist_complete')) ? eval($hook) : false;

// navbar and output
$navbits = construct_navbits(array(
	'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
	fetch_seo_url('project', $project) => $project['title_clean'],
	fetch_seo_url('projectmilestone', $milestone) => $milestone['title_clean'],
	'' => $vbphrase['issue_list']
));

$navbar = render_navbar_template($navbits);

$templater = vB_Template::create('pt_milestone_issuelist');
	$templater->register_page_templates();
	$templater->register('assignable_users', $assignable_users);
	$templater->register('columns', $columns);
	$templater->register('filter_options', $filter_options);
	$templater->register('filter_value', $filter_value);
	$templater->register('issuebits', $issuebits);
	$templater->register('milestone', $milestone);
	$templater->register('navbar', $navbar);
	$templater->register('pageinfo_title', $pageinfo_title);
	$templater->register('pageinfo_username', $pageinfo_username);
	$templater->register('pageinfo_applyversion', $pageinfo_applyversion);
	$templater->register('pageinfo_addressversion', $pageinfo_addressversion);
	$templater->register('pageinfo_category', $pageinfo_category);
	$templater->register('pageinfo_issuestatus', $pageinfo_issuestatus);
	$templater->register('pageinfo_priority', $pageinfo_priority);
	$templater->register('pageinfo_replies', $pageinfo_replies);
	$templater->register('pageinfo_lastpost', $pageinfo_lastpost);
	$templater->register('pagenav', $pagenav);
	$templater->register('sort_arrow', $sort_arrow);
	$templater->register('search_status_options', $search_status_options);
print_output($templater->render());

?>