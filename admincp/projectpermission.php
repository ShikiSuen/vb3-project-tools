<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| #  This is file is subject to the vBulletin Open Source License.   # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Revision: 32878 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('projecttools', 'cppermission', 'projecttoolsadmin');
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

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canpt'))
{
	print_cp_no_permission();
}
if (!can_administer('canptpermissions'))
{
	print_cp_no_permission();
}
// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'usergroupid' => TYPE_UINT,
	'projectid' => TYPE_UINT,
));
log_admin_action(
	(!empty($vbulletin->GPC['usergroupid']) ? ' user group id = ' . $vbulletin->GPC['usergroupid'] : '') .
	(!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '')
);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_permissions']);

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
$vbulletin->pt_bitfields = build_project_bitfields();

// ########################################################################

if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'usergroupid' => TYPE_UINT,
		'projectid' => TYPE_UINT,
		'permissions' => TYPE_ARRAY,
		'ugpermissions' => TYPE_ARRAY_UINT,
		'original' => TYPE_ARRAY,
		'revert' => TYPE_ARRAY_UINT,
		'force' => TYPE_ARRAY_UINT
	));


	require_once(DIR . '/includes/functions_misc.php');

	// figure out what the permission columns are,
	// so we can put in entries for any groups that don't have permissions
	$default_perms = array();
	$perm_fields = $db->query_read("
		SHOW COLUMNS FROM " . TABLE_PREFIX . "pt_projectpermission
		LIKE '%permissions'
	");
	while ($perm_field = $db->fetch_array($perm_fields))
	{
		$default_perms[str_replace('permissions', '', $perm_field['Field'])] = 0;
	}

	if ($vbulletin->GPC['projectid'])
	{
		$projecttype_options = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);
		$issuetype_options = array();
		while ($issuetype = $db->fetch_array($projecttype_options))
		{
			$issuetype_options["$issuetype[issuetypeid]"] = $vbphrase["issuetype_$issuetype[issuetypeid]_singular"];
		}
	}

	// loop through permissions array, which is [type][name of bitfield entry]
	foreach (array_keys($issuetype_options) AS $permtype)
	{
		if (!empty($vbulletin->GPC['revert']["$permtype"]))
		{
			// reverting
			if ($vbulletin->GPC['projectid'])
			{
				// can only revert if these are custom permissions to start
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "pt_projectpermission
					WHERE usergroupid = " . $vbulletin->GPC['usergroupid'] . "
						AND projectid = " . $vbulletin->GPC['projectid'] . "
						AND issuetypeid = '" . $db->escape_string($permtype) . "'
				");
			}
			continue;
		}

		if (!is_array($vbulletin->GPC['permissions']["$permtype"]))
		{
			$vbulletin->GPC['permissions']["$permtype"] = $default_perms;
		}
		else
		{
			foreach ($default_perms AS $permgroupid => $group_default)
			{
				if (!is_array($vbulletin->GPC['permissions']["$permtype"]["$permgroupid"]))
				{
					$vbulletin->GPC['permissions']["$permtype"]["$permgroupid"] = $group_default;
				}
			}
		}

		$do_save = $vbulletin->GPC['force']["$permtype"];
		if (!$vbulletin->GPC['projectid'])
		{
			$do_save = true;
		}
		$perms = array();
		foreach ($vbulletin->GPC['permissions']["$permtype"] AS $groupid => $bits)
		{
			if (is_int($bits))
			{
				$perms["$groupid"] = $bits;
			}
			else
			{
				// convert to int from array of bits
				$perms["$groupid"] = intval(convert_array_to_bits($bits, $vbulletin->pt_bitfields["$groupid"]));
			}

			if (!isset($vbulletin->GPC['original']["$permtype"]["$groupid"]) OR $perms["$groupid"] != intval($vbulletin->GPC['original']["$permtype"]["{$groupid}permissions"]))
			{
				$do_save = true;
			}
		}

		if ($do_save)
		{
			// permissions changed or we're forcing custom perms to be set
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "pt_projectpermission
					(usergroupid, projectid, issuetypeid, generalpermissions, postpermissions, attachpermissions)
				VALUES
					(" . $vbulletin->GPC['usergroupid'] . ",
					" . $vbulletin->GPC['projectid'] . ",
					'" . $db->escape_string($permtype) . "',
					" . intval($perms['general']) . ",
					" . intval($perms['post']) . ",
					" . intval($perms['attach']) . ")
			");
		}
	}

	if (!$vbulletin->GPC['projectid'])
	{
		// updating usergroup-level permissions
		$newpermval = 0;
		foreach ($vbulletin->GPC['ugpermissions'] AS $bitval => $yesno)
		{
			if ($yesno)
			{
				$newpermval += $bitval;
			}
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "usergroup SET
				ptpermissions = $newpermval
			WHERE usergroupid = " . $vbulletin->GPC['usergroupid']
		);

		require_once(DIR . '/includes/functions_databuild.php');
		build_forum_permissions();
	}

	//build_project_permissions();
	build_assignable_users();
	build_pt_user_list('pt_report_users', 'pt_report_user_cache');

	define('CP_REDIRECT', 'projectpermission.php?do=list' . ($vbulletin->GPC['projectid'] ? ('&amp;projectid=' . $vbulletin->GPC['projectid']) : ''));
	print_stop_message('project_permissions_saved');
}

