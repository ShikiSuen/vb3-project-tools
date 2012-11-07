<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.4                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
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
* Class to do data save/delete operations for PT issue statuses.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueStatus extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuestatusid'    => array(TYPE_UINT, REQ_INCR),
		'issuetypeid'      => array(TYPE_STR,  REQ_YES),
		'displayorder'     => array(TYPE_UINT, REQ_NO),
		'canpetitionfrom'  => array(TYPE_BOOL, REQ_NO),
		'issuecompleted'   => array(TYPE_BOOL, REQ_NO),
		'statuscolor'      => array(TYPE_STR, REQ_NO),
		'projectset'       => array(TYPE_STR, REQ_NO),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'title' => null, // name for the default phrase
		'delete_deststatusid' => 0, // if deleting, ID of status to move all affected issues to
		'rebuild_caches' => true,
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuestatus';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuestatus = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuestatusid = %1$d', 'issuestatusid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueStatus(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuestatusdata_start')) ? eval($hook) : false;
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

		if (!$this->condition AND $this->info['title'] === null)
		{
			$this->error('please_complete_required_fields');
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuestatusdata_presave')) ? eval($hook) : false;

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

		$title = ($this->info['title'] !== null ? $this->info['title'] : $this->existing['title']);

		$db =& $this->registry->db;
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'issuestatus" . $this->fetch_field('issuestatusid') . "',
					'" . $db->escape_string($title) . "',
					'vbprojecttools',
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $db->escape_string($product_version) . "'
				)
		");


		if ($this->info['rebuild_caches'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();

			require_once(DIR . '/includes/adminfunctions_projecttools.php');
			build_issue_type_cache();
			
			rebuild_project_counters(false);
			rebuild_milestone_counters(false);
		}

		($hook = vBulletinHook::fetch_hook('pt_issuestatusdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Any code to run before deleting.
	*/
	function pre_delete()
	{
		if (!$this->registry->db->query_first("
			SELECT issuestatusid
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
				AND issuestatusid <> " . $this->fetch_field('issuestatusid') . "
			LIMIT 1
		"))
		{
			// no other statuses in this type, don't let the save go through
			$this->error('type_must_have_one_status');
			return false;
		}

		if ($project = $this->registry->db->query_first("
				SELECT project.title_clean
				FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
				INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = projecttype.projectid)
				WHERE projecttype.startstatusid = " . $this->fetch_field('issuestatusid') . "
					AND projecttype.issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
			"))
		{
			// this is part of a project start state, so we can't change it to complete
			$this->error('project_x_using_status_start_state', $project['title_clean']);
			return false;
		}

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->registry->db->query_first("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'issuestatus" . $this->fetch_field('issuestatusid') . "'
				AND fieldname = 'projecttools'
		");

		if ($this->info['rebuild_caches'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();

			require_once(DIR . '/includes/adminfunctions_projecttools.php');
			build_issue_type_cache();
		}

		// update any issues with this status...
		if ($this->info['delete_deststatusid'] AND $dest_status = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuestatusid = " . intval($this->info['delete_deststatusid']) . "
				AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
		"))
		{
			// ... to the destination status
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					issuestatusid = $dest_status[issuestatusid]
				WHERE issuestatusid = " . $this->fetch_field('issuestatusid')
			);
		}
		else
		{
			// ... or, if we don't know a destination, the default start state
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue AS issue
				INNER JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON
					(projecttype.projectid = issue.projectid
					AND projecttype.issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "')
				SET issue.issuestatusid = projecttype.startstatusid
				WHERE issue.issuestatusid = " . $this->fetch_field('issuestatusid')
			);
		}
		
		if ($this->info['rebuild_caches'])
		{
			rebuild_project_counters(false);
			rebuild_milestone_counters(false);
		}

		($hook = vBulletinHook::fetch_hook('pt_issuestatusdata_delete')) ? eval($hook) : false;
		return true;
	}
}
?>
