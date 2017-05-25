<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Rev: 897 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'projecttools',
	'projecttoolsadmin',
	'style'
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
	'issuestatusid' => TYPE_UINT,
	'issuetypeid' => TYPE_NOHTML
));

log_admin_action((!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') . (!empty($vbulletin->GPC['issuestatusid']) ? ' status id = ' . $vbulletin->GPC['issuestatusid'] : '') . (!empty($vbulletin->GPC['issuetypeid']) ? ' status id = ' . $vbulletin->GPC['issuetypeid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools'], iif(in_array($_REQUEST['do'], array('statusedit', 'statusadd', 'typelist')) , 'init_color_preview()'));

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'typelist';
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
// ##################### ISSUE STATUS MANAGEMENT ##########################
// ########################################################################
if ($_POST['do'] == 'statusupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'title' => TYPE_STR,
		'displayorder' => TYPE_UINT,
		'canpetitionfrom' => TYPE_UINT,
		'issuecompleted' => TYPE_UINT,
		'statuscolor' => TYPE_STR,
		'statuscolor2' => TYPE_STR,
		'projectset' => TYPE_ARRAY_UINT
	));

	if (empty($vbulletin->GPC['title']) OR empty($vbulletin->GPC['issuetypeid']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$statusdata = datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['issuestatusid'])
	{
		$issuestatus = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
		);

		if (!$issuestatus)
		{
			print_stop_message('invalid_action_specified');
		}

		$statusdata->set_existing($issuestatus);
	}
	else
	{
		$statusdata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	}

	$statusdata->set('displayorder', $vbulletin->GPC['displayorder']);
	$statusdata->set('canpetitionfrom', $vbulletin->GPC['canpetitionfrom']);
	$statusdata->set('issuecompleted', $vbulletin->GPC['issuecompleted']);
	$statusdata->set('statuscolor', $vbulletin->GPC['statuscolor']);
	$statusdata->set('statuscolor2', $vbulletin->GPC['statuscolor2']);
	$statusdata->set_info('title', $vbulletin->GPC['title']);
	$issuestatusid = $statusdata->save();

	if (!$vbulletin->GPC['issuestatusid'])
	{
		$vbulletin->GPC['issuestatusid'] = $issuestatusid;
	}

	$add_projectsets = array();

	// Delete all projects for this issue status
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_issuestatusprojectset
		WHERE issuestatusid = " . intval($vbulletin->GPC['issuestatusid']) . "
	");

	foreach ($vbulletin->GPC['projectset'] AS $projectsetid)
	{
		$add_projectsets[] = "(" . intval($vbulletin->GPC['issuestatusid']) . ", " . intval($projectsetid) . ")";
	}

	// Now add all checked projects
	if ($add_projectsets)
	{
		$db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "pt_issuestatusprojectset
				(issuestatusid, projectid)
			VALUES
				" . implode(',', $add_projectsets)
		);
	}

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('issue_status_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'statusadd' OR $_REQUEST['do'] == 'statusedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'type' => TYPE_STR,
		'colorPickerType' => TYPE_INT
	));

	if ($vbulletin->GPC['issuestatusid'])
	{
		$issuestatus = $db->query_first("
			SELECT issuestatus.*, phrase.text AS title
			FROM " . TABLE_PREFIX . "pt_issuestatus AS issuestatus
			LEFT JOIN " . TABLE_PREFIX . "phrase AS phrase ON
				(phrase.languageid = 0 AND phrase.fieldname = 'projecttools' AND phrase.varname = 'issuestatus" . $vbulletin->GPC['issuestatusid'] . "')
			WHERE issuestatus.issuestatusid = " . $vbulletin->GPC['issuestatusid']
		);
	}

	if (empty($issuestatus))
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_issuestatus
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['type']) . "'
		");

		$issuestatus = array(
			'issuestatusid' => 0,
			'issuetypeid' => $vbulletin->GPC['type'],
			'displayorder' => $maxorder['maxorder'] + 10,
			'canpetitionfrom' => 1,
			'issuecompleted' => 0,
			'title' => '',
			'statuscolor' => '',
			'statuscolor2' => '',
			'projectset' => '',
		);
	}

	?>
	<script type="text/javascript">
	<!--
	<?php
	foreach (array(
		'css_value_invalid',
		'color_picker_not_ready',
	) AS $phrasename)
	{
			$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}
	?>

	var vbphrase = {
		<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
	};
	//-->
	</script>
	<?php

	echo '<script type="text/javascript" src="../clientscript/vbulletin_cpcolorpicker.js"></script>';

	print_form_header('projecttype', 'statusupdate');

	if ($issuestatus['issuestatusid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_status_x'], $issuestatus['title']));
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname=issuestatus"; // has ID appended
	}
	else
	{
		print_table_header($vbphrase['add_issue_status']);
		$trans_link = '';
	}

	print_input_row(
		$vbphrase['title'] . ($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . $issuestatus['issuestatusid'], true) . '</dfn>' : ''),
		'title',
		$issuestatus['title']
	);

	if (isset($issuetype_options["$issuestatus[issuetypeid]"]))
	{
		print_label_row($vbphrase['issue_type'], $issuetype_options["$issuestatus[issuetypeid]"], '', 'top', 'issuetypeid');
		construct_hidden_code('issuetypeid', $issuestatus['issuetypeid']);
	}
	else
	{
		print_select_row($vbphrase['issue_type'], 'issuetypeid', $issuetype_options);
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $issuestatus['displayorder'], true, 5);
	print_yes_no_row($vbphrase['status_represents_completed_issue'], 'issuecompleted', $issuestatus['issuecompleted']);
	print_yes_no_row($vbphrase['can_create_petitions_from_this_status'], 'canpetitionfrom', $issuestatus['canpetitionfrom']);

	require_once(DIR . '/includes/adminfunctions_template.php');
	$colorPicker = construct_color_picker(11);

	print_label_row($vbphrase['status_color_dark_styles'], construct_status_color_row('statuscolor', $issuestatus['statuscolor'], 'bginput', 22, false));
	print_label_row($vbphrase['status_color_light_styles'], construct_status_color_row('statuscolor2', $issuestatus['statuscolor2'], 'bginput', 22, false));

	$projectsets = '';

	$projectsets_sql = $vbulletin->db->query_read("
		SELECT pt.projectid, pt.title_clean, IF(ptset.projectid IS NULL, 0, 1) AS selected
		FROM " . TABLE_PREFIX . "pt_project AS pt
		LEFT JOIN " . TABLE_PREFIX . "pt_issuestatusprojectset AS ptset ON
			(ptset.projectid = pt.projectid AND ptset.issuestatusid = " . intval($issuestatus['issuestatusid']) . ")
		ORDER BY pt.displayorder
	");

	while ($projectset = $vbulletin->db->fetch_array($projectsets_sql))
	{
		$projectsets .= "<div class=\"smallfont\"><label>"
			. "<input type=\"checkbox\" name=\"projectset[]\" value=\"$projectset[projectid]\" tabindex=\"1\"" . ($projectset['selected'] ? ' checked="checked"' : '') . " />"
			. htmlspecialchars_uni($projectset['title_clean']) . "</label></div>";
	}

	if ($projectsets)
	{
		print_label_row($vbphrase['use_selected_project_sets'], $projectsets, '', 'top', 'projectset');
	}

	construct_hidden_code('issuestatusid', $issuestatus['issuestatusid']);
	print_submit_row();

	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = "<?php echo $numcolors; ?>";
	var colorPickerWidth = 253;
	var colorPickerType = <?php echo intval($colorPickerType); ?>;

	//-->
	</script>
	<?php
}

