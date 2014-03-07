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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'projectmilestone');
define('FRIENDLY_URL_LINK', 'projectmilestone');
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
	'pt_milestonebit',
	'pt_project_milestones'
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

($hook = vBulletinHook::fetch_hook('projectmilestone_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'viewall'   => TYPE_BOOL
));

$project = verify_project($vbulletin->GPC['projectid']);
$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

$milestone_types = fetch_viewable_milestone_types($projectperms);

if (!$milestone_types)
{
	print_no_permission();
}

$pageinfo = array();

if ($vbulletin->GPC['viewall'])
{
	$pageinfo['viewall'] = $vbulletin->GPC['viewall'];
}

verify_seo_url('projectmilestone', $project, $pageinfo);

$milestone_data = $vbulletin->db->query_read("
	SELECT *
	FROM " . TABLE_PREFIX . "pt_milestone
	WHERE projectid = " . $project['projectid'] . "
	ORDER BY completeddate DESC, targetdate, displayorder
");

if (!$db->num_rows($milestone_data))
{
	standard_error(fetch_error('invalidid', $vbphrase['project'], $vbulletin->options['contactuslink']));
}

$counts = fetch_milestone_count_data("milestone.projectid = " . $project['projectid'] . " AND milestonetypecount.issuetypeid IN ('" . implode("','", $milestone_types) . "')");
$active_milestones = '';
$no_target_milestones = '';
$completed_milestones = '';
$count_completed = 0;

while ($milestone = $db->fetch_array($milestone_data))
{
	if ($milestone['completeddate'] AND !$vbulletin->GPC['viewall'])
	{
		$count_completed++;
		continue;
	}

	$raw_counts = fetch_milestone_counts($counts["$milestone[milestoneid]"], $projectperms);
	$stats = prepare_milestone_stats($milestone, $raw_counts);
	$milestone['title'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_name'];
	$milestone['description'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_description'];

	// Needed for links inside each milestone summary
	$pageinfo_filteractive = $pageinfo + array('filter' => 'active');
	$pageinfo_filtercompleted = $pageinfo + array('filter' => 'completed');

	if ($milestone['completeddate'])
	{
		$templater = vB_Template::create('pt_milestonebit');
			$templater->register('milestone', $milestone);
			$templater->register('pageinfo_filteractive', $pageinfo_filteractive);
			$templater->register('pageinfo_filtercompleted', $pageinfo_filtercompleted);
			$templater->register('raw_counts', $raw_counts);
			$templater->register('stats', $stats);
		$completed_milestones .= $templater->render();
	}
	else if ($milestone['targetdate'])
	{
		$templater = vB_Template::create('pt_milestonebit');
			$templater->register('milestone', $milestone);
			$templater->register('pageinfo_filteractive', $pageinfo_filteractive);
			$templater->register('pageinfo_filtercompleted', $pageinfo_filtercompleted);
			$templater->register('raw_counts', $raw_counts);
			$templater->register('stats', $stats);
		$active_milestones .= $templater->render();
	}
	else
	{
		$templater = vB_Template::create('pt_milestonebit');
			$templater->register('milestone', $milestone);
			$templater->register('pageinfo_filteractive', $pageinfo_filteractive);
			$templater->register('pageinfo_filtercompleted', $pageinfo_filtercompleted);
			$templater->register('raw_counts', $raw_counts);
			$templater->register('stats', $stats);
		$no_target_milestones .= $templater->render();
	}
}

$filter = array();
$filter['viewall'] = 1;

$show['active_milestones'] = ($active_milestones OR $no_target_milestones);
$show['completed_placeholder'] = (!$vbulletin->GPC['viewall'] AND $count_completed);
$count_completed = vb_number_format($count_completed);

// navbar and output
$navbits = construct_navbits(array(
	'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
	fetch_seo_url('project', $project) => $project['title_clean'],
	'' => $vbphrase['milestones']
));
$navbar = render_navbar_template($navbits);

$templater = vB_Template::create('pt_project_milestones');
	$templater->register_page_templates();
	$templater->register('active_milestones', $active_milestones);
	$templater->register('completed_milestones', $completed_milestones);
	$templater->register('count_completed', $count_completed);
	$templater->register('filter', $filter);
	$templater->register('navbar', $navbar);
	$templater->register('no_target_milestones', $no_target_milestones);
	$templater->register('project', $project);
print_output($templater->render());

?>