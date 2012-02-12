<?php
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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for PT issue assignments.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_MagicSelect extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'projectmagicselectid'		=> array(TYPE_UINT,		REQ_INCR),
		'title'						=> array(TYPE_NOHTML,	REQ_NO),
		'displayorder'				=> array(TYPE_UINT,		REQ_NO),
		'projectid'					=> array(TYPE_UINT,		REQ_NO),
		'value'						=> array(TYPE_UINT,		REQ_NO),
		'projectmagicselectgroupid'	=> array(TYPE_UINT,		REQ_NO)
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_projectmagicselect';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('projectmagicselectid = %1$d', 'projectmagicselectid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_MagicSelect(&$registry, $errtype = ERRTYPE_CP)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_project_magicselect_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (empty($this->info['title']))
		{
			$this->error('please_complete_required_fields');
			return false;
		}

		/*if ($this->fetch_field('value') != '' AND $this->fetch_field('value') == 0 OR empty($this->fetch_field('value')))
		{
			$this->error('value_must_be_higher_than_zero');
			return false;
		}*/

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_project_magicselect_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// create automatically the corresponding column in pt_issue table
		$db =& $this->registry->db;

		// replace (master) phrases entry
		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);
		$product_version = $full_product_info['vbprojecttools']['version'];

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'magicselect" . $this->fetch_field('projectmagicselectid') . "',
					'" . $db->escape_string($this->info['text']) . "',
					'vbprojecttools',
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $db->escape_string($product_version) . "'
				)
		");

		// Rebuild language
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		($hook = vBulletinHook::fetch_hook('pt_project_magicselect_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$db =& $this->registry->db;

		$magicselectid = intval($this->fetch_field('projectmagicselectid'));

		// Phrases
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'magicselect" . $magicselectid . "'
		");

		// Rebuild language
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		($hook = vBulletinHook::fetch_hook('pt_project_magicselect_delete')) ? eval($hook) : false;
		return true;
	}
}

class vB_DataManager_Pt_Issue_MagicSelect extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issueid'		=> array(TYPE_UINT,		REQ_YES),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuemagicselect';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issueid = %1$d', 'issueid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_Issue_MagicSelect(&$registry, $errtype = ERRTYPE_ARRAY)
	{
		parent::vB_DataManager($registry, $errtype);

		// Custom Magic Selects
		$magicselects = $this->registry->db->query_read("
			SELECT projectmagicselectgroupid
			FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		");

		while ($magicselect = $this->registry->db->fetch_array($magicselects))
		{
			$this->validfields['magicselect' . $magicselect['projectmagicselectgroupid']] = array(TYPE_UINT, REQ_NO);
			$this->track_changes[] = $magicselect['projectmagicselectgroupid'];
		}

		($hook = vBulletinHook::fetch_hook('pt_issue_magicselect_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issue_magicselect_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// create automatically the corresponding column in pt_issue table
		$db =& $this->registry->db;

		($hook = vBulletinHook::fetch_hook('pt_issue_magicselect_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$db =& $this->registry->db;

		($hook = vBulletinHook::fetch_hook('pt_issue_magicselect_delete')) ? eval($hook) : false;
		return true;
	}
}

?>