<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
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

require_once(DIR . '/includes/class_reportitem.php');

/**
* Report Issue Note Message Class
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_ReportItem_Pt_IssueNote extends vB_ReportItem
{
	/**
	 * @var string	"Key" for the phrase(s) used when reporting this item
	 */
	var $phrasekey = 'issuenote';

	/**
	 * Fetches the moderators affected by this report
	 *
	 * @return null|array	The moderators affected.
	 *
	 */
	function fetch_affected_moderators()
	{
		$projectid = $this->extrainfo['issue']['projectid'];
		$issuetypeid = $this->extrainfo['issue']['issuetypeid'];
		if (isset($this->registry->pt_report_users) AND !empty($this->registry->pt_report_users[$projectid]) AND !empty($this->registry->pt_report_users[$projectid][$issuetypeid]))
		{
			$users = $this->registry->pt_report_users[$projectid][$issuetypeid];
			if (empty($users))
			{
				return;
			}

			$users_check = $this->registry->db->query_read_slave("
				SELECT userid, usergroupid, membergroupids, infractiongroupids
				FROM " . TABLE_PREFIX . "user AS user
				WHERE userid IN (" . implode(',', array_keys($users)) . ")
			");
			while ($user_check = $this->registry->db->fetch_array($users_check))
			{ // check that the user can actually see the state in order to do something with it.
				if (!verify_issue_note_perms($this->extrainfo['issue'], $this->extrainfo['issue_note'], $user_check))
				{
					unset($users[$user_check['userid']]);
				}
			}

			if (!empty($users))
			{
				return $this->registry->db->query_read_slave("
					SELECT DISTINCT user.email, user.languageid, user.userid, user.username
					FROM " . TABLE_PREFIX . "user AS user
					WHERE userid IN (" . implode(',', array_keys($users)) . ")
				");
			}
		}
	}

	/**
	 * Sets information to be used in the form for the report
	 *
	 * @param	array	Information to be used.
	 *
	 */
	function set_forminfo(&$iteminfo)
	{
		global $vbphrase;

		$this->forminfo = array(
			'file'         => 'project',
			'action'       => 'sendemail',
			'reportphrase' => $vbphrase['report_issue_note'],
			'reporttype'   => $vbphrase['issue_note'],
			'description'  => $vbphrase['only_used_to_report'],
			'itemname'     => $this->extrainfo['issue']['title'],
			'itemlink'     => "issue.php?" . $this->registry->session->vars['sessionurl'] . "do=gotonote&amp;issuenoteid=$iteminfo[issuenoteid]",
		);

		$this->set_reporting_hidden_value('issuenoteid', $iteminfo['issuenoteid']);

		return $this->forminfo;
	}

	/**
	 * Sets information regarding the report
	 *
	 * @param	array	Information regarding the report
	 *
	 */
	function set_reportinfo(&$reportinfo)
	{
		$reportinfo = array_merge($reportinfo, array(
			'messagetitle' => unhtmlspecialchars($this->extrainfo['issue']['title']),
			'pusername'    => unhtmlspecialchars($this->iteminfo['username']),
			'issuenoteid'  => $this->iteminfo['issuenoteid'],
			'issueid'      => $this->iteminfo['issueid'],
			'puserid'      => $this->iteminfo['userid'],
			'pagetext'     => $this->iteminfo['pagetext'],
			'ptitle'       => $this->extrainfo['project']['title_clean'],
			'projectid'    => $this->extrainfo['project']['projectid'],
		));
	}

	/**
	 * Updates the Item being reported with the item report info.
	 *
	 * @param	integer	ID of the item being reported
	 *
	 */
	function update_item_reportid($newthreadid)
	{
		$dataman = datamanager_init(
			'Pt_IssueNote_User',
			$this->registry,
			ERRTYPE_SILENT,
			'pt_issuenote'
		);
		$dataman->set_info('is_automated', true);
		$dataman->set_info('parseurl', true);
		$dataman->set('reportthreadid', $newthreadid);

		// if $this->iteminfo['reportthreadid'] exists then it means then the discussion thread has been deleted/moved
		$checkrpid = ($this->iteminfo['reportthreadid'] ? $this->iteminfo['reportthreadid'] : 0);
		$dataman->condition = "issuenoteid = " . $this->iteminfo['issuenoteid'] . " AND reportthreadid = $checkrpid";

		// affected_rows = 0, meaning another user reported this before us (race condition)
		return $dataman->save(true, false, true);
	}

	/**
	 * Re-fetches information regarding the reported item from the database
	 *
	 */
	function refetch_iteminfo()
	{
		$rpinfo = $this->registry->db->query_first("
			SELECT reportthreadid
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . $this->iteminfo['issuenoteid']
		);
		if ($rpinfo['reportthreadid'])
		{
			$this->iteminfo['reportthreadid'] = $rpinfo['reportthreadid'];
		}
	}
}

?>