<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
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
	'overview' => array(
		'pt_markread_script',
		'pt_overview',
		'pt_projectbit',
		'pt_projectbit_typecount',
		'pt_timeline',
		'pt_timeline_group',
		'pt_timeline_item',
		'pt_reportmenubit'
	),
	'project' => array(
		'pt_listprojects',
		'pt_listprojects_link',
		'pt_project',
		'pt_project_typecountbit',
		'pt_postmenubit',
		'pt_issuebit',
		'pt_issuebit_deleted',
		'pt_timeline',
		'pt_timeline_group',
		'pt_timeline_item',
		'pt_petitionbit',
		'pt_reportmenubit'
	),
	'timeline' => array(
		'pt_timeline_page',
		'pt_timeline',
		'pt_timeline_group',
		'pt_timeline_item',
	),
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
	'issue' => array(
		'pt_issue',
		'pt_issuenotebit_user',
		'pt_issuenotebit_petition',
		'pt_issuenotebit_system',
		'pt_issuenotebit_systembit',
		'pt_listprojects',
		'pt_listprojects_link',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'bbcode_video',
		'pt_attachmentbit',
		'showthread_quickreply',
	),
	'notehistory' => array(
		'pt_notehistory',
		'pt_historybit',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'bbcode_video',
	),
	'viewip' => array(
		'pt_viewip'
	),
	'patch' => array(
		'pt_patch',
		'pt_patchbit_file_header',
		'pt_patchbit_chunk_header',
		'pt_patchbit_line_context',
		'pt_patchbit_line_added',
		'pt_patchbit_line_removed',
	),
	'report' => array(
		'reportitem',
		'newpost_usernamecode',
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
if ($_REQUEST['do'] == 'markread')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid'   => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'ajax'        => TYPE_BOOL,
	));

	$project = verify_project($vbulletin->GPC['projectid']);
	if ($vbulletin->GPC['issuetypeid'])
	{
		verify_issuetypeid($vbulletin->GPC['issuetypeid'], $project['projectid']);

		mark_project_read($project['projectid'], $vbulletin->GPC['issuetypeid'], TIMENOW);

		$issuetypes = array($vbulletin->GPC['issuetypeid']);
	}
	else
	{
		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

		$issuetypes = array();

		foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
		{
			if ($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
			{
				mark_project_read($project['projectid'], $issuetypeid, TIMENOW);
				$issuetypes[] = $issuetypeid;
			}
		}
	}

	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('readmarker');

		$xml->add_group('project', array('projectid' => $project['projectid']));
		foreach ($issuetypes AS $issuetypeid)
		{
			$xml->add_tag('issuetype', $issuetypeid);
		}
		$xml->close_group();

		$xml->close_group();
		$xml->print_xml();
	}
	else
	{
		$vbulletin->url = 'project.php?' . $vbulletin->session->vars['sessionurl'] . 'projectid=' . $project['projectid'];
		eval(print_standard_redirect('project_markread'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'notehistory')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuenoteid' => TYPE_UINT
	));

	$issuenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote
		WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
	");

	$issue = verify_issue($issuenote['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!can_edit_issue_note($issue, $issuenote, $issueperms))
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/class_bbcode_pt.php');
	$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

	require_once(DIR . '/includes/functions_pt_notehistory.php');

	$edit_history = '';
	$previous_edits =& fetch_note_history($issuenote['issuenoteid']);
	while ($history = $db->fetch_array($previous_edits))
	{
		$edit_history .= build_history_bit($history, $bbcode);
	}

	if ($edit_history === '')
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue_note'], $vbulletin->options['contactuslink']));
	}

	$current_message = $bbcode->parse($issuenote['pagetext'], 'pt');

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" => $issue['title'],
		'' => $vbphrase['edit_history']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_history_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_notehistory');
		$templater->register_page_templates();
		$templater->register('current_message', $current_message);
		$templater->register('edit_history', $edit_history);
		$templater->register('navbar', $navbar);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'viewip')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuenoteid' => TYPE_UINT
	));

	$issuenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote
		WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
	");

	$issue = verify_issue($issuenote['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']))
	{
		print_no_permission();
	}

	$ipaddress = ($issuenote['ipaddress'] ? htmlspecialchars_uni(long2ip($issuenote['ipaddress'])) : '');
	if ($ipaddress === '')
	{
		exec_header_redirect("project.php?issueid=$issue[issueid]");
	}

	$hostname = htmlspecialchars_uni(gethostbyaddr($ipaddress));

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" => $issue['title'],
		'' => $vbphrase['ip_address']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_viewip_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_viewip');
		$templater->register_page_templates();
		$templater->register('hostname', $hostname);
		$templater->register('ipaddress', $ipaddress);
		$templater->register('navbar', $navbar);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'patch')
{
	require_once(DIR . '/includes/functions_pt_patch.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'attachmentid' => TYPE_UINT
	));

	$attachment = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issueattach
		WHERE attachmentid = " . $vbulletin->GPC['attachmentid']
	);

	$issue = verify_issue($attachment['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachview']))
	{
		print_no_permission();
	}

	if (!$attachment['ispatchfile'])
	{
		exec_header_redirect("projectattachment.php?attachmentid=$attachment[attachmentid]");
		exit;
	}

	if ($vbulletin->options['pt_attachfile'])
	{
		require_once(DIR . '/includes/functions_file.php');
		$attachpath = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], false, $vbulletin->options['pt_attachpath']);
		$attachment['filedata'] = file_get_contents($attachpath);
	}

	$patch_parser = new vB_PatchParser();
	if (!$patch_parser->parse($attachment['filedata']))
	{
		// parsing failed for some reason, just download the attachment
		exec_header_redirect("projectattachment.php?attachmentid=$attachment[attachmentid]");
		exit;
	}

	$patchbits = build_colored_patch($patch_parser);

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" => $issue['title'],
		'' => $vbphrase['view_patch']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_patch_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_patch');
		$templater->register_page_templates();
		$templater->register('attachment', $attachment);
		$templater->register('navbar', $navbar);
		$templater->register('patchbits', $patchbits);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'vote')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'vote' => TYPE_NOCLEAN
	));

	// allow support for "vote=positive" and "vote[positive]"
	if (is_array($vbulletin->GPC['vote']))
	{
		reset($vbulletin->GPC['vote']);
		$vbulletin->GPC['vote'] = key($vbulletin->GPC['vote']);
	}
	$vbulletin->GPC['vote'] = htmlspecialchars_uni(strval($vbulletin->GPC['vote']));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canvote']) OR $issue['state'] == 'closed')
	{
		print_no_permission();
	}

	// issue starters can't vote on the issue (unless the option allows them to)
	if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid'] == $issue['submituserid'] AND !$vbulletin->options['pt_allowstartervote'])
	{
		print_no_permission();
	}

	if (!$vbulletin->GPC['vote'])
	{
		standard_error(fetch_error('pt_need_vote'));
	}

	$votedata =& datamanager_init('Pt_IssueVote', $vbulletin, ERRTYPE_STANDARD);
	$votedata->set('issueid', $issue['issueid']);
	$votedata->set('vote', $vbulletin->GPC['vote']);
	if ($vbulletin->userinfo['userid'])
	{
		$votedata->set('userid', $vbulletin->userinfo['userid']);
	}
	else
	{
		$votedata->set('ipaddress', ip2long(IPADDRESS));
	}

	($hook = vBulletinHook::fetch_hook('project_vote')) ? eval($hook) : false;

	$votedata->save();

	$vbulletin->url = 'project.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
	eval(print_standard_redirect('pt_vote_cast'));
}

