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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* This class choose the correct method to import data into Project Tools.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Import_Factory
{
	/**
	 * The vBulletin Registry
	 * 
	 * @var	vB_Registry object
	 */
	public $registry = null;

	/**
	* The type of data from the source
	*
	* @var	string
	*/
	var $datatype = '';

	/**
	* The info of the source - this could be post or thread
	*
	* @var	array
	*/
	var $datainfo = array();

	/**
	* The id of the newly created issue
	*
	* @var integer
	*/
	var $issueid = 0;

	/**
	* The project info of the target project
	*
	* @var	array
	*/
	var $project = array();

	/**
	* The posting permissions
	*
	* @var	array
	*/
	var $posting_perms = array();

	/**
	* The issue type of the new issue
	*
	* @var	string
	*/
	var $issuetypeid = '';

	public function fetch_import($datatype)
	{
		switch ($datatype)
		{
			case 'thread':
				$out = new vB_Pt_Import_Thread();
				break;
			case 'post':
				$out = new vB_Pt_Import_Post();
				break;
			case 'issuethread':
				$out = new vB_Pt_Import_Issuenote();
				break;
			default:
				trigger_error('vB_Pt_Import_Factory::fetch_import(): Invalid import type.', E_USER_ERROR);
		}

		$out->registry =& $this->registry;
		$out->datatype =& $this->datatype;
		$out->datainfo =& $this->datainfo;
		$out->project =& $this->project;
		$out->posting_perms =& $this->posting_perms;

		return $out;
	}
}

