<?php
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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for PT issue petitions.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssuePetition extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuenoteid'      => array(TYPE_UINT,     REQ_YES),
		'petitionstatusid' => array(TYPE_UINT,     REQ_YES),
		'resolution'       => array(TYPE_STR,      REQ_YES, 'if (!in_array($data, array("pending", "accepted", "rejected", "cancelled"))) { $data = "pending"; } return true;'),
		'resolveuserid'    => array(TYPE_UINT,     REQ_NO),
		'resolvedate'      => array(TYPE_UNIXTIME, REQ_AUTO)
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'auto_issue_update' => true
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuepetition';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuepetition = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuenoteid = %1$d', 'issuenoteid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssuePetition(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuepetitiondata_start')) ? eval($hook) : false;
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

		if (empty($this->pt_issuepetition['resolvedate']) AND $this->fetch_field('resolution') != 'pending')
		{
			// select the dateline automatically if not specified and not updating
			$this->set('resolvedate', TIMENOW);
		}

		switch ($this->fetch_field('resolution'))
		{
			case 'pending':
				// pending is by definition not resolved...
				$this->set('resolveuserid', 0);
				$this->set('resolvedate', 0);
				break;

			case 'accepted':
			case 'rejected':
				if (!$this->fetch_field('resolveuserid'))
				{
					$this->set('resolveuserid', $this->registry->userinfo['userid']);
				}
				if (!$this->fetch_field('resolvedate'))
				{
					$this->set('resolvedate', TIMENOW);
				}
				break;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuepetitiondata_presave')) ? eval($hook) : false;

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
		$old_resolution = $this->existing['resolution'];
		$new_resolution = $this->fetch_field('resolution');

		if ($old_resolution != $new_resolution  AND ($new_resolution == 'pending' OR $old_resolution == 'pending'))
		{
			// changed resolutions, may need to update issue table's pending petition count
			$issue = $this->registry->db->query_first("
				SELECT issue.*
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuenote.issueid)
				WHERE issuenote.issuenoteid = " . intval($this->fetch_field('issuenoteid'))
			);
			if ($issue)
			{
				$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_STANDARD);
				$issuedata->set_existing($issue);
				if ($new_resolution == 'pending')
				{
					// newly pending - increment
					$issuedata->set('pendingpetitions', 'pendingpetitions + 1', false);
				}
				else if ($old_resolution == 'pending')
				{
					// no longer pending - decrement
					$issuedata->set('pendingpetitions', 'CAST(pendingpetitions AS SIGNED) - 1', false);
				}
				$issuedata->save();
			}
		}

		// if we moved from pending to accept, change the status
		if ($this->info['auto_issue_update'] AND $old_resolution == 'pending' AND $new_resolution == 'accepted')
		{
			$issue = $this->registry->db->query_first("
				SELECT issue.*
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON
					(issuenote.issueid = issue.issueid)
				WHERE issuenote.issuenoteid = " . intval($this->fetch_field('issuenoteid'))
			);
			if ($issue)
			{
				$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_SILENT);
				$issuedata->set_existing($issue);
				$issuedata->set('issuestatusid', $this->fetch_field('petitionstatusid'));
				$issuedata->save();
			}
		}

		($hook = vBulletinHook::fetch_hook('pt_issuepetitiondata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('pt_issuepetitiondata_delete')) ? eval($hook) : false;
		return true;
	}
}

?>