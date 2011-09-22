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
		'magicselectid'			=> array(TYPE_UINT,	REQ_INCR),
		'text'					=> array(TYPE_STR,	REQ_NO),
		'displayorder'			=> array(TYPE_UINT,	REQ_NO),
		'projects'				=> array(TYPE_STR,	REQ_NO),
		'itemtype'				=> array(TYPE_STR,	REQ_NO),
		'data'					=> array(TYPE_STR,	REQ_NO)
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
	var $table = 'pt_magicselect';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('magicselectid = %1$d', 'magicselectid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_MagicSelect(&$registry, $errtype = ERRTYPE_CP)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_magicselect_start')) ? eval($hook) : false;
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

		if (empty($this->info['text']))
		{
			$this->error('missing_text');
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_magicselect_presave')) ? eval($hook) : false;

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
		// replace (master) phrase entry
		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);
		$product_version = $full_product_info['vbprojecttools']['version'];

		$db =& $this->registry->db;
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'magicselect" . $this->fetch_field('pt_magicselectid') . "',
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

		($hook = vBulletinHook::fetch_hook('pt_magicselect_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$magicselectid = intval($this->fetch_field('magicselectid'));
		$db =& $this->registry->db;

		// Phrase
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'magicselect" . $magicselectid . "'
		");

		// Rebuild language
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		($hook = vBulletinHook::fetch_hook('pt_magicselect_delete')) ? eval($hook) : false;
		return true;
	}
}
?>