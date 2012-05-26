<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
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
* Class to do data save/delete operations for PT issue notes (generic).
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueNote extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuenoteid'    => array(TYPE_UINT,       REQ_INCR),
		'issueid'        => array(TYPE_UINT,       REQ_YES),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'pagetext'       => array(TYPE_STR,        REQ_YES, VF_METHOD),
		'userid'         => array(TYPE_UINT,       REQ_NO),
		'username'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'type'           => array(TYPE_STR,        REQ_NO, 'if ($dm->condition AND $data != $dm->existing["type"]) { trigger_error("You cannot set the type of an issue note manually.", E_USER_ERROR); } return true;'),
		'ispending'      => array(TYPE_UINT,       REQ_NO, 'if ($data != 0) { $data = 1; } return true;'),
		'visible'        => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array("moderation", "visible", "private", "deleted"))) { $data = "visible"; } return true;'),
		'lasteditdate'   => array(TYPE_UNIXTIME,   REQ_NO),
		'isfirstnote'    => array(TYPE_UINT,       REQ_NO, 'if ($data != 0) { $data = 1; } return true;'),
		'ipaddress'      => array(TYPE_STR,        REQ_AUTO, VF_METHOD),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'skip_history' => false,
		'allow_type_change' => false,
		'do_floodcheck' => true,
		'do_dupecheck' => true,
		'reason' => '', // string, nohtml (stored htmlspecialchars'd)
		'parseurl' => false
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuenote';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuenote = array();

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
	function vB_DataManager_Pt_IssueNote(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuenotedata_start')) ? eval($hook) : false;
	}

	/**
	* Verify a guest's username as valid. Pretty much as we'd do any username.
	*
	* @param	string	Username
	*/
	function verify_guest_name(&$name)
	{
		$name = unhtmlspecialchars($name);
		return parent::verify_username($name);
	}

	/**
	* Turns a string IP (x.x.x.x) into an integer representation for efficient storage
	*
	* @param	string	String IP (turns into integer)
	*/
	function verify_ipaddress(&$ipaddress)
	{
		// Log IP Address only if defined in the corresponding vB option
		if ($this->registry->options['logip'])
		{
			// need to run it through sprintf to get the integer representation
			$ipaddress = sprintf('%u', ip2long($ipaddress));
		}
		else
		{
			// Do not save IP address - define $ipaddress as an empty variable
			$ipaddress = '';
		}

		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_pagetext(&$pagetext)
	{
		if (vbstrlen(strip_bbcode($pagetext, $this->registry->options['ignorequotechars'])) < 1)
		{
			$this->error('tooshort', 1);
			return false;
		}

		return parent::verify_pagetext($pagetext);
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

		// confirm userid/username combo
		if (isset($this->pt_issuenote['userid']) OR !$this->condition)
		{
			if ($this->pt_issuenote['userid'] == 0)
			{
				// guest, verify name if changed or inserting
				if (!$this->condition OR isset($this->pt_issuenote['username']))
				{
					$this->verify_guest_name($this->pt_issuenote['username']);
				}
			}
			else
			{
				// changing the userid, so get the name
				$userinfo = fetch_userinfo($this->pt_issuenote['userid']);
				if (!$userinfo)
				{
					// invalid user
					global $vbphrase;
					$this->error('invalid_username_specified');
					return false;
				}
				else
				{
					$this->do_set('username', $userinfo['username']);
				}
			}
		}

		// not allowed to change types explicitly
		if ($this->condition AND isset($this->pt_issuenote['type']) AND !$this->info['allow_type_change'])
		{
			$this->error('issue_type_change_not_allowed');
			return false;
		}

		// default dateline
		if (!$this->condition AND !$this->fetch_field('dateline'))
		{
			$this->set('dateline', TIMENOW);
		}

		// default ipaddress
		if (!$this->condition AND !$this->fetch_field('ipaddress'))
		{
			$this->set('ipaddress', IPADDRESS);
		}

		// last thing we can do -- dupe and flood check
		if (!$this->condition)
		{
			if ($this->info['do_floodcheck'] AND $this->registry->options['floodchecktime'] > 0 AND $this->fetch_field('userid') AND $this->is_flooding())
			{
				return false;
			}

			if ($this->info['do_dupecheck']AND $this->fetch_field('userid') AND $this->is_duplicate())
			{
				return false;
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuenotedata_presave')) ? eval($hook) : false;

		if ($this->condition AND $return_value AND !isset($this->pt_issuenote['lasteditdate']) AND empty($this->info['skip_history']) AND $this->fetch_field('type') != 'system')
		{
			$this->info['reason'] = $this->registry->input->clean($this->info['reason'], TYPE_NOHTMLCOND);

			$insert_history = ($this->info['reason'] !== '');

			if ((isset($this->pt_issuenote['pagetext']) AND $this->pt_issuenote['pagetext'] != $this->existing['pagetext']) OR (isset($this->pt_issuenote['visible']) AND $this->pt_issuenote['visible'] != $this->existing['visible']))
			{
				$insert_history = true;
			}

			if ($insert_history)
			{
				// updating an all of our checks went through, update last edit time
				$this->set('lasteditdate', TIMENOW);
			}
		}

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Determine if this post will violate the flood check. This does no permission
	* checks, so call only if necessary.
	*
	* @return	boolean	True if flooding
	*/
	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->registry->options['floodchecktime'];

		if ($this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "pt_issuenotehash
				WHERE userid = " . $this->fetch_field('userid') . "
					AND dateline > " . $floodmintime . "
				ORDER BY dateline DESC
				LIMIT 1
			");

			if ($flood)
			{
				$this->error('postfloodcheck', $this->registry->options['floodchecktime'], $flood['dateline'] - $floodmintime);
				return true;
			}
		}

		return false;
	}

	/**
	* Is this post a duplicate of an existing one? (Checks posts in the last 5 minutes)
	*
	* @return	boolean	True if duplicate
	*/
	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;

		if ($this->fetch_field('dateline') > $dupemintime)
		{
			$dupehash = md5($this->fetch_field('issueid') . $this->fetch_field('pagetext') . $this->fetch_field('userid') .  $this->fetch_field('visible'));

			$dupe = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "pt_issuenotehash
				WHERE userid = " . $this->fetch_field('userid') . "
					AND dateline > " . $dupemintime . "
					AND dupehash = '" . $this->registry->db->escape_string($dupehash) . "'
				ORDER BY dateline DESC
				LIMIT 1
			");

			if ($dupe)
			{
				$this->error('duplicate_post');
				return true;
			}
		}

		return false;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('pt_issuenotedata_postsave')) ? eval($hook) : false;

		if ($this->condition AND empty($this->info['skip_history']) AND $this->fetch_field('type') != 'system')
		{
			$this->info['reason'] = $this->registry->input->clean($this->info['reason'], TYPE_NOHTMLCOND);

			$insert_history = ($this->info['reason'] !== '');

			if ((isset($this->pt_issuenote['pagetext']) AND $this->pt_issuenote['pagetext'] != $this->existing['pagetext']) OR (isset($this->pt_issuenote['visible']) AND $this->pt_issuenote['visible'] != $this->existing['visible']))
			{
				$insert_history = true;
			}

			if ($insert_history)
			{
				// insert into history table if necessary
				// note that 'reason' field comes from $this->info['reason']
				$this->registry->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "pt_issuenotehistory
						(issuenoteid, reason, pagetext, visible, dateline, userid)
					VALUES
						(" . $this->fetch_field('issuenoteid') . ",
						'" . $this->registry->db->escape_string($this->info['reason']) . "',
						'" . $this->registry->db->escape_string($this->existing['pagetext']) . "',
						'" . $this->registry->db->escape_string($this->existing['visible']) . "',
						" . intval(max($this->existing['dateline'], $this->existing['lasteditdate'])) . ",
						" . intval($this->registry->userinfo['userid']) . ")
				");
			}
		}

		if (!$this->condition)
		{
			// insert - potentially add to counts
			$this->update_issue_counters(null, $this->pt_issuenote['visible']);

			if ($this->fetch_field('type') != 'system')
			{
				// insert the duplicate note hash
				$dupehash = md5($this->fetch_field('issueid') . $this->fetch_field('pagetext') . $this->fetch_field('userid') .  $this->fetch_field('visible'));

				$this->registry->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "pt_issuenotehash
						(issuenoteid, userid, issueid, dupehash, dateline)
					VALUES
						(" . $this->fetch_field('issuenoteid') . ",
						" . $this->fetch_field('userid') . ",
						" . $this->fetch_field('issueid') . ",
						'" . $this->registry->db->escape_string($dupehash) . "',
						" . $this->fetch_field('dateline') . ")
				");
			}

			// Activity stream
			if (version_compare($this->registry->options['templateversion'], '4.2', '>='))
			{
				$activity = new vB_ActivityStream_Manage('project', 'issuenote');
					$activity->set('contentid', $this->fetch_field('issuenoteid'));
					$activity->set('userid', $this->fetch_field('userid'));
					$activity->set('dateline', $this->fetch_field('dateline'));
					$activity->set('action', 'create');
				$activity->save();
			}
		}
		else if ($this->fetch_field('visible') != $this->existing['visible'] OR $this->fetch_field('type') != $this->existing['type'])
		{
			// updating an existing issue, let's redo the counters to be sure
			$issue = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issue
				WHERE issueid = " . intval($this->fetch_field('issueid'))
			);

			if ($issue)
			{
				$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_SILENT);
				$issuedata->set_existing($issue);
				$issuedata->rebuild_issue_counters();
				$issuedata->save();

				$project = $this->registry->db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "pt_project
					WHERE projectid = $issue[projectid]
				");

				if ($project)
				{
					$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_SILENT);
					$projectdata->set_existing($project);
					$projectdata->rebuild_project_counters();
					$projectdata->save();
				}
			}
		}

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBProjectTools', 'IssueNote', 'index', intval($this->fetch_field('issuenoteid')));

		return true;
	}

	/**
	* Deletes the specified data item from the database
	*
	* @return	integer	The number of rows deleted
	*/
	function delete($hard_delete = false)
	{
		if (empty($this->condition))
		{
			if ($this->error_handler == ERRTYPE_SILENT)
			{
				return false;
			}
			else
			{
				trigger_error('Delete SQL condition not specified!', E_USER_ERROR);
			}
		}
		else
		{
			if (!$this->pre_delete($doquery))
			{
				return false;
			}

			$this->info['hard_delete'] = $hard_delete;

			if ($this->info['hard_delete'])
			{
				$return = $this->db_delete(TABLE_PREFIX, $this->table, $this->condition, true);
			}
			else
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . $this->table . " SET
						visible = 'deleted'
					WHERE " . $this->condition
				);
			}

			$this->post_delete($doquery);
			return $return;
		}
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$issuenoteid = intval($this->fetch_field('issuenoteid'));
		$db =& $this->registry->db;

		if ($this->info['hard_delete'])
		{
			// this is a hard delete
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuechange WHERE issuenoteid = $issuenoteid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuedeletionlog WHERE primaryid = $issuenoteid AND type = 'issuenote'");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuenotehistory WHERE issuenoteid = $issuenoteid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuepetition WHERE issuenoteid = $issuenoteid");
		}
		else
		{
			// soft delete
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "pt_issuedeletionlog
					(primaryid, type, userid, username, reason, dateline)
				VALUES
					($issuenoteid,
					'issuenote',
					" . $this->registry->userinfo['userid'] . ",
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					'" . $db->escape_string($this->info['reason']) . "',
					" . TIMENOW . ")
			");
		}

		$issue = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE issueid = " . intval($this->fetch_field('issueid'))
		);

		if ($issue)
		{
			$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_SILENT);
			$issuedata->set_existing($issue);
			$issuedata->rebuild_issue_counters();
			$issuedata->save();

			$project = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_project
				WHERE projectid = $issue[projectid]
			");

			if ($project)
			{
				$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_SILENT);
				$projectdata->set_existing($project);
				$projectdata->rebuild_project_counters();
				$projectdata->save();
			}
		}

		require_once(DIR . '/vb/search/indexcontroller/queue.php');
		vb_Search_Indexcontroller_Queue::indexQueue('vBProjectTools', 'IssueNote', 'delete', intval($this->fetch_field('issuenoteid')));

		($hook = vBulletinHook::fetch_hook('pt_issuenotedata_delete')) ? eval($hook) : false;
		return true;
	}

	/**
	* Undeletes a soft-deleted note. Needs $this->existing to be set properly.
	*
	* @return	boolean	True if the undelete succeeded
	*/
	function undelete()
	{
		$issuenoteid = intval($this->fetch_field('issuenoteid'));

		if (!$issuenoteid)
		{
			return false;
		}

		$db =& $this->registry->db;

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuenote SET
				visible = 'visible'
			WHERE issuenoteid = $issuenoteid
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issuedeletionlog
			WHERE primaryid = $issuenoteid
				AND type = 'issuenote'
		");

		$issue = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE issueid = " . intval($this->fetch_field('issueid'))
		);

		if ($issue)
		{
			$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_SILENT);
			$issuedata->set_existing($issue);
			$issuedata->rebuild_issue_counters();
			$issuedata->save();

			$project = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_project
				WHERE projectid = $issue[projectid]
			");

			if ($project)
			{
				$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_SILENT);
				$projectdata->set_existing($project);
				$projectdata->rebuild_project_counters();
				$projectdata->save();
			}
		}

		($hook = vBulletinHook::fetch_hook('pt_issuenotedata_undelete')) ? eval($hook) : false;
		return true;
	}

	/**
	* Updates the counters of the associated issue based on old/new visibility values
	*
	* @param	string|null	old/existing visibility. Null if this is an insert
	* @param	string|null	new visiblity value. Null if this is a delete.
	*/
	function update_issue_counters($old_vis, $new_vis)
	{
		if ($old_vis == $new_vis)
		{
			// we didn't change any counters, do nothing
			return false;
		}

		$issue = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issue
			WHERE issueid = " . intval($this->fetch_field('issueid'))
		);

		if (!$issue)
		{
			return false;
		}

		$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_STANDARD);
		$issuedata->set_existing($issue);

		if ($issue['firstnoteid'] == 0)
		{
			// if firstnoteid is 0, then we are just creating this issue, so we don't need to get a reply count
			$issuedata->set('firstnoteid', $this->fetch_field('issuenoteid'));
		}
		else
		{
			if ($new_vis == 'visible')
			{
				// didn't have an old visibility (inserting) or the new value is visible
				// (implicitly, by the first if, the old visiblity is not visible) -- add
				$issuedata->set('replycount', 'replycount + 1', false, false);
			}
			else if ($old_vis == 'visible')
			{
				// no new visibility (deleting) or we're making a visible issue
				// invisible -- subtract
				$issuedata->set('replycount', 'IF(replycount > 0, replycount - 1, replycount)', false, false);
			}
	
			if ($new_vis == 'private')
			{
				$issuedata->set('privatecount', 'privatecount + 1', false, false);
			}
			else if ($old_vis == 'private')
			{
				$issuedata->set('privatecount', 'IF(privatecount > 0, privatecount - 1, privatecount)', false, false);
			}
		}

		if ($this->fetch_field('dateline') >= $issue['lastpost'])
		{
			if ($new_vis == 'visible')
			{
				$issuedata->set('lastnoteid', $this->fetch_field('issuenoteid'));
				$issuedata->set('lastpost', $this->fetch_field('dateline'));
				$issuedata->set('lastpostuserid', $this->fetch_field('userid'));
				$issuedata->set('lastpostusername', $this->fetch_field('username'));

				$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueprivatelastpost WHERE issueid = $issue[issueid]");

				if ($issue['visible'] == 'private')
				{
					$issuedata->add_project_private_lastpost($this);
				}
			}
			else if ($new_vis == 'private')
			{
				$this->registry->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "pt_issueprivatelastpost
						(issueid, lastnoteid, lastpostuserid, lastpostusername, lastpost)
					VALUES
						(
							" . intval($this->fetch_field('issueid')) . ",
							" . intval($this->fetch_field('issuenoteid')) . ",
							" . intval($this->fetch_field('userid')) . ",
							'" . $this->registry->db->escape_string($this->fetch_field('username')) . "',
							" . intval($this->fetch_field('dateline')) . "
						)
				");

				$issuedata->add_project_private_lastpost($this);
	 		}
	 	}

		if (!empty($issuedata->pt_issue))
		{
			$issuedata->save();
		}

		return true;
	}
}

