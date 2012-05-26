<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Sends the reply notification to users subscribed to this issue.
*
* @param	array	Info about this issue
* @param	array	Info about this note (including text)
*/
function send_issue_reply_notification($issue, $issuenote)
{
	global $vbulletin, $db, $vbphrase;

	if ($issuenote['type'] != 'user' AND $issuenote['type'] != 'petition')
	{
		// only send if the note is a "normal" note type
		return;
	}

	$project = fetch_project_info($issue['projectid']);

	$previousnote = $db->query_first("
		SELECT MAX(dateline) AS dateline
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.dateline < $issuenote[dateline]
			AND issuenote.visible = 'visible'
			AND issuenote.type IN ('user', 'petition')
	");

	$notifications = $db->query_read_slave("
		SELECT user.*
		FROM " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (issuesubscribe.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		WHERE issuesubscribe.issueid = $issue[issueid]
			AND issuesubscribe.subscribetype = 'instant'
			AND (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
			" . ($issuenote['userid'] ? "AND CONCAT(' ', IF(usertextfield.ignorelist IS NULL, '', usertextfield.ignorelist), ' ') NOT LIKE ' " . intval($issuenote['userid']) . " '" : '') . "
			AND user.userid <> $issuenote[userid]
			AND user.lastactivity >= " . intval($previousnote['dateline']) . "
	");
	if ($db->num_rows($notifications) == 0)
	{
		return;
	}

	require_once(DIR . '/includes/functions_misc.php');

	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser = new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$pagetext_cache = array(); // used to cache the results per languageid for speed

	$evalemail = array();
	$email_texts = $vbulletin->db->query_read_slave("
		SELECT text, languageid, fieldname
		FROM " . TABLE_PREFIX . "phrase
		WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'notify_pt'
	");

	while ($email_text = $vbulletin->db->fetch_array($email_texts))
	{
		$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
	}

	foreach ($emails AS $languageid => $email_text)
	{
		// lets cycle through our array of notify phrases
		$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
		$text_message = replace_template_variables($text_message);
		$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
		$text_subject = replace_template_variables($text_subject);

		$evalemail["$languageid"] = '
			$message = "' . $text_message . '";
			$subject = "' . $text_subject . '";
		';
	}

	vbmail_start();

	while ($notification = $vbulletin->db->fetch_array($notifications))
	{
		// check that this user has the correct permissions to view
		if (verify_issue_perms($issue, $notification) === false OR verify_issue_note_perms($issue, $issuenote, $notification) === false)
		{
			continue;
		}

		$notification['username'] = unhtmlspecialchars($notification['username']);
		$notification['languageid'] = iif($notification['languageid'] == 0, $vbulletin->options['languageid'], $notification['languageid']);

		// parse the page text into plain text, taking selected language into account
		if (!isset($pagetext_cache["$notification[languageid]"]))
		{
			$plaintext_parser->set_parsing_language($notification['languageid']);
			$pagetext_cache["$notification[languageid]"] = $plaintext_parser->parse($issuenote['pagetext'], 'pt');
		}
		$pagetext = $pagetext_cache["$notification[languageid]"];


		eval(empty($evalemail["$notification[languageid]"]) ? $evalemail["-1"] : $evalemail["$notification[languageid]"]);
		vbmail($notification['email'], $subject, $message);
		
		
	}

	unset($plaintext_parser, $pagetext_cache);

	vbmail_end();
}

/**
* Prepares the preview of note text or the error box.
*
* @param	array	Input data (GPC)
* @param	object	Issue note data manager
* @param	array	(Output) Array of info about this note, in the same form that would come from the DB
* @param	array	(In/Out) Info about this issue, as it would come from the DB
*
* @return	string	Preview/error HTML
*/
function prepare_pt_note_preview($input, &$notedata, &$issuenote, &$issue)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	if ($notedata->errors)
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$preview = construct_errors($notedata->errors);
	}
	else
	{
		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode = new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$preview_text =  $bbcode->parse($input['message'], 'pt');

		$templater = vB_Template::create('pt_preview');
			$templater->register('preview_text', $preview_text);
		$preview = $templater->render();
	}

	$input_map = array(
		'pagetext'         => $input['message'],
		'private'          => $input['private'],
		'petitionstatusid' => $input['petitionstatusid'],
		'reason'           => $input['reason']
	);

	$issuenote = $notedata->pt_issuenote + $notedata->existing + $input_map;
	$issue['subscribetype'] = $input['subscribetype'];

	return $preview;
}

/**
* Handles an update to the subscription status for an issue
*
* @param	integer	Issue ID
* @param	string	Old/existing subscription status
* @param	string	New subscription status
* @param	integer	User to update. -1 means the browsing user
*/
function handle_issue_subscription_change($issueid, $oldvalue, $newvalue, $userid = -1)
{
	global $vbulletin, $db;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	$userid = intval($userid);

	if ($userid)
	{
		if ($newvalue AND $newvalue != $oldvalue)
		{
			// chose to add/change subscription
			$subscriptiondata =& datamanager_init('Pt_IssueSubscribe', $vbulletin, ERRTYPE_SILENT);
			$subscriptiondata->set('subscribetype', $newvalue);
			$subscriptiondata->set('issueid', $issueid);
			$subscriptiondata->set('userid', $userid);
			$subscriptiondata->save();
		}
		else if ($oldvalue AND !$newvalue)
		{
			// means the user is deleting the subscription, because they didn't send any new value
			$subscription = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issuesubscribe
				WHERE issueid = $issueid
					AND userid = $userid
			");
			if ($subscription)
			{
				$subscriptiondata =& datamanager_init('Pt_IssueSubscribe', $vbulletin, ERRTYPE_SILENT);
				$subscriptiondata->set_existing($subscription);
				$subscriptiondata->delete();
			}
		}
	}
}

/**
* Fetches the note to be quoted and modifies it as appropriate. Determines
* if this message should be private based on the quoted note.
*
* @param	integer	Note to quote
* @param	integer	Issue being responded to/issue the note is in
* @param	array	Permissions for this issue
* @param	bool	(Output) Whether the quoted note is private
*
* @return	string	Quoted note (ready for passing into editor functions)
*/
function fetch_pt_quoted_note($quotenoteid, $issueid, $issueperms, &$quoted_private)
{
	global $vbulletin, $db, $show, $vbphrase, $template_hook;

	$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);
	$quotenote = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenoteid = $quotenoteid
			AND issueid = $issueid
			AND (visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			AND type IN ('user', 'petition')
	");

	if ($quotenote)
	{
		// new post with a quote
		$quoted_private = ($quotenote['visible'] == 'private');

		require_once(DIR . '/includes/functions_newpost.php');

		$originalposter = fetch_quote_username($quotenote['username']);
		$quote_text = trim(strip_quotes($quotenote['pagetext']));

		$templater = vB_Template::create('pt_postreply_quote');
			$templater->register('originalposter', $originalposter);
			$templater->register('quote_text', $quote_text);
		return $templater->render(true) . "\n";

	}
	else
	{
		$quoted_private = false;
		return '';
	}
}

/**
* Prepares tag changes based on whether JS was used or not
*
* @param	array	Array of GPC input
* @param	array	Array of existing tags (must be an array!)
* @param	array	(Output) Tags to be added
* @param	array	(Output) Tags to be removed
*/
function prepare_tag_changes($input, $existing_tags, &$tag_add, &$tag_remove)
{
	if ($input['submit_addtag'])
	{
		// no JS assigning
		if ($input['customtag'])
		{
			$tag_add = array($input['customtag']);
		}
		else
		{
			$tag_add = $input['unappliedtags'];
		}
		$tag_remove = array();
	}
	else if ($input['submit_removetag'])
	{
		// no JS unassigning
		$tag_add = array();
		$tag_remove = $input['appliedtags'];
	}
	else
	{
		// the JS method
		$tag_add = array_diff($input['appliedtags'], $existing_tags);
		$tag_remove = array_diff($existing_tags, $input['appliedtags']);
	}
}

/**
* Process changes the user assignment
*
* @param	array	Array of GPC input
* @param	array	Array of posting perms (to check what the editing user can change)
* @param	array	Array of existing assignments
* @param	array	Project information
* @param	array	Issue information
* @param	bool	Whether to log changes to assignments (should be true, unless this is called during issue creation)
*/
function process_assignment_changes($input, $posting_perms, $existing_assignments, $project, $issue, $log_assignment_changes = true)
{
	global $vbulletin;

	if ($posting_perms['assign_dropdown'])
	{
		if ($input['submit_assign'])
		{
			// no JS assigning
			$assign_add = $input['unassigned'];
			$assign_remove = array();
		}
		else if ($input['submit_unassign'])
		{
			// no JS unassigning
			$assign_remove = $input['assigned'];
			$assign_add = array();
		}
		else
		{
			// the JS method
			$assign_add = array_diff($input['assigned'], $existing_assignments);
			$assign_remove = array_diff($existing_assignments, $input['assigned']);
		}

		foreach ($assign_add AS $userid)
		{
			if (!isset($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"]["$userid"]))
			{
				// user cannot be assigned
				continue;
			}

			$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
			$assign->set_info('project', $project);
			$assign->set('userid', $userid);
			$assign->set('issueid', $issue['issueid']);
			$assign->set_info('log_assignment_changes', $log_assignment_changes);
			$assign->save();
		}

		foreach ($assign_remove AS $userid)
		{
			$data = array('userid' => $userid, 'issueid' => $issue['issueid']);
			$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
			$assign->set_existing($data);
			$assign->set_info('log_assignment_changes', $log_assignment_changes);
			$assign->delete();
		}
	}
	else if ($posting_perms['assign_checkbox'])
	{
		// can only modify own assignment
		if ($input['assignself'] AND empty($issue['isassigned']))
		{
			// unassigned -> assigned
			$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
			$assign->set_info('project', $project);
			$assign->set('userid', $vbulletin->userinfo['userid']);
			$assign->set('issueid', $issue['issueid']);
			$assign->save();
		}
		else if (!$input['assignself'] AND !empty($issue['isassigned']))
		{
			// assigned -> unassigned
			$data = array('userid' => $vbulletin->userinfo['userid'], 'issueid' => $issue['issueid']);
			$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
			$assign->set_existing($data);
			$assign->delete();
		}
	}
}

/**
* Sends email assignment notification when a user is assigned
*
* @param	integer	Issueid to send notification for
* @param	integer	User who is being assigned this issue
* @param	integer	User who assigned this issue
*/
function send_issue_assignment_notification_email($issueid, $assignee, $assigner)
{
	global $vbulletin, $vbphrase;

	$issue = fetch_issue_info($issueid);

	// invalid issue
	if (!$issue)
	{
		return;
	}

	// no need for notification to yourself
	if ($assignee == $assigner)
	{
		return;
	}

	$project = fetch_project_info($issue['projectid']);
	$assignee_userinfo = fetch_userinfo($assignee);

	if (verify_issue_perms($issue, $assignee_userinfo) === false)
	{
		return;
	}

	$assigner_userinfo = fetch_userinfo($assigner);

	$issue['title'] = unhtmlspecialchars($issue['title']);
	$project['title'] = unhtmlspecialchars($project['title']);
	$assignee_userinfo['username'] = unhtmlspecialchars($assignee_userinfo['username']);
	$assigner_userinfo['username'] = unhtmlspecialchars($assigner_userinfo['username']);

	eval(fetch_email_phrases('pt_issueassignment', $assignee_userinfo['languageid']));
	vbmail($assignee_userinfo['email'], $subject, $message, true);
}

/**
* Sends PM assignment notification when a user is assigned
*
* @param	integer	Issueid to send notification for
* @param	integer	User who is being assigned this issue
* @param	integer	User who assigned this issue
*/
function send_issue_assignment_notification_pm($issueid, $assignee, $assigner)
{
	global $vbulletin, $vbphrase;

	$issue = fetch_issue_info($issueid);

	// invalid issue
	if (!$issue)
	{
		return;
	}

	// no need for notification to yourself
	if ($assignee == $assigner)
	{
		return;
	}

	$project = fetch_project_info($issue['projectid']);
	$assignee_userinfo = fetch_userinfo($assignee);

	if (verify_issue_perms($issue, $assignee_userinfo) === false)
	{
		return;
	}

	$assigner_userinfo = fetch_userinfo($assigner);

	$issue['title'] = unhtmlspecialchars($issue['title']);
	$project['title'] = unhtmlspecialchars($project['title']);
	$assignee_userinfo['username'] = unhtmlspecialchars($assignee_userinfo['username']);
	$assigner_userinfo['username'] = unhtmlspecialchars($assigner_userinfo['username']);

	eval(fetch_email_phrases('pt_issueassignment', $assignee_userinfo['languageid']));
	
	// Init vB_PM datamanager.
	$pm =& datamanager_init('PM', $vbulletin, ERRTYPE_SILENT);
	$pm->set('fromuserid', $assigner_userinfo['userid']);
	$pm->set('fromusername', $assigner_userinfo['username']);
	$pm->set('title', $subject);
	$pm->set('message', $message);
	$pm->set('dateline', TIMENOW);
	$pm->set_recipients($assignee_userinfo['username'], $permissions);
	$pm->pre_save();
	$pm->save(); 
}

/**
* Fetches the list of milestones for use in a select box.
* Does not handle dealing with selected entries.
*
* @param	integer	Project ID to fetch milestones for
* @param	array	Optional list of milestone ids to skip
*
* @return	array	Array of milestones, grouped as necessary
*/
function fetch_milestone_select_list($projectid, $skip_ids = array())
{
	global $vbulletin, $vbphrase;

	$milestones = array(
		'0' => $vbphrase['none_meta'],
		$vbphrase['active_milestones'] => array(),
		$vbphrase['completed_milestones'] => array()
	);
	$no_targets = array();

	$milestone_data = $vbulletin->db->query_read("
		SELECT milestoneid, completeddate, targetdate
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE projectid = $projectid
			" . ($skip_ids ? "AND milestoneid NOT IN (" . implode(',', $skip_ids) . ")" : '') . "
		ORDER BY completeddate DESC, targetdate
	");
	while ($milestone = $vbulletin->db->fetch_array($milestone_data))
	{
		if ($milestone['completeddate'])
		{
			$milestones["$vbphrase[completed_milestones]"]["$milestone[milestoneid]"] = htmlspecialchars_uni($vbphrase['milestone_' . $milestone['milestoneid'] . '_name']);
		}
		else if (!$milestone['targetdate'])
		{
			$no_targets["$milestone[milestoneid]"] = htmlspecialchars_uni($vbphrase['milestone_' . $milestone['milestoneid'] . '_name']);
		}
		else
		{
			$milestones["$vbphrase[active_milestones]"]["$milestone[milestoneid]"] = htmlspecialchars_uni($vbphrase['milestone_' . $milestone['milestoneid'] . '_name']);
		}
	}

	$milestones["$vbphrase[active_milestones]"] += $no_targets;

	if (empty($milestones["$vbphrase[active_milestones]"]))
	{
		unset($milestones["$vbphrase[active_milestones]"]);
	}

	if (empty($milestones["$vbphrase[completed_milestones]"]))
	{
		unset($milestones["$vbphrase[completed_milestones]"]);
	}

	return $milestones;
}

/**
* Fetches actual milestone select options based on templates.
*
* @param	integer	Project ID to pull milestones from
* @param	integer	Selected milestone ID
* @param	array	Optional list of milestone IDs to omit
*
* @return	string	Outputtable HTML
*/
function fetch_milestone_select($projectid, $selected_milestone = 0, $skip_ids = array())
{
	global $vbulletin, $vbphrase, $show;

	$milestone_array = fetch_milestone_select_list($projectid, $skip_ids);
	$milestone_options = $milestone_none = array();

	foreach ($milestone_array AS $optgroup_label => $option_container)
	{
		$option = array();

		if (!is_array($option_container))
		{
			// Here, it corresponds only to the '(none)' milestone
			// All others options are 'Active Milestones' and 'Completed Milestones'
			// This is now hardcoded in template 'pt_postissue'
		}
		else if (!empty($option_container))
		{
			$optgroup = $option = $optiongroup = array();

			foreach ($option_container AS $optionvalue => $optiontitle)
			{
				$option['title'] = $optiontitle;
				$option['value'] = $optionvalue;
				$option['selected'] = ($selected_milestone == $option['value'] ? ' selected="selected"' : '');
				$optgroup[] = $option;
			}

			$optiongroup['label'] = $optgroup_label;
			$optiongroup['group'] = $optgroup;

			$milestone_options[] = $optiongroup;
		}
	}

	return $milestone_options;
}

?>