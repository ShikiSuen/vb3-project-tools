<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
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

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/vb/search/result.php');

/**
* Enter description here...
*
* @package vBulletin Project Tools
* @subpackage Search
*/
class vBProjectTools_Search_Result_Issue extends vB_Search_Result
{
	public static function create($id)
	{
		require_once(DIR . '/vb/legacy/issue.php');

		if ($issue = vB_Legacy_Issue::create_from_id($id))
		{
			$item = new vBProjectTools_Search_Result_Issue();
			$item->issue = $issue;
			return $item;
		}

		// If we get here, the id must be invalid
		require_once(DIR . '/vb/search/result/null.php');
		return new vB_Search_Result_Null();
	}

	public static function create_from_issue($issue)
	{
		if ($issue)
		{
			$item = new vBProjectTools_Search_Result_Issue();

			// If we just have an id, we need to create the
			// object
			$item->issue = $issue;
			return $item;
		}
		else
		{
			require_once(DIR . '/vb/search/result/null.php');
			return new vB_Search_Result_Null();
		}
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'Issue');
	}

	public function can_search($user)
	{
		return $this->issue->can_search($user);
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin, $vbphrase, $show;

		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('projecttools'));

		if (!strlen($template_name))
		{
			$template_name = 'search_results_ptissue';
		}

		$datastores = $vbulletin->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN('pt_bitfields', 'pt_permissions', 'pt_issuestatus', 'pt_issuetype', 'pt_projects', 'pt_categories', 'pt_assignable', 'pt_versions')
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
				LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON (issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON (issueassign.issueid = issue.issueid AND issueassign.userid = " . $vbulletin->userinfo['userid'] . ")
			" : '') . "
			" . ($marking ? "
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread AS projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			" : '') . "
			$private_lastpost_join
			$hook_query_joins
			WHERE issue.issueid = " . $this->issue['issueid'] . "
				AND ((" . implode(') OR (', $search_perms) . "))
				$hook_query_where
			LIMIT $perpage
		");

		static $projectperms = array();

		$issue = $this->get_issue($results['issueid']);

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

		$template = vB_Template::create($template_name);
			$template->register('issue', $issue);
			$template->register('project', $project);
		return $template->render();
	}

	private function get_issue($id)
	{
		global $vbulletin;

		$issue = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE issueid = $id
		");

		return $issue;
	}

	private $issue;
}

?>