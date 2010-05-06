<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| #  This is file is subject to the vBulletin Open Source License.   # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 30444 $
 * @since $Date: 2009-04-23 15:02:08 -0700 (Thu, 23 Apr 2009) $
 * @copyright Jelsoft Enterprises Ltd.
 */


/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBProjectTools_Search_Result_Issue extends vB_Search_Result
{
	public static function create($id)
	{
		$newIssue = new vBProjectTools_Search_Result_Issue();
		$newIssue->issueid = $id;
		return $newIssue;
	}


	protected function __construct()
	{
		$this->contenttypeid = vB_Types::instance()->getContentTypeId("vBProjectTools_Issue");
	}

	public function get_contenttype()
	{
		return $this->issueid;
	}

	public function can_search($user)
	{
		require_once(DIR . '/includes/functions_projecttools.php');
		return verify_issue_perms($this->issueid, $userinfo);
	}

	public function get_group_item()
	{
		return vBProjectTools_Search_Result_Project::create($this->get_projectid());
	}

	public function render($current_user, $criteria)
	{
		global $vbulletin, $vbphrase, $show;

//		$template = vB_Template::create('search_results_postbit');
//		return $template->render();
		return "<b>Got one</b><br />\n";
	}



	public function get_issue()
	{
		global $vbulletin;
		if (! isset($this->issue))
		{
			$this->issue = $vbulletin->db->query_first("SELECT issue.* from "
				. TABLE_PREFIX . "pt_issue AS issue where issueid = " . $this->issueid);
		}
		return $this->issue;
	}

	public function get_projectid()
	{
		$issue = $this->get_issue();
		return $issue['projectid'];
	}


	private $issue;
	protected $contenttypeid;
	protected $issueid;
}

