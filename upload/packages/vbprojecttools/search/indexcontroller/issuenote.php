<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

if (!class_exists('vB_Search_Core', false))
{
	exit;
}

require_once(DIR . '/vb/search/indexcontroller.php');
require_once(DIR . '/vb/legacy/issuenote.php');
require_once(DIR . '/vb/search/core.php');

/**
* @package vBulletin Project Tools
* @subpackage Search
* @author $Author
* @version $Revision$
* @since $Date$
* @copyright http://www.vbulletin.org/open_source_license_agreement.php
*/

/**
* Index controller for issue notes
*
* @package vBulletin Project Tools
* @subpackage Search
*/
class vBProjectTools_Search_IndexController_IssueNote extends vB_Search_IndexController
{
	// We need to set the content types. This is available in a static method as below
	public function __construct()
	{
		$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "IssueNote");
		$this->groupcontenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Issue");
	}

	/**
	* Return the maximum id for the item type
	*
	* Should be overridden by the specific type function.  If it is not
	* then loop through to the max id logic won't work correctly.
	*
	* @return	int		Max id value
	*/
	public function get_max_id()
	{
		global $vbulletin;

		$row = $vbulletin->db->query_first_slave("
			SELECT MAX(issuenoteid) AS max
			FROM " . TABLE_PREFIX . "pt_issuenote"
		);

		return $row['max'];
	}

	/**
	* Index the Issue
	*
	* @param	int		Id of the issue note to index
	*/
	public function index($id)
	{
		global $vbulletin;

		$issuenote = vB_Legacy_IssueNote::create_from_id($id, true);

		if ($issuenote)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->issuenote_to_indexfields($issuenote);
			$indexer->index($fields);
		}
	}

	/**
	* Index a range of Issue notes
	*
	* @param	mixed		First document to index
	* @param	mixed		Last document to index
	*/
	public function index_id_range($start, $end)
	{
		global $vbulletin;

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();

		$issuenote_fields = vB_Legacy_IssueNote::get_field_names();
		$issue_fields = vB_Legacy_Issue::get_field_names();

		$select = array();

		foreach ($issuenote_fields AS $field)
		{
			$select[] = 'issuenote.' . $field;
		}

		foreach ($issue_fields AS $field)
		{
			$select[] = 'issue.' . $field;
		}

		$set = $vbulletin->db->query("
			SELECT " . implode(', ', $select) . "
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issuenote.issueid = issue.issueid)
			WHERE issuenote.issuenoteid >= " . intval($start) . "
				AND issuenote.issuenoteid <= " . intval($end) . "
			ORDER BY issuenote.issueid, issuenote.issuenoteid ASC
		");

		while ($row = $vbulletin->db->fetch_row($set))
		{
			// The assumption that cached thread lookups were fast enough seems to have been good.
			// however the memory requirements for long ranges added up fast, so we'll try pulling
			// the appropriate fields in one step.

			$issuenote_data = array_combine($issuenote_fields, array_slice($row, 0, count($issuenote_fields)));
			$issue_data = array_combine($issue_fields, array_slice($row, count($issuenote_fields)));

			$issuenote = vB_Legacy_IssueNote::create_from_record($issuenote_data, $issue_data);

			$fields = $this->issuenote_to_indexfields($issuenote);

			if ($fields)
			{
				$indexer->index($fields);
				$this->range_indexed++;
			}
		}
		$vbulletin->db->free_result($set);
	}

	/**
	* Delete a range of Issues
	*
	* @param	int		First document to remove
	* @param	int		Last document to remove
	*/
	public function delete_id_range($start, $end)
	{
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		for ($i = $start; $i <= $end; $i++)
		{
			$indexer->delete($this->get_contentypeid(), $i);
		}
	}

	/**
	* Index an issue
	*
	* By default this will look up all of the issue notes in an issue and calls the core
	* indexer for each one
	*
	* @param	integer		Issue id
	*/
	public function index_issue($id)
	{
		throw new Exception ('should not be here');
		global $vbulletin;

		$issue = vB_Legacy_Issue::create_from_record($id);

		$set = $vbulletin->db->query_read("
			SELECT issuenote.*
			FROM " . TABLE_PREFIX . "issuenote AS issuenote
			WHERE issuenote.issueid = " . intval($id)
		);

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$issuenote = vB_Legacy_IssueNote::create_from_record($row, $issue);
			$fields = $this->issuenote_to_indexfields($issuenote);

			if ($fields)
			{
				$indexer->index($fields);
			}
		}
	}

	/**
	* Reindex all the project data for issue notes in that issue
	*
	* By default, this calls index_issue. This is included so that search
	* implementations can potentially implement a more efficient approach when
	* they know that issue note data hasn't changed.
	*
	* @param	integer		issueid
	*/
	public function issue_data_change($id)
	{
		return $this->index_issue($id);
	}

	public function group_data_change($id)
	{
		return $this->index_issue($id);
	}

	/**
	* Merge one or more issue into a new issue id.
	*
	* By default, this simply calls index_issue on the new issue.
	*
	* @param	integer		The old issueids that were merged
	* @param	integer		The issueid the issuess where merged to
	*/
	public function merge_group($oldid, $newid)
	{
		// All of the issue notes from the old issue should be in the
		// new issue. As a result, if we ignore the old issues entirely
		// and reindex the new issue the index will be updated.
		$this->index_issue($newid);
	}

	/**
	* Delete all of the issue notes in an issue.
	*
	* By default this looks up all of the issuenote ids in an issue and
	* calls delete for each one
	*
	* @param	integer		Issue id
	*/
	public function delete_issue($id)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read("
			SELECT issueid
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE issueid = " . intval($id)
		);

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->delete($row['issueid']);
		}
	}

	/**
	* Index all of the issue notes in a project.
	*
	* By default this looks up all of the issue ids in a project and calls index_thread on each one.
	*
	* @param	integer		Project id
	*/
	public function index_project($id)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read("
			SELECT issueid FROM " . TABLE_PREFIX . "pt_issue WHERE projectid = " . intval($id) . "
		");

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->index_issue($row['issueid']);
		}
	}

	/**
	* Handle reindexing for a project merge
	*
	* By default this reindexes the new project remaining after the merge
	*
	* @param	array		Projects eliminated due to the merge
	* @param	integer		Project remaining after the merge
	*/
	public function merge_projects($oldids, $newid)
	{
		$this->index_project($newid);
	}

	/**
	* Delete all issue notes for a project.
	*
	* By default this fetches all of the issue ids for a project and calls delete_issue
	* on each.
	*
	* @param	integer		Project id
	*/
	public function delete_project($id)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read("
			SELECT issueid FROM " . TABLE_PREFIX . "pt_issue WHERE projectid = " . intval($id) . "
		");

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->delete_issue($row['issueid']);
		}
	}

	// *********************************************************************************
	// Private functions

	/**
	* Convert a issuenote object into the fieldset for the indexer
	*
	* @todo		document	fields passed to indexer
	*
	* @param	array		Issuenote object
	*
	* @return	array		The index fields
	*/
	private function issuenote_to_indexfields($issuenote)
	{
		// Don't try to index inconsistent records
		$issue = $issuenote->get_issue();
		if (!$issue)
		{
			return false;
		}

		$project = $issue->get_project();
		if (!$project)
		{
			return false;
		}

		//common fields
		$fields['contenttypeid'] = $this->contenttypeid;

		$fields['id'] = $issuenote->get_field('issuenoteid');
		$fields['dateline'] = $issuenote->get_field('dateline');
		$fields['groupdateline'] = $issue->get_field('lastpost');
		$fields['defaultdateline'] = $fields['groupdateline'];
		$fields['grouptitle'] = $issue->get_field('title');
		$fields['userid'] = $issuenote->get_field('userid');
		$fields['groupuserid'] = $issue->get_field('submituserid');
		$fields['defaultuserid'] = $fields['user'];
		$fields['username'] = $issuenote->get_field('username');
		$fields['groupusername'] = $issue->get_field('submitusername');
		$fields['defaultusername'] = $fields['username'];
		$fields['ipaddress'] = $issuenote->get_ipstring();
		$fields['title'] = $issue->get_field('title');

		if ($issue->get_field('summary'))
		{
			$fields['keywordtext'] = $issue->get_field('title') . " " . $issue->get_field('summary') . ' : ' . $issuenote->get_field('pagetext');
		}
		else
		{
			$fields['keywordtext'] = $issue->get_field('title') . " " . $issuenote->get_field('pagetext');
		}

		$fields['groupcontenttypeid'] = $this->groupcontenttypeid;

		$fields['groupid'] = $issuenote->get_field('issueid');

		// additional issue note fields
		$fields['visible'] = $issuenote->get_field('visible');

		// issue fields
		$fields['issueid'] = $issue->get_field('issueid');
		$fields['replycount'] = $issue->get_field('replycount');
		$fields['lastpost'] = $issue->get_field('lastpost');
		$fields['issuevisible'] = $issue->get_field('visible');
		$fields['open'] = $issue->get_field('state');
		$fields['appliesversionid'] = $issue->get_field('appliesversionid');
		$fields['addressedversionid'] = $issue->get_field('addressedversionid');
		$fields['priority'] = $issue->get_field('priority');
		$fields['assignedusers'] = $issue->get_field('assignedusers');
		$fields['milestoneid'] = $issue->get_field('milestoneid');

		// project fields
		$fields['projectid'] = $project->get_field('projectid');
		$fields['projecttitle'] = $project->get_field('title');

		return $fields;
	}

	protected $contenttypeid;
	protected $groupcontenttypeid;
}

?>