<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision$
 * @since $Date$
 * @copyright Jelsoft Enterprises Ltd.
 */

require_once (DIR . "/vb/legacy/forum.php");
require_once (DIR."/vb/search/core.php");
/**
 * Index Controller for group Messages
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBProjectTools_Search_IndexController_Project extends vB_Search_IndexController
{
	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT MAX(projectid) AS max FROM " . TABLE_PREFIX . "pt_project"
		);
		return $row['max'];
	}


	public function index($id)
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave($this->make_query("Project.Projectid = " . intval($id)));
		if ($row)
		{
			$indexer = vB_Search_Core::get_instance()->get_core_indexer();
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}

	public function index_id_range($start, $finish)
	{
		global $vbulletin;
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$row = $vbulletin->db->query_read_slave($this->make_query("Project.Projectid BETWEEN " .
			intval($start) . " AND " . intval($finish)));

		while ($row = $vbulletin->db->fetch_row($set))
		{
			$fields = $this->record_to_indexfields($row);
			$indexer->index($fields);
		}
	}

	private function make_query($filter)
	{
		return "
		SELECT Project.Projectid, Project.title, Project.summary,
			Project.description, max(issue.lastpost) as dateline
			FROM " . TABLE_PREFIX . "pt_project as Project
			LEFT JOIN " . TABLE_PREFIX . "pt_issue issue on issue.projectid = Project.projectid
			WHERE $filter
			GROUP BY Project.Projectid, Project.title, Project.summary,
			Project.description;
		";
	}

	//We need to set the content types. This is available in a static method as
  // below
  public function __construct()
  {
     $this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Project");
  }

  /**
	 * Convert the basic table row to the index fieldset
	 *
	 * @param array $record
	 * @return return index fields
	 */
	private function record_to_indexfields($project)
	{
		//make it easy to switch default fields
		$default = '';

		//common fields
		$fields['contenttypeid'] = $this->get_contenttypeid();
		$fields['id'] = $project['Projectid'];
		$fields['groupid'] = 0;
		$fields['dateline'] = intval($project['dateline']) ?
			 $project['dateline'] : TIMENOW;
		$fields['userid'] = 0;
		$fields['username'] = '';
		$fields['ipaddress'] = '';
		$fields['title'] = $project['title'];
		$fields['keywordtext'] = $project['summary'] . ' : ' . $project['description'];
		return $fields;
	}

	protected $contenttypeid;
}