// #############################################################################

/**
* Class to do data save/delete operations for PT issue notes (system).
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueNote_System extends vB_DataManager_Pt_IssueNote
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueNote_System(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Pt_IssueNote($registry, $errtype);

		$this->info['do_floodcheck'] = false;
		$this->info['do_dupecheck'] = false;

		($hook = vBulletinHook::fetch_hook('pt_issuenotesystemdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies the serialized text of a system issue note.
	*
	* @param	string	Serialized string of changes
	*/
	function verify_pagetext(&$pagetext)
	{
		if (!@unserialize($pagetext))
		{
			$pagetext = serialize(array());
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

		if (!$this->condition)
		{
			$this->set('type', 'system', true, false);
			$this->set('ispending', 0, true, false);
		}

		if (!parent::pre_save($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuenotesystemdata_presave')) ? eval($hook) : false;

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
		parent::post_save_each();

		($hook = vBulletinHook::fetch_hook('pt_issuenotesystemdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		parent::post_delete();

		($hook = vBulletinHook::fetch_hook('pt_issuenotesystemdata_delete')) ? eval($hook) : false;
	}

	/**
	* Updates the counters of the associated issue based on old/new visibility values
	*
	* @param	string|null	old/existing visibility. Null if this is an insert
	* @param	string|null	new visiblity value. Null if this is a delete.
	*/
	function update_issue_counters($old_vis, $new_vis)
	{
		// do nothing - a system note won't update an issue
	}
}

// #############################################################################

/**
* Class to do data save/delete operations for PT issue notes (user).
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueNote_User extends vB_DataManager_Pt_IssueNote
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueNote_User(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Pt_IssueNote($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuenoteuserdata_start')) ? eval($hook) : false;
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

		if (!$this->condition)
		{
			$this->set('type', 'user', true, false);
			$this->set('ispending', 0, true, false);
		}

		if (!parent::pre_save($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuenoteuserdata_presave')) ? eval($hook) : false;

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
		parent::post_save_each();

		($hook = vBulletinHook::fetch_hook('pt_issuenoteuserdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		parent::post_delete();

		($hook = vBulletinHook::fetch_hook('pt_issuenoteuserdata_delete')) ? eval($hook) : false;
	}
}

// #############################################################################

/**
* Class to do data save/delete operations for PT issue notes (petition).
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueNote_Petition extends vB_DataManager_Pt_IssueNote
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueNote_Petition(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Pt_IssueNote($registry, $errtype);

		$this->info['petitionstatusid'] = 0;

		($hook = vBulletinHook::fetch_hook('pt_issuenotepetitiondata_start')) ? eval($hook) : false;
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

		if (!$this->condition)
		{
			$this->set('type', 'petition', true, false);
			$this->set('ispending', 1, true, false);
		}

		if (!parent::pre_save($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->condition AND empty($this->info['petitionstatusid']))
		{
			$this->error('pt_petition_status_invalid');
			return false;
		}

		if (!$this->condition  AND $this->registry->db->query_first("
				SELECT issuenote.issuenoteid
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issuepetition AS issuepetition ON (issuenote.issuenoteid = issuepetition.issuenoteid)
				WHERE issuenote.issueid = " . intval($this->fetch_field('issueid')) . "
					AND issuepetition.resolution = 'pending'
					AND issuenote.userid = " . intval($this->fetch_field('userid')) . "
			"))
		{
			$this->error('pt_have_pending_petition');
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuenotepetitiondata_presave')) ? eval($hook) : false;

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
		parent::post_save_each();

		// insert into issuepetition table with IssuePetition DM
		if (!$this->condition)
		{
			$petitiondata =& datamanager_init('Pt_IssuePetition', $this->registry, ERRTYPE_SILENT);
			$petitiondata->set('issuenoteid', $this->fetch_field('issuenoteid'));
			$petitiondata->set('resolution', 'pending');
			$petitiondata->set('petitionstatusid', $this->info['petitionstatusid']);
			$petitiondata->save();
		}

		($hook = vBulletinHook::fetch_hook('pt_issuenotepetitiondata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		parent::post_delete();

		($hook = vBulletinHook::fetch_hook('pt_issuenotepetitiondata_delete')) ? eval($hook) : false;
	}
}

?>