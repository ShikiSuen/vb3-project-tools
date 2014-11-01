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
define('THIS_SCRIPT', 'project');
define('FRIENDLY_URL_LINK', 'project');
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
	'pt_issuebit',
	'pt_issuebit_deleted',
	'pt_issuebit_pagelink',
	'pt_markread_script',
	'pt_overview',
	'pt_petitionbit',
	'pt_project',
	'pt_projectbit',
	'pt_projectgroupbit',
	'pt_postmenubit',
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

if ($vbulletin->options['pt_maintenancemode'] AND !$show['admincplink'])
{
	standard_error(fetch_error('pt_in_maintenance_mode'));
}

if (!isset($vbulletin->pt_bitfields) OR (!count($vbulletin->pt_bitfields)))
{
	require_once(DIR . '/includes/adminfunctions_projecttools.php');
	$vbulletin->pt_bitfields = build_project_bitfields();
}

require_once(DIR . '/includes/functions_projecttools.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('project_start')) ? eval($hook) : false;

require_once(DIR . '/includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();
$issue_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Issue');
$project_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Project');

$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'issueid' => TYPE_UINT,
	'issuenoteid' => TYPE_UINT,
));

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'timeline')
{
	if (!empty($vbulletin->GPC['projectid']))
	{
		exec_header_redirect("projecttimeline.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=" . $vbulletin->GPC['projectid'], 301);
	}
	else
	{
		exec_header_redirect("projecttimeline.php?" . $vbulletin->session->vars['sessionurl_q'], 301);
	}
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'issue')
{
	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "issueid=" . $vbulletin->GPC['issueid'], 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'issuelist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuetypeid' => TYPE_NOHTML,
		'appliesversionid' => TYPE_NOHTML,
		'issuestatusid' => TYPE_INT,
		'pagenumber' => TYPE_UINT,
		'sortfield' => TYPE_NOHTML,
		'sortorder' => TYPE_NOHTML
	));

	$issuetypeid_url = (!empty($vbulletin->GPC['issuetypeid']) ? "&issuetypeid=" . $vbulletin->GPC['issuetypeid'] : '');
	$appliesversionid_url = (!empty($vbulletin->GPC['appliesversionid']) ? "&appliesversionid=" . $vbulletin->GPC['appliesversionid'] : '');
	$issuestatusid_url = (!empty($vbulletin->GPC['issuestatusid']) ? "&issuestatusid=" . $vbulletin->GPC['issuestatusid'] : '');
	$pagenumber_url = (!empty($vbulletin->GPC['pagenumber']) ? "&pagenumber=" . $vbulletin->GPC['pagenumber'] : '');
	$sortfield_url = (!empty($vbulletin->GPC['sortfield']) ? "&sortfield=" . $vbulletin->GPC['sortfield'] : '');
	$sortorder_url = (!empty($vbulletin->GPC['sortorder']) ? "&sortorder=" . $vbulletin->GPC['sortorder'] : '');

	exec_header_redirect("issuelist.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=" . $vbulletin->GPC['projectid'] . "$issuetypeid_url$appliesversionid_url$issuestatusid_url$pagenumber_url$sortfield_url$sortorder_url", 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'notehistory')
{
	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=notehistory&issuenoteid=" . $vbulletin->GPC['issuenoteid'], 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'viewip')
{
	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=viewip&issuenoteid=" . $vbulletin->GPC['issuenoteid'], 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'patch')
{
	$vbulletin->input->clean_gpc('r', 'attachmentid', TYPE_UINT);

	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=patch&attachmentid=" . $vbulletin->GPC['attachmentid'], 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'gotonote')
{
	$vbulletin->input->clean_gpc('r', 'goto', TYPE_STR);

	$issueid_url = (!empty($vbulletin->GPC['issueid']) ? "&issueid=" . $vbulletin->GPC['issueid'] : '');
	$issuenoteid_url = (!empty($vbulletin->GPC['issuenoteid']) ? "&issuenoteid=" . $vbulletin->GPC['issuenoteid'] : '');
	$goto_url = (!empty($vbulletin->GPC['goto']) ? "&goto=" . $vbulletin->GPC['goto'] : '');

	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=gotonote$issuenoteid_url$issueid_url$goto_url", 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'lastnote')
{
	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=lastnote&issueid=" . $vbulletin->GPC['issueid'], 301);
}

// #######################################################################
// Redirect to the new place for this 'do' branch
if ($_REQUEST['do'] == 'report')
{
	exec_header_redirect("issue.php?" . $vbulletin->session->vars['sessionurl'] . "do=report&issuenoteid=" . $vbulletin->GPC['issuenoteid'], 301);
}

// #######################################################################
// Previously do=overview branch
if (empty($vbulletin->GPC['projectid']))
{
	$perms_query = build_issue_permissions_query($vbulletin->userinfo);

	if (empty($perms_query))
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('project_overview_start')) ? eval($hook) : false;

	// activity list
	$timeline = '';

	if ($vbulletin->options['pt_overview_timelineentries'])
	{
		$show['timeline_project_title'] = true;

		$note_perms = build_issuenote_permissions_query($vbulletin->userinfo);

		require_once(DIR . '/includes/functions_pt_timeline.php');
		$activity_results = fetch_activity_list('(' . implode(' OR ', $perms_query) . ') AND (' . implode(' OR ', $note_perms) . ')', $vbulletin->options['pt_overview_timelineentries'], 0, false);
		$activity_groups = prepare_activity_list($activity_results);

		$activitybits = '';

		foreach ($activity_groups AS $groupid => $groupbits)
		{
			$group_date = make_group_date($groupid);

			($hook = vBulletinHook::fetch_hook('project_timeline_group')) ? eval($hook) : false;

			$templater = vB_Template::create('pt_timeline_group');
				$templater->register('groupbits', $groupbits);
				$templater->register('group_date', $group_date);
			$activitybits .= $templater->render();
		}

		// activity scope
		$startdate = explode(',', vbdate('j,n,Y', strtotime('-1 month'), false, false));
		$startdate['day'] = $startdate[0];
		$startdate['year'] = $startdate[2];
		$startdate_selected = array();

		for ($i = 1; $i <= 12; $i++)
		{
			$startdate_selected["$i"] = ($i == $startdate[1] ? ' selected="selected"' : '');
		}

		$enddate = explode(',', vbdate('j,n,Y', TIMENOW, false, false));
		$enddate['day'] = $enddate[0];
		$enddate['year'] = $enddate[2];
		$enddate_selected = array();

		for ($i = 1; $i <= 12; $i++)
		{
			$enddate_selected["$i"] = ($i == $enddate[1] ? ' selected="selected"' : '');
		}

		$timeline_entries = vb_number_format($db->num_rows($activity_results));

		if ($timeline_entries)
		{
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
		}
	}

	build_project_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

	$project_types = array();
	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);

	$project_types_query = $db->query_read("
		SELECT projecttype.*
			" . ($marking ? ", projectread.readtime AS projectread" : '') . "
			" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
		FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
		INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuetype.issuetypeid = projecttype.issuetypeid)
		" . ($marking ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON
				(projectread.projectid = projecttype.projectid AND projectread.issuetypeid = projecttype.issuetypeid AND projectread.userid = " . $vbulletin->userinfo['userid'] . ")
		" : '') . "
		$private_lastpost_join
		WHERE projecttype.projectid IN (" . implode(',', array_keys($perms_query)) . ")
		ORDER BY issuetype.displayorder
	");

	while ($project_type = $db->fetch_array($project_types_query))
	{
		$project_types["$project_type[projectid]"][] = $project_type;
	}

	$show['search_options'] = false;

	// project list
	$projectgroupbits = '';

	foreach ($vbulletin->pt_projects AS $projectgroupid => $projectgroupdata)
	{
		$projectbits = '';

		if (is_array($projectgroupdata['projects']) AND isset($projectgroupdata['projects']))
		{
			foreach ($projectgroupdata['projects'] AS $projectid => $project)
			{
				if (!isset($perms_query["$project[projectid]"]) OR !is_array($project_types["$project[projectid]"]) OR $project['displayorder'] == 0)
				{
					continue;
				}
		
				$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
				$project['lastpost'] = 0;
				$show['private_lastpost'] = false;
				$project['newflag'] = false;
		
				$type_counts = array();
		
				foreach ($project_types["$project[projectid]"] AS $type)
				{
					if (!($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
					{
						continue;
					}
		
					if ($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['cansearch'])
					{
						$show['search_options'] = true;
					}
		
					if ($type['lastpost'] > $project['lastpost'])
					{
						$project['lastpost'] = $type['lastpost'];
						$project['lastpostuserid'] = $type['lastpostuserid'];
						$project['lastpostusername'] = $type['lastpostusername'];
						$project['lastpostid'] = $type['lastpostid'];
						$project['lastissueid'] = $type['lastissueid'];
						$project['lastissuetitle'] = $type['lastissuetitle'];
		
						$show['private_lastpost'] = (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']) ? false : true);
					}
		
					$type['name'] = $vbphrase["issuetype_$type[issuetypeid]_plural"];
					$type['issuecount'] = vb_number_format($type['issuecount']);
					$type['issuecountactive'] = vb_number_format($type['issuecountactive']);
		
					if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
					{
						$projettypeview = max($type['projectread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
					}
					else
					{
						$projettypeview = intval(fetch_bbarray_cookie('project_lastview', $project['projectid'] . $type['issuetypeid']));
						if (!$projettypeview)
						{
							$projettypeview = $vbulletin->userinfo['lastvisit'];
						}
					}
		
					if ($type['lastpost'] > $projettypeview)
					{
						$type['newflag'] = true;
						$project['newflag'] = true;
					}
		
					$project['projectread'] = max($project['projectread'], $projettypeview);
		
					$type['countid'] = "project_typecount_$project[projectid]_$type[issuetypeid]";
		
					$type['pageinfo'] = array('issuetypeid' => $type['issuetypeid']);
		
					$type_counts[] = $type;
				}
		
				if (!$type_counts)
				{
					continue;
				}
		
				if ($project['lastpost'])
				{
					$project['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $project['lastpost'], true);
					$project['lastposttime'] = vbdate($vbulletin->options['timeformat'], $project['lastpost']);
					$project['lastissuetitle_short'] = fetch_trimmed_title(fetch_censored_text($project['lastissuetitle']));
				}
				else
				{
					$project['lastpostdate'] = '';
					$project['lastposttime'] = '';
				}
		
				($hook = vBulletinHook::fetch_hook('project_overview_projectbit')) ? eval($hook) : false;
					
				$templater = vB_Template::create('pt_projectbit');
					$templater->register('project', $project);
					$templater->register('type_counts', $type_counts);
				$projectbits .= $templater->render();
			}
		}

		// Project groups
		$templater = vB_Template::create('pt_projectgroupbit');
			$templater->register('projectbits', $projectbits);
			$templater->register('projectgroup', $projectgroupdata);
		$projectgroupbits .= $templater->render();
	}

	// report list
	$reportbits = prepare_subscribed_reports();

	// navbar and output
	$navbits = construct_navbits(array('' => $vbphrase['projects']));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_overview_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_overview');
		$templater->register_page_templates();
		$templater->register('markread_script', $markread_script);
		$templater->register('navbar', $navbar);
		//$templater->register('projectbits', $projectbits);
		$templater->register('projectgroupbits', $projectgroupbits);
		$templater->register('reportbits', $reportbits);
		$templater->register('timeline', $timeline);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

$project = verify_project($vbulletin->GPC['projectid']);

verify_seo_url('project', $project);

$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
$perms_query = build_issue_permissions_query($vbulletin->userinfo);

if (empty($perms_query["$project[projectid]"]))
{
	print_no_permission();
}

$project['description'] = nl2br($project['description']);

($hook = vBulletinHook::fetch_hook('project_project_start')) ? eval($hook) : false;

// Definition to display selected columns
$columns = fetch_issuelist_columns($vbulletin->options['issuelist_columns'], $project);

// milestones
require_once(DIR . '/includes/functions_pt_milestone.php');

if ($project['milestonecount'] AND fetch_viewable_milestone_types($projectperms))
{
	$show['milestones'] = true;
	$project['milestonecount_formatted'] = vb_number_format($project['milestonecount']);
}

// activity list
$timeline = '';

if ($vbulletin->options['pt_project_timelineentries'])
{
	require_once(DIR . '/includes/functions_pt_timeline.php');

	$note_perms = build_issuenote_permissions_query($vbulletin->userinfo);
	$activity_results = fetch_activity_list('(' . $perms_query["$project[projectid]"] . ') AND (' . $note_perms["$project[projectid]"] . ')', $vbulletin->options['pt_project_timelineentries'], 0, false);
	$activity_groups = prepare_activity_list($activity_results);

	$activitybits = '';

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

	// activity scope
	$startdate = explode(',', vbdate('j,n,Y', strtotime('-1 month'), false, false));
	$startdate['day'] = $startdate[0];
	$startdate['year'] = $startdate[2];
	$startdate_selected = array();

	for ($i = 1; $i <= 12; $i++)
	{
		$startdate_selected["$i"] = ($i == $startdate[1] ? ' selected="selected"' : '');
	}

	$enddate = explode(',', vbdate('j,n,Y', TIMENOW, false, false));
	$enddate['day'] = $enddate[0];
	$enddate['year'] = $enddate[2];
	$enddate_selected = array();

	for ($i = 1; $i <= 12; $i++)
	{
		$enddate_selected["$i"] = ($i == $enddate[1] ? ' selected="selected"' : '');
	}

	$timeline_entries = vb_number_format($db->num_rows($activity_results));

	if ($timeline_entries)
	{
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
	}
}

// general viewing
build_project_private_lastpost_sql_project($vbulletin->userinfo, $project['projectid'],	$private_lastpost_join, $private_lastpost_fields);

$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);

$project_types = array();

$project_types_query = $db->query_read("
	SELECT issuetype.*, projecttype.*
		" . ($marking ? ", projectread.readtime AS projectread" : '') . "
		" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
	FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
	INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuetype.issuetypeid = projecttype.issuetypeid)
	" . ($marking ? "
		LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON
			(projectread.projectid = projecttype.projectid AND projectread.issuetypeid = projecttype.issuetypeid AND projectread.userid = " . $vbulletin->userinfo['userid'] . ")
	" : '') . "
	$private_lastpost_join
	WHERE projecttype.projectid = $project[projectid]
	ORDER BY issuetype.displayorder
");

while ($project_type = $db->fetch_array($project_types_query))
{
	$project_types[] = $project_type;
}

$project['lastactivity'] = 0;
$show['private_lastactivity'] = false;

$postable_types = array();

$type_counts = array();
$post_issue_options = array();

foreach ($project_types AS $type)
{
	if (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) AND ($projectperms["$type[issuetypeid]"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
	{
		$postable_types[] = $type['issuetypeid'];

		$type['name'] = $vbphrase["issuetype_$type[issuetypeid]_singular"];

		$post_issue_options[] = $type;
	}

	if (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
	{
		if ($type['lastactivity'] > $project['lastactivity'])
		{
			$project['lastactivity'] = $type['lastactivity'];
			$show['private_lastactivity'] = (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']) ? false : true);
		}

		$type['name'] = $vbphrase["issuetype_$type[issuetypeid]_plural"];
		$type['issuecount'] = vb_number_format($type['issuecount']);
		$type['issuecountactive'] = vb_number_format($type['issuecountactive']);

		if ($marking)
		{
			$projettypeview = max($type['projectread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
		}
		else
		{
			$projettypeview = intval(fetch_bbarray_cookie('project_lastview', $project['projectid'] . $type['issuetypeid']));

			if (!$projettypeview)
			{
				$projettypeview = $vbulletin->userinfo['lastvisit'];
			}
		}

		if ($type['lastpost'] > $projettypeview)
		{
			$type['newflag'] = true;
		}

		$type['pageinfo'] = array('issuetypeid' => $type['issuetypeid']);

		$type_counts[] = $type;
	}
}

if (sizeof($postable_types) == 1)
{
	$show['direct_post_link'] = true;
	$post_new_issue_text = $vbphrase["post_new_issue_$postable_types[0]"];
}
else
{
	$show['direct_post_link'] = false;
	$post_new_issue_text = '';
}

if ($project['lastactivity'])
{
	$project['lastactivitydate'] = vbdate($vbulletin->options['dateformat'], $project['lastactivity'], true);
	$project['lastactivitydate_date'] = vbdate($vbulletin->options['dateformat'], $project['lastactivity']);
	$project['lastactivitytime'] = vbdate($vbulletin->options['timeformat'], $project['lastactivity']);
}
else
{
	$project['lastactivitydate'] = '';
	$project['lastactivitytime'] = '';
}

// issue list
$issuebits = '';

if ($vbulletin->options['pt_project_recentissues'])
{
	require_once(DIR . '/includes/class_pt_issuelist.php');
	$issue_list = new vB_Pt_IssueList($project, $vbulletin);
	$issue_list->calc_total_rows = false;
	$issue_list->exec_query($perms_query["$project[projectid]"], 1, $vbulletin->options['pt_project_recentissues']);

	while ($issue = $db->fetch_array($issue_list->result))
	{
		$issuebits .= build_issue_bit($issue, $project, $projectperms["$issue[issuetypeid]"]);
	}
}

// pending petitions
// NOTE: this query could be bad, might be best to cache
$pending_petition_data = $db->query_read_slave("
	SELECT issue.*, issuenote.*, issuepetition.petitionstatusid
	FROM " . TABLE_PREFIX . "pt_issuepetition AS issuepetition
	INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issuenoteid = issuepetition.issuenoteid)
	INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuenote.issueid)
	WHERE issuepetition.resolution = 'pending'
		AND issue.projectid = $project[projectid]
	ORDER BY issuenote.dateline DESC
");

$project['petitioncount'] = $db->num_rows($pending_petition_data);
$petitionbits = '';

while ($pending = $db->fetch_array($pending_petition_data))
{
	$pending['issuetype'] = $vbphrase["issuetype_$pending[issuetypeid]_singular"];
	$pending['petitionstatus'] = $vbphrase["issuestatus$pending[petitionstatusid]"];

	if ($typeicon = $vbulletin->pt_issuetype["$pending[issuetypeid]"]['iconfile'])
	{
		$pending['typeicon'] = $typeicon;
	}

	$pending['note_date'] = vbdate($vbulletin->options['dateformat'], $pending['dateline'], true);
	$pending['note_time'] = vbdate($vbulletin->options['timeformat'], $pending['dateline']);

	($hook = vBulletinHook::fetch_hook('project_project_petitionbit')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_petitionbit');
		$templater->register('pending', $pending);
	$petitionbits .= $templater->render();
}

// search box data
$assignable_users = fetch_assignable_users_select($project['projectid']);
$search_status_options = fetch_issue_status_search_select($projectperms);

// report list
$reportbits = prepare_subscribed_reports();

// Do a query for adding the project group
$projectgroup = $vbulletin->db->query_first("
	SELECT projectgroupid
	FROM " . TABLE_PREFIX . "pt_project
	WHERE projectid = " . $project['projectid'] . "
");

// Project navigation
if ($vbulletin->options['pt_disablequicknav'] AND count($vbulletin->pt_projects[$projectgroup['projectgroupid']]['projects']) >= 2)
{
	$projectlist = array();

	foreach ($vbulletin->pt_projects AS $projectgroupid => $projectgroupdata)
	{
		foreach ($projectgroupdata['projects'] AS $projectid => $projectdata)
		{
			if (!isset($perms_query["$projectdata[projectid]"]) OR $projectdata['displayorder'] == 0)
			{
				continue;
			}
	
			$projectlist[$projectdata['projectid']] = $projectdata;
		}
	}
}

// navbar and output
$navbits = construct_navbits(array('project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'], '' => $project['title_clean']));
$navbar = render_navbar_template($navbits);

($hook = vBulletinHook::fetch_hook('project_project_complete')) ? eval($hook) : false;

$templater = vB_Template::create('pt_project');
	$templater->register_page_templates();
	$templater->register('assignable_users', $assignable_users);
	$templater->register('columns', $columns);
	$templater->register('issuebits', $issuebits);
	$templater->register('navbar', $navbar);
	$templater->register('petitionbits', $petitionbits);
	$templater->register('postable_types', $postable_types);
	$templater->register('post_issue_options', $post_issue_options);
	$templater->register('post_new_issue_text', $post_new_issue_text);
	$templater->register('project', $project);
	$templater->register('projectlist', $projectlist);
	$templater->register('reportbits', $reportbits);
	$templater->register('search_status_options', $search_status_options);
	$templater->register('timeline', $timeline);
	$templater->register('type_counts', $type_counts);
	$templater->register('contenttypeid', $issue_contenttypeid);
print_output($templater->render());

?>