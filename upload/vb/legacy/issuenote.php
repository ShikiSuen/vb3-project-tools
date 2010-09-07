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
require_once(DIR . '/vb/legacy/issue.php');

/**
 * @package vBulletin Project Tools
 * @subpackage Legacy
 * @author $author$
 * @version $revision$
 * @since $date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

/**
 * Legacy functions for issue notes
 *
 * @package vBulletin Project Tools
 * @subpackage Legacy
 */
class vB_Legacy_IssueNote extends vB_Legacy_DataObject
{
	/**
	* Get issue note fields
	*
	* @return 	array	Array list of the pt_issuenote table
	*/
	public static function get_field_names()
	{
		return array(
			'issuenoteid', 'issueid', 'dateline', 'pagetext', 'userid', 'username', 'type', 'ispending',
			'visible', 'lasteditdate', 'isfirstnote', 'ipaddress', 'reportthreadid'
		);
	}

	/**
	* Load object from an id
	*
	* @param	integer		Id of the issuenote
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
	* Select all informations for issue notes from the database
	* With corresponding issueids
	*
	* @param	array		Array of issuenote ids
	*
	* @return	array		Array of issuenote informations
	*/
	public static function create_array($ids)
	{
		global $vbulletin;

		$select = array();
		$joins = array();
		$where = array();

		$select[] = "issuenote.*";
		$where[] = "issuenote.issuenoteid IN (" . implode(',', array_map('intval', $ids) . ")";

		$set = $vbulletin->db->query("
			SELECT " . implode(",", $select) . "
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				" . implode("\n", $joins) . "
			WHERE " . implode (' AND ', $where) . "
		");

		$issuenotes = array();
		while ($issuenote = $vbulletin->db->fetch_array($set))
		{
			$issuenotes[$issuenote['issuenoteid']] = $issuenote;
		}

		return $issuenotes;
	}
}

?>