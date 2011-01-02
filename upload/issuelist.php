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
define('THIS_SCRIPT', 'project');
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
	'pt_navbar_search',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'issuelist' => array(
		'pt_issuelist',
		'pt_issuelist_arrow',
		'pt_listprojects',
		'pt_listprojects_link',
		'pt_postmenubit',
		'pt_issuebit',
		'pt_issuebit_pagelink',
		'pt_issuebit_deleted',
	),
);

if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['issueid']))
	{
		$_REQUEST['do'] = 'issue';
		$actiontemplates['none'] =& $actiontemplates['issue'];
	}
	else if (!empty($_REQUEST['projectid']))
	{
		$_REQUEST['do'] = 'project';
		$actiontemplates['none'] =& $actiontemplates['project'];
	}
	else
	{
		$_REQUEST['do'] = 'overview';
		$actiontemplates['none'] =& $actiontemplates['overview'];
	}
}

if ($_REQUEST['do'] == 'issue')
{
	define('GET_EDIT_TEMPLATES', true);
}

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

($hook = vBulletinHook::fetch_hook('project_start')) ? eval($hook) : false;

require_once(DIR . '/includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();
$issue_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Issue');
$project_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Project');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
if ($_REQUEST['do'] == 'issuelist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'appliesversionid' => TYPE_NOHTML,
		'issuestatusid' => TYPE_INT,
		'pagenumber' => TYPE_UINT,
		'sortfield' => TYPE_NOHTML,
		'sortorder' => TYPE_NOHTML
	));

	$project = verify_project($vbulletin->GPC['projectid']);
	if ($vbulletin->GPC['issuetypeid'])
	{
		verify_issuetypeid($vbulletin->GPC['issuetypeid'], $project['projectid']);
		$issuetype_printable = $vbphrase['issuetype_' . $vbulletin->GPC['issuetypeid'] . '_singular'];
		$issuetype_printable_plural = $vbphrase['issuetype_' . $vbulletin->GPC['issuetypeid'] . '_plural'];
		$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_" . $vbulletin->GPC['issuetypeid']];

		$vbphrase['post_new_issue_issuetype'] = $vbphrase["post_new_issue_" . $vbulletin->GPC['issuetypeid']];
	}
	else
	{
		$issuetype_printable = '';
		$vbphrase['applies_version_issuetype'] = '';

		$vbphrase['post_new_issue_issuetype'] = '';
	}

	($hook = vBulletinHook::fetch_hook('project_issuelist_start')) ? eval($hook) : false;

	// issues per page = 0 means "unlmiited"
	if (!$vbulletin->options['pt_issuesperpage'])
	{
		$vbulletin->options['pt_issuesperpage'] = 999999;
	}

	// activity list
	$perms_query = build_issue_permissions_query($vbulletin->userinfo);
	if (empty($perms_query["$project[projectid]"]))
	{
		print_no_permission();
	}

	$input['sortorder'] = $vbulletin->GPC['sortorder'];
	$input['issuetypeid'] = $vbulletin->GPC['issuetypeid'];
	$input['issuestatusid'] = $vbulletin->GPC['issuestatusid'];
	$input['appliesversionid'] = urlencode($vbulletin->GPC['appliesversionid']);

	$group_filter = 0;
	$version_filter = 0;

	if (!empty($vbulletin->GPC['appliesversionid']))
	{
		if ($vbulletin->GPC['appliesversionid'] == -1)
		{
			$version_filter = -1;
		}
		else
		{
			$type = $vbulletin->GPC['appliesversionid'][0];
			$value = intval(substr($vbulletin->GPC['appliesversionid'], 1));
			if ($type == 'g')
			{
				$group_filter = $value;
			}
			else
			{
				$version_filter = $value;
			}
		}
	}

	if ($vbulletin->GPC['issuestatusid'] == -1)
	{
		$status_limit = array();
		foreach ($vbulletin->pt_issuestatus AS $issuestatus)
		{
			if ($issuestatus['issuecompleted'] == 0)
			{
				$status_limit[] = $issuestatus['issuestatusid'];
			}
		}

		if ($status_limit)
		{
			$status_criteria = " AND issue.issuestatusid IN (" . implode(',', $status_limit) . ")";
		}
		else
		{
			// no matching statuses = no results
			$status_criteria = " AND 1=0";
		}
	}
	else if ($vbulletin->GPC['issuestatusid'] > 0)
	{
		$status_criteria = " AND issue.issuestatusid = " . $vbulletin->GPC['issuestatusid'];
	}
	else
	{
		$status_criteria = '';
	}

	require_once(DIR . '/includes/class_pt_issuelist.php');
	$issue_list = new vB_Pt_IssueList($project, $vbulletin);
	$issue_list->set_sort($vbulletin->GPC['sortfield'], $vbulletin->GPC['sortorder']);

	$list_criteria = $perms_query["$project[projectid]"] . "
		" . ($vbulletin->GPC['issuetypeid'] ? " AND issue.issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'" : '') . "
		$status_criteria
		" . ($group_filter ? " AND projectversion.projectversiongroupid = " . $group_filter : '') . "
		" . ($version_filter == -1 ? " AND issue.appliesversionid = 0" : '') . "
		" . ($version_filter > 0 ? " AND issue.appliesversionid = $version_filter" : '');

	$issue_list->exec_query($list_criteria, $vbulletin->GPC['pagenumber'], $vbulletin->options['pt_issuesperpage']);

	$nav_url_base = 'project.php?' . $vbulletin->session->vars['sessionurl'] . "do=issuelist&amp;projectid=$project[projectid]" .
			($vbulletin->GPC['issuetypeid'] ? '&amp;issuetypeid=' . $vbulletin->GPC['issuetypeid'] : '') .
			($vbulletin->GPC['issuestatusid'] ? '&amp;issuestatusid=' . $vbulletin->GPC['issuestatusid'] : '') .
			($vbulletin->GPC['appliesversionid'] ? '&amp;appliesversionid=' . $vbulletin->GPC['appliesversionid'] : '');

	$sort_arrow = $issue_list->fetch_sort_arrow_array($nav_url_base);

	$pagenav = construct_page_nav(
		$issue_list->real_pagenumber,
		$vbulletin->options['pt_issuesperpage'],
		$issue_list->total_rows,
		$nav_url_base,
		($issue_list->sort_field != 'lastpost' ? '&amp;sort=' . urlencode($issue_list->sort_field) : '') .
			($issue_list->sort_order != 'desc' ? '&amp;order=asc' : '')
	);

	$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

	$issuenewcount = array();
	$issueoldcount = array();
	$issuebits = '';
	while ($issue = $db->fetch_array($issue_list->result))
	{
		$issuebits .= build_issue_bit($issue, $project, $projectperms["$issue[issuetypeid]"]);

		$projectread["$issue[issuetypeid]"] = max($projectread["$issue[issuetypeid]"], $issue['projectread']);

		$lastread = max($issue['projectread'], $issue['issueread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
		if ($issue['lastpost'] > $lastread)
		{
			$issuenewcount["$issue[issueid]"] = $issue['lastpost'];
		}
		else
		{
			$issueoldcount["$issue[issueid]"] = $issue['lastpost'];
		}
	}

	// project marking
	if ($vbulletin->GPC['issuetypeid'])
	{
		$issuetypeid = $vbulletin->GPC['issuetypeid'];
	}
	else if (sizeof($projectread) == 1)
	{
		// no explicit issuetypeid, but implicitly on the page was displayed just one type
		$issuetypeid = key($projectread);
	}
	else
	{
		$issuetypeid = '';
	}

	if (!empty($issuetypeid) AND empty($issuenewcount) AND !empty($issueoldcount) AND $issue_list->real_pagenumber == 1 AND $issue_list->sort_field == 'lastpost' AND $issue_list->sort_order == 'desc')
	{
		arsort($issueoldcount, SORT_NUMERIC);
		$issuelastposttime = current($issueoldcount);

		$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);
		if ($marking)
		{
			$projectview = max($projectread["$issuetypeid"], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
		}
		else
		{
			$projectview = intval(fetch_bbarray_cookie('project_lastview', $project['projectid'] . $issuetypeid));
			if (!$projectview)
			{
				$projectview = $vbulletin->userinfo['lastvisit'];
			}
		}

		$perms_sql = build_issue_permissions_sql($vbulletin->userinfo);
		if ($issuelastposttime >= $projectview AND $perms_sql["$project[projectid]"]["$issuetypeid"])
		{
			// TODO: may need to change this to take into account private replies
			$unread = $db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				" . ($marking ? "
					LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				" : '') . "
				WHERE issue.projectid = $project[projectid]
					AND " . $perms_sql["$project[projectid]"]["$issuetypeid"] . "
					AND issue.lastpost > " . intval($projectview) . "
					" . ($marking ? "
						AND issue.lastpost > IF(issueread.readtime IS NOT NULL, issueread.readtime, " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . ")
					" : '') . "
			");

			if ($unread['count'] == 0)
			{
				mark_project_read($project['projectid'], $issuetypeid, TIMENOW);
			}
		}
	}

	// issue type selection options
	$issuetype_options = build_issuetype_select($projectperms, array_keys($vbulletin->pt_projects["$project[projectid]"]['types']), $vbulletin->GPC['issuetypeid']);
	$any_issuetype_selected = (!$vbulletin->GPC['issuetypeid'] ? ' selected="selected"' : '');

	// version options
	$version_cache = array();
	foreach ($vbulletin->pt_versions AS $version)
	{
		if ($version['projectid'] != $project['projectid'])
		{
			continue;
		}

		$version_cache["$version[projectversiongroupid]"][] = $version;
	}

	$appliesversion_options = '';
	$appliesversion_printable = ($vbulletin->GPC['appliesversionid'] == -1 ? $vbphrase['unknown'] : '');
	$version_groups = $db->query_read("
		SELECT projectversiongroup.projectversiongroupid, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup
		WHERE projectversiongroup.projectid = $project[projectid]
		ORDER BY projectversiongroup.displayorder DESC
	");

	$optionclass = '';
	while ($version_group = $db->fetch_array($version_groups))
	{
		$optionvalue = 'g' . $version_group['projectversiongroupid'];
		$optiontitle = $version_group['groupname'];
		$optionselected = ($optionvalue == $vbulletin->GPC['appliesversionid'] ? ' selected="selected"' : '');
		if ($optionselected)
		{
			$appliesversion_printable = $version_group['groupname'];
		}

		$appliesversion_options .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);

		if (!is_array($version_cache["$version_group[projectversiongroupid]"]))
		{
			continue;
		}

		foreach ($version_cache["$version_group[projectversiongroupid]"] AS $version)
		{
			$optionvalue = 'v' . $version['projectversionid'];
			$optiontitle = '-- ' . $version['versionname'];
			$optionselected = ($optionvalue == $vbulletin->GPC['appliesversionid'] ? ' selected="selected"' : '');
			if ($optionselected)
			{
				$appliesversion_printable = $version['versionname'];
			}

			$appliesversion_options .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
		}
	}

	$anyversion_selected = ($vbulletin->GPC['appliesversionid'] == 0 ? ' selected="selected"' : '');
	$unknownversion_selected = ($vbulletin->GPC['appliesversionid'] == -1 ? ' selected="selected"' : '');

	// status options / posting options drop down
	$postable_types = array();
	$status_options = '';
	$post_issue_options = '';
	foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
	{
		if (($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) AND ($projectperms["$issuetypeid"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
		{
			$postable_types[] = $issuetypeid;
			$type = $typeinfo;
			$typename = $vbphrase["issuetype_{$issuetypeid}_singular"];
			$templater = vB_Template::create('pt_postmenubit');
				$templater->register('project', $project);
				$templater->register('type', $type);
				$templater->register('typename', $typename);
				$templater->register('contenttypeid', $issue_contenttypeid);
			$post_issue_options .= $templater->render();
		}


		if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			continue;
		}

		$optgroup_options = build_issuestatus_select($typeinfo['statuses'], $vbulletin->GPC['issuestatusid']);
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

	// search box data
	$assignable_users = fetch_assignable_users_select($project['projectid']);
	$search_status_options = fetch_issue_status_search_select($projectperms);

	// Project jump
	if ($vbulletin->options['pt_listprojects_activate'] AND $vbulletin->options['pt_listprojects_locations'] & 2)
	{
		$ptdropdown = '';
		$perms_query = build_issue_permissions_query($vbulletin->userinfo);

		foreach ($vbulletin->pt_projects AS $projectlist)
		{
			if (!isset($perms_query["$projectlist[projectid]"]) OR $projectlist['displayorder'] == 0)
			{
				continue;
			}

			$templater = vB_Template::create('pt_listprojects_link');
				$templater->register('issuetypeid', $vbulletin->GPC['issuetypeid']);
				$templater->register('projectlist', $projectlist);
			$ptdropdown .= $templater->render();
		}

		if ($ptdropdown)
		{
			// Define particular conditions for spaces
			$navpopup = array();
			$navpopup['css'] = '';

			if ($vbulletin->options['pt_listprojects_locations'] & 2)
			{
				if (empty($pagenav))
				{
					if ($vbulletin->options['pt_listprojects_position_issuelist'] == 1)
					{
						if ($vbphrase['post_new_issue_issuetype'])
						{
							$navpopup['css'] = 'margin43';
						}
						else
						{
							$navpopup['css'] = 'margin38';
						}
					}
					else
					{
						$navpopup['css'] = 'margin43';
					}
				}
			}

			$navpopup['title'] = $project['title'];

			// Evaluate the drop_down menu
			$templater = vB_Template::create('pt_listprojects');
				$templater->register('navpopup', $navpopup);
				$templater->register('ptdropdown', $ptdropdown);
			$pt_ptlist = $templater->render();
		}
	}

	// navbar and output
	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean']
	);
	if ($vbulletin->GPC['issuetypeid'])
	{
		$navbits["project.php?" . $vbulletin->session->vars['sessionurl'] . "do=issuelist&amp;projectid=$project[projectid]&amp;issuetypeid=" . $vbulletin->GPC['issuetypeid']] = $vbphrase['issuetype_' . $vbulletin->GPC['issuetypeid'] . '_singular'];
	}
	$navbits[''] = $vbphrase['issue_list'];
	$navbits = construct_navbits($navbits);

	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_issuelist_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_issuelist');
		$templater->register_page_templates();
		$templater->register('activestatus_selected', $activestatus_selected);
		$templater->register('anystatus_selected', $anystatus_selected);
		$templater->register('anyversion_selected', $anyversion_selected);
		$templater->register('any_issuetype_selected', $any_issuetype_selected);
		$templater->register('appliesversion_options', $appliesversion_options);
		$templater->register('appliesversion_printable', $appliesversion_printable);
		$templater->register('assignable_users', $assignable_users);
		$templater->register('input', $input);
		$templater->register('issuebits', $issuebits);
		$templater->register('issuestatus_printable', $issuestatus_printable);
		$templater->register('issuetype_options', $issuetype_options);
		$templater->register('issuetype_printable', $issuetype_printable);
		$templater->register('issuetype_printable_plural', $issuetype_printable_plural);
		$templater->register('navbar', $navbar);
		$templater->register('pagenav', $pagenav);
		$templater->register('postable_types', $postable_types);
		$templater->register('post_issue_options', $post_issue_options);
		$templater->register('project', $project);
		$templater->register('pt_ptlist', $pt_ptlist);
		$templater->register('search_status_options', $search_status_options);
		$templater->register('sortfield', $sortfield);
		$templater->register('sort_arrow', $sort_arrow);
		$templater->register('status_options', $status_options);
		$templater->register('unknownversion_selected', $unknownversion_selected);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

?>