// ########################################################################

if ($_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'usergroupid' => TYPE_UINT,
		'projectid' => TYPE_UINT
	));

	$default_perms = array();
	$perm_fields = $db->query_read("
		SHOW COLUMNS FROM " . TABLE_PREFIX . "pt_projectpermission
		LIKE '%permissions'
	");
	while ($perm_field = $db->fetch_array($perm_fields))
	{
		$default_perms[str_replace('permissions', '', $perm_field['Field'])] = 0;
	}

	$global_permissions = array();
	foreach (array_keys($issuetype_options) AS $typeid)
	{
		$global_permissions["$typeid"] = $default_perms;
	}

	$usergroup_info = array();

	$usergroup_data = $db->query_read("
		SELECT projectpermission.*,
			usergroup.usergroupid, usergroup.title, usergroup.ptpermissions
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		LEFT JOIN " . TABLE_PREFIX . "pt_projectpermission AS projectpermission ON
			(usergroup.usergroupid = projectpermission.usergroupid AND projectpermission.projectid = 0)
		WHERE usergroup.usergroupid = " . $vbulletin->GPC['usergroupid']
	);
	while ($usergroup = $db->fetch_array($usergroup_data))
	{
		if ($usergroup['issuetypeid'])
		{
			$perms = $usergroup;
			unset($perms['usergroupid'], $perms['projectid'], $perms['issuetypeid'], $perms['title']);
			$global_permissions["$usergroup[issuetypeid]"] = $perms;
		}

		$usergroup_info = array(
			'title' => $usergroup['title'],
			'usergroupid' => $usergroup['usergroupid'],
			'ptpermissions' => $usergroup['ptpermissions']
		);
	}

	if (!$usergroup_info)
	{
		print_stop_message('invalid_action_specified');
	}

	if ($vbulletin->GPC['projectid'])
	{
		$project = fetch_project_info($vbulletin->GPC['projectid']);

		$projecttype_options = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = $project[projectid]
		");
		$issuetype_options = array();
		while ($issuetype = $db->fetch_array($projecttype_options))
		{
			$issuetype_options["$issuetype[issuetypeid]"] = $vbphrase["issuetype_$issuetype[issuetypeid]_singular"];
		}
	}

	$issue_types_js = array();
	foreach ($issuetype_options AS $issuetypeid => $phrase)
	{
		$issue_types_js[] = "'$issuetypeid'";
	}

	?>
<script type="text/javascript">
<!--

var issue_types = new Array(<?php echo implode(', ', $issue_types_js); ?>);

function js_toggle_perms(formobj, id_prefix, setto)
{
	for (var i =0; i < formobj.elements.length; i++)
	{
		var elm = formobj.elements[i];
		if (elm.type == 'checkbox' && elm.id.indexOf(id_prefix) == 0 && elm.id.substring(id_prefix.length).match(/^[0-9]+$/))
		{
			elm.checked = setto;
		}
	}
}

function js_toggle_perm_all_types(id_format)
{
	var obj, first_obj = null;
	var have_true = false, have_false = false;

	for (var issuetypeid in issue_types)
	{
		if (obj = fetch_object(id_format.replace('%s', issue_types[issuetypeid])))
		{
			if (first_obj === null)
			{
				first_obj = obj;
			}

			if (obj.checked)
			{
				have_true = true;
			}
			else
			{
				have_false = true;
			}
		}
	}

	if (have_true && have_false)
	{
		var newval = first_obj.checked;
	}
	else if (have_true)
	{
		var newval = false;
	}
	else
	{
		var newval = true;
	}

	for (var issuetypeid in issue_types)
	{
		if (obj = fetch_object(id_format.replace('%s', issue_types[issuetypeid])))
		{
			obj.checked = newval;
		}
	}
}

// -->
</script>
	<?php

	print_form_header('projectpermission', 'update');
	if (!empty($project))
	{
		// editing project-specific permissions
		print_table_header(construct_phrase($vbphrase['edit_permissions_for_x_in_y'], $usergroup_info['title'], $project['title_clean']), sizeof($issuetype_options) + 1);

		// fetch the custom perms, row per type this time
		$customperms = array();
		$customperms_data = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpermission
			WHERE usergroupid = $usergroup_info[usergroupid]
				AND projectid = $project[projectid]
		");
		while ($customperm = $db->fetch_array($customperms_data))
		{
			$perms = $customperm;
			unset($perms['usergroupid'], $perms['projectid'], $perms['issuetypeid']);

			$customperms["$customperm[issuetypeid]"] = $perms;
		}
	}
	else
	{
		// editing global permissions
		print_table_header(construct_phrase($vbphrase['edit_permissions_for_x'], $usergroup_info['title']), sizeof($issuetype_options) + 1);

		$customperms = array();
	}

	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::build(false);
	$builder =& vB_Bitfield_Builder::init();

	$perms = array();
	$has_custom = array();

	if (!$project)
	{
		// these are the global-only (settable in one place) permissions for the project tools
		print_description_row($vbphrase['global_project_tools_permissions'], false, 4, 'thead');

		foreach ($builder->data['ugp']['ptpermissions'] AS $bitname => $permvalue)
		{
			$bit = $permvalue['value'];
			print_yes_no_row($vbphrase["$permvalue[phrase]"], "ugpermissions[$bit]", (intval($usergroup_info['ptpermissions']) & intval($bit)) ? 1 : 0);
		}

		print_description_row('<span class="smallfont"><a href="#" onclick="js_open_help(\'projectpermission\', \'edit\', \'about_reports\'); return false;">' . $vbphrase['what_are_reports'] . '</a></span>', false, 2, '', 'right');
		print_table_break();
		print_table_header(construct_phrase($vbphrase['project_tools_permissions_for_x'], $usergroup_info['title']), sizeof($issuetype_options) + 1);
	}
	else
	{
		// project specific -- let's check the general permissions for this group
		if (!(intval($usergroup_info['ptpermissions']) & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
		{
			print_description_row($vbphrase['usergroups_access_globally_disabled'], false, sizeof($issuetype_options) + 1);
		}
	}

	print_description_row('<span class="smallfont">' . $vbphrase['double_click_permission_quick_set'] . '</span>', false, sizeof($issuetype_options) + 1);

	// show all permissions for each type
	foreach (array_keys($issuetype_options) AS $permtype)
	{
		// if we have custom use them, else use the global ones
		if (isset($customperms["$permtype"]))
		{
			$has_custom["$permtype"]  = true;
			$perms["$permtype"] = $customperms["$permtype"];
		}
		else
		{
			$has_custom["$permtype"]  = false;
			$perms["$permtype"] = $global_permissions["$permtype"];
		}

		if (is_array($perms["$permtype"]))
		{
			foreach ($perms["$permtype"] AS $permname => $value)
			{
				construct_hidden_code("original[$permtype][$permname]", $value);
			}
		}
	}

	print_column_style_code(array(
		'width: 100%',
		''
	));

	// show all permissions in the bitfields
	$groups = array();
	$bit_permgroups = array();
	foreach ($builder->data['pt_permissions'] AS $groupid => $permission_group)
	{
		foreach ($permission_group AS $bitname => $permvalue)
		{
			$groups["$permvalue[group]"]["$bitname"] = $permvalue;
			$bit_permgroups["$bitname"] = $groupid;
		}
	}

	foreach ($groups AS $groupid => $permission_group)
	{
		// loop through the group's permissions once, to determine if the check all box is selected
		$cell = array($vbphrase["ptperm_group_$groupid"]);

		foreach ($issuetype_options AS $issuetypeid => $issuetype_text)
		{
			$checkall = ' checked="checked"';

			foreach ($permission_group AS $bitname => $permvalue)
			{
				$permgroupid = $bit_permgroups["$bitname"];
				if (!($perms["$issuetypeid"]["{$permgroupid}permissions"] & intval($permvalue['value'])))
				{
					$checkall = '';
					break;
				}
			}

			$cell[] = '<label style="white-space:nowrap" class="smallfont">' .
				'<input type="checkbox" title="' . $vbphrase['check_all'] . '" onclick="js_toggle_perms(this.form, \'permissions_' . "{$issuetypeid}_{$groupid}" . '_\', this.checked);"' . $checkall . ' />' .
				$issuetype_text .
			'</label>';
		}

		print_cells_row($cell, true, false, 0);

		foreach ($permission_group AS $bitname => $permvalue)
		{
			$cell = array("<span style=\"cursor: pointer\" title=\"$vbphrase[quick_click_quick_set_permission]\" ondblclick=\"js_toggle_perm_all_types('permissions_%s_{$groupid}_$permvalue[value]')\">" .
				(isset($vbphrase["$permvalue[phrase]"]) ? $vbphrase["$permvalue[phrase]"] : $permvalue['phrase']) .
			'</span>');

			foreach ($issuetype_options AS $issuetypeid => $issuetype_text)
			{
				$permgroupid = $bit_permgroups["$bitname"];
				$getval = ($perms["$issuetypeid"]["{$permgroupid}permissions"] & intval($permvalue['value'])) ? 1 : 0;

				$cell[] = '<span style="white-space:nowrap" class="smallfont">' .
					'<input type="checkbox" id="' . "permissions_{$issuetypeid}_{$groupid}_$permvalue[value]\" name=\"permissions[$issuetypeid][$permgroupid][$bitname]" . '" value="1" ' . ($getval ? 'checked="checked"' : '') . ' />' .
					'<span style="visibility:hidden">' . $issuetype_text . '</span>
				</span>';
			}

			print_cells_row($cell, false, false, 0);
		}
	}

	// show the reset options if we're editing a project's info
	if ($project)
	{
		print_table_break();

		$options = array();
		foreach ($has_custom AS $type => $actually_has)
		{
			if ($actually_has)
			{
				$options[] = "<div nowrap=\"nowrap\"><label><input type=\"checkbox\" name=\"revert[$type]\" value=\"1\" /> " . $issuetype_options["$type"] . '</label></div>';
			}
		}
		if ($options)
		{
			print_label_row($vbphrase['revert_to_default_permissions'], implode("\n", $options));
		}

		$options = array();
		foreach ($has_custom AS $type => $actually_has)
		{
			if (!$actually_has)
			{
				$options[] = "<div nowrap=\"nowrap\"><label><input type=\"checkbox\" name=\"force[$type]\" value=\"1\" /> " . $issuetype_options["$type"] . '</label></div>';
			}
		}
		if ($options)
		{
			print_label_row($vbphrase['force_to_be_custom'], implode("\n", $options));
		}
	}

	construct_hidden_code('usergroupid', $usergroup_info['usergroupid']);
	construct_hidden_code('projectid', $project['projectid']);
	print_submit_row('', '', sizeof($issuetype_options) + 1);
}

// ########################################################################
if ($_REQUEST['do'] == 'list')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'projectid' => TYPE_UINT,
		'hidedisabled' => TYPE_UINT
	));

	// global permission info
	$global_usergroups = array();
	$usergroup_info = array();

	$usergroup_data = $db->query_read("
		SELECT usergroup.usergroupid, usergroup.title, usergroup.ptpermissions
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		ORDER BY usergroup.title
	");
	while ($usergroup = $db->fetch_array($usergroup_data))
	{
		$usergroup_info["$usergroup[usergroupid]"] = $usergroup;
	}

	$usergroup_data = $db->query_read("
		SELECT projectpermission.*
		FROM " . TABLE_PREFIX . "pt_projectpermission AS projectpermission
		WHERE projectpermission.projectid = 0
	");
	while ($usergroup = $db->fetch_array($usergroup_data))
	{
		$global_usergroups["$usergroup[usergroupid]"]["$usergroup[issuetypeid]"] = $usergroup;
	}


	if ($vbulletin->GPC['projectid'])
	{
		$project = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_project
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);
	}

	if ($vbulletin->GPC['hidedisabled'])
	{
		$disabled_groups_line = '<a href="projectpermission.php?do=list&amp;projectid=' . $vbulletin->GPC['projectid'] . '">' . $vbphrase['show_globally_disabled_groups'] . '</a>';
	}
	else
	{
		$disabled_groups_line = '<a href="projectpermission.php?do=list&amp;projectid=' . $vbulletin->GPC['projectid'] . '&amp;hidedisabled=1">' . $vbphrase['hide_globally_disabled_groups'] .'</a>';
	}

	if (!empty($project))
	{
		// show permissions for a specific project
		$have_project = true;

		$project_usergroups = array();
		$usergroup_data = $db->query_read("
			SELECT projectpermission.*
			FROM " . TABLE_PREFIX . "pt_projectpermission AS projectpermission
			WHERE projectpermission.projectid = $project[projectid]
		");

		while ($usergroup = $db->fetch_array($usergroup_data))
		{
			$project_usergroups["$usergroup[usergroupid]"]["$usergroup[issuetypeid]"] = $usergroup;
		}

		$projecttype_options = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projecttype
			WHERE projectid = $project[projectid]
		");
		$project_types = array();
		while ($issuetype = $db->fetch_array($projecttype_options))
		{
			$project_types["$issuetype[issuetypeid]"] = $vbphrase["issuetype_$issuetype[issuetypeid]_singular"];
		}

		$header = array($vbphrase['usergroup']);
		foreach ($project_types AS $issuetype)
		{
			$header[] = "<span style=\"white-space: nowrap\">$issuetype</span>";
		}
		$header[] = '&nbsp;';

		print_form_header('', '');
		print_table_header(construct_phrase($vbphrase['permissions_for_project_x'], $project['title']) . "<a name=\"project$project[projectid]\"></a>", sizeof($header));

		print_column_style_code(array(
			'width: 100%',
			''
		));

		print_cells_row($header, true);

		foreach ($usergroup_info AS $usergroupid => $usergroup)
		{
			if (!(intval($usergroup['ptpermissions']) & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
			{
				// global permission to disable viewing set
				if ($vbulletin->GPC['hidedisabled'])
				{
					continue;
				}

				$cell = array("<strike>$usergroup[title]</strike>");
			}
			else
			{
				$cell = array("<span class=\"am-grant\"><strong>$usergroup[title]</strong></span>");
			}

			$hascustom = false;

			foreach (array_keys($project_types) AS $issuetypeid)
			{
				$perms = $global_usergroups["$usergroupid"]["$issuetypeid"];
				if (isset($project_usergroups["$usergroupid"]["$issuetypeid"]))
				{
					$perms = $project_usergroups["$usergroupid"]["$issuetypeid"];
					$hascustom = true;
				}
				$can_view_img = ($vbulletin->pt_bitfields['general']['canview'] & intval($perms['generalpermissions']) ? 'yes' : 'no');

				$cell[] = "<img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_tick_{$can_view_img}.gif\" alt=\"\" />";
			}

			if ($hascustom)
			{
				$cell[] = construct_link_code('<strong><span class="am-deny">' . $vbphrase['edit'] . '</span></strong>', 'projectpermission.php?do=edit&amp;usergroupid=' . $usergroupid . '&amp;projectid=' . $project['projectid']);
			}
			else
			{
				$cell[] = construct_link_code($vbphrase['edit'], 'projectpermission.php?do=edit&amp;usergroupid=' . $usergroupid . '&amp;projectid=' . $project['projectid']);
			}

			print_cells_row($cell);
		}

		print_description_row($disabled_groups_line, false, sizeof($cell), 'thead smallfont', 'center');
		print_table_footer();
	}
	else
	{
		// show only the global permissions
		$have_project = false;

		$header = array($vbphrase['usergroup']);
		foreach ($issuetype_options AS $issuetype)
		{
			$header[] = "<span style=\"white-space: nowrap\">$issuetype</span>";
		}
		$header[] = '&nbsp;';

		print_form_header('', '');
		print_table_header($vbphrase['global_project_permissions'], sizeof($header));
		print_description_row($vbphrase['permission_apply_all_projects_unless_custom'], false, sizeof($header));

		print_column_style_code(array(
			'width: 100%',
			''
		));

		print_cells_row($header, true);

		foreach ($usergroup_info AS $usergroupid => $usergroup)
		{
			if (!(intval($usergroup['ptpermissions']) & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
			{
				// global permission to disable viewing set
				if ($vbulletin->GPC['hidedisabled'])
				{
					continue;
				}

				$cell = array("<strike>$usergroup[title]</strike>");
			}
			else
			{
				$cell = array("<span class=\"am-grant\"><strong>$usergroup[title]</strong></span>");
			}

			$usergroup_perms = $global_usergroups["$usergroupid"];

			foreach (array_keys($issuetype_options) AS $issuetypeid)
			{
				$can_view_img = ($vbulletin->pt_bitfields['general']['canview'] & intval($usergroup_perms["$issuetypeid"]['generalpermissions']) ? 'yes' : 'no');

				$cell[] = "<img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/cp_tick_{$can_view_img}.gif\" alt=\"\" />";
			}

			$cell[] = construct_link_code($vbphrase['edit'], 'projectpermission.php?do=edit&amp;usergroupid=' . $usergroupid);

			print_cells_row($cell);
		}

		print_description_row($disabled_groups_line, false, sizeof($cell), 'thead smallfont', 'center');
		print_table_footer();
	}

	// list projects
	print_form_header('', '');
	print_table_header($vbphrase['project_specific_permissions']);
	print_description_row("<a href=\"projectpermission.php?do=list&amp;hidedisabled=" . $vbulletin->GPC['hidedisabled'] . "\">$vbphrase[global_project_permissions]</a>", false, 2, '', 'center');

	$projects = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_project
		ORDER BY displayorder
	");
	while ($project = $db->fetch_array($projects))
	{
		if ($project['projectid'] == $vbulletin->GPC['projectid'])
		{
			print_label_row(
				"<strong>$project[title_clean]</strong>",
				"<div align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\"><strong>
					<a href=\"projectpermission.php?do=list&amp;projectid=$project[projectid]&amp;hidedisabled=" . $vbulletin->GPC['hidedisabled'] . "\">$vbphrase[edit_permissions]</a>
				</strong></div>"
			);
		}
		else
		{
			print_label_row(
				$project['title_clean'],
				"<div align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\">
					<a href=\"projectpermission.php?do=list&amp;projectid=$project[projectid]&amp;hidedisabled=" . $vbulletin->GPC['hidedisabled'] . "\">$vbphrase[edit_permissions]</a>
				</div>"
			);
		}
	}
	print_table_footer();

	// list color key
	print_form_header('', '');
	print_table_header($vbphrase['color_key']);
	print_label_row('<span class="am-grant"><strong>' . $vbphrase['usergroup'] . '</strong></span>', $vbphrase['project_permission_values_apply']);
	print_label_row('<strike>' . $vbphrase['usergroup'] . '</strike>', $vbphrase['project_permission_values_do_not_apply']);
	if ($have_project)
	{
		print_label_row('<strong><span class="am-deny">' . $vbphrase['edit'] . '</span></strong>', $vbphrase['edit_link_colored_custom_permissions']);
	}
	print_table_footer();

}

// ########################################################################

print_cp_footer();
?>
