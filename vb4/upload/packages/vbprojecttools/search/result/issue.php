<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
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

		fetch_pt_datastore();
		$issue = $this->issue->get_record();

		static $projectperms = array();

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

	private $issue;
}

?>