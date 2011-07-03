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
define('THIS_SCRIPT', 'milestone');
define('FRIENDLY_URL_LINK', 'milestone');
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
	'pt_assignable',
	'pt_versions',
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'pt_issuebit',
	'pt_issuebit_pagelink',
	'pt_milestone',
	'pt_postmenubit',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/includes/functions_pt_milestone.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('milestone_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_gpc('r', 'milestoneid', TYPE_UINT);

$milestone = verify_milestone($vbulletin->GPC['milestoneid']);

// Workaround for having milestone title in phrase system
$milestone['title'] = $milestone['title_clean'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_name'];
$milestone['description'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_description'];

$project = verify_project($milestone['projectid']);
$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

verify_seo_url('milestone', $milestone);

$perms_query = build_issue_permissions_query($vbulletin->userinfo);
if (empty($perms_query["$project[projectid]"]))
{
	print_no_permission();
}

// Definition to display selected columns
$columns = fetch_issuelist_columns($vbulletin->options['issuelist_columns']);

// status options / posting options drop down
$postable_types = array();
$status_options = '';
$post_issue_options = '';

foreach ($vbulletin->pt_issuetype AS $issuetypeid => $type)
{
	if (($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) AND ($projectperms["$issuetypeid"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
	{
		$postable_types[] = $issuetypeid;

		$type['name'] = $vbphrase["issuetype_{$issuetypeid}_singular"];
		$type['milestoneid'] = $milestone['milestoneid'];
		$type['projectid'] = $project['projectid'];

		$post_issue_options[] = $type;
	}

	if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
	{
		continue;
	}

	$optgroup_options = build_issuestatus_select($type['statuses'], $vbulletin->GPC['issuestatusid']);
	$status_options .= "<optgroup label=\"" . $vbphrase["issuetype_{$issuetypeid}_singular"] . "\">$optgroup_options</optgroup>";
}

if (sizeof($postable_types) == 1)
{
	$vbphrase['post_new_issue_issuetype'] = $vbphrase["post_new_issue_$postable_types[0]"];
}

$anystatus_selected = '';
$activestatus_selected = '';

if ($vbulletin->GPC['issuestatusid'] == -1)
{
	$issuestatus_printable = $vbphrase['any_active_meta'];
	$activestatus_selected = ' selected="selected"';
}
else if ($vbulletin->GPC['issuestatusid'] > 0)
{
	$issuestatus_printable = $vbphrase["issuestatus" . $vbulletin->GPC['issuestatusid']];
}
else
{
	$issuestatus_printable = '';
	$anystatus_selected = ' selected="selected"';
}

$milestone_types = fetch_viewable_milestone_types($projectperms);

if (!$milestone_types)
{
	print_no_permission();
}

$counts = fetch_milestone_count_data("milestonetypecount.milestoneid = $milestone[milestoneid] AND milestonetypecount.issuetypeid IN ('" . implode("','", $milestone_types) . "')");

$raw_counts = fetch_milestone_counts($counts["$milestone[milestoneid]"], $projectperms);
$stats = prepare_milestone_stats($milestone, $raw_counts);

require_once(DIR . '/includes/class_pt_issuelist.php');
$issue_list = new vB_Pt_IssueList($project, $vbulletin);
$issue_list->calc_total_rows = false;

$list_criteria = $perms_query["$project[projectid]"] . "AND issue.milestoneid = $milestone[milestoneid] AND issue.issuetypeid IN ('" . implode("','", $milestone_types) . "') AND issue.visible IN ('visible', 'private')";

$issue_list->exec_query($list_criteria, 1, $vbulletin->options['pt_project_recentissues']);

$issuebits = array();

while ($issue = $db->fetch_array($issue_list->result))
{
	$issuebits .= build_issue_bit($issue, $project, $projectperms["$issue[issuetypeid]"]);
}

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

// navbar and output
$navbits = construct_navbits(array(
	'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
	fetch_seo_url('project', $project) => $project['title_clean'],
	fetch_seo_url('projectmilestone', $project) => $vbphrase['milestones'],
	'' => $milestone['title_clean']
));
$navbar = render_navbar_template($navbits);

$templater = vB_Template::create('pt_milestone');
	$templater->register_page_templates();
	$templater->register('assignable_users', $assignable_users);
	$templater->register('columns', $columns);
	$templater->register('post_issue_options', $post_issue_options);
	$templater->register('postable_types', $postable_types);
	$templater->register('issuebits', $issuebits);
	$templater->register('milestone', $milestone);
	$templater->register('navbar', $navbar);
	$templater->register('raw_counts', $raw_counts);
	$templater->register('search_status_options', $search_status_options);
	$templater->register('stats', $stats);
print_output($templater->render());

?>