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

/*
delete -
$vbphrase[active]
$vbphrase[magicselect_html_code]
$vbphrase[magicselect_html_code_desc]
$vbphrase[magicselect_fetch_code]
$vbphrase[magicselect_fetch_code_desc]
$vbphrase[magicselect_save_code]
$vbphrase[magicselect_save_code_desc]
*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Rev$');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'projecttools',
	'projecttoolsadmin'
);

$specialtemplates = array(
	'pt_projects'
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
	'projectmagicselectid' => TYPE_UINT,
	'projectmagicselectgroupid' => TYPE_UINT,
	'projectid' => TYPE_UINT,
));

log_admin_action((!empty($vbulletin->GPC['magicselectid']) ? ' magic select id = ' . $vbulletin->GPC['magicselectid'] : '') . (!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

if ($vbulletin->GPC['projectid'])
{
	$project = fetch_project_info($vbulletin->GPC['projectid']);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}
}

// #############################################################################
if ($_POST['do'] == 'kill')
{
	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		WHERE projectmagicselectid = " . $vbulletin->GPC['projectmagicselectid']
	);

	if (!$magicselect)
	{
		print_stop_message('invalid_action_specified');
	}

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set_existing($magicselect);
	$dataman->delete();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $magicselect['projectid']);
	print_stop_message('project_magic_select_deleted');
}

// #############################################################################
if ($_REQUEST['do'] == 'delete')
{
	$projectmagicselect = $db->query_first("
		SELECT projectmagicselectid, projectid
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		WHERE projectmagicselectid = " . $vbulletin->GPC['projectmagicselectid']
	);

	$project = fetch_project_info($projectmagicselect['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_delete_confirmation(
		'pt_projectmagicselect',
		$projectmagicselect['projectmagicselectid'],
		'projectmagicselect',
		'kill'
	);
}

// ########################################################################
if ($_POST['do'] == 'insert')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'text'			=> TYPE_NOHTML,
		'displayorder'	=> TYPE_UINT,
		'value'			=> TYPE_UINT
	));

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set_info('text', $vbulletin->GPC['text']);
	$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
	$dataman->set('projectid', $project['projectid']);
	$dataman->set('value', $vbulletin->GPC['value']);
	$dataman->set('projectmagicselectgroupid', $vbulletin->GPC['projectmagicselectgroupid']);

	$dataman->save();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_magic_select_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'add')
{
	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . intval($vbulletin->GPC['projectmagicselectgroupid']) . "
	");

	$project = fetch_project_info($magicselect['projectid']);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectmagicselect', 'insert');
	print_table_header($vbphrase['add_magic_select']);

	print_input_row($vbphrase['text'], 'text');
	print_input_row($vbphrase['display_order'], 'displayorder');
	print_input_row($vbphrase['value'], 'value');

	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectmagicselectgroupid', $magicselect['projectmagicselectgroupid']);

	print_submit_row();
}

// #############################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'text'			=> TYPE_STR,
		'displayorder'	=> TYPE_UINT,
		'value'			=> TYPE_UINT
	));

	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		WHERE projectmagicselectid = " . intval($vbulletin->GPC['projectmagicselectid']) . "
	");

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set_existing($magicselect);
	$dataman->set_info('text', $vbulletin->GPC['text']);
	$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
	$dataman->set('value', $vbulletin->GPC['value']);
	$dataman->set('projectmagicselectgroupid', $vbulletin->GPC['projectmagicselectgroupid']);

	$dataman->save();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_magic_select_updated');
}

// #############################################################################
if ($_REQUEST['do'] == 'edit')
{
	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		WHERE projectmagicselectid = " . intval($vbulletin->GPC['projectmagicselectid']) . "
	");

	$project = fetch_project_info($magicselect['projectid']);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($magicselect['projectmagicselectid']))
	{
		print_stop_message('no_magic_select_matched_your_query');
	}

	print_form_header('projectmagicselect', 'update');
	print_table_header(construct_phrase($vbphrase['edit_project_magic_select'], $vbphrase['magicselect' . $magicselect['projectmagicselectid'] . '']));

	print_input_row($vbphrase['text'], 'text', $vbphrase['magicselect' . $magicselect['projectmagicselectid'] . '']);
	print_input_row($vbphrase['display_order'], 'displayorder', $magicselect['displayorder']);
	print_input_row($vbphrase['value'], 'value', $magicselect['value']);

	construct_hidden_code('projectmagicselectid', $magicselect['projectmagicselectid']);
	construct_hidden_code('projectmagicselectgroupid', $magicselect['projectmagicselectgroupid']);
	construct_hidden_code('projectid', $magicselect['projectid']);

	print_submit_row($vbphrase['save']);
}

// ########################################################################
if ($_POST['do'] == 'groupkill')
{
	$projectmagicselectgroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . $vbulletin->GPC['projectmagicselectgroupid']
	);

	$project = fetch_project_info($projectmagicselectgroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . intval($projectmagicselectgroup['projectmagicselectgroupid']) . "
	");

	// Delete the field in the pt_issue table
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "pt_issuemagicselect
		DROP magicselect" . intval($projectmagicselectgroup['projectmagicselectgroupid']) . "
	");

	// Remove the existing phrase
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = 'magicselectgroup" . intval($projectmagicselectgroup['projectmagicselectgroupid']) . "'
			AND fieldname = 'projecttools'
			AND languageid = 0
	");

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_magicselect_cache();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_magic_select_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'groupdelete')
{
	$projectmagicselectgroup = $db->query_first("
		SELECT projectmagicselectgroupid, projectid
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . $vbulletin->GPC['projectmagicselectgroupid']
	);

	$project = fetch_project_info($projectmagicselectgroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_delete_confirmation(
		'pt_projectmagicselectgroup',
		$projectmagicselectgroup['projectmagicselectgroupid'],
		'projectmagicselect',
		'groupkill'
	);
}

// ########################################################################
if ($_POST['do'] == 'groupinsert')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'text' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT
	));

	$projectmagicselectgroup = array();

	if (empty($vbulletin->GPC['text']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "pt_projectmagicselectgroup
			(projectid, displayorder)
		VALUES
			($project[projectid],
			" . $vbulletin->GPC['displayorder'] . ")
	");

	$projectmagicselectgroupid = $db->insert_id();

	// Add a field in the pt_issue table
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "pt_issuemagicselect
		ADD magicselect" . $projectmagicselectgroupid . " INT(10) UNSIGNED NOT NULL DEFAULT '0'
	");

	// Add the text in a phrase
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, fieldname, varname, text, product, username, dateline, version)
		VALUES
			(0,
			'projecttools',
			'magicselectgroup" . intval($projectmagicselectgroupid) . "',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['text']) . "',
			'vbprojecttools',
			'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
			" . TIMENOW . ",
			'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
			)
	");

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_magicselect_cache();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_magic_select_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'groupadd')
{
	$maxorder = $db->query_first("
		SELECT MAX(displayorder) AS maxorder
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectid = " . $project['projectid']
	);

	$projectmagicselectgroup = array(
		'projectmagicselectgroupid' => 0,
		'displayorder' => $maxorder['maxorder'] + 10
	);

	print_form_header('projectmagicselect', 'groupinsert');

	print_table_header($vbphrase['add_project_magic_select_group']);

	print_input_row($vbphrase['title'], 'text');
	print_input_row($vbphrase['display_order'], 'displayorder', '', true, 5);
	construct_hidden_code('projectid', $project['projectid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'groupupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'text' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT
	));

	$projectmagicselectgroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . $vbulletin->GPC['projectmagicselectgroupid']
	);

	$vbulletin->GPC['projectid'] = $projectmagicselectgroup['projectid'];

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['text']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_projectmagicselectgroup SET
			displayorder = " . $vbulletin->GPC['displayorder'] . "
		WHERE projectmagicselectgroupid = $projectmagicselectgroup[projectmagicselectgroupid]
	");

	// Add the text in a phrase
	$db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "phrase
			(languageid, fieldname, varname, text, product, username, dateline, version)
		VALUES
			(0,
			'projecttools',
			'magicselectgroup" . intval($projectmagicselectgroup['projectmagicselectgroupid']) . "',
			'" . $vbulletin->db->escape_string($vbulletin->GPC['text']) . "',
			'vbprojecttools',
			'" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',
			" . TIMENOW . ",
			'" . $vbulletin->db->escape_string($full_product_info['vbprojecttools']['version']) . "'
			)
	");

	// Rebuild language
	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_magicselect_cache();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_magic_select_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'groupedit')
{
	$projectmagicselectgroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectmagicselectgroupid = " . $vbulletin->GPC['projectmagicselectgroupid']
	);

	$vbulletin->GPC['projectid'] = $projectmagicselectgroup['projectid'];

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectmagicselect', 'groupupdate');
	print_table_header(construct_phrase($vbphrase['edit_project_magic_select'], $vbphrase['magicselectgroup' . $projectmagicselectgroup['projectmagicselectgroupid'] . '']));
	print_input_row($vbphrase['text'], 'text', $vbphrase['magicselectgroup' . $projectmagicselectgroup['projectmagicselectgroupid'] . ''], false);
	print_input_row($vbphrase['display_order'], 'displayorder', $projectmagicselectgroup['displayorder'], true, 5);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectmagicselectgroupid', $projectmagicselectgroup['projectmagicselectgroupid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'order')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'magicselectorder' => TYPE_ARRAY_UINT,
		'grouporder' => TYPE_ARRAY_UINT,
	));

	$groupcase = '';
	$grouporder = array();

	foreach ($vbulletin->GPC['grouporder'] AS $id => $displayorder)
	{
		$grouporder[intval($id)] = $displayorder;
		$groupcase .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($groupcase)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectmagicselectgroup SET
				displayorder = CASE projectmagicselectgroupid $groupcase ELSE displayorder END
		");
	}

	$magicselectcase_display = '';

	foreach ($vbulletin->GPC['magicselectorder'] AS $id => $displayorder)
	{
		$magicselectcase_display .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($magicselectcase_display)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectmagicselect AS projectmagicselect
			INNER JOIN " . TABLE_PREFIX . "pt_projectmagicselectgroup AS projetmagicselectgroup ON
				(projectmagicselect.projectmagicselectgroupid = projectmagicselectgroup.projectmagicselectgroupid)
			SET
				projectmagicselect.displayorder = CASE projectmagicselect.projectmagicselectid $magicselectcase_display ELSE projectmagicselect.displayorder END
		");
	}

	define('CP_REDIRECT', 'projectmagicselect.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$groups_data = array();

	$group_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselectgroup
		WHERE projectid = " . $project['projectid'] . "
		ORDER BY displayorder ASC
	");

	while ($group = $db->fetch_array($group_data))
	{
		$groups["$group[projectmagicselectgroupid]"] = $group;
	}

	$magicselects = array();

	$magicselect_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectmagicselect
		WHERE projectid = " . $project['projectid'] . "
		ORDER BY displayorder ASC
	");

	while ($magicselect = $db->fetch_array($magicselect_data))
	{
		$magicselects["$magicselect[projectmagicselectgroupid]"][] = $magicselect;
	}

	if ($groups)
	{
		print_form_header('projectmagicselect', 'order');
		print_table_header(construct_phrase($vbphrase['project_magic_select_list'], $project['title']), 3);

		foreach ($groups AS $group)
		{
			print_cells_row(array(
				$vbphrase['magicselectgroup' . $group['projectmagicselectgroupid']],
				"<input type=\"text\" class=\"bginput\" name=\"grouporder[$group[projectmagicselectgroupid]]\" value=\"$group[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="normal smallfont">' .
					construct_link_code($vbphrase['edit'], 'projectmagicselect.php?do=groupedit&amp;projectmagicselectgroupid=' . $group['projectmagicselectgroupid']) .
					construct_link_code($vbphrase['delete'], 'projectmagicselect.php?do=groupdelete&amp;projectmagicselectgroupid=' . $group['projectmagicselectgroupid']) .
					construct_link_code($vbphrase['add_magic_select'], 'projectmagicselect.php?do=add&amp;projectmagicselectgroupid=' . $group['projectmagicselectgroupid']) .
					'</div>',
			), 'thead');

			if (is_array($magicselects["$group[projectmagicselectgroupid]"]))
			{
				foreach ($magicselects["$group[projectmagicselectgroupid]"] AS $magicselect)
				{
					print_cells_row(array(
						$vbphrase['magicselect' . $magicselect['projectmagicselectid']],
						"<input type=\"text\" class=\"bginput\" name=\"magicselectorder[$magicselect[projectmagicselectid]]\" value=\"$magicselect[displayorder]\" tabindex=\"1\" size=\"3\" />",
						'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
							construct_link_code($vbphrase['edit'], 'projectmagicselect.php?do=edit&amp;projectmagicselectid=' . $magicselect['projectmagicselectid']) .
							construct_link_code($vbphrase['delete'], 'projectmagicselect.php?do=delete&amp;projectmagicselectid=' . $magicselect['projectmagicselectid']) .
						'</div>'
					));
				}
			}
			else
			{
				print_description_row($vbphrase['no_magic_selects_defined_in_this_group'], false, 3, '', 'center');
			}
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_form_header('', '');
		print_table_header(construct_phrase($vbphrase['project_magic_select_list'], $project['title']), 3);
		print_description_row(construct_phrase($vbphrase['no_project_magic_select_defined_click_here_to_add_one'], $project['projectid']), false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_magic_select_group'], 'projectmagicselect.php?do=groupadd&amp;projectid=' . $project['projectid']) . '</p>';
}

print_cp_footer();

?>