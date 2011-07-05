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
define('THIS_SCRIPT', 'projectpost');
define('CSRF_PROTECTION', true);
define('PROJECT_SCRIPT', true);

if (in_array($_POST['do'], array('postreply', 'postissue', 'processpetition')))
{
	if (isset($_POST['ajax']))
	{
		define('NOPMPOPUP', 1);
		define('NOSHUTDOWNFUNC', 1);
	}
	if (isset($_POST['fromquickreply']))
	{	// Don't update Who's Online for Quick Replies since it will get stuck on that until the user goes somewhere else
		define('LOCATION_BYPASS', 1);
	}
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('projecttools', 'posting', 'threadmanage');

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
	'forumcache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'pt_navbar_search',
	'pt_headinclude',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'addissue' => array(
		'humanverify',
		'pt_postissue',
		'pt_postissue_short',
		'pt_preview',
		'newpost_usernamecode',
		'optgroup'
	),
	'addreply' => array(
		'humanverify',
		'pt_postreply',
		'pt_postreply_quote',
		'pt_preview',
		'newpost_usernamecode',
		'pt_historybit',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'bbcode_video',
	),
	'export' => array(
		'optgroup',
		'pt_export_content'
	),
	'confirmexport' => array(
		'optgroup',
		'pt_export_content_confirm',
		'pt_export_content_post',
		'pt_export_content_thread'
	),
	'importcontent' => array(
		'optgroup',
		'pt_import_content',
	),
	'importcontent2' => array(
		'optgroup',
		'pt_import_content_confirm',
	),
	'manageattach' => array(
		'pt_manageattach'
	),
	'managesubscriptions' => array(
		'pt_managesubscriptions',
		'pt_subscriptionbit',
		'USERCP_SHELL',
		'usercp_nav_folderbit',
		'pt_usercp_navbit',
		'pt_project_subscriptionbit'
	),
	'managesubscription' => array(
		'pt_managesubscription'
	),
	'manageprojectsubscription' => array(
		'pt_checkbox_option',
		'pt_manageprojectsubscription'
	),
	'moveissue' => array(
		'optgroup',
		'pt_move_issue'
	),
	'moveissue2' => array(
		'optgroup',
		'pt_move_issue_confirm'
	),
);

$actiontemplates['postreply'] = $actiontemplates['editreply'] = $actiontemplates['addreply'];
$actiontemplates['postissue'] = $actiontemplates['editissue'] = $actiontemplates['addissue'];

if ($_REQUEST['do'] == 'managesubscriptions')
{
	$phrasegroups[] = 'user';
}

define('GET_EDIT_TEMPLATES', true);


// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/includes/functions_pt_posting.php');
require_once(DIR . '/includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('projectpost_start')) ? eval($hook) : false;

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// #######################################################################
if ($_POST['do'] == 'quickeditissue')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'editorid' => TYPE_NOHTML,
		'issueid' => TYPE_UINT,
	));

	require_once(DIR . '/includes/class_xml.php');
	require_once(DIR . '/includes/functions_editor.php');

	$issueinfo = verify_issue($vbulletin->GPC['issueid']);

	$editorid = construct_edit_toolbar(
		htmlspecialchars_uni($issueinfo['pagetext']),
		false,
		'pt',
		true,
		true,
		false,
		'qe',
		$vbulletin->GPC['editorid'],
		array(), // attachments - handled differently in PT
		'projecttools', // default value
		'vBProjectTools_Issue', // Content type - needed for auto-save
		$issueinfo['issueid'] // ID of the content
	);

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	$xml->add_group('quickedit');
	$xml->add_tag('editor', process_replacement_vars($messagearea), array(
		'reason'       => '',
		'parsetype'    => 'pt',
		'parsesmilies' => true,
		'mode'         => $show['is_wysiwyg_editor'],
		'content'      => 'projecttools'
	));
	$xml->add_tag('ckeconfig', vB_Ckeditor::getInstance($editorid)->getConfig());
	$xml->close_group();

	$xml->print_xml();
}

// #######################################################################
if ($_POST['do'] == 'quickedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'editorid' => TYPE_NOHTML,
		'issuenoteid' => TYPE_UINT,
	));

	$issuenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote
		WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid']
	);
	$vbulletin->GPC['issueid'] = $issuenote['issueid'];

	require_once(DIR . '/includes/class_xml.php');
	require_once(DIR . '/includes/functions_editor.php');

	$issueinfo = verify_issue($vbulletin->GPC['issueid']);

	$editorid = construct_edit_toolbar(
		htmlspecialchars_uni($issuenote['pagetext']),
		false,
		'pt',
		true,
		true,
		false,
		'qe',
		$vbulletin->GPC['editorid'],
		'', // attachments - handled differently in PT
		'content', // default value
		'vBProjectTools_IssueNote', // Content type - needed for auto-save
		$issuenote['issuenoteid'], // ID of the content
		$issueinfo['issueid'] // Parent ID of the content
	);

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	$xml->add_group('quickedit');
	$xml->add_tag('editor', process_replacement_vars($messagearea), array(
		'reason'       => '',
		'parsetype'    => 'pt',
		'parsesmilies' => true,
		'mode'         => $show['is_wysiwyg_editor']
	));
	$xml->add_tag('ckeconfig', vB_Ckeditor::getInstance($editorid)->getConfig());
	$xml->close_group();

	$xml->print_xml();
}

