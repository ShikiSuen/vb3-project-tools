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
	'milestoneidid' => TYPE_UINT
));

log_admin_action((!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') . (!empty($vbulletin->GPC['milestoneid']) ? ' milestone id = ' . $vbulletin->GPC['milestoneid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'projectmilestone';
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
// ######################## MILESTONE MANAGEMENT ##########################
// ########################################################################
if ($_POST['do'] == 'projectmilestoneupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_STR,
		'description' => TYPE_STR,
		'targetdate' => TYPE_UNIXTIME,
		'completeddate' => TYPE_UNIXTIME
	));

	if ($vbulletin->GPC['milestoneid'])
	{
		$milestone = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
		);

		$vbulletin->GPC['projectid'] = $milestone['projectid'];
	}
	else
	{
		$milestone = array();
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

	$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_CP);

	if ($milestone['milestoneid'])
	{
		$milestonedata->set_existing($milestone);
	}
	else
	{
		$milestonedata->set('projectid', $project['projectid']);
	}

	$milestonedata->set_info('title', $vbulletin->GPC['title']);
	$milestonedata->set_info('description', $vbulletin->GPC['description']);
	$milestonedata->set('targetdate', $vbulletin->GPC['targetdate']);
	$milestonedata->set('completeddate', $vbulletin->GPC['completeddate']);
	$milestonedata->save();	

	define('CP_REDIRECT', 'projectmilestone.php?do=projectmilestone&projectid=' . $project['projectid']);
	print_stop_message('project_milestone_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestoneadd' OR $_REQUEST['do'] == 'projectmilestoneedit')
{
	if ($vbulletin->GPC['milestoneid'])
	{
		$milestone = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
		);

		$vbulletin->GPC['projectid'] = $milestone['projectid'];
	}
	else
	{
		$milestone = array(
			'milestoneid' => 0,
			'title' => '',
			'description' => '',
			'targetdate' => 0,
			'completeddate' => 0
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectmilestone', 'projectmilestoneupdate');

	if ($milestone['milestoneid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_milestone'], $vbphrase['milestone_' . $milestone['milestoneid'] . '_name']));
		$trans_link_name = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=milestone_" . $milestone['milestoneid'] . "_name";
		$trans_link_desc = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=milestone_" . $milestone['milestoneid'] . "_description";
	}
	else
	{
		print_table_header($vbphrase['add_milestone']);
		$trans_link_name = '';
		$trans_link_desc = '';
	}

	print_input_row("$vbphrase[title]<dfn>$vbphrase[html_is_allowed]</dfn>" . ($trans_link_name ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link_name, true) . '</dfn>' : '') . "", 'title', $vbphrase['milestone_' . $milestone['milestoneid'] . '_name']);
	print_textarea_row("$vbphrase[description]<dfn>$vbphrase[html_is_allowed]</dfn>" . ($trans_link_desc ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link_desc, true) . '</dfn>' : '') . "", 'description', $vbphrase['milestone_' . $milestone['milestoneid'] . '_description']);
	print_time_row("$vbphrase[target_date]<dfn>$vbphrase[target_date_desc]</dfn>", 'targetdate', $milestone['targetdate'], false);
	print_time_row("$vbphrase[completed_date]<dfn>$vbphrase[completed_date_desc]</dfn>", 'completeddate', $milestone['completeddate'], false);

	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('milestoneid', $milestone['milestoneid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectmilestonekill')
{
	$vbulletin->input->clean_gpc('p', 'destmilestoneid', TYPE_UINT);

	$milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
	);

	$project = fetch_project_info($milestone['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			milestoneid = " . $vbulletin->GPC['destmilestoneid'] . "
		WHERE milestoneid = $milestone[milestoneid]
	");

	$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_CP);
	$milestonedata->set_existing($milestone);
	$milestonedata->delete();

	// rebuild the counters for the target milestone
	$dest_milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['destmilestoneid']
	);

	if ($dest_milestone)
	{
		$milestonedata =& datamanager_init('Pt_Milestone', $vbulletin, ERRTYPE_SILENT);
		$milestonedata->set_existing($dest_milestone);
		$milestonedata->rebuild_milestone_counters();
		$milestonedata->save();
	}

	define('CP_REDIRECT', 'projectmilestone.php?do=projectmilestone&projectid=' . $project['projectid']);
	print_stop_message('project_milestone_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestonedelete')
{
	$milestone = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = " . $vbulletin->GPC['milestoneid']
	);

	$project = fetch_project_info($milestone['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	require_once(DIR . '/includes/functions_pt_posting.php');
	$milestones = fetch_milestone_select_list($project['projectid'], array($milestone['milestoneid']));

	print_delete_confirmation(
		'pt_milestone',
		$milestone['milestoneid'],
		'projectmilestone', 'projectmilestonekill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_milestone'] . '<select name="destmilestoneid">' . construct_select_options($milestones) . '</select>',
		'title'
	);
}

// ########################################################################
if ($_REQUEST['do'] == 'projectmilestone')
{
	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$milestones = array();

	$milestone_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE projectid = $project[projectid]
		ORDER BY completeddate, targetdate DESC
	");

	while ($milestone = $db->fetch_array($milestone_data))
	{
		$milestones["$milestone[milestoneid]"] = $milestone;
	}

	$lastcompleted = null;

	print_form_header();
	print_table_header(construct_phrase($vbphrase['milestones_for_x_html'], $project['title_clean']), 3);

	if ($milestones)
	{
		foreach ($milestones AS $milestone)
		{
			if ($lastcompleted !== $milestone['completeddate'])
			{
				if ($milestone['completeddate'] == 0)
				{
					print_cells_row(array($vbphrase['active_milestones'], $vbphrase['target_date'], '&nbsp;'), true);
				}
				else if ($lastcompleted == 0 AND $milestone['completeddate'] != 0)
				{
					print_cells_row(array($vbphrase['completed_milestones'], $vbphrase['completed_date'], '&nbsp;'), true);
				}

				$lastcompleted = $milestone['completeddate'];
			}

			if ($milestone['completeddate'])
			{
				$formatted_date = vbdate($vbulletin->options['dateformat'], $milestone['completeddate']);
			}
			else if ($milestone['targetdate'])
			{
				$formatted_date = vbdate($vbulletin->options['dateformat'], $milestone['targetdate']);
			}
			else
			{
				$formatted_date = $vbphrase['n_a'];
			}

			print_cells_row(array(
				$vbphrase['milestone_' . $milestone['milestoneid'] . '_name'],
				$formatted_date,
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
					construct_link_code($vbphrase['edit'], 'projectmilestone.php?do=projectmilestoneedit&amp;milestoneid=' . $milestone['milestoneid']) .
					construct_link_code($vbphrase['delete'], 'projectmilestone.php?do=projectmilestonedelete&amp;milestoneid=' . $milestone['milestoneid']) .
				'</div>'
			));
		}

		construct_hidden_code('projectid', $project['projectid']);
	}
	else
	{
		print_description_row($vbphrase['no_milestones_defined_for_this_project'], false, 3, '', 'center');
	}

	print_table_footer();

	echo '<p align="center">' . construct_link_code($vbphrase['add_milestone'], 'projectmilestone.php?do=projectmilestoneadd&amp;projectid=' . $project['projectid']) . '</p>';

}

print_cp_footer();

?>