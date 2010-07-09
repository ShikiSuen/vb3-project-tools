<?php
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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for PT issue votes.
*
* @package	vBulletin Project Tools
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_Pt_IssueVote extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuevoteid' => array(TYPE_UINT,     REQ_INCR),
		'userid'      => array(TYPE_UINT,     REQ_NO),
		'ipaddress'   => array(TYPE_UINT,     REQ_NO),
		'issueid'     => array(TYPE_UINT,     REQ_YES),
		'dateline'    => array(TYPE_UNIXTIME, REQ_AUTO),
		'vote'        => array(TYPE_STR,      REQ_YES, 'if ($data != "positive") { $data = "negative"; } return true;')
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
	var $table = 'pt_issuevote';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuevote = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuevoteid = %1$d ', 'issuevoteid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueVote(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuevotedata_start')) ? eval($hook) : false;
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

		if (!$this->fetch_field('userid') AND !$this->fetch_field('ipaddress'))
		{
			$this->error('fieldmissing');
			return false;
		}

		// NOTE: vote switching; updates are completely prevented with this current code
		if ($this->fetch_field('userid'))
		{
			$existing = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issuevote
				WHERE issueid = " . intval($this->fetch_field('issueid')) . "
					AND userid = " . $this->fetch_field('userid')
			);
			if ($existing)
			{
				$this->error('useralreadyvote');
				return false;
			}
		}
		else
		{
			$existing = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issuevote
				WHERE issueid = " . intval($this->fetch_field('issueid')) . "
					AND ipaddress = " . intval($this->fetch_field('ipaddress')) . "
					AND userid = 0
			");
			if ($existing)
			{
				$this->error('useralreadyvote');
				return false;
			}
		}

		if (!$this->condition AND empty($this->pt_issuevote['dateline']))
		{
			// select the dateline automatically if not specified and not updating
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuevotedata_presave')) ? eval($hook) : false;

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
		$fieldname = 'vote' . $this->fetch_field('vote');

		if (!$this->condition)
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					$fieldname = $fieldname + 1
				WHERE issueid = " . intval($this->fetch_field('issueid'))
			);
		}

		($hook = vBulletinHook::fetch_hook('pt_issuevotedata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$fieldname = 'vote' . $this->fetch_field('vote');

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				$fieldname = CAST($fieldname AS SIGNED) - 1
			WHERE issueid = " . intval($this->fetch_field('issueid'))
		);

		($hook = vBulletinHook::fetch_hook('pt_issuevotedata_delete')) ? eval($hook) : false;
		return true;
	}
}
?>
