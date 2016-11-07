<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
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
	$_REQUEST['do'] = 'counters';
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
// ######################### GENERAL MANAGEMENT ###########################
// ########################################################################
if ($_REQUEST['do'] == 'counters')
{
	print_form_header('projectcounters', 'issuecounters');
	print_table_header($vbphrase['rebuild_issue_counters']);
	print_description_row($vbphrase['rebuilding_issue_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('projectcounters', 'projectcounters');
	print_table_header($vbphrase['rebuild_project_counters']);
	print_description_row($vbphrase['rebuilding_project_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('projectcounters', 'milestonecounters');
	print_table_header($vbphrase['rebuild_milestone_counters']);
	print_description_row($vbphrase['rebuilding_milestone_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	print_form_header('projectcounters', 'profileissuecounters');
	print_table_header($vbphrase['rebuild_profile_issue_counters']);
	print_description_row($vbphrase['rebuilding_profile_issue_counters_will_update_various_fields']);
	print_submit_row($vbphrase['go'], '');

	// Do the check for 4.2.0+ only
	if (version_compare($vbulletin->options['templateversion'], '4.2.0', '>='))
	{
		// First, get the packageid of Project Tools from database
		$packageid = vB_Types::instance()->getPackageId('vBProjectTools');

		// Check if the packageid exists in the activitystreamtype table
		$upgrade42 = $db->query_read("
			SELECT type
			FROM " . TABLE_PREFIX . "activitystreamtype
			WHERE packageid = " . intval($packageid) . "
		");

		if ($db->num_rows($upgrade42) == 0)
		{
			print_form_header('projectcounters', 'upgrade42');
			print_table_header($vbphrase['upgrade_script_42_title']);
			print_description_row($vbphrase['upgrade_script_42_description']);
			print_submit_row($vbphrase['go'], '');
		}
	}
}

// ########################################################################
if ($_REQUEST['do'] == 'issuecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	$vbulletin->input->clean_gpc('r', 'start', TYPE_UINT);
	$perpage = 250;

	$issues = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_issue
		LIMIT " . $vbulletin->GPC['start'] . ", $perpage
	");

	$haveissues = false;

	while ($issue = $db->fetch_array($issues))
	{
		$haveissues = true;
		$issuedata = datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_SILENT);
		$issuedata->set_existing($issue);
		$issuedata->rebuild_issue_counters();
		$issuedata->save();
		unset($issuedata);

		echo ' . '; vbflush();
	}

	if ($haveissues)
	{
		print_cp_redirect('project.php?do=issuecounters&start=' . ($vbulletin->GPC['start'] + $perpage));
	}
	else
	{
		define('CP_REDIRECT', 'projectcounters.php?do=counters');
		print_stop_message('counters_rebuilt');
	}
}

// ########################################################################
if ($_POST['do'] == 'projectcounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_project_counters(true);

	define('CP_REDIRECT', 'projectcounters.php?do=counters');
	print_stop_message('counters_rebuilt');
}

// ########################################################################
if ($_REQUEST['do'] == 'milestonecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_milestone_counters(true);

	define('CP_REDIRECT', 'projectcounters.php?do=counters');
	print_stop_message('counters_rebuilt');
}

// ########################################################################
if ($_REQUEST['do'] == 'profileissuecounters')
{
	@set_time_limit(0);
	ignore_user_abort(1);

	rebuild_profile_issue_counters();

	define('CP_REDIRECT', 'projectcounters.php?do=counters');
	print_stop_message('counters_rebuilt');
}

// ########################################################################
if ($_POST['do'] == 'upgrade42')
{
	// Package ID required
	$packageid = vB_Types::instance()->getPackageID('vBProjectTools');

	// Insert Issue in Activity Stream
	vB::$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "activitystreamtype
			(packageid, section, type, enabled)
		VALUES
			(" . intval($packageid) . ", 'project', 'issue', 1)
	");

	// Insert IssueNote in Activity Stream
	vB::$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "activitystreamtype
			(packageid, section, type, enabled)
		VALUES
			(" . intval($packageid) . ", 'project', 'issuenote', 1)
	");

	// Rebuild Activity Stream datastore
	build_activitystream_datastore();
}

print_cp_footer();

?>