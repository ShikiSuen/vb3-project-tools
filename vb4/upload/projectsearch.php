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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'projectsearch');
define('CSRF_PROTECTION', true);
define('PROJECT_SCRIPT', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('projecttools', 'posting', 'search');

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
	'pt_navbar_search',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'search' => array(
		'pt_search',
		'pt_checkbox_option',
		'pt_checkbox_option_hidden',
		'pt_checkbox_optgroup',
		'optgroup',
	),
	'searchresults' => array(
		'pt_searchresults',
		'pt_searchresultbit',
		'pt_searchresultgroupbit',
		'pt_searchresultgroupbit_arrow'
	),
	'savereport' => array(
		'pt_savereport'
	),
	'reports' => array(
		'pt_reportlist',
		'pt_reportbit'
	),
);

if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['searchid']))
	{
		$_REQUEST['do'] = 'issue';
		$actiontemplates['none'] =& $actiontemplates['searchresults'];
	}
	else
	{
		$_REQUEST['do'] = 'search';
		$actiontemplates['none'] =& $actiontemplates['search'];
	}
}

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
require_once(DIR . '/includes/functions_pt_search.php');

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('projectsearch_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
if ($_REQUEST['do'] == 'search')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid'     => TYPE_UINT,
		'milestoneid'   => TYPE_UINT,
		'issuetypeid'   => TYPE_NOHTML,
		'contenttypeid' => TYPE_UINT,
	));

	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('projectsearch_form_start')) ? eval($hook) : false;

	$limit_shown_projectid = null;
	if ($vbulletin->GPC['milestoneid'])
	{
		require_once(DIR . '/includes/functions_pt_milestone.php');

		$milestone = $db->query_first("
			SELECT milestone.*, project.title_clean AS project_title
			FROM " . TABLE_PREFIX . "pt_milestone AS milestone
			INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = milestone.projectid)
			WHERE milestone.milestoneid = " . $vbulletin->GPC['milestoneid']
		);
		if (!$milestone)
		{
			standard_error(fetch_error('invalidid', $vbphrase['milestone'], $vbulletin->options['contactuslink']));
		}

		$projectperms = fetch_project_permissions($vbulletin->userinfo, $milestone['projectid']);

		$milestone_types = fetch_viewable_milestone_types($projectperms);
		if (!$milestone_types)
		{
			print_no_permission();
		}

		$limit_shown_projectid = $milestone['projectid'];
		$vbulletin->GPC['projectid'] = $milestone['projectid'];
	}

	// These settings are on by default - they are turning off if projectid is specified in url
	$show['category'] = true;
	$show['appliesversionid'] = true;
	$show['priority'] = true;

	// cache for project names - [projectid] = title_clean
	$project_names = array();

	// project drop down
	$projects = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY displayorder
	");

	$project_options = '';
	while ($project = $db->fetch_array($projects))
	{
		if (!isset($search_perms["$project[projectid]"]))
		{
			// can't search or view
			continue;
		}

		// add name to project name cache
		$project_names["$project[projectid]"] = $project['title_clean'];

		$optionname = 'projectid[]';
		$optionvalue = $project['projectid'];
		$optiontitle = $project['title_clean'];
		$optionid = "project_$project[projectid]";
		$optionchecked = ($project['projectid'] == $vbulletin->GPC['projectid'] ? ' checked="checked"' : '');

		if ($limit_shown_projectid)
		{
			if ($limit_shown_projectid != $project['projectid'])
			{
				$templater = vB_Template::create('pt_checkbox_option_hidden');
					$templater->register('optionchecked', $optionchecked);
					$templater->register('optionid', $optionid);
					$templater->register('optionname', $optionname);
					$templater->register('optiontitle', $optiontitle);
					$templater->register('optionvalue', $optionvalue);
				$project_options .= $templater->render();
				continue;
			}
			else
			{
				$optionchecked .= ' disabled="disabled"';
			}
		}

		$show['category'] = ($project['requirecategory'] > 0);
		$show['appliesversionid'] = ($project['requireappliesversion'] > 0);
		$show['priority'] = ($project['requirepriority'] > 0);

		$templater = vB_Template::create('pt_checkbox_option');
			$templater->register('optionchecked', $optionchecked);
			$templater->register('optionid', $optionid);
			$templater->register('optionname', $optionname);
			$templater->register('optiontitle', $optiontitle);
			$templater->register('optionvalue', $optionvalue);
		$project_options .= $templater->render();
	}

	$optionchecked = '';

	// assigned user drop down
	$assign_list = array();
	foreach ($vbulletin->pt_assignable AS $types)
	{
		foreach ($types AS $type)
		{
			$assign_list += $type;
		}
	}
	asort($assign_list);

	$assignable_users = array('col1' => '', 'col2' => '');
	$col_count = ceil(sizeof($assign_list) / 2);
	$i = 0;
	$colid = 'col1';
	foreach ($assign_list AS $optionvalue => $optiontitle)
	{
		$optionname = 'assigneduser[]';
		$optionid = "assigneduser_$optionvalue";
		$templater = vB_Template::create('pt_checkbox_option');
			$templater->register('optionchecked', $optionchecked);
			$templater->register('optionid', $optionid);
			$templater->register('optionname', $optionname);
			$templater->register('optiontitle', $optiontitle);
			$templater->register('optionvalue', $optionvalue);
		$assignable_users[$colid] .= $templater->render();

		if (++$i >= $col_count)
		{
			$colid = 'col2';
		}
	}

	// status options drop down
	$status_options = '';
	foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
	{
		$optgroup_options = fetch_pt_search_issuestatus_options($typeinfo['statuses'], $issue['issuestatusid']);

		$optionid = $issuetypeid;
		$optgroup_name = 'issuetypeid[]';
		$optgroup_value = $issuetypeid;
		$optgroup_label = $vbphrase["issuetype_{$issuetypeid}_singular"];
		$optgroup_id = "issuetype_{$issuetypeid}_statuses";
		$optionchecked = ($issuetypeid == $vbulletin->GPC['issuetypeid'] ? ' checked="checked"' : '');
		$show['optgroup_checkbox'] = true;
		$templater = vB_Template::create('pt_checkbox_optgroup');
			$templater->register('optgroup_id', $optgroup_id);
			$templater->register('optgroup_label', $optgroup_label);
			$templater->register('optgroup_name', $optgroup_name);
			$templater->register('optgroup_options', $optgroup_options);
			$templater->register('optgroup_value', $optgroup_value);
			$templater->register('optionchecked', $optionchecked);
		$status_options .= $templater->render();
	}

	$optionchecked = '';

	// tag drop down
	$tags = $db->query_read("
		SELECT tagtext
		FROM " . TABLE_PREFIX . "pt_tag
		ORDER BY tagtext
	");

	$tag_options = array('col1' => '', 'col2' => '');
	$col_count = ceil($db->num_rows($tags) / 2);
	$i = 0;
	$colid = 'col1';

	$optionclass = '';
	$optionselected = '';
	while ($tag = $db->fetch_array($tags))
	{
		$optionname = 'tag[]';
		$optionvalue = $tag['tagtext'];
		$optiontitle = $tag['tagtext'];
		$optionid = "tag_$i";

		$templater = vB_Template::create('pt_checkbox_option');
			$templater->register('optionchecked', $optionchecked);
			$templater->register('optionid', $optionid);
			$templater->register('optionname', $optionname);
			$templater->register('optiontitle', $optiontitle);
			$templater->register('optionvalue', $optionvalue);
		$tag_options[$colid] .= $templater->render();

		if (++$i >= $col_count)
		{
			$colid = 'col2';
		}
	}

	// setup versions
	fetch_pt_search_versions($appliesversion_options, $addressedversion_options, $project_names);

	// setup categories
	$category_options = '';
	if ($show['category'])
	{
		$category_options = fetch_pt_search_categories($project_names);
	}

	// setup priorities
	$priority_options = '';
	if ($show['priority'])
	{
		$priority_options = fetch_pt_search_priorities($project_names);
	}

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'' => $vbphrase['search']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectsearch_form_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_search');
		$templater->register_page_templates();
		$templater->register('addressedversion_options', $addressedversion_options);
		$templater->register('appliesversion_options', $appliesversion_options);
		$templater->register('assignable_users', $assignable_users);
		$templater->register('category_options', $category_options);
		$templater->register('contenttypeid', intval($vbulletin->GPC['contenttypeid']));
		$templater->register('milestone', $milestone);
		$templater->register('navbar', $navbar);
		$templater->register('priority_options', $priority_options);
		$templater->register('project_options', $project_options);
		$templater->register('status_options', $status_options);
		$templater->register('tag_options', $tag_options);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'dosearch')
{
	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	check_pt_search_floodcheck();

	// directly searchable fields only
	$search_fields = array(
		'text'      => TYPE_STR,
		'issuetext' => TYPE_STR,
		'firsttext' => TYPE_STR,

		'user' => TYPE_NOHTML,
		'user_issue' => TYPE_NOHTML,

		'searchdate_gteq' => TYPE_INT,
		'searchdate_lteq' => TYPE_INT,

		'replycount_gteq' => TYPE_UINT,
		'replycount_lteq' => TYPE_UINT,

		'votecount_pos_gteq' => TYPE_UINT,
		'votecount_pos_lteq' => TYPE_UINT,
		'votecount_neg_gteq' => TYPE_UINT,
		'votecount_neg_lteq' => TYPE_UINT,

		'projectid'     => TYPE_ARRAY_UINT,
		'milestoneid'   => TYPE_UINT,

		'assigneduser'  => TYPE_ARRAY_UINT,
		'tag'           => TYPE_ARRAY_STR,

		'issuetypeid'   => TYPE_ARRAY_STR,
		'issuestatusid' => TYPE_ARRAY_UINT,
		'typestatusmix' => TYPE_ARRAY,

		'appliesversion'   => TYPE_ARRAY_INT,
		'appliesgroup'     => TYPE_ARRAY_INT,
		'appliesmix'       => TYPE_ARRAY,

		'addressedversion' => TYPE_ARRAY_INT,
		'addressedgroup'   => TYPE_ARRAY_INT,
		'addressedmix'     => TYPE_ARRAY,

		'projectcategoryid' => TYPE_ARRAY_INT,
		'projectpriorityid' => TYPE_ARRAY_INT,

		'needsattachments'      => TYPE_UINT,
		'needspendingpetitions' => TYPE_UINT,

		'newonly' => TYPE_BOOL,
	);

	// auxiliary fields that help searching, but aren't directly searchable
	$aux_fields = array(
		'gotoissueinteger' => TYPE_BOOL,

		'textlocation'    => TYPE_STR,
		'userissuesonly'  => TYPE_NOHTML,

		'searchdate'      => TYPE_INT,
		'searchdate_type' => TYPE_STR,

		'replycount'      => TYPE_INT,
		'replycount_type' => TYPE_STR,

		'votecount'        => TYPE_INT,
		'votecount_type'   => TYPE_STR,
		'votecount_posneg' => TYPE_STR,

		'sort'      => TYPE_NOHTML,
		'sortorder' => TYPE_NOHTML,
		'groupby'   => TYPE_NOHTML
	);

	$vbulletin->input->clean_array_gpc('r', $search_fields + $aux_fields);

	if ($vbulletin->GPC['gotoissueinteger'] AND preg_match('#^\d+$#', $vbulletin->GPC['text']) AND $db->query_first("SELECT issueid FROM " . TABLE_PREFIX . "pt_issue WHERE issueid = " . intval($vbulletin->GPC['text'])))
	{
		exec_header_redirect("project.php?" . $vbulletin->session->vars['sessionurl_js'] . "issueid=" . intval($vbulletin->GPC['text']));
		exit;
	}

	($hook = vBulletinHook::fetch_hook('projectsearch_dosearch_start')) ? eval($hook) : false;

	// aux setup
	process_aux_search_cases($vbulletin->GPC);

	// #### do the search ####
	require_once(DIR . '/includes/class_pt_issuesearch.php');
	$search = new vB_Pt_IssueSearch($vbulletin);

	foreach ($search_fields AS $fieldname => $clean_type)
	{
		$search->add($fieldname, $vbulletin->GPC["$fieldname"]);
	}

	$search->set_sort($vbulletin->GPC['sort'], $vbulletin->GPC['sortorder']);
	$search->set_group($vbulletin->GPC['groupby']);

	($hook = vBulletinHook::fetch_hook('projectsearch_dosearch_fields')) ? eval($hook) : false;

	if ($search->has_errors())
	{
		handle_pt_search_errors($search->generator->errors);
	}

	if (!$search->has_criteria())
	{
		standard_error(fetch_error('pt_need_search_criteria'));
	}

	$searchid = $search->execute('(' . implode(') OR (', $search_perms) . ')');
	if (!$searchid)
	{
		handle_pt_search_errors($search->generator->errors);
	}

	($hook = vBulletinHook::fetch_hook('projectsearch_dosearch_complete')) ? eval($hook) : false;

	$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$searchid";
	eval(print_standard_redirect('pt_searchexecuted'));
}

