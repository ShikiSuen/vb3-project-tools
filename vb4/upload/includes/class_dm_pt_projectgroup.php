<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
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
* @package		vBulletin Project Tools
* @since		$Date: 2013-02-28 00:21:29 +0100 (Thu, 28 Feb 2013) $
* @version		$Rev: 781 $
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_ProjectGroup extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'projectid'				=> array(TYPE_UINT, REQ_INCR),
		'displayorder'			=> array(TYPE_UINT, REQ_NO),
		'title'					=> array(TYPE_STR, REQ_YES),
		'title_clean'			=> array(TYPE_NOHTMLCOND, REQ_AUTO),
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
	var $table = 'pt_projectgroup';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_projectgroup = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('projectgroupid = %1$d', 'projectgroupid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		//($hook = vBulletinHook::fetch_hook('pt_projectgroupdata_start')) ? eval($hook) : false;
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

		if (isset($this->pt_projectgroup['title']))
		{
			$this->set('title_clean', htmlspecialchars_uni($this->pt_projectgroup['title']));
		}

		$return_value = true;
		//($hook = vBulletinHook::fetch_hook('pt_projectgroupdata_presave')) ? eval($hook) : false;

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
		//($hook = vBulletinHook::fetch_hook('pt_projectgroupdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		//($hook = vBulletinHook::fetch_hook('pt_projectgroupdata_delete')) ? eval($hook) : false;

		return true;
	}
}

?>