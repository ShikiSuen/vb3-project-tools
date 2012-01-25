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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Rev$');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'projecttools',
	'projecttoolsadmin'
);

$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
);

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

if (empty($vbulletin->products['vbprojecttools']))
{
	print_stop_message('product_not_installed_disabled');
}

require_once(DIR . '/includes/adminfunctions_projecttools.php');
require_once(DIR . '/includes/functions_projecttools.php');

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

$full_product_info = fetch_product_list(true);

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canpt'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'projectpriorityid' => TYPE_UINT
));

log_admin_action((!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') . (!empty($vbulletin->GPC['projectpriorityid']) ? ' priority id = ' . $vbulletin->GPC['projectpriorityid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools'], iif(in_array($_REQUEST['do'], array('edit', 'add')) , 'init_color_preview()'));

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// ########################################################################
// ################### PROJECT PRIORITY MANAGEMENT ########################
// ########################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT,
		'default' => TYPE_BOOL,
		'statuscolor' => TYPE_STR,
		'statuscolor2' => TYPE_STR,
	));

	if ($vbulletin->GPC['projectpriorityid'])
	{
		$projectpriority = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
		);
		$vbulletin->GPC['projectid'] = $projectpriority['projectid'];
	}
	else
	{
		$projectpriority = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['title']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($projectpriority['projectpriorityid'])
	{
		// Check first if the default value is already defined for this project
		// If yes, remove it and save the actual form
		$defaultvalue = $db->query_first("
			SELECT projectpriorityid AS priority
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['priority'] != $projectpriority['projectpriorityid'])
		{
			// Default value already defined for an other category
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
					defaultvalue = 0
				WHERE projectpriorityid = " . intval($defaultvalue['priority']) . "
			");
		}

		// Perform the save
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
				displayorder = " . $vbulletin->GPC['displayorder'] . ",
				defaultvalue = " . ($vbulletin->GPC['default'] ? 1 : 0) . "
				" . ($vbulletin->GPC['statuscolor'] ? ", statuscolor = '" . $db->escape_string($vbulletin->GPC['statuscolor']) . "'" : '') . "
				" . ($vbulletin->GPC['statuscolor2'] ? ", statuscolor2 = '" . $db->escape_string($vbulletin->GPC['statuscolor2']) . "'" : '') . "
			WHERE projectpriorityid = " . $projectpriority['projectpriorityid'] . "
		");

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'priority" . intval($vbulletin->GPC['projectpriorityid']) . "',
					'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
					'vbprojecttools',
					'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
				)
		");
	}
	else
	{
		// Check first if the default value is already defined for this project
		// If yes, remove it and save the actual form
		$defaultvalue = $db->query_first("
			SELECT projectpriorityid AS priority
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['priority'])
		{
			// Default value already defined for an other category
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
					defaultvalue = 0
				WHERE projectpriorityid = " . intval($defaultvalue['priority']) . "
			");
		}

		// Perform the save
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectpriority
				(projectid, displayorder, defaultvalue)
			VALUES
				(" . $project['projectid'] . ",
				" . $vbulletin->GPC['displayorder'] . ",
				" . ($vbulletin->GPC['default'] ? 1 : 0) . ")
		");

		$priorityid = $db->insert_id();

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'priority" . intval($priorityid) . "',
					'" . $vbulletin->db->escape_string($vbulletin->GPC['title']) . "',
					'vbprojecttools',
					'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
				)
		");
	}

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_project_priority_cache();

	define('CP_REDIRECT', 'projectpriority.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_priority_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	if ($vbulletin->GPC['projectpriorityid'])
	{
		$projectpriority = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
		);
		$vbulletin->GPC['projectid'] = $projectpriority['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectpriority
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectpriority = array(
			'projectpriorityid' => 0,
			'title' => '',
			'displayorder' => $maxorder['maxorder'] + 10,
			'statuscolor' => ''
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	echo '<script type="text/javascript" src="../clientscript/vbulletin_cpcolorpicker.js"></script>';

	print_form_header('projectpriority', 'update');

	if ($projectpriority['projectpriorityid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_project_priority'], $vbphrase['priority' . $projectpriority['projectpriorityid'] . '']));
		print_input_row($vbphrase['title'] . '<dfn>' . construct_link_code($vbphrase['translations'], 'phrase.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&amp;fieldname=projecttools&amp;t=1&amp;varname=priority' . $projectpriority['projectpriorityid'], true) . '</dfn>', 'title', $vbphrase['priority' . $projectpriority['projectpriorityid'] . ''], false);
	}
	else
	{
		print_table_header($vbphrase['add_project_priority']);
		print_input_row($vbphrase['title'], 'title', '', false);
	}


	print_input_row($vbphrase['display_order'], 'displayorder', $projectpriority['displayorder'], true, 5);
	print_yes_no_row($vbphrase['default_value'], 'default', $projectpriority['default']);

	require_once(DIR . '/includes/adminfunctions_template.php');
	$colorPicker = construct_color_picker(11);

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt2\">" . $vbphrase['severitycolor_darkstyles'] . "</td>
		<td class=\"alt2\" align=\"left\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td align=\"left\"><input type=\"text\" class=\"bginput\" name=\"statuscolor\" id=\"color_0\" value=\"{$projectpriority['statuscolor']}\" title=\"statuscolor\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(0)\" dir=\"ltr\" />&nbsp;</td>
				<td align=\"left\"><div id=\"preview_0\" class=\"colorpreview\" onclick=\"open_color_picker(0, event)\"></div></td>
			</tr>
			</table>
			<table align=\"right\" width=\"10%\" style=\"margin-top: -28px; margin-right: -3px\"><tr><td align=\"right\" width=\"10%\">" . ($projectpriority['projectpriorityid'] ? construct_help_button('statuscolor', 'edit', 'projectpriority') : construct_help_button('statuscolor', 'add', 'projectpriority')) . "</td></tr></table>
		</td>
	</tr>\n";

	// Construct_color_row reworked just for here
	echo "<tr>
		<td class=\"alt1\">" . $vbphrase['severitycolor_lightstyles'] . "</td>
		<td class=\"alt1\">
			<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
			<tr>
				<td align=\"left\"><input type=\"text\" class=\"bginput\" name=\"statuscolor2\" id=\"color_1\" value=\"{$projectpriority['statuscolor2']}\" title=\"statuscolor2\" tabindex=\"1\" size=\"22\" onchange=\"preview_color(1)\" dir=\"ltr\" />&nbsp;</td>
				<td align=\"left\"><div id=\"preview_1\" class=\"colorpreview\" onclick=\"open_color_picker(1, event)\"></div></td>
			</tr>
			</table>
			<table align=\"right\" width=\"10%\" style=\"margin-top: -28px; margin-right: -3px\"><tr><td align=\"right\" width=\"10%\">" . ($projectpriority['projectpriorityid'] ? construct_help_button('statuscolor2', 'edit', 'projectpriority') : construct_help_button('statuscolor2', 'add', 'projectpriority')) . "</td></tr></table>
		</td>
	</tr>\n";

	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectpriorityid', $projectpriority['projectpriorityid']);
	print_submit_row();

	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = 2;
	var colorPickerWidth = 253;
	var colorPickerType = 0;

	//-->
	</script>
	<?php
}

