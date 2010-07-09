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
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision$
 * @since $Date$
 * @copyright Jelsoft Enterprises Ltd.
 */

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/vb/search/result.php');
require_once(DIR . '/packages/vbprojecttools/search/result/project.php');

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
		return verify_issue_perms($this->issueid, $userinfo);
	}

	public function get_group_item()
	{
		return vBProjectTools_Search_Result_Project::create($this->get_projectid());
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin, $vbphrase, $show;

		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('projecttools'));

		if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
		{
			print_no_permission();
		}

		($hook = vBulletinHook::fetch_hook('projectsearch_results_start')) ? eval($hook) : false;

		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		if (!$vbulletin->GPC['start'])
		{
			$vbulletin->GPC['start'] = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
		}

		if (!$perpage)
		{
			$perpage = 999999;
		}

		build_issue_private_lastpost_sql_all($vbulletin->userinfo, $private_lastpost_join, $private_lastpost_fields);

		$replycount_clause = fetch_private_replycount_clause($vbulletin->userinfo);

		$show['first_group'] = true;
		$resultgroupbits = '';

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('projectsearch_results_query')) ? eval($hook) : false;

		$results = $vbulletin->db->query_first("
			SELECT issue.*
				" . ($vbulletin->userinfo['userid'] ? ", issuesubscribe.subscribetype, IF(issueassign.issueid IS NULL, 0, 1) AS isassigned" : '') . "
				" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
				" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			" . ($vbulletin->userinfo['userid'] ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON
					(issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON
					(issueassign.issueid = issue.issueid AND issueassign.userid = " . $vbulletin->userinfo['userid'] . ")
			" : '') . "
			" . ($marking ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			" : '') . "
			$private_lastpost_join
			$hook_query_joins
			WHERE issue.issueid = $this->issueid
				AND ((" . implode(') OR (', $search_perms) . "))
				$hook_query_where
			LIMIT $perpage
		");

		static $projectperms = array();

		$issue = $this->get_issue();

		if (!isset($projectperms["$issue[projectid]"]))
		{
			$projectperms["$issue[projectid]"] = fetch_project_permissions($vbulletin->userinfo, $issue['projectid']);
		}

		$project = $vbulletin->pt_projects["$issue[projectid]"];
		$issueperms = $projectperms["$issue[projectid]"]["$issue[issuetypeid]"];
		$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

		$show['edit_issue'] = $posting_perms['issue_edit'];
		$show['status_edit'] = $posting_perms['status_edit'];

		$issue = prepare_issue($issue);

		($hook = vBulletinHook::fetch_hook('projectsearch_results_bit')) ? eval($hook) : false;

		$template = vB_Template::create('search_results_ptissue');
			$template->register('issue', $issue);
			$template->register('project', $project);
		return $template->render();
	}

	public function get_issue()
	{
		global $vbulletin;

		if (!isset($this->issue))
		{
			$this->issue = $vbulletin->db->query_first("
				SELECT issue.*
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				WHERE issueid = " . $this->issueid
			);
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