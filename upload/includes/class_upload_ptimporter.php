<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
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

require_once(DIR . '/includes/class_upload_pt.php');
require_once(DIR . '/includes/class_image.php');
require_once(DIR . '/includes/class_dm.php');
require_once(DIR . '/includes/class_dm_attachment_pt.php');

/**
* This class imports attachments from the forum into the project tools
* 
* The file extension and the filesize are not checked in this class - you have to do it before!
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Upload_Attachment_PtImporter extends vB_Upload_Attachment_Pt
{
	/**
	* Array that holds all information about the vB attachment
	*
	* @var	array
	*/
	var $attachinfo = array();

	/**
	* Allows all file sizes - returns the uploaded file size * 2
	* Make sure you check beforehand if you want to
	*/
	function fetch_max_uploadsize($extension)
	{
		return $this->upload['filesize'] * 2;
	}

	/**
	* Allows all extensions
	* Make sure you check beforehand if you want to
	*/
	function is_valid_extension($extension)
	{
		return true;
	}

	/**
	* This is a HIGHLY trimmed accept_upload function which checks NOTHING - only use from the ptimporter class!!!
	*
	* @var	array	An array containing the file information
	*/
	function accept_upload(&$upload)
	{
		$this->error = '';

		$this->upload['filename'] = trim($upload['name']);
		$this->upload['filesize'] = intval($upload['size']);
		$this->upload['location'] = trim($upload['tmp_name']);
		$this->upload['extension'] = strtolower(file_extension($this->upload['filename']));
		$this->upload['thumbnail'] = '';
		$this->upload['filestuff'] = '';

		return true;
	}

	/**
	* Saves the upload using the information from the vB attachment
	*/
	function save_upload()
	{
		$this->data->set('dateline', $this->attachinfo['dateline']);
		$this->data->set('thumbnail_dateline', TIMENOW); // thumbnail is new
		$this->data->set('visible', false); // This is to suppress the creation of upload notices
		$this->data->setr('userid', $this->attachinfo['userid']);
		$this->data->setr('filename', $this->attachinfo['filename']);
		$this->data->setr_info('filedata', $this->upload['filestuff']);
		$this->data->setr_info('thumbnail', $this->upload['thumbnail']['filedata']);
		$this->data->setr('issueid', $this->issueinfo['issueid']);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		// Correct visibility
		$this->data->condition = sprintf($this->data->condition_construct[0], $result);

		if ($this->attachinfo['state'] == 'visible')
		{
			// Visible
			$this->data->set('visible', 1);
		}
		else
		{
			// Moderation
			$this->data->set('visible', 0);
		}
		$this->data->save();

		unset($this->upload);

		return $result;
	}
}

?>