// #######################################################################
if ($_REQUEST['do'] == 'douser')
{
	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	check_pt_search_floodcheck();

	$vbulletin->input->clean_array_gpc('r', array(
		'userid' => TYPE_UINT,
		'type' => TYPE_NOHTML
	));

	$user = fetch_userinfo($vbulletin->GPC['userid']);
	if (!$user)
	{
		standard_error('invalid_user_specified');
	}

	// #### do the search ####
	require_once(DIR . '/includes/class_pt_issuesearch.php');
	$search = new vB_Pt_IssueSearch($vbulletin);
	$search->add(($vbulletin->GPC['type'] == 'issue' ? 'user_issue' : 'user'), $user['username']);

	($hook = vBulletinHook::fetch_hook('projectsearch_douser')) ? eval($hook) : false;

	$searchid = $search->execute('(' . implode(') OR (', $search_perms) . ')');
	if (!$searchid)
	{
		handle_pt_search_errors($search->generator->errors);
	}

	$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$searchid";
	eval(print_standard_redirect('pt_searchexecuted'));
}

// #######################################################################
if ($_REQUEST['do'] == 'resort')
{
	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'searchid' => TYPE_UINT,
		'sort' => TYPE_NOHTML,
		'sortorder' => TYPE_NOHTML,
		'groupid' => TYPE_NOTML
	));

	// searches can only be viewed by the same person that made the search (guests could share searches)
	$search = verify_pt_search($vbulletin->GPC['searchid']);

	require_once(DIR . '/includes/class_pt_issuesearch.php');
	$search_query = new vB_Pt_IssueSearch_Resort($vbulletin);
	$search_query->set_issuesearchid($search['issuesearchid']);

	$search_query->set_sort($vbulletin->GPC['sort'], $vbulletin->GPC['sortorder']);

	($hook = vBulletinHook::fetch_hook('projectsearch_resort')) ? eval($hook) : false;

	if ($search_query->has_errors())
	{
		handle_pt_search_errors($search_query->generator->errors);
	}

	$searchid = $search_query->execute('(' . implode(') OR (', $search_perms) . ')');
	if (!$searchid)
	{
		handle_pt_search_errors($search_query->generator->errors);
	}

	$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$searchid" . ($vbulletin->GPC['groupid'] ? "&groupid=" . urlencode($vbulletin->GPC['groupid']) : '');
	eval(print_standard_redirect('pt_searchexecuted'));
}

