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
define('THIS_SCRIPT', 'issue');
define('FRIENDLY_URL_LINK', 'issue');
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
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'none' => array(
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

/*if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['issueid']))
	{
		$_REQUEST['do'] = 'issue';
		$actiontemplates['none'] =& $actiontemplates['issue'];
	}
}*/

if (empty($_REQUEST['do']))
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

($hook = vBulletinHook::fetch_hook('issue_start')) ? eval($hook) : false;

require_once(DIR . '/includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();
$issue_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Issue');
$project_contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_Project');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
if ($_REQUEST['do'] == 'notehistory')
{
	$vbulletin->input->clean_gpc('r', 'issuenoteid', TYPE_UINT);

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
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
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
	$vbulletin->input->clean_gpc('r', 'issuenoteid', TYPE_UINT);

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
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
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

	$vbulletin->input->clean_gpc('r', 'attachmentid', TYPE_UINT);

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
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
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

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
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
		exec_header_redirect('issue.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]");
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

	exec_header_redirect('issue.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]$filter_url$page_url#note$issuenote[issuenoteid]");
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
		exec_header_redirect('issue.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]");
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

	exec_header_redirect('issue.php?' . $vbulletin->session->vars['sessionurl_js'] . "issueid=$issue[issueid]$filter_url$page_url#note$issuenote[issuenoteid]");
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

$userid = $vbulletin->userinfo['userid'];
require_once(DIR . '/includes/class_bbcode_pt.php');
require_once(DIR . '/includes/class_pt_issuenote.php');

$vbulletin->input->clean_array_gpc('r', array(
	'issueid' => TYPE_UINT,
	'filter' => TYPE_NOHTML,
	'pagenumber' => TYPE_UINT
));

$issue = verify_issue($vbulletin->GPC['issueid'], true, array('avatar', 'vote', 'milestone'));
$project = verify_project($issue['projectid']);

verify_seo_url('issue', $issue);

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
$show['assigntoself'] = (($show['assign_dropdown'] AND isset($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"]["$userid"])) ? TRUE : FALSE);
	
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

	if ($assignment['userid'] == $vbulletin->userinfo['userid'])
	{
		$show['assigntoself'] = FALSE;
	}
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
	'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]" .
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
		$show['manage_attach_link'] = (($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachedit']) AND (($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattacheditothers']) OR $vbulletin->userinfo['userid'] == $attachment['userid']));

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
	fetch_seo_url('project', $project) => $project['title_clean'],
	fetch_seo_url('issuelist', $project) . "&amp;issuetypeid=$issue[issuetypeid]" => $vbphrase["issuetype_$issue[issuetypeid]_singular"],
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

?>