<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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

require_once(DIR . '/vb/search/result.php');
require_once(DIR . '/includes/functions_projecttools.php');

/**
 * Enter description here...
 *
 * @package vBulletin Project Tools
 * @subpackage Search
 */
class vBProjectTools_Search_Result_Project extends vB_Search_Result
{
	public static function create($id)
	{
		$result = new vBProjectTools_Search_Result_Project();
		$result->projectid = $id;
		return $result;
	}

	protected function __construct() {}


	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Project");
	}

	public function can_search($user)
	{
		return true;
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin, $vbphrase, $show;

		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('projecttools'));

		// We only have projectid, so displaying project id line
		$project = $vbulletin->pt_projects["$this->projectid"];

		// type counts
		$perms_query = build_issue_permissions_query($vbulletin->userinfo);
		if (empty($perms_query))
		{
			print_no_permission();
		}

		build_project_private_lastpost_sql_all($vbulletin->userinfo,
			$private_lastpost_join, $private_lastpost_fields
		);

		$project_types = array();
		$project_types_query = $vbulletin->db->query_read("
			SELECT projecttype.*
				" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
			FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
				INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuetype.issuetypeid = projecttype.issuetypeid)
				$private_lastpost_join
			WHERE projecttype.projectid = " . $this->projectid . "
			ORDER BY issuetype.displayorder
		");
		while ($project_type = $vbulletin->db->fetch_array($project_types_query))
		{
			$project_types["$project_type[projectid]"][] = $project_type;
		}

		if (!isset($perms_query["$project[projectid]"]) OR !is_array($project_types["$project[projectid]"]) OR $project['displayorder'] == 0)
		{
			continue;
		}

		$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
		$project['lastpost'] = 0;
		$show['private_lastpost'] = false;
		$project['newflag'] = false;

		$type_counts = array();
		foreach ($project_types["$project[projectid]"] AS $type)
		{
			if (!($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
			{
				continue;
			}

			if ($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['cansearch'])
			{
				$show['search_options'] = true;
			}

			if ($type['lastpost'] > $project['lastpost'])
			{
				$project['lastpost'] = $type['lastpost'];
				$project['lastpostuserid'] = $type['lastpostuserid'];
				$project['lastpostusername'] = $type['lastpostusername'];
				$project['lastpostid'] = $type['lastpostid'];
				$project['lastissueid'] = $type['lastissueid'];
				$project['lastissuetitle'] = $type['lastissuetitle'];

				$show['private_lastpost'] = (($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']) ? false : true);
			}

			$type['name'] = $vbphrase["issuetype_$type[issuetypeid]_plural"];
			$type['issuecount'] = vb_number_format($type['issuecount']);
			$type['issuecountactive'] = vb_number_format($type['issuecountactive']);

			if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
			{
				$projettypeview = max($type['projectread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
			}
			else
			{
				$projettypeview = intval(fetch_bbarray_cookie('project_lastview', $project['projectid'] . $type['issuetypeid']));
				if (!$projettypeview)
				{
					$projettypeview = $vbulletin->userinfo['lastvisit'];
				}
			}
			if ($type['lastpost'] > $projettypeview)
			{
				$type['newflag'] = true;
				$project['newflag'] = true;
			}
			$project['projectread'] = max($project['projectread'], $projettypeview);

			$type['countid'] = "project_typecount_$project[projectid]_$type[issuetypeid]";

			$type_counts[] = $type;
		}

		if (!$type_counts)
		{
			continue;
		}

		$template = vB_Template::create('search_results_ptproject');
			$template->register('project', $project);
			$template->register('type_counts', $type_counts);
		return $template->render();
	}
}

?>