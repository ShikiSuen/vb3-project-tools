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

if (!class_exists('vB_Search_Core', false))
{
	exit;
}

require_once(DIR . '/vb/search/indexcontroller.php');
require_once(DIR . '/vb/legacy/issue.php');
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
			SELECT MAX(issueid) AS max
			FROM " . TABLE_PREFIX . "pt_issue"
		);

		return $row['max'];
	}

	/**
	* Index the Issue
	*
	* @param	int		Id of the issue to index
	*/
	public function index($id)
	{
		global $vbulletin;

		$issue = vB_Legacy_IssueNote::create_from_id($id, true);

		if ($issue)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->issuenote_to_indexfields($issue);
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

		$issuenote_fields = vB_Legacy_IssueNote::get_fields_names();
		$issue_fields = vB_Legacy_Issue::get_fields_names();

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
			FROM " . TABLE_PREFIX . "pt_issue AS issue
				LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issueid = issue.issueid)
			WHERE issue.issueid >= " . intval($start) . "
				AND issue.issueid <= " . intval($end) . "
			ORDER BY issue.issueid, issuenote.issuenoteid ASC
		");

		while ($row = $vbulletin->db->fetch_array($set))
		{
			// The assumption that cached thread lookups were fast enough seems to have been good.
			// however the memory requirements for long ranges added up fast, so we'll try pulling
			// the appropriate fields in one step.
			$fields = $this->issue_to_indexfields($row);

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

		$set = $vbulletin->db->query_read("
			SELECT issuenote.*
			FROM " . TABLE_PREFIX . "issuenote AS issuenote
			WHERE issuenote.issueid = " . intval($id)
		);

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$fields = $this->issuenote_to_indexfields($row);

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
		$fields = array();

		//common fields
		$fields['contenttypeid'] = $this->contenttypeid;
		$fields['id'] = $issuenote['issuenoteid'];
		$fields['dateline'] = $issuenote['submitdate'];

		if ($issue['summary'])
		{
			$fields['keywordtext'] = $issuenote['summary'] . ' : ' . $issuenote['pagetext'];
		}
		else
		{
			$fields['keywordtext'] = $issuenote['pagetext'];
		}

		$fields['title'] = $issuenote['title'];
		$fields['userid'] = $issuenote['submituserid'];
		$fields['username'] = $issuenote['submitusername'];
		$fields['groupcontenttypeid'] = $this->groupcontenttypeid;
		$fields['groupid'] = $issuenote['projectid'];
		$fields['ipaddress'] = $issuenote['ipaddress'];

		return $fields;
	}

	protected $contenttypeid;
	protected $groupcontenttypeid;
}

?>