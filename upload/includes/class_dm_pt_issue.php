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
* Class to do data save/delete operations for PT issues.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_Issue extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issueid'            => array(TYPE_UINT,       REQ_INCR),
		'projectid'          => array(TYPE_UINT,       REQ_YES),
		'issuestatusid'      => array(TYPE_UINT,       REQ_YES),
		'issuetypeid'        => array(TYPE_STR,        REQ_YES),
		'title'              => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD),
		'summary'            => array(TYPE_NOHTMLCOND, REQ_NO, VF_METHOD, 'verify_cleantext'),
		'submituserid'       => array(TYPE_UINT,       REQ_NO),
		'submitusername'     => array(TYPE_NOHTMLCOND, REQ_NO),
		'submitdate'         => array(TYPE_UNIXTIME,   REQ_AUTO),
		'appliesversionid'   => array(TYPE_UINT,       REQ_NO),
		'isaddressed'        => array(TYPE_UINT,       REQ_NO, 'if ($data > 1) { $data = 1; } return true;'),
		'addressedversionid' => array(TYPE_UINT,       REQ_NO),
		'priority'           => array(TYPE_UINT,       REQ_NO),
		'visible'            => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array("moderation", "visible", "private", "deleted"))) { $data = "visible"; } return true;'),
		'lastpost'           => array(TYPE_UNIXTIME,   REQ_NO),
		'lastactivity'       => array(TYPE_UNIXTIME,   REQ_NO),
		'lastpostuserid'     => array(TYPE_UINT,       REQ_NO),
		'lastpostusername'   => array(TYPE_NOHTMLCOND, REQ_NO),
		'firstnoteid'        => array(TYPE_UINT,       REQ_NO),
		'lastnoteid'         => array(TYPE_UINT,       REQ_NO),
		'attachcount'        => array(TYPE_UINT,       REQ_NO),
		'pendingpetitions'   => array(TYPE_UINT,       REQ_NO),
		'replycount'         => array(TYPE_UINT,       REQ_NO),
		'privatecount'       => array(TYPE_UINT,       REQ_NO),
		'votepositive'       => array(TYPE_UINT,       REQ_NO),
		'votenegative'       => array(TYPE_UINT,       REQ_NO),
		'projectcategoryid'  => array(TYPE_UINT,       REQ_NO),
		'state'              => array(TYPE_STR,        REQ_NO, 'if (!in_array($data, array("open", "closed"))) { $data = "open"; } return true;'),
		'milestoneid'        => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'perform_activity_updates' => true,
		'insert_change_log' => true,
		'allow_tag_creation' => true,
		'project' => array()
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issue';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issue = array();

	/**
	* Array of tags to add for this issue.
	*
	* @var	array
	*/
	var $tag_add = array();

	/**
	* Array of tags to remove for this issue.
	*
	* @var	array
	*/
	var $tag_remove = array();

	/**
	* A list of fields that should be tracked when changed.
	*
	* @var	array
	*/
	var $track_changes = array(
		'projectid',
		'issuestatusid',
		'issuetypeid',
		'title',
		'summary',
		'appliesversionid',
		'isaddressed',
		'addressedversionid',
		'priority',
		'projectcategoryid',
		'milestoneid',
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issueid = %1$d', 'issueid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_Issue(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		// Custom Magic Selects
		$magicselects = $this->registry->db->query_read("
			SELECT varname
			FROM " . TABLE_PREFIX . "pt_magicselect
		");

		while ($magicselect = $this->registry->db->fetch_array($magicselects))
		{
			$this->validfields["$magicselect[varname]"] = array(TYPE_UINT, REQ_NO);
			$this->track_changes[] = $magicselect['varname'];
		}

		($hook = vBulletinHook::fetch_hook('pt_issuedata_start')) ? eval($hook) : false;
	}

	/**
	* Verify a clean (no markup) bit of text
	*
	* @param	string	Text
	*/
	function verify_cleantext(&$clean_text)
	{
		$clean_text = trim(preg_replace('/&#(0*32|x0*20);/', ' ', $clean_text));

		// censor, remove all caps subjects
		require_once(DIR . '/includes/functions_newpost.php');
		$clean_text = fetch_no_shouting_text(fetch_censored_text($clean_text));

		// do word wrapping
		if ($this->registry->options['pt_wordwrap'] != 0)
		{
			$clean_text = fetch_word_wrapped_string($clean_text, $this->registry->options['pt_wordwrap']);
		}

		return true;
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
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		if (!$this->verify_cleantext($title))
		{
			return false;
		}

		if (!$this->registry->GPC['ajax'])
		{
			if ($title == '')
			{
				$this->error('nosubject');
				return false;
			}
		}
		else
		{
			if ($title == '')
			{
				$title_result = $this->registry->db->query_first("
					SELECT title
					FROM " . TABLE_PREFIX . "pt_issue
					WHERE issueid = " . $this->registry->GPC['issueid'] . "
				");

				$title = $title_result['title'];
				return true;
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

		// confirm submituserid/submitusername combo
		if (isset($this->pt_issue['submituserid']) OR !$this->condition)
		{
			if ($this->pt_issue['submituserid'] == 0)
			{
				// guest, verify name if changed or inserting
				if (!$this->condition OR isset($this->pt_issue['submitusername']))
				{
					$this->verify_guest_name($this->pt_issue['submitusername']);
				}
			}
			else
			{
				// changing the userid, so get the name
				$userinfo = fetch_userinfo($this->pt_issue['submituserid']);
				if (!$userinfo)
				{
					// invalid user
					$this->error('invalid_username_specified');
					return false;
				}
				else
				{
					$this->do_set('submitusername', $userinfo['username']);
				}
			}
		}

		// confirm that the status is valid for this type
		if (!empty($this->pt_issue['issuestatusid']))
		{
			if (!$this->registry->db->query_first("
				SELECT issuestatusid
				FROM " . TABLE_PREFIX . "pt_issuestatus
				WHERE issuestatusid = " . intval($this->pt_issue['issuestatusid']) . "
					AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['issue_status'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// confirm that the priority is valid for this project
		if (!empty($this->pt_issue['priority']))
		{
			if (!$this->registry->db->query_first("
				SELECT projectpriorityid
				FROM " . TABLE_PREFIX . "pt_projectpriority
				WHERE projectpriorityid = " . intval($this->pt_issue['priority']) . "
					AND projectid = " . intval($this->fetch_field('projectid')) . "
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['priority'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// confirm that the category is valid for this project
		if (!empty($this->pt_issue['projectcategoryid']))
		{
			if (!$this->registry->db->query_first("
				SELECT projectcategoryid
				FROM " . TABLE_PREFIX . "pt_projectcategory
				WHERE projectcategoryid = " . intval($this->pt_issue['projectcategoryid']) . "
					AND projectid = " . intval($this->fetch_field('projectid')) . "
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['category'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// confirm that the aplies version is valid for this project
		if (!empty($this->pt_issue['appliesversionid']))
		{
			if (!$this->registry->db->query_first("
				SELECT projectversionid
				FROM " . TABLE_PREFIX . "pt_projectversion
				WHERE projectversionid = " . intval($this->pt_issue['appliesversionid']) . "
					AND projectid = " . intval($this->fetch_field('projectid')) . "
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['version'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// confirm that the addressed version is valid for this project
		if (!empty($this->pt_issue['addressedversionid']))
		{
			if (!$this->registry->db->query_first("
				SELECT projectversionid
				FROM " . TABLE_PREFIX . "pt_projectversion
				WHERE projectversionid = " . intval($this->pt_issue['addressedversionid']) . "
					AND projectid = " . intval($this->fetch_field('projectid')) . "
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['version'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// confirm that the milestone is valid for this project
		if (!empty($this->pt_issue['milestoneid']))
		{
			if (!$this->registry->db->query_first("
				SELECT milestoneid
				FROM " . TABLE_PREFIX . "pt_milestone
				WHERE milestoneid = " . intval($this->pt_issue['milestoneid']) . "
					AND projectid = " . intval($this->fetch_field('projectid')) . "
			"))
			{
				global $vbphrase;
				$this->error('invalidid', $vbphrase['milestone'], $this->registry->options['contactuslink']);
				return false;
			}
		}

		// check for required settings
		if ($this->info['project'])
		{
			$project_requiredappliesversion = $this->info['project']['requireappliesversion'];
			$project_requiredcategory = $this->info['project']['requirecategory'];
			$project_requiredpriority = $this->info['project']['requirepriority'];

			// 0 = Off
			// 1 = On, auto-set from default value
			// 2 = On, not required
			// 3 = On, required

			// Specs:
			// If On, apply some more checks:
			// - If auto-set, check if it exixts at least 1 item. If not, error
			// - If required and not defined, error

			// Applies version
			if (in_array($project_requiredappliesversion, array(1, 2, 3)) AND !$this->fetch_field('appliesversionid'))
			{
				if ($project_requiredappliesversion == 1)
				{
					// Defining one automatically
					$appliesversionid = $this->registry->db->query_first("
						SELECT projectversionid
						FROM " . TABLE_PREFIX . "pt_projectversion
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
							AND defaultvalue = 1
					");

					if (!$appliesversionid)
					{
						$this->error('applicable_version_missing_contact_administrator', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('appliesversionid', $appliesversionid['projectversionid']);
					}
				}
				else if ($project_requiredappliesversion == 3)
				{
					if (!$this->registry->db->query_first("
						SELECT projectversionid
						FROM " . TABLE_PREFIX . "pt_projectversion
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
						LIMIT 1
						"))
					{
						$this->error('applicable_version_required', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('appliesversionid', $appliesversionid['projectversionid']);
					}
				}
			}

			// Category
			if (in_array($project_requiredcategory, array(1, 2, 3)) AND !$this->fetch_field('projectcategoryid'))
			{
				if ($project_requiredcategory == 1)
				{
					// Defining one automatically
					$projectcategoryid = $this->registry->db->query_first("
						SELECT projectcategoryid
						FROM " . TABLE_PREFIX . "pt_projectcategory
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
							AND defaultvalue = 1
					");

					if (!$projectcategoryid)
					{
						$this->error('category_missing_contact_administrator', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('projectcategoryid', $projectcategoryid['projectcategoryid']);
					}
				}
				else if ($project_requiredcategory == 3)
				{
					if (!$this->registry->db->query_first("
						SELECT projectcategoryid
						FROM " . TABLE_PREFIX . "pt_projectcategory
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
						LIMIT 1
					"))
					{
						$this->error('category_required', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('projectcategoryid', $projectcategoryid['projectcategoryid']);
					}
				}
			}

			// Priority
			if (in_array($project_requiredpriority, array(1, 2, 3)) AND !$this->fetch_field('priority'))
			{
				if ($project_requiredpriority == 1)
				{
					// Defining one automatically
					$priorityid = $this->registry->db->query_first("
						SELECT projectpriorityid
						FROM " . TABLE_PREFIX . "pt_projectpriority
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
							AND defaultvalue = 1
					");

					if (!$priorityid)
					{
						$this->error('priority_missing_contact_administrator', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('priority', $priorityid['projectpriorityid']);
					}
				}
				else if ($project_requiredpriority == 3)
				{
					if (!$this->registry->db->query_first("
						SELECT projectpriorityid
						FROM " . TABLE_PREFIX . "pt_projectpriority
						WHERE projectid = " . intval($this->fetch_field('projectid')) . "
						LIMIT 1
					"))
					{
						$this->error('priority_required', $this->registry->options['contactuslink']);
					}
					else
					{
						$this->do_set('priority', $priorityid['projectpriorityid']);
					}
				}
			}

			if ($this->errors)
			{
				return false;
			}
		}

		// confirm lastpostuserid/lastpostusername combo
		if (!$this->condition)
		{
			// lastpostuserid/lastpostusername are the initial poster for an insert
			$this->do_set('lastpostuserid', $this->fetch_field('submituserid'));
			$this->do_set('lastpostusername', $this->fetch_field('submitusername'));

			if (!$this->fetch_field('submitdate'))
			{
				$this->set('submitdate', TIMENOW);
			}

			if (!$this->fetch_field('lastpost'))
			{
				$this->set('submitdate', $this->fetch_field('submitdate'));
			}
		}
		else if (!empty($this->pt_issue['lastpostuserid']))
		{
			// if lastpostuserid is not changed (!isset), don't do anything.
			// if lastpostuserid is changed to 0 (empty), don't do anything; need lastpostusername passed in explicitly
			// if lastpostuserid is change to non-0 (!empty), we can get the username from the db
			if ($userinfo = fetch_userinfo($this->pt_issue['lastpostuserid']))
			{
				$this->do_set('lastpostusername', $userinfo['username']);
			}
		}

		if ($this->info['perform_activity_updates'])
		{
			$this->set('lastactivity', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuedata_presave')) ? eval($hook) : false;

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
		require_once (DIR . '/vb/search/indexcontroller/queue.php');
		($hook = vBulletinHook::fetch_hook('pt_issuedata_postsave')) ? eval($hook) : false;

		if ($this->condition AND !empty($this->pt_issue) AND $this->info['insert_change_log'])
		{
			// this is an update and we're actually updating something, so build issuechange
			foreach ($this->pt_issue AS $field => $newvalue)
			{
				if (isset($this->existing["$field"]) AND $newvalue == $this->existing["$field"])
				{
					// value unchanged
					continue;
				}
				else if (!in_array($field, $this->track_changes))
				{
					// value not to be tracked
					continue;
				}

				$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_SILENT);
				$change->set('issueid', $this->fetch_field('issueid'));
				$change->set('userid', $this->registry->userinfo['userid']);
				$change->set('field', $field);
				$change->set('newvalue', $newvalue);
				if (isset($this->existing["$field"]))
				{
					$change->set('oldvalue', $this->existing["$field"]);
				}
				$change->save();
			}
		}

		if ($this->condition AND (!empty($this->tag_add) OR !empty($this->tag_remove)) AND $this->info['insert_change_log'])
		{
			// we updated tags, insert an issue change for this
			$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
			$change->set('issueid', $this->fetch_field('issueid'));
			$change->set('userid', $this->registry->userinfo['userid']);
			$change->set('field', 'tags');
			$change->set('newvalue', '');
			$change->set('oldvalue','');
			$change->save();
		}

		// delete any tags that are supposed to be removed
		if ($this->condition AND $this->tag_remove)
		{
			$this->registry->db->query_write("
				DELETE pt_issuetag
				FROM " . TABLE_PREFIX . "pt_issuetag AS pt_issuetag
				INNER JOIN " . TABLE_PREFIX . "pt_tag AS pt_tag ON (pt_issuetag.tagid = pt_tag.tagid)
				WHERE pt_tag.tagtext IN ('" . implode("', '", array_map(array(&$this->registry->db, 'escape_string'), $this->tag_remove)) . "')
					AND pt_issuetag.issueid = " . $this->fetch_field('issueid')
			);
		}

		// add any tags that are to be added
		if ($this->tag_add)
		{
			$clean_add = array_map(array(&$this->registry->db, 'escape_string'), $this->tag_add);

			if ($this->info['allow_tag_creation'])
			{
				$this->registry->db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "pt_tag
						(tagtext)
					VALUES
						('" . implode("'), ('", $clean_add) . "')
				");
			}

			$this->registry->db->query_write("
				INSERT IGNORE " . TABLE_PREFIX . "pt_issuetag
					(issueid, tagid)
				SELECT " . $this->fetch_field('issueid') . ", pt_tag.tagid
				FROM " . TABLE_PREFIX . "pt_tag AS pt_tag
				WHERE pt_tag.tagtext IN ('" . implode("', '", $clean_add) . "')
			");
		}

		// if we're changing the status, check for any pending petitions in this thread so we can change their status
		if ($this->condition AND $this->fetch_field('issuestatusid') != $this->existing['issuestatusid'])
		{
			$petitions = $this->registry->db->query_read("
				SELECT issuepetition.*
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issuepetition AS issuepetition ON (issuepetition.issuenoteid = issuenote.issuenoteid)
				WHERE issuenote.issueid = " . $this->fetch_field('issueid') . "
					AND issuepetition.resolution = 'pending'
			");
			while ($petition = $this->registry->db->fetch_array($petitions))
			{
				$petitiondata =& datamanager_init('Pt_IssuePetition', $this->registry, ERRTYPE_STANDARD);
				$petitiondata->set_existing($petition);
				$petitiondata->set_info('auto_issue_update', false);
				$petitiondata->set('resolution', $petition['petitionstatusid'] == $this->fetch_field('issuestatusid') ? 'accepted' : 'rejected');
				$petitiondata->save();
			}
		}

		$rebuild_project = false;
		if ($this->condition)
		{
			foreach (array('issuestatusid', 'issuetypeid', 'projectid', 'visible', 'title') AS $triggerfield)
			{
				if ($this->fetch_field($triggerfield) != $this->existing["$triggerfield"])
				{
					$rebuild_project = true;
					break;
				}
			}

			if ($rebuild_project)
			{
				if ($project = fetch_project_info($this->fetch_field('projectid'), false))
				{
					$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_STANDARD);
					$projectdata->set_existing($project);
					$projectdata->rebuild_project_counters();
					$projectdata->save();
				}

				// changed project, rebuild the old project counters too
				if ($this->fetch_field('projectid') != $this->existing['projectid'] AND $project = fetch_project_info($this->existing['projectid'], false))
				{
					$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_STANDARD);
					$projectdata->set_existing($project);
					$projectdata->rebuild_project_counters();
					$projectdata->save();
				}
			}
		}

		if (!$this->condition)
		{
			// Insert new issue - increase 'totalissues' counter in pt_user table for the original user
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_user SET
					totalissues = totalissues + 1
				WHERE userid = " . $this->fetch_field('submituserid') . "
			");
		}

		if (!$rebuild_project)
		{
			$this->update_project_counters(
				$this->existing['visible'],       $this->fetch_field('visible'),
				$this->existing['issuestatusid'], $this->fetch_field('issuestatusid')
			);
		}

		// milestone or status changed -- we need to rebuild the milestone counters
		if ($this->fetch_field('milestoneid') != $this->existing['milestoneid'])
		{
			$this->rebuild_milestone_counters($this->fetch_field('milestoneid'));
			$this->rebuild_milestone_counters($this->existing['milestoneid']);
		}
		else if ($this->fetch_field('issuestatusid') != $this->existing['issuestatusid'])
		{
			$this->rebuild_milestone_counters($this->fetch_field('milestoneid'));
		}
		vb_Search_Indexcontroller_Queue::indexQueue('vBProjectTools', 'Issue', 'index', intval($this->fetch_field('issueid')) );

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

			// Decrement totalissues counter
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_user SET
					totalissues = totalissues - 1
				WHERE userid = " . $this->fetch_field('userid') . "
			");

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
		require_once DIR . '/vb/search/indexcontroller/queue.php';
		$issueid = intval($this->fetch_field('issueid'));
		$db =& $this->registry->db;

		if ($this->info['hard_delete'])
		{
			// this is a hard delete
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueassign WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueattach WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuechange WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuedeletionlog WHERE primaryid = $issueid AND type = 'issue'");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuenote WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueprivatelastpost WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuesubscribe WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuetag WHERE issueid = $issueid");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issuevote WHERE issueid = $issueid");

			// Attachments
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "attachment WHERE contentid = $issueid AND contenttypeid = " . vB_Types::instance()->getContentTypeID('vBProjectTools_Issue') . "");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueattach WHERE issueid = $issueid");

			// Before to hard-delete import infos, we need to open back the thread if the import was from a thread
			$importdata = $this->registry->db->query_first("
				SELECT contenttypeid, contentid, data
				FROM " . TABLE_PREFIX . "pt_issueimport
				WHERE issueid = " . $issueid . "
			");

			if ($importdata)
			{
				$data = unserialize($importdata['data']);

				// Update the original content - open back thread
				// We need first to get the content type id about 'vBForum_Thread' - could be not the same in each install
				$thread_contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');

				if ($thread_contenttypeid == $importdata['contenttypeid'] AND $data['pt_forwardmode'] == 1)
				{
					// Content type ID are the same, continue
					// Open back the original thread
					$this->registry->db->query_write("
						UPDATE " . TABLE_PREFIX . "thread SET
							open = 1
						WHERE threadid = " . $importdata['contentid'] . "
					");
				}
			}

			// Now perform the deletion
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueimport WHERE issueid = $issueid");
		}
		else
		{
			// soft delete
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "pt_issuedeletionlog
					(primaryid, type, userid, username, reason, dateline)
				VALUES
					($issueid,
					'issue',
					" . $this->registry->userinfo['userid'] . ",
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					'" . $db->escape_string($this->info['reason']) . "',
					" . TIMENOW . ")
			");

			// We need to check here if the issue was imported from another content type.
			// If yes, change the 'visible' value in the serialized data
			$importdata = $this->registry->db->query_first("
				SELECT contenttypeid, contentid, data
				FROM " . TABLE_PREFIX . "pt_issueimport
				WHERE issueid = " . $issueid . "
			");

			if ($importdata)
			{
				$data = unserialize($importdata['data']);

				// Set the soft-deleted issue as not visible
				$data['visible'] = 'deleted';

				// Update the original content - open back thread
				// We need first to get the content type id about 'vBForum_Thread' - could be not the same in each install
				$thread_contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');

				if ($thread_contenttypeid == $importdata['contenttypeid'] AND $data['pt_forwardmode'] == 1)
				{
					// Content type ID are the same, continue
					// Open back the original thread
					$this->registry->db->query_write("
						UPDATE " . TABLE_PREFIX . "thread SET
							open = 1
						WHERE threadid = " . $importdata['contentid'] . "
					");
				}

				$newdata = serialize($data);

				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "pt_issueimport SET
						data = '" . $this->registry->db->escape_string($newdata) . "'
					WHERE issueid = " . $issueid . "
						AND contentid = " . $importdata['contentid'] . "
				");
			}
		}

		if ($project = fetch_project_info($this->fetch_field('projectid'), false))
		{
			$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_SILENT);
			$projectdata->set_existing($project);
			$projectdata->rebuild_project_counters();
			$projectdata->save();
		}

		($hook = vBulletinHook::fetch_hook('pt_issuedata_delete')) ? eval($hook) : false;

		vb_Search_Indexcontroller_Queue::indexQueue('vBProjectTools', 'Issue', 'delete', intval($this->fetch_field('issueid')) );

		return true;
	}

	/**
	* Undeletes a soft-deleted issue. Needs $this->existing to be set properly.
	*
	* @return	boolean	True if the undelete succeeded
	*/
	function undelete()
	{
		$issueid = intval($this->fetch_field('issueid'));

		if (!$issueid)
		{
			return false;
		}

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				visible = 'visible'
			WHERE issueid = $issueid
		");

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issuedeletionlog
			WHERE primaryid = $issueid
				AND type = 'issue'
		");

		// Restore a soft-deleted issue which was imported from a thread need to edit original thread back
		// Like it was when the thread was imported as an issue
		$importdata = $this->registry->db->query_first("
			SELECT contenttypeid, contentid, data
			FROM " . TABLE_PREFIX . "pt_issueimport
			WHERE issueid = " . $issueid . "
		");

		if ($importdata)
		{
			$data = unserialize($importdata['data']);

			// Set the import infos back to visible
			$data['visible'] = 'visible';

			// We need to close the thread again
			$thread_contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');

			if ($thread_contenttypeid == $importdata['contenttypeid'] AND $data['pt_forwardmode'] == 1)
			{
				// Content type ID are the same, continue
				// Open back the original thread
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "thread SET
						open = 0
					WHERE threadid = " . $importdata['contentid'] . "
				");
			}

			$newdata = serialize($data);

			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issueimport SET
					data = '" . $this->registry->db->escape_string($newdata) . "'
				WHERE issueid = " . $issueid . "
					AND contentid = " . $importdata['contentid'] . "
			");
		}

		// Increment totalissues counter
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_user SET
				totalissues = totalissues + 1
			WHERE userid = " . $this->fetch_field('userid') . "
		");

		if ($project = fetch_project_info($this->fetch_field('projectid'), false))
		{
			$projectdata =& datamanager_init('Pt_Project', $this->registry, ERRTYPE_SILENT);
			$projectdata->set_existing($project);
			$projectdata->rebuild_project_counters();
			$projectdata->save();
		}

		($hook = vBulletinHook::fetch_hook('pt_issuedata_undelete')) ? eval($hook) : false;
		return true;
	}

	/**
	* Determines if any changes will be tracked for this update or
	* if a new issue is being inserted.
	*
	* @return	bool
	*/
	function have_issue_changes()
	{
		if (!$this->condition)
		{
			// new issues always have something changed
			return true;
		}

		if ($this->condition AND !empty($this->pt_issue) AND $this->info['insert_change_log'])
		{
			// this is an update and we're actually updating something, so build issuechange
			foreach ($this->pt_issue AS $field => $newvalue)
			{
				if (isset($this->existing["$field"]) AND $newvalue == $this->existing["$field"])
				{
					// value unchanged
					continue;
				}
				else if (!in_array($field, $this->track_changes))
				{
					// value not to be tracked
					continue;
				}

				return true;
			}
		}

		if ($this->condition AND (!empty($this->tag_add) OR !empty($this->tag_remove)) AND $this->info['insert_change_log'])
		{
			// we updated tags, insert an issue change for this
			return true;
		}

		return false;
	}

	/**
	* Adds a tag to the issue.
	*
	* @param	string	Tag
	*/
	function add_tag($tag)
	{
		$tag = $this->registry->input->clean($tag, TYPE_NOHTMLCOND);
		$tag_lower = vbstrtolower($tag);

		$this->tag_add["$tag_lower"] = $tag;
		if (isset($this->tag_remove["$tag_lower"]))
		{
			// can't add and remove a tag
			unset($this->tag_remove["$tag_lower"]);
		}
	}

	/**
	* Removes a tag from the issue.
	*
	* @param	string	Tag
	*/
	function remove_tag($tag)
	{
		$tag = $this->registry->input->clean($tag, TYPE_NOHTMLCOND);
		$tag_lower = vbstrtolower($tag);

		$this->tag_remove["$tag_lower"] = $tag;
		if (isset($this->tag_add["$tag_lower"]))
		{
			// can't add and remove a tag
			unset($this->tag_add["$tag_lower"]);
		}
	}

	/**
	* Updates the counters of the associated project based on old/new visibility values
	*
	* @param	string|null	Old/existing visibility. Null if this is an insert
	* @param	string|null	New visiblity value. Null if this is a delete.
	* @param	string|null	Old/existing status. Null if this is an insert
	* @param	string|null	New status value. Null if this is a delete.
	*/
	function update_project_counters($old_vis, $new_vis, $old_status, $new_status)
	{
		if (!$project = fetch_project_info($this->fetch_field('projectid'), false))
		{
			return false;
		}

		$update = array();

		if ($old_vis == $new_vis)
		{
			// we didn't change any counters, do nothing
		}
		else if ($new_vis == 'visible')
		{
			// didn't have an old visibility (inserting) or the new value is visible
			// (implicitly, by the first if, the old visiblity is not visible) -- add
			$update[] = "issuecount = issuecount + 1";
		}
		else if ($old_vis == 'visible')
		{
			// no new visibility (deleting) or we're making a visible issue
			// invisible -- subtract
			$update[] = "issuecount = issuecount - 1";
		}

		// determine if open issue count needs to be updated because of status change
		if ($new_vis == 'visible' AND $old_status != $new_status)
		{
			$old_status_info = false;
			$new_status_info = false;
			$status_sql = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "pt_issuestatus
				WHERE issuestatusid IN (" . intval($old_status) . "," . intval($new_status) . ")
			");

			while ($status = $this->registry->db->fetch_array($status_sql))
			{
				if ($status['issuestatusid'] == $old_status)
				{
					$old_status_info = $status;
				}
				else if ($status['issuestatusid'] == $new_status)
				{
					$new_status_info = $status;
				}
			}

			if ($new_status_info AND $new_status_info['issuecompleted'] == 0)
			{
				if (!$old_status_info OR $old_status_info['issuecompleted'] == 1)
				{
					$update[] = "issuecountactive = issuecountactive + 1";
				}
			}
			else if ($old_status_info AND $old_status_info['issuecompleted'] == 0)
			{
				if (!$new_status_info OR $new_status_info['issuecompleted'] == 1)
				{
					$update[] = "issuecountactive = issuecountactive - 1";
				}
			}
		}

		$projecttypeinfo = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = $project[projectid]
				AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
		");

		if ($new_vis == 'visible')
		{
			$lastactivity = intval($this->fetch_field('lastactivity'));
			if ($lastactivity > $projecttypeinfo['lastactivity'])
			{
				$update[] = "lastactivity = $lastactivity";
			}

			// update on >= to ensure that all the info from the first note in an issue is used
			$lastpost = intval($this->fetch_field('lastpost'));
			if ($lastpost >= $projecttypeinfo['lastpost'])
			{
				$update[] = "lastpost = $lastpost";
				$update[] = "lastpostuserid = " . intval($this->fetch_field('lastpostuserid'));
				$update[] = "lastpostusername = '" . $this->registry->db->escape_string($this->fetch_field('lastpostusername')) . "'";
				$update[] = "lastpostid = " . intval($this->fetch_field('lastnoteid'));
				$update[] = "lastissueid = " . intval($this->fetch_field('issueid'));
				$update[] = "lastissuetitle = '" . $this->registry->db->escape_string($this->fetch_field('title')) . "'";

				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "pt_projecttypeprivatelastpost
					WHERE projectid = $project[projectid]
						AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
				");
			}
		}

		if ($update)
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projecttype SET
					" . implode(', ', $update) . "
				WHERE projectid = $project[projectid]
					AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
			");
		}

		return true;
	}

	/**
	* Adds private last post data to a project
	*
	* @param	vB_DataManager_Pt_IssueNote	Issue note DM
	*/
	function add_project_private_lastpost(&$issuenotedata)
	{
		switch ($this->fetch_field('visible'))
		{
			case 'visible':
			case 'private':
				break;

			default:
				// don't update project last post times if the issue is deleted/moderated
				return;
		}

		$projecttypeinfo = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = " . intval($this->fetch_field('projectid')) . "
				AND issuetypeid = '" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "'
		");

		if ($issuenotedata->fetch_field('dateline') > $projecttypeinfo['lastpost'])
		{
			$this->registry->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "pt_projecttypeprivatelastpost
					(projectid, issuetypeid,
					lastpost, lastpostuserid, lastpostusername, lastpostid,
					lastissueid, lastissuetitle)
				VALUES
					(" . intval($this->fetch_field('projectid')) . ",
					'" . $this->registry->db->escape_string($this->fetch_field('issuetypeid')) . "',
					" . intval($issuenotedata->fetch_field('dateline')) . ",
					" . intval($issuenotedata->fetch_field('userid')) . ",
					'" . $this->registry->db->escape_string($issuenotedata->fetch_field('username')) . "',
					" . intval($issuenotedata->fetch_field('issuenoteid')) . ",
					" . intval($this->fetch_field('issueid')) . ",
					'" . $this->registry->db->escape_string($this->fetch_field('title')) . "')
			");
		}
	}

	/**
	* Rebuilds the counters for this issue. Save() must be called explicitly afterwards
	*/
	function rebuild_issue_counters()
	{
		if (!$this->condition OR !$this->fetch_field('issueid'))
		{
			trigger_error("You cannot call rebuild_issue_counters without a proper condition.", E_USER_ERROR);
		}

		$db =& $this->registry->db;

		// first user post
		$first = $db->query_first("
			SELECT issuenote.*, IF(user.username IS NOT NULL, user.username, issuenote.username) AS submitusername
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
			WHERE issuenote.issueid = " . $this->fetch_field('issueid') . "
				AND issuenote.visible = 'visible'
				AND issuenote.type IN ('user', 'petition')
			ORDER BY issuenote.dateline
			LIMIT 1
		");

		$this->set('firstnoteid', $first['issuenoteid']);
		$this->set('submitdate', $first['dateline']);
		$this->set('submituserid', $first['userid']);
		$this->set('submitusername', $first['submitusername']);

		// last user post
		$last = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issueid = " . $this->fetch_field('issueid') . "
				AND visible = 'visible'
				AND type IN ('user', 'petition')
			ORDER BY dateline DESC
			LIMIT 1
		");

		$this->set('lastnoteid', $last['issuenoteid']);
		$this->set('lastpost', $last['dateline']);
		$this->set('lastpostuserid', $last['userid']);
		$this->set('lastpostusername', $last['username']);

		// last change to the issue
		$lastact = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issueid = " . $this->fetch_field('issueid') . "
				AND visible = 'visible'
			ORDER BY dateline DESC
			LIMIT 1
		");

		$this->set('lastactivity', $lastact['dateline']);

		// note-based counts
		$counts = $db->query_first("
			SELECT
				COUNT(*) - 1 AS replycount,
				SUM(IF(issuepetition.resolution = 'pending', 1, 0)) AS pendingpetitions
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			LEFT JOIN " . TABLE_PREFIX . "pt_issuepetition AS issuepetition ON
				(issuepetition.issuenoteid = issuenote.issuenoteid)
			WHERE issuenote.issueid = " . $this->fetch_field('issueid') . "
				AND issuenote.visible = 'visible'
				AND issuenote.type IN ('user', 'petition')
		");

		$this->set('replycount', $counts['replycount']);
		$this->set('pendingpetitions', $counts['pendingpetitions']);

		// private replies
		$private = $db->query_first("
			SELECT COUNT(*) AS privatecount
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
			WHERE issuenote.issueid = " . $this->fetch_field('issueid') . "
				AND issuenote.visible = 'private'
				AND issuenote.type IN ('user', 'petition')
		");

		$this->set('privatecount', $private['privatecount']);

		// attachment-based counts
		$attach = $db->query_first("
			SELECT COUNT(*) AS attachcount
			FROM " . TABLE_PREFIX . "pt_issueattach
			WHERE issueid = " . $this->fetch_field('issueid') . "
				AND visible = 1
		");

		$this->set('attachcount', $attach['attachcount']);

		// vote-based counts
		$votes = $db->query_first("
			SELECT SUM(IF(vote = 'positive', 1, 0)) AS votepositive,
				SUM(IF(vote = 'negative', 1, 0)) AS votenegative
			FROM " . TABLE_PREFIX . "pt_issuevote
			WHERE issueid = " . $this->fetch_field('issueid')
		);

		$this->set('votepositive', $votes['votepositive']);
		$this->set('votenegative', $votes['votenegative']);

		$this->set_info('perform_activity_updates', false);

		$this->rebuild_private_lastpost();
	}

	/**
	* Rebuilds the issueprivatelastpost table for this issue.
	*/
	function rebuild_private_lastpost()
	{
		$issueid = $this->fetch_field('issueid');
		$lastpost = $this->fetch_field('lastpost');

		if (!$issueid OR !$lastpost)
		{
			return;
		}

		// delete the all lines of the given issue
		$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "pt_issueprivatelastpost WHERE issueid = $issueid");

		// write the non visible notes after the latest public one into the issueprivatelastpost table
		$this->registry->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "pt_issueprivatelastpost
				(issueid, lastnoteid, lastpostuserid, lastpostusername, lastpost)
			SELECT $issueid, issuenoteid, userid, username, dateline
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issueid = $issueid
				AND type IN ('user', 'petition')
				AND dateline >= $lastpost
				AND visible = 'private'
			ORDER BY dateline DESC
		");
	}

	function rebuild_milestone_counters($milestoneid)
	{
		$milestoneid = intval($milestoneid);
		if (!$milestoneid)
		{
			return;
		}

		$milestone = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = $milestoneid
		");
		if (!$milestone)
		{
			return;
		}

		$milestonedata =& datamanager_init('Pt_Milestone', $this->registry, ERRTYPE_SILENT);
		$milestonedata->set_existing($milestone);
		$milestonedata->rebuild_milestone_counters();
		$milestonedata->save();
	}
}
?>