// #######################################################################
if ($_POST['do'] == 'postreply' OR $_REQUEST['do'] == 'addreply' OR $_REQUEST['do'] == 'editreply')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'issuenoteid' => TYPE_UINT,
		'fromquickreply' => TYPE_BOOL,
	));

	if ($vbulletin->GPC['issuenoteid'])
	{
		$issuenote = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid']
		);
		$vbulletin->GPC['issueid'] = $issuenote['issueid'];
	}
	else
	{
		$issuenote = array(
			'issuenoteid' => 0,
			'isfirstnote' => 0
		);
	}

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);
	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (!$issuenote['issuenoteid'])
	{
		if (!$posting_perms['can_reply'])
		{
			print_no_permission();
		}

		// setup default subscribe type for QR
		if ($vbulletin->GPC['fromquickreply'])
		{
			if (!$issue['subscribetype'])
			{
				switch ($vbulletin->userinfo['autosubscribe'])
				{
					case -1: $_POST['subscribetype'] = ''; break;
					case  0: $_POST['subscribetype'] = 'none'; break;
					case  1: $_POST['subscribetype'] = 'instant'; break;
					case  2: $_POST['subscribetype'] = 'daily'; break;
					case  3: $_POST['subscribetype'] = 'weekly'; break;
					default: $_POST['subscribetype'] = ''; break;
				}
			}
			else
			{
				 $_POST['subscribetype'] = $issue['subscribetype'];
			}
		}
	}
	else
	{
		if (!can_edit_issue_note($issue, $issuenote, $issueperms))
		{
			print_no_permission();
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_reply_setup')) ? eval($hook) : false;
}

// #######################################################################
if ($_POST['do'] == 'postreply')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'message'            => TYPE_STR,
		'wysiwyg'            => TYPE_BOOL,
		'quickreply'         => TYPE_BOOL,
		'ajaxqrfailed'       => TYPE_BOOL,
		'private'            => TYPE_BOOL,
		'delete'             => TYPE_STR,
		'undelete'           => TYPE_BOOL,
		'reason'             => TYPE_NOHTML,
		'petitionstatusid'   => TYPE_UINT,
		'changestatusid'     => TYPE_UINT,
		'subscribetype'      => TYPE_NOHTML,
		'preview'            => TYPE_NOHTML,
		'humanverify'        => TYPE_ARRAY,
		'ajax'               => TYPE_BOOL,
		'ajax_lastissuenote' => TYPE_INT,
		'loggedinuser'       => TYPE_INT,
		'specifiedissuenote' => TYPE_BOOL,
		'advanced'			 => TYPE_BOOL
	));

	if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
	{
		// User was logged in when writing post but isn't now. If we got this
		// far, guest posts are allowed, but they didn't enter a username so
		// they'll get an error. Force them to log back in.
		standard_error(fetch_error('session_timed_out_login'), '', false, 'STANDARD_ERROR_LOGIN');
	}

	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/class_wysiwygparser.php');
		$html_parser = new vB_WysiwygHtmlParser($vbulletin);
		$vbulletin->GPC['message'] = $html_parser->parse_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->options['pt_allowhtml']);
	}

	if ($vbulletin->GPC['ajax'])
	{
		// posting via ajax so we need to handle those %u0000 entries
		$vbulletin->GPC['message'] = convert_urlencoded_unicode($vbulletin->GPC['message']);
	}

	if ($vbulletin->GPC['ajax'] AND $vbulletin->GPC['username'])
	{
		if ($vbulletin->GPC['username'])
		{
			$vbulletin->GPC['username'] = convert_urlencoded_unicode($vbulletin->GPC['username']);
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_postreply_start')) ? eval($hook) : false;

	if (!$issuenote['issuenoteid'])
	{
		if ($vbulletin->GPC['petitionstatusid'] AND (!$vbulletin->pt_issuestatus["$issue[issuestatusid]"]['canpetitionfrom'] OR !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpetition'])))
		{
			$vbulletin->GPC['petitionstatusid'] = 0;
		}
	}
	else
	{
		$vbulletin->GPC['petitionstatusid'] = 0;
	}

	// prepare note
	$issuenotedata =& datamanager_init(
		$vbulletin->GPC['petitionstatusid'] ? 'Pt_IssueNote_Petition' : 'Pt_IssueNote_User',
		$vbulletin,
		ERRTYPE_ARRAY,
		'pt_issuenote'
	);

	$issuenotedata->set_info('do_floodcheck', !can_moderate());

	if ($issuenote['issuenoteid'])
	{
		// an edit
		$issuenotedata->set_existing($issuenote);
		$issuenotedata->set_info('reason', $vbulletin->GPC['reason']);

		if ($vbulletin->GPC['delete'])
		{
			if ($issuenote['isfirstnote'])
			{
				print_no_permission();
			}

			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenote']) OR ($issuenote['userid'] != $vbulletin->userinfo['userid'] AND !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenoteothers']))
			)
			{
				print_no_permission();
			}

			$issuenotedata->delete($vbulletin->GPC['delete'] == 'hard');

			$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
			eval(print_standard_redirect('pt_issuenote_deleted'));
		}
		else if ($vbulletin->GPC['undelete'])
		{
			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenote']) OR !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']))
			{
				print_no_permission();
			}

			$issuenotedata->undelete();

			$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "do=gotonote&amp;issuenoteid=$issuenote[issuenoteid]";
			eval(print_standard_redirect('pt_issuenote_undeleted'));
		}

		if (!$issuenote['isfirstnote'] AND !$vbulletin->GPC['advanced'])
		{
			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditprivate']))
			{
				// can't set an existing issue to private, so it stays this way
			}
			else if ($vbulletin->GPC['private'])
			{
				// make it private
				$issuenotedata->set('visible', 'private');
			}
			else
			{
				// make it visible if it was private, else leave it as is
				$issuenotedata->set('visible', $issuenote['visible'] == 'private' ? 'visible': $issuenote['visible']);
			}
		}
	}
	else
	{
		// an insert
		$issuenotedata->set('userid', $vbulletin->userinfo['userid']);
		$issuenotedata->set('username', $vbulletin->userinfo['username']);
		if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancreateprivate']))
		{
			// new issue -- can't make private
			$issuenotedata->set('visible', 'visible');
		}
		else
		{
			$issuenotedata->set('visible', $vbulletin->GPC['private'] ? 'private' : 'visible');
		}
		$issuenotedata->set('issueid', $issue['issueid']);
	}

	$issuenotedata->set_info('parseurl', $vbulletin->options['pt_allowbbcode']);
	$issuenotedata->setr('pagetext', $vbulletin->GPC['message']);

	if ($vbulletin->GPC['petitionstatusid'] AND !$issuenote['issuenoteid'])
	{
		$issuenotedata->set_info('petitionstatusid', $vbulletin->GPC['petitionstatusid']);
	}

	if (fetch_require_pt_hvcheck('post'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
			$issuenotedata->error($verify->fetch_error());
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_postreply_save')) ? eval($hook) : false;

	$issuenotedata->pre_save();

	if ($issuenotedata->errors)
	{
		// ### POST HAS ERRORS ###
		if ($vbulletin->GPC['ajax'])
		{
			require_once(DIR . '/includes/class_xml.php');
			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_group('errors');
			foreach ($issuenotedata->errors AS $error)
			{
				$xml->add_tag('error', $error);
			}
			$xml->close_group();
			$xml->print_xml(true);
		}
		else
		{
			$preview = prepare_pt_note_preview($vbulletin->GPC, $issuenotedata, $issuenote, $issue);
			construct_checkboxes($vbulletin->GPC);
			define('IS_PREVIEW', true);
			$_REQUEST['do'] = 'editreply';
			$vbulletin->GPC['message'] = htmlspecialchars_uni($vbulletin->GPC['message']);
		}

		($hook = vBulletinHook::fetch_hook('projectpost_postreply_preview')) ? eval($hook) : false;
	}
	else if ($vbulletin->GPC['preview'])
	{
		// Nothing actually - need to be developed
		$preview = prepare_pt_note_preview($vbulletin->GPC, $issuenotedata, $issuenote, $issue);
		define('IS_PREVIEW', true);
		$_REQUEST['do'] = 'editreply';
	}
	else if ($vbulletin->GPC['advanced'])
	{
		$_REQUEST['do'] = 'editreply';
	}
	else
	{
		if (!$issuenote['issuenoteid'])
		{
			$issuenoteid = $issuenotedata->save();
			$issuenote = $issuenotedata->pt_issuenote;
			$issuenote['issuenoteid'] = $issuenoteid;

			if ($vbulletin->GPC['ajax'])
			{
				// AJAX return code
				// Quick reply
				require_once(DIR . '/includes/class_xml.php');

				$show['quick_reply'] = ($vbulletin->userinfo['userid'] AND $posting_perms['can_reply']);

				// determine which note types the browsing user can see
				$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
				$can_see_deleted = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']);

				// find total results for each type
				$notetype_counts = array(
					'user' => 0,
					'petition' => 0,
					'system' => 0
				);

				$hook_query_fields = $hook_query_joins = $hook_query_where = '';
				($hook = vBulletinHook::fetch_hook('projectpost_postreply_ajax')) ? eval($hook) : false; // Need to add to hook file for 2.2

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
						AND issuenote.issuenoteid = $issuenote[issuenoteid]
						AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
						$type_filter
						$hook_query_where
					ORDER BY issuenote.dateline
				");

				require_once(DIR . '/includes/class_bbcode_pt.php');
				$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

				require_once(DIR . '/includes/class_pt_issuenote.php');
				$factory = new vB_Pt_IssueNoteFactory();
				$factory->registry =& $vbulletin;
				$factory->bbcode =& $bbcode;
				$factory->issue =& $issue;
				$factory->project =& $project;
				$factory->browsing_perms = $issueperms;

				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('issuenotes');

				while ($note = $db->fetch_array($notes))
				{
					$note_handler =& $factory->create($note);
					$xml->add_tag('issuenotebit', process_replacement_vars($note_handler->construct($note)), array('issuenoteid' => $note['issuenoteid']));
				}

				$xml->add_tag('time', TIMENOW);
				$xml->close_group();
				$xml->print_xml(true);
			}
			else
			{
				send_issue_reply_notification($issue, $issuenote);
				handle_issue_subscription_change($issue['issueid'], $issue['subscribetype'], $vbulletin->GPC['subscribetype']);

				// trying to change the status while replying -- ensure we can actually do that
				if ($vbulletin->GPC['changestatusid'])
				{
					if ($posting_perms['status_edit'])
					{
						// changing status - make sure the type is right
						$status = $vbulletin->pt_issuestatus[$vbulletin->GPC['changestatusid']];
						if ($status AND $issue['issuetypeid'] == $status['issuetypeid'])
						{
							$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_SILENT);
							$issuedata->set_info('project', $project);
							$issuedata->set_existing($issue);
							$issuedata->set('issuestatusid', $vbulletin->GPC['changestatusid']);
							$issuedata->save();
						}
					}
				}

				($hook = vBulletinHook::fetch_hook('projectpost_postreply_complete')) ? eval($hook) : false;

				$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "do=gotonote&amp;issuenoteid=$issuenote[issuenoteid]";
				eval(print_standard_redirect('pt_issuenote_inserted'));
			}
		}
		else
		{
			$issuenotedata->save();

			$issuenote['issuenoteid'] = $vbulletin->GPC['issuenoteid'];

			if ($vbulletin->GPC['ajax'])
			{
				// AJAX return code
				// Quick edit
				require_once(DIR . '/includes/class_xml.php');

				$show['quick_reply'] = ($vbulletin->userinfo['userid'] AND $posting_perms['can_reply']);

				// determine which note types the browsing user can see
				$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
				$can_see_deleted = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']);

				// find total results for each type
				$notetype_counts = array(
					'user' => 0,
					'petition' => 0,
					'system' => 0
				);

				$hook_query_fields = $hook_query_joins = $hook_query_where = '';
				($hook = vBulletinHook::fetch_hook('projectpost_postreply_ajax')) ? eval($hook) : false; // Need to add to hook file for 2.2

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
						AND issuenote.issuenoteid = $issuenote[issuenoteid]
						AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
						$type_filter
						$hook_query_where
					ORDER BY issuenote.dateline
				");

				require_once(DIR . '/includes/class_bbcode_pt.php');
				$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

				require_once(DIR . '/includes/class_pt_issuenote.php');
				$factory = new vB_Pt_IssueNoteFactory();
				$factory->registry =& $vbulletin;
				$factory->bbcode =& $bbcode;
				$factory->issue =& $issue;
				$factory->project =& $project;
				$factory->browsing_perms = $issueperms;

				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('commentbits');

				while ($note = $db->fetch_array($notes))
				{
					$note_handler =& $factory->create($note);
					$xml->add_tag('message', process_replacement_vars($note_handler->construct($note)), array('issuenoteid' => $note['issuenoteid'], 'quickedit' => 1));
				}

				$xml->add_tag('time', TIMENOW);
				$xml->close_group();
				$xml->print_xml(true);
			}
			else
			{

				($hook = vBulletinHook::fetch_hook('projectpost_postreply_complete')) ? eval($hook) : false;

				$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "do=gotonote&amp;issuenoteid=$issuenote[issuenoteid]";
				eval(print_standard_redirect('pt_issuenote_edited'));
			}
		}
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'addreply' OR $_REQUEST['do'] == 'editreply')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'quotenoteid'      => TYPE_UINT,
		'petitionstatusid' => TYPE_UINT,
		'changestatusid'   => TYPE_UINT,
	));

	// determine what they can actually do
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (fetch_require_pt_hvcheck('post'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification =& vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	($hook = vBulletinHook::fetch_hook('projectpost_addreply_start')) ? eval($hook) : false;

	$usernamecode = vB_Template::create('newpost_usernamecode')->render();

	$show['editnote'] = ($issuenote['issuenoteid'] != 0);

	if ($issuenote['issuenoteid'])
	{
		$show['private_edit'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['caneditprivate']);
	}
	else
	{
		$show['private_edit'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancreateprivate']);
	}

	$edit_history = '';

	if (!$issue['subscribetype'] AND !defined('IS_PREVIEW'))
	{
		switch ($vbulletin->userinfo['autosubscribe'])
		{
			case -1: $issue['subscribetype'] = ''; break;
			case  0: $issue['subscribetype'] = 'none'; break;
			case  1: $issue['subscribetype'] = 'instant'; break;
			case  2: $issue['subscribetype'] = 'daily'; break;
			case  3: $issue['subscribetype'] = 'weekly'; break;
			default: $issue['subscribetype'] = ''; break;
		}
	}

	$show['quoted_private_auto'] = false; // true if we automatically set the note to private

	if (!$issuenote['issuenoteid'])
	{
		// Add reply
		if (!$vbulletin->pt_issuestatus["$issue[issuestatusid]"]['canpetitionfrom'])
		{
			$show['status_petition'] = false;
		}
		else
		{
			$show['status_petition'] = ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpetition']);
		}

		$show['delete_option'] = false;
		$show['undelete_option'] = false;
		$show['reason_option'] = false;

		$subscribe_selected = array(
			'donot' => ($issue['subscribetype'] == '' ? ' selected="selected"' : ''),
			'none' => ($issue['subscribetype'] == 'none' ? ' selected="selected"' : ''),
			'instant' => ($issue['subscribetype'] == 'instant' ? ' selected="selected"' : ''),
			'daily' => ($issue['subscribetype'] == 'daily' ? ' selected="selected"' : ''),
			'weekly' => ($issue['subscribetype'] == 'weekly' ? ' selected="selected"' : ''),
		);
		$show['subscribe_option'] = ($vbulletin->userinfo['userid'] > 0);

		if ($vbulletin->GPC['quotenoteid'])
		{
			$issuenote['pagetext'] = fetch_pt_quoted_note($vbulletin->GPC['quotenoteid'], $issue['issueid'], $issueperms, $quoted_private);
			if ($quoted_private AND ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancreateprivate']))
			{
				// quoting a private note, make this private by default if we can
				$issuenote['visible'] = 'private';
				$show['quoted_private_auto'] = true;
			}
		}
		else if (!defined('IS_PREVIEW'))
		{
			$issuenote['pagetext'] = '';
		}
	}
	else
	{
		// Edit reply
		$show['status_petition'] = false;
		$show['subscribe_option'] = false;

		if ($issuenote['issuenoteid'] == $issue['firstnoteid'])
		{
			$show['undelete_option'] = false;
			$show['delete_option'] = false;
			$show['private_edit'] = false;
		}
		else if ($issuenote['visible'] == 'deleted')
		{
			$show['undelete_option'] = (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenote']) AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']));
			$show['delete_option'] = false;
		}
		else
		{
			$show['undelete_option'] = false;
			$show['delete_option'] = (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenote']) AND
				($issuenote['userid'] == $vbulletin->userinfo['userid'] OR $issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeletenoteothers'])
			);
		}

		$show['reason_option'] = (!$show['undelete_option']); // show reason only when we're not showing undelete

		// show edit history
		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode = new vB_BbCodeParser($vbulletin, fetch_tag_list());

		require_once(DIR . '/includes/functions_pt_notehistory.php');

		$previous_edits =& fetch_note_history($issuenote['issuenoteid']);
		while ($history = $db->fetch_array($previous_edits))
		{
			$edit_history .= build_history_bit($history, $bbcode);
		}
	}

	// issue status for petition
	if ($show['status_petition'] OR $posting_perms['status_edit'])
	{
		$petition_options = build_issuestatus_select(
			$vbulletin->pt_issuetype["$issue[issuetypeid]"]['statuses'],
			$posting_perms['status_edit'] ? $vbulletin->GPC['changestatusid'] : $vbulletin->GPC['petitionstatusid'],
			array($issue['issuestatusid'])
		);
	}
	else
	{
		$petition_options = '';
	}

	// editor
	require_once(DIR . '/includes/functions_editor.php');
	$editorid = construct_edit_toolbar(
		htmlspecialchars_uni($issuenote['pagetext']),
		false,
		'pt',
		$vbulletin->options['pt_allowsmilies'],
		true,
		false,
		'fe',
		'',
		'', // attachments - handled differently in PT
		'content', // default value
		'vBProjectTools_IssueNote', // Content type - needed for auto-save
		$issuenote['issuenoteid'], // ID of the content
		$issue['issueid'] // Parent ID of the content
	);

	$private_checked = ($issuenote['visible'] == 'private' ? ' checked="checked"' : '');

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
		'' => ($issuenote['issuenoteid'] ? $vbphrase['edit_reply'] : $vbphrase['post_reply'])
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectpost_addreply_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_postreply');
		$templater->register_page_templates();
		$templater->register('editorid', $editorid);
		$templater->register('edit_history', $edit_history);
		$templater->register('human_verify', $human_verify);
		$templater->register('issue', $issue);
		$templater->register('issuenote', $issuenote);
		$templater->register('messagearea', $messagearea);
		$templater->register('navbar', $navbar);
		$templater->register('petition_options', $petition_options);
		$templater->register('posting_perms', $posting_perms);
		$templater->register('preview', $preview);
		$templater->register('private_checked', $private_checked);
		$templater->register('project', $project);
		$templater->register('subscribe_selected', $subscribe_selected);
		$templater->register('usernamecode', $usernamecode);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'postissue' OR $_REQUEST['do'] == 'addissue' OR $_REQUEST['do'] == 'editissue')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'milestoneid' => TYPE_UINT
	));

	if ($vbulletin->GPC['issueid'])
	{
		// editing an issue
		$issue = verify_issue($vbulletin->GPC['issueid'], true, array('milestone'));

		$project = verify_project($issue['projectid']);
		verify_issuetypeid($issue['issuetypeid'], $project['projectid']);

		$issuetype_printable = $vbphrase['issuetype_' . $issue['issuetypeid'] . '_singular'];
		$issuetype_printable_plural = $vbphrase['issuetype_' . $issue['issuetypeid'] . '_plural'];
		$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_" . $issue['issuetypeid']];

		$vbphrase['post_new_issue_issuetype'] = $vbphrase["post_new_issue_" . $issue['issuetypeid']];

		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
		$issueperms = $projectperms["$issue[issuetypeid]"];
		$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

		if (!$posting_perms['issue_edit'])
		{
			print_no_permission();
		}
	}
	else
	{
		// posting a new issue
		$project = verify_project($vbulletin->GPC['projectid']);
		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

		$type_choices = array_keys($vbulletin->pt_projects["$project[projectid]"]['types']);

		$can_post = array();
		foreach ($type_choices AS $issuetype)
		{
			if ($projectperms["$issuetype"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']
				AND $projectperms["$issuetype"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
			{
				if (!$vbulletin->GPC['issuetypeid'] OR $vbulletin->GPC['issuetypeid'] == $issuetype)
				{
					$can_post[] = $issuetype;
				}
			}
		}

		if (!$can_post)
		{
			print_no_permission();
		}
		else if (sizeof($can_post) == 1)
		{
			$vbulletin->GPC['issuetypeid'] = $can_post[0];
		}

		if ($vbulletin->GPC['issuetypeid'])
		{
			// we have a issue type, don't need the short form
			verify_issuetypeid($vbulletin->GPC['issuetypeid'], $project['projectid']);

			$issue = array(
				'issueid' => 0,
				'projectid' => $project['projectid'],
				'issuestatusid' => $vbulletin->pt_projects["$project[projectid]"]['types'][$vbulletin->GPC['issuetypeid']],
				'issuetypeid' => $vbulletin->GPC['issuetypeid'],
				'issuetype' => $vbphrase['issuetype_' . $vbulletin->GPC['issuetypeid'] . '_singular'],
				'projectcategoryid' => 0,
				'title' => '',
				'summary' => '',
				'pagetext' => '',
				'priority' => 0
			);
			
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

			$issueperms = $projectperms["$issue[issuetypeid]"];
			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew'])
				OR !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
			{
				print_no_permission();
			}
		}
		else
		{
			// no issue type, need the short form
			$issue = array(
				'issueid' => 0,
				'projectid' => $project['projectid'],
				'title' => '',
				'summary' => '',
				'pagetext' => '',
				'priority' => 0
			);
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_issue_setup')) ? eval($hook) : false;
}

// #######################################################################
if ($_POST['do'] == 'postissue')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_NOHTML,
		'summary' => TYPE_NOHTML,
		'issuestatusid' => TYPE_UINT,
		'priority' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT,
		'milestoneid' => TYPE_UINT,
		'projectcategoryid' => TYPE_UINT,
		'message' => TYPE_STR,
		'wysiwyg' => TYPE_BOOL,
		'appliedtags' => TYPE_ARRAY_NOHTML,
		'unappliedtags' => TYPE_ARRAY_NOHTML,
		'customtag' => TYPE_NOHTML,
		'submit_addtag' => TYPE_STR,
		'submit_removetag' => TYPE_STR,
		'assigned' => TYPE_ARRAY_UINT,
		'unassigned' => TYPE_ARRAY_UINT,
		'submit_assign' => TYPE_STR,
		'submit_unassign' => TYPE_STR,
		'assignself' => TYPE_BOOL,
		'private' => TYPE_BOOL,
		'close_issue' => TYPE_BOOL,
		'original_state' => TYPE_STR,
		'delete' => TYPE_STR,
		'undelete' => TYPE_BOOL,
		'reason' => TYPE_NOHTML,
		'subscribetype' => TYPE_NOHTML,
		'preview' => TYPE_NOHTML,
		'humanverify' => TYPE_ARRAY,
		'ajax' => TYPE_BOOL,
		'advanced' => TYPE_BOOL
	));

	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/class_wysiwygparser.php');
		$html_parser = new vB_WysiwygHtmlParser($vbulletin);
		$vbulletin->GPC['message'] = $html_parser->parse_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->options['pt_allowhtml']);
	}

	if ($vbulletin->GPC['ajax'])
	{
		// posting via ajax so we need to handle those %u0000 entries
		$vbulletin->GPC['message'] = convert_urlencoded_unicode($vbulletin->GPC['message']);
	}

	if ($vbulletin->GPC['ajax'] AND $vbulletin->GPC['username'])
	{
		if ($vbulletin->GPC['username'])
		{
			$vbulletin->GPC['username'] = convert_urlencoded_unicode($vbulletin->GPC['username']);
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_postissue_start')) ? eval($hook) : false;

	// determine what they can actually do
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if ($posting_perms['status_edit'] AND $vbulletin->GPC['issuestatusid'])
	{
		// changing status - make sure the type is right
		$status = $vbulletin->pt_issuestatus[$vbulletin->GPC['issuestatusid']];
		if (!$status)
		{
			standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
		}

		if ($issue['issuetypeid'] != $status['issuetypeid'])
		{
			// trying to change the type and we can't
			standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
		}
	}
	else
	{
		$vbulletin->GPC['issuestatusid'] = $issue['issuestatusid'];
	}

	// prepare issue
	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_ARRAY);
	$issuedata->set_info('project', $project);
	if ($issue['issueid'])
	{
		$issuedata->set_existing($issue);

		if ($vbulletin->GPC['delete'])
		{
			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissue']) OR (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissueothers']) AND $vbulletin->userinfo['userid'] != $issue['submituserid'])
			)
			{
				print_no_permission();
			}

			$issuedata->delete($vbulletin->GPC['delete'] == 'hard');

			$vbulletin->url = 'project.php?' . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]";
			eval(print_standard_redirect('pt_issue_deleted'));
		}
		else if ($vbulletin->GPC['undelete'])
		{
			if (!($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissue']) OR !($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']))
			{
				print_no_permission();
			}

			$issuedata->undelete();

			$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
			eval(print_standard_redirect('pt_issue_undeleted'));
		}
	}

	if (!$vbulletin->GPC['advanced'])
	{
		$issuedata->set('title', $vbulletin->GPC['title']);
		$issuedata->set('summary', $vbulletin->GPC['summary']);
		$issuedata->set('issuestatusid', $vbulletin->GPC['issuestatusid']);
		$issuedata->set('priority', $vbulletin->GPC['priority']);
		$issuedata->set('projectcategoryid', $vbulletin->GPC['projectcategoryid']);
		$issuedata->set('appliesversionid', $vbulletin->GPC['appliesversionid']);

		if ($posting_perms['status_edit'])
		{
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
		}

		if ($posting_perms['milestone_edit'])
		{
			$issuedata->set('milestoneid', $vbulletin->GPC['milestoneid']);
		}

		if ($posting_perms['issue_close'] AND $vbulletin->GPC_exists['original_state'])
		{
			if (!$issue['issueid']
				OR ($vbulletin->GPC['close_issue'] AND $vbulletin->GPC['original_state'] == 'open')
				OR (!$vbulletin->GPC['close_issue'] AND $vbulletin->GPC['original_state'] == 'closed')
			)
			{
				// new issue or we tried to change the state
				$issuedata->set('state', $vbulletin->GPC['close_issue'] ? 'closed' : 'open');
			}
		}

		$existing_tags = array();
		$existing_assignments = array();

		if (!$issue['issueid'])
		{
			$issuedata->set('projectid', $project['projectid']);
			$issuedata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);

			$issuedata->set('submituserid', $vbulletin->userinfo['userid']);
			$issuedata->set('submitusername', $vbulletin->userinfo['username']);
			$issuedata->set('visible', ($posting_perms['private_edit'] AND $vbulletin->GPC['private']) ? 'private' : 'visible');
			$issuedata->set('lastpost', TIMENOW);
		}
		else
		{
			if ($posting_perms['private_edit'])
			{
				if ($vbulletin->GPC['private'])
				{
					// make it private
					$issuedata->set('visible', 'private');
				}
				else
				{
					// make it visible if it was private, else leave it as is
					$issuedata->set('visible', $issue['visible'] == 'private' ? 'visible': $issue['visible']);
				}
			}

			// tags
			$tag_data = $db->query_read("
				SELECT tag.tagtext
				FROM " . TABLE_PREFIX . "pt_issuetag AS issuetag
				INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (issuetag.tagid = tag.tagid)
				WHERE issuetag.issueid = $issue[issueid]
				ORDER BY tag.tagtext
			");
			while ($tag = $db->fetch_array($tag_data))
			{
				$existing_tags[] = $tag['tagtext'];
			}

			// assignments
			$assignment_data = $db->query_read("
				SELECT user.userid
				FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
				WHERE issueassign.issueid = $issue[issueid]
				ORDER BY user.username
			");
			while ($assignment = $db->fetch_array($assignment_data))
			{
				$existing_assignments["$assignment[userid]"] = $assignment['userid'];
			}
		}

		// prepare tag changes
		if ($posting_perms['tags_edit'])
		{
			$issuedata->set_info('allow_tag_creation', $posting_perms['can_custom_tag']);
			prepare_tag_changes($vbulletin->GPC, $existing_tags, $tag_add, $tag_remove);

			foreach ($tag_add AS $tag)
			{
				$issuedata->add_tag($tag);
			}

			foreach ($tag_remove AS $tag)
			{
				$issuedata->remove_tag($tag);
			}
		}
	}

	// prepare first note
	$issuenote =& datamanager_init('Pt_IssueNote_User', $vbulletin, ERRTYPE_ARRAY, 'pt_issuenote');
	$issuenote->set_info('do_floodcheck', !can_moderate());
	$issuenote->set_info('parseurl', $vbulletin->options['pt_allowbbcode']);
	if ($issue['issueid'])
	{
		$issuenote->set_existing($issue);

		$issuenote->set('pagetext', $vbulletin->GPC['message']);
		$issuenote->set_info('reason', $vbulletin->GPC['reason']);
	}
	else
	{
		$issuenote->set('userid', $vbulletin->userinfo['userid']);
		$issuenote->set('username', $vbulletin->userinfo['username']);
		$issuenote->set('visible', 'visible');
		$issuenote->set('isfirstnote', 1);
		$issuenote->set('pagetext', $vbulletin->GPC['message']);
	}

	if (fetch_require_pt_hvcheck('post'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verify =& vB_HumanVerify::fetch_library($vbulletin);
		if (!$verify->verify_token($vbulletin->GPC['humanverify']))
		{
			$issuedata->error($verify->fetch_error());
		}
	}

	($hook = vBulletinHook::fetch_hook('projectpost_postissue_save')) ? eval($hook) : false;

	$issuedata->set_info('perform_activity_updates', $issuedata->have_issue_changes());

	$issuedata->pre_save();
	if (!$issuedata->errors)
	{
		$issuenote->pre_save();
	}

	$errors = array_merge($issuedata->errors, $issuenote->errors);

	if ($errors OR $vbulletin->GPC['preview'] OR $vbulletin->GPC['advanced'])
	{
		if ($errors)
		{
			if ($vbulletin->GPC['ajax'])
			{
				require_once(DIR . '/includes/class_xml.php');
				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('errors');
				foreach ($errors AS $error)
				{
					$xml->add_tag('error', $error);
					echo $error;
				}
				$xml->close_group();
				$xml->print_xml(true);
			}
			else
			{
				require_once(DIR . '/includes/functions_newpost.php');
				$preview = construct_errors($errors);
			}
		}
		else if ($vbulletin->GPC['preview'])
		{
			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode = new vB_BbCodeParser($vbulletin, fetch_tag_list());
			$preview_text =  $bbcode->parse($vbulletin->GPC['message'], 'pt');

			$templater = vB_Template::create('pt_preview');
				$templater->register('preview_text', $preview_text);
			$preview = $templater->render();
		}

		$input_map = array(
			'title' => $vbulletin->GPC['title'],
			'summary' => $vbulletin->GPC['summary'],
			'issuestatusid' => $vbulletin->GPC['issuestatusid'],
			'priority' => $vbulletin->GPC['priority'],
			'projectcategoryid' => $vbulletin->GPC['projectcategoryid'],
			'appliesversionid' => $vbulletin->GPC['appliesversionid'],
			'addressedversionid' => $vbulletin->GPC['addressedversionid'],
			'private' => $vbulletin->GPC['private'],
			'reason' => $vbulletin->GPC['reason'],
			'subscribetype' => $vbulletin->GPC['subscribetype'],
		);

		$issue = $issuedata->pt_issue + $issuedata->existing + $input_map;
		$issue['pagetext'] = (isset($issuenote->pt_issue['pagetext']) ? $issuenote->fetch_field('pagetext') : $vbulletin->GPC['message']);
		$issue['issueid'] = intval($issue['issueid']);
		$issue['issuetype'] = $vbphrase["issuetype_$issue[issuetypeid]_singular"];

		if ($vbulletin->GPC['assigned'])
		{
			$preview_assigned = array();
			foreach ($vbulletin->GPC['assigned'] AS $val)
			{
				$preview_assigned["$val"] = $val;
			}
		}
		else
		{
			$preview_assigned = array();
		}
		$issue['isassigned'] = (!empty($vbulletin->GPC['assignself']) OR isset($preview_assigned[$vbulletin->userinfo['userid']]));

		if ($vbulletin->GPC['appliedtags'])
		{
			$preview_tags = array();
			foreach ($vbulletin->GPC['appliedtags'] AS $val)
			{
				$preview_tags["$val"] = $val;
			}
		}
		else
		{
			$preview_tags = array();
		}

		define('IS_PREVIEW', true);
		$_REQUEST['do'] = 'editissue';

		($hook = vBulletinHook::fetch_hook('projectpost_postissue_preview')) ? eval($hook) : false;
	}
	else
	{
		if ($issue['issueid'])
		{
			$issuedata->save();
			if ($vbulletin->GPC['message'] != $issue['pagetext'])
			{
				$issuenote->save();
			}
			$log_assignment_changes = true;
		}
		else
		{
			$issue['issueid'] = $issuedata->save();
			$issuenote->set('issueid', $issue['issueid']);
			$issue['issuenoteid'] = $issuenote->save();
			$log_assignment_changes = false;
		}

		// user assignments
		process_assignment_changes($vbulletin->GPC, $posting_perms, $existing_assignments, $project, $issue, $log_assignment_changes);

		// done
		if ($vbulletin->GPC['issueid'])
		{
			if ($vbulletin->GPC['ajax'])
			{
				// Quick edit
				$issue = verify_issue($vbulletin->GPC['issueid'], true, array('avatar', 'vote', 'milestone'));
				$project = verify_project($issue['projectid']);

				$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
				$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

				// determine which note types the browsing user can see
				$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
				$can_see_deleted = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']);

				$show['reply_issue'] = $posting_perms['can_reply'];
				$show['edit_issue'] = $posting_perms['issue_edit'];

				// find total results for each type
				$notetype_counts = array(
					'user' => 0,
					'petition' => 0,
					'system' => 0
				);

				$hook_query_fields = $hook_query_joins = $hook_query_where = '';
				($hook = vBulletinHook::fetch_hook('projectpost_postreply_ajax')) ? eval($hook) : false; // Need to add to hook file for 2.2

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

				// notes
				$note = $db->query_first("
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
						AND issuenote.issuenoteid = $issue[firstnoteid]
						AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
						$type_filter
						$hook_query_where
					ORDER BY issuenote.dateline
				");

				require_once(DIR . '/includes/class_bbcode_pt.php');
				$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

				require_once(DIR . '/includes/class_pt_issuenote.php');
				$factory = new vB_Pt_IssueNoteFactory();
				$factory->registry =& $vbulletin;
				$factory->bbcode =& $bbcode;
				$factory->issue =& $issue;
				$factory->project =& $project;
				$factory->browsing_perms = $issueperms;

				require_once(DIR . '/includes/class_xml.php');
				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('commentbits');

				// Workaround some issues with the general system
				$note['type'] = 'firstnote';

				$note_handler =& $factory->create($note);

				$xml->add_tag('message', process_replacement_vars($note_handler->construct($note)), array('issuenoteid' => $note['issuenoteid'], 'quickedit' => 1));

				$xml->add_tag('time', TIMENOW);
				$xml->close_group();
				$xml->print_xml(true);
			}
			else
			{
				($hook = vBulletinHook::fetch_hook('projectpost_postissue_complete')) ? eval($hook) : false;

				$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
				eval(print_standard_redirect('pt_issue_edited'));
			}
		}
		else
		{
			handle_issue_subscription_change($issue['issueid'], '', $vbulletin->GPC['subscribetype']);

			($hook = vBulletinHook::fetch_hook('projectpost_postissue_complete')) ? eval($hook) : false;

			$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
			eval(print_standard_redirect('pt_issue_inserted'));
		}
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'addissue' OR $_REQUEST['do'] == 'editissue')
{
	// navbar - do this up here because of pt_postissue_short
	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean']
	);

	if ($issue['issueid'])
	{
		$navbits[fetch_seo_url('issue', $issue)] = $issue['title'];
		$navbits[''] = $vbphrase['edit_issue'];
	}
	else
	{
		$navbits[''] = $vbphrase['post_new_issue'];
	}

	if (fetch_require_pt_hvcheck('post'))
	{
		require_once(DIR . '/includes/class_humanverify.php');
		$verification =& vB_HumanVerify::fetch_library($vbulletin);
		$human_verify = $verification->output_token();
	}
	else
	{
		$human_verify = '';
	}

	($hook = vBulletinHook::fetch_hook('projectpost_addissue_start')) ? eval($hook) : false;

	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);

	$usernamecode = vB_Template::create('newpost_usernamecode')->render();

	if (!$issue['issuetypeid'])
	{
		// need to select an issue type first
		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
		foreach (array_keys($vbulletin->pt_issuetype) AS $issuetypeid)
		{
			$show["type_$issuetypeid"] = (($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) AND ($projectperms["$issuetypeid"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']));
		}

		$optionclass = '';
		$optionselected = '';
		$issuetype_options = $option = array();

		foreach (array_keys($vbulletin->pt_projects["$project[projectid]"]['types']) AS $type)
		{
			if (!($projectperms["$type"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($projectperms["$type"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
			{
				continue;
			}

			$option['value'] = $type;
			$option['title'] = $vbphrase["issuetype_{$type}_singular"];
			$issuetype_options[] = $option;
		}

		$templater = vB_Template::create('pt_postissue_short');
			$templater->register_page_templates();
			$templater->register('issue', $issue);
			$templater->register('issuetype_options', $issuetype_options);
			$templater->register('navbar', $navbar);
			$templater->register('project', $project);
		print_output($templater->render());
	}

	// ######
	if ($issue['issueid'])
	{
		// editing an issue
		if ($issue['visible'] == 'deleted')
		{
			$show['undelete_option'] = (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissue']) AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']));
			$show['delete_option'] = false;
		}
		else
		{
			$show['undelete_option'] = false;
			$show['delete_option'] = (($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissue'])
				AND ($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['candeleteissueothers'] OR $vbulletin->userinfo['userid'] == $issue['submituserid'])
			);
		}

		$show['reason_option'] = (!$show['undelete_option']); // show reason only when we're not showing undelete
	}
	else
	{
		if (!defined('IS_PREVIEW'))
		{
			$vbulletin->input->clean_array_gpc('p', array(
				'title' => TYPE_NOHTML,
				'summary' => TYPE_NOHTML
			));

			if ($vbulletin->GPC_exists['title'])
			{
				$issue['title'] = $vbulletin->GPC['title'];
			}
			if ($vbulletin->GPC_exists['summary'])
			{
				$issue['summary'] = $vbulletin->GPC['summary'];
			}
		}

		$show['delete_option'] = false;
		$show['undelete_option'] = false;
		$show['reason_option'] = false;
	}

	// setup posting permissions
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);
	$show['status_edit'] = $posting_perms['status_edit'];
	$show['tags_edit'] = $posting_perms['tags_edit'];
	$show['can_custom_tag'] = $posting_perms['can_custom_tag'];
	$show['assign_checkbox'] = $posting_perms['assign_checkbox'];
	$show['assign_dropdown'] = $posting_perms['assign_dropdown'];
	$show['private_edit'] = $posting_perms['private_edit'];
	$assign_checkbox_checked = $posting_perms['assign_checkbox_checked'];

	$show['close_issue'] = $posting_perms['issue_close'];
	$close_issue_checked = ($issue['state'] == 'closed' ? ' checked="checked"' : '');

	// setup default subscribe type
	if ($issue['subscribetype'] === NULL)
	{
		switch ($vbulletin->userinfo['autosubscribe'])
		{
			case -1: $issue['subscribetype'] = ''; break;
			case 0: $issue['subscribetype'] = 'none'; break;
			case 1: $issue['subscribetype'] = 'instant'; break;
			case 2: $issue['subscribetype'] = 'daily'; break;
			case 3: $issue['subscribetype'] = 'weekly'; break;
			default: $issue['subscribetype'] = ''; break;
		}
	}

	$subscribe_selected = array(
		'donot' => ($issue['subscribetype'] == '' ? ' selected="selected"' : ''),
		'none' => ($issue['subscribetype'] == 'none' ? ' selected="selected"' : ''),
		'instant' => ($issue['subscribetype'] == 'instant' ? ' selected="selected"' : ''),
		'daily' => ($issue['subscribetype'] == 'daily' ? ' selected="selected"' : ''),
		'weekly' => ($issue['subscribetype'] == 'weekly' ? ' selected="selected"' : ''),
	);

	$show['subscribe_option'] = (!$issue['issueid'] AND $vbulletin->userinfo['userid'] > 0);

	// setup milestones
	$show['milestone'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'] AND $project['milestonecount']);
	$show['milestone_edit'] = ($show['milestone'] AND $posting_perms['milestone_edit']);

	if (!empty($vbulletin->GPC['milestoneid']) AND empty($issue['milestoneid']))
	{
		$issue['milestoneid'] = $vbulletin->GPC['milestoneid'];
	}

	$milestone_options = fetch_milestone_select($project['projectid'], $issue['milestoneid']);

	// figure out viable status/type options
	if ($show['status_edit'])
	{
		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
		$status_options = build_issuestatus_select($vbulletin->pt_issuetype["$issue[issuetypeid]"]['statuses'], $issue['issuestatusid']);
	}
	else
	{
		$issue['issuestatus'] =  $vbphrase["issuestatus$issue[issuestatusid]"];
	}

	$category_options = array();

	foreach ($vbulletin->pt_categories AS $category)
	{
		$option = array();

		if ($category['projectid'] != $project['projectid'])
		{
			continue;
		}

		$option['title'] = $vbphrase['category' . $category['projectcategoryid']];
		$option['value'] = $category['projectcategoryid'];
		$option['selected'] = ($issue['projectcategoryid'] == $category['projectcategoryid'] ? ' selected="selected"' : '');

		$category_options[] = $option;
	}

	// setup priority options
	$priority = $priority_options = array();

	$priorities = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = " . $project['projectid'] . "
	");

	while ($prioritys = $db->fetch_array($priorities))
	{
		$priority["$prioritys[projectpriorityid]"] = $prioritys;
	}

	foreach ($priority AS $optionvalue => $options)
	{
		$option = array();

		$option['title'] = $vbphrase['priority' . $optionvalue . ''];
		$option['value'] = $optionvalue;
		$option['selected'] = ($issue['priority'] == $optionvalue ? ' selected="selected"' : '');

		$priority_options[] = $option;
	}

	// setup versions
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

	$applies_versions = array();
	$addressed_versions = array();

	foreach ($version_groups AS $optgroup_label => $versions)
	{
		$option = array();
		$optiongroup = array();
		$group_applies = array();
		$group_addressed = array();

		foreach ($versions AS $optionvalue => $optiontitle)
		{
			$option['title'] = $optiontitle;
			$option['value'] = $optionvalue;
			$option['selected'] = ($issue['appliesversionid'] == $optionvalue ? ' selected="selected"' : '');

			$group_applies[] = $option;

			$option['selected'] = (($issue['isaddressed'] AND $issue['addressedversionid'] == $optionvalue) ? ' selected="selected"' : '');

			$group_addressed[] = $option;
		}

		$optiongroup['group'] = $group_applies;
		$optiongroup['label'] = $optgroup_label;

		$applies_versions[] = $optiongroup;

		$optiongroup['group'] = $group_addressed;

		$addressed_versions[] = $optiongroup;
	}

	$applies_unknown_selected = ($issue['appliesversionid'] == 0 ? ' selected="selected"' : '');

	if ($posting_perms['status_edit'])
	{
		$addressed_unaddressed_selected = ($issue['isaddressed'] == 0 ? ' selected="selected"' : '');
		$addressed_next_selected = (($issue['isaddressed'] == 1 AND $issue['addressedversionid'] == 0) ? ' selected="selected"' : '');
	}

	$issue = fetch_issue_version_text($issue);

	// set up appliable tags
	$unapplied_tags = array();
	$applied_tags = array();

	$tag_data = $db->query_read("
		SELECT tag.tagtext, IF(issuetag.tagid IS NOT NULL, 1, 0) AS isapplied
		FROM " . TABLE_PREFIX . "pt_tag AS tag
		LEFT JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag ON (issuetag.tagid = tag.tagid AND issuetag.issueid = $issue[issueid])
		ORDER BY tag.tagtext
	");

	$option = array();

	while ($tag = $db->fetch_array($tag_data))
	{
		$option['value'] = $option['title'] = $tag['tagtext'];
		if ((!defined('IS_PREVIEW') AND $tag['isapplied']) OR (defined('IS_PREVIEW') AND isset($preview_tags["$tag[tagtext]"])))
		{
			unset($preview_tags["$tag[tagtext]"]);
			$applied_tags[] = $option;
		}
		else
		{
			$unapplied_tags[] = $option;
		}
	}

	if (defined('IS_PREVIEW') AND !empty($preview_tags))
	{
		foreach ($preview_tags AS $optionvalue => $optiontitle)
		{
			$applied_tags[] = $option;
		}
	}

	// set up assignable users
	$assigned_user_list = array();

	if (defined('IS_PREVIEW'))
	{
		$assigned_user_list = $preview_assigned;
	}
	else if ($issue['issueid'])
	{
		// assignments
		$assignments = '';
		$assignment_data = $db->query_read("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
			WHERE issueassign.issueid = $issue[issueid]
			ORDER BY user.username
		");

		while ($assignment = $db->fetch_array($assignment_data))
		{
			$assigned_user_list["$assignment[userid]"] = $assignment['userid'];
		}
	}

	$unassigned_users = $assigned_users = $option = array();

	foreach ($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"] AS $optionvalue => $optiontitle)
	{
		$option['title'] = $optiontitle;
		$option['value'] = $optionvalue;

		if (isset($assigned_user_list["$optionvalue"]))
		{
			$assigned_users[] = $option;
		}
		else
		{
			$unassigned_users[] = $option;
		}
	}

	$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_$issue[issuetypeid]"];
	$vbphrase['addressed_version_issuetype'] = $vbphrase["addressed_version_$issue[issuetypeid]"];

	// editor
	require_once(DIR . '/includes/functions_editor.php');
	$editorid = construct_edit_toolbar(
		htmlspecialchars_uni($issue['pagetext']),
		false,
		'pt',
		$vbulletin->options['pt_allowsmilies'],
		true,
		false,
		'fe',
		'',
		'', // attachments - handled differently in PT
		'content', // default value
		'vBProjectTools_Issue', // Content type - needed for auto-save
		$issue['issueid'] // ID of the content
	);

	$private_checked = ($issue['visible'] == 'private' ? ' checked="checked"' : '');

	($hook = vBulletinHook::fetch_hook('projectpost_addissue_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_postissue');
		$templater->register_page_templates();
		$templater->register('issuetype_printable', $issuetype_printable);
		$templater->register('addressed_next_selected', $addressed_next_selected);
		$templater->register('addressed_unaddressed_selected', $addressed_unaddressed_selected);
		$templater->register('addressed_versions', $addressed_versions);
		$templater->register('applied_tags', $applied_tags);
		$templater->register('applies_unknown_selected', $applies_unknown_selected);
		$templater->register('applies_versions', $applies_versions);
		$templater->register('assigned_users', $assigned_users);
		$templater->register('assign_checkbox_checked', $assign_checkbox_checked);
		$templater->register('category_options', $category_options);
		$templater->register('category_unknown_selected', $category_unknown_selected);
		$templater->register('close_issue_checked', $close_issue_checked);
		$templater->register('editorid', $editorid);
		$templater->register('human_verify', $human_verify);
		$templater->register('issue', $issue);
		$templater->register('messagearea', $messagearea);
		$templater->register('milestone_options', $milestone_options);
		$templater->register('navbar', $navbar);
		$templater->register('preview', $preview);
		$templater->register('priority_options', $priority_options);
		$templater->register('private_checked', $private_checked);
		$templater->register('project', $project);
		$templater->register('status_options', $status_options);
		$templater->register('subscribe_selected', $subscribe_selected);
		$templater->register('unapplied_tags', $unapplied_tags);
		$templater->register('unassigned_users', $unassigned_users);
		$templater->register('usernamecode', $usernamecode);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'uploadattachment')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issueid' => TYPE_UINT,
		'contenttypeid' => TYPE_UINT
	));

	$attachments = $db->query_read("
		SELECT attachmentid
		FROM " . TABLE_PREFIX . "attachment
		WHERE contenttypeid = " . $vbulletin->GPC['contenttypeid'] . "
			AND contentid = 0
	");

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattach']) OR is_issue_closed($issue, $issueperms))
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('projectpost_attachment_upload')) ? eval($hook) : false;

	require_once(DIR . '/includes/class_dm.php');
	require_once(DIR . '/includes/class_dm_attachment_pt.php');

	while ($attachment = $db->fetch_array($attachments))
	{
		$attachdata =& vB_DataManager_Attachment_Pt::fetch_library($vbulletin, ERRTYPE_STANDARD);
			$attachdata->setr('attachmentid', $attachment['attachmentid']);
			$attachdata->setr('userid', $vbulletin->userinfo['userid']);
			$attachdata->setr('issueid', $issue['issueid']);
			$attachdata->set('visible', 1); // Need to redefine how to manage it
		$attachdata->save();
	}

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]#attachments";
	eval(print_standard_redirect('pt_attachment_uploaded'));
}

// #######################################################################
if ($_POST['do'] == 'updateattach')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'attachmentid' => TYPE_UINT,
		'delete' => TYPE_BOOL,
		'obsolete' => TYPE_BOOL,
		'unobsolete' => TYPE_BOOL,
	));

	$attachment = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "attachment
		WHERE attachmentid = " . $vbulletin->GPC['attachmentid']
	);

	$issue = verify_issue($attachment['contentid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachedit'])
		OR (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattacheditothers']) AND $vbulletin->userinfo['userid'] != $attachment['userid'])
	)
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/class_dm.php');
	require_once(DIR . '/includes/class_dm_attachment_pt.php');

	$attachdata =& vB_DataManager_Attachment_Pt::fetch_library($vbulletin, ERRTYPE_STANDARD);
	$attachdata->set_existing($attachment);

	($hook = vBulletinHook::fetch_hook('projectpost_attachment_update')) ? eval($hook) : false;

	if ($vbulletin->GPC['delete'])
	{
		$attachdata->delete();

		$vbulletin->url = fetch_seo_url('issue', $issue) . "#attachments";
		eval(print_standard_redirect('pt_attachment_deleted'));
	}
	else if ($vbulletin->GPC['obsolete'] OR $vbulletin->GPC['unobsolete'])
	{
		if ($vbulletin->GPC['obsolete'])
		{
			$attachdata->set('status', 'obsolete');
		}
		else if ($vbulletin->GPC['unobsolete'])
		{
			$attachdata->set('status', 'current');
		}
		$attachdata->save();

		$vbulletin->url = fetch_seo_url('issue', $issue) . "#attachments";
		eval(print_standard_redirect('pt_attachment_edited'));
	}
	else
	{
		// we did nothing, just make it look like an edit
		$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]#attachments";
		eval(print_standard_redirect('pt_attachment_edited'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'manageattach')
{
	$vbulletin->input->clean_gpc('r', 'attachmentid', TYPE_UINT);

	$attachment = $db->query_first("
		SELECT a.*, i.status
		FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "pt_issueattach AS i ON (i.attachmentid = a.attachmentid)
		WHERE a.attachmentid = " . $vbulletin->GPC['attachmentid']
	);

	$issue = verify_issue($attachment['contentid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	if (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachedit'])
		OR (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattacheditothers']) AND $vbulletin->userinfo['userid'] != $attachment['userid'])
	)
	{
		print_no_permission();
	}

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
		'' => $vbphrase['manage_attachment']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectpost_attachment_manage')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_manageattach');
		$templater->register_page_templates();
		$templater->register('attachment', $attachment);
		$templater->register('navbar', $navbar);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'updateprojectsubscription')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectid' => TYPE_UINT,
		'issuetypes' => TYPE_ARRAY_NOHTML,
		'subscribetype' => TYPE_NOHTML
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$project = verify_project($vbulletin->GPC['projectid']);
	$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

	switch ($vbulletin->GPC['subscribetype'])
	{
		case 'none':
		case 'daily':
		case 'weekly':
			break;

		default:
			$vbulletin->GPC['subscribetype'] = 'none';
	}

	($hook = vBulletinHook::fetch_hook('projectpost_projectsubscription_update')) ? eval($hook) : false;

	foreach ($vbulletin->GPC['issuetypes'] AS $issuetypeid)
	{
		if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			continue;
		}

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "pt_projecttypesubscribe
				(userid, projectid, issuetypeid, subscribetype)
			VALUES
				(" . $vbulletin->userinfo['userid'] . ",
				$project[projectid],
				'" . $db->escape_string($issuetypeid) . "',
				'" . $db->escape_string($vbulletin->GPC['subscribetype']) . "')
		");
	}

	$vbulletin->url = 'project.php?' . $vbulletin->session->vars['sessionurl'] . "projectid=$project[projectid]";
	eval(print_standard_redirect('pt_subscriptions_updated'));
}

// #######################################################################
if ($_REQUEST['do'] == 'manageprojectsubscription')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$project = verify_project($vbulletin->GPC['projectid']);
	$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);

	foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
	{
		if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			continue;
		}

		$optionid = "type_$issuetypeid";
		$optionname = "issuetypes[]";
		$optionvalue = $issuetypeid;
		$optionchecked = '';
		$optiontitle = $vbphrase["issuetype_{$issuetypeid}_plural"];

		$templater = vB_Template::create('pt_checkbox_option');
			$templater->register('optionchecked', $optionchecked);
			$templater->register('optionid', $optionid);
			$templater->register('optionname', $optionname);
			$templater->register('optiontitle', $optiontitle);
			$templater->register('optionvalue', $optionvalue);
		$issuetypes .= $templater->render();
	}

	if (!$issuetypes)
	{
		print_no_permission();
	}

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		'' => $vbphrase['manage_project_subscription']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectpost_project_subscription_manage')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_manageprojectsubscription');
		$templater->register_page_templates();
		$templater->register('issuetypes', $issuetypes);
		$templater->register('navbar', $navbar);
		$templater->register('project', $project);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'updatesubscription')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issueid' => TYPE_UINT,
		'subscribetype' => TYPE_NOHTML
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('projectpost_subscription_update')) ? eval($hook) : false;

	if ($vbulletin->GPC['subscribetype'])
	{
		$subscriptiondata =& datamanager_init('Pt_IssueSubscribe', $vbulletin, ERRTYPE_STANDARD);
		$subscriptiondata->set('subscribetype', $vbulletin->GPC['subscribetype']);
		$subscriptiondata->set('issueid', $issue['issueid']);
		$subscriptiondata->set('userid', $vbulletin->userinfo['userid']);
		$subscriptiondata->save();
	}
	else
	{
		$subscription = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuesubscribe
			WHERE issueid = $issue[issueid]
				AND userid = " . $vbulletin->userinfo['userid']
		);
		if ($subscription)
		{
			$subscriptiondata =& datamanager_init('Pt_IssueSubscribe', $vbulletin, ERRTYPE_STANDARD);
			$subscriptiondata->set_existing($subscription);
			$subscriptiondata->delete();
		}
	}

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
	eval(print_standard_redirect('pt_subscriptions_updated'));
}