// ########################################################################
if ($_POST['do'] == 'statuskill')
{
	$vbulletin->input->clean_gpc('p', 'deststatusid', TYPE_UINT);

	$issuestatus = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
	);

	if (!$issuestatus)
	{
		print_stop_message('invalid_action_specified');
	}

	$statusdata = datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);
	$statusdata->set_existing($issuestatus);
	$statusdata->set_info('delete_deststatusid', $vbulletin->GPC['deststatusid']);
	$statusdata->delete();

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('issue_status_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'statusdelete')
{
	$issuestatus = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuestatusid = " . $vbulletin->GPC['issuestatusid']
	);

	if (!$issuestatus)
	{
		print_stop_message('invalid_action_specified');
	}

	$statusdata = datamanager_init('Pt_IssueStatus', $vbulletin, ERRTYPE_CP);
	$statusdata->set_existing($issuestatus);
	$statusdata->pre_delete();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuetypeid = '" . $db->escape_string($issuestatus['issuetypeid']) . "'
			AND issuestatusid <> $issuestatus[issuestatusid]
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	print_delete_confirmation(
		'pt_issuestatus',
		$vbulletin->GPC['issuestatusid'],
		'projecttype',
		'statuskill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_status'] .
			'<select name="deststatusid">' . construct_select_options($statuses) . '</select>'
	);
}

