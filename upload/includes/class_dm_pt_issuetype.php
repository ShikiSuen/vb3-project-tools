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
* Class to do data save/delete operations for PT issue types.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueType extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuetypeid'   => array(TYPE_STR,         REQ_YES, VF_METHOD),
		'displayorder'  => array(TYPE_UINT,        REQ_NO),
		'iconfile'      => array(TYPE_NOHTMLCOND,  REQ_NO),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		// these are all phrases that are associated with issue types - see $info_phrase for a lookup table
		'title_singular' => null,
		'title_plural' => null,
		'vote_question' => null,
		'vote_count_positive' => null,
		'vote_count_negative' => null,
		'applies_version' => null,
		'addressed_version' => null,
		'post_new_issue' => null,

		'delete_deststatusid' => 0, // if deleting, ID of status to move all affected issues to
		'rebuild_caches' => true,
	);

	/**
	* The relationship between the phrases in $info and their actual names.
	* Values are passed through sprintf and %s is replaced with the issuetypeid.
	*
	* @param	array	Key: $info key, value: phrase name
	*/
	var $info_phrase = array(
		'title_singular' => 'issuetype_%s_singular',
		'title_plural' => 'issuetype_%s_plural',
		'vote_question' => 'vote_question_%s',
		'vote_count_positive' => 'vote_count_positive_%s',
		'vote_count_negative' => 'vote_count_negative_%s',
		'applies_version' => 'applies_version_%s',
		'addressed_version' => 'addressed_version_%s',
		'post_new_issue' => 'post_new_issue_%s',
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuetype';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuetype = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuetypeid = \'%1$s\'', 'issuetypeid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueType(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuetypedata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies an issue type ID is valid
	*
	* @param	string	Issue type ID
	*
	* @return	bool
	*/
	function verify_issuetypeid(&$issuetypeid)
	{
		$issuetypeid = preg_replace('#[^a-z0-9_]#i', '', $issuetypeid);
		if (!$issuetypeid)
		{
			$this->error('please_complete_required_fields');
			return false;
		}

		if (!$this->condition OR $issuetypeid != $this->existing['issuetypeid'])
		{
			$issuetype = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issuetype
				WHERE issuetypeid = '" . $this->registry->db->escape_string($issuetypeid) . "'
			");
			if ($issuetype)
			{
				$this->error('issue_type_already_exists');
				return false;
			}
		}

		return true;
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

		if (!$this->condition AND ($this->info['title_singular'] === null OR $this->info['title_plural'] === null))
		{
			$this->error('please_complete_required_fields');
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuetypedata_presave')) ? eval($hook) : false;

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

		foreach ($this->info_phrase AS $info_name => $phrase_name)
		{
			if ($this->info["$info_name"] !== null)
			{
				$phrase = sprintf($phrase_name, $this->fetch_field('issuetypeid'));

				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						(
							0,
							'projecttools',
							'" . $db->escape_string($phrase) . "',
							'" . $db->escape_string($this->info["$info_name"]) . "',
							'vbprojecttools',
							'" . $db->escape_string($this->registry->userinfo['username']) . "',
							" . TIMENOW . ",
							'" . $db->escape_string($product_version) . "'
						)
				");
			}
		}

		if ($this->info['rebuild_caches'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();

			require_once(DIR . '/includes/adminfunctions_projecttools.php');
			build_issue_type_cache();
		}

		($hook = vBulletinHook::fetch_hook('pt_issuetypedata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Any code to run before deleting.
	*/
	function pre_delete()
	{
		if (!$this->registry->db->query_first("
			SELECT issuetypeid
			FROM " . TABLE_PREFIX . "pt_issuetype
			WHERE issuetypeid <> '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
			LIMIT 1
		"))
		{
			// no other types, don't let the save go through
			$this->error('must_have_one_type');
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
		$db =& $this->registry->db;

		$escaped_type = $db->escape_string($this->fetch_field('issuetypeid'));

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projectpermission WHERE issuetypeid = '$escaped_type'
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projecttype WHERE issuetypeid = '$escaped_type'
		");
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_projecttypeprivatelastpost WHERE issuetypeid = '$escaped_type'
		");

		$del_phrases = array();
		foreach ($this->info_phrase AS $phrase_name)
		{
			$del_phrases[] = sprintf($phrase_name, $escaped_type);
		}

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname IN ('" . implode('", "', $del_phrases) . "')
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
		"))
		{
			// ... to the destination status
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					issuestatusid = $dest_status[issuestatusid],
					issuetypeid = '$dest_status[issuetypeid]'
				WHERE issuetypeid = '$escaped_type'
			");
		}

		($hook = vBulletinHook::fetch_hook('pt_issuetypedata_delete')) ? eval($hook) : false;
		return true;
	}
}
?>
