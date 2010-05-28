<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #             http://www.vbulletin.com/oslicense.html              # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* This class imports a thread into the Project Tools.
* 
* Note that NO PERMISSION CHECKING is done here. You have to do it beforehand.
* Requires most of the data available in $vbulletin->GPC.
*/
class vB_PtImporter
{
	/**
	 * The vBulletin Registry
	 * 
	 * @var	vB_Registry object
	 */
	public $registry = NULL;

	/**
	* The threadinfo of the source thread
	*
	* @var	array
	*/
	var $threadinfo = array();

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
	* An array containing the ids of the posts to import
	*
	* @var	array
	*/
	var $postids = array();

	/**
	* An array containing the ids of the attachments to import
	*
	* @var	array
	*/
	var $attachmentids = array();

	/**
	* Ctor.
	*
	* @param	integer		The id of the source thread
	* @param	integer		The id of the target project
	* @param	string		The issue type of the new issue
	* @param	array		Integer array containing the ids of the posts to import.
	* @param	array		Integer array containing the ids of the attachments to import.
	*/
	function __construct(vB_Registry &$registry, $threadinfo, $project, $posting_perms, $postids = array(), $attachmentids = array())
	{
		$this->registry = &$registry;
		$this->threadinfo = $threadinfo;
		$this->project = $project;

		$this->postids = $this->validate_array($postids);
		$this->attachmentids = $this->validate_array($attachmentids);
	}

	/**
	* Make sure we have an array here
	*
	* @param	mixed		Input
	*
	* @return 	array		The input in array form
	*/
	function validate_array($source = array())
	{
		if ($source == null)
		{
			return array();
		}

		if (!is_array($source))
		{
			return array($source);
		}

		return $source;
	}

	/**
	* Executes all import methods in the correct order
	*
	* @return	integer		The id of the new issue
	*/
	function import_all()
	{
		//Import issue and notes
		$this->execute_import_issue();

		// Assign to self
		$this->execute_set_assignment();

		// Import attachments
		$this->execute_import_attachments();

		// Import subscriptions
		$this->execute_import_subscriptions();

		// Update the original thread
		$this->execute_update_thread();

		// Create import notice
		$this->execute_insert_import_notice();

		// Useful to redirect the user
		return $this->issueid;
	}

	/**
	* Import the issue and its notes
	*
	* @return	integer		The id of the new issue
	*/
	function execute_import_issue()
	{
		// prepare issue
		$issuedata =& datamanager_init('Pt_Issue', $this->registry, ERRTYPE_ARRAY);
		$issuedata->set_info('project', $this->project);

		$issuedata->set('title', $this->registry->GPC['title']);
		$issuedata->set('summary', $this->registry->GPC['summary']);
		$issuedata->set('issuestatusid', $this->registry->GPC['issuestatusid']);
		$issuedata->set('priority', $this->registry->GPC['priority']);
		$issuedata->set('projectcategoryid', $this->registry->GPC['projectcategoryid']);
		$issuedata->set('appliesversionid', $this->registry->GPC['appliesversionid']);

		switch ($this->registry->GPC['addressedversionid'])
		{
			case -1:
				$issuedata->set('isaddressed', 1);
				$issuedata->set('addressedversionid', 0);
				break;
			case 0:
				$issuedata->set('isaddressed', 0);
				$issuedata->set('addressedversionid', 0);
				break;
			default:
				$issuedata->set('isaddressed', 1);
				$issuedata->set('addressedversionid', $this->registry->GPC['addressedversionid']);
				break;
		}

		$issuedata->set('projectid', $this->project['projectid']);
		$issuedata->set('issuetypeid', $this->registry->GPC['issuetypeid']);
		$issuedata->set('milestoneid', $this->registry->GPC['milestoneid']);
		$issuedata->set('submituserid', $this->threadinfo['postuserid']);
		$issuedata->set('submitusername', $this->threadinfo['postusername']);
		$issuedata->set('visible', 'visible');
		$issuedata->set('submitdate', $this->threadinfo['dateline']);
		$issuedata->set('lastpost', $this->threadinfo['lastpost']);

		$issuedata->pre_save();
		$errors = $issuedata->errors;

		// prepare issue notes
		$issuenotes = array();

		$i = 0;
		$postids = array();

		$postlimit = count($this->postids) > 0 ? 'AND postid IN (' . implode(',', $this->postids) . ')' : '';
		$threadid = $this->threadinfo['threadid'];

		$post_query = $this->registry->db->query_read("
			SELECT postid, userid, username, dateline, pagetext
			FROM " . TABLE_PREFIX . "post AS post
			WHERE threadid = $threadid
			$postlimit
			ORDER BY dateline
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
				$issuenotes[$i]->set('isfirstnote', 0);
				$issuenotes[$i]->set('pagetext', $post['pagetext']);
				$issuenotes[$i]->set('dateline', $post['dateline']);

				$issuenotes[$i]->pre_save();
				$errors = array_merge($errors, $issuenotes[$i]->errors);

				$postids[] = $post['postid'];

				$i++;
			}

			$this->postids = $postids;
		}