// #######################################################################
if ($_REQUEST['do'] == 'managesubscription')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$subscription = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuesubscribe
		WHERE issueid = $issue[issueid]
			AND userid = " . $vbulletin->userinfo['userid']
	);

	// navbar and output
	$navbits = construct_navbits(array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
		'' => $vbphrase['manage_subscription']
	));
	$navbar = render_navbar_template($navbits);

	if ($subscription)
	{
		$subscription_selected = array("$subscription[subscribetype]" => ' selected="selected"');
	}
	else
	{
		$type_map = array('none', 'instant', 'daily', 'weekly');

		$type = (($vbulletin->userinfo['autosubscribe'] == 9999 OR $vbulletin->userinfo['autosubscribe'] == -1) ? 'none' : $type_map[$vbulletin->userinfo['autosubscribe']]);

		$subscription_selected = array($type => ' selected="selected"');
	}

	($hook = vBulletinHook::fetch_hook('projectpost_subscription_manage')) ? eval($hook) : false;

	$templater = vB_Template::create('pt_managesubscription');
		$templater->register_page_templates();
		$templater->register('issue', $issue);
		$templater->register('navbar', $navbar);
		$templater->register('subscription', $subscription);
		$templater->register('subscription_selected', $subscription_selected);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'updatesubscriptions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuesubscription' => TYPE_ARRAY_INT,
		'what' => TYPE_STR
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	switch ($vbulletin->GPC['what'])
	{
		case 'unsubscribe':
		case 'none':
		case 'instant':
		case 'daily':
		case 'weekly':
			break;

		default:
			$vbulletin->GPC['what'] = 'none';
	}

	if ($vbulletin->GPC['issuesubscription'])
	{
		if ($vbulletin->GPC['what'] == 'unsubscribe')
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "pt_issuesubscribe
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND issueid IN (" . implode(',', $vbulletin->GPC['issuesubscription']) . ")
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issuesubscribe SET
					subscribetype = '" . $db->escape_string($vbulletin->GPC['what']) . "'
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND issueid IN (" . implode(',', $vbulletin->GPC['issuesubscription']) . ")
			");
		}
	}

	$vbulletin->url = 'project.php' . $vbulletin->session->vars['sessionurl_q'];
	eval(print_standard_redirect('pt_subscriptions_updated'));
}