// #######################################################################
if ($_REQUEST['do'] == 'searchresults')
{
	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'searchid' => TYPE_UINT,
		'start' => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'groupid' => TYPE_NOHTML,
	));

	// searches can only be viewed by the same person that made the search (guests could share searches)
	$search = verify_pt_search($vbulletin->GPC['searchid']);

	($hook = vBulletinHook::fetch_hook('projectsearch_results_start')) ? eval($hook) : false;

	$groups = prepare_group_filter($search, $vbulletin->GPC['groupid'], $perpage);
	$request_groupid = urlencode($vbulletin->GPC['groupid']);

	if (!$vbulletin->GPC['pagenumber'])
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}
	if (!$vbulletin->GPC['start'])
	{
		$vbulletin->GPC['start'] = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
	}

	// setup the sort arrow
	$opposite_sort = ($search['sortorder'] == 'asc' ? 'desc' : 'asc');
	$sort_arrow = array(
		'title' => '',
		'priority' => '',
		'replies' => '',
		'lastpost' => '',
	);
	$templater = vB_Template::create('pt_searchresultgroupbit_arrow');
		$templater->register('opposite_sort', $opposite_sort);
		$templater->register('request_groupid', $request_groupid);
		$templater->register('search', $search);
	$sort_arrow["$search[sortby]"] = $templater->render();

	if (!$perpage)
	{
		$perpage = 999999;
	}

	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);

	build_issue_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

	$replycount_clause = fetch_private_replycount_clause($vbulletin->userinfo);

	$repeat_search_link = generate_repeat_search_link($search['criteria'], $search['sortby'], $search['sortorder'], $search['groupby']);
	$show['save_report'] = ($vbulletin->userinfo['userid'] AND !$search['issuereportid'] AND $vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['cancreatereport']);

	$show['first_group'] = true;
	$resultgroupbits = '';
	foreach ($groups AS $groupid => $group)
	{
		$group_only = ($vbulletin->GPC['groupid'] AND $vbulletin->GPC['groupid'] == $groupid);

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('projectsearch_results_query')) ? eval($hook) : false;

		$results = $db->query_read("
			SELECT issue.*, issuesearchresult.offset
				" . ($vbulletin->userinfo['userid'] ? ", issuesubscribe.subscribetype, IF(issueassign.issueid IS NULL, 0, 1) AS isassigned" : '') . "
				" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
				" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "pt_issuesearchresult AS issuesearchresult
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issuesearchresult.issueid = issue.issueid)
			" . ($vbulletin->userinfo['userid'] ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON
					(issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON
					(issueassign.issueid = issue.issueid AND issueassign.userid = " . $vbulletin->userinfo['userid'] . ")
			" : '') . "
			" . ($marking ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			" : '') . "
			$private_lastpost_join
			$hook_query_joins
			WHERE issuesearchresult.issuesearchid = $search[issuesearchid]
				" . (!$group_only ? " AND issuesearchresult.offset >= " . $vbulletin->GPC['start'] : '') . "
				" . ($groupid != -1 ? " AND issuesearchresult.groupid = '" . $db->escape_string($groupid) . "'" : '') . "
				AND ((" . implode(') OR (', $search_perms) . "))
				$hook_query_where
			ORDER BY issuesearchresult.offset
			" . ($group_only ? "LIMIT " . $vbulletin->GPC['start'] . ", $perpage" : "LIMIT $perpage") . "
		");

		$resultbits = '';
		while ($issue = $db->fetch_array($results))
		{
			$resultbits .= build_pt_search_resultbit($issue);
		}
		if (!$resultbits)
		{
			continue;
		}

		if ($search['groupby'])
		{
			if (!empty($group['phraseme']))
			{
				$group['grouptitle'] = $vbphrase[str_replace('*', $group['groupid'], $group['grouptitle'])];
			}
			$group_phrase = 'search_group_' . $search['groupby'];
			$group_header = construct_phrase($vbphrase["$group_phrase"], $group['grouptitle']);
		}
		else
		{
			$group_header = ($search['issuereportid'] ? construct_phrase($vbphrase['search_results_report_x'], $search['reporttitle']) : $vbphrase['search_results']);
		}

		if ($groupid != -1 AND $vbulletin->GPC['pagenumber'] == 1 AND !$vbulletin->GPC['groupid'])
		{
			$pagenav = '';
			$show['view_more'] = ($vbulletin->GPC['pagenumber'] * $perpage < $group['count']);
		}
		else
		{
			$pagenav = construct_page_nav(
				$vbulletin->GPC['pagenumber'],
				$perpage,
				$group['count'],
				'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&amp;searchid=$search[issuesearchid]" .
					($groupid != -1 ? "&amp;groupid=$groupid" : ''),
				''
			);

			$show['view_more'] = false;
		}

		$group['count'] = vb_number_format($group['count']);

		$project = fetch_project_info($group['projectid']);

		$bypass = (($group['groupid'] == 0));

		// Definition to display selected columns
		$columns = fetch_issuelist_columns($vbulletin->options['issuelist_columns'], $project, $bypass);

		($hook = vBulletinHook::fetch_hook('projectsearch_results_groupbit')) ? eval($hook) : false;

		$templater = vB_Template::create('pt_searchresultgroupbit');
			$templater->register('columns', $columns);
			$templater->register('group', $group);
			$templater->register('groupid', $groupid);
			$templater->register('group_header', $group_header);
			$templater->register('pagenav', $pagenav);
			$templater->register('repeat_search_link', $repeat_search_link);
			$templater->register('request_groupid', $request_groupid);
			$templater->register('resultbits', $resultbits);
			$templater->register('search', $search);
			$templater->register('sort_arrow', $sort_arrow);
		$resultgroupbits .= $templater->render();

		$show['first_group'] = false;
	}

	if (!$resultgroupbits)
	{
		standard_error(fetch_error('searchnoresults', ''));
	}

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'projectsearch.php?' . $vbulletin->session->var['sessionurl'] . 'do=search' => $vbphrase['search'],
		'' => ($search['issuereportid'] ? construct_phrase($vbphrase['search_results_report_x'], $search['reporttitle']) : $vbphrase['search_results'])
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectsearch_results_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_searchresults');
		$templater->register_page_templates();
		$templater->register('navbar', $navbar);
		$templater->register('repeat_search_link', $repeat_search_link);
		$templater->register('resultgroupbits', $resultgroupbits);
		$templater->register('search', $search);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'dosavereport' OR $_REQUEST['do'] == 'savereport')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'searchid' => TYPE_UINT,
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['cancreatereport']))
	{
		print_no_permission();
	}

	$search = verify_pt_search($vbulletin->GPC['searchid']);
}