// ########################################################################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_gpc('p', 'destpriorityid', TYPE_UINT);

	$projectpriority = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
	);

	$project = fetch_project_info($projectpriority['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = $projectpriority[projectpriorityid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = 'priority" . $projectpriority['projectpriorityid'] . "'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			projectpriorityid = " . $vbulletin->GPC['destpriorityid'] . "
		WHERE projectpriorityid = $projectpriority[projectpriorityid]
	");

	build_project_priority_cache();

	define('CP_REDIRECT', 'projectpriority.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_priority_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'delete')
{
	$projectpriority = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectpriorityid = " . $vbulletin->GPC['projectpriorityid']
	);

	$project = fetch_project_info($projectpriority['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$priorities = array();

	$priority_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = $project[projectid]
			AND projectpriorityid <> $projectpriority[projectpriorityid]
		ORDER BY displayorder
	");

	while ($priority = $db->fetch_array($priority_data))
	{
		$priorities["$priority[projectpriorityid]"] = $vbphrase['priority' . $priority['projectpriorityid'] . ''];
	}

	$priorities = array(0 => $vbphrase['unknown']) + $priorities;

	print_delete_confirmation(
		'pt_projectpriority',
		$projectpriority['projectpriorityid'],
		'projectpriority',
		'kill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_priority'] . '&nbsp;<select name="destpriorityid">' . construct_select_options($priorities) . '</select>',
		'title'
	);
}

// ########################################################################
if ($_POST['do'] == 'order')
{
	$vbulletin->input->clean_gpc('p', 'order', TYPE_ARRAY_UINT);

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $id => $displayorder)
	{
		$case .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectpriority SET
				displayorder = CASE projectpriorityid $case ELSE displayorder END
		");
	}

	build_project_priority_cache();

	define('CP_REDIRECT', 'projectpriority.php?do=list&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$project = fetch_project_info($vbulletin->GPC['projectid'], true);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$priorities = array();

	$priority_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectpriority
		WHERE projectid = $project[projectid]
		ORDER BY displayorder
	");

	while ($priority = $db->fetch_array($priority_data))
	{
		$priorities["$priority[projectpriorityid]"] = $priority;
	}

	print_form_header('projectpriority', 'order');
	print_table_header(construct_phrase($vbphrase['priorities_for_x'], $project['title_clean']), 3);

	if ($priorities)
	{
		print_cells_row(array(
			$vbphrase['priority'],
			$vbphrase['display_order'],
			'&nbsp;'
		), true);

		foreach ($priorities AS $priority)
		{
			print_cells_row(array(
				$vbphrase['priority' . $priority['projectpriorityid'] . ''],
				"<input type=\"text\" class=\"bginput\" name=\"order[$priority[projectpriorityid]]\" value=\"$priority[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'projectpriority.php?do=edit&amp;projectpriorityid=' . $priority['projectpriorityid']) .
					construct_link_code($vbphrase['delete'], 'projectpriority.php?do=delete&amp;projectpriorityid=' . $priority['projectpriorityid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row(construct_phrase($vbphrase['no_priorities_defined_project'], $project['projectid']), false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_priority'], 'projectpriority.php?do=add&amp;projectid=' . $project['projectid']) . '</p>';
}

print_cp_footer();

?>