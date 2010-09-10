<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* @package vBulletin Project Tools
* @subpackage Search
* @author $Author$
* @version $Revision$
* @since $Date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

require_once(DIR . '/vb/search/searchcontroller.php');

class vBProjectTools_Search_SearchController_NewIssueNote extends vB_Search_SearchController
{
	public function get_results($user, $criteria)
	{
		global $vbulletin;

		$datastores = $vbulletin->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ('pt_bitfields', 'pt_permissions', 'pt_issuestatus', 'pt_issuetype', 'pt_projects', 'pt_categories', 'pt_assignable', 'pt_versions')
		");

		while ($datastore = $vbulletin->db->fetch_array($datastores))
		{
			$title = $datastore['title'];

			if (!is_array($datastore['data']))
			{
				$data = unserialize($datastore['data']);

				if (is_array($data))
				{
					$vbulletin->$title = $data;
				}
			}
			else if ($datastore['data'] != '')
			{
				$vbulletin->$title = $datastore['data'];
			}
		}

		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();
		$equals_filters = $criteria->get_equals_filters();
		$notequals_filter = $criteria->get_notequals_filters();

		//handle forums
		if (isset($equals_filters['projectid']))
		{
			$projectids = $equals_filters['projectid'];
		}
		else
		{
			$projectids = array_keys($vbulletin->pt_projects);
		}

		$excluded_projectids = array();
		if (isset($notequals_filter['projectid']))
		{
			$excluded_projectids = $notequals_filter['projectid'];
		}

		$projectids = array_diff($projectids, $excluded_projectids, $this->getUnsearchableProjects());

		$results = array();

		if (empty($projectids))
		{
			return $results;
		}

		//get thread/post results.
		if (!empty($range_filters['markinglimit'][0]))
		{
			$cutoff = $range_filters['markinglimit'][0];

			$marking_join = "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = project.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$lastpost_where = "
				AND issue.lastpost > IF(issueread.readtime IS NULL, $cutoff, issueread.readtime)
				AND issue.lastpost > IF(projectread.readtime IS NULL, $cutoff, projectread.readtime)
				AND issue.lastpost > $cutoff
			";

			$issuenote_lastpost_where = "
				AND issuenote.dateline > IF(issueread.readtime IS NULL, $cutoff, issueread.readtime)
				AND issuenote.dateline > IF(projectread.readtime IS NULL, $cutoff, projectread.readtime)
				AND issuenote.dateline > $cutoff
			";
		}
		else
		{
			//get date cut -- but only if we're not using the threadmarking filter
			if (isset($range_filters['datecut']))
			{
				//ignore any upper limit
				$datecut = $range_filters['datecut'][0];
			}
			else
			{
				return $results;
			}

			$marking_join = '';
			$lastpost_where = "AND issue.lastpost >= $datecut";
			$issuenote_lastpost_where = "AND issuenote.dateline >= $datecut";
		}

		$orderby = $this->get_orderby($criteria);

		//This doesn't actually work -- removing.
		//even though showresults would filter thread.visible=0, thread.visible remains in these 2 queries
		//so that the 4 part index on thread can be used.

		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'IssueNote');
			$issuenotes = $db->query_read_slave($q = "
				SELECT issuenote.issuenoteid, issuenote.issueid
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuenote.issueid)
				$marking_join
				WHERE issue.projectid IN(" . implode(', ', $projectids) . ")
					$lastpost_where
					$issuenote_lastpost_where
				ORDER BY $orderby
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($issuenote = $db->fetch_array($issuenotes))
			{
				$results[] = array($contenttypeid, $issuenote['issuenoteid'], $issuenote['issueid']);
			}
		}
		else
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'Issue');
			$threads = $db->query_read_slave($q = "
				SELECT issue.issueid
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				$marking_join
				WHERE issue.projectid IN(" . implode(', ', $projectids) . ")
					$lastpost_where
					AND issue.open <> 10
				ORDER BY $orderby
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($issue = $db->fetch_array($issues))
			{
				$results[] = array($contenttypeid, $issue['issueid'], $issue['issueid']);
			}
		}

		return $results;
	}

	/**
	 * Does the user have the requested permission on this project.
	 *
	 * @param int $projectid
	 * @param string $permission Name of permission
	 * @return boolean
	 */
	public function hasProjectPermission($projectid, $permission)
	{
		//should be cached and therefore not too expensive to look up on every permissions call.
		$perms = fetch_permissions($projectid);
		return (bool) ($perms & $this->registry->bf_ugp_projectpermissions[$permission]);
	}

	/**
	* Get projects the user is unable to view.
	*
	*	Need to verify that this makes sense in general code stolen from search
	* logic and search specific param removed.
	*
	*	This value is calculated once and the list is returned on subsequent calls
	*
	*	@return array(int) list of hidden project ids 
	*/
	public function getHiddenProjects()
	{
		if (is_null($this->hidden_projects))
		{
			$this->hidden_projects = array();

			foreach ($this->registry->userinfo['projectpermissions'] AS $projectid => $pperms)
			{
				$project = fetch_foruminfo($projectid);

				if (!$this->hasProjectPermission($projectid, 'canview'))
				{
					$this->hidden_projects[] = $projectid;
				}
			}
		}
		return $this->hidden_projects;
	}

	/**
	* Get projects the user is unable to search.
	*
	*	This value is calculated once and the list is returned on subsequent calls
	*
	*	@return array(int) list of unsearchable project ids 
	*/
	public function getUnsearchableProjects()
	{
		if (is_null($this->unsearchable_projects))
		{
			$this->unsearchable_projects = $this->getHiddenProjects();

			foreach ($this->registry->userinfo['projectpermissions'] AS $projectid => $pperms)
			{
				if (!in_array($projectid, $this->unsearchable_projects))
				{
					if (!$this->hasProjectPermission($projectid, 'cansearch'))
					{
						$this->unsearchable_projects[] = $projectid;
					}
				}
			}
		}
		return $this->unsearchable_projects;
	}

	private function get_orderby($criteria)
	{
		$sort = $criteria->get_sort();
		$direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';

		$sort_map = array(
			'user'				=> 'submitusername',
			'dateline'			=> 'submitdate',
			'groupuser'			=> 'submitusername',
			'groupdateline'		=> 'lastpost',
			'defaultdateline'	=> 'lastpost',
			'defaultuser'		=> 'username',
			'replycount'		=> 'replycount',
			'issuestart'		=> 'dateline'
		);

		if (!isset($sort_map[$sort]))
		{
			$sort = ($criteria->get_grouped() == vB_Search_Core::GROUP_NO) ? 'dateline' : 'groupdateline';
		}

		//if its a non group field and we aren't grouping, use the post table
		$nongroup_field = in_array($sort, array('user', 'dateline'));

		//if a field is a date, don't add the secondary sort by the "dateline" field
		$date_sort = in_array($sort, array('dateline', 'groupdateline', 'defaultdateline', 'issuestart'));

		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			if ($nongroup_field)
			{
				$table = 'pt_issuenote';
			}
			else
			{
				$table = 'pt_issue';
			}

			$orderby = "$table.$sort_map[$sort] $direction";
			if (!$date_sort)
			{
				$orderby .= ", issuenote.dateline DESC";
			}
		}
		else
		{
			$orderby = "issue.$sort_map[$sort] $direction";
			if (!$date_sort)
			{
				$orderby .= ", issue.submitdate DESC";
			}
		}

		return $orderby;
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 18:15, Fri Sep 3rd 2010
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