// ########################################################################
/*if ($_POST['do'] == 'statusdisplayorder')
{
	$vbulletin->input->clean_gpc('p', 'order', TYPE_ARRAY_UINT);

	$case = '';

	foreach ($vbulletin->GPC['order'] AS $statusid => $displayorder)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				displayorder = CASE issuestatusid $case ELSE displayorder END
		");
	}

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('saved_display_order_successfully');
}*/

// ########################################################################
// ##################### ISSUE TYPE/STATUS MANAGEMENT #####################
// ########################################################################
if ($_POST['do'] == 'typeupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'exists' => TYPE_BOOL,

		'title_singular' => TYPE_STR,
		'title_plural' => TYPE_STR,
		'vote_question' => TYPE_STR,
		'vote_count_positive' => TYPE_STR,
		'vote_count_negative' => TYPE_STR,
		'applies_version' => TYPE_STR,
		'addressed_version' => TYPE_STR,
		'post_new_issue' => TYPE_STR,

		'displayorder' => TYPE_UINT,
		'iconfile' => TYPE_NOHTML,
		'permissionbase' => TYPE_NOHTML
	));

	$vbulletin->GPC['issuetypeid'] = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['issuetypeid']);

	if (empty($vbulletin->GPC['title_singular']) OR empty($vbulletin->GPC['title_plural']) OR empty($vbulletin->GPC['issuetypeid']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$typedata = datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['exists'])
	{
		$issuetype = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuetype
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
		");

		if (!$issuetype)
		{
			print_stop_message('invalid_action_specified');
		}

		$typedata->set_existing($issuetype);
	}
	else
	{
		$typedata->set('issuetypeid', $vbulletin->GPC['issuetypeid']);
	}

	$typedata->set('displayorder', $vbulletin->GPC['displayorder']);
	$typedata->set('iconfile', $vbulletin->GPC['iconfile']);

	$typedata->set_info('title_singular', $vbulletin->GPC['title_singular']);
	$typedata->set_info('title_plural', $vbulletin->GPC['title_plural']);
	$typedata->set_info('vote_question', $vbulletin->GPC['vote_question']);
	$typedata->set_info('vote_count_positive', $vbulletin->GPC['vote_count_positive']);
	$typedata->set_info('vote_count_negative', $vbulletin->GPC['vote_count_negative']);
	$typedata->set_info('applies_version', $vbulletin->GPC['applies_version']);
	$typedata->set_info('addressed_version', $vbulletin->GPC['addressed_version']);
	$typedata->set_info('post_new_issue', $vbulletin->GPC['post_new_issue']);

	$typedata->save();

	if (!$vbulletin->GPC['exists'] AND $vbulletin->GPC['permissionbase'])
	{
		$permissions = array();

		$permission_query = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectpermission
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['permissionbase']) . "'
		");

		while ($permission = $db->fetch_array($permission_query))
		{
			$permissions[] = "
				($permission[usergroupid], $permission[projectid], '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "',
				$permission[generalpermissions], $permission[postpermissions], $permission[attachpermissions])
			";
		}

		if ($permissions)
		{
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "pt_projectpermission
					(usergroupid, projectid, issuetypeid, generalpermissions, postpermissions, attachpermissions)
				VALUES
					" . implode(',', $permissions)
			);
		}
	}

	build_assignable_users();
	build_pt_user_list('pt_report_users', 'pt_report_user_cache');

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('issue_type_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'typeadd' OR $_REQUEST['do'] == 'typeedit')
{
	if ($vbulletin->GPC['issuetypeid'])
	{
		$issuetype = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuetype
			WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
		");

		$phrases = array();

		$phrase_data = $db->query_read("
			SELECT varname, text
			FROM " . TABLE_PREFIX . "phrase
			WHERE languageid = 0
				AND varname IN (
					'issuetype_$issuetype[issuetypeid]_singular',
					'issuetype_$issuetype[issuetypeid]_plural',
					'vote_question_$issuetype[issuetypeid]',
					'vote_count_positive_$issuetype[issuetypeid]',
					'vote_count_negative_$issuetype[issuetypeid]',
					'applies_version_$issuetype[issuetypeid]',
					'addressed_version_$issuetype[issuetypeid]',
					'post_new_issue_$issuetype[issuetypeid]'
				)
		");

		while ($phrase = $db->fetch_array($phrase_data))
		{
			$phrases["$phrase[varname]"] = $phrase['text'];
		}

		$issuetype['title_singular'] = $phrases["issuetype_$issuetype[issuetypeid]_singular"];
	}

	if (empty($issuetype))
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_issuetype
		");

		$issuetype = array(
			'issuetypeid' => '',
			'displayorder' => $maxorder['maxorder'] + 10,
			'title_singular' => '',
			'title_plural' => '',
		);

		$phrases = array(
			'issuetype__singular' => '',
			'issuetype__plural' => '',
			'vote_question_' => '',
			'vote_count_positive_' => '',
			'vote_count_negative_' => '',
			'post_new_issue_' =>''
		);
	}

	print_form_header('projecttype', 'typeupdate');

	if ($issuetype['issuetypeid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_type_x'], $issuetype['title_singular']));
		$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=projecttools&t=1&varname="; // has ID appended

		print_label_row($vbphrase['issue_type_key_alphanumeric_only'], $issuetype['issuetypeid']);
		construct_hidden_code('issuetypeid', $issuetype['issuetypeid']);
		construct_hidden_code('exists', 1);
	}
	else
	{
		print_table_header($vbphrase['add_issue_type']);
		$trans_link = '';

		print_input_row($vbphrase['issue_type_key_alphanumeric_only'], 'issuetypeid');
		construct_hidden_code('exists', 0);
	}

	print_input_row($vbphrase['display_order'], 'displayorder', $issuetype['displayorder'], true, 5);
	print_input_row($vbphrase['filename_for_icon'], 'iconfile', $issuetype['iconfile'], false);

	if (!$issuetype['issuetypeid'])
	{
		$types = array();
		$type_query = $db->query_read("
			SELECT issuetypeid
			FROM " . TABLE_PREFIX . "pt_issuetype
			ORDER BY displayorder
		");

		while ($type = $db->fetch_array($type_query))
		{
			$types["$type[issuetypeid]"] = $vbphrase["issuetype_$type[issuetypeid]_singular"];
		}

		print_select_row($vbphrase['base_permissions_off_existing_type'], 'permissionbase', array('' => $vbphrase['none_meta']) + $types);
	}

	print_description_row($vbphrase['phrases'], false, 2, 'thead', 'left', 'phrases');

	print_input_row(
		$vbphrase['singular_form_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "issuetype_$issuetype[issuetypeid]_singular", true) . '</dfn>' : ''),
		'title_singular',
		$phrases["issuetype_$issuetype[issuetypeid]_singular"]
	);

	print_input_row(
		$vbphrase['plural_form_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "issuetype_$issuetype[issuetypeid]_plural", true) . '</dfn>' : ''),
		'title_plural',
		$phrases["issuetype_$issuetype[issuetypeid]_plural"]
	);

	print_input_row(
		$vbphrase['vote_question_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_question_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_question',
		$phrases["vote_question_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['positive_vote_count_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_count_positive_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_count_positive',
		$phrases["vote_count_positive_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['negative_vote_count_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "vote_count_negative_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'vote_count_negative',
		$phrases["vote_count_negative_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['applicable_version_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "applies_version_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'applies_version',
		$phrases["applies_version_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['addressed_version_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "addressed_version_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'addressed_version',
		$phrases["addressed_version_$issuetype[issuetypeid]"]
	);

	print_input_row(
		$vbphrase['post_new_issue_example'] .
			($trans_link ? '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "post_new_issue_$issuetype[issuetypeid]", true) . '</dfn>' : ''),
		'post_new_issue',
		$phrases["post_new_issue_$issuetype[issuetypeid]"]
	);

	print_submit_row();

	if (!$issuetype['issuetypeid'])
	{
		echo '<p align="center" class="smallfont">' . $vbphrase['need_manually_select_projects_type'] . '</p>';
	}

}

// ########################################################################
if ($_POST['do'] == 'typekill')
{
	$vbulletin->input->clean_gpc('r', 'deststatusid', TYPE_UINT);

	$issuetype = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
	");

	if (!$issuetype)
	{
		print_stop_message('invalid_action_specified');
	}

	$typedata = datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);
	$typedata->set_existing($issuetype);
	$typedata->set_info('delete_deststatusid', $vbulletin->GPC['deststatusid']);
	$typedata->delete();

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('issue_type_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'typedelete')
{
	$issuetype = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		WHERE issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "'
	");

	if (!$issuetype)
	{
		print_stop_message('invalid_action_specified');
	}

	$typedata = datamanager_init('Pt_IssueType', $vbulletin, ERRTYPE_CP);
	$typedata->set_existing($issuetype);
	$typedata->pre_delete();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		WHERE issuetypeid <> '" . $db->escape_string($issuetype['issuetypeid']) . "'
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses[$vbphrase["issuetype_$status[issuetypeid]_singular"]]["$status[issuestatusid]"] = $vbphrase["issuestatus$status[issuestatusid]"];
	}

	print_delete_confirmation(
		'pt_issuetype',
		$vbulletin->GPC['issuetypeid'],
		'project',
		'typekill',
		'',
		0,
		$vbphrase['existing_affected_issues_updated_delete_select_status'] .
			'<select name="deststatusid">' . construct_select_options($statuses) . '</select>'
	);
}

// ########################################################################
if ($_POST['do'] == 'typedisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => TYPE_ARRAY_UINT,
		'issuecompleted' => TYPE_ARRAY_BOOL,
		'statuscolor' => TYPE_ARRAY_NOHTML,
		'statuscolor2' => TYPE_ARRAY_NOHTML
	));

	// Display order
	$case = '';

	foreach ($vbulletin->GPC['order'] AS $statusid => $displayorder)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . $displayorder;
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				displayorder = CASE issuestatusid $case ELSE displayorder END
		");
	}

	// Issue completed
	$case = '';

	foreach ($vbulletin->GPC['issuecompleted'] AS $statusid => $issuecompleted)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN " . ($issuecompleted ? 1 : 0);
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				issuecompleted = CASE issuestatusid $case ELSE 0 END
		");
	}

	// Status color for dark styles
	$case = '';

	foreach ($vbulletin->GPC['statuscolor'] AS $statusid => $colorvalue)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN '" . $colorvalue . "'";
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				statuscolor = CASE issuestatusid $case ELSE 0 END
		");
	}

	// Status color for light styles
	$case = '';

	foreach ($vbulletin->GPC['statuscolor2'] AS $statusid => $colorvalue)
	{
		$case .= "\nWHEN " . intval($statusid) . " THEN '" . $colorvalue . "'";
	}

	if ($case)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuestatus SET
				statuscolor2 = CASE issuestatusid $case ELSE 0 END
		");
	}

	build_issue_type_cache();
	rebuild_project_counters(false);
	rebuild_milestone_counters(false);

	define('CP_REDIRECT', 'projecttype.php?do=typelist');
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'typelist')
{
	$vbulletin->input->clean_gpc('r', 'colorPickerType', TYPE_INT);

	print_form_header('', '');
	print_table_header($vbphrase['issue_type_manager']);
	print_description_row(
		'<a href="#" onclick="js_open_help(\'projecttype\', \'typelist\', \'\'); return false;">[' . $vbphrase['help'] . ']</a> | ' . construct_link_code($vbphrase['add_issue_type'], 'projecttype.php?do=typeadd'),
		false, 2, '', 'center'
	);
	print_table_footer();

	$statuses = array();

	$status_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuestatus
		ORDER BY displayorder
	");

	while ($status = $db->fetch_array($status_data))
	{
		$statuses["$status[issuetypeid]"][] = $status;
	}

	$types = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issuetype
		ORDER BY displayorder
	");

	?>
	<script type="text/javascript">
	<!--
	<?php
	foreach (array(
		'css_value_invalid',
		'color_picker_not_ready',
	) AS $phrasename)
	{
			$JS_PHRASES[] = "\"$phrasename\" : \"" . fetch_js_safe_string($vbphrase["$phrasename"]) . "\"";
	}
	?>

	var vbphrase = {
		<?php echo implode(",\r\n\t", $JS_PHRASES) . "\r\n"; ?>
	};
	//-->
	</script>
	<?php

	echo '<script type="text/javascript" src="../clientscript/vbulletin_cpcolorpicker.js"></script>';

	print_form_header('projecttype', 'typedisplayorder');

	$firstpass = true;

	while ($type = $db->fetch_array($types))
	{
		print_cells_row(array(
			$vbphrase['issue_type'] . ' <b>' . $vbphrase["issuetype_$type[issuetypeid]_plural"] . '</b>',
			'&nbsp;',
			'&nbsp;',
			'&nbsp;',
			'&nbsp;',
			'<b>' .
				construct_link_code($vbphrase['edit'], 'projecttype.php?do=typeedit&amp;issuetypeid=' . $type['issuetypeid']) .
				construct_link_code($vbphrase['delete'], 'projecttype.php?do=typedelete&amp;issuetypeid=' . $type['issuetypeid']) .
			'</b>'
		), false, 'tcat');

		print_cells_row(array(
			'<span class="normal">' . $vbphrase['ptstatus'] . '</span>',
			'<span class="normal">' . $vbphrase['display_order'] . '</span>',
			'<span class="normal">' . $vbphrase['issue_completed'] . '</span>',
			'<span class="normal">' . $vbphrase['status_color_dark_styles'] . '</span>',
			'<span class="normal">' . $vbphrase['status_color_light_styles'] . '</span>',
			'<b>' . construct_link_code($vbphrase['add_status'], 'projecttype.php?do=statusadd&amp;type=' . $type['issuetypeid']) . '</b>'
		), true);

		if (!empty($statuses["$type[issuetypeid]"]))
		{
			foreach ($statuses["$type[issuetypeid]"] AS $status)
			{
				require_once(DIR . '/includes/adminfunctions_template.php');
				$colorPicker = construct_color_picker(11);

				print_cells_row(array(
					$vbphrase['issuestatus' . $status['issuestatusid'] . ''],
					'<input type="text" class="bginput" name="order[' . $status['issuestatusid'] . ']" value="' . $status['displayorder'] . '" tabindex="1" size="3" />',
					'<input type="checkbox" name="issuecompleted[' . $status['issuestatusid'] . ']" value="1" ' . ($status['issuecompleted'] ? 'checked="checked"' : '') . ' />',
					construct_status_color_row('statuscolor[' . $status['issuestatusid'] . ']', $status['statuscolor'], 'bginput', 22, false),
					construct_status_color_row('statuscolor2[' . $status['issuestatusid'] . ']', $status['statuscolor2'], 'bginput', 22, false),
					"<div align=\"" . vB_Template_Runtime::fetchStyleVar('right') . "\" class=\"smallfont\">" .
						construct_link_code($vbphrase['edit'], 'projecttype.php?do=statusedit&amp;issuestatusid=' . $status['issuestatusid']) .
						construct_link_code($vbphrase['delete'], 'projecttype.php?do=statusdelete&amp;issuestatusid=' . $status['issuestatusid']) .
					'</div>'
				));
			}
		}
		else
		{
			print_description_row(construct_phrase($vbphrase['no_statuses_of_this_type_defined_click_here_to_add'], $type['issuetypeid']), false, 6, '', 'center');
		}
	}

	print_submit_row($vbphrase['save_changes'], '', 6);

	echo $colorPicker;

	?>
	<script type="text/javascript">
	<!--

	var bburl = "<?php echo $vbulletin->options['bburl']; ?>/";
	var cpstylefolder = "<?php echo $vbulletin->options['cpstylefolder']; ?>";
	var numColors = <?php echo intval($numcolors); ?>;
	var colorPickerWidth = 253;
	var colorPickerType = <?php echo intval($vbulletin->GPC['colorPickerType']); ?>;

	//-->
	</script>
	<?php
}

print_cp_footer();

?>