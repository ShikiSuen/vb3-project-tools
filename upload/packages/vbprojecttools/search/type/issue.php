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
 * @package vBulletin Project Tools
 * @subpackage Search
 * @author $Author$
 * @version $Revision$
 * @since $Date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

require_once(DIR . '/vb/search/type.php');
require_once(DIR . '/packages/vbprojecttools/search/result/issue.php');
require_once(DIR . '/includes/functions_projecttools.php');

/**
* There is a type file for each search type. This is the one for issues
*
* @package vBulletin Project Tools
* @subpackage Search
*/
class vBProjectTools_Search_Type_Issue extends vB_Search_Type
{
	/***
	* This checks to see if we can view this project.
	*
	* @param integer $projectid
	* @return  boolean
	**/
	private function verify_project_canread($projectid)
	{
		global $vbulletin;

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

		$permissions = fetch_project_permissions($vbulletin->userinfo, $projectid);

		//We get an array, like 'type=>array('perm_type' => 65555,...), ...
		// the types are the three (currently at least) issue types. perm_types
		// are currently generalpermissions, postpermissions, attachpermissions
		//I would say that if we have rights to one of the three issue types we
		// can view the project.

		if ($vbulletin->userinfo['projectpermissions'])
		{
			foreach ($vbulletin->userinfo['projectpermissions'] AS $pjid => $tasks)
			{
				foreach ($tasks AS $key => $value)
				{
					if ($value['generalpermissions'] > 0 OR $value['postpermissions'] > 0)
					{
						return true;
					}
				}
			}
		}

		/*if ($permissions)
		{
			foreach ($permissions AS $type => $permission)
			{
				if ($permission['projectpermissions']["$projectid"] > 0)
				{
					return true;
				}
			}
		}*/

		//If we got here we aren't authorized
		return false;
	}
	
	/**
	* When displaying results we get passed a list of id's. This
	* function determines which are viewable by the user.
	*
	* @param	object	User infos
	* @param	array	Issue note id's returned from a search
	* @param	array	Issue id's for the issue notes
	*
	* @return	array	(array of viewable issue notes, array of rejected issues)
	*/
	public function fetch_validated_list($user, $ids, $gids)
	{
		global $vbulletin;

		/*$map = array();

		foreach ($ids AS $i => $id)
		{
			$map[$gids[$i]][] = $id;
		}

		$issues = array_unique($gids);
		$rejected_issues = array();
		foreach ($issues as $issueid)
		{
			if (!$this->verify_project_canread($issueid))
			{
				$rejected_groups[] = $issueid;
			}
		}*/

		if (count($ids))
		{
			foreach ($ids AS $id => $issueid)
			{
				if ($issue = verify_issue($issueid, false))
				{
					$list[$issueid] = vBProjectTools_Search_Result_Issue::create($issueid);
				}
			}
		}

		$list = array_fill_keys($ids, false);
		foreach (vB_Legacy_Issue::create_array($ids) AS $key => $issue)
		{
			$item = vBProjectTools_Search_Result_Issue::create_from_issue($issue);

			$list[$key] = $item;
		}

		return array('list' => $list, 'groups_rejected' => $rejected_groups);
	}


	/**
	* Each search type has some responsibilities, one of which is to give
	* its display name.
	*
	* @return string
	*/
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_issues');
	}

	/**
	* This is how the type objects are created
	*
	* @param		integer		Id of the item
	*
	* @return		object		vBProjectTools_Search_Type_Issue object
	*/
	public function create_item($id)
	{
		return vBProjectTools_Search_Result_Issue::create($id);
	}

	/**
	* This indicates if the user can search for issues
	*
	* @param	mixed		User infos
	*
	* @return	mixed		Allowed search or not
	*/
	public function can_search($user)
	{
		return true;
	}

	protected $package = "vBProjectTools";
	protected $class = "Issue";
}

?>