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
		'pt_magicselectid'		=> array(TYPE_UINT,	REQ_INCR),
		'text'					=> array(TYPE_STR,	REQ_NO),
		'displayorder'			=> array(TYPE_UINT,	REQ_NO),
		'projects'				=> array(TYPE_STR,	REQ_NO)
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
	var $condition_construct = array('pt_magicselectid = %1$d', 'pt_magicselectid');

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
		$projectid = intval($this->fetch_field('pt_magicselectid'));
		$db =& $this->registry->db;

		// project related data
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projecttypeprivatelastpost
			WHERE projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projectpermission
			WHERE projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectid = $projectid
		");

		// MySQL 4 needs to use the non-aliased tables in multi-table deletes (#23024)
		// No longer needed as of PT 2.1.x/vB 4.0.x. (#100)
		// $mysqlversion = $db->query_first("SELECT version() AS version");
		// $include_prefix = version_compare($mysqlversion['version'], '4.1.0', '<');

		// clear out all the issue data
		$db->query_write("
			DELETE issueassign
			FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issueassign.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issueattach
			FROM " . TABLE_PREFIX . "pt_issueattach AS issueattach
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issueattach.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuechange
			FROM " . TABLE_PREFIX . "pt_issuechange AS issuechange
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuechange.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuesubscribe
			FROM " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesubscribe.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuetag
			FROM " . TABLE_PREFIX . "pt_issuetag AS issuetag
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuetag.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuevote
			FROM " . TABLE_PREFIX . "pt_issuevote AS issuevote
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuevote.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuedeletionlog
			FROM " . TABLE_PREFIX . "pt_issuedeletionlog AS issuedeletionlog
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuedeletionlog.primaryid AND issuedeletionlog.type = 'issue')
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issuenote
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuenote.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE issueprivatelastpost
			FROM " . TABLE_PREFIX . "pt_issueprivatelastpost AS issueprivatelastpost
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issueprivatelastpost.issueid)
			WHERE issue.projectid = $projectid
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issue
			WHERE projectid = $projectid
		");

		require_once(DIR . '/includes/adminfunctions_projecttools.php');
		build_project_cache();
		build_version_cache();
		build_assignable_users(); // builds bitfields and perms as well
		build_pt_user_list('pt_report_users', 'pt_report_user_cache');

		($hook = vBulletinHook::fetch_hook('pt_magicselect_delete')) ? eval($hook) : false;
		return true;
	}
}
?>