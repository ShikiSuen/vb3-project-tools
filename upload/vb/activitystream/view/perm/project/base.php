<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

require_once(DIR . '/includes/functions_projecttools.php');

abstract class vB_ActivityStream_View_Perm_Project_Base extends vB_ActivityStream_View_Perm_Base
{
	protected function fetchCanViewIssueNote($issuenoteid)
	{
		if (!($issuenoterecord = $this->content['issuenote'][$issuenoteid]))
		{
			return false;
		}
		$issueid = $issuenoterecord['issueid'];
		$issuerecord = $this->content['issue'][$issueid];
		$projectid = $threadrecord['projectid'];
		$postviewable = ($issuenoterecord['visible'] == 'visible');

		if (!$postviewable OR !$this->fetchCanViewIssue($issueid))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	protected function fetchCanViewIssue($issueid)
	{
		if (!($issuerecord = $this->content['issue'][$issueid]))
		{
			return false;
		}
		$projectid = $issuerecord['projectid'];

		$issueperms = fetch_project_permissions(vB::$vbulletin->userinfo, $projectid, $issuerecord['issuetypeid']);
		$canviewothers = (!(vB::$vbulletin->userinfo['userid'] != $issuerecord['submituserid'] AND !($issueperms['generalpermissions'] & vB::$vbulletin->pt_bitfields['general']['canviewothers'])));
		$issueviewable = ($issuerecord['visible'] == 'visible');
		if (!$issueviewable OR !$this->fetchCanViewProject($projectid) OR (!$canviewothers AND $issuerecord['submituserid'] != vB::$vbulletin->userinfo['userid']))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	protected function fetchCanViewProject($projectid)
	{
		$projectperms = fetch_project_permissions(vB::$vbulletin->userinfo, $projectid);
		$perms_query = build_issue_permissions_query(vB::$vbulletin->userinfo);

		return (!empty($perms_query["$projectid"]));
	}
}

?>