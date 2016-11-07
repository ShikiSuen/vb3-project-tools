<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
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
* Class to do data save/delete operations for PT milestones.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_Milestone extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'milestoneid'   => array(TYPE_UINT,       REQ_INCR),
		'title_clean'   => array(TYPE_NOHTMLCOND, REQ_AUTO),
		'projectid'     => array(TYPE_UINT,       REQ_YES),
		'targetdate'    => array(TYPE_UNIXTIME,   REQ_NO),
		'completeddate' => array(TYPE_UNIXTIME,   REQ_NO),
		'displayorder'	=> array(TYPE_UINT,		  REQ_NO),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'title'			=> null,
		'description'	=> null,

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
		'title' => 'milestone_%d_name',
		'description' => 'milestone_%d_description'
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_milestone';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_milestone = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('milestoneid = %1$d', 'milestoneid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_milestonedata_start')) ? eval($hook) : false;
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

		if (isset($this->pt_milestone['title']))
		{
			$this->set('title_clean', htmlspecialchars_uni($this->pt_milestone['title']));
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_milestonedata_presave')) ? eval($hook) : false;

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

		foreach ($this->info_phrase AS $info_name => $phrase_name)
		{
			if ($this->info["$info_name"] !== null)
			{
				$phrase = sprintf($phrase_name, $this->fetch_field('milestoneid'));

				// Phrase for milestones
				$this->registry->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product, username, dateline, version)
					VALUES
						(
							0,
							'projecttools',
							'" . $this->registry->db->escape_string($phrase) . "',
							'" . $this->registry->db->escape_string($this->info["$info_name"]) . "',
							'vbprojecttools',
							'" . $this->registry->db->escape_string($this->registry->userinfo['username']) . "',
							" . TIMENOW . ",
							'" . $this->registry->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
						)
				");
			}
		}

		if ($this->info['rebuild_caches'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();
		}

		if (!$this->condition)
		{
			$this->rebuild_project_milestone_counters();
		}

		($hook = vBulletinHook::fetch_hook('pt_milestonedata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$del_phrases = array();

		foreach ($this->info_phrase AS $phrase_name)
		{
			$del_phrases[] = sprintf($phrase_name, intval($this->fetch_field('milestoneid')));
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname IN ('" . implode('", "', $del_phrases) . "')
				AND fieldname = 'projecttools'
		");

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_milestonetypecount
			WHERE milestoneid = " . $this->fetch_field('milestoneid')
		);

		if ($this->info['rebuild_caches'])
		{
			require_once(DIR . '/includes/adminfunctions_language.php');
			build_language();
		}

		$this->rebuild_project_milestone_counters();

		($hook = vBulletinHook::fetch_hook('pt_milestonedata_delete')) ? eval($hook) : false;
		return true;
	}

	/**
	* Rebuilds the counters relating to issues within this milestone.
	*/
	function rebuild_milestone_counters()
	{
		$counts = array();
		$count_data = $this->registry->db->query_read("
			SELECT issue.issuetypeid,
				COUNT(IF(issuestatus.issuecompleted = 0 AND issue.visible = 'visible', 1, NULL)) AS activepublic,
				COUNT(IF(issuestatus.issuecompleted = 0 AND issue.visible = 'private', 1, NULL)) AS activeprivate,
				COUNT(IF(issuestatus.issuecompleted = 1 AND issue.visible = 'visible', 1, NULL)) AS completepublic,
				COUNT(IF(issuestatus.issuecompleted = 1 AND issue.visible = 'private', 1, NULL)) AS completeprivate
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			INNER JOIN " . TABLE_PREFIX . "pt_issuestatus AS issuestatus ON
				(issue.issuestatusid = issuestatus.issuestatusid)
			WHERE issue.milestoneid = " . $this->fetch_field('milestoneid') . "
				AND issue.projectid = " . $this->fetch_field('projectid') . "
				AND issue.visible IN ('visible', 'private')
			GROUP BY issue.issuetypeid
		");
		while ($count = $this->registry->db->fetch_array($count_data))
		{
			$counts["$count[issuetypeid]"] = $count;
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_milestonetypecount
			WHERE milestoneid = " . $this->fetch_field('milestoneid')
		);

		$issuetype_data = $this->registry->db->query_read("
			SELECT issuetypeid
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = " . $this->fetch_field('projectid')
		);
		while ($issuetype = $this->registry->db->fetch_array($issuetype_data))
		{
			$typecounts = $counts["$issuetype[issuetypeid]"];

			$this->registry->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "pt_milestonetypecount
					(milestoneid, issuetypeid, activepublic, activeprivate, completepublic, completeprivate)
				VALUES
					(" . $this->fetch_field('milestoneid') . ",
					'" . $this->registry->db->escape_string($issuetype['issuetypeid']) . "',
					" . intval($typecounts['activepublic']) . ",
					" . intval($typecounts['activeprivate']) . ",
					" . intval($typecounts['completepublic']) . ",
					" . intval($typecounts['completeprivate']) . ")
			");
		}
	}

	/**
	* Rebuild project milestone counters.
	*/
	function rebuild_project_milestone_counters()
	{
		$count = $this->registry->db->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE projectid = " . $this->fetch_field('projectid')
		);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_project SET
				milestonecount = " . intval($count['count']) . "
			WHERE projectid = " . $this->fetch_field('projectid')
		);

		require_once(DIR . '/includes/adminfunctions_projecttools.php');
		build_project_cache();
	}
}

?>