<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletisn.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

class vB_ActivityStream_View_Perm_Project_IssueNote extends vB_ActivityStream_View_Perm_Project_Base
{
	public function __construct(&$content, &$vbphrase)
	{
		$this->requireExist['vB_ActivityStream_View_Perm_Project_Issue'] = 1;
		return parent::__construct($content, $vbphrase);
	}

	public function group($activity)
	{
		if (!$this->content['issuenote'][$activity['contentid']])
		{
			$this->content['issuenoteid'][$activity['contentid']] = 1;
		}
	}

	public function process()
	{
		if (!$this->content['issuenoteid'])
		{
			return;
		}

		$issuenotes = vB::$db->query_read_slave("
			SELECT
				isn.issuenoteid AS isn_issuenoteid, isn.issueid AS isn_issueid, isn.visible AS isn_visible, isn.userid AS isn_userid, isn.pagetext AS isn_pagetext, isn.type AS isn_type,
				i.issueid AS i_issueid, i.title AS i_title, i.projectid AS i_projectid, p.projectgroupid AS i_projectgroupid, i.state AS i_state, i.issuetypeid AS i_issuetypeid,
				i.visible AS i_visible, i.submituserid AS i_submituserid, i.submituserid AS i_userid, i.replycount AS i_replycount,
				isnfp.pagetext AS i_pagetext
			FROM " . TABLE_PREFIX . "pt_issuenote AS isn
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS i ON (isn.issueid = i.issueid)
				INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS isnfp ON (i.firstnoteid = isnfp.issuenoteid)
				INNER JOIN " . TABLE_PREFIX . "pt_project AS p ON (p.projectid = i.projectid)
			WHERE
				isn.issuenoteid IN (" . implode(",", array_keys($this->content['issuenoteid'])) . ")
					AND
				isn.visible IN ('visible', 'moderated')
					AND
				isn.type IN ('user', 'system', 'petition')
					AND
				i.visible IN ('visible', 'moderated')
		");
		while ($issuenote = vB::$db->fetch_array($issuenotes))
		{
			if ($issuenote['isn_type'] == 'petition')
			{
				// We need to query pt_issuepetition table to display the petition status
				$petitiondata = vB::$db->query_first("
					SELECT petitionstatusid
					FROM " . TABLE_PREFIX . "pt_issuepetition
					WHERE issuenoteid = " . intval($issuenote['isn_issuenoteid']) . "
				");

				$issuenote['isn_petition'] = $petitiondata['petitionstatusid'];
			}
			else
			{
				$issuenote['isn_petition'] = '';
			}

			unset($this->content['issueid'][$issuenote['isn_issueid']]);
			$this->content['issuenote'][$issuenote['isn_issuenoteid']] = $this->parse_array($issuenote, 'isn_');
			$this->content['userid'][$issuenote['isn_userid']] = 1;
			if (!$this->content['issue'][$issuenote['i_issueid']])
			{
				$this->content['issue'][$issuenote['i_issueid']] = $this->parse_array($issuenote, 'i_');
				$this->content['userid'][$issuenote['i_submituserid']] = 1;
			}
		}

		$this->content['issuenoteid'] = array();
	}

	public function fetchCanView($activity)
	{
		$this->processUsers();
		return $this->fetchCanViewIssueNote($activity['contentid']);
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

		$issuenoteinfo =& $this->content['issuenote'][$activity['contentid']];
		$issueinfo =& $this->content['issue'][$issuenoteinfo['issueid']];
		$activity['issuenotedate'] = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
		$activity['issuenotetime'] = vbdate(vB::$vbulletin->options['timeformat'], $activity['dateline']);

		if ($issuenoteinfo['type'] == 'system')
		{
			$issuenoteinfo['preview'] = translate_system_note($issuenoteinfo['pagetext']);
		}
		else if ($issuenoteinfo['type'] == 'petition')
		{
			$issuenoteinfo['issuestatus'] = new vB_Phrase('projecttools', 'issuestatus' . $issuenoteinfo['petition']);

			$preview = strip_quotes($issuenoteinfo['pagetext']);
			$issuenoteinfo['preview'] = htmlspecialchars_uni(fetch_censored_text(fetch_trimmed_title(strip_bbcode($preview, false, true, true, true), vB::$vbulletin->options['threadpreview'])));
		}
		else
		{
			$preview = strip_quotes($issuenoteinfo['pagetext']);
			$issuenoteinfo['preview'] = htmlspecialchars_uni(fetch_censored_text(fetch_trimmed_title(strip_bbcode($preview, false, true, true, true), vB::$vbulletin->options['threadpreview'])));
		}

		$projectperms = fetch_project_permissions(vB::$vbulletin->userinfo, $issueinfo['projectid'], $issueinfo['issuetypeid']);
		$show['issuecontent'] = ($projectperms & vB::$vbulletin->pt_bitfields['general']['canview']);

		$templater = vB_Template::create($templatename);
			$templater->register('userinfo', $this->content['user'][$activity['userid']]);
			$templater->register('activity', $activity);
			$templater->register('issueinfo', $issueinfo);
			$templater->register('issuenoteinfo', $issuenoteinfo);
			$templater->register('pageinfo', array('p' => $issuenoteinfo['issuenoteid']));
			$templater->register('projectinfo', vB::$vbulletin->pt_projects[$issueinfo['projectgroupid']]['projects'][$issueinfo['projectid']]);
		return $templater->render();
	}
}

?>