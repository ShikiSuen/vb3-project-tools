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

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'projectajax');
define('CSRF_PROTECTION', true);
define('PROJECT_SCRIPT', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('projecttools');

// get special data templates from the datastore
$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
	'pt_issuestatus',
	'pt_issuetype',
	'pt_assignable',
	'pt_projects',
	'pt_categories',
	'pt_versions',
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'pt_listbuilder_box'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./packages/vbprojecttools/api_exception.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

try
{
	if(file_exists('packages/vbprojecttools/api.php'))
	{
		require('packages/vbprojecttools/api.php');
	}
	else
	{
		throw new vBPT_Api_Exception('API not Present');
	}
	
	if($_SERVER['HTTP_METHOD'] != 'POST')
	{
		throw new vBPT_Api_Exception('Method available via POST only.');
	}
	
	if(!isset($_POST['api_key']) || !isset($vbulletin->options['vbpt_privateAPIkey']))
	{
		throw new vBPT_Api_Exception('API keys missing');
	}
	
	$api = new vBPT_Api($_POST['api_key']);
	
	//split route (projectapi.php?info/project/1) call $api->actionInfoProject(1);
}
catch(vBPT_Api_Exception $e)
{
	die($e->displayError());
}
finally
{
	die($api->displayResponse());
}
?>