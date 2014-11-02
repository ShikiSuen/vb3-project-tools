<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/

require_once(DIR . '/vb/search/searchcontroller.php');

class vBProjectTools_Search_SearchController_NewIssueNote extends vB_Search_SearchController
{
	public function get_results($user, $criteria)
	{
		global $vbulletin;

		fetch_pt_datastore();

		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();
		$equals_filters = $criteria->get_equals_filters();
		$notequals_filter = $criteria->get_notequals_filters();

		//handle projects
		if (isset($equals_filters['projectid']))
		{
			// Do a query for adding the project group
			$projectgroup = $vbulletin->db->query_first("
				SELECT projectgroupid
				FROM " . TABLE_PREFIX . "pt_project
				WHERE projectid = " . $equals_filters['projectid'] . "
			");

			$projectids = $equals_filters['projectid'];
		}
		else
		{
			$projectlist = array();

			foreach ($vbulletin->pt_projects AS $projectgroupid => $projectgroupprojectid)
			{
				foreach ($projectgroupprojectid AS $notused => $projectid)
				{
					$projectlist[] = $projectid;
				}
			}

			$projectids = array_keys($projectlist);
		}

		$excluded_projectids = array();

		if (isset($notequals_filter['projectid']))
		{
			$excluded_projectids = $notequals_filter['projectid'];
		}

		$project_where = build_issue_permissions_query($vbulletin->userinfo);

		foreach ($excluded_projectids AS $exclude)
		{
			unset($project_where["$exclude"]);
		}

		$results = array();

		if (empty($project_where))
		{
			return $results;
		}

		$project_where = "((" . implode(") OR (", $project_where) . "))";
		build_issue_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $devnull);

		$lastpost_col = ($private_lastpost_join ? 'IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost)' : 'issue.lastpost');

		if (!empty($range_filters['markinglimit'][0]))
		{
			$cutoff = $range_filters['markinglimit'][0];

			$marking_join = "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			";

			$lastpost_where = "
				AND ($lastpost_col > IF(issueread.readtime AND issueread.readtime > $cutoff, issueread.readtime, $cutoff)
				AND $lastpost_col > IF(projectread.readtime AND issueread.readtime > $cutoff, projectread.readtime, $cutoff)
				AND $lastpost_col > $cutoff)
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
			$lastpost_where = "AND $lastpost_col >= $datecut";
		}

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
				WHERE $project_where
					$lastpost_where
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
			$issues = $db->query_read_slave($q = "
				SELECT issue.issueid
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				$marking_join
				$private_lastpost_join
				WHERE $project_where
					$lastpost_where
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
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 18:15, Fri Sep 3rd 2010
|| # SVN: $Revision$
|| ####################################################################
\*======================================================================*/
