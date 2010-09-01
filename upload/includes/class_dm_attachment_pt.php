<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
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

// Temporary
require_once(DIR . '/includes/functions_file.php');

/**
* Abstract class to do data save/delete operations for ATTACHMENTS in the PT.
* You should call the fetch_library() function to instantiate the correct
* object based on how attachments are being stored.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Attachment_Pt extends vB_DataManager
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'attachmentid'       => array(TYPE_UINT,     REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'userid'             => array(TYPE_UINT,     REQ_YES),
		'issueid'            => array(TYPE_UINT,     REQ_YES),
		'dateline'           => array(TYPE_UNIXTIME, REQ_AUTO),
		'filename'           => array(TYPE_NOHTMLCOND, REQ_YES, VF_METHOD, 'verify_filename'),
		'filedata'           => array(TYPE_BINARY,   REQ_NO, VF_METHOD),
		'filesize'           => array(TYPE_UINT,     REQ_YES),
		'visible'            => array(TYPE_UINT,     REQ_NO),
		'counter'            => array(TYPE_UINT,     REQ_NO),
		'filehash'           => array(TYPE_STR,      REQ_YES, VF_METHOD, 'verify_md5'),
		'thumbnail'          => array(TYPE_BINARY,   REQ_NO, VF_METHOD),
		'thumbnail_dateline' => array(TYPE_UNIXTIME, REQ_AUTO),
		'thumbnail_filesize' => array(TYPE_UINT,     REQ_NO),
		'extension'          => array(TYPE_NOHTMLCOND, REQ_YES),
		'status'             => array(TYPE_STR,      REQ_NO),
		'ispatchfile'        => array(TYPE_UINT,     REQ_NO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issueattach';

	/**
	* Storage holder
	*
	* @var  array   Storage Holder
	*/
	var $lists = array();

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('attachmentid = %1$d', 'attachmentid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Attachment_Pt(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('ptattachdata_start')) ? eval($hook) : false;
	}

	/**
	* Fetches the appropriate subclassed based on how attachments are being stored.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*
	* @return	vB_DataManager_Attachment_Pt	Subclass of vB_DataManager_Attachment_Pt
	*/
	function &fetch_library(&$registry, $errtype = ERRTYPE_ARRAY)
	{
		// Library
		$selectclass = ($registry->options['pt_attachfile']) ? 'vB_DataManager_Attachment_Pt_Filesystem' : 'vB_DataManager_Attachment_Pt_Database';
		return new $selectclass($registry, $errtype);
	}

	/**
	* Verify that posthash is either md5 or empty
	* @param	string the md5
	*
	* @return	boolean
	*/
	function verify_md5_alt(&$md5)
	{
		return (empty($md5) OR (strlen($md5) == 32 AND preg_match('#^[a-f0-9]{32}$#', $md5)));
	}

	/**
	* Set the extension of the filename
	*
	* @param	filename
	*
	* @return	boolean
	*/
	function verify_filename(&$filename)
	{
		$this->set('extension', strtolower(substr(strrchr($filename, '.'), 1)));
		return true;
	}

	/**
	* Set the filesize of the thumbnail
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_thumbnail(&$thumbnail)
	{
		if (strlen($thumbnail) > 0)
		{
			$this->set('thumbnail_filesize', strlen($thumbnail));
		}
		return true;
	}

	/**
	* Set the filehash/filesize of the file
	*
	* @param	integer	Maximum posts per page
	*
	* @return	boolean
	*/
	function verify_filedata(&$filedata)
	{
		if (strlen($filedata) > 0)
		{
			$this->set('filehash', md5($filedata));
			$this->set('filesize', strlen($filedata));
		}

		return true;
	}

	/**
	* Saves the data from the object into the specified database tables
	* Overwrites parent
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/
	function save($doquery = true, $delayed = false)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
			$this->set('attachmentid', $return);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery))
		{
			return $return;
		}
		else
		{
			return false;
		}
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

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('ptattachdata_presave')) ? eval($hook) : false;

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
		// attachment counts
		if ($this->fetch_field('visible') AND empty($this->existing['visible']))
		{
			// new attachment or making one invisible
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					attachcount = attachcount + 1
				WHERE issueid = " . intval($this->fetch_field('issueid'))
			);
		}
		else if (!$this->fetch_field('visible') AND !empty($this->existing['visible']))
		{
			// hiding visible attachment
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					attachcount = IF(attachcount > 0, attachcount - 1, 0)
				WHERE issueid = " . intval($this->fetch_field('issueid'))
			);
		}

		if (!$this->condition AND $this->fetch_field('visible'))
		{
			// insert issue change
			$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
			$change->set_info('roll_post_time_limit', 0); // disable folding for attachment uploads
			$change->set('issueid', $this->fetch_field('issueid'));
			$change->set('userid', $this->registry->userinfo['userid']);
			$change->set('field', 'attachment_uploaded');
			$change->set('newvalue', $this->fetch_field('filename'));
			$change->set('oldvalue','');
			$change->save();
		}

		($hook = vBulletinHook::fetch_hook('ptattachdata_postsave')) ? eval($hook) : false;
		return parent::post_save_each($doquery);
	}

	/**
	* Any code to run before deleting. Builds lists and updates mod log
	*
	* @param	Boolean Do the query?
	*/
	function pre_delete($doquery = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'idlist'   => array(),
			'issuelist' => array(),
		);

		$ids = $this->registry->db->query_read("
			SELECT
				attachment.attachmentid,
				attachment.userid,
				issue.issueid,
				issue.submitdate AS issue_dateline,
				issue.submituserid AS issue_userid
			FROM " . TABLE_PREFIX . "pt_issueattach AS attachment
			LEFT JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = attachment.issueid)
			WHERE " . $this->condition
		);
		while ($id = $this->registry->db->fetch_array($ids))
		{
			$this->lists['idlist']["{$id['attachmentid']}"] = $id['userid'];

			if ($id['issueid'])
			{
				$this->lists['issuelist']["{$id['issueid']}"]++;
			}
		}

		if ($this->registry->db->num_rows($ids) == 0)
		{
			// nothing to delete
			return false;
		}
		else
		{
			// condition needs to have any attachment. replaced with TABLE_PREFIX . attachment
			// since DELETE doesn't suport table aliasing in some versions of MySQL
			// we needed the attachment. for the query run above at the start of this function
			$this->condition = preg_replace('#(pt_issueattach\.)#si', TABLE_PREFIX . '\1', $this->condition);
			return true;
		}
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		// A little cheater function..
		if (!empty($this->lists['idlist']) AND $this->registry->options['pt_attachfile'])
		{
			require_once(DIR . '/includes/functions_file.php');
			// Delete attachments from the FS
			foreach ($this->lists['idlist'] AS $attachmentid => $userid)
			{
				@unlink(fetch_attachment_path($userid, $attachmentid, false, $this->registry->options['pt_attachpath']));
				@unlink(fetch_attachment_path($userid, $attachmentid, true, $this->registry->options['pt_attachpath']));
			}
		}

		// Build MySQL CASE Statement to update post/thread attach counters
		// future: examine using subselect option for MySQL 4.1

		foreach($this->lists['issuelist'] AS $issueid => $count)
		{
			$issueidlist .= ",$issueid";
			$issuecasesql .= " WHEN issueid = $issueid THEN $count";
		}

		if ($issuecasesql)
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue
				SET attachcount = CAST(attachcount AS SIGNED) -
				CASE
					$issuecasesql
					ELSE 0
				END
				WHERE issueid IN (-1$issueidlist)
			");
		}

		if ($this->fetch_field('visible'))
		{
			// insert issue change
			$change =& datamanager_init('Pt_IssueChange', $this->registry, ERRTYPE_STANDARD);
			$change->set_info('roll_post_time_limit', 0); // disable folding for attachment deletes
			$change->set('issueid', $this->fetch_field('issueid'));
			$change->set('userid', $this->registry->userinfo['userid']);
			$change->set('field', 'attachment_deleted');
			$change->set('newvalue', $this->fetch_field('filename'));
			$change->set('oldvalue','');
			$change->save();
		}

		($hook = vBulletinHook::fetch_hook('ptattachdata_delete')) ? eval($hook) : false;
	}
}