/**
* This class imports a thread into the Project Tools.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Import
{
	/**
	 * The vBulletin Registry
	 * 
	 * @var	vB_Registry object
	 */
	public $registry = null;

	/**
	* The type of data from the source
	*
	* @var	string
	*/
	var $datatype = '';

	/**
	* The info of the source - this could be post, thread or issuenote
	*
	* @var	array
	*/
	var $datainfo = array();

	/**
	* The id of the newly created issue
	*
	* @var integer
	*/
	var $issueid = 0;

	/**
	* The project info of the target project
	*
	* @var	array
	*/
	var $project = array();

	/**
	* The posting permissions
	*
	* @var	array
	*/
	var $posting_perms = array();

	/**
	* The issue type of the new issue
	*
	* @var	string
	*/
	var $issuetypeid = '';

	/**
	* Ctor.
	*
	* @param	integer		The id of the source thread
	* @param	integer		The id of the target project
	* @param	string		The issue type of the new issue
	* @param	array		Integer array containing the ids of the posts to import.
	* @param	array		Integer array containing the ids of the attachments to import.
	*/
	public function __construct()
	{
		if (!is_subclass_of($this, 'vB_Pt_Import'))
		{
			trigger_error('Direct instantiation of vB_Pt_Import class prohibited. Use the vB_Pt_Imxport_Factory class.', E_USER_ERROR);
		}

		// Require to get the contenttypeid
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
	}

	/**
	* Executes all import methods in the correct order
	*
	* @return	integer		The id of the new issue
	*/
	public function import_all()
	{
		// Import issue and notes
		$this->execute_import_issue();

		if (empty($this->errors))
		{
			// Assign to self
			$this->execute_import_set_assignment();

			// Import attachments
			$this->execute_import_attachments();

			if ($this->datainfo['threadid'])
			{
				// Import subscriptions
				$this->execute_import_subscriptions();
			}

			$this->execute_import_from_source();

			// Create import notice
			$this->execute_import_insert_notice();

			// Useful to redirect the user
			return $this->issueid;
		}
		else
		{
			return $this->errors;
		}
	}

	/**
	* Import the issue and its notes
	*
	* @return	integer		The id of the new issue
	*/
	public function execute_import_issue()
	{
		// prepare issue
		$this->issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_ARRAY);
		$this->issuedata->set_info('project', $this->project);

		$this->issuedata->set('title', $this->registry->GPC['title']);
		$this->issuedata->set('summary', $this->registry->GPC['summary']);
		$this->issuedata->set('issuestatusid', $this->registry->GPC['issuestatusid']);
		$this->issuedata->set('priority', $this->registry->GPC['priority']);
		$this->issuedata->set('projectcategoryid', $this->registry->GPC['projectcategoryid']);
		$this->issuedata->set('appliesversionid', $this->registry->GPC['appliesversionid']);

		switch ($this->registry->GPC['addressedversionid'])
		{
			case -1:
				$this->issuedata->set('isaddressed', 1);
				$this->issuedata->set('addressedversionid', 0);
				break;
			case 0:
				$this->issuedata->set('isaddressed', 0);
				$this->issuedata->set('addressedversionid', 0);
				break;
			default:
				$this->issuedata->set('isaddressed', 1);
				$this->issuedata->set('addressedversionid', $this->registry->GPC['addressedversionid']);
				break;
		}

		$this->issuedata->set('projectid', $this->project['projectid']);
		$this->issuedata->set('projectcategoryid', $this->registry->GPC['projectcategoryid']);
		$this->issuedata->set('issuetypeid', $this->registry->GPC['issuetypeid']);
		$this->issuedata->set('milestoneid', $this->registry->GPC['milestoneid']);
		$this->issuedata->set('submituserid', ($this->datainfo['userid'] ? $this->datainfo['userid'] : $this->datainfo['postuserid']));
		$this->issuedata->set('submitusername', ($this->datainfo['username'] ? $this->datainfo['username'] : $this->datainfo['postusername']));
		$this->issuedata->set('submitdate', $this->datainfo['dateline']);
		$this->issuedata->set('lastpost', $this->datainfo['lastpost']);

		// New
		$this->issuedata->set('state', $this->registry->GPC['close_issue'] ? 'closed' : 'open');

		if ($this->registry->GPC['private'])
		{
			// make it private
			$this->issuedata->set('visible', 'private');
		}
		else
		{
			// make it visible if it was private, else leave it as is
			$this->issuedata->set('visible', 'visible');
		}

		if ($posting_perms['tags_edit'])
		{
			$this->issuedata->set_info('allow_tag_creation', $this->posting_perms['can_custom_tag']);
			prepare_tag_changes($this->registry->GPC, $existing_tags, $tag_add, $tag_remove);

			foreach ($tag_add AS $tag)
			{
				$this->issuedata->add_tag($tag);
			}

			foreach ($tag_remove AS $tag)
			{
				$this->issuedata->remove_tag($tag);
			}
		}
	}

	/**
	* Assign to self if permitted and checkbox set
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	private function execute_import_set_assignment()
	{
		// Note to self:
		// Can't use process_assignment_changes because it won't use the log_assignment_changes parameter
		// for self assignments (bug?)

		// Validate permission to assign to self
		if (!$this->posting_perms['assign_checkbox'] AND !$this->posting_perms['assign_dropdown'])
		{
			return;
		}

		// Has the checkbox been set?
		if (!$this->registry->GPC['assignself'])
		{
			return;
		}

		$assign =& datamanager_init('Pt_IssueAssign', $this->registry, ERRTYPE_SILENT);
		$assign->set_info('log_assignment_changes', false);
		$assign->set('userid', $this->registry->userinfo['userid']);
		$assign->set('issueid', $this->issueid);
		$assign->save();
	}

	/**
	* Import those attachments allowed by the settings for the project tools
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_attachments()
	{
		$this->postlist = array();
		$this->totalattach = 0;
	}

	/**
	* Import subscriptions
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	private function execute_import_subscriptions()
	{
		$threadid = $this->datainfo['threadid'];

		$subscription_insert = array();

		$subscription_query = $this->registry->db->query_read("
			SELECT userid, emailupdate, folderid, canview
			FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
			WHERE threadid = $threadid
		");

		while ($subscription = $this->registry->db->fetch_array($subscription_query))
		{
			$subscriptiontype = 'none';
			switch ($subscription['emailupdate'])
			{
				case 0:
					$subscriptiontype = 'none';
					break;
				case 1:
					$subscriptiontype = 'instant';
					break;
				case 2:
					$subscriptiontype = 'daily';
					break;
				case 3:
					$subscriptiontype = 'weekly';
					break;
			}

			$subscription_insert[] = "($subscription[userid], $this->issueid, '$subscriptiontype')";
		}

		if (count($subscription_insert) > 0)
		{
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "pt_issuesubscribe
					(userid, issueid, subscribetype)
				VALUES
				" . implode(', ', $subscription_insert)
			);
		}
	}

	/**
	* Update the original source
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_from_source()
	{
		// Define $importdata as an array of values to serialize
		$this->importdata = array();

		// Adding the issue id
		$this->importdata['pt_issueid'] = $this->issueid;

		// Issue is visible
		$this->importdata['visible'] = 'visible';
	}

	/**
	* Insert a notice stating the import date and the importer
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_insert_notice()
	{
		if (!$this->registry->options['ptimporter_createnotice'])
		{
			return;
		}
	}
}

/**
* This class imports a thread from forums as new issue
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Import_Thread extends vB_Pt_Import
{
	public function execute_import_issue()
	{
		parent::execute_import_issue();

		// prepare issue notes
		$issuenotes = array();

		$i = 0;
		$postids = array();

		$post_query = $this->registry->db->query_read("
			SELECT p.postid, p.userid, p.username, p.dateline, p.pagetext, t.firstpostid, p.ipaddress
			FROM " . TABLE_PREFIX . "post AS p
				LEFT JOIN " . TABLE_PREFIX . "thread AS t ON (t.threadid = p.threadid)
			WHERE p.threadid = " . $this->datainfo['threadid'] . "
				" . (count($this->postids) > 0 ? 'AND p.postid IN (' . implode(',', $this->postids) . ')' : '') . "
			ORDER BY p.dateline
		");

		if ($this->registry->db->num_rows($post_query) > 0)
		{
			while ($post = $this->registry->db->fetch_array($post_query))
			{
				$issuenotes[$i] =& datamanager_init('Pt_IssueNote_User', $this->registry, ERRTYPE_ARRAY, 'pt_issuenote');
				$issuenotes[$i]->set_info('do_floodcheck', false);
				$issuenotes[$i]->set_info('parseurl', $this->registry->options['pt_allowbbcode']);
				$issuenotes[$i]->set('userid', $post['userid']);
				$issuenotes[$i]->set('username', $post['username']);
				$issuenotes[$i]->set('visible', 'visible');

				if ($post['postid'] == $post['firstpostid'])
				{
					$issuenotes[$i]->set('isfirstnote', 1);
				}
				else
				{
					$issuenotes[$i]->set('isfirstnote', 0);
				}

				$issuenotes[$i]->set('pagetext', $post['pagetext']);
				$issuenotes[$i]->set('dateline', $post['dateline']);
				$issuenotes[$i]->set('ipaddress', $post['ipaddress']);

				$this->issuedata->pre_save();

				if (!$this->issuedata->errors)
				{
					$issuenotes[$i]->pre_save();
				}

				$this->errors = array_unique(array_merge($this->issuedata->errors, $issuenotes[$i]->errors));

				$postids[] = $post['postid'];

				$i++;
			}

			$this->postids = $postids;
		}

		if ($this->errors)
		{
			return $this->errors;
		}
		else
		{
			$this->issueid = $this->issuedata->save();

			for ($i = 0; $i < count($issuenotes); $i++)
			{
				$issuenotes[$i]->set('issueid', $this->issueid);
				$issuenotes[$i]->save();
			}

			// Custom Magic Selects
			$issuems =& datamanager_init('Pt_Issue_MagicSelect', $this->registry, ERRTYPE_ARRAY, 'pt_magicselect');
			$issuems->set('issueid', $this->issueid);
			$issuems->save();

			return $this->issueid;
		}
	}

	/**
	* Import those attachments allowed by the settings for the project tools
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_attachments()
	{
		parent::execute_import_attachments();

		$postids = $this->registry->db->query_read("
			SELECT postid
			FROM " . TABLE_PREFIX . "post
			WHERE threadid = " . intval($this->datainfo['id']) . "
		");

		while ($postid = $this->registry->db->fetch_array($postids))
		{
			$this->postlist[] = $postid['postid'];
		}

		$attachments = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.filedataid, a.state, a.filename, a.settings, a.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contenttypeid = " . intval($this->datainfo['contenttypeid']) . "
					AND
				a.contentid IN (" . implode(',', $this->postlist) . ")
		");

		while ($attach = $this->registry->db->fetch_array($attachments))
		{
			// 4.x format
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "attachment
					(contenttypeid, userid, dateline, filedataid, state, filename, settings, posthash, contentid)
				VALUES
					(" . vB_Types::instance()->getContentTypeID("vBProjectTools_Issue") . ",
					" . $attach['userid'] . ",
					" . TIMENOW . ",
					" . $attach['filedataid'] . ",
					'" . $attach['state'] . "',
					'" . $this->registry->db->escape_string($attach['filename']) . "',
					'" . $this->registry->db->escape_string($attach['settings']) . "',
					'',
					$this->issueid)
			");

			$attachmentid = $this->registry->db->insert_id();

			// Specific values for PT
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "pt_issueattach
					(attachmentid, issueid, userid, visible, status, ispatchfile)
				VALUES
					($attachmentid,
					" . $this->issueid . ",
					" . $attach['userid'] . ",
					1,
					'current',
					" . (in_array(substr($attach['filename'], strpos($attach['filename'], '.')), array('diff', 'patch', 'xml')) ? 1 : 0) . ")
			");

			// Increment total attachment counter
			$this->totalattach++;
		}
	}

	/**
	* Update the original thread
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_from_source()
	{
		parent::execute_import_from_source();

		// We need to get the content type id of threads
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');

		if ($this->registry->options['ptimporter_keepthreads'])
		{
			// Adding the forward mode
			$this->importdata['pt_forwardmode'] = 0;
		}
		else
		{
			// Adding the forward mode
			$this->importdata['pt_forwardmode'] = 1;

			// Close the original thread - this is rules by the vb option 'ptimporter_keepthreads'
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					open = 0
				WHERE threadid = " . $this->datainfo['threadid'] . "
			");
		}

		// Serialize the data
		$data = serialize($this->importdata);

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueimport
				(issueid, contenttypeid, contentid, data)
			VALUES
				(" . $this->importdata['pt_issueid'] . ", $contenttypeid, " . $this->datainfo['threadid'] . ", '" . $data . "')
		");

		// Update total attach counter into issue infos
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				attachcount = " . $this->totalattach . "
			WHERE issueid = " . $this->issueid . "
		");
	}

	public function execute_import_insert_notice()
	{
		parent::execute_import_insert_notice();

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->issueid);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_imported');
		$change->set('oldvalue', $this->datainfo['threadid']);
		$change->set('newvalue', $this->datainfo['originaltitle']);
		$change->save();
	}
}

/**
* This class imports a post from forums as new issue
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Import_Post extends vB_Pt_Import
{
	public function execute_import_issue()
	{
		parent::execute_import_issue();

		// prepare issue notes
		$post = $this->registry->db->query_first("
			SELECT postid, userid, username, dateline, pagetext, ipaddress
			FROM " . TABLE_PREFIX . "post
			WHERE postid = " . $this->datainfo['postid'] . "
		");

		$issuenotes =& datamanager_init('Pt_IssueNote_User', $this->registry, ERRTYPE_ARRAY, 'pt_issuenote');
		$issuenotes->set_info('do_floodcheck', false);
		$issuenotes->set_info('parseurl', $this->registry->options['pt_allowbbcode']);
		$issuenotes->set('userid', $post['userid']);
		$issuenotes->set('username', $post['username']);
		$issuenotes->set('visible', 'visible');
		$issuenotes->set('isfirstnote', 1);
		$issuenotes->set('pagetext', $post['pagetext']);
		$issuenotes->set('dateline', $post['dateline']);
		$issuenotes->set('ipaddress', $post['ipaddress']);

		$this->postids = $post['postid'];

		$this->issuedata->pre_save();

		if (!$this->issuedata->errors)
		{
			$issuenotes->pre_save();
		}

		$this->errors = array_merge($this->issuedata->errors, $issuenotes->errors);

		if ($this->errors)
		{
			return $this->errors;
		}
		else
		{
			$this->issueid = $this->issuedata->save();

			$issuenotes->set('issueid', $this->issueid);
			$issuenotes->save();

			return $this->issueid;
		}
	}

	/**
	* Import those attachments allowed by the settings for the project tools
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_attachments()
	{
		parent::execute_import_attachments();

		$this->postlist[] = $this->datainfo['id'];

		$attachments = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.filedataid, a.state, a.filename, a.settings, a.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contenttypeid = " . intval($this->datainfo['contenttypeid']) . "
					AND
				a.contentid IN (" . implode(',', $this->postlist) . ")
		");

		while ($attach = $this->registry->db->fetch_array($attachments))
		{
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "attachment
					(contenttypeid, userid, dateline, filedataid, state, filename, settings, posthash, contentid)
				VALUES
					(" . vB_Types::instance()->getContentTypeID("vBProjectTools_Issue") . ",
					" . $this->registry->userinfo['userid'] . ",
					" . TIMENOW . ",
					" . $attach['filedataid'] . ",
					'" . $attach['state'] . "',
					'" . $this->registry->db->escape_string($attach['filename']) . "',
					'" . $this->registry->db->escape_string($attach['settings']) . "',
					'',
					$this->issueid)
			");

			$attachmentid = $this->registry->db->insert_id();

			// Specific values for PT
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "pt_issueattach
					(attachmentid, issueid, userid, visible, status, ispatchfile)
				VALUES
					($attachmentid,
					" . $this->issueid . ",
					" . $attach['userid'] . ",
					1,
					'current',
					" . (in_array(substr($attach['filename'], strpos($attach['filename'], '.')), array('diff', 'patch', 'xml')) ? 1 : 0) . ")
			");

			// Increment total attachment counter
			$this->totalattach++;
		}
	}

	/**
	* Update the original post
	*
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_from_source()
	{
		parent::execute_import_from_source();

		// We need to get the content type id of posts
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Post');

		if ($this->registry->options['ptimporter_keepthreads'])
		{
			// Adding the forward mode
			$this->importdata['pt_forwardmode'] = 0;
		}
		else
		{
			// Adding the forward mode
			$this->importdata['pt_forwardmode'] = 1;
		}

		// Serialize the data
		$data = serialize($this->importdata);

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueimport
				(issueid, contenttypeid, contentid, data)
			VALUES
				(" . $this->importdata['pt_issueid'] . ", $contenttypeid, " . $this->datainfo['postid'] . ", '" . $data . "')
		");

		// Update total attach counter into issue infos
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				attachcount = " . $this->totalattach . "
			WHERE issueid = " . $this->issueid . "
		");
	}

	public function execute_import_insert_notice()
	{
		parent::execute_import_insert_notice();

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->issueid);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_imported_post');
		$change->set('oldvalue', $this->datainfo['threadid']); // It seems there is a bug with SEO urls which goes to 'post' content - need to use 'thread'.
		$change->set('newvalue', $this->datainfo['originaltitle']);
		$change->save();
	}
}

/**
* This class imports an issue note from forums as new issue
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Import_Issuenote extends vB_Pt_Import
{
	public function execute_import_issue()
	{
		parent::execute_import_issue();

		// prepare issue notes
		$issuenote = $this->registry->db->query_first("
			SELECT userid, username, pagetext, dateline, ipaddress
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . intval($this->datainfo['issuenoteid']) . "
		");

		$issuenotes =& datamanager_init('Pt_IssueNote_User', $this->registry, ERRTYPE_ARRAY, 'pt_issuenote');
		$issuenotes->set_info('do_floodcheck', false);
		$issuenotes->set_info('parseurl', $this->registry->options['pt_allowbbcode']);
		$issuenotes->set('userid', $issuenote['userid']);
		$issuenotes->set('username', $issuenote['username']);
		$issuenotes->set('visible', 'visible');
		$issuenotes->set('isfirstnote', 1);
		$issuenotes->set('pagetext', $issuenote['pagetext']);
		$issuenotes->set('dateline', $issuenote['dateline']);
		$issuenotes->set('ipaddress', $issuenote['ipaddress']);

		$this->issuedata->pre_save();

		if (!$this->issuedata->errors)
		{
			$issuenotes->pre_save();
		}

		$this->errors = array_merge($this->issuedata->errors, $issuenotes->errors);

		if ($this->errors)
		{
			return $this->errors;
		}
		else
		{
			$this->issueid = $this->issuedata->save();

			$issuenotes->set('issueid', $this->issueid);
			$issuenotes->save();

			return $this->issueid;
		}
	}

	/**
	* Import those attachments allowed by the settings for the project tools
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_attachments()
	{
		parent::execute_import_attachments();

		$this->postlist[] = $this->datainfo['id'];

		$attachments = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.filedataid, a.state, a.filename, a.settings, a.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			WHERE
				a.contenttypeid = " . intval($this->datainfo['contenttypeid']) . "
					AND
				a.contentid IN (" . implode(',', $this->postlist) . ")
		");

		while ($attach = $this->registry->db->fetch_array($attachments))
		{
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "attachment
					(contenttypeid, userid, dateline, filedataid, state, filename, settings, posthash, contentid)
				VALUES
					(" . vB_Types::instance()->getContentTypeID("vBProjectTools_Issue") . ",
					" . $this->registry->userinfo['userid'] . ",
					" . TIMENOW . ",
					" . $attach['filedataid'] . ",
					'" . $attach['state'] . "',
					'" . $this->registry->db->escape_string($attach['filename']) . "',
					'" . $this->registry->db->escape_string($attach['settings']) . "',
					'',
					$this->issueid)
			");

			$attachmentid = $this->registry->db->insert_id();

			// Specific values for PT
			$this->registry->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "pt_issueattach
					(attachmentid, issueid, userid, visible, status, ispatchfile)
				VALUES
					($attachmentid,
					" . $this->issueid . ",
					" . $attach['userid'] . ",
					1,
					'current',
					" . (in_array(substr($attach['filename'], strpos($attach['filename'], '.')), array('diff', 'patch', 'xml')) ? 1 : 0) . ")
			");

			// Increment total attachment counter
			$this->totalattach++;
		}
	}

	/**
	* Update the original issue note
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	public function execute_import_from_source()
	{
		parent::execute_import_from_source();

		// We need to get the content type id of issue notes
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBProjectTools_IssueNote');

		// Adding the forward mode
		$this->importdata['pt_forwardmode'] = 0; // No use here

		// Srialialize the data
		$data = serialize($this->importdata);

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueimport
				(issueid, contenttypeid, contentid, data)
			VALUES
				(" . $this->importdata['pt_issueid'] . ", $contenttypeid, " . $this->datainfo['issuenoteid'] . ", '" . $data . "')
		");

		// Update total attach counter into issue infos
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				attachcount = " . $this->totalattach . "
			WHERE issueid = " . $this->issueid . "
		");
	}

	public function execute_import_insert_notice()
	{
		parent::execute_import_insert_notice();

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->issueid);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_imported_issuenote');
		$change->set('oldvalue', $this->datainfo['issueid']); // There is no issuenote SEO url.
		$change->set('newvalue', $this->datainfo['originaltitle']);
		$change->save();
	}
}

/**
* This class choose the correct method to export data from Project Tools.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Export_Factory
{
	/**
	* The vBulletin Registry
	*
	* @var	vB_Registry object
	*/
	public $registry = null;

	/**
	* The type of data from the target
	*
	* @var	string
	*/
	var $datatype = '';

	/**
	* The info of the target - this could be post or thread
	*
	* @var	array
	*/
	var $datainfo = array();

	/**
	* The id of the existing issue
	*
	* @var integer
	*/
	var $contentid = 0;

	/**
	* The project info of the source project
	*
	* @var	array
	*/
	var $project = array();

	/**
	* The posting permissions
	*
	* @var	array
	*/
	var $posting_perms = array();

	/**
	* An array containing the ids of the issue notes to export
	*
	* @var	array
	*/
	var $postids = array();

	/**
	* An array containing the ids of the attachments to export
	*
	* @var	array
	*/
	var $attachmentids = array();

	public function fetch_export($datatype)
	{
		switch ($datatype)
		{
			case 'thread':
				$out = new vB_Pt_Export_Thread();
				break;
			case 'post':
				$out = new vB_Pt_Export_Post();
				break;
			case 'issuethread':
				$out = new vB_Pt_Export_Issuethread();
				break;
			default:
				trigger_error('vB_Pt_Export_Factory::fetch_export(): Invalid export type.', E_USER_ERROR);
		}

		$out->registry =& $this->registry;
		$out->datatype =& $this->datatype;
		$out->datainfo =& $this->datainfo;
		$out->project =& $this->project;
		$out->posting_perms =& $this->posting_perms;

		return $out;
	}
}