// #######################################################################
if ($_POST['do'] == 'updateprojectsubscriptions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectsubscription' => TYPE_ARRAY_ARRAY,
		'what' => TYPE_STR
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	switch ($vbulletin->GPC['what'])
	{
		case 'unsubscribe':
		case 'none':
		case 'daily':
		case 'weekly':
			break;

		default:
			$vbulletin->GPC['what'] = 'none';
	}

	if ($vbulletin->GPC['what'] == 'unsubscribe')
	{
		foreach ($vbulletin->GPC['projectsubscription'] AS $projectid => $types)
		{
			$projectid = intval($projectid);

			foreach ($types AS $issuetypeid => $devnull)
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "pt_projecttypesubscribe
					WHERE userid = " . $vbulletin->userinfo['userid'] . "
						AND projectid = $projectid
						AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
				");
			}
		}
	}
	else
	{
		foreach ($vbulletin->GPC['projectsubscription'] AS $projectid => $types)
		{
			$projectid = intval($projectid);

			foreach ($types AS $issuetypeid => $devnull)
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "pt_projecttypesubscribe SET
						subscribetype = '" . $db->escape_string($vbulletin->GPC['what']) . "'
					WHERE userid = " . $vbulletin->userinfo['userid'] . "
						AND projectid = $projectid
						AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
				");
			}
		}
	}

	$vbulletin->url = 'project.php' . $vbulletin->session->vars['sessionurl_q'];
	eval(print_standard_redirect('pt_subscriptions_updated'));
}

