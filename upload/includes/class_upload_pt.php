<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.4                   # ||
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

require_once(DIR . '/includes/class_upload.php');

/**
* Abstracted class that handles uploading attachments to the project tools
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Upload_Attachment_Pt extends vB_Upload_Attachment
{
	/**
	* Array of information about the issue being posted into
	*
	* @var	array
	*/
	var $issueinfo = array();

	/**
	* The max upload file size for the specified extension. In the PT, this
	* is not per extension, but global.
	*
	* @param	string	File extension
	*
	* @return	integer	Max upload size, in bytes
	*/
	function fetch_max_uploadsize($extension)
	{
		return $this->registry->options['pt_attachmentsize'] * 1024;
	}

	/**
	* Determine if the selected attachment type is allowed to be uploaded
	*
	* @param	string	File extension
	*
	* @return	boolean
	*/
	function is_valid_extension($extension)
	{
		$valid_extensions = strtolower(trim($this->registry->options['pt_attachmentextensions']));
		if (!$valid_extensions)
		{
			return true;
		}
		else
		{
			return in_array($extension, preg_split('#\s+#', $valid_extensions));
		}
	}

	/**
	* Process the actual attachment upload
	*
	* @param	mixed	Information about the upload
	*
	* @return	boolean	True if the upload succeeded
	*/
	function process_upload($uploadstuff = '')
	{
		if ($this->accept_upload($uploadstuff))
		{
			// Verify Extension is proper
			if (!$this->is_valid_extension($this->upload['extension']))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			$jpegconvert = false;
			// is this a filetype that can be processed as an image?
			if ($this->image->is_valid_info_extension($this->upload['extension']))
			{
				$this->maxwidth = 0;
				$this->maxheight = 0;

				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}
				}
				else if ($this->upload['extension'] != 'pdf')
				{	// don't error on .pdf imageinfo failures
					if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					}
					else
					{
						$this->set_error('upload_invalid_image');
					}
					return false;
				}

				// Generate Thumbnail
				$thumbable = array('gif', 'jpg', 'jpe', 'jpeg', 'png');
				if (in_array($this->upload['extension'], $thumbable) AND $this->registry->options['attachthumbs'])
				{
					$labelimage = ($this->registry->options['attachthumbs'] == 3 OR $this->registry->options['attachthumbs'] == 4);
					$drawborder = ($this->registry->options['attachthumbs'] == 2 OR $this->registry->options['attachthumbs'] == 4);
					$this->upload['thumbnail'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->registry->options['attachthumbssize'], $this->registry->options['attachthumbssize'], $this->registry->options['thumbquality'], $labelimage, $drawborder, $jpegconvert, true, $this->upload['resized']['width'], $this->upload['resized']['height'], $this->upload['resized']['filesize']);
					if (empty($this->upload['thumbnail']['filedata']) AND !empty($this->upload['thumbnail']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
						{
							$this->set_warning('thumbnail_failed_x', htmlspecialchars_uni($error));
						}
						else
						{
							$this->set_warning($this->upload['thumbnail']['imageerror']);
						}
					}
				}
			}

			$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);
			if (!$jpegconvert AND $this->maxuploadsize > 0 AND $this->upload['filesize'] > $this->maxuploadsize)
			{
				$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
				return false;
			}

			if (!empty($this->upload['resized']))
			{
				if (!empty($this->upload['resized']['filedata']))
				{
					$this->upload['filestuff'] =& $this->upload['resized']['filedata'];
					$this->upload['filesize'] =& $this->upload['resized']['filesize'];
					if ($this->upload['resized']['filename'])
					{
						$this->upload['filename'] =& $this->upload['resized']['filename'];
					}
				}
				else
				{
					$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
			{
				$this->set_error('upload_file_failed');
				return false;
			}

			@unlink($this->upload['location']);
			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	/**
	* Determine if the user is over his/her attachment quota. Disabled in the project tools.
	*/
	function check_attachment_overage()
	{
		return true;
	}

	/**
	* Saves the upload using the supplied datamanager
	*/
	function save_upload()
	{
		$this->data->set('dateline', TIMENOW);
		$this->data->set('thumbnail_dateline', TIMENOW);
		if ($this->data->fetch_field('state') === null)
		{
			$this->data->set('visible', 1);
		}
		$this->data->setr('userid', $this->userinfo['userid']);
		$this->data->setr('filename', $this->upload['filename']);
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

		unset($this->upload);

		return $result;
	}
}
?>