/**
* This class exports an issue note from Project Tools.
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Export
{
	/**
	* The vBulletin Registry
	* 
	* @var	vB_Registry object
	*/
	public $registry = null;

	/**
	* The type of data from the target
	*
	* @var	string
	*/
	var $datatype = '';

	/**
	* The info of the target - this could be post or thread
	*
	* @var	array
	*/
	var $datainfo = array();

	/**
	* The id of the existing issue
	*
	* @var integer
	*/
	var $contentid = 0;

	/**
	* The project info of the source project
	*
	* @var	array
	*/
	var $project = array();

	/**
	* The posting permissions
	*
	* @var	array
	*/
	var $posting_perms = array();

	/**
	* Constructor. Prevents direct instantiation.
	*/
	public function __construct()
	{
		if (!is_subclass_of($this, 'vB_Pt_Export'))
		{
			trigger_error('Direct instantiation of vB_Pt_Export class prohibited. Use the vB_Pt_Export_Factory class.', E_USER_ERROR);
		}

		// Require to get the contenttypeid
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
	}

	/**
	* Executes all export methods in the correct order
	*
	* @return	integer		The id of the actual issue note
	*/
	public function export_all()
	{
		// Export the content
		$this->export_issue();

		// Export attachments
		$this->execute_export_attachments();

		// Export subscriptions
		$this->execute_export_subscriptions();

		// Create export notice
		$this->execute_export_insert_notice();

		// Useful to redirect the user
		return $this->contentid;
	}

	/**
	* Export the issue in the choosen type
	*
	* @return	mixed
	*/
	public function export_issue()
	{
		// Nothing to do here - will be in subclasses
		return $this->contentid;
	}

	/**
	* Export attachments from issue(notes) to the target
	*
	* @return	mixed
	*/
	private function execute_export_attachments()
	{
		// Nothing to do here - will be in subclasses
		return true;
	}

	/**
	* Export subscriptions from issue(notes) to the target
	*
	* @return	mixed
	*/
	private function execute_export_subscriptions()
	{
		// Nothing to do here - will be in subclasses
		return true;
	}

	/**
	* Insert some notice in the original issue
	*
	* @return	mixed
	*/
	public function execute_export_insert_notice()
	{
		if (!$this->registry->options['ptimporter_createnotice'])
		{
			return false;
		}
	}
}

