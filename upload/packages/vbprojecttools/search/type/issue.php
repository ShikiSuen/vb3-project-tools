<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
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
	/**
	* This checks to see if we can view this issue.
	*
	* @param	vB_Legacy_Object	$issue
	* @param	vB_Legacy_Object	$user
	*
	* @return  boolean
	**/
	protected function verify_issue_canread(&$issue, &$user)
	{
		global $vbulletin;

		fetch_pt_datastore();

		return verify_issue_perms($issue->get_record(), $user->get_record());
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
		require_once(DIR . '/vb/legacy/issue.php');
		$issues = vB_Legacy_Issue::create_array($ids);

		foreach ($issues AS $key => $issue)
		{
			if (!$this->verify_issue_canread($issue, $user))
			{
				$rejected_groups[] = $key;
				$list["$key"] = false;
			}
			else
			{
				$list["$key"] = vBProjectTools_Search_Result_Issue::create_from_issue($issue);
			}
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