// #######################################################################
if ($_POST['do'] == 'dosavereport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_NOHTML,
		'description' => TYPE_NOHTML,
		'public' => TYPE_BOOL
	));

	if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['cancreatepublicreport']))
	{
		$vbulletin->GPC['public'] = 0;
	}

	$report = datamanager_init('Pt_IssueReport', $vbulletin, ERRTYPE_STANDARD);
	$report->set('title', $vbulletin->GPC['title']);
	$report->set('description', $vbulletin->GPC['description']);
	$report->set('public', $vbulletin->GPC['public'] ? 1 : 0);
	$report->set('userid', $vbulletin->userinfo['userid']);
	$report->set('criteria', $search['criteria']);
	$report->set('sortby', $search['sortby']);
	$report->set('sortorder', $search['sortorder']);
	$report->set('groupby', $search['groupby']);
	$report->set_info('subscribe_searchid', $search['issuesearchid']);

	($hook = vBulletinHook::fetch_hook('projectsearch_report_save')) ? eval($hook) : false;

	$reportid = $report->save();

	$vbulletin->url = 'project.php' . $vbulletin->session->vars['sessionurl_q'];
	eval(print_standard_redirect('pt_report_created'));
}

// #######################################################################
if ($_REQUEST['do'] == 'savereport')
{
	$show['public_option'] = ($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['cancreatepublicreport']);

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'projectsearch.php?' . $vbulletin->session->var['sessionurl'] . 'do=search' => $vbphrase['search'],
		'' => $vbphrase['save_report']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectsearch_report_form')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_savereport');
		$templater->register_page_templates();
		$templater->register('navbar', $navbar);
		$templater->register('search', $search);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'viewreport')
{
	$vbulletin->input->clean_gpc('r', 'issuereportid', TYPE_UINT);

	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		print_no_permission();
	}

	$report = $db->query_first("
		SELECT issuereport.*, IF(issuereportsubscribe.issuesearchid IS NOT NULL, 1, 0) AS issubscribed,
			issuesearch.issuesearchid
		FROM " . TABLE_PREFIX . "pt_issuereport AS issuereport
		LEFT JOIN " . TABLE_PREFIX . "pt_issuereportsubscribe AS issuereportsubscribe ON
			(issuereportsubscribe.issuereportid = issuereport.issuereportid AND issuereportsubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "pt_issuesearch AS issuesearch ON
			(issuesearch.issuesearchid = issuereportsubscribe.issuesearchid AND issuesearch.dateline >= " . (TIMENOW - 3600) . ")
		WHERE issuereport.issuereportid = " . $vbulletin->GPC['issuereportid'] . "
			AND (issuereport.public = 1 OR (issuereport.public = 0 AND issuereport.userid = " . $vbulletin->userinfo['userid'] . "))
	");

	if (!$report)
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue_report'], $vbulletin->options['contactuslink']));
	}

	// NOTE: disabled caching
	if ($report['issuesearchid'] AND 1==0)
	{
		$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$report[issuesearchid]";
		eval(print_standard_redirect('pt_searchexecuted'));
	}

	require_once(DIR . '/includes/class_pt_issuesearch.php');
	$search = new vB_Pt_IssueSearch($vbulletin);

	foreach (unserialize($report['criteria']) AS $name => $value)
	{
		$search->add($name, $value);
	}

	$search->set_sort($report['sortby'], $report['sortorder']);
	$search->set_group($report['groupby']);
	$search->set_issuereportid($report['issuereportid']);

	($hook = vBulletinHook::fetch_hook('projectsearch_report_view')) ? eval($hook) : false;

	$searchid = $search->execute('(' . implode(') OR (', $search_perms) . ')');
	if (!$searchid)
	{
		handle_pt_search_errors($search->generator->errors);
	}

	if ($report['issubscribed'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuereportsubscribe SET
				issuesearchid = $searchid
			WHERE issuereportid = $report[issuereportid]
				AND userid = " . $vbulletin->userinfo['userid']
		);
	}

	$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$searchid";
	eval(print_standard_redirect('pt_searchexecuted'));
}

