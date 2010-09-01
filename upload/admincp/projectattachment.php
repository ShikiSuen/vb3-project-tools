<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Revision$');
@ini_set('display_errors', 'On');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'attachment_image',
	'projecttools',
	'projecttoolsadmin'
);

$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	print_stop_message('product_not_installed_disabled');
}

require_once(DIR . '/includes/adminfunctions_attachment.php');
require_once(DIR . '/includes/functions_file.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canpt'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'attachpath'   => TYPE_STR,
	'dowhat'       => TYPE_STR,
));

log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_attachment_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'storage';
}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'storage')
{
	if ($vbulletin->options['pt_attachfile'])
	{
		$options = array(
			'FS_to_DB' => $vbphrase['move_items_from_filesystem_into_database'],
			'FS_to_FS' => $vbphrase['move_items_to_a_different_directory']
		);
	}
	else
	{
		$options = array(
			'DB_to_FS' => $vbphrase['move_items_from_database_into_filesystem']
		);
	}

	$i = 0;
	$dowhat = '';
	foreach($options AS $value => $text)
	{
		$dowhat .= "<label for=\"dw$value\"><input type=\"radio\" name=\"dowhat\" id=\"dw$value\" value=\"$value\"" . iif($i++ == 0, ' checked="checked"') . " />$text</label><br />";
	}

	print_form_header('projectattachment', 'switchtype');
	print_table_header("$vbphrase[storage_type]: <span class=\"normal\">" . $vbphrase['project_tools_attachments'] . "</span>");
	if ($vbulletin->options['pt_attachfile'])
	{
		print_description_row(construct_phrase($vbphrase['attachments_are_currently_being_stored_in_the_filesystem_at_x'], '<b>' . $vbulletin->options['pt_attachpath'] . '</b>'));
	}
	else
	{
		print_description_row($vbphrase['attachments_are_currently_being_stored_in_the_database']);
	}
	print_label_row($vbphrase['action'], $dowhat);
	print_submit_row($vbphrase['go'], 0);
}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'switchtype')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dowhat' 	=> TYPE_STR
	));

	if ($vbulletin->GPC['dowhat'] == 'FS_to_DB')
	{
		// redirect straight through to attachment mover
		$vbulletin->GPC['attachpath'] = $vbulletin->options['pt_attachpath'];
		$vbulletin->GPC['dowhat'] = 'FS_to_DB';
		$_POST['do'] = 'doswitchtype';
	}
	else
	{
		if ($vbulletin->GPC['dowhat'] == 'FS_to_FS')
		{
			// show a form to allow user to specify file path
			print_form_header('projectattachment', 'doswitchtype');
			construct_hidden_code('dowhat', $vbulletin->GPC['dowhat']);
			print_table_header($vbphrase['move_attachments_to_a_different_directory']);
			print_description_row(construct_phrase($vbphrase['attachments_are_currently_being_stored_in_the_filesystem_at_x'], '<b>' . $vbulletin->options['pt_attachpath'] . '</b>'));
		}
		else
		{
			if (SAFEMODE)
			{
				// Attachments as files is not compatible with safe_mode since it creates directories
				// Safe_mode does not allow you to write to directories created by PHP
				print_stop_message('your_server_has_safe_mode_enabled');
			}
			// show a form to allow user to specify file path
			print_form_header('projectattachment', 'doswitchtype');
			construct_hidden_code('dowhat', $vbulletin->GPC['dowhat']);
			print_table_header($vbphrase['move_items_from_database_into_filesystem']);
			print_description_row($vbphrase['attachments_are_currently_being_stored_in_the_database']);
		}

		print_input_row($vbphrase['attachment_file_path_dfn'], 'attachpath', $vbulletin->options['pt_attachpath']);
		if ($vbulletin->options['attachfile'])
		{
			print_description_row('<span class="smallfont">' . $vbphrase['note_pt_attachments_diff_dir_vb'] . '</span>');
		}
		print_submit_row($vbphrase['go']);
	}
}