// #######################################################################
if ($_REQUEST['do'] == 'gotonote')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuenoteid' => TYPE_UINT,
		'issueid' => TYPE_UINT,
		'goto' => TYPE_STR
	));

	$issuenote = false;

	if ($vbulletin->GPC['issueid'] AND $vbulletin->GPC['goto'] == 'firstnew')
	{
		$issue = verify_issue($vbulletin->GPC['issueid']);
		$project = verify_project($issue['projectid']);

		$private_text = '';
		$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
		$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);

		$issuenote = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			WHERE issuenote.issueid = " . $vbulletin->GPC['issueid'] . "
				AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
				AND issuenote.type IN ('user', 'petition')
				AND issuenote.dateline > $issue[lastread]
			ORDER BY issuenote.dateline ASC
			LIMIT 1
		");
		if (!$issuenote)
		{
			$vbulletin->GPC['issuenoteid'] = $issue['lastnoteid'];
		}
	}

	if (!$issuenote)
	{
		$issuenote = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
		");
	}

	$issue = verify_issue($issuenote['issueid']);
	$project = verify_project($issue['projectid']);

	if ($issue['firstnoteid'] == $issuenote['issuenoteid'])
	{
		exec_header_redirect('project.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]");
		exit;
	}

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);

	// determine which note types the browsing user can see
	$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);

	if ($issuenote['type'] == 'system')
	{
		$type_filter = '';
		$filter_url = '&filter=all';
	}
	else
	{
		$type_filter = "AND issuenote.type IN ('user', 'petition')";
		$filter_url = '';
	}

	// notes
	$notesbefore = $db->query_first("
		SELECT COUNT(*) AS notesbefore
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.issuenoteid <> $issue[firstnoteid]
			AND issuenote.dateline < $issuenote[dateline]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			$type_filter
	");

	$pagenum = ($vbulletin->options['pt_notesperpage'] ? ceil(($notesbefore['notesbefore'] + 1) / $vbulletin->options['pt_notesperpage']) : 1);

	if ($pagenum > 1)
	{
		$page_url = "&page=$pagenum";
	}
	else
	{
		$page_url = '';
	}

	exec_header_redirect('project.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]$filter_url$page_url#note$issuenote[issuenoteid]");
}

// #######################################################################
if ($_REQUEST['do'] == 'lastnote')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	// determine which note types the browsing user can see
	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);

	$issuenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenote.issueid = $issue[issueid]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			AND issuenote.type IN ('user', 'petition')
		ORDER BY dateline DESC
		LIMIT 1
	");
	if (!$issuenote)
	{
		exec_header_redirect('project.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]");
		exit;
	}

	// notes
	$notesbefore = $db->query_first("
		SELECT COUNT(*) AS notesbefore
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.dateline <= $issuenote[dateline]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			AND issuenote.type IN ('user', 'petition')
		ORDER BY dateline DESC
		LIMIT 1
	");

	$pagenum = ceil(($notesbefore['notesbefore'] + 1) / $vbulletin->options['pt_notesperpage']);
	if ($pagenum > 1)
	{
		$page_url = "&page=$pagenum";
	}
	else
	{
		$page_url = '';
	}

	exec_header_redirect('project.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]$filter_url$page_url#note$issuenote[issuenoteid]");
}

// #######################################################################
if ($_REQUEST['do'] == 'issue')
{
	require_once(DIR . '/includes/class_bbcode_pt.php');
	require_once(DIR . '/includes/class_pt_issuenote.php');

	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'filter' => TYPE_NOHTML,
		'pagenumber' => TYPE_UINT
	));

	$issue = verify_issue($vbulletin->GPC['issueid'], true, array('avatar', 'vote', 'milestone'));
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	($hook = vBulletinHook::fetch_hook('project_issue_start')) ? eval($hook) : false;

	$show['issue_closed'] = ($issue['state'] == 'closed');
	$show['reply_issue'] = $posting_perms['can_reply'];
	$show['quick_reply'] = ($vbulletin->userinfo['userid'] AND $posting_perms['can_reply']);
	$show['lightbox'] = ($vbulletin->options['lightboxenabled'] AND $vbulletin->options['usepopups']);

	if (!$vbulletin->pt_issuestatus["$issue[issuestatusid]"]['canpetitionfrom'])
	{
		$show['status_petition'] = false;
	}
	else
	{
		$show['status_petition'] = ($show['quick_reply'] AND ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpetition']));
	}

	$show['attachments'] = ($vbulletin->userinfo['userid'] AND ($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachview']));
	$show['attachment_upload'] = ($show['attachments'] AND ($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattach']) AND !is_issue_closed($issue, $issueperms));
	$show['edit_issue'] = $posting_perms['issue_edit'];

	if ($issue['state'] == 'closed')
	{
		// if the issue is closed, no one can vote at all
		$show['vote_option'] = false;
	}
	else if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid'] == $issue['submituserid'] AND !$vbulletin->options['pt_allowstartervote'])
	{
		// issue starters can't vote
		$show['vote_option'] = false;
	}
	else
	{
		$show['vote_option'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canvote']);
	}

	$show['private_edit'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancreateprivate']); // for quick reply
	$show['status_edit'] = $posting_perms['status_edit'];
	$show['milestone'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'] AND $project['milestonecount']);
	$show['milestone_edit'] = ($show['milestone'] AND $posting_perms['milestone_edit']);
	$show['tags_edit'] = $posting_perms['tags_edit'];
	$show['assign_dropdown'] = $posting_perms['assign_dropdown'];

	$show['move_issue'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmoveissue']);
	$show['edit_issue_private'] = ($posting_perms['issue_edit'] AND $posting_perms['private_edit']);
	$show['newflag'] = ($issue['newflag'] ? TRUE : FALSE);

	// get voting phrases
	$vbphrase['vote_question_issuetype'] = $vbphrase["vote_question_$issue[issuetypeid]"];
	$vbphrase['vote_count_positive_issuetype'] = $vbphrase["vote_count_positive_$issue[issuetypeid]"];
	$vbphrase['vote_count_negative_issuetype'] = $vbphrase["vote_count_negative_$issue[issuetypeid]"];
	$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_$issue[issuetypeid]"];
	$vbphrase['addressed_version_issuetype'] = $vbphrase["addressed_version_$issue[issuetypeid]"];

	if (!$vbulletin->options['pt_notesperpage'])
	{
		$vbulletin->options['pt_notesperpage'] = 999999;
	}

	// tags
	$tags = array();
	$tag_data = $db->query_read("
		SELECT tag.tagtext
		FROM " . TABLE_PREFIX . "pt_issuetag AS issuetag
		INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (issuetag.tagid = tag.tagid)
		WHERE issuetag.issueid = $issue[issueid]
		ORDER BY tag.tagtext
	");
	while ($tag = $db->fetch_array($tag_data))
	{
		$tags[] = $tag['tagtext'];
	}
	$tags = implode(', ', $tags);

	// assignments
	$assignments = array();
	$assignment_data = $db->query_read("
		SELECT user.userid, user.username, user.usergroupid, user.membergroupids, user.displaygroupid
		FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
		WHERE issueassign.issueid = $issue[issueid]
		ORDER BY user.username
	");
	while ($assignment = $db->fetch_array($assignment_data))
	{
		$assignments[] = "$assignment[username]";
	}
	$assignments = implode(', ', $assignments);

	// determine which note types the browsing user can see
	$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
	$can_see_deleted = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']);

	// find total results for each type
	$notetype_counts = array(
		'user' => 0,
		'petition' => 0,
		'system' => 0
	);

	$hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('project_issue_typecount_query')) ? eval($hook) : false;

	$notetype_counts_query = $db->query_read("
		SELECT issuenote.type, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		$hook_query_joins
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.issuenoteid <> $issue[firstnoteid]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			$hook_query_where
		GROUP BY issuenote.type
	");
	while ($notetype_count = $db->fetch_array($notetype_counts_query))
	{
		$notetype_counts["$notetype_count[type]"] = intval($notetype_count['total']);
	}

	// sanitize type filter
	switch ($vbulletin->GPC['filter'])
	{
		case 'petitions':
		case 'changes':
		case 'all':
		case 'comments':
			break;
		default:
			// we haven't specified a valid filter, so let's pick a default that has something if possible
			if ($notetype_counts['user'] OR $notetype_counts['petition'])
			{
				// have replies
				$vbulletin->GPC['filter'] = 'comments';
			}
			else if ($notetype_counts['system'])
			{
				// changes only
				$vbulletin->GPC['filter'] = 'changes';
			}
			else
			{
				// nothing, just show comments
				$vbulletin->GPC['filter'] = 'comments';
			}
	}

	// setup filtering
	switch ($vbulletin->GPC['filter'])
	{
		case 'petitions':
			$type_filter = "AND issuenote.type = 'petition'";
			$note_count = $notetype_counts['petition'];
			break;

		case 'changes':
			$type_filter = "AND issuenote.type = 'system'";
			$note_count = $notetype_counts['system'];
			break;

		case 'all':
			$type_filter = '';
			$note_count = array_sum($notetype_counts);
			break;

		case 'comments':
		default:
			$type_filter = "AND issuenote.type IN ('user', 'petition')";
			$note_count = $notetype_counts['user'] + $notetype_counts['petition'];
			$vbulletin->GPC['filter'] = 'comments';
	}

	$selected_filter = array(
		'comments'  => ($vbulletin->GPC['filter'] == 'comments'  ? ' selected="selected"' : ''),
		'petitions' => ($vbulletin->GPC['filter'] == 'petitions' ? ' selected="selected"' : ''),
		'changes'   => ($vbulletin->GPC['filter'] == 'changes'   ? ' selected="selected"' : ''),
		'all'       => ($vbulletin->GPC['filter'] == 'all'       ? ' selected="selected"' : ''),
	);

	$display_type_counts = array(
		'comments' => vb_number_format($notetype_counts['user'] + $notetype_counts['petition']),
		'petitions' => vb_number_format($notetype_counts['petition']),
		'changes' => vb_number_format($notetype_counts['system']),
	);

	// prepare counts to be viewable
	foreach ($notetype_counts AS $notetype => $count)
	{
		$notetype_counts["$notetype"] = vb_number_format($count);
	}

	// pagination
	if (!$vbulletin->GPC['pagenumber'])
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}
	$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['pt_notesperpage'];

	if ($start > $note_count)
	{
		$vbulletin->GPC['pagenumber'] = ceil($note_count / $vbulletin->options['pt_notesperpage']);
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['pt_notesperpage'];
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('project_issue_note_query')) ? eval($hook) : false;

	// notes
	$notes = $db->query_read("
		SELECT issuenote.*, issuenote.username AS noteusername, issuenote.ipaddress AS noteipaddress,
			" . ($vbulletin->options['avatarenabled'] ? 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,' : '') . "
			user.*, userfield.*, usertextfield.*,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, user.infractiongroupid,
			issuepetition.petitionstatusid, issuepetition.resolution AS petitionresolution
			" . ($can_see_deleted ? ", issuedeletionlog.reason AS deletionreason" : '') . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "pt_issuepetition AS issuepetition ON (issuepetition.issuenoteid = issuenote.issuenoteid)
		" . ($can_see_deleted ? "LEFT JOIN " . TABLE_PREFIX . "pt_issuedeletionlog AS issuedeletionlog ON (issuedeletionlog.primaryid = issuenote.issuenoteid AND issuedeletionlog.type = 'issuenote')" : '') . "
		" . ($vbulletin->options['avatarenabled'] ? "
			LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid)
			LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : '') . "
		$hook_query_joins
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.issuenoteid <> $issue[firstnoteid]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			$type_filter
			$hook_query_where
		ORDER BY issuenote.dateline
		LIMIT $start, " . $vbulletin->options['pt_notesperpage'] . "
	");

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$vbulletin->options['pt_notesperpage'],
		$note_count,
		'project.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" .
			($vbulletin->GPC['filter'] != 'comments' ? '&amp;filter=' . $vbulletin->GPC['filter'] : ''),
		''
	);

	$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

	$factory = new vB_Pt_IssueNoteFactory();
	$factory->registry =& $vbulletin;
	$factory->bbcode =& $bbcode;
	$factory->issue =& $issue;
	$factory->project =& $project;
	$factory->browsing_perms = $issueperms;

	$notebits = '';
	$displayed_dateline = 0;

	while ($note = $db->fetch_array($notes))
	{
		$displayed_dateline = max($displayed_dateline, $note['dateline']);
		$note_handler =& $factory->create($note);
		$notebits .= $note_handler->construct();
	}

	// prepare the original issue like a note since it has note text
	$displayed_dateline = max($displayed_dateline, $issue['dateline']);
	$note_handler =& $factory->create($issue);
	$note_handler->construct();
	$issue = $note_handler->note;

	if ($show['status_petition'] OR $show['status_edit'])
	{
		// issue status for petition
		$petition_options = build_issuestatus_select($vbulletin->pt_issuetype["$issue[issuetypeid]"]['statuses'], 0, array($issue['issuestatusid']));
	}

	if ($show['attachments'])
	{
		// attachments
		$attachments = $db->query_read("
			SELECT issueattach.attachmentid, issueattach.userid, issueattach.filename, issueattach.extension,
				issueattach.dateline, issueattach.visible, issueattach.status, issueattach.filesize,
				issueattach.thumbnail_filesize, issueattach.thumbnail_dateline, issueattach.ispatchfile,
				user.username
			FROM " . TABLE_PREFIX . "pt_issueattach AS issueattach
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (issueattach.userid = user.userid)
			WHERE issueattach.issueid = $issue[issueid]
				AND visible = 1
			ORDER BY dateline
		");
		$attachmentbits = '';
		while ($attachment = $db->fetch_array($attachments))
		{
			$show['attachment_obsolete'] = ($attachment['status'] == 'obsolete');
			$show['manage_attach_link'] = (($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachedit'])
				AND (($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattacheditothers']) OR $vbulletin->userinfo['userid'] == $attachment['userid']));

			if ($attachment['ispatchfile'])
			{
				$attachment['link'] = 'project.php?' . $vbulletin->session->vars['sessionurl'] . "do=patch&amp;attachmentid=$attachment[attachmentid]";
			}
			else
			{
				$attachment['link'] = 'projectattachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]";
			}
			$attachment['attachtime'] = vbdate($vbulletin->options['timeformat'], $attachment['dateline']);
			$attachment['attachdate'] = vbdate($vbulletin->options['dateformat'], $attachment['dateline'], true);

			($hook = vBulletinHook::fetch_hook('project_issue_attachmentbit')) ? eval($hook) : false;

			$templater = vB_Template::create('pt_attachmentbit');
				$templater->register('attachment', $attachment);
				$templater->register('contenttypeid', $issue_contenttypeid);
			$attachmentbits .= $templater->render();
		}
	}

	// mark this issue as read
	if ($displayed_dateline AND $displayed_dateline >= $issue['lastread'])
	{
		mark_issue_read($issue, $displayed_dateline);
	}

	// quick reply
	if ($show['quick_reply'])
	{
		require_once(DIR . '/includes/functions_editor.php');
		$editorid = construct_edit_toolbar(
			'',
			false,
			'pt',
			$vbulletin->options['pt_allowsmilies'],
			true,
			false,
			'qr'
		);
	}

	// Project jump
	if ($vbulletin->options['pt_listprojects_activate'] AND $vbulletin->options['pt_listprojects_locations'] & 4)
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
				$templater->register('projectlist', $projectlist);
			$ptdropdown .= $templater->render();
		}

		if ($ptdropdown)
		{
			// Define particular conditions for spaces
			$navpopup = array();
			$navpopup['css'] = '';

			if (empty($pagenav))
			{
				if ($vbulletin->options['pt_listprojects_position_issue'] == 0)
				{
					$navpopup['css'] = 'margin38';
				}
				else if ($vbulletin->options['pt_listprojects_position_issue'] == 1)
				{
					$navpopup['css'] = 'margin33';
				}
			}
			else
			{
				if ($vbulletin->options['pt_listprojects_position_issue'] == 0)
				{
					$navpopup['css'] = 'margin15 marginbottomadd5';
				}
				else if ($vbulletin->options['pt_listprojects_position_issue'] == 1)
				{
					$navpopup['css'] = 'marginbottomadd5';
				}
			}

			if ($vbulletin->options['pt_listprojects_position_issue'] == 2)
			{
				$navpopup['css'] = 'marginmore10 marginbottomadd5';
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
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean'],
		"project.php?" . $vbulletin->session->vars['sessionurl'] . "do=issuelist&amp;projectid=$project[projectid]&amp;issuetypeid=$issue[issuetypeid]" => $vbphrase["issuetype_$issue[issuetypeid]_singular"],
		'' => $issue['title']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_issue_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_issue');
		$templater->register_page_templates();
		$templater->register('assignments', $assignments);
		$templater->register('attachmentbits', $attachmentbits);
		$templater->register('display_type_counts', $display_type_counts);
		$templater->register('editorid', $editorid);
		$templater->register('issue', $issue);
		$templater->register('messagearea', $messagearea);
		$templater->register('navbar', $navbar);
		$templater->register('notebits', $notebits);
		$templater->register('pagenav', $pagenav);
		$templater->register('petition_options', $petition_options);
		$templater->register('posting_perms', $posting_perms);
		$templater->register('project', $project);
		$templater->register('pt_ptlist', $pt_ptlist);
		$templater->register('selected_filter', $selected_filter);
		$templater->register('tags', $tags);
		$templater->register('vBeditTemplate', $vBeditTemplate);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

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

// #######################################################################
if ($_REQUEST['do'] == 'timeline')
{
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
			'project.php?' . $vbulletin->session->vars['sessionurl'] . "do=timeline" .
				($vbulletin->GPC['projectid'] ? '&amp;projectid=' . $vbulletin->GPC['projectid'] : '') .
				($vbulletin->GPC['startdate'] ? '&amp;startdate=' . $vbulletin->GPC['startdate'] : '') .
				($vbulletin->GPC['enddate'] ? '&amp;enddate=' . $vbulletin->GPC['enddate'] : ''),
			''
		);
	}
	else
	{
		$pagenav = '';
	}

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
		$navbits["project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]"] = $project['title_clean'];
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
}

// #######################################################################
if ($_REQUEST['do'] == 'project')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT
	));

	$project = verify_project($vbulletin->GPC['projectid']);
	$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

	$perms_query = build_issue_permissions_query($vbulletin->userinfo);
	if (empty($perms_query["$project[projectid]"]))
	{
		print_no_permission();
	}

	$project['description'] = nl2br($project['description']);

	($hook = vBulletinHook::fetch_hook('project_project_start')) ? eval($hook) : false;

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

		$activity_results = fetch_activity_list(
			'(' . $perms_query["$project[projectid]"] . ') AND (' . $note_perms["$project[projectid]"] . ')',
			$vbulletin->options['pt_project_timelineentries'], 0, false
		);
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
	build_project_private_lastpost_sql_project($vbulletin->userinfo, $project['projectid'],
		$private_lastpost_join, $private_lastpost_fields
	);

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

	$type_counts = '';
	$post_issue_options = '';
	foreach ($project_types AS $type)
	{
		if (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) AND ($projectperms["$type[issuetypeid]"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
		{
			$postable_types[] = $type['issuetypeid'];
			$typename = $vbphrase["issuetype_$type[issuetypeid]_singular"];
			$templater = vB_Template::create('pt_postmenubit');
				$templater->register('project', $project);
				$templater->register('type', $type);
				$templater->register('typename', $typename);
				$templater->register('contenttypeid', $issue_contenttypeid);
			$post_issue_options .= $templater->render();
		}

		if (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			if ($type['lastactivity'] > $project['lastactivity'])
			{
				$project['lastactivity'] = $type['lastactivity'];
				$show['private_lastactivity'] = (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']) ? false : true);
			}

			$typename = $vbphrase["issuetype_$type[issuetypeid]_plural"];
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

			$templater = vB_Template::create('pt_project_typecountbit');
				$templater->register('project', $project);
				$templater->register('type', $type);
				$templater->register('typename', $typename);
				$templater->register('contenttypeid', $issue_contenttypeid);
			$type_counts .= $templater->render();
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
	$status_options = fetch_issue_status_search_select($projectperms);

	// report list
	$reportbits = prepare_subscribed_reports();

	// Project jump
	if ($vbulletin->options['pt_listprojects_activate'] AND $vbulletin->options['pt_listprojects_locations'] & 1)
	{
		$ptdropdown = '';

		// Create the list
		foreach ($vbulletin->pt_projects AS $projectlist)
		{
			if (!isset($perms_query["$projectlist[projectid]"]) OR $projectlist['displayorder'] == 0)
			{
				continue;
			}

			$templater = vB_Template::create('pt_listprojects_link');
				$templater->register('projectlist', $projectlist);
			$ptdropdown .= $templater->render();
		}

		// Do some things if the list is done and filled
		if ($ptdropdown)
		{
			$navpopup = array();
			$navpopup['css'] = '';

			// Timeline isn't empty - some particular conditions for spaces
			if (!empty($timeline))
			{
				if ($vbulletin->options['pt_listprojects_position_projects'] == 2)
				{
					$navpopup['css'] = 'margin10 marginright marginbottom5';
				}
			}
			else
			{
				$navpopup['css'] = 'margin10 marginright';
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
	$navbits = construct_navbits(array('project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'], '' => $project['title_clean']));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_project_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_project');
		$templater->register_page_templates();
		$templater->register('assignable_users', $assignable_users);
		$templater->register('issuebits', $issuebits);
		$templater->register('navbar', $navbar);
		$templater->register('petitionbits', $petitionbits);
		$templater->register('post_issue_options', $post_issue_options);
		$templater->register('post_new_issue_text', $post_new_issue_text);
		$templater->register('project', $project);
		$templater->register('pt_ptlist', $pt_ptlist);
		$templater->register('reportbits', $reportbits);
		$templater->register('status_options', $status_options);
		$templater->register('timeline', $timeline);
		$templater->register('type_counts', $type_counts);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'overview')
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
		$activity_results = fetch_activity_list(
			'(' . implode(' OR ', $perms_query) . ') AND (' . implode(' OR ', $note_perms) . ')',
			$vbulletin->options['pt_overview_timelineentries'], 0, false
		);
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

	build_project_private_lastpost_sql_all($vbulletin->userinfo,
		$private_lastpost_join, $private_lastpost_fields
	);

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
	$projectbits = '';
	foreach ($vbulletin->pt_projects AS $project)
	{
		if (!isset($perms_query["$project[projectid]"]) OR !is_array($project_types["$project[projectid]"]) OR $project['displayorder'] == 0)
		{
			continue;
		}

		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
		$project['lastpost'] = 0;
		$show['private_lastpost'] = false;
		$project['newflag'] = false;

		$type_counts = '';
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

			$typename = $vbphrase["issuetype_$type[issuetypeid]_plural"];
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

			$templater = vB_Template::create('pt_projectbit_typecount');
				$templater->register('project', $project);
				$templater->register('type', $type);
				$templater->register('typename', $typename);
			$type_counts .= $templater->render();
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

	// report list
	$reportbits = prepare_subscribed_reports();

	$markread_script = vB_Template::create('pt_markread_script')->render();

	// navbar and output
	$navbits = construct_navbits(array('' => $vbphrase['projects']));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('project_overview_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_overview');
		$templater->register_page_templates();
		$templater->register('markread_script', $markread_script);
		$templater->register('navbar', $navbar);
		$templater->register('projectbits', $projectbits);
		$templater->register('reportbits', $reportbits);
		$templater->register('timeline', $timeline);
		$templater->register('contenttypeid', $issue_contenttypeid);
	print_output($templater->render());
}

// ############################### start report ###############################
if ($_REQUEST['do'] == 'report' OR $_POST['do'] == 'sendemail')
{
	require_once(DIR . '/includes/class_reportitem_pt.php');

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'issuenoteid' => TYPE_UINT
	));

	$issuenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote
		WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
	");

	$issue = verify_issue($issuenote['issueid']);
	$project = verify_project($issue['projectid']);
	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);

	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

	if (!$reportthread AND !$reportemail)
	{
		eval(standard_error(fetch_error('emaildisabled')));
	}

	$userinfo = fetch_userinfo($issuenote['userid']);

	$reportobj = new vB_ReportItem_Pt_IssueNote($vbulletin);
	$reportobj->set_extrainfo('user', $userinfo);
	$reportobj->set_extrainfo('issue', $issue);
	$reportobj->set_extrainfo('issue_note', $issuenote);
	$reportobj->set_extrainfo('project', $project);

	$perform_floodcheck = $reportobj->need_floodcheck();

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_precommit();
	}

	if (!$issuenote OR ($issuenote['type'] != 'user' AND $issuenote['type'] != 'petition'))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink'])));
	}

	if (!verify_issue_note_perms($issue, $issuenote, $vbulletin->userinfo))
	{
			eval(standard_error(fetch_error('invalidid', $vbphrase['issue_note'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('project_report_start')) ? eval($hook) : false;

	if ($_REQUEST['do'] == 'report')
	{
		$navbits = array(
			'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
			"project.php?" . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]" => $project['title_clean'],
			'project.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" => $issue['title'],
			'' => $vbphrase['report_issue_note']
		);
		$navbits = construct_navbits($navbits);

		$usernamecode = vB_Template::create('newpost_usernamecode')->render();

		$navbar = render_navbar_template($navbits);
		$url =& $vbulletin->url;

		($hook = vBulletinHook::fetch_hook('project_report_form_start')) ? eval($hook) : false;

		$forminfo = $reportobj->set_forminfo($issuenote);
		$templater = vB_Template::create('reportitem');
			$templater->register_page_templates();
			$templater->register('forminfo', $forminfo);
			$templater->register('navbar', $navbar);
			$templater->register('url', $url);
			$templater->register('usernamecode', $usernamecode);
			$templater->register('contenttypeid', $issue_contenttypeid);
		print_output($templater->render());
	}

	if ($_POST['do'] == 'sendemail')
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'reason' => TYPE_STR,
		));

		if ($vbulletin->GPC['reason'] == '')
		{
			eval(standard_error(fetch_error('noreason')));
		}

		if ($perform_floodcheck)
		{
			$reportobj->perform_floodcheck_commit();
		}

		$reportobj->do_report($vbulletin->GPC['reason'], $issuenote);

		$url =& $vbulletin->url;
		eval(print_standard_redirect('redirect_reportthanks'));
	}

}
?>