// #######################################################################
if ($_REQUEST['do'] == 'managesubscriptions')
{
	$vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$perms_query = build_issue_permissions_query($vbulletin->userinfo);

	if (!$perms_query)
	{
		print_no_permission();
	}

	// issues per page = 0 means "unlmiited"
	if (!$vbulletin->options['pt_issuesperpage'])
	{
		$vbulletin->options['pt_issuesperpage'] = 999999;
	}

	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);

	build_issue_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

	$replycount_clause = fetch_private_replycount_clause($vbulletin->userinfo);

	// wrapping this in a do-while allows us to detect if someone goes to a page
	// that's too high and take them back to the last page seamlessly
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['pt_issuesperpage'];

		$subscriptions = $db->query_read("
			SELECT SQL_CALC_FOUND_ROWS issue.*, issuesubscribe.subscribetype
				" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
				" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
			FROM " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesubscribe.issueid)
			" . ($marking ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread as projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			" : '') . "
			$private_lastpost_join
			WHERE issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . "
				AND (" . implode(' OR ', $perms_query) . ")
			ORDER BY lastpost DESC
			LIMIT $start, " . $vbulletin->options['pt_issuesperpage']
		);

		list($issue_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
		if ($start >= $issue_count)
		{
			$vbulletin->GPC['pagenumber'] = ceil($issue_count / $vbulletin->options['pt_issuesperpage']);
		}
	}
	while ($start >= $issue_count AND $issue_count);

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$vbulletin->options['pt_issuesperpage'],
		$issue_count,
		'projectpost.php?' . $vbulletin->session->vars['sessionurl'] . "do=managesubscriptions",
		''
	);

	$subscriptionbits = '';

	while ($issue = $db->fetch_array($subscriptions))
	{
		$issue = prepare_issue($issue);
		$issue['notification'] = $vbphrase["$issue[subscribetype]"];

		($hook = vBulletinHook::fetch_hook('projectpost_subscription_bit')) ? eval($hook) : false;

		$templater = vB_Template::create('pt_subscriptionbit');
			$templater->register('issue', $issue);
		$subscriptionbits .= $templater->render();
	}

	// project subscriptions
	build_project_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

	$projecttype_subscriptions = $db->query_read("
		SELECT project.*, projecttype.*
			" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
			, projecttypesubscribe.subscribetype
		FROM " . TABLE_PREFIX . "pt_projecttypesubscribe AS projecttypesubscribe
		INNER JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON
			(projecttype.projectid = projecttypesubscribe.projectid AND projecttype.issuetypeid = projecttypesubscribe.issuetypeid)
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = projecttypesubscribe.projectid)
		INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuetype.issuetypeid = projecttypesubscribe.issuetypeid)
		$private_lastpost_join
		WHERE projecttypesubscribe.userid = " . $vbulletin->userinfo['userid'] . "
		ORDER BY project.displayorder, issuetype.displayorder
	");
	$project_subscriptionbits = '';

	while ($projecttype = $db->fetch_array($projecttype_subscriptions))
	{
		$issueperms = fetch_project_permissions($vbulletin->userinfo, $projecttype['projectid'], $projecttype['issuetypeid']);

		if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
		{
			continue;
		}

		$projecttype['issuetype'] = $vbphrase["issuetype_$projecttype[issuetypeid]_plural"];

		if ($typeicon = $vbulletin->pt_issuetype["$projecttype[issuetypeid]"]['iconfile'])
		{
			$projecttype['typeicon'] = $typeicon;
		}

		$show['private_lastpost'] = (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']) ? false : true);

		if ($projecttype['lastpost'])
		{
			$projecttype['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $projecttype['lastpost'], true);
			$projecttype['lastposttime'] = vbdate($vbulletin->options['timeformat'], $projecttype['lastpost']);
			$projecttype['lastissuetitle_short'] = fetch_trimmed_title(fetch_censored_text($projecttype['lastissuetitle']));
		}
		else
		{
			$projecttype['lastpostdate'] = '';
			$projecttype['lastposttime'] = '';
		}

		$projecttype['notification'] = $vbphrase["$projecttype[subscribetype]"];

		$templater = vB_Template::create('pt_project_subscriptionbit');
			$templater->register('projecttype', $projecttype);
		$project_subscriptionbits .= $templater->render();
	}

	require_once(DIR . '/includes/functions_user.php');
	construct_usercp_nav('ptsubscriptions');

	// navbar and output
	$navbits = construct_navbits(array(
		'usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['user_control_panel'],
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'' => $vbphrase['issue_subscriptions']
	));
	$navbar = render_navbar_template($navbits);

	($hook = vBulletinHook::fetch_hook('projectpost_subscription_complete')) ? eval($hook) : false;

	// shell template
	$templater = vB_Template::create('pt_managesubscriptions');
		$templater->register('pagenav', $pagenav);
		$templater->register('project_subscriptionbits', $project_subscriptionbits);
		$templater->register('subscriptionbits', $subscriptionbits);
	$HTML = $templater->render();
	$templater = vB_Template::create('USERCP_SHELL');
		$templater->register_page_templates();
		$templater->register('cpnav', $cpnav);
		$templater->register('HTML', $HTML);
		$templater->register('navbar', $navbar);
		$templater->register('navclass', $navclass);
		$templater->register('onload', $onload);
		$templater->register('pagetitle', $pagetitle);
		$templater->register('template_hook', $template_hook);
	print_output($templater->render());
}

// #######################################################################
if ($_POST['do'] == 'processpetition')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuenoteid' => TYPE_UINT,
		'confirm' => TYPE_ARRAY_STR,
		'ajax' => TYPE_BOOL
	));

	$issuenote = $db->query_first("
		SELECT issuenote.*, issuepetition.*
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		INNER JOIN " . TABLE_PREFIX . "pt_issuepetition AS issuepetition ON (issuepetition.issuenoteid = issuenote.issuenoteid)
		WHERE issuenote.issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
	");
	$issue = verify_issue($issuenote['issueid'], true, array('avatar', 'vote', 'milestone'));
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (!$posting_perms['status_edit'])
	{
		// if you can't edit the status, you can't process the petition
		print_no_permission();
	}

	if ($issuenote['resolution'] != 'pending')
	{
		standard_error(fetch_error('pt_petition_not_pending'));
	}

	$petitiondata =& datamanager_init('Pt_IssuePetition', $vbulletin, ERRTYPE_STANDARD);
	$petitiondata->set_existing($issuenote);
	$petitiondata->set('resolution', !empty($vbulletin->GPC['confirm']['yes']) ? 'accepted' : 'rejected');

	($hook = vBulletinHook::fetch_hook('projectpost_petition')) ? eval($hook) : false;

	$petitiondata->save();

	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');

		// determine which note types the browsing user can see
		$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
		$can_see_deleted = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage']);

		$show['reply_issue'] = $posting_perms['can_reply'];
		$show['edit_issue'] = $posting_perms['issue_edit'];

		// find total results for each type
		$notetype_counts = array(
			'user' => 0,
			'petition' => 0,
			'system' => 0
		);

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';

		$notetype_counts_query = $db->query_read("
			SELECT issuenote.type, COUNT(*) AS total
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			$hook_query_joins
			WHERE issuenote.issueid = $issue[issueid]
				AND issuenote.issuenoteid <> $issue[issuenoteid]
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

		// notes
		$note = $db->query_first("
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
			WHERE issuenote.issueid = $issuenote[issueid]
				AND issuenote.issuenoteid = $issuenote[issuenoteid]
				AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
				$type_filter
				$hook_query_where
			ORDER BY issuenote.dateline
		");

		require_once(DIR . '/includes/class_bbcode_pt.php');
		$bbcode = new vB_BbCodeParser_Pt($vbulletin, fetch_tag_list());

		require_once(DIR . '/includes/class_pt_issuenote.php');
		$factory = new vB_Pt_IssueNoteFactory();
		$factory->registry =& $vbulletin;
		$factory->bbcode =& $bbcode;
		$factory->issue =& $issue;
		$factory->project =& $project;
		$factory->browsing_perms = $issueperms;

		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_group('commentbits');

		$note_handler =& $factory->create($note);

		$xml->add_tag('message', process_replacement_vars($note_handler->construct($note)), array('issuenoteid' => $note['issuenoteid'], 'petition' => 1));

		$xml->close_group();
		$xml->print_xml(true);
	}
	else
	{
		$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "do=gotonote&amp;issuenoteid=$issuenote[issuenoteid]";
		eval(print_standard_redirect('pt_petition_processed'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'changeissuestate')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'fromstate' => TYPE_NOHTML,
		'securitytoken' => TYPE_NOHTML,
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (!$posting_perms['issue_close'])
	{
		print_no_permission();
	}

	switch ($vbulletin->GPC['fromstate'])
	{
		case 'open':
		case 'closed':
			break;

		default:
			$vbulletin->GPC['fromstate'] = 'open';
	}

	if ($vbulletin->userinfo['userid'] != 0 AND !verify_security_token($vbulletin->GPC['securitytoken'], $vbulletin->userinfo['securitytoken_raw']))
	{
		standard_error(fetch_error('pt_action_error',
			"projectpost.php?" . $vbulletin->session->vars['sessionurl']
				. "do=changeissuestate"
				. "&amp;issueid=$issue[issueid]"
				. "&amp;securitytoken=" . $vbulletin->userinfo['securitytoken']
				. "&amp;fromstate=" . $vbulletin->GPC['fromstate']
		));
	}

	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_STANDARD);
	$issuedata->set_info('project', $project);
	$issuedata->set_existing($issue);
	$issuedata->set('state', $vbulletin->GPC['fromstate'] == 'open' ? 'closed' : 'open');
	$issuedata->save();

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
	eval(print_standard_redirect('pt_issue_state_modified'));
}

// #######################################################################
if ($_REQUEST['do'] == 'changeissueprivacy')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'from' => TYPE_NOHTML,
		'securitytoken' => TYPE_NOHTML,
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (!$posting_perms['issue_edit'] OR !$posting_perms['private_edit'])
	{
		print_no_permission();
	}

	if ($issue['visible'] != 'visible' AND $issue['visible'] != 'private')
	{
		standard_error(fetch_error('invalidid', $vbphrase['issue'], $vbulletin->options['contactuslink']));
	}

	switch ($vbulletin->GPC['from'])
	{
		case 'private':
		case 'public':
			break;

		default:
			$vbulletin->GPC['from'] = 'public';
	}

	if ($vbulletin->userinfo['userid'] != 0 AND !verify_security_token($vbulletin->GPC['securitytoken'], $vbulletin->userinfo['securitytoken_raw']))
	{
		standard_error(fetch_error('pt_action_error',
			"projectpost.php?" . $vbulletin->session->vars['sessionurl']
				. "do=changeissueprivacy"
				. "&amp;issueid=$issue[issueid]"
				. "&amp;securitytoken=" . $vbulletin->userinfo['securitytoken']
				. "&amp;from=" . $vbulletin->GPC['from']
		));
	}

	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_STANDARD);
	$issuedata->set_existing($issue);
	$issuedata->set('visible', $vbulletin->GPC['from'] == 'public' ? 'private' : 'public');
	$issuedata->save();

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
	eval(print_standard_redirect('pt_issue_state_modified'));
}