/**
* Class to do data save/delete operations for PT ATTACHMENTS in the DATABASE.
*
* @package	vBulletin Project Tools
* @version	$Revision$
*/

class vB_DataManager_Attachment_Pt_Database extends vB_DataManager_Attachment_Pt
{
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

		if (!empty($this->info['filedata']))
		{
			$this->setr('filedata', $this->info['filedata']);

			// determine if this file is actually a patch
			if (in_array(strtolower($this->fetch_field('extension')), array('txt', 'diff', 'patch')))
			{
				require_once(DIR . '/includes/class_pt_patch_parse.php');
				$patch_parser = new vB_PatchParser();
				$this->set('ispatchfile', $patch_parser->parse($this->info['filedata']) ? 1 : 0);
			}
		}
		if (!empty($this->info['thumbnail']))
		{
			$this->setr('thumbnail', $this->info['thumbnail']);
		}

		return parent::pre_save($doquery);
	}
}


/**
* Class to do data save/delete operations for PT ATTACHMENTS in the FILE SYSTEM.
*
* @package	vBulletin Project Tools
* @version	$Revision$
*/
class vB_DataManager_Attachment_Pt_Filesystem extends vB_DataManager_Attachment_Pt
{
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

		// make sure we don't have the binary data set
		// if so move it to an information field
		// benefit of this is that when we "move" files from DB to FS,
		// the filedata/thumbnail fields are not blanked in the database
		// during the update.
		if ($file =& $this->fetch_field('filedata'))
		{
			$this->setr_info('filedata', $file);
			$this->do_unset('filedata');
		}