// ############### Move files from database to file system and vice versa ###########
if ($_POST['do'] == 'doswitchtype')
{
	$vbulletin->GPC['attachpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['attachpath']);

	switch($vbulletin->GPC['dowhat'])
	{
		// #############################################################################
		// update attachment file path
		case 'FS_to_FS':

			if ($vbulletin->GPC['attachpath'] === $vbulletin->options['pt_attachpath'])
			{
				// new and old path are the same - show error
				print_stop_message('invalid_file_path_specified');
			}
			else
			{
				// new and old paths are different - check the directory is valid
				verify_upload_folder($vbulletin->GPC['attachpath']);
				$oldpath = $vbulletin->options['pt_attachpath'];

				if ($vbulletin->options['attachfile'] AND $vbulletin->GPC['attachpath'] == $vbulletin->options['attachpath'])
				{
					// can't be stored in same place as vB's attachment files
					print_stop_message('pt_attachment_dir_different_vb');
				}

				// update $vboptions
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "setting
					SET value = '" . $db->escape_string($vbulletin->GPC['attachpath']) . "'
					WHERE varname = 'pt_attachpath'
				");
				build_options();

				// show message
				print_stop_message('your_vb_settings_have_been_updated_to_store_attachments_in_x', $vbulletin->GPC['attachpath'], $oldpath);
			}

			break;

		// #############################################################################
		// move attachments from database to filesystem
		case 'DB_to_FS':

			// check path is valid
			verify_upload_folder($vbulletin->GPC['attachpath']);

			if ($vbulletin->options['attachfile'] AND $vbulletin->GPC['attachpath'] == $vbulletin->options['attachpath'])
			{
				// can't be stored in same place as vB's attachment files
				print_stop_message('pt_attachment_dir_different_vb');
			}

			// update $vboptions
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '" . $db->escape_string($vbulletin->GPC['attachpath']) . "'
				WHERE varname = 'pt_attachpath'
			");
			build_options();

			break;
	}

	// #############################################################################

	print_form_header('projectattachment', 'domoveattachment');
	print_table_header($vbphrase['edit_storage_type']);
	construct_hidden_code('dowhat', $vbulletin->GPC['dowhat']);

	if ($vbulletin->GPC['dowhat'] == 'DB_to_FS')
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_attachments_from_database_to_filesystem']);
	}
	else
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_attachments_from_filesystem_to_database']);
	}

	print_input_row($vbphrase['number_of_attachments_to_process_per_cycle'], 'perpage', 300, 1, 5);
	if ($vbulletin->debug)
	{
		print_input_row($vbphrase['attachmentid_start_at'], 'startat', 0, 1, 5);
	}
	print_submit_row($vbphrase['go']);
}