		if ($errors)
		{
			require_once(DIR . '/includes/functions_newpost.php');
			standard_error(construct_errors($errors));
		}

		$this->issueid = $issuedata->save();

		for ($i = 0; $i < count($issuenotes); $i++)
		{
			$issuenotes[$i]->set('issueid', $this->issueid);
			$issuenotes[$i]->save();
		}

		return $this->issueid;
	}

	/**
	* Assign to self if permitted and checkbox set
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	function execute_set_assignment()
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
	* Make sure to set $this->issueid and $this->postids if you have not called execute_import_issue() before!
	*/
	function execute_import_attachments()
	{
		if (!$this->threadinfo['attach'])
		{
			return;
		}

		$attachlimit = count($this->attachmentids) > 0 ? 'AND attachmentid IN (' . implode(',', $this->attachmentids) . ') ' : '';

		if (!$this->registry->options['ptimporter_ignoreattachlimits'])
		{
			// Make sure only those attachments are selected that comply with the limits
			$attachlimit .= 'AND LOWER(extension) IN (\'' . implode('\',\'', preg_split('#\s+#', strtolower($this->registry->options['pt_attachmentextensions']))) . '\') ';
			$attachlimit .= 'AND filesize <= ' . $this->registry->options['pt_attachmentsize'] * 1024;
		}

		if ($this->registry->options['pt_attachfile'] || $this->registry->options['attachfile'] > 0)
		{
			// There are attachments stored in the file system
			$this->execute_import_attachments_filesystem($attachlimit);
		}
		else
		{
			// Attachments are stored in the database
			$this->execute_import_attachments_database($attachlimit);
		}
	}

	/**
	* Import attachments by copying them within the database
	* 
	* This function is to be used if both vBulletin and PT uses the database as attachment datastore.
	*/
	function execute_import_attachments_database($attachlimit)
	{
		$this->attachmentids = array();

		$attach_query = $this->registry->db->query_read("
			SELECT attachmentid
			FROM " . TABLE_PREFIX . "attachment AS attachment
			WHERE postid IN (" . implode(',', $this->postids) . ")
			$attachlimit
			ORDER BY dateline
		");

		while ($attach = $this->registry->db->fetch_array($attach_query))
		{
			$this->attachmentids[] = $attach['attachmentid'];
		}

		if (count($this->attachmentids) < 1)
		{
			return;
		}

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueattach
				(issueid, userid, filename, extension, dateline, visible, filesize, filehash, filedata, thumbnail, thumbnail_filesize, thumbnail_dateline)
			SELECT $this->issueid, userid, filename, extension, dateline, visible, filesize, filehash, filedata, thumbnail, thumbnail_filesize, thumbnail_dateline
			FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid IN (" . implode(',', $this->attachmentids) . ")
		");
	}

	/**
	* Import attachments by using a temporary file
	* 
	* This function is to be used if either vBulletin or PT uses the filesystem as attachment datastore.
	*/
	function execute_import_attachments_filesystem($attachlimit)
	{
		require_once(DIR . '/includes/class_upload_ptimporter.php');
		require_once(DIR . '/includes/functions_file.php');

		if ($this->registry->options['attachfile'] > 0)
		{
			// vBulletin attachments are saved in the filesystem
			// Should be able to write in that directory
			$tempdir = $this->registry->options['attachpath'] . '/ptimporter_temp';
		}
		else
		{
			// Project Tools attachments are saved in the filesystem
			// Should be able to write in that directory
			$tempdir = $this->registry->options['pt_attachpath'] . '/ptimporter_temp';
		}

		// Make sure the directory exists and is writable
		if (!is_dir($tempdir))
		{
			vbmkdir($tempdir);
		}

		// Query to select adequate attachments
		// vB4 switched from postid to contentid to be available in all parts of vB
		// And added contenttypeid 1 to limit the selection to posts (security)
		$attach_query = $this->registry->db->query_read("
			SELECT attachmentid, dateline, visible, userid, filename, filedata
			FROM " . TABLE_PREFIX . "attachment AS attachment
			WHERE contentid IN (" . implode(',', $this->postids) . ")
				AND contenttypeid = 1
			$attachlimit
			ORDER BY dateline
		");

		while ($attach = $this->registry->db->fetch_array($attach_query))
		{
			// copy attachment to temporary directory
			$filename_temp = $tempdir . '/' . $attach['attachmentid'] . '.import';

			if ($this->registry->options['attachfile'] > 0)
			{
				// Saved in filesystem, copy to temp file
				$filename_current = fetch_attachment_path($attach['userid'], $attach['attachmentid']);
				copy($filename_current, $filename_temp);
			}
			else
			{
				// Saved in database, save to temp file
				$handle = fopen($filename_temp, 'w');
				fwrite($handle, $attach['filedata']);
				fclose($handle);
			}

			// Now we have a "uploaded" temporary file, import it
			$attachdata =& vB_DataManager_Attachment_Pt::fetch_library($this->registry, ERRTYPE_STANDARD);
			$upload = new vB_Upload_Attachment_PtImporter($this->registry);
			$image =& vB_Image::fetch_library($this->registry);

			$upload->data =& $attachdata;
			$upload->image =& $image;
			$upload->issueinfo = array('issueid' => $this->issueid);
			$upload->attachinfo = $attach;

			$attachment = array(
				'name'     => $attach['filename'],
				'tmp_name' => $filename_temp,
				'error'    => 0,
				'size'     => filesize($filename_temp)
			);

			$attachmentid = $upload->process_upload($attachment);
			if ($error = $upload->fetch_error())
			{
				standard_error($error);
			}

			$attachids[] = $attach['attachmentid'];
		}

		$this->attachmentids = $attachids;
	}

	/**
	* Import subscriptions
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	function execute_import_subscriptions()
	{
		$threadid = $this->threadinfo['threadid'];

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
	* Update the original thread
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	function execute_update_thread()
	{
		$threadid = $this->threadinfo['threadid'];
		$issueid = $this->issueid;

		if ($this->registry->options['ptimporter_keepthreads'])
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					ptissueid = $issueid
					ptforwardmode = 0,
				WHERE threadid = $threadid
			");
		}
		else
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					ptissueid = $issueid,
					ptforwardmode = 1,
					open = 0
				WHERE threadid = $threadid
			");
		}
	}

	/**
	* Insert a notice stating the import date and the importer
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	function execute_insert_import_notice()
	{
		if (!$this->registry->options['ptimporter_createnotice'])
		{
			return;
		}

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->issueid);
		$change->set('userid', $this->registry->userinfo['userid']);
		$change->set('field', 'issue_imported');
		$change->set('newvalue', $this->threadinfo['title']);
		$change->set('oldvalue', $this->threadinfo['threadid']);
		$change->save();
	}
}

?>