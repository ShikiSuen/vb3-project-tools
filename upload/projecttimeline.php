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
define('THIS_SCRIPT', 'projecttimeline');
define('FRIENDLY_URL_LINK', 'projecttimeline');
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
	'pt_report_users',
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'pt_timeline_page',
	'pt_timeline',
	'pt_timeline_group',
	'pt_timeline_item',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

if (!isset($vbulletin->pt_bitfields) or (!count($vbulletin->pt_bitfields)))
{
	require_once DIR . '/includes/adminfunctions_projecttools.php';
	$vbulletin->pt_bitfields = build_project_bitfields();
}

require_once(DIR . '/includes/functions_projecttools.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('timeline_start')) ? eval($hook) : false;

require_once(DIR . '/includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();
$issue_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Issue');
$project_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Project');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
require_once(DIR . '/includes/functions_pt_timeline.php');

$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'pagenumber' => TYPE_UINT,
	'startdate' => TYPE_UNIXTIME,
	'enddate' => TYPE_UNIXTIME
));

$project = ($vbulletin->GPC['projectid'] ? verify_project($vbulletin->GPC['projectid']) : array());

// activity list
$show['timeline_project_title'] = (empty($project) ? true : false);

$perms_query = build_issue_permissions_query($vbulletin->userinfo);

if (empty($perms_query))
{
	print_no_permission();
}

$note_perms = build_issuenote_permissions_query($vbulletin->userinfo);

if ($project)
{
	if (empty($perms_query["$project[projectid]"]))
	{
		print_no_permission();
	}

	$viewable_query = '(' . $perms_query["$project[projectid]"] . ') AND (' . $note_perms["$project[projectid]"] . ')';

	verify_seo_url('projecttimeline', $project);
}
else
{
	$viewable_query = '(' . implode(' OR ', $perms_query) . ') AND (' . implode(' OR ', $note_perms) . ')';
}

($hook = vBulletinHook::fetch_hook('project_timeline_start')) ? eval($hook) : false;

// default date limits
if (!$vbulletin->GPC['startdate'])
{
	$vbulletin->GPC['startdate'] = strtotime('-1 month');
}
if (!$vbulletin->GPC['enddate'])
{
	$vbulletin->GPC['enddate'] = TIMENOW;
}

$datelimit = '1=1';
if ($vbulletin->GPC['startdate'] AND $vbulletin->GPC['enddate'])
{
	$datelimit = "issuenote.dateline >= " . $vbulletin->GPC['startdate'] . " AND issuenote.dateline <= " . ($vbulletin->GPC['enddate'] + 86399);
}

// wrapping this in a do-while allows us to detect if someone goes to a page
// that's too high and take them back to the last page seamlessly
do
{
	if (!$vbulletin->GPC['pagenumber'])
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}
	$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['pt_timelineperpage'];

	$activity_groups = prepare_activity_list(fetch_activity_list(
		"($datelimit) AND ($viewable_query)",
		$vbulletin->options['pt_timelineperpage'],
		$start
	));

	$activity_count = $activity_count[0];

	if ($start >= $activity_count)
	{
		$vbulletin->GPC['pagenumber'] = ceil($activity_count / $vbulletin->options['pt_timelineperpage']);
	}
}
while ($start >= $activity_count AND $activity_count);

if ($vbulletin->options['pt_timelineperpage'])
{
	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$vbulletin->options['pt_timelineperpage'],
		$activity_count,
		/*'projecttimeline.php?' . $vbulletin->session->vars['sessionurl'] .
			($vbulletin->GPC['projectid'] ? '&amp;projectid=' . $vbulletin->GPC['projectid'] : '') .
			($vbulletin->GPC['startdate'] ? '&amp;startdate=' . $vbulletin->GPC['startdate'] : '') .
			($vbulletin->GPC['enddate'] ? '&amp;enddate=' . $vbulletin->GPC['enddate'] : '')*/'',
		'',
		''
		'projecttimeline',
		'',
		array(($vbulletin->GPC['projectid'] ? '&amp;projectid=' . $vbulletin->GPC['projectid'] : '') .
		($vbulletin->GPC['startdate'] ? '&amp;startdate=' . $vbulletin->GPC['startdate'] : '') .
		($vbulletin->GPC['enddate'] ? '&amp;enddate=' . $vbulletin->GPC['enddate'] : ''))
	);
}
else
{
	$pagenav = '';
}

$activitybits = '';

if (!empty($activity_groups))
{
	foreach ($activity_groups AS $groupid => $groupbits)
	{
		$group_date = make_group_date($groupid);

		($hook = vBulletinHook::fetch_hook('project_timeline_group')) ? eval($hook) : false;

		$templater = vB_Template::create('pt_timeline_group');
			$templater->register('groupbits', $groupbits);
			$templater->register('group_date', $group_date);
			$templater->register('contenttypeid', $issue_contenttypeid);
		$activitybits .= $templater->render();
	}
}
else
{
	$activitybits = vB_Template::create('pt_timeline_empty')-> render();
}

// activity scope
$startdate = explode(',', vbdate('j,n,Y', $vbulletin->GPC['startdate'], false, false));
$startdate['day'] = $startdate[0];
$startdate['year'] = $startdate[2];
$startdate_selected = array();

for ($i = 1; $i <= 12; $i++)
{
	$startdate_selected["$i"] = ($i == $startdate[1] ? ' selected="selected"' : '');
}

$enddate = explode(',', vbdate('j,n,Y', $vbulletin->GPC['enddate'], false, false));
$enddate['day'] = $enddate[0];
$enddate['year'] = $enddate[2];
$enddate_selected = array();

for ($i = 1; $i <= 12; $i++)
{
	$enddate_selected["$i"] = ($i == $enddate[1] ? ' selected="selected"' : '');
}

$show['timeline_daterange'] = true;
$startdate_display = vbdate($vbulletin->options['dateformat'], $vbulletin->GPC['startdate']);
$enddate_display = vbdate($vbulletin->options['dateformat'], $vbulletin->GPC['enddate']);

$show['disable_timeline_collapse'] = true;

$templater = vB_Template::create('pt_timeline');
	$templater->register('activitybits', $activitybits);
	$templater->register('enddate', $enddate);
	$templater->register('enddate_display', $enddate_display);
	$templater->register('enddate_selected', $enddate_selected);
	$templater->register('project', $project);
	$templater->register('startdate', $startdate);
	$templater->register('startdate_display', $startdate_display);
	$templater->register('startdate_selected', $startdate_selected);
	$templater->register('timeline_entries', $timeline_entries);
	$templater->register('contenttypeid', $issue_contenttypeid);
$timeline = $templater->render();

// navbar and output
$navbits = array('project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects']);
if ($project)
{
	$navbits[fetch_seo_url('projecttimeline', $project)] = $project['title_clean'];
}
$navbits[''] = $vbphrase['project_timeline'];

$navbits = construct_navbits($navbits);
$navbar = render_navbar_template($navbits);

($hook = vBulletinHook::fetch_hook('project_timeline_complete')) ? eval($hook) : false;

$templater = vB_Template::create('pt_timeline_page');
	$templater->register_page_templates();
	$templater->register('navbar', $navbar);
	$templater->register('pagenav', $pagenav);
	$templater->register('timeline', $timeline);
	$templater->register('contenttypeid', $issue_contenttypeid);
print_output($templater->render());

?>