// ################### Move attachments ######################################
if ($_REQUEST['do'] == 'domoveattachment')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'          => TYPE_UINT,
		'startat'          => TYPE_UINT,
		'attacherrorcount' => TYPE_UINT,
		'count'            => TYPE_UINT
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 10;
	}

	if (empty($vbulletin->GPC['startat'])) // Grab the first attachmentid so that we don't process a bunch of nonexistent ids to begin with.
	{
		$start = $db->query_first("SELECT MIN(attachmentid) AS min FROM " . TABLE_PREFIX . "pt_issueattach");
		$vbulletin->GPC['startat'] = intval($start['min']);
	}
	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	// echo '<p>' . $vbphrase['attachments'] . '</p>';

	$attachments = $db->query_read("
		SELECT attachmentid, filename, filedata, filesize, userid, thumbnail
		FROM " . TABLE_PREFIX . "pt_issueattach
		WHERE attachmentid >= " . $vbulletin->GPC['startat'] . " AND attachmentid < $finishat
		ORDER BY attachmentid ASC
	");

	if ($vbulletin->debug)
	{
		echo '<table width="100%" border="1" cellspacing="0" cellpadding="1">
				<tr>
				<td><b>Attachment ID</b></td><td><b>Filename</b></td><td><b>Size in Database</b></td><td><b>Size in Filesystem</b></td>
				</tr>
			';
	}

	require_once(DIR . '/includes/class_dm.php');
	require_once(DIR . '/includes/class_dm_attachment_pt.php');

	while ($attachment = $db->fetch_array($attachments))
	{
		$vbulletin->GPC['count']++;
		$attacherror = false;
		if ($vbulletin->options['pt_attachfile'] == ATTACH_AS_DB)
		{ // Converting FROM mysql TO fs
			$vbulletin->options['pt_attachfile'] = ATTACH_AS_FILES_NEW;

			$attachdata =& vB_DataManager_Attachment_Pt::fetch_library($vbulletin, ERRTYPE_SILENT);
			$attachdata->set_existing($attachment);
			if (!($result = $attachdata->save()))
			{
				if (empty($attachdata->errors[0]))
				{
					$attacherror = fetch_error('upload_file_failed'); // change this error
				}
				else
				{
					$attacherror =& $attachdata->errors[0];
				}
			}
			unset($attachdata);
			$filepath = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], false, $vbulletin->options['pt_attachpath']);
			if (!is_readable($filepath) OR @filesize($filepath) == 0)
			{
				$vbulletin->GPC['attacherrorcount']++;
			}

			$vbulletin->options['pt_attachfile'] = ATTACH_AS_DB;
		}
		else
		{ // Converting FROM fs TO mysql
			$path = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], false, $vbulletin->options['pt_attachpath']);
			$thumbnail_path = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true, $vbulletin->options['pt_attachpath']);

			$temp = $vbulletin->options['pt_attachfile'];
			$vbulletin->options['pt_attachfile'] = ATTACH_AS_DB;

			if ($filedata = @file_get_contents($path))
			{
				$thumbnail_filedata = @file_get_contents($thumbnail_path);

				$attachdata =& vB_DataManager_Attachment_Pt::fetch_library($vbulletin, ERRTYPE_SILENT);
				$attachdata->set_existing($attachment);
				$attachdata->setr('filedata', $filedata);
				$attachdata->setr('thumbnail', $thumbnail_filedata);

				if (!($result = $attachdata->save()))
				{
					if (empty($attachdata->errors[0]))
					{
						$attacherror = fetch_error('upload_file_failed'); // change this error
					}
					else
					{
						$attacherror =& $attachdata->errors[0];
					}
				}
				unset($attachdata);
			}
			else
			{
				// Add error about file missing..
				$vbulletin->GPC['attacherrorcount']++;
			}

			$vbulletin->options['pt_attachfile'] = $temp;

		}
		if ($vbulletin->debug)
		{
			echo "	<tr>
					<td>$attachment[attachmentid]</td>
					<td>" . htmlspecialchars_uni($attachment['filename']) . iif($attacherror, "<br />$attacherror") . "</td>
					<td>$attachment[filesize]</td>
					<td>$filesize / $thumbnail_filesize</td>
					</tr>
					";
		}
		else
		{
			echo "$vbphrase[attachment] : <b>$attachment[attachmentid]</b> $vbphrase[filename] : <b>$attachment[filename]</b><br />";
			if ($attacherror)
			{
				echo "$vbphrase[attachment] : <b>$attachment[attachmentid] $vbphrase[error]</b> $attacherror<br />";
			}
			vbflush();
		}
	}

	if ($vbulletin->debug)
	{
		echo '</table>';
		vbflush();
	}
	if ($checkmore = $db->query_first("SELECT attachmentid FROM " . TABLE_PREFIX . "pt_issueattach WHERE attachmentid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=domoveattachment&startat=$finishat" .
												"&pp=" . $vbulletin->GPC['perpage'] .
												"&count=" . $vbulletin->GPC['count'] .
												"&attacherrorcount=" . $vbulletin->GPC['attacherrorcount']);

		echo "<p><a href=\"projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=domoveattachment&amp;startat=$finishat" .
												"&amp;pp=" . $vbulletin->GPC['perpage'] .
												"&amp;count=" . $vbulletin->GPC['count'] .
												"&amp;attacherrorcount=" . $vbulletin->GPC['attacherrorcount'] . "\">" .
												$vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		if ($db->num_rows($attachments) > 0)
		{
			// Bump this to a new page
			print_cp_redirect("projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=domoveattachment&startat=$finishat" .
													"&pp=" . $vbulletin->GPC['perpage'] .
													"&count=" . $vbulletin->GPC['count'] .
													"&attacherrorcount=" . $vbulletin->GPC['attacherrorcount']);
			echo "<p><a href=\"projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=domoveattachment&amp;startat=$finishat" .
													"&amp;pp=" . $vbulletin->GPC['perpage'] .
													"&amp;count=" . $vbulletin->GPC['count'] .
													"&amp;attacherrorcount=" . $vbulletin->GPC['attacherrorcount'] . "\">" .
													$vbphrase['click_here_to_continue_processing'] . "</a></p>";
		}

		$totalattach = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "pt_issueattach");
		if ($vbulletin->options['pt_attachfile'] == ATTACH_AS_DB)
		{
			// Here we get a form that the user must continue on to delete the filedata column so that they are really sure to complete this step!
			print_form_header('projectattachment', 'confirmattachmentremove');
			print_table_header($vbphrase['confirm_attachment_removal']);
			print_description_row(construct_phrase($vbphrase['attachment_removal'], $totalattach['count'], $vbulletin->GPC['count'], $vbulletin->GPC['attacherrorcount']));

			if ($totalattach['count'] != $vbulletin->GPC['count'] OR !$vbulletin->GPC['count'] OR ($vbulletin->GPC['attacherrorcount'] / $vbulletin->GPC['count']) * 10 > 1)
			{
				$finalizeoption = false;
			}
			else
			{
				$finalizeoption = true;
			}

			print_yes_no_row($vbphrase['finalize'], 'removeattachments', $finalizeoption);
			print_submit_row($vbphrase['go']);

		}

		else
		{
			$filetype = $vbulletin->options['Pt_attachfile'];
			// update $vboptions // attachments are now being read from and saved to the database
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . ATTACH_AS_DB . "' WHERE varname = 'pt_attachfile'");
			build_options();

			print_form_header('projectattachment', 'confirmfileremove');
			print_table_header($vbphrase['confirm_attachment_removal']);
			print_description_row(construct_phrase($vbphrase['file_removal'], $totalattach['count'], $vbulletin->GPC['count'], $vbulletin->GPC['attacherrorcount']));
			construct_hidden_code('attachtype', $filetype);
			print_submit_row($vbphrase['go']);

		}
	}
}

