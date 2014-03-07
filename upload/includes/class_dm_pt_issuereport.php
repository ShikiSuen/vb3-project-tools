<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
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
* Class to do data save/delete operations for PT issue reports.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueReport extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuereportid' => array(TYPE_UINT, REQ_INCR),
		'title'         => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD, 'verify_nonempty'),
		'description'   => array(TYPE_NOHTMLCOND, REQ_NO),
		'public'        => array(TYPE_UINT, REQ_NO, 'if ($data != 1) { $data = 0; } return true;'),
		'userid'        => array(TYPE_UINT, REQ_NO),
		'criteria'      => array(TYPE_STR, REQ_YES),
		'sortby'        => array(TYPE_STR, REQ_YES),
		'sortorder'     => array(TYPE_STR, REQ_YES, 'if (strtolower($data) != "desc") { $data = "asc"; } return true;'),
		'groupby'       => array(TYPE_STR, REQ_YES),
		'projectlist'   => array(TYPE_STR, REQ_AUTO),
		'issuetypelist' => array(TYPE_STR, REQ_AUTO)
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'subscribe_searchid' => 0
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuereport';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuereport = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuereportid = %1$d', 'issuereportid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueReport(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuereportdata_start')) ? eval($hook) : false;
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

		if (!empty($this->pt_issuereport['criteria']) AND $criteria = @unserialize($this->pt_issuereport['criteria']))
		{
			$this->set('projectlist', !empty($criteria['projectid']) ? implode(',', $criteria['projectid']) : '');
			$this->set('issuetypelist', !empty($criteria['issuetypeid']) ? implode(',', $criteria['issuetypeid']) : '');
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuereportdata_presave')) ? eval($hook) : false;

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
		if (!$this->condition AND $this->info['subscribe_searchid'])
		{
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "pt_issuereportsubscribe
					(userid, issuereportid, issuesearchid)
				VALUES
					(" . intval($this->fetch_field('userid')) . ",
					" . intval($this->fetch_field('issuereportid')) . ",
					" . intval($this->fetch_field($this->info['subscribe_searchid'])) . ")
			");
		}

		($hook = vBulletinHook::fetch_hook('pt_issuereportdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issuereportsubscribe
			WHERE issuereportid = " . intval($this->fetch_field('issuereportid'))
		);

		($hook = vBulletinHook::fetch_hook('pt_issuereportdata_delete')) ? eval($hook) : false;
		return true;
	}
}

?>