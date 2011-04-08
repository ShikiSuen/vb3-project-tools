<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_projecttools.php');

/**
* Shows the new subscribed PT issues in the user CP
*
* @return	string	Printable issue bits
*/
function process_new_subscribed_issues()
{
	global $vbulletin, $show, $vbphrase, $template_hook, $vbcollapse;

	if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
	{
		return '';
	}

	$perms_query = build_issue_permissions_query($vbulletin->userinfo);
	if (!$perms_query)
	{
		return '';
	}

	$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);
	if ($marking)
	{
		$issueview_sql = "IF(issueread IS NOT NULL, issueread, " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . ")";
		$issueview_sql2 = "IF(projectread IS NOT NULL, projectread, " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . ")";
	}
	else
	{
		$issueview = max(intval(fetch_bbarray_cookie('issue_lastview', $issue['issueid'])), intval(fetch_bbarray_cookie('issue_lastview', $issue['projectid'] . $issue['issuetypeid'])));
		if (!$issueview)
		{
			$issueview = $vbulletin->userinfo['lastvisit'];
		}
		$issueview_sql = intval($issueview);
		$issueview_sql2 = '';
	}

	build_issue_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

	$replycount_clause = fetch_private_replycount_clause($vbulletin->userinfo);

	$subscriptions = $vbulletin->db->query_read("
		SELECT issue.*, issuesubscribe.subscribetype,
			project.title_clean
			" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
			" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
			" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
		FROM " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe
		INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesubscribe.issueid)
		INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
		" . ($marking ? "
			LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "pt_projectread as projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
		" : '') . "
		$private_lastpost_join
		WHERE issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . "
			AND (" . implode(' OR ', $perms_query) . ")
		HAVING lastpost > " . intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) . "
			AND lastpost > " . $issueview_sql . "
			" . (!empty($issueview_sql2) ? " AND lastpost > " . $issueview_sql2 : '' ) . "
		ORDER BY lastpost DESC
	");

	$show['issuebit_project_title'] = true;
	$subscriptionbits = '';
	while ($issue = $vbulletin->db->fetch_array($subscriptions))
	{
		$issue = prepare_issue($issue);
		$templater = vB_Template::create('pt_issuebit');
			$templater->register('issue', $issue);
		$subscriptionbits .= $templater->render();
	}

	if (!$subscriptionbits)
	{
		return '';
	}

	$templater = vB_Template::create('pt_usercp_subscriptions');
		$templater->register('subscriptionbits', $subscriptionbits);
	$return = $templater->render();
	return $return;
}

?>