/**
* This class exports an issue note from Project Tools as new thread
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Export_Thread extends vB_Pt_Export
{
	/**
	* Export the issue to the target datatype
	*
	* @return	mixed
	*/
	public function export_issue()
	{
		// Verify if the sent hash is correct
		if (md5($this->datainfo['poststarttime'] . $this->registry->userinfo['userid'] . $this->registry->userinfo['salt']) != $this->datainfo['posthash'])
		{
			return false;
		}

		$foruminfo = verify_id('forum', $this->datainfo['forumid'], 0, 1);
		$userinfo = verify_id('user', $this->datainfo['userid'], 0, 1);

		$allowsmilies = ($foruminfo['options'] & 512) ? 1 : 0;

		// Export as new thread
		$thread =& datamanager_init('Thread_FirstPost', $this->registry, ERRTYPE_ARRAY, 'threadpost');
			$thread->set_info('posthash', $this->datainfo['posthash']);
			$thread->setr('userid', $userinfo['userid']);
			$thread->setr('title', $this->datainfo['title']);
			$thread->setr('forumid', $foruminfo['forumid']);
			$thread->setr('dateline', $this->datainfo['dateline']);
			$thread->setr('pagetext', $this->datainfo['pagetext']);
			$thread->setr('allowsmilie', $allowsmilies);

			if (
				((
					($foruminfo['moderatenewpost']) OR !($forumperms & $this->registry->bf_ugp_forumpermissions['followforummoderation'])
				)
				AND !can_moderate($foruminfo['forumid']))
			)
			{
				// note: specified post comes from a variable passed into newreply.php
				$thread->set('visible', 0);
			}
			else
			{
				$thread->set('visible', 1);
			}

		$this->contentid = $thread->save();

		parent::export_issue();
	}

	/**
	* Insert some notice in the original issue
	*
	* @return	mixed
	*/
	public function execute_export_insert_notice()
	{
		parent::execute_export_insert_notice();

		$contentdata = $this->registry->db->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "thread
			WHERE threadid = " . $this->contentid . "
		");

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->datainfo['issueid']);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_exported');
		$change->set('oldvalue', $this->contentid);
		$change->set('newvalue', $contentdata['title']);
		$change->save();
	}
}

