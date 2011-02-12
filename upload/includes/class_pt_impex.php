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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* This class imports a thread into the Project Tools.
* 
* Note that NO PERMISSION CHECKING is done here. You have to do it beforehand.
* Requires most of the data available in $vbulletin->GPC.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_Impex
{
	/**
	 * The vBulletin Registry
	 * 
	 * @var	vB_Registry object
	 */
	public $registry = NULL;

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
	public function __construct(vB_Registry &$registry, $datatype, $datainfo, $project, $posting_perms, $postids = array(), $attachmentids = array())
	{
		$this->registry = &$registry;
		$this->datatype = $datatype;
		$this->datainfo = $datainfo;
		$this->project = $project;

		$this->postids = $this->validate_array($postids);
		$this->attachmentids = $this->validate_array($attachmentids);

		// Require to get the contenttypeid
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
	}

	/**
	* Make sure we have an array here
	*
	* @param	mixed		Input
	*
	* @return 	array		The input in array form
	*/
	private function validate_array($source = array())
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
	public function import_all()
	{
		// Import issue and notes
		$this->execute_import_issue();

		// Assign to self
		$this->execute_import_set_assignment();

		// Import attachments
		$this->execute_import_attachments();

		// Import subscriptions
		$this->execute_import_subscriptions();

		switch ($this->datatype)
		{
			case 'thread':
				// Update the original thread
				$this->execute_import_from_thread();
				break;

			case 'post':
				// Update the original post
				$this->execute_import_from_post();
				break;
		}

		// Create import notice
		$this->execute_import_insert_notice();

		// Useful to redirect the user
		return $this->issueid;
	}

	/**
	* Import the issue and its notes
	*
	* @return	integer		The id of the new issue
	*/
	private function execute_import_issue()
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
		$issuedata->set('submituserid', ($this->datainfo['userid'] ? $this->datainfo['userid'] : $this->datainfo['postuserid']));
		$issuedata->set('submitusername', ($this->datainfo['username'] ? $this->datainfo['username'] : $this->datainfo['postusername']));
		$issuedata->set('visible', 'visible');
		$issuedata->set('submitdate', $this->datainfo['dateline']);
		$issuedata->set('lastpost', $this->datainfo['lastpost']);

		if ($this->datatype == 'thread')
		{
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

					$issuedata->pre_save();

					if (!$issuedata->errors)
					{
						$issuenotes[$i]->pre_save();
					}

					$errors = array_merge($issuedata->errors, $issuenotes[$i]->errors);

					$postids[] = $post['postid'];

					$i++;
				}

				$this->postids = $postids;
			}

			if ($errors)
			{
				require_once(DIR . '/includes/functions_newpost.php');
				echo construct_errors($errors);
				exit; // need to review this on a later release - issue #156

				$_REQUEST['do'] = 'importthread2';
			}
			else
			{
				$this->issueid = $issuedata->save();

				for ($i = 0; $i < count($issuenotes); $i++)
				{
					$issuenotes[$i]->set('issueid', $this->issueid);
					$issuenotes[$i]->save();
				}

				return $this->issueid;
			}
		}
		else if ($this->datatype == 'post')
		{
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

			$issuedata->pre_save();

			if (!$issuedata->errors)
			{
				$issuenotes->pre_save();
			}

			$errors = array_merge($issuedata->errors, $issuenotes->errors);

			if ($errors)
			{
				require_once(DIR . '/includes/functions_newpost.php');
				echo construct_errors($errors);
				exit; // need to review this on a later release - issue #156

				$_REQUEST['do'] = 'importthread2';
			}
			else
			{
				$this->issueid = $issuedata->save();

				$issuenotes->set('issueid', $this->issueid);
				$issuenotes->save();

				return $this->issueid;
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
	* Make sure to set $this->issueid and $this->postids if you have not called execute_import_issue() before!
	*/
	private function execute_import_attachments()
	{
		if (!$this->datainfo['attach'])
		{
			return;
			
		}

		$attachlimit = count($this->attachmentids) > 0 ? 'AND attachmentid IN (' . implode(',', $this->attachmentids) . ') ' : '';

		if (!$this->registry->options['ptimporter_ignoreattachlimits'])
		{
			if ($this->datatype == 'thread')
			{
				$attachlimit .= "AND attachment.contentid IN (" . implode(',', $this->postids) . ")";
			}
			else if ($this->datatype == 'post')
			{
				$attachlimit .= "AND attachment.contentid = " . $this->postids . " ";
			}

			// Make sure only those attachments are selected that comply with the limits
			$attachlimit .= 'AND LOWER(fd.extension) IN (\'' . implode('\',\'', preg_split('#\s+#', strtolower($this->registry->options['pt_attachmentextensions']))) . '\') ';
			$attachlimit .= 'AND fd.filesize <= ' . $this->registry->options['pt_attachmentsize'] * 1024;
		}

		if ($this->registry->options['pt_attachfile'] OR $this->registry->options['attachfile'] > 0)
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
	private function execute_import_attachments_database($attachlimit)
	{
		$this->attachmentids = array();

		// Query to select adequate attachments
		// vB4 switched from postid to contentid to be available in all parts of vB
		// And added contenttypeid 1 to limit the selection to posts (security)
		$attach_query = $this->registry->db->query_read("
			SELECT attachment.filedataid AS attachmentid
			FROM " . TABLE_PREFIX . "attachment AS attachment
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = attachment.filedataid)
			WHERE attachment.contenttypeid = 1
				$attachlimit
			ORDER BY attachment.dateline
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
			SELECT $this->issueid, attachment.userid, attachment.filename, fd.extension, attachment.dateline, IF(attachment.state = 'visible', 1, 0), fd.filesize, fd.filehash, fd.filedata, fd.thumbnail, fd.thumbnail_filesize, fd.thumbnail_dateline
			FROM " . TABLE_PREFIX . "attachment AS attachment
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = attachment.filedataid)
			WHERE attachment.attachmentid IN (" . implode(',', $this->attachmentids) . ")
		");
	}

	/**
	* Import attachments by using a temporary file
	* 
	* This function is to be used if either vBulletin or PT uses the filesystem as attachment datastore.
	*/
	private function execute_import_attachments_filesystem($attachlimit)
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
			SELECT attachment.filedataid AS attachmentid, attachment.dateline, attachment.state, attachment.userid, attachment.filename, fd.filedata
			FROM " . TABLE_PREFIX . "attachment AS attachment
				INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = attachment.filedataid)
			WHERE attachment.contenttypeid = 1
				$attachlimit
			ORDER BY attachment.dateline
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
	* Update the original thread
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	private function execute_import_from_thread()
	{
		// We need to get the content type id of threads
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Thread');

		// Define $importdata as an array of values to serialize
		$importdata = array();

		// Adding the issue id
		$importdata['pt_issueid'] = $this->issueid;

		if ($this->registry->options['ptimporter_keepthreads'])
		{
			// Adding the forward mode
			$importdata['pt_forwardmode'] = 0;
		}
		else
		{
			// Adding the forward mode
			$importdata['pt_forwardmode'] = 1;

			// Close the original thread - this is rules by the vb option 'ptimporter_keepthreads'
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					open = 0
				WHERE threadid = " . $this->datainfo['threadid'] . "
			");
		}

		// Serialize the data
		$data = serialize($importdata);

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueimport
				(issueid, contenttypeid, contentid, data)
			VALUES
				(" . $importdata['pt_issueid'] . ", $contenttypeid, " . $this->datainfo['threadid'] . ", '" . $data . "')
		");
	}

	/**
	* Update the original post
	*
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	private function execute_import_from_post()
	{
		// We need to get the content type id of threads
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Post');

		// Define $importdata as an array of values to serialize
		$importdata = array();

		// Adding the issue id
		$importdata['pt_issueid'] = $this->issueid;

		if ($this->registry->options['ptimporter_keepthreads'])
		{
			// Adding the forward mode
			$importdata['pt_forwardmode'] = 0;
		}
		else
		{
			// Adding the forward mode
			$importdata['pt_forwardmode'] = 1;
		}

		// Serialize the data
		$data = serialize($importdata);

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issueimport
				(issueid, contenttypeid, contentid, data)
			VALUES
				(" . $importdata['pt_issueid'] . ", $contenttypeid, " . $this->datainfo['postid'] . ", '" . $data . "')
		");
	}

	/**
	* Insert a notice stating the import date and the importer
	* 
	* Make sure to set $this->issueid if you have not called execute_import_issue() before!
	*/
	private function execute_import_insert_notice()
	{
		if (!$this->registry->options['ptimporter_createnotice'])
		{
			return;
		}

		$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
		$change->set('issueid', $this->issueid);
		$change->set('userid', $this->registry->userinfo['userid']);

		if ($this->datatype == 'thread')
		{
			$change->set('field', 'issue_imported');
			$change->set('oldvalue', $this->datainfo['threadid']);
		}

		if ($this->datatype == 'post')
		{
			$change->set('field', 'issue_imported_post');
			$change->set('oldvalue', $this->datainfo['threadid']); // It seems there is a bug with SEO urls which goes to 'post' content - need to use 'thread'.
		}

		$change->set('newvalue', $this->datainfo['threadtitle']);
		$change->save();
	}

	/**
	* Executes all export methods in the correct order
	*
	* @return	integer		The id of the actual issue
	*/
	public function export_all()
	{
		// Export issue and notes
		$this->execute_export_issue();

		// Import attachments
		$this->execute_export_attachments();

		// Import subscriptions
		$this->execute_export_subscriptions();

		switch ($this->datatype)
		{
			case 'thread':
				// Update the original thread
				$this->execute_export_to_thread();
				break;

			case 'post':
				// Update the original post
				$this->execute_export_to_post();
				break;
		}

		// Create export notice
		$this->execute_export_insert_notice();

		// Useful to redirect the user
		return $this->issueid;
		
	}
}

?>