// #######################################################################
if ($_POST['do'] == 'processmoveissue')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issueid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'priority' => TYPE_UINT,
		'projectcategoryid' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT,
		'issuestatusid' => TYPE_UINT,
		'milestoneid' => TYPE_UINT,
		'title' => TYPE_NOHTML,
		'summary' => TYPE_NOHTML,
		'appliedtags' => TYPE_ARRAY_NOHTML,
		'unappliedtags' => TYPE_ARRAY_NOHTML,
		'customtag' => TYPE_NOHTML,
		'submit_addtag' => TYPE_STR,
		'submit_removetag' => TYPE_STR,
		'assigned' => TYPE_ARRAY_UINT,
		'unassigned' => TYPE_ARRAY_UINT,
		'submit_assign' => TYPE_STR,
		'submit_unassign' => TYPE_STR,
		'assignself' => TYPE_BOOL,
		'private' => TYPE_BOOL,
		'close_issue' => TYPE_BOOL,
		'original_state' => TYPE_STR,
		'subscribetype' => TYPE_NOHTML,
	));

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);
	$new_project = verify_project($vbulletin->GPC['projectid']);

	verify_issuetypeid($vbulletin->GPC['issuetypeid'], $new_project['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$new_issueperms = fetch_project_permissions($vbulletin->userinfo, $new_project['projectid'], $vbulletin->GPC['issuetypeid']);

	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);
	$new_posting_perms = prepare_issue_posting_pemissions($issue, $new_issueperms);

	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmoveissue']))
	{
		print_no_permission();
	}

	// Check we can both view and post the target issue type
	if (!($new_issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($new_issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
	{
		print_no_permission();
	}

	if (($posting_perms['status_edit'] AND $new_posting_perms['status_edit']) AND $vbulletin->GPC['issuestatusid'])
	{
		// changing status - make sure the type is right
		$status = $vbulletin->pt_issuestatus[$vbulletin->GPC['issuestatusid']];

		if (!$status)
		{
			standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
		}
	}
	else
	{
		if ($issue['issuetypeid'] != $status['issuetypeid'])
		{
			// trying to change the type and we can't
			standard_error(fetch_error('invalidid', $vbphrase['issue_status'], $vbulletin->options['contactuslink']));
		}

		$vbulletin->GPC['issuestatusid'] = $issue['issuestatusid'];
	}

	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_STANDARD);
	$issuedata->set_existing($issue);
	$issuedata->set_info('perform_activity_updates', false);
	$issuedata->set_info('insert_change_log', false);

	$issuedata->set('title', $vbulletin->GPC['title']);
	$issuedata->set('summary', $vbulletin->GPC['summary']);
	$issuedata->set('issuestatusid', $vbulletin->GPC['issuestatusid']);
	$issuedata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	$issuedata->set('projectid', $vbulletin->GPC['projectid']);
	$issuedata->set('priority', $vbulletin->GPC['priority']);
	$issuedata->set('projectcategoryid', $vbulletin->GPC['projectcategoryid']);
	$issuedata->set('appliesversionid', $vbulletin->GPC['appliesversionid']);

	if ($posting_perms['status_edit'] AND $new_posting_perms['status_edit'])
	{
		$issuedata->set('issuestatusid', $vbulletin->GPC['issuestatusid']);
	}
	else if (!$issuedata->validate_issuestatusid($issue['issuestatusid']))
	{
		// status is no longer valid, but can't be edited. Reset to default.
		$issuedata->set('issuestatusid',
			$vbulletin->pt_projects[$vbulletin->GPC['projectid']]['types'][$vbulletin->GPC['issuetypeid']]
		);
	}

	if ($posting_perms['milestone_edit'])
	{
		$issuedata->set('milestoneid', $vbulletin->GPC['milestoneid']);
	}
	else if (!$issuedata->validate_milestoneid($issue['milestoneid']))
	{
		// milestone is no longer valid, but can't be edited. Reset to default.
		$issuedata->set('milestoneid', 0);
	}

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

	if ($posting_perms['private_edit'])
	{
		if ($vbulletin->GPC['private'])
		{
			// make it private
			$issuedata->set('visible', 'private');
		}
		else
		{
			// make it visible if it was private, else leave it as is
			$issuedata->set('visible', $issue['visible'] == 'private' ? 'visible': $issue['visible']);
		}
	}

	$existing_tags = array();

	// tags
	$tag_data = $db->query_read("
		SELECT tag.tagtext
		FROM " . TABLE_PREFIX . "pt_issuetag AS issuetag
		INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (issuetag.tagid = tag.tagid)
		WHERE issuetag.issueid = $issue[issueid]
		ORDER BY tag.tagtext
	");
	while ($tag = $db->fetch_array($tag_data))
	{
		$existing_tags[] = $tag['tagtext'];
	}

	// assignments
	$assignment_data = $db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
		WHERE issueassign.issueid = $issue[issueid]
		ORDER BY user.username
	");

	while ($assignment = $db->fetch_array($assignment_data))
	{
		$existing_assignments["$assignment[userid]"] = $assignment['userid'];
	}

	// prepare tag changes
	if ($posting_perms['tags_edit'])
	{
		$issuedata->set_info('allow_tag_creation', $posting_perms['can_custom_tag']);
		prepare_tag_changes($vbulletin->GPC, $existing_tags, $tag_add, $tag_remove);

		foreach ($tag_add AS $tag)
		{
			$issuedata->add_tag($tag);
		}

		foreach ($tag_remove AS $tag)
		{
			$issuedata->remove_tag($tag);
		}
	}

	$issuedata->save();

	$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=$issue[issueid]";
	eval(print_standard_redirect('pt_issue_state_modified'));
}

// #######################################################################
if ($_POST['do'] == 'moveissue2')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issueid' => TYPE_UINT,
		'project-issuetype' => TYPE_NOHTML,
	));

	list($projectid, $issuetypeid) = explode('-', $vbulletin->GPC['project-issuetype']);

	$issue = verify_issue($vbulletin->GPC['issueid'], true, array('milestone'));
	$project = verify_project($issue['projectid']);
	$new_project = verify_project($projectid);

	verify_issuetypeid($issuetypeid, $new_project['projectid']);

	$new_issuetype = $vbphrase["issuetype_{$issuetypeid}_singular"];

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$new_issueperms = fetch_project_permissions($vbulletin->userinfo, $new_project['projectid'], $issuetypeid);

	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);
	$new_posting_perms = prepare_issue_posting_pemissions($issue, $new_issueperms);

	$show['status_edit'] = ($posting_perms['status_edit'] AND $new_posting_perms['status_edit']);
	$show['tags_edit'] = ($posting_perms['tags_edit'] AND $new_posting_perms['tags_edit']);
	$show['can_custom_tag'] = ($posting_perms['can_custom_tag'] AND $new_posting_perms['can_custom_tag']);
	$show['assign_checkbox'] = ($posting_perms['assign_checkbox'] AND $new_posting_perms['assign_checkbox']);
	$show['assign_dropdown'] = ($posting_perms['assign_dropdown'] AND $new_posting_perms['assign_dropdown']);

	$show['private_edit'] = $posting_perms['private_edit'];
	$assign_checkbox_checked = $posting_perms['assign_checkbox_checked'];

	$show['close_issue'] = $posting_perms['issue_close'];
	$close_issue_checked = ($issue['state'] == 'closed' ? ' checked="checked"' : '');

	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmoveissue']))
	{
		print_no_permission();
	}

	// Check we can both view and post the target issue type
	if (!($new_issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($new_issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
	{
		print_no_permission();
	}

	// categories
	$category_options = array();

	foreach ($vbulletin->pt_categories AS $category)
	{
		$option = array();

		if ($category['projectid'] != $new_project['projectid'])
		{
			continue;
		}

		$option['title'] = $vbphrase['category' . $category['projectcategoryid'] . ''];
		$option['value'] = $category['projectcategoryid'];
		$option['selected'] = ($issue['projectcategoryid'] == $category['projectcategoryid'] ? ' selected="selected"' : '');

		$category_options[] = $option;
	}

	$category_unknown_selected = ($issue['projectcategoryid'] == 0 ? ' selected="selected"' : '');

	// setup priority options
	$priority = $priority_options = array();

	$priorities = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = " . $project['projectid'] . "
	");

	while ($prioritys = $db->fetch_array($priorities))
	{
		$priority["$prioritys[projectpriorityid]"] = $prioritys;
	}

	foreach ($priority AS $optionvalue => $options)
	{
		$option = array();

		$option['title'] = $vbphrase['priority' . $optionvalue . ''];
		$option['value'] = $optionvalue;
		$option['selected'] = ($issue['priority'] == $optionvalue ? ' selected="selected"' : '');

		$priority_options[] = $option;
	}

	// setup versions
	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
		INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = $new_project[projectid]
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$applies_versions = array();
	$addressed_versions = array();

	foreach ($version_groups AS $optgroup_label => $versions)
	{
		$option = array();
		$optiongroup = array();
		$group_applies = array();
		$group_addressed = array();

		foreach ($versions AS $optionvalue => $optiontitle)
		{
			$option['title'] = $optiontitle;
			$option['value'] = $optionvalue;
			$option['selected'] = ($issue['appliesversionid'] == $optionvalue ? ' selected="selected"' : '');

			$group_applies[] = $option;

			$option['selected'] = (($issue['isaddressed'] AND $issue['addressedversionid'] == $optionvalue) ? ' selected="selected"' : '');

			$group_addressed[] = $option;
		}

		$optiongroup['group'] = $group_applies;
		$optiongroup['label'] = $optgroup_label;

		$applies_versions[] = $optiongroup;

		$optiongroup['group'] = $group_addressed;

		$addressed_versions[] = $optiongroup;
	}

	$applies_unknown_selected = ($issue['appliesversionid'] == 0 ? ' selected="selected"' : '');
	$addressed_unaddressed_selected = ($issue['isaddressed'] == 0 ? ' selected="selected"' : '');
	$addressed_next_selected = (($issue['isaddressed'] == 1 AND $issue['addressedversionid'] == 0) ? ' selected="selected"' : '');

	// set up appliable tags
	$unapplied_tags = $applied_tags = $option = array();

	$tag_data = $db->query_read("
		SELECT tag.tagtext, IF(issuetag.tagid IS NOT NULL, 1, 0) AS isapplied
		FROM " . TABLE_PREFIX . "pt_tag AS tag
		LEFT JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag ON (issuetag.tagid = tag.tagid AND issuetag.issueid = $issue[issueid])
		ORDER BY tag.tagtext
	");

	while ($tag = $db->fetch_array($tag_data))
	{
		$option['title'] = $option['value'] = $tag['tagtext'];

		if ($tag['isapplied'])
		{
			unset($preview_tags["$tag[tagtext]"]);
			$applied_tags[] = $option;
		}
		else
		{
			$unapplied_tags[] = $option;
		}
	}

	// status
	$status_options = build_issuestatus_select($vbulletin->pt_issuetype["$issuetypeid"]['statuses'], $issue['issuestatusid']);

	// setup default subscribe type
	if ($issue['subscribetype'] === NULL)
	{
		switch ($vbulletin->userinfo['autosubscribe'])
		{
			case -1: $issue['subscribetype'] = ''; break;
			case 0: $issue['subscribetype'] = 'none'; break;
			case 1: $issue['subscribetype'] = 'instant'; break;
			case 2: $issue['subscribetype'] = 'daily'; break;
			case 3: $issue['subscribetype'] = 'weekly'; break;
			default: $issue['subscribetype'] = ''; break;
		}
	}

	$subscribe_selected = array(
		'donot' => ($issue['subscribetype'] == '' ? ' selected="selected"' : ''),
		'none' => ($issue['subscribetype'] == 'none' ? ' selected="selected"' : ''),
		'instant' => ($issue['subscribetype'] == 'instant' ? ' selected="selected"' : ''),
		'daily' => ($issue['subscribetype'] == 'daily' ? ' selected="selected"' : ''),
		'weekly' => ($issue['subscribetype'] == 'weekly' ? ' selected="selected"' : ''),
	);

	$show['subscribe_option'] = ($vbulletin->userinfo['userid'] > 0);

	// setup milestones
	$show['milestone'] = ($new_issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'] AND $project['milestonecount']);
	$show['milestone_edit'] = ($show['milestone'] AND $new_posting_perms['milestone_edit']);

	if (!empty($vbulletin->GPC['milestoneid']) AND empty($issue['milestoneid']))
	{
		$issue['milestoneid'] = $vbulletin->GPC['milestoneid'];
	}

	$milestone_options = fetch_milestone_select($new_project['projectid'], $issue['milestoneid']);

	// set up assignable users
	$assigned_user_list = array();

	// assignments
	$assignments = '';

	$assignment_data = $db->query_read("
		SELECT user.userid
		FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
		WHERE issueassign.issueid = $issue[issueid]
		ORDER BY user.username
	");

	while ($assignment = $db->fetch_array($assignment_data))
	{
		$assigned_user_list["$assignment[userid]"] = $assignment['userid'];
	}

	$unassigned_users = $assigned_users = $option = array();

	foreach ($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"] AS $optionvalue => $optiontitle)
	{
		$option['title'] = $optiontitle;
		$option['value'] = $optionvalue;

		if (isset($assigned_user_list["$optionvalue"]))
		{
			$assigned_users[] = $option;
		}
		else
		{
			$unassigned_users[] = $option;
		}
	}

	$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_$issuetypeid"];
	$vbphrase['addressed_version_issuetype'] = $vbphrase["addressed_version_$issuetypeid"];

	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
		'' => $vbphrase['move_issue']
	);

	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);

	$templater = vB_Template::create('pt_move_issue_confirm');
		$templater->register_page_templates();
		$templater->register('addressed_next_selected', $addressed_next_selected);
		$templater->register('addressed_unaddressed_selected', $addressed_unaddressed_selected);
		$templater->register('addressed_versions', $addressed_versions);
		$templater->register('applies_unknown_selected', $applies_unknown_selected);
		$templater->register('applies_versions', $applies_versions);
		$templater->register('assign_checkbox_checked', $assign_checkbox_checked);
		$templater->register('assigned_users', $assigned_users);
		$templater->register('category_options', $category_options);
		$templater->register('category_unknown_selected', $category_unknown_selected);
		$templater->register('close_issue_checked', $close_issue_checked);
		$templater->register('issue', $issue);
		$templater->register('issuetypeid', $issuetypeid);
		$templater->register('milestone_options', $milestone_options);
		$templater->register('navbar', $navbar);
		$templater->register('new_issuetype', $new_issuetype);
		$templater->register('new_project', $new_project);
		$templater->register('priority_options', $priority_options);
		$templater->register('status_options', $status_options);
		$templater->register('subscribe_selected', $subscribe_selected);
		$templater->register('unassigned_users', $unassigned_users);
	print_output($templater->render());

}

// #######################################################################
if ($_REQUEST['do'] == 'moveissue')
{
	$vbulletin->input->clean_gpc('r', 'issueid', TYPE_UINT);

	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmoveissue']))
	{
		print_no_permission();
	}

	$project_type_select = array();

	foreach ($vbulletin->pt_projects AS $projectid => $projectinfo)
	{
		$project_perms["$projectid"] = fetch_project_permissions($vbulletin->userinfo, $projectid);

		$optgroup = $optiongroup = $option = array();

		foreach (array_keys($projectinfo['types']) AS $type)
		{
			// Check we can both view and post the target issue type
			if (!($project_perms["$projectid"]["$type"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($project_perms["$projectid"]["$type"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
			{
				continue;
			}

			$option['value'] = $projectinfo['projectid'] . '-' . $type;
			$option['title'] = $vbphrase["issuetype_{$type}_singular"];
			$option['selected'] = (($issue['issuetypeid'] == $type AND $issue['projectid'] == $projectid) ? ' selected="selected"' : '');

			$optgroup[] = $option;
		}

		if (empty($optgroup))
		{
			continue;
		}

		$optiongroup['label'] = $projectinfo['title'];
		$optiongroup['group'] = $optgroup;

		$project_type_select[] = $optiongroup;
	}

	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title_clean'],
		fetch_seo_url('issue', $issue) => $issue['title'],
		'' => $vbphrase['edit_issue']
	);

	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);

	$templater = vB_Template::create('pt_move_issue');
		$templater->register_page_templates();
		$templater->register('issue', $issue);
		$templater->register('navbar', $navbar);
		$templater->register('project', $project);
		$templater->register('project_type_select', $project_type_select);
	print_output($templater->render());
}

// ##################################################################################
if ($_REQUEST['do'] == 'assigntoself')
{
	$vbulletin->input->clean_gpc('r', 'issueid', TYPE_UINT);
		
	$existing_assignments = array();
	$userid = $vbulletin->userinfo['userid'];
	
	$issue = verify_issue($vbulletin->GPC['issueid']);
	$project = verify_project($issue['projectid']);
	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);
	
	if (!isset($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"]["$userid"]))
	{
		print_no_permission();
	}
	else
	{
		$assignment_data = $db->query_read("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
			WHERE issueassign.issueid = $issue[issueid]
			ORDER BY user.username
		");

		while ($assignment = $db->fetch_array($assignment_data))
		{
			$existing_assignments["$assignment[userid]"] = $assignment['userid'];
		}
		
		if (in_array($vbulletin->userinfo['userid'], $existing_assignments))
		{
			// Already assigned, an error occured somewhere to show the "Assign to Me" link. Just perform a return to previous issue.
			$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=" . $issue['issueid'];
			eval(print_standard_redirect($vbulletin->phrase['pt_already_assigned']));
		}
		else
		{
			// Not already in the assigned users, add them now.
			if (!isset($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"]["$userid"]))
			{
				// user cannot be assigned
				$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=" . $issue['issueid'];
				eval(print_standard_redirect($vbulletin->phrase['pt_cannont_be_assigned']));
			}
			else
			{
				$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
				$assign->set_info('project', $project);
				$assign->set('userid', $userid);
				$assign->set('issueid', $issue['issueid']);
				$assign->save();

				$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=" . $issue['issueid'];
				eval(print_standard_redirect($vbulletin->phrase['pt_assigned']));
			}
		}
	}
}

// #######################################################################
if (in_array($_REQUEST['do'], array('processexport', 'export', 'confirmexport')))
{
	// Allowed to be here?
	if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canexportfromissues']))
	{
		print_no_permission();
	}
}

// #######################################################################
if ($_POST['do'] == 'processexport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'contenttype' => TYPE_NOHTML,
		'issuenoteid' => TYPE_UINT,
		'issueid' => TYPE_UINT,
		// Specific data
		'destforumid' => TYPE_UINT,
		'title' => TYPE_NOHTML,
		'mergethreadurl' => TYPE_STR,
		'posthash' => TYPE_NOHTML,
		'poststarttime' => TYPE_UINT
	));

	// If issueid is specified, export the full issue
	// If issuenoteid is specified, export the issue note only
	// Issueid and issuenoteid can't be specified at the same time.
	if ($vbulletin->GPC['issueid'] AND $vbulletin->GPC['issuenoteid'])
	{
		eval(standard_error(fetch_error('pt_issue_issuenote_cant_defined_same_time')));
	}

	if ($vbulletin->GPC['issuenoteid'])
	{
		// Request issue infos from issuenote
		$issuenote = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
		");
	}
	else
	{
		// Request issue infos from issuenote
		$issuenote = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issueid = " . $vbulletin->GPC['issueid'] . "
		");
	}

	$issue = verify_issue($issuenote['issueid']);
	$project = verify_project($issue['projectid']);

	require_once(DIR . '/includes/functions_pt_impex.php');
	require_once(DIR . '/includes/class_pt_impex.php');
	require_once(DIR . '/includes/functions_threadmanage.php');

	switch ($vbulletin->GPC['contenttype'])
	{
		case 'thread':
			$datatype = 'thread';

			// Move to new thread
			if (empty($vbulletin->GPC['title']))
			{
				eval(standard_error(fetch_error('notitle')));
			}

			// check whether dest can contain posts
			$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
			$destforuminfo = fetch_foruminfo($destforumid);
			if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
			{
				eval(standard_error(fetch_error('moveillegalforum')));
			}

			// check destination forum permissions
			$forumperms = fetch_permissions($destforuminfo['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				print_no_permission();
			}

			$datainfo = array(
				'forumid' => $destforumid,
				'posthash' => $vbulletin->GPC['posthash'],
				'poststarttime' => $vbulletin->GPC['poststarttime'],
				'title' => $vbulletin->GPC['title'],
				'issueid' => $issue['issueid'],
				'userid' => $issuenote['userid'],
				'pagetext' => $issuenote['pagetext'],
				'dateline' => $issuenote['dateline']
			);

			break;

		case 'post':
			$datatype = 'post';

			// Validate destination thread
			$destthreadid = extract_threadid_from_url($vbulletin->GPC['mergethreadurl']);

			if (!$destthreadid)
			{
				// Invalid URL
				eval(standard_error(fetch_error('mergebadurl')));
			}

			$destthreadid = verify_id('thread', $destthreadid);
			$destthreadinfo = fetch_threadinfo($destthreadid);
			$destforuminfo = fetch_foruminfo($destthreadinfo['forumid']);

			$forumperms = fetch_permissions($destforuminfo['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				print_no_permission();
			}

			if ($destthreadinfo['open'] == 10)
			{
				if (can_moderate($destthreadinfo['forumid']))
				{
					eval(standard_error(fetch_error('mergebadurl')));
				}
				else
				{
					eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
				}
			}

			if (($destthreadinfo['isdeleted'] AND !can_moderate($destthreadinfo['forumid'], 'candeleteposts')) OR (!$destthreadinfo['visible'] AND !can_moderate($destthreadinfo['forumid'], 'canmoderateposts')))
			{
				if (can_moderate($destthreadinfo['forumid']))
				{
					print_no_permission();
				}
				else
				{
					eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
				}
			}

			// allow merging only in forums this user can moderate - otherwise, they
			// have a good vector for faking posts in other forums, etc
			if (!can_moderate($destthreadinfo['forumid']))
			{
				eval(standard_error(fetch_error('move_posts_moderated_forums_only')));
			}

			if (!can_moderate($destforumid, 'canmanagethreads'))
			{
				eval(standard_error(fetch_error('you_do_not_have_permission_to_manage_threads_and_posts', $issue['title'], $issue['title'], $vbulletin->forumcache["$destforumid"]['title'])));
			}

			$datainfo = array(
				'threadid' => $destthreadid,
				'forumid' => $destforumid,
				'posthash' => $vbulletin->GPC['posthash'],
				'poststarttime' => $vbulletin->GPC['poststarttime'],
				'title' => $vbulletin->GPC['title'],
				'issueid' => $issue['issueid'],
				'userid' => $issuenote['userid'],
				'pagetext' => $issuenote['pagetext'],
				'dateline' => $issuenote['dateline']
			);

			break;
		case 'issuethread':
			$datatype = 'issuethread';

			// Move to new thread
			if (empty($vbulletin->GPC['title']))
			{
				eval(standard_error(fetch_error('notitle')));
			}

			// check whether dest can contain posts
			$destforumid = verify_id('forum', $vbulletin->GPC['destforumid']);
			$destforuminfo = fetch_foruminfo($destforumid);
			if (!$destforuminfo['cancontainthreads'] OR $destforuminfo['link'])
			{
				eval(standard_error(fetch_error('moveillegalforum')));
			}

			// check destination forum permissions
			$forumperms = fetch_permissions($destforuminfo['forumid']);
			if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				print_no_permission();
			}

			$datainfo = array(
				'forumid' => $destforumid,
				'posthash' => $vbulletin->GPC['posthash'],
				'poststarttime' => $vbulletin->GPC['poststarttime'],
				'title' => $vbulletin->GPC['title'],
				'issueid' => $issue['issueid'],
				'userid' => $issuenote['userid'],
				'pagetext' => $issuenote['pagetext'],
				'dateline' => $issuenote['dateline'],
				'issuenoteid' => $issue['issuenoteid']
			);

			break;
	}

	// Do the export
	$exporter = new vB_Pt_Export_Factory();

	$exporter->registry =& $vbulletin;
	$exporter->datatype =& $datatype;
	$exporter->datainfo =& $datainfo;
	$exporter->project =& $project;
	$exporter->posting_perms =& $posting_perms;

	$exportdata = $exporter->fetch_export($datatype);

	$contentid = $exportdata->export_all();
	
	// Getting the correct redirect URL
	switch ($vbulletin->GPC['contenttype'])
	{
		case 'post':
			$threadinfo = $db->query_first("
				SELECT t.threadid, t.title
				FROM " . TABLE_PREFIX . "post AS p
					LEFT JOIN " . TABLE_PREFIX . "thread AS t ON (p.threadid = t.threadid)
				WHERE p.postid = " . $contentid . "
			");

			$url = fetch_seo_url('thread', $threadinfo) . '#post' . $contentid;
			break;
		case 'thread':
			$threadinfo = verify_id('thread', $contentid, 0, 1);
			$url = fetch_seo_url('thread', $threadinfo);
			break;
		case 'issuethread':
			$threadinfo = verify_id('thread', $contentid, 0, 1);
			$url = fetch_seo_url('thread', $threadinfo);
			break;
	}

	$vbulletin->url = $url;
	eval(print_standard_redirect('pt_issue_exported'));
}

// #######################################################################
if ($_POST['do'] == 'confirmexport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'issuenoteid' => TYPE_UINT,
		'issueid' => TYPE_UINT,
		'contenttype' => TYPE_NOHTML
	));

	// If issueid is specified, export the full issue
	// If issuenoteid is specified, export the issue note only
	// Issueid and issuenoteid can't be specified at the same time.
	if ($vbulletin->GPC['issueid'] AND $vbulletin->GPC['issuenoteid'])
	{
		eval(standard_error(fetch_error('pt_issue_issuenote_cant_defined_same_time')));
	}

	if ($vbulletin->GPC['issuenoteid'])
	{
		// Get the issueid from the issuenoteid
		$issueinfo = $db->query_first("
			SELECT issueid
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
		");

		$issueid = $issueinfo['issueid'];
	}
	else
	{
		$issueid = $vbulletin->GPC['issueid'];
	}

	$issue = verify_issue($issueid);
	$project = verify_project($issue['projectid']);

	if ($vbulletin->GPC['issuenoteid'])
	{
		$issue['issuenoteid'] = $vbulletin->GPC['issuenoteid'];
	}

	$poststarttime = TIMENOW;
	$posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);

	require_once(DIR . '/includes/functions_threadmanage.php');
	$curforumid = '-1';
	$moveforumbits = construct_move_forums_options();

	// Display the corresponding template from the previous selected content type
	switch (strtolower($vbulletin->GPC['contenttype']))
	{
		case 'post':
			$templater = vB_Template::create('pt_export_content_post');
				$templater->register('contenttype', $vbulletin->GPC['contenttype']);
				$templater->register('issuenoteid', $issue['issuenoteid']);
				$templater->register('posthash', $posthash);
				$templater->register('poststarttime', $poststarttime);
			$HTML = $templater->render();
			break;
		case 'thread':
			$templater = vB_Template::create('pt_export_content_thread');
				$templater->register('moveforumbits', $moveforumbits);
				$templater->register('title', $issue['title']);
				$templater->register('contenttype', $vbulletin->GPC['contenttype']);
				$templater->register('issuenoteid', $issue['issuenoteid']);
				$templater->register('posthash', $posthash);
				$templater->register('poststarttime', $poststarttime);
			$HTML = $templater->render();
			break;
		case 'issuethread':
			$templater = vB_Template::create('pt_export_content_thread');
				$templater->register('moveforumbits', $moveforumbits);
				$templater->register('title', $issue['title']);
				$templater->register('contenttype', $vbulletin->GPC['contenttype']);
				$templater->register('issueid', $issue['issueid']);
				$templater->register('posthash', $posthash);
				$templater->register('poststarttime', $poststarttime);
			$HTML = $templater->render();
			break;
		default:
			break;
	}

	// Navbits
	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title'],
		'' => $vbphrase['export_issue']
	);

	$navbar = render_navbar_template(construct_navbits($navbits));

	$templater = vB_Template::create('pt_export_content_confirm');
		$templater->register_page_templates();
		$templater->register('HTML', $HTML);
		$templater->register('navbar', $navbar);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'export')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'issuenoteid' => TYPE_UINT,
		'issueid' => TYPE_UINT
	));

	// If issueid is specified, export the full issue
	// If issuenoteid is specified, export the issue note only
	// Issueid and issuenoteid can't be specified at the same time.
	if ($vbulletin->GPC['issueid'] AND $vbulletin->GPC['issuenoteid'])
	{
		eval(standard_error(fetch_error('pt_issue_issuenote_cant_defined_same_time')));
	}

	if (!$vbulletin->GPC['issueid'])
	{
		$issueinfo = $db->query_first("
			SELECT issueid
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . $vbulletin->GPC['issuenoteid'] . "
		");

		$issueid = $issueinfo['issueid'];
	}
	else
	{
		$issueid = $vbulletin->GPC['issueid'];
	}

	$issue = verify_issue($issueid);
	$project = verify_project($issue['projectid']);

	if ($vbulletin->GPC['issuenoteid'])
	{
		$issue['issuenoteid'] = $vbulletin->GPC['issuenoteid'];

		$contenttypes = array(
			'post',
			'thread'
		);
	}
	else
	{
		$contenttypes = array(
			'issuethread'
		);
	}

	$content_type_select = array();

	foreach ($contenttypes AS $contenttype)
	{
		$option['title'] = $vbphrase['export_issue_' . $contenttype . ''];
		$option['value'] = $contenttype;
		$content_type_select[] = $option;
	}

	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		fetch_seo_url('project', $project) => $project['title'],
		'' => $vbphrase['export_issue']
	);

	$navbar = render_navbar_template(construct_navbits($navbits));

	$templater = vB_Template::create('pt_export_content');
		$templater->register_page_templates();
		$templater->register('content_type_select', $content_type_select);
		$templater->register('navbar', $navbar);
		$templater->register('issue', $issue);
	print_output($templater->render());
}

// #######################################################################
if (in_array($_REQUEST['do'], array('processimportcontent', 'importcontent', 'importcontent2')))
{
	// Allowed to be here?
	if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canimportintoissues']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'issuenote' => TYPE_UINT,
	));

	if (
		!$vbulletin->GPC['issuenoteid']
	)
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'issuenote' => TYPE_UINT,
		));
	}

	require_once(DIR . '/includes/functions_pt_impex.php');
	$datainfo = array();

	if ($threadid)
	{	
		$threadinfo = verify_id('thread', $threadid, 1, 1);
		$foruminfo = verify_id('forum', $threadinfo['forumid']);
	}

	if ($postid)
	{
		$threadinfo = verify_id('thread', $threadid, 1, 1);
		$foruminfo = verify_id('forum', $threadinfo['forumid']);
		$postinfo = verify_id('post', $postid, 1, 1);
	}

	if ($vbulletin->GPC['issuenote'])
	{
		$issuenoteinfo = $db->query_first("
			SELECT note.*, issue.title, issue.summary, issue.issueid
			FROM " . TABLE_PREFIX . "pt_issuenote AS note
				LEFT JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = note.issueid)
			WHERE note.issuenoteid = " . intval($vbulletin->GPC['issuenote']) . "
		");

		$issue = verify_issue($issuenoteinfo['issueid']);
		$project = verify_project($issue['projectid']);
	}
}

