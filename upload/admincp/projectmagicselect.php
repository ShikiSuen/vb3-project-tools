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
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// #############################################################################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_gpc('p', 'magicselectid', TYPE_UINT);

	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_magicselect
		WHERE magicselectid = " . $vbulletin->GPC['magicselectid']
	);

	if (!$magicselect)
	{
		print_stop_message('invalid_action_specified');
	}

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set_existing($magicselect);
	$dataman->delete();

	define('CP_REDIRECT', 'projectmagicselect.php?do=list');
	print_stop_message('project_magic_select_deleted');
}

// #############################################################################
if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_gpc('r', 'magicselectid', TYPE_UINT);

	print_delete_confirmation(
		'pt_magicselect',
		$vbulletin->GPC['magicselectid'],
		'projectmagicselect',
		'kill',
		'',
		array('magicselectid' => intval($vbulletin->GPC['magicselectid']))
	);
}

// ########################################################################
if ($_POST['do'] == 'insert')
{
	// 'insert'
	$vbulletin->input->clean_array_gpc('p', array(
		'varname'			=> TYPE_STR,
		'text'				=> TYPE_STR,
		'displayorder'		=> TYPE_UINT,
		'active'			=> TYPE_BOOL,
		'projects'			=> TYPE_ARRAY_UINT,
		'htmlcode'			=> TYPE_STR,
		'fetchcode'			=> TYPE_STR,
		'savecode'			=> TYPE_STR,
		'return'			=> TYPE_STR
	));

	// Serialize the array
	$vbulletin->GPC['projects'] = implode(',', $vbulletin->GPC['projects']);

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set('varname', $vbulletin->GPC['varname']);
	$dataman->set_info('text', $vbulletin->GPC['text']);
	$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
	$dataman->set('active', $vbulletin->GPC['active']);
	$dataman->set('projects', $vbulletin->GPC['projects']);
	$dataman->set('htmlcode', $vbulletin->GPC['htmlcode']);
	$dataman->set('fetchcode', $vbulletin->GPC['fetchcode']);
	$dataman->set('savecode', $vbulletin->GPC['savecode']);

	$dataman->save();

	// stuff to handle the redirect
	if ($vbulletin->GPC['return'])
	{
		define('CP_REDIRECT', "projectmagicselect.php?do=edit&amp;magicselectid=" . $vbulletin->GPC['magicselectid']);
	}
	else
	{
		define('CP_REDIRECT', 'projectmagicselect.php?do=list');
	}
	print_stop_message('project_magic_select_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'add')
{
	print_form_header('projectmagicselect', 'insert');
	print_table_header($vbphrase['add_project_magic_select']);

	print_input_row($vbphrase['varname'], 'varname');
	print_input_row($vbphrase['text'], 'text');
	print_input_row($vbphrase['display_order'], 'displayorder');
	print_yes_no_row($vbphrase['active'], 'active');

	// Project list
	$endtable = 0;

	foreach ($vbulletin->pt_projects AS $projectlist)
	{
		$projecttext .= iif(!$endtable, "<tr>\n");
		$projecttext .= "<td><label><input type=\"checkbox\" name=\"projects[$projectlist[projectid]]\" value=\"1\" />$projectlist[title]</label></td>\n";
		$projecttext .= iif($endtable, "</tr>\n");
		$endtable = iif($endtable, 0, 1);
	}
	print_label_row($vbphrase['projects'], '<table cellspacing="2" cellpadding="0" border="0">' . $projecttext . '</tr></table>', '', 'top', 'projects');

	print_textarea_row(
		"$vbphrase[magicselect_html_code] <dfn>$vbphrase[magicselect_html_code_desc]</dfn>",
		'htmlcode',
		htmlspecialchars($magicselect['htmlcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_textarea_row(
		"$vbphrase[magicselect_fetch_code] <dfn>$vbphrase[magicselect_fetch_code_desc]</dfn>",
		'fetchcode',
		htmlspecialchars($magicselect['fetchcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_textarea_row(
		"$vbphrase[magicselect_save_code] <dfn>$vbphrase[magicselect_save_code_desc]</dfn>",
		'savecode',
		htmlspecialchars($magicselect['savecode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_submit_row();
}

// #############################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'magicselectid'		=> TYPE_UINT,
		'varname'			=> TYPE_STR,
		'text'				=> TYPE_STR,
		'displayorder'		=> TYPE_UINT,
		'active'			=> TYPE_BOOL,
		'projects'			=> TYPE_ARRAY_UINT,
		'htmlcode'			=> TYPE_STR,
		'fetchcode'			=> TYPE_STR,
		'savecode'			=> TYPE_STR,
		'return'			=> TYPE_STR
	));

	// Serialize the array
	$vbulletin->GPC['projects'] = implode(',', $vbulletin->GPC['projects']);

	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_magicselect
		WHERE magicselectid = " . intval($vbulletin->GPC['magicselectid']) . "
	");

	$dataman =& datamanager_init('Pt_MagicSelect', $vbulletin, ERRTYPE_CP);
	$dataman->set_existing($magicselect);
	$dataman->set_info('text', $vbulletin->GPC['text']);
	$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
	$dataman->set('active', $vbulletin->GPC['active']);
	$dataman->set('projects', $vbulletin->GPC['projects']);
	$dataman->set('htmlcode', $vbulletin->GPC['htmlcode']);
	$dataman->set('fetchcode', $vbulletin->GPC['fetchcode']);
	$dataman->set('savecode', $vbulletin->GPC['savecode']);

	$dataman->save();

	// stuff to handle the redirect
	if ($vbulletin->GPC['return'])
	{
		define('CP_REDIRECT', "projectmagicselect.php?do=edit&amp;magicselectid=" . $vbulletin->GPC['magicselectid']);
	}
	else
	{
		define('CP_REDIRECT', 'projectmagicselect.php?do=list');
	}

	print_stop_message('project_magic_select_updated');
}

// #############################################################################
if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_gpc('r', 'magicselectid', TYPE_UINT);

	$magicselect = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_magicselect
		WHERE magicselectid = " . intval($vbulletin->GPC['magicselectid']) . "
	");

	if (empty($magicselect['magicselectid']))
	{
		print_stop_message('no_magic_select_matched_your_query');
	}

	print_form_header('projectmagicselect', 'update');
	print_table_header(construct_phrase($vbphrase['edit_project_magic_select'], $vbphrase['magicselect' . $magicselect['magicselectid'] . '']));

	print_label_row($vbphrase['varname'], $magicselect['varname']);
	print_input_row($vbphrase['text'], 'text', $vbphrase['magicselect' . $magicselect['magicselectid'] . '']);
	print_input_row($vbphrase['display_order'], 'displayorder', $magicselect['displayorder']);
	print_yes_no_row($vbphrase['active'], 'active', $magicselect['active']);

	// Project list
	$selected = array();
	$projectselected = explode(',', $magicselect['projects']);

	foreach ($projectselected AS $key => $value)
	{
		$selected[] = $value;
	}

	$endtable = 0;

	foreach ($vbulletin->pt_projects AS $projectlist)
	{
		$projecttext .= iif(!$endtable, "<tr>\n");
		$checked = iif(in_array($projectlist['projectid'], $selected), 'checked="checked"');
		$projecttext .= "<td><label><input type=\"checkbox\" name=\"projects[$projectlist[projectid]]\" value=\"1\" $checked />$projectlist[title]</label></td>\n";
		$projecttext .= iif($endtable, "</tr>\n");
		$endtable = iif($endtable, 0, 1);
	}
	print_label_row($vbphrase['projects'], '<table cellspacing="2" cellpadding="0" border="0">' . $projecttext . '</tr></table>', '', 'top', 'projects');

	print_textarea_row(
		"$vbphrase[magicselect_html_code] <dfn>$vbphrase[magicselect_html_code_desc]</dfn>",
		'htmlcode',
		htmlspecialchars($magicselect['htmlcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_textarea_row(
		"$vbphrase[magicselect_fetch_code] <dfn>$vbphrase[magicselect_fetch_code_desc]</dfn>",
		'fetchcode',
		htmlspecialchars($magicselect['fetchcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	print_textarea_row(
		"$vbphrase[magicselect_save_code] <dfn>$vbphrase[magicselect_save_code_desc]</dfn>",
		'savecode',
		htmlspecialchars($magicselect['savecode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	construct_hidden_code('magicselectid', $magicselect['magicselectid']);

	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");
}

// ########################################################################
if ($_POST['do'] == 'saveorder')
{
	$vbulletin->input->clean_gpc('p', 'displayorder', TYPE_ARRAY_UINT);

	$case = '';

	foreach ($vbulletin->GPC['displayorder'] AS $magicselectid => $displayorder)
	{
		$case .= "\nWHEN " . intval($magicselectid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_magicselect SET
				displayorder = CASE magicselectid $case ELSE displayorder END
		");
	}

	define('CP_REDIRECT', 'projectmagicselect.php?do=list');
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$mslist = $db->query_read("
		SELECT magicselectid, displayorder
		FROM " . TABLE_PREFIX . "pt_magicselect
		ORDER BY displayorder
	");

	print_form_header('projectmagicselect', 'saveorder');
	print_table_header($vbphrase['project_magic_select_list'], 3);

	print_cells_row(array(
		$vbphrase['project_magic_select'],
		$vbphrase['display_order'],
		$vbphrase['controls']
	), true);

	if ($db->num_rows($mslist) > 0)
	{
		while ($magicselect = $db->fetch_array($mslist))
		{
			print_cells_row(array(
				$vbphrase['magicselect' . $magicselect['magicselectid'] . ''],
				'<input type="text" name="displayorder[' . $magicselect['magicselectid'] . ']" value="' . $magicselect['displayorder'] . '" size="3" />',
				"<div align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" class=\"smallfont\">" .
					construct_link_code($vbphrase['edit'], 'projectmagicselect.php?do=edit&amp;magicselectid=' . $magicselect['magicselectid']) .
					construct_link_code($vbphrase['delete'], 'projectmagicselect.php?do=delete&amp;magicselectid=' . $magicselect['magicselectid']) .
				'</div>'
			));
		}

		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row(
			$vbphrase['no_project_magic_select_defined_click_here_to_add_one'],
			false,
			3,
			'',
			'center'
		);

		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_magic_select'], 'projectmagicselect.php?do=add') . '</p>';
}

print_cp_footer();

?>