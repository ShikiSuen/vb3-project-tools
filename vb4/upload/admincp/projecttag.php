<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
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
log_admin_action();

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
// ########################### TAG MANAGEMENT #############################
// ########################################################################
if ($_POST['do'] == 'insert')
{
	$vbulletin->input->clean_gpc('p', 'tagtext', TYPE_STR);

	if ($db->query_first("
		SELECT tagid
		FROM " . TABLE_PREFIX . "pt_tag
		WHERE tagtext = '" . $db->escape_string($vbulletin->GPC['tagtext']) . "'
	"))
	{
		print_stop_message('tag_exists');
	}

	$db->query_write("
		INSERT IGNORE INTO " . TABLE_PREFIX . "pt_tag
			(tagtext)
		VALUES
			('" . $db->escape_string($vbulletin->GPC['tagtext']) . "')
	");

	define('CP_REDIRECT', 'projecttag.php?do=list');
	print_stop_message('tag_saved');
}

// ########################################################################
if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_gpc('p', 'tag', TYPE_ARRAY_KEYS_INT);

	if ($vbulletin->GPC['tag'])
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_tag
			WHERE tagid IN (" . implode(',', $vbulletin->GPC['tag']) . ")
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "pt_issuetag
			WHERE tagid IN (" . implode(',', $vbulletin->GPC['tag']) . ")
		");
	}

	define('CP_REDIRECT', 'projecttag.php?do=list');
	print_stop_message('tags_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);

	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	$column_count = 3;
	$max_per_column = 15;

	$perpage = $column_count * $max_per_column;
	$start = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

	$tags = $db->query_read("
		SELECT SQL_CALC_FOUND_ROWS *
		FROM " . TABLE_PREFIX . "pt_tag
		ORDER BY tagtext
		LIMIT $start, $perpage
	");

	list($tag_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

	print_form_header('projecttag', 'kill');
	print_table_header($vbphrase['tag_list'], 3);

	if ($db->num_rows($tags))
	{
		$columns = array();
		$counter = 0;

		// build page navigation
		$total_pages = ceil($tag_count / $perpage);

		if ($total_pages > 1)
		{
			$pagenav = '<strong>' . $vbphrase['go_to_page'] . '</strong>';

			for ($thispage = 1; $thispage <= $total_pages; $thispage++)
			{
				if ($thispage == $vbulletin->GPC['pagenumber'])
				{
					$pagenav .= " <strong>[$thispage]</strong> ";
				}
				else
				{
					$pagenav .= " <a href=\"project.php?$session[sessionurl]do=list&amp;page=$thispage\" class=\"normal\">$thispage</a> ";
				}
			}

			print_description_row($pagenav, false, 3, 'thead', 'right');
		}

		// build columns
		while ($tag = $db->fetch_array($tags))
		{
			$columnid = floor($counter++ / $max_per_column);
			$columns["$columnid"][] = '<input type="checkbox" name="tag[' . $tag['tagid'] . ']" id="tag' . $tag['tagid'] . '_1" value="1" tabindex="1" /> ' . $tag['tagtext'];
		}

		// make column values printable
		$cells = array();

		for ($i = 0; $i < $column_count; $i++)
		{
			if ($columns["$i"])
			{
				$cells[] = implode("<br />\n", $columns["$i"]);
			}
			else
			{
				$cells[] = '&nbsp;';
			}
		}

		print_column_style_code(array(
			'width: 33%',
			'width: 33%',
			'width: 34%'
		));
		print_cells_row($cells, false, false, -3);
		print_submit_row($vbphrase['delete_selected'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_tags_defined'], false, 3, '', 'center');
		print_table_footer();
	}

	print_form_header('projecttag', 'insert');
	print_input_row($vbphrase['add_tag'], 'tagtext');
	print_submit_row();
}

print_cp_footer();

?>