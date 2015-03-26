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

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/vb/search/result.php');
require_once(DIR . '/packages/vbprojecttools/search/result/issue.php');

/**
 * Enter description here...
 *
 * @package vBulletin Project Tools
 * @subpackage Search
 */
class vBProjectTools_Search_Result_IssueNote extends vB_Search_Result
{
	public static function create($id)
	{
		return vBProjectTools_Search_Result_IssueNote::create_from_object(vB_Legacy_IssueNote::create_from_id($id, true));
	}

	public static function create_from_object($issuenote)
	{
		if ($issuenote)
		{
			$item = new vBProjectTools_Search_Result_IssueNote($issuenote);
			return $item;
		}
		else
		{
			return new vB_Search_Result_Null();
		}
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'IssueNote');
	}

	public function can_search($user)
	{
		return $this->issuenote->can_search($user);
	}

	public function get_group_item()
	{
		return vBProjectTools_Search_Result_Issue::create_from_issue($this->issuenote->get_project());
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin, $vbphrase, $show;

		fetch_phrase_group('projecttools');
		fetch_phrase_group('search');

		if (!strlen($template_name))
		{
			$template_name = 'search_results_ptissuenote';
		}

		$issuenote = $this->issuenote->get_record();
		$issue = $this->issuenote->get_issue()->get_record();

		static $projectperms = array();

		if (!isset($projectperms["$issue[projectid]"]))
		{
			$projectperms["$issue[projectid]"] = fetch_project_permissions($vbulletin->userinfo, $issue['projectid']);
		}

		// Do a query for adding the project group
		$projectgroup = $vbulletin->db->query_first("
			SELECT projectgroupid
			FROM " . TABLE_PREFIX . "pt_project
			WHERE projectid = " . $issue['projectid'] . "
		");

		$project = $vbulletin->pt_projects[$projectgroup['projectgroupid']]['projects'][$issue['projectid']];
		$issueperms = $projectperms["$issue[projectid]"]["$issue[issuetypeid]"];
		$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

		$show['edit_issue'] = $posting_perms['issue_edit'];
		$show['status_edit'] = $posting_perms['status_edit'];

		$issue = prepare_issue($results);

		$issue['issuenoteid'] = $this->issuenote['issuenoteid'];

		($hook = vBulletinHook::fetch_hook('projectsearch_results_bit')) ? eval($hook) : false;

		$template = vB_Template::create($template_name);
			$template->register('issuenote', $issuenote);
			$template->register('issue', $issue);
			$template->register('project', $project);
		return $template->render();
	}

	private $issuenote;
}

?>