// #######################################################################
if ($_POST['do'] == 'processimportcontent')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'projectid' => TYPE_UINT,
		'issuetypeid' => TYPE_NOHTML,
		'title' => TYPE_NOHTML,
		'summary' => TYPE_NOHTML,
		'priority' => TYPE_UINT,
		'projectcategoryid' => TYPE_UINT,
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT,
		'issuestatusid' => TYPE_UINT,
		'milestoneid' => TYPE_UINT,
		'type' => TYPE_NOHTML,
		'originaltitle' => TYPE_NOHTML,
		'private' => TYPE_BOOL,
	));

	// Do our own checking to make sure we have all permissions needed to create issues
	$project = ptimporter_verify_issuetypeid($vbulletin->GPC['issuetypeid'], $vbulletin->GPC['projectid']);
	$posting_perms = ptimporter_prepare_issue_posting_pemissions($project['projectid'], $vbulletin->GPC['issuetypeid']);
	
	// Make sure the new issue status is valid
	ptimporter_verify_issuestatusid($vbulletin->GPC['issuestatusid'], $vbulletin->GPC['issuetypeid']);

	// Finally, run the import
	require_once(DIR . '/includes/class_pt_impex.php');

	switch ($vbulletin->GPC['type'])
	{
		// $datainfo['title'] corresponds to the original title from which the content is imported from
		case 'thread':
			$datatype = 'thread';
			$datainfo = $threadinfo;
			$datainfo['id'] = $datainfo['threadid'];
			$datainfo['contenttypeid'] = vB_Types::instance()->getContentTypeID('vBForum_Post'); // Attachments are linked to posts, not threads
			break;
		case 'post':
			$datatype = 'post';
			$datainfo = $postinfo;
			$datainfo['id'] = $datainfo['postid'];
			$datainfo['contenttypeid'] = vB_Types::instance()->getContentTypeID('vBForum_Post');
			$datainfo['title'] = ($postinfo['title'] ? $postinfo['title'] : $threadinfo['title']);
			break;
		case 'issuenote':
			$datatype = 'issuenote';
			$datainfo = $issuenoteinfo;
			$datainfo['id'] = $datainfo['issueid'];
			$datainfo['contenttypeid'] = vB_Types::instance()->getContentTypeID('vBProjectTools_Issue'); // Attachments are linked to issues, not issuenotes
			$datainfo['title'] = $issuenoteinfo['title'];
			break;
	}

	$datainfo['originaltitle'] = $vbulletin->GPC['originaltitle'];

	// Do the import
	$importer = new vB_Pt_Import_Factory();

	$importer->registry =& $vbulletin;
	$importer->datatype =& $datatype;
	$importer->datainfo =& $datainfo;
	$importer->project =& $project;
	$importer->posting_perms =& $posting_perms;

	$importdata = $importer->fetch_import($datatype);

	$return = $importdata->import_all();

	if (is_array($return))
	{
		define('PTIPREVIEW', 1);
		require_once(DIR . '/includes/functions_newpost.php');
		$preview = construct_errors($return);

		$_POST['do'] = 'importcontent2';
	}
	else
	{
		$vbulletin->url = 'issue.php?' . $vbulletin->session->vars['sessionurl'] . "issueid=" . $return;
		eval(print_standard_redirect('pt_issue_inserted'));
	}
}