/**
* This class exports an issue note from Project Tools as new post in an existing thread
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Export_Post extends vB_Pt_Export
{
	/**
	* Export the issue to the target datatype
	*
	* @return	mixed
	*/
	public function export_issue()
	{
		// Verify if the sent hash is correct
		if (md5($this->datainfo['poststarttime'] . $this->registry->userinfo['userid'] . $this->registry->userinfo['salt']) != $this->datainfo['posthash'])
		{
			return false;
		}

		$threaddata = verify_id('thread', $this->datainfo['threadid'], 0, 1);
		$foruminfo = verify_id('forum', $this->datainfo['forumid'], 0, 1);
		$userinfo = verify_id('user', $this->datainfo['userid'], 0, 1);

		$forumperms = fetch_permissions($foruminfo['forumid']);
		$threadid = $threaddata['threadid'];

		$allowsmilies = ($foruminfo['options'] & 512) ? 1 : 0;
		$allowsignature = ($userinfo['options'] & 1) AND !empty($userinfo['signature']) ? 1 : 0;

		// Export as post in a thread
		$post =& datamanager_init('Post', $this->registry, ERRTYPE_ARRAY, 'threadpost');
			$post->set_info('posthash', $this->datainfo['posthash']);
			$post->setr('userid', $userinfo['userid']);
			$post->setr('title', $this->datainfo['title']);
			$post->setr('dateline', $this->datainfo['dateline']);
			$post->setr('pagetext', $this->datainfo['pagetext']);
			$post->setr('allowsmilie', $allowsmilies);

			$post->setr('showsignature', $allowsignature);
			$post->setr('iconid', $this->registry->options['showdeficon']);

			// get parentid of the new post
			// we're not posting a new thread, so make this post a child of the first post in the thread
			if(!empty($threaddata['firstpostid']))
			{
				//we have the postid in the thread table (firstpostid)
				$parentid = $threaddata['firstpostid'];
			}
			else
			{
				//for some reason it might not be available in the $threadinfo array, need to fetch it
				$getfirstpost = $this->registry->db->query_first("
					SELECT postid
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = " . $threaddata['threadid'] . "
					ORDER BY dateline
					LIMIT 1
				");
				$parentid = $getfirstpost['postid'];
			}

			$post->setr('parentid', $parentid);
			$post->setr('threadid', $threadid);

			$post->setr('htmlstate', trim('on_nl2br')); // trim() use is needed - PHP don't like to have numbers in variables, need to 'confirm' this is text, not integer

			if ($userinfo['userid'] == 0)
			{
				$post->setr('username', $userinfo['username']);
			}

			if (
				((
					($foruminfo['moderatenewpost']) OR !($forumperms & $this->registry->bf_ugp_forumpermissions['followforummoderation'])
				)
				AND !can_moderate($foruminfo['forumid']))
			)
			{
				// note: specified post comes from a variable passed into newreply.php
				$post->set('visible', 0);
			}
			else
			{
				$post->set('visible', 1);
			}

		$this->contentid = $post->save();

		parent::export_issue();
	}

	/**
	* Insert some notice in the original issue
	*
	* @return	mixed
	*/
	public function execute_export_insert_notice()
	{
		parent::execute_export_insert_notice();

		$contentdata = $this->registry->db->query_first("
			SELECT title, threadid
			FROM " . TABLE_PREFIX . "post
			WHERE postid = " . $this->contentid . "
		");

		if (!$contentdata['title'])
		{
			$contentdata = $this->registry->db->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "thread
				WHERE threadid = " . $contentdata['threadid'] . "
			");
		}

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->datainfo['issueid']);
		$change->set('userid', $this->registry->userinfo['userid']);

		$change->set('field', 'issue_exported_post');
		$change->set('oldvalue', $this->contentid); // It seems there is a bug with SEO urls which goes to 'post' content - need to use 'thread'.
		$change->set('newvalue', $contentdata['title']);
		$change->save();
	}
}

