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

class vB_ActivityStream_View_Perm_Project_Issue extends vB_ActivityStream_View_Perm_Project_Base
{
	public function __construct(&$content, &$vbphrase)
	{
		$this->requireFirst['vB_ActivityStream_View_Perm_Project_IssueNote'] = 1;
		return parent::__construct($content, $vbphrase);
	}

	public function group($activity)
	{
		if (!$this->content['issue'][$activity['contentid']])
		{
			$this->content['issueid'][$activity['contentid']] = 1;
		}
	}

	public function process()
	{
		if (!$this->content['issueid'])
		{
			return true;
		}

		$issues = vB::$db->query_read_slave("
			SELECT
				i.issueid, i.title, i.projectid, p.projectgroupid, i.state, i.visible, i.submituserid, i.submituserid AS userid, i.replycount, i.issuetypeid,
				infp.pagetext
			FROM " . TABLE_PREFIX . "pt_issue AS i
			INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS infp ON (i.firstnoteid = infp.issuenoteid)
			INNER JOIN " . TABLE_PREFIX . "pt_project AS p ON (i.projectid = p.projectid)
			WHERE
				i.issueid IN (" . implode(",", array_keys($this->content['issueid'])) . ")
					AND
				i.visible IN ('visible', 'private')
		");
		while ($issue = vB::$db->fetch_array($issues))
		{
			$this->content['projectid'][$issue['projectid']] = 1;
			$this->content['issue'][$issue['issueid']] = $issue;
			$this->content['userid'][$issue['submituserid']] = 1;
		}

		$this->content['issueid'] = array();
	}

	public function fetchCanView($activity)
	{
		$this->processUsers();
		return $this->fetchCanViewIssue($activity['contentid']);
	}

	/*
	 * Register Template
	 *
	 * @param	string	Template Name
	 * @param	array	Activity Record
	 *
	 * @return	string	Template
	 */
	public function fetchTemplate($templatename, $activity, $skipgroup = false, $fetchphrase = false)
	{
		global $show;

		$issueinfo =& $this->content['issue'][$activity['contentid']];
		$activity['issuenotedate'] = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
		$activity['issuenotetime'] = vbdate(vB::$vbulletin->options['timeformat'], $activity['dateline']);

		$issueinfo['preview'] = strip_quotes($issueinfo['pagetext']);
		$issueinfo['preview'] = htmlspecialchars_uni(fetch_censored_text(fetch_trimmed_title(strip_bbcode($issueinfo['preview'], false, true, true, true), vB::$vbulletin->options['threadpreview'])));

		$projectperms = fetch_project_permissions(vB::$vbulletin->userinfo, $issueinfo['projectid'], $issueinfo['issuetypeid']);
		$show['issuecontent'] = ($projectperms & vB::$vbulletin->pt_bitfields['general']['canview']);

		$templater = vB_Template::create($templatename);
			$templater->register('userinfo', $this->content['user'][$activity['userid']]);
			$templater->register('activity', $activity);
			$templater->register('issueinfo', $issueinfo);
			$templater->register('projectinfo', vB::$vbulletin->pt_projects[$issueinfo['projectgroupid']]['projects'][$issueinfo['projectid']]);
		return $templater->render();
	}
}

?>