		if ($thumb =& $this->fetch_field('thumbnail'))
		{
			$this->setr_info('thumbnail', $thumb);
			$this->do_unset('thumbnail');
		}

		if (!empty($this->info['filedata']))
		{
			$this->set('filehash', md5($this->info['filedata']));
			$this->set('filesize', strlen($this->info['filedata']));
		}
		if (!empty($this->info['thumbnail']))
		{
			$this->set('thumbnail_filesize', strlen($this->info['thumbnail']));
		}

		if (!empty($this->info['filedata']) OR !empty($this->info['thumbnail']))
		{
			$path = $this->verify_attachment_path($this->fetch_field('userid'));
			if (!$path)
			{
				$this->error('attachpathfailed');
				return false;
			}

			if (!is_writable($path))
			{
				$this->error('upload_file_system_is_not_writable');
				return false;
			}

			// determine if this file is actually a patch
			if (in_array(strtolower($this->fetch_field('extension')), array('txt', 'diff', 'patch')))
			{
				require_once(DIR . '/includes/class_pt_patch_parse.php');
				$patch_parser = new vB_PatchParser();
				$this->set('ispatchfile', $patch_parser->parse($this->info['filedata']) ? 1 : 0);
			}
		}

		return parent::pre_save($doquery);
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$attachmentid =& $this->fetch_field('attachmentid');
		$userid =& $this->fetch_field('userid');
		$failed = false;

		// Check for filedata in an information field
		if (!empty($this->info['filedata']))
		{
			$filename = fetch_attachment_path($userid, $attachmentid, false, $this->registry->options['pt_attachpath']);
			if ($fp = fopen($filename, 'wb'))
			{
				fwrite($fp, $this->info['filedata']);
				fclose($fp);
				#remove possible existing thumbnail in case no thumbnail is written in the next step.
				if (file_exists(fetch_attachment_path($userid, $attachmentid, true, $this->registry->options['pt_attachpath'])))
				{
					@unlink(fetch_attachment_path($userid, $attachmentid, true, $this->registry->options['pt_attachpath']));
				}
			}
			else
			{
				$failed = true;
			}
		}

		if (!$failed AND !empty($this->info['thumbnail']))
		{
			// write out thumbnail now
			$filename = fetch_attachment_path($userid, $attachmentid, true, $this->registry->options['pt_attachpath']);
			if ($fp = fopen($filename, 'wb'))
			{
				fwrite($fp, $this->info['thumbnail']);
				fclose($fp);
			}
			else
			{
				$failed = true;
			}
		}

		($hook = vBulletinHook::fetch_hook('ptattachdata_postsave')) ? eval($hook) : false;

		if ($failed)
		{
			if ($this->condition === null) // Insert, delete attachment
			{
				$this->condition = "attachmentid = $attachmentid";
				$this->delete();
			}

			// $php_errormsg is automatically set if track_vars is enabled
			$this->error('upload_copyfailed', htmlspecialchars_uni($php_errormsg));
			return false;
		}
		else
		{
			parent::post_save_each();
			return true;
		}
	}

	/**
	* Verify that user's attach path exists, create if it doesn't
	*
	* @param	int		userid
	*/
	function verify_attachment_path($userid)
	{
		if (!$userid)
		{
			return false;
		}

		$path = fetch_attachment_path($userid, 0, false, $this->registry->options['pt_attachpath']);
		if (vbmkdir($path))
		{
			return $path;
		}
		else
		{
			return false;
		}
	}
}
?>
