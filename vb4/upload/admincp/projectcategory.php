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
	'projectcategoryid' => TYPE_UINT
));

log_admin_action((!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') . (!empty($vbulletin->GPC['projectcategoryid']) ? ' category id = ' . $vbulletin->GPC['projectcategoryid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

$issuetype_options = array();

$types = $db->query_read("
	SELECT *
	FROM " . TABLE_PREFIX . "pt_issuetype
	ORDER BY displayorder
");

while ($type = $db->fetch_array($types))
{
	$issuetype_options["$type[issuetypeid]"] = $vbphrase["issuetype_$type[issuetypeid]_singular"];
}

$helpcache['project']['projectadd']['afterforumids[]'] = 1;
$helpcache['project']['projectedit']['afterforumids[]'] = 1;

// ########################################################################
// ################### PROJECT CATEGORY MANAGEMENT ########################
// ########################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT,
		'default' => TYPE_BOOL
	));

	if ($vbulletin->GPC['projectcategoryid'])
	{
		$projectcategory = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
		);
		$vbulletin->GPC['projectid'] = $projectcategory['projectid'];
	}
	else
	{
		$projectcategory = array();
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

	if ($projectcategory['projectcategoryid'])
	{
		// Check first if the default value is already defined for this project
		// If yes, remove it and save the actual form
		$defaultvalue = $db->query_first("
			SELECT projectcategoryid AS cat
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['cat'] != $projectcategory['projectcategoryid'])
		{
			// Default value already defined for an other category
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
					defaultvalue = 0
				WHERE projectcategoryid = " . intval($defaultvalue['cat']) . "
			");
		}

		// Perform the save
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
				displayorder = " . $vbulletin->GPC['displayorder'] . ",
				defaultvalue = " . ($vbulletin->GPC['default'] ? 1 : 0) . "
			WHERE projectcategoryid = $projectcategory[projectcategoryid]
		");

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'category" . intval($vbulletin->GPC['projectcategoryid']) . "',
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
			SELECT projectcategoryid AS cat
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['cat'])
		{
			// Default value already defined for an other category
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
					defaultvalue = 0
				WHERE projectcategoryid = " . intval($defaultvalue['cat']) . "
			");
		}

		// Perform the save
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectcategory
				(projectid, displayorder, defaultvalue)
			VALUES
				(" . $project['projectid'] . ",
				" . $vbulletin->GPC['displayorder'] . ",
				" . ($vbulletin->GPC['default'] ? 1 : 0) . ")
		");

		$categoryid = $db->insert_id();

		// Phrase the category
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'category" . intval($categoryid) . "',
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

	build_project_category_cache();

	define('CP_REDIRECT', 'projectcategory.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_category_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	if ($vbulletin->GPC['projectcategoryid'])
	{
		$projectcategory = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
		);
		$vbulletin->GPC['projectid'] = $projectcategory['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectcategory
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectcategory = array(
			'projectcategoryid' => 0,
			'title' => '',
			'displayorder' => $maxorder['maxorder'] + 10
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectcategory', 'update');

	if ($projectcategory['projectcategoryid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_project_category'], $vbphrase['category' . $projectcategory['projectcategoryid'] . '']));
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=category";
	}
	else
	{
		print_table_header($vbphrase['add_project_category']);
		$trans_link = '';
	}

	print_input_row($vbphrase['title'] . ($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $projectcategory['projectcategoryid'], true) . '</dfn>' : ''), 'title', $vbphrase['category' . $projectcategory['projectcategoryid'] . ''], false);
	print_input_row($vbphrase['display_order'], 'displayorder', $projectcategory['displayorder'], true, 5);
	print_yes_no_row($vbphrase['default_value'], 'default', $projectpriority['defaultvalue']);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectcategoryid', $projectcategory['projectcategoryid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_gpc('p', 'destcategoryid', TYPE_UINT);

	$projectcategory = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
	);

	$project = fetch_project_info($projectcategory['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = $projectcategory[projectcategoryid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "phrase
		WHERE varname = 'category" . $projectcategory['projectcategoryid'] . "'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			projectcategoryid = " . $vbulletin->GPC['destcategoryid'] . "
		WHERE projectcategoryid = $projectcategory[projectcategoryid]
	");

	build_project_category_cache();

	define('CP_REDIRECT', 'projectcategory.php?do=list&projectid=' . $project['projectid']);
	print_stop_message('project_category_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'delete')
{
	$projectcategory = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectcategoryid = " . $vbulletin->GPC['projectcategoryid']
	);

	$project = fetch_project_info($projectcategory['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$categories = array();

	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectid = $project[projectid]
			AND projectcategoryid <> $projectcategory[projectcategoryid]
		ORDER BY displayorder
	");

	while ($category = $db->fetch_array($category_data))
	{
		$categories["$category[projectcategoryid]"] = $vbphrase['category' . $category['projectcategoryid'] . ''];
	}

	$categories = array(0 => $vbphrase['unknown']) + $categories;

	print_delete_confirmation(
		'pt_projectcategory',
		$projectcategory['projectcategoryid'],
		'projectcategory',
		'kill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_category'] . '<select name="destcategoryid">' . construct_select_options($categories) . '</select>',
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
			UPDATE " . TABLE_PREFIX . "pt_projectcategory SET
				displayorder = CASE projectcategoryid $case ELSE displayorder END
		");
	}

	build_project_category_cache();

	define('CP_REDIRECT', 'projectcategory.php?do=list&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$vbulletin->input->clean_gpc('r', 'projectid', TYPE_UINT);

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$categories = array();

	$category_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectcategory
		WHERE projectid = " . $project['projectid'] . "
		ORDER BY displayorder
	");

	while ($category = $db->fetch_array($category_data))
	{
		$categories["$category[projectcategoryid]"] = $category;
	}

	print_form_header('projectcategory', 'order');
	print_table_header(construct_phrase($vbphrase['categories_for_x'], $project['title_clean']), 3);

	if ($categories)
	{
		print_cells_row(array($vbphrase['category'], $vbphrase['display_order'], '&nbsp;'), true);

		foreach ($categories AS $category)
		{
			print_cells_row(array(
				$vbphrase['category' . $category['projectcategoryid'] . ''],
				"<input type=\"text\" class=\"bginput\" name=\"order[$category[projectcategoryid]]\" value=\"$category[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'projectcategory.php?do=edit&amp;projectcategoryid=' . $category['projectcategoryid']) .
					construct_link_code($vbphrase['delete'], 'projectcategory.php?do=delete&amp;projectcategoryid=' . $category['projectcategoryid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_categories_defined_project'], false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_category'], 'projectcategory.php?do=add&amp;projectid=' . $project['projectid']) . '</p>';
}

print_cp_footer();

?>