// #######################################################################
if ($_POST['do'] == 'reportsubscription')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'subscribe' => TYPE_ARRAY_KEYS_INT,
		'delete' => TYPE_ARRAY_KEYS_INT
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if ($vbulletin->GPC['delete'])
	{
		$reports = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuereport AS issuereport
			WHERE issuereportid IN (" . implode(',', $vbulletin->GPC['delete']) . ")
				AND issuereport.public = 1
		");
		while ($report = $db->fetch_array($reports))
		{
			$can_delete = (($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportothers'])
				OR ($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportown'] AND $vbulletin->userinfo['userid'] == $report['userid']));
			if ($can_delete)
			{
				$reportdata = datamanager_init('Pt_IssueReport', $vbulletin, ERRTYPE_STANDARD);
				$reportdata->set_existing($report);
				$reportdata->delete();
			}
		}
	}
	else
	{
		$reports = $db->query_read("
			SELECT issuereport.*
			FROM " . TABLE_PREFIX . "pt_issuereport AS issuereport
			WHERE issuereport.public = 1 OR (issuereport.public = 0 AND issuereport.userid = " . $vbulletin->userinfo['userid'] . ")
		");

		$subscribe = array();
		$unsubscribe = array();

		while ($report = $db->fetch_array($reports))
		{
			if (in_array($report['issuereportid'], $vbulletin->GPC['subscribe']))
			{
				// we want to subscribe to this
				$subscribe[] = "($report[issuereportid], " . $vbulletin->userinfo['userid'] . ")";
			}
			else if ($report['public'])
			{
				// want to unsubscribe from this, but it's public -- just unsubscribe
				$unsubscribe[] = $report['issuereportid'];
			}
			else
			{
				// this is private and we want to unsubscribe -- delete it
				$reportdata = datamanager_init('Pt_IssueReport', $vbulletin, ERRTYPE_STANDARD);
				$reportdata->set_existing($report);
				$reportdata->delete();
			}
		}

		if ($unsubscribe)
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "pt_issuereportsubscribe
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND issuereportid IN (" . implode(',', $unsubscribe) . ")
			");
		}

		if ($subscribe)
		{
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "pt_issuereportsubscribe
					(issuereportid, userid)
				VALUES
					" . implode(',', $subscribe)
			);
		}

	}

	$vbulletin->url = 'projectsearch.php?' . $vbulletin->session->vars['sessionurl'] . 'do=reports';
	eval(print_standard_redirect('pt_report_subscriptions_updated'));
}

// #######################################################################
if ($_REQUEST['do'] == 'reports')
{
	$can_delete_public = (($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportown'])
		OR ($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportothers']));

	($hook = vBulletinHook::fetch_hook('projectsearch_reportlist_start')) ? eval($hook) : false;

	$reports = $db->query_read("
		SELECT issuereport.*, IF(issuereportsubscribe.issuesearchid IS NOT NULL, 1, 0) AS issubscribed,
			user.username
		FROM " . TABLE_PREFIX . "pt_issuereport AS issuereport
		LEFT JOIN " . TABLE_PREFIX . "pt_issuereportsubscribe AS issuereportsubscribe ON
			(issuereport.issuereportid = issuereportsubscribe.issuereportid AND issuereportsubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuereport.userid)
		WHERE (issuereport.public = 1 OR (issuereport.public = 0 AND issuereport.userid = " . $vbulletin->userinfo['userid'] . "))
		ORDER BY issuereport.public, issubscribed, issuereport.title
	");

	$viewable_projects = build_issue_permissions_sql($vbulletin->userinfo);
	$reportbits = '';
	while ($report = $db->fetch_array($reports))
	{
		if (!can_view_report($report, $viewable_projects))
		{
			continue;
		}

		$report['description'] = nl2br($report['description']);

		exec_switch_bg();
		if ($report['public'])
		{
			$colspan = ($can_delete_public ? 4 : 3);
			$show['submitted_user'] = true;
			$show['delete_option'] = (($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportothers'])
				OR ($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['candeletepublicreportown'] AND $vbulletin->userinfo['userid'] == $report['userid']));
			$show['delete_public_column'] = $can_delete_public;

			($hook = vBulletinHook::fetch_hook('projectsearch_report_bit')) ? eval($hook) : false;

			$templater = vB_Template::create('pt_reportbit');
				$templater->register('bgclass', $bgclass);
				$templater->register('report', $report);
			$publicreportbits .= $templater->render();
		}
		else
		{
			$show['submitted_user'] = false;
			$show['delete_option'] = false;
			$show['delete_public_column'] = false;

			($hook = vBulletinHook::fetch_hook('projectsearch_reportlist_bit')) ? eval($hook) : false;

			$templater = vB_Template::create('pt_reportbit');
				$templater->register('bgclass', $bgclass);
				$templater->register('report', $report);
			$privatereportbits .= $templater->render();
		}
	}

	$show['delete_public_column'] = $can_delete_public;

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'' => $vbphrase['reports']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectsearch_reportlist_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_reportlist');
		$templater->register_page_templates();
		$templater->register('colspan', $colspan);
		$templater->register('navbar', $navbar);
		$templater->register('privatereportbits', $privatereportbits);
		$templater->register('publicreportbits', $publicreportbits);
	print_output($templater->render());
}

?>