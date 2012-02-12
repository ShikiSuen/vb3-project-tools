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
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_Project extends vB_DataManager
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
		'summary'				=> array(TYPE_STR, REQ_NO),
		'summary_clean'			=> array(TYPE_NOHTMLCOND, REQ_AUTO),
		'description'			=> array(TYPE_STR, REQ_NO),
		'options'				=> array(TYPE_UINT, REQ_NO), 
		'requireappliesversion'	=> array(TYPE_UINT, REQ_NO),
		'requirecategory'		=> array(TYPE_UINT, REQ_NO),
		'requirepriority'		=> array(TYPE_UINT, REQ_NO),
		'afterforumids'			=> array(TYPE_STR, REQ_NO, VF_METHOD, 'verify_commalist'),
		'forumtitle'			=> array(TYPE_STR, REQ_NO)
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
	var $table = 'pt_project';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_project = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('projectid = %1$d', 'projectid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_Project(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_projectdata_start')) ? eval($hook) : false;
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

		if (isset($this->pt_project['title']))
		{
			$this->set('title_clean', htmlspecialchars_uni($this->pt_project['title']));
		}

		if (isset($this->pt_project['summary']))
		{
			$this->set('summary_clean', htmlspecialchars_uni($this->pt_project['summary']));
		}

		if (isset($this->pt_project['afterforumids']))
		{
			$afterforumids = explode(',', $this->pt_project['afterforumids']);
			if ($this->pt_project['afterforumids'] === '' OR !$afterforumids OR in_array(-1, $afterforumids))
			{
				// should be empty
				$this->pt_project['afterforumids'] = '';
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_projectdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Called internally, to update the global setting that determines whether any
	* projects will be shown in the forum list.
	*
	* @param	bool	Whether this project wants to display itself in the forum list
	*/
	function update_project_forum_setting($display_forum)
	{
		$db =& $this->registry->db;

		if ($display_forum)
		{
			// the setting needs to be enabled
			$new_value = '1';
		}
		else
		{
			// check if there are others with this set, if not turn vb option off (else turn it on)
			if ($db->query_first("
				SELECT projectid
				FROM " . TABLE_PREFIX . "pt_project
				WHERE afterforumids <> ''
					AND projectid <> " . intval($this->fetch_field('projectid')) . "
				LIMIT 1
			"))
			{
				$new_value = '1';
			}
			else
			{
				$new_value = '0';
			}
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting SET
				value = '" . $db->escape_string($new_value) . "'
			WHERE varname = 'pt_hasprojectforums'
		");

		build_options();
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if (isset($this->pt_project['afterforumids']))
		{
			$this->update_project_forum_setting(!empty($this->pt_project['afterforumids']));
		}

		($hook = vBulletinHook::fetch_hook('pt_projectdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$projectid = intval($this->fetch_field('projectid'));
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

		$this->update_project_forum_setting(false);

		($hook = vBulletinHook::fetch_hook('pt_projectdata_delete')) ? eval($hook) : false;
		return true;
	}

	/**
	* Rebuilds the counters for this issue. Must call save() explicitly afterwards.
	*/
	function rebuild_project_counters()
	{
		if (!$this->condition OR !$this->fetch_field('projectid'))
		{
			trigger_error("You cannot call rebuild_project_counters without a proper condition.", E_USER_ERROR);
		}

		$db =& $this->registry->db;

		$type_counts = array();
		$types = $db->query_read("
			SELECT issuetypeid
			FROM " . TABLE_PREFIX . "pt_issuetype
		");
		while ($type = $db->fetch_array($types))
		{
			$type_counts["$type[issuetypeid]"] = array('count' => 0, 'lastactivity' => 0);
		}

		$counters = $db->query_read("
			SELECT issue.issuetypeid,
				COUNT(*) AS issuecount,
				COUNT(IF(issuestatus.issuecompleted <> 1, 1, NULL)) AS issuecountactive,
				MAX(issue.lastactivity) AS lastactivity
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			LEFT JOIN " . TABLE_PREFIX . "pt_issuestatus AS issuestatus ON (issue.issuestatusid = issuestatus.issuestatusid)
			WHERE issue.visible = 'visible'
				AND issue.projectid = " . $this->fetch_field('projectid') . "
			GROUP BY issue.issuetypeid
		");
		while ($counter = $db->fetch_array($counters))
		{
			$type_counts["$counter[issuetypeid]"] = array(
				'issuecount' => $counter['issuecount'],
				'issuecountactive' => $counter['issuecountactive'],
				'lastactivity' => $counter['lastactivity'],
			);

			if ($counter['issuecount'])
			{
				$lastissue = $db->query_first("
					SELECT issue.*
					FROM " . TABLE_PREFIX . "pt_issue AS issue
					WHERE issue.visible = 'visible'
						AND issue.projectid = " . $this->fetch_field('projectid') . "
						AND issue.issuetypeid = '" . $db->escape_string($counter['issuetypeid']) . "'
					ORDER BY issue.lastpost DESC
					LIMIT 1
				");

				$type_counts["$counter[issuetypeid]"]['lastpost'] = $lastissue['lastpost'];
				$type_counts["$counter[issuetypeid]"]['lastpostuserid'] = $lastissue['lastpostuserid'];
				$type_counts["$counter[issuetypeid]"]['lastpostusername'] = $lastissue['lastpostusername'];
				$type_counts["$counter[issuetypeid]"]['lastpostid'] = $lastissue['lastnoteid'];
				$type_counts["$counter[issuetypeid]"]['lastissueid'] = $lastissue['issueid'];
				$type_counts["$counter[issuetypeid]"]['lastissuetitle'] = $lastissue['title'];
			}
		}

		foreach ($type_counts AS $issuetypeid => $counter)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projecttype SET
					issuecount = " . intval($counter['issuecount']) . ",
					issuecountactive = " . intval($counter['issuecountactive']) . ",
					lastactivity = " . intval($counter['lastactivity']) . ",
					lastpost = " . intval($counter['lastpost']) . ",
					lastpostuserid = " . intval($counter['lastpostuserid']) . ",
					lastpostusername = '" . $db->escape_string($counter['lastpostusername']) . "',
					lastpostid = " . intval($counter['lastpostid']) . ",
					lastissueid = " . intval($counter['lastissueid']) . ",
					lastissuetitle = '" . $db->escape_string($counter['lastissuetitle']) . "'
				WHERE projectid = " . $this->fetch_field('projectid') . "
					AND issuetypeid = '" . $db->escape_string($issuetypeid) . "'
			");
		}

		$this->rebuild_private_lastpost();
	}

	/**
	* Rebuilds the issueprivatelastpost table for this issue.
	*/
	function rebuild_private_lastpost()
	{
		$projectid = $this->fetch_field('projectid');

		if (!$projectid)
		{
			return;
		}

		$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_projecttypeprivatelastpost WHERE projectid = $projectid");

		// look for latest private replies and public reply info from private issues.
		// grab newest first and don't overwrite on conflict.
		$this->registry->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "pt_projecttypeprivatelastpost
				(projectid, issuetypeid,
				lastpost, lastpostuserid, lastpostusername, lastpostid,
				lastissueid, lastissuetitle)
			(
				SELECT issue.projectid, issue.issuetypeid,
					issueprivatelastpost.lastpost, issueprivatelastpost.lastpostuserid,
					issueprivatelastpost.lastpostusername, issueprivatelastpost.lastnoteid AS lastpostid,
					issue.issueid, issue.title AS lastissuetitle
				FROM " . TABLE_PREFIX . "pt_issueprivatelastpost AS issueprivatelastpost
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON
					(issue.issueid = issueprivatelastpost.issueid)
				INNER JOIN " . TABLE_PREFIX . "pt_projecttype AS projecttype ON
					(projecttype.projectid = issue.projectid AND projecttype.issuetypeid = issue.issuetypeid)
				WHERE issue.projectid = $projectid
					AND issueprivatelastpost.lastpost >= issue.lastpost
					AND issueprivatelastpost.lastpost >= projecttype.lastpost
			)
			UNION
			(
				SELECT issue.projectid, issue.issuetypeid,
					issue.lastpost, issue.lastpostuserid,
					issue.lastpostusername, issue.lastnoteid AS lastpostid,
					issue.issueid, issue.title AS lastissuetitle
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				WHERE issue.projectid = $projectid
					AND issue.visible = 'private'
			)
			ORDER BY lastpost DESC
		");
	}
}
?>