// ###################### Confirm emptying of filedata ##########
if ($_REQUEST['do'] == 'confirmfileremove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'startat'    => TYPE_UINT,
		'perpage'    => TYPE_UINT,
		'attachtype' => TYPE_UINT,
	));

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 200;
	}

	$attachments = $db->query_read("
		SELECT attachmentid, userid
		FROM " . TABLE_PREFIX . "pt_issueattach
		ORDER BY userid DESC, attachmentid ASC
		LIMIT " . $vbulletin->GPC['startat'] . ", " . $vbulletin->GPC['perpage'] . "
	");
	if ($records = $db->num_rows($attachments))
	{
		echo '<p>' . construct_phrase($vbphrase['removing_x_attachments'], $records) . '</p>';
		vbflush();

		while ($attachment = $db->fetch_array($attachments))
		{
			if ($userid === null)
			{
				$userid = $attachment['userid'];
			}
			if ($vbulletin->GPC['attachtype'] == ATTACH_AS_FILES_NEW)
			{
				$path = $vbulletin->options['pt_attachpath'] . '/' . implode('/', preg_split('//', $attachment['userid'],  -1, PREG_SPLIT_NO_EMPTY));
			}
			else
			{
				$path = $vbulletin->options['pt_attachpath'] . '/' . $attachment['userid'];
			}

			@unlink($path . '/' . $attachment['attachmentid'] . '.attach');
			@unlink($path . '/' . $attachment['attachmentid'] . '.thumb');

			if ($userid != $attachment['userid'])
			{
				// Try to remove directory of previous userid
				if ($vbulletin->GPC['attachtype'] == ATTACH_AS_FILES_NEW)
				{
					$path = $vbulletin->options['pt_attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY));
					$result = @rmdir($path);
					$temp = $userid;
					while ($result AND $temp > 1)
					{
						$temp = floor($temp / 10);
						$path = $vbulletin->options['pt_attachpath'] . '/' . implode('/', preg_split('//', $temp,  -1, PREG_SPLIT_NO_EMPTY));
						$result = @rmdir($path);
					}
				}
				else
				{
					$path = $vbulletin->options['pt_attachpath'] . '/' . $userid;
					@rmdir($path);
				}

				$userid = $attachment['userid'];
			}
		}

		// Try to remove directory
		if ($vbulletin->GPC['attachtype'] == ATTACH_AS_FILES_NEW)
		{
			$path = $vbulletin->options['pt_attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY));
			$result = @rmdir($path);
			while ($result AND $temp > 1)
			{
				$userid = floor($userid / 10);
				$path = $vbulletin->options['pt_attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY));
				$result = @rmdir($path);
			}
		}
		else
		{
			$path = $vbulletin->options['pt_attachpath'] . '/' . $userid;
			@rmdir($path);
		}

		$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];
		print_cp_redirect("projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfileremove&startat=$finishat&attachtype=" . $vbulletin->GPC['attachtype'] .
											"&pp=" . $vbulletin->GPC['perpage']);

		echo "<p><a href=\"projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfileremove&amp;startat=$finishat&amp;attachtype=" . $vbulletin->GPC['attachtype'] .
											"&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" .
											$vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_CONTINUE', 'projectattachment.php');
		print_stop_message('attachments_moved_to_the_database');
	}
}

// ###################### Confirm emptying of filedata ##########
if ($_REQUEST['do'] == 'confirmattachmentremove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'removeattachments' => TYPE_BOOL,
		'startat'           => TYPE_UINT,
		'perpage'           => TYPE_UINT,
	));

	if ($vbulletin->GPC['removeattachments'])
	{
		if (empty($vbulletin->GPC['perpage']))
		{
			$vbulletin->GPC['perpage'] = 500;
		}

		if ($vbulletin->GPC['startat'] == 0)
		{
			// update $vboptions to attachments as files...
			// attachfile is only set to 1 to indicate the PRE 3.0.0 RC1 attachment FS behaviour
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . ATTACH_AS_FILES_NEW . "' WHERE varname = 'pt_attachfile'");
			build_options();
		}

		$attachments = $db->query_read("
			SELECT attachmentid
			FROM " . TABLE_PREFIX . "pt_issueattach
			ORDER BY attachmentid
			LIMIT " . $vbulletin->GPC['startat'] . ", " . $vbulletin->GPC['perpage'] . "
		");
		if ($records = $db->num_rows($attachments))
		{
			echo '<p>' . construct_phrase($vbphrase['removing_x_attachments'], $records) . '</p>';
			vbflush();

			$attachmentids = '';
			while ($attachment = $db->fetch_array($attachments))
			{
				$attachmentids .= ",$attachment[attachmentid]";
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issueattach SET
					filedata = '',
					thumbnail = ''
				WHERE attachmentid IN (0$attachmentids)
			");

			$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];
			print_cp_redirect("projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmattachmentremove&startat=$finishat&removeattachments=1" .
												"&pp=" . $vbulletin->GPC['perpage']);

			echo "<p><a href=\"projectattachment.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmattachmentremove&amp;startat=$finishat&amp;removeattachments=1" .
												"&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" .
												$vbphrase['click_here_to_continue_processing'] . "</a></p>";

		}
		else
		{
			// Again, make sure we are on attachments as files setting.
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . ATTACH_AS_FILES_NEW . "' WHERE varname = 'pt_attachfile'");
			build_options();

			define('CP_CONTINUE', 'projectattachment.php');
			print_stop_message('attachments_moved_to_the_filesystem', $vbulletin->session->vars['sessionurl']);
		}
	}
	else
	{
		define('CP_CONTINUE', 'projectattachment.php');
		print_stop_message('attachments_not_moved_to_the_filesystem');
	}
}

print_cp_footer();

?>