// #######################################################################
if ($_POST['do'] == 'importcontent2')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'project-issuetype' => TYPE_NOHTML,
		'type' => TYPE_NOHTML,
		'preview' => TYPE_NOHTML,
	));

	if ((($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canimportintoissues']) AND $foruminfo['canimportintoissues']))
	{
		print_no_permission();
	}

	list($projectid, $issuetypeid) = explode('-', $vbulletin->GPC['project-issuetype']);

	$project = verify_project($projectid);
	$posting_perms = ptimporter_prepare_issue_posting_pemissions($project['projectid'], $issuetypeid);

	$show['status_edit'] = ($posting_perms['status_edit']);
	$show['tags_edit'] = ($posting_perms['tags_edit']);
	$show['can_custom_tag'] = ($posting_perms['can_custom_tag']);
	$show['assign_checkbox'] = ($posting_perms['assign_checkbox']);
	$show['assign_dropdown'] = ($posting_perms['assign_dropdown']);

	$show['private_edit'] = $posting_perms['private_edit'];
	$assign_checkbox_checked = $posting_perms['assign_checkbox_checked'];

	$show['close_issue'] = $posting_perms['issue_close'];
	$close_issue_checked = ($issue['state'] == 'closed' ? ' checked="checked"' : '');

	verify_issuetypeid($issuetypeid, $project['projectid']);

	$issuetype = $vbphrase["issuetype_{$issuetypeid}_singular"];

	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issuetypeid);

	// Check we can both view and post the target issue type
	if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
	{
		print_no_permission();
	}

	// setup for preview display
	if (defined('PTIPREVIEW'))
	{
		$postpreview =& $preview;
	}

	// setup priorities
	$priority_array = $priority_options = array();

	$priorities = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = " . intval($project['projectid']) . "
	");

	while ($priority = $db->fetch_array($priorities))
	{
		$priority_array["$priority[projectpriorityid]"] = $priority;
	}

	foreach ($priority_array AS $optionvalue => $options)
	{
		$option = array();

		$option['title'] = $vbphrase['priority' . $optionvalue . ''];
		$option['value'] = $optionvalue;

		$priority_options[] = $option;
	}

	// categories
	$category_options = array();

	foreach ($vbulletin->pt_categories AS $category)
	{
		$option = array();

		if ($category['projectid'] != $project['projectid'])
		{
			continue;
		}

		$option['title'] = $vbphrase['category' . $category['projectcategoryid'] . ''];
		$option['value'] = $category['projectcategoryid'];

		$category_options[] = $option;
	}

	$category_unknown_selected = ' selected="selected"';

	// setup versions
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

	$applies_versions = $addressed_versions = array();

	foreach ($version_groups AS $optgroup_label => $versions)
	{
		$group_applies = $group_addressed = array();

		foreach ($versions AS $optionvalue => $optiontitle)
		{
			$option['title'] = $optiontitle;
			$option['value'] = $optionvalue;

			$group_applies[] = $option;
			$group_addressed[] = $option;
		}

		$optiongroup['label'] = $optgroup_label;
		$optiongroup['group'] = $group_applies;

		$applies_versions[] = $optiongroup;

		$optiongroup['group'] = $group_addressed;

		$addressed_versions[] = $optiongroup;
	}

	$applies_unknown_selected = '';
	$addressed_unaddressed_selected = '';
	$addressed_next_selected = '';

	// set up appliable tags
	$unapplied_tags = $applied_tags = $option = array();

	$tag_data = $db->query_read("
		SELECT
			tag.tagtext
			" . ($vbulletin->GPC['type'] == 'issuenote' ? ", IF(issuetag.tagid IS NOT NULL, 1, 0) AS isapplied" : '') . "
		FROM " . TABLE_PREFIX . "pt_tag AS tag
			" . ($vbulletin->GPC['type'] == 'issuenote' ? "LEFT JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag ON (issuetag.tagid = tag.tagid AND issuetag.issueid = $issue[issueid])" : '') . "
		ORDER BY tag.tagtext
	");

	while ($tag = $db->fetch_array($tag_data))
	{
		$option['title'] = $option['value'] = $tag['tagtext'];

		if ($vbulletin->GPC['type'] == 'issuenote')
		{
			if ($tag['isapplied'])
			{
				$applied_tags[] = $option;
			}
			else
			{
				$unapplied_tags[] = $option;
			}
		}
		else
		{
			$applied_tags[] = $option;
		}
	}

	// status
	// Select the default status from the corresponding project / issue type
	$issuestatus = $db->query_first("
		SELECT startstatusid
		FROM " . TABLE_PREFIX . "pt_projecttype
		WHERE projectid = " . intval($project['projectid']) . "
			AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
	");

	if ($posting_perms['status_edit'])
	{
		// We can select the status
		$status_options = build_issuestatus_select($vbulletin->pt_issuetype["$issuetypeid"]['statuses'], $issuestatus['startstatusid']);
	}
	else
	{
		// We can't select the status - display only the default status
		$status_options = $vbphrase["issuestatus" . $issuestatus['startstatusid'] . ""];
	}

	// setup default subscribe type
	if ($issue['subscribetype'] === NULL)
	{
		switch ($vbulletin->userinfo['autosubscribe'])
		{
			case -1: $issue['subscribetype'] = ''; break;
			case 0: $issue['subscribetype'] = 'none'; break;
			case 1: $issue['subscribetype'] = 'instant'; break;
			case 2: $issue['subscribetype'] = 'daily'; break;
			case 3: $issue['subscribetype'] = 'weekly'; break;
			default: $issue['subscribetype'] = ''; break;
		}
	}

	$subscribe_selected = array(
		'donot' => ($issue['subscribetype'] == '' ? ' selected="selected"' : ''),
		'none' => ($issue['subscribetype'] == 'none' ? ' selected="selected"' : ''),
		'instant' => ($issue['subscribetype'] == 'instant' ? ' selected="selected"' : ''),
		'daily' => ($issue['subscribetype'] == 'daily' ? ' selected="selected"' : ''),
		'weekly' => ($issue['subscribetype'] == 'weekly' ? ' selected="selected"' : ''),
	);

	$show['subscribe_option'] = ($vbulletin->userinfo['userid'] > 0);

	// setup milestones
	$show['milestone'] = ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'] AND $project['milestonecount']);
	$show['milestone_edit'] = ($show['milestone'] AND $posting_perms['milestone_edit']);
	$milestone_options = fetch_milestone_select($project['projectid'], $issue['milestoneid']);

	// set up assignable users
	$assigned_user_list = array();

	// assignments
	$assignments = '';

	if ($vbulletin->GPC['type'] == 'issuenote')
	{
		$assignment_data = $db->query_read("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
			" . ($vbulletin->GPC['type'] == 'issuenote' ? "WHERE issueassign.issueid = $issue[issueid]" : '') . "
			ORDER BY user.username
		");

		while ($assignment = $db->fetch_array($assignment_data))
		{
			$assigned_user_list["$assignment[userid]"] = $assignment['userid'];
		}

		$unassigned_users = $assigned_users = $option = array();

		foreach ($vbulletin->pt_assignable["$project[projectid]"]["$issuetypeid"] AS $optionvalue => $optiontitle)
		{
			$option['title'] = $optiontitle;
			$option['value'] = $optionvalue;

			if (isset($assigned_user_list["$optionvalue"]))
			{
				$assigned_users[] = $option;
			}
			else
			{
				$unassigned_users[] = $option;
			}
		}
	}
	else
	{
		$unassigned_users = $assigned_users = $option = array();

		foreach ($vbulletin->pt_assignable["$project[projectid]"]["$issuetypeid"] AS $optionvalue => $optiontitle)
		{
			$option['title'] = $optiontitle;
			$option['value'] = $optionvalue;

			$unassigned_users[] = $option;
		}
	}

	$vbphrase['applies_version_issuetype'] = $vbphrase["applies_version_$issuetypeid"];
	$vbphrase['addressed_version_issuetype'] = $vbphrase["addressed_version_$issuetypeid"];

	// Some values for template
	$issue = array();

	$issue['issuetype'] = $vbphrase['issuetype_' . $issuetypeid . '_singular'];
	$issue['issuestatus'] = $vbphrase['issuestatus' . $issuestatus['startstatusid'] . ''];
	$issue['issuestatusid'] = $issuestatus['startstatusid'];
	$issue['state'] = 'open';
	$issue['addressedversion'] = $vbphrase['none_meta'];
	$issue['milestonetitle'] = $vbphrase['none_meta'];

	// Import infos
	switch ($vbulletin->GPC['type'])
	{
		case 'thread':
			$datainfo = $threadinfo;
			$datainfo['id'] = $threadinfo['threadid'];
			$datainfo['title'] = $threadinfo['title'];
			$datainfo['longtype'] = 'thread';
			$datainfo['shorttype'] = 't';
			break;
		case 'post':
			$datainfo = $postinfo;
			$datainfo['id'] = $postinfo['postid'];
			$datainfo['title'] = ($postinfo['title'] ? $postinfo['title'] : $threadinfo['title']);
			$datainfo['longtype'] = 'post';
			$datainfo['shorttype'] = 'p';
			break;
		case 'issuenote':
			$datainfo = $issuenoteinfo;
			$datainfo['id'] = $issuenoteinfo['issuenoteid'];
			$datainfo['title'] = $issuenoteinfo['title'];
			$datainfo['summary'] = $issuenoteinfo['summary'];
			$datainfo['longtype'] = 'issuenote';
			$datainfo['shorttype'] = 'issuenote';
			break;
	}

	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'' => $vbphrase['pt_create_issue']
	);

	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);

	$templater = vB_Template::create('pt_import_content_confirm');
		$templater->register_page_templates();
		$templater->register('addressed_next_selected', $addressed_next_selected);
		$templater->register('addressed_unaddressed_selected', $addressed_unaddressed_selected);
		$templater->register('addressed_versions', $addressed_versions);
		$templater->register('applies_unknown_selected', $applies_unknown_selected);
		$templater->register('applies_versions', $applies_versions);
		$templater->register('assign_checkbox_checked', $assign_checkbox_checked);
		$templater->register('assigned_users', $assigned_users);
		$templater->register('category_options', $category_options);
		$templater->register('category_unknown_selected', $category_unknown_selected);
		$templater->register('datainfo', $datainfo);
		$templater->register('issue', $issue);
		$templater->register('issuetype', $issuetype);
		$templater->register('issuetypeid', $issuetypeid);
		$templater->register('milestone_options', $milestone_options);
		$templater->register('navbar', $navbar);
		$templater->register('postpreview', $postpreview);
		$templater->register('priority_options', $priority_options);
		$templater->register('project', $project);
		$templater->register('projectissuetype', $vbulletin->GPC['project-issuetype']);
		$templater->register('startstatusid', $issuestatus['startstatusid']);
		$templater->register('status_options', $status_options);
		$templater->register('subscribe_selected', $subscribe_selected);
		$templater->register('unassigned_users', $unassigned_users);
	print_output($templater->render());
}

// #######################################################################
if ($_REQUEST['do'] == 'importcontent')
{
	$vbulletin->input->clean_gpc('r', 'type', TYPE_NOHTML);

	if ((($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canimportintoissues']) AND $foruminfo['canimportintoissues']))
	{
		print_no_permission();
	}

	$project_type_select = array();

	foreach ($vbulletin->pt_projects AS $projectid => $projectinfo)
	{
		$project_perms["$projectid"] = fetch_project_permissions($vbulletin->userinfo, $projectid);

		$optiongroup = $optgroup = $option = array();

		foreach (array_keys($projectinfo['types']) AS $type)
		{
			// Check we can both view and post the target issue type
			if (!($project_perms["$projectid"]["$type"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']) OR !($project_perms["$projectid"]["$type"]['postpermissions'] & $vbulletin->pt_bitfields['post']['canpostnew']))
			{
				continue;
			}

			$option['title'] = $vbphrase["issuetype_{$type}_singular"];
			$option['value'] = $projectinfo['projectid'] . '-' . $type;

			$optiongroup[] = $option;
		}

		if (empty($optiongroup))
		{
			continue;
		}

		$optgroup['label'] = $projectinfo['title'];
		$optgroup['group'] = $optiongroup;

		$project_type_select[] = $optgroup;
	}

	switch ($vbulletin->GPC['type'])
	{
		case 'thread':
			$datainfo = $threadinfo;
			$datainfo['id'] = $threadinfo['threadid'];
			$datainfo['title'] = $threadinfo['title'];
			$datainfo['longtype'] = 'thread';
			$datainfo['shorttype'] = 't';
			break;
		case 'post':	
			$datainfo = $postinfo;
			$datainfo['id'] = $postinfo['postid'];
			$datainfo['title'] = ($postinfo['title'] ? $postinfo['title'] : $threadinfo['title']);
			$datainfo['longtype'] = 'post';
			$datainfo['shorttype'] = 'p';
			break;
		case 'issuenote':
			$datainfo = $issuenoteinfo;
			$datainfo['id'] = $issuenoteinfo['issuenoteid'];
			$datainfo['title'] = $issuenoteinfo['title']; // Use issue title as issue note can't have title
			$datainfo['longtype'] = 'issuenote';
			$datainfo['shorttype'] = 'issuenote';
			break;
	}

	$navbits = array(
		'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
		'' => $vbphrase['pt_create_issue']
	);

	$navbits = construct_navbits($navbits);
	$navbar = render_navbar_template($navbits);

	$templater = vB_Template::create('pt_import_content');
		$templater->register_page_templates();
		$templater->register('navbar', $navbar);
		$templater->register('datainfo', $datainfo);
		$templater->register('project_type_select', $project_type_select);
	print_output($templater->render());
}

?>