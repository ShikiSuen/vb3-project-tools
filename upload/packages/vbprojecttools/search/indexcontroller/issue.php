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
require_once(DIR . '/vb/search/core.php');

/**
 * @package vBulletin Project Tools
 * @subpackage Search
 * @author $Author$
 * @version $Revision$
 * @since $Date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

/**
 * Index controller for issues
 *
 * @package vBulletin Project Tools
 * @subpackage Search
 */
class vBProjectTools_Search_IndexController_Issue extends vB_Search_IndexController
{
	// We need to set the content types. This is available in a static method as below
	public function __construct()
	{
		$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Issue");
		$this->groupcontenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Project");
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

		$issue = $vbulletin->db->query_first("
			SELECT
				issue.issueid, issue.title, issue.summary, issue.projectid, issue.submituserid, issue.submitusername, issue.submitdate, issue.projectid, note.pagetext
			FROM " . TABLE_PREFIX . "pt_issue AS issue
				LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS note ON (note.issueid = issue.issueid)
			WHERE issue.issueid = " . intval($id) . "
			ORDER BY note.issuenoteid ASC
			LIMIT 1
		");

		if ($issue)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->issue_to_indexfields($issue);
			$indexer->index($fields);
		}
	}

	/**
	* Index a range of Issues
	*
	* @param	mixed		First document to index
	* @param	mixed		Last document to index
	*/
	public function index_id_range($start, $end)
	{
		global $vbulletin;

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();

		$set = $vbulletin->db->query("
			SELECT issue.issueid, issue.title, issue.summary, issue.submituserid, issue.submitusername, issue.submitdate, issue.projectid, note.pagetext, note.ipaddress
			FROM " . TABLE_PREFIX . "pt_issue AS issue
				LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS note ON (note.issueid = issue.issueid)
			WHERE issue.issueid >= " . intval($start) . "
				AND issue.issueid <= " . intval($end) . "
			ORDER BY issue.issueid, note.issuenoteid ASC
		");

		// The database would allow multiple notes per issue. We only want to index the
		// first one. We could do a correlated subquery in the sql, but
		// that is probably more expensive than discarding the duplicateresults here.
		$processed = array();

		while ($row = $vbulletin->db->fetch_array($set))
		{
			// The assumption that cached thread lookups were fast enough seems to have been good.
			// however the memory requirements for long ranges added up fast, so we'll try pulling
			// the appropriate fields in one step.
			$fields = $this->issue_to_indexfields($row);

			if ($fields)
			{
				if (in_array($fields['issueid'], $processed))
				{
					continue;
				}

				$processed[$fields['issueid']] = 1;
				$indexer->index($fields);
				$this->range_indexed++;
			}
		}
		$vbulletin->db->free_result($set);
	}

	/**
	* Remove the issue id from the index
	*
	* @param	int		Id of issue to delete
	*/
	public function delete($id)
	{
		vB_Search_Core::get_instance()->get_core_indexer()->delete($this->get_contenttypeid(), $id);
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
	* Reindex all the project data for issues in that project
	*
	* By default, this calls index_project.  This is included so that search
	* implementations can potentially implement a more efficient approach when
	* they know that Issue data hasn't changed.
	*
	* @param	int		threadid
	*/
	public function project_data_change($id)
	{
		return $this->index_project($id);
	}

	public function group_data_change($id)
	{
		return $this->index_project($id);
	}

	/**
	* Merge one or more project into a new projectid.
	*
	* By default, this simply calls index_project on the new project.
	*
	* @param	int		The old projectids that were merged
	* @param	int		The projectid the threads where merged to
	*/
	public function merge_group($oldid, $newid)
	{
		//all of the Issues from the old project should be in the
		//new project.  As a result, if we ignore the old project entirely
		//and reindex the new project the index will be updated.
		$this->index_project($newid);
	}

	/**
	* Delete all of the Issues in a project.
	*
	* By default this looks up all of the Issue ids in a project and
	* calls delete for each one
	*
	* @param	int		Project id
	*/
	public function delete_project($id)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read("
			SELECT issueid
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE projectid = " . intval($id)
		);

		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->delete($row['issueid']);
		}
	}

	// *********************************************************************************
	// Private functions

	/**
	* Convert a issue object into the fieldset for the indexer
	*
	* @todo		document	fields passed to indexer
	* @param	array		Issue object
	*
	* @return	array		The index fields
	*/
	private function issue_to_indexfields($issue)
	{
		$fields = array();

		//common fields
		$fields['contenttypeid'] = $this->contenttypeid;
		$fields['id'] = $issue['issueid'];
		$fields['dateline'] = $issue['submitdate'];

		if ($issue['summary'])
		{
			$fields['keywordtext'] = $issue['summary'] . ' : ' . $issue['pagetext'];
		}
		else
		{
			$fields['keywordtext'] = $issue['pagetext'];
		}

		$fields['title'] = $issue['title'];
		$fields['userid'] = $issue['submituserid'];
		$fields['username'] = $issue['submitusername'];
		$fields['groupcontenttypeid'] = $this->groupcontenttypeid;
		$fields['groupid'] = $issue['projectid'];
		$fields['ipaddress'] = $issue['ipaddress'];

		return $fields;
	}

	protected $issue_fields = array('');
	protected $project_fields = array();

	protected $contenttypeid;
	protected $groupcontenttypeid;
}

?>