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

require_once(DIR . '/vb/legacy/dataobject.php');
require_once(DIR . '/vb/legacy/project.php');

/**
 * @package vBulletin Project Tools
 * @subpackage Legacy
 * @author $author$
 * @version $revision$
 * @since $date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

/**
 * Legacy functions for issues
 *
 * @package vBulletin Project Tools
 * @subpackage Legacy
 */
class vB_Legacy_Issue extends vB_Legacy_DataObject
{
	/**
	* Get issue fields
	*
	* @return 	array	Array list of the pt_issue table
	*/
	public static function get_field_names()
	{
		return array(
			'issueid', 'projectid', 'issuestatusid', 'issuetypeid', 'title', 'summary', 'submituserid', 'submitusername', 'submitdate',
			'appliesversionid', 'isaddressed', 'addressedversionid', 'priority', 'visible', 'lastpost', 'lastactivity',
			'lastpostuserid', 'lastpostusername', 'firstnoteid', 'lastnoteid', 'attachcount', 'pendingpetitions', 'replycount',
			'votepositive', 'votenegative', 'projectcategoryid', 'assignedusers', 'privatecount', 'state', 'milestoneid'
		);
	}

	/**
	* Load object from an id
	*
	* @param	integer		Id of the issue
	* @return	array		Array of the result
	*/
	public static function create_from_id($id)
	{
		$list = array_values(self::create_array(array($id)));

		if (!count($list))
		{
			return null;
		}
		else
		{
			return array_shift($list);
		}
	}

	/**
	* Select all informations for issues from the database
	* With corresponding issueids
	*
	* @param	array		Array of issue ids
	*
	* @return	array		Array of issue informations
	*/
	public static function create_array($ids)
	{
		global $vbulletin;

		$select = array();
		$joins = array();
		$where = array();

		$select[] = "issue.*, note.pagetext";
		$joins[] = "LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS note ON (note.issueid = issue.issueid)";
		$where[] = "issue.issueid IN (" . implode(',', array_map('intval', $ids) . ")";

		$set = $vbulletin->db->query("
			SELECT " . implode(",", $select) . "
			FROM " . TABLE_PREFIX . "pt_issue AS issue
				" . implode("\n", $joins) . "
			WHERE " . implode (' AND ', $where) . "
		");

		$issues = array();
		while ($issue = $vbulletin->db->fetch_array($set))
		{
			$issues[$issue['issueid']] = $issue;
		}

		return $issues;
	}
}

?>