/**
* This class exports an issue note from Project Tools as new issue
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Export_Issuethread extends vB_Pt_Export
{
	/**
	* Export the issue to the target datatype
	*
	* @return	mixed
	*/
	public function export_issue()
	{
		// First, create the thread which will 'host' all replies

		// Verify if the sent hash is correct
		if (md5($this->datainfo['poststarttime'] . $this->registry->userinfo['userid'] . $this->registry->userinfo['salt']) != $this->datainfo['posthash'])
		{
			return false;
		}

		$foruminfo = verify_id('forum', $this->datainfo['forumid'], 0, 1);
		$userinfo = verify_id('user', $this->datainfo['userid'], 0, 1);

		$allowsmilies = ($foruminfo['options'] & 512) ? 1 : 0;

		// Export as new thread
		$thread =& datamanager_init('Thread_FirstPost', $this->registry, ERRTYPE_ARRAY, 'threadpost');
			$thread->set_info('posthash', $this->datainfo['posthash']);
			$thread->setr('userid', $userinfo['userid']);
			$thread->setr('title', $this->datainfo['title']);
			$thread->setr('forumid', $foruminfo['forumid']);
			$thread->setr('dateline', $this->datainfo['dateline']);
			$thread->setr('pagetext', $this->datainfo['pagetext']);
			$thread->setr('allowsmilie', $allowsmilies);

			if (
				((
					($foruminfo['moderatenewpost']) OR !($forumperms & $this->registry->bf_ugp_forumpermissions['followforummoderation'])
				)
				AND !can_moderate($foruminfo['forumid']))
			)
			{
				// note: specified post comes from a variable passed into newreply.php
				$thread->set('visible', 0);
			}
			else
			{
				$thread->set('visible', 1);
			}

		$this->threadid = $thread->save();

		// Now we have the threadid of the thread which will contains all replies,
		// we can create an array of issue notes from the issue minus the original issue note
		// and create a post in a foreach for every issue note listed.
		$issuenotearray = array();

		$issuenotelist = $this->registry->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issueid = " . $this->datainfo['issueid'] . "
				AND issuenoteid != " . $this->datainfo['issuenoteid'] . "
				AND type = 'user'
		");

		while ($issuenotedata = $this->registry->db->fetch_array($issuenotelist))
		{
			$issuenotearray[$issuenotedata['issuenoteid']] = $issuenotedata;
		}

		foreach ($issuenotearray AS $issuenoteid => $issuenote)
		{
			// Create a post for each issue note
			$post =& datamanager_init('Post', $this->registry, ERRTYPE_ARRAY, 'threadpost');
				$post->set_info('posthash', $this->datainfo['posthash']);
				$post->setr('userid', $issuenote['userid']);
				$post->setr('dateline', $issuenote['dateline']);
				$post->setr('pagetext', $issuenote['pagetext']);
				$post->setr('allowsmilie', $allowsmilies);
				$post->setr('ipaddress', $issuenote['ipaddress']);

				$post->setr('showsignature', $allowsignature);
				$post->setr('iconid', $this->registry->options['showdeficon']);

				// get parentid of the new post
				$getfirstpost = $this->registry->db->query_first("
						SELECT postid
						FROM " . TABLE_PREFIX . "post
						WHERE threadid = " . $this->threadid . "
						ORDER BY dateline
						LIMIT 1
				");

				$post->setr('parentid', $getfirstpost['postid']);
				$post->setr('threadid', $this->threadid);

				$post->setr('htmlstate', trim('on_nl2br')); // trim() use is needed - PHP don't like to have numbers in variables, need to 'confirm' this is text, not integer

				if ($userinfo['userid'] == 0)
				{
					$post->setr('username', $issuenote['username']);
				}

				if (in_array($issuenote['visible'], array('moderation', 'private')))
				{
					// note: specified post comes from a variable passed into newreply.php
					$post->set('visible', 0);
				}
				else
				{
					$post->set('visible', 1);
				}

			$post->save();
		}

		require_once(DIR . '/includes/functions_databuild.php');
		build_thread_counters($this->threadid);

		$this->contentid = $this->threadid;

		parent::export_issue();
	}

	/**
	* Insert some notice in the original issue
	*
	* @return	mixed
	*/
	public function execute_export_insert_notice()
	{
		parent::execute_export_insert_notice();

		$contentdata = $this->registry->db->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "thread
			WHERE threadid = " . $this->threadid . "
		");

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->datainfo['issueid']);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_exported_issuethread');
		$change->set('oldvalue', $this->contentid); // It seems there is a bug with SEO urls which goes to 'post' content - need to use 'thread'.
		$change->set('newvalue', $contentdata['title']);
		$change->save();
	}
}

?>