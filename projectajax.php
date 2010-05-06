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
if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/includes/class_xml.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('p', array(
	'issueid' => TYPE_UINT,
	'field' => TYPE_NOHTML,
	'value' => TYPE_NOCLEAN // might be an array, might be a scalar
));

if (is_string($vbulletin->GPC['value']))
{
	$vbulletin->GPC['value'] = convert_urlencoded_unicode($vbulletin->GPC['value']);
}

$issue = verify_issue($vbulletin->GPC['issueid']);

$project = verify_project($issue['projectid']);
verify_issuetypeid($issue['issuetypeid'], $project['projectid']);

$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
$issueperms = $projectperms["$issue[issuetypeid]"];
$posting_perms = prepare_issue_posting_pemissions($issue, $issueperms);

$can_edit_issue = $posting_perms['issue_edit'];

($hook = vBulletinHook::fetch_hook('projectajax_start')) ? eval($hook) : false;

// #######################################################################

function throw_ajax_error($text = '')
{
	global $vbulletin;

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_tag('error', $text);
	$xml->print_xml();
}

// #######################################################################
if ($_POST['do'] == 'save')
{
	$issuedata =& datamanager_init('Pt_Issue', $vbulletin, ERRTYPE_STANDARD);
	$issuedata->set_existing($issue);

	($hook = vBulletinHook::fetch_hook('projectajax_save_start')) ? eval($hook) : false;

	switch ($vbulletin->GPC['field'])
	{
		// #### SIMPLE SELECTS ####
		case 'issuestatusid':
			if (!$posting_perms['status_edit'])
			{
				throw_ajax_error('');
			}

			$issuedata->set('issuestatusid', $vbulletin->GPC['value']);
			break;

		case 'priority':
			if (!$can_edit_issue)
			{
				throw_ajax_error('');
			}

			$issuedata->set('priority', $vbulletin->GPC['value']);
			break;

		case 'projectcategoryid':
			if (!$can_edit_issue)
			{
				throw_ajax_error('');
			}

			$issuedata->set('projectcategoryid', $vbulletin->GPC['value']);
			break;

		case 'appliesversionid':
			if (!$can_edit_issue)
			{
				throw_ajax_error('');
			}

			$issuedata->set('appliesversionid', $vbulletin->GPC['value']);
			break;

		case 'addressedversionid':
			if (!$posting_perms['status_edit'])
			{
				throw_ajax_error('');
			}

			switch ($vbulletin->GPC['value'])
			{
				case -1:
					$issuedata->set('isaddressed', 1);
					$issuedata->set('addressedversionid', 0);
					break;

				case 0:
					$issuedata->set('isaddressed', 0);
					$issuedata->set('addressedversionid', 0);
					break;

				default:
					$issuedata->set('isaddressed', 1);
					$issuedata->set('addressedversionid', $vbulletin->GPC['value']);
					break;
			}
			break;

		// ########### MILESTONE ###########
		case 'milestoneid':
			if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'])
				OR !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canchangemilestone'])
			)
			{
				throw_ajax_error('');
			}

			$issuedata->set('milestoneid', $vbulletin->GPC['value']);
			break;

		// #### COMPLEX MULTI SELECTS ####
		case 'tags':
			if (!$posting_perms['tags_edit'])
			{
				throw_ajax_error('');
			}

			$vbulletin->input->clean_array_gpc('p', array(
				'value' => TYPE_ARRAY_NOHTML
			));

			foreach ($vbulletin->GPC['value'] AS $key => $value)
			{
				$vbulletin->GPC['value']["$key"] = convert_urlencoded_unicode($value);
			}

			$issuedata->set_info('allow_tag_creation', $posting_perms['can_custom_tag']);

			// existing tags
			$existing_tags = array();
			$tag_data = $db->query_read("
				SELECT tag.tagtext
				FROM " . TABLE_PREFIX . "pt_issuetag AS issuetag
				INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (issuetag.tagid = tag.tagid)
				WHERE issuetag.issueid = $issue[issueid]
				ORDER BY tag.tagtext
			");
			while ($tag = $db->fetch_array($tag_data))
			{
				$existing_tags[] = $tag['tagtext'];
			}

			$tag_add = array_diff($vbulletin->GPC['value'], $existing_tags);
			$tag_remove = array_diff($existing_tags, $vbulletin->GPC['value']);

			foreach ($tag_add AS $tag)
			{
				$issuedata->add_tag($tag);
			}

			foreach ($tag_remove AS $tag)
			{
				$issuedata->remove_tag($tag);
			}
			break;

		case 'assignusers':
			if (!$posting_perms['assign_dropdown'])
			{
				throw_ajax_error('');
			}

			$vbulletin->input->clean_array_gpc('p', array(
				'value' => TYPE_ARRAY_NOHTML
			));

			$vbulletin->GPC['value'] = array_keys($vbulletin->GPC['value']);

			// existing assignments
			$existing_assignments = array();
			$assignment_data = $db->query_read("
				SELECT user.userid
				FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
				WHERE issueassign.issueid = $issue[issueid]
				ORDER BY user.username
			");
			while ($assignment = $db->fetch_array($assignment_data))
			{
				$existing_assignments["$assignment[userid]"] = $assignment['userid'];
			}

			$assign_add = array_diff($vbulletin->GPC['value'], $existing_assignments);
			$assign_remove = array_diff($existing_assignments, $vbulletin->GPC['value']);

			foreach ($assign_add AS $userid)
			{
				if (!isset($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"]["$userid"]))
				{
					// user cannot be assigned
					continue;
				}

				$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
				$assign->set_info('project', $project);
				$assign->set('userid', $userid);
				$assign->set('issueid', $issue['issueid']);
				$assign->save();
			}

			foreach ($assign_remove AS $userid)
			{
				$data = array('userid' => $userid, 'issueid' => $issue['issueid']);
				$assign =& datamanager_init('Pt_IssueAssign', $vbulletin, ERRTYPE_SILENT);
				$assign->set_existing($data);
				$assign->delete();
			}
			break;
	}

	$issuedata->save();
	$issue = verify_issue($issue['issueid']);

	$_POST['do'] = 'fetch';
}

// #######################################################################
if ($_POST['do'] == 'fetch')
{
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('ajax');

	($hook = vBulletinHook::fetch_hook('projectajax_fetch_start')) ? eval($hook) : false;

	switch ($vbulletin->GPC['field'])
	{
	// #### SIMPLE SELECTS ####
		// ########### ISSUE STATUS ID ###########
		case 'issuestatusid':
			if (!$posting_perms['status_edit'])
			{
				$xml->add_tag('error', '');
			}
			else
			{
				// find all the statuses for this issue's type
				$xml->add_group('items');
				foreach ($vbulletin->pt_issuetype["$issue[issuetypeid]"]['statuses'] AS $status)
				{
					$xml->add_tag('item', $vbphrase["issuestatus$status[issuestatusid]"], array(
						'itemid' => $status['issuestatusid'],
						'selected' => ($issue['issuestatusid'] == $status['issuestatusid'] ? 'yes' : 'no')
					));
				}
				$xml->close_group();
			}
			break;

		// ########### PRIORITY ###########
		case 'priority':
			if (!$can_edit_issue)
			{
				$xml->add_tag('error', '');
			}
			else
			{
				$xml->add_group('items');
					for ($i = 0; $i <= 10; $i++)
					{
						$xml->add_tag('item', $vbphrase["priority_$i"], array(
							'itemid' => $i,
							'selected' => ($issue['priority'] == $i ? 'yes' : 'no')
						));
					}
				$xml->close_group();
			}
			break;

		// ########### PROJECT CATEGORY ID ###########
		case 'projectcategoryid':
			if (!$can_edit_issue)
			{
				$xml->add_tag('error', '');
			}
			else
			{
				$xml->add_group('items');
				$xml->add_tag('item', $vbphrase['unknown'], array('itemid' => 0, 'selected' => ($issue['projectcategoryid'] == 0 ? 'yes' : 'no')));

				foreach ($vbulletin->pt_categories AS $category)
				{
					if ($category['projectid'] != $issue['projectid'])
					{
						continue;
					}
					$xml->add_tag('item', $category['title'], array(
						'itemid' => $category['projectcategoryid'],
						'selected' => ($issue['projectcategoryid'] == $category['projectcategoryid'] ? 'yes' : 'no')
					));
				}
				$xml->close_group();
			}
			break;

		// ########### APPLIES AND ADDRESSED VERSIONS ###########
		case 'appliesversionid':
		case 'addressedversionid':
			if (!$posting_perms['status_edit'] AND $vbulletin->GPC['field'] == 'addressedversionid')
			{
				$xml->add_tag('error', '');
			}
			else
			{
				// group versions as necessary, ordered by their effective order
				$version_groups = array();
				$version_query = $db->query_read("
					SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
					FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
					INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
						(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
					WHERE projectversion.projectid = $project[projectid]
					ORDER BY projectversion.effectiveorder DESC
				");
				while ($version = $db->fetch_array($version_query))
				{
					$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
				}

				$xml->add_group('items');

				// add the starting/non-version options
				if ($vbulletin->GPC['field'] == 'appliesversionid')
				{
					$xml->add_tag('item', $vbphrase['unknown'], array('itemid' => 0, 'selected' => ($issue['appliesversionid'] == 0 ? 'yes' : 'no')));
				}
				else
				{
					$xml->add_tag('item', $vbphrase['none_meta'], array('itemid' => 0, 'selected' => ($issue['isaddressed'] == 0 ? 'yes' : 'no')));
					$xml->add_tag('item', $vbphrase['next_release'], array(
						'itemid' => -1,
						'selected' => (($issue['isaddressed'] == 1 AND $issue['addressedversionid'] == 0) ? 'yes' : 'no')
					));
				}

				// search the groups
				foreach ($version_groups AS $label => $versions)
				{
					$xml->add_group('itemgroup', array('label' => $label));

					// then the versions in them
					foreach ($versions AS $versionid => $versionname)
					{
						$xml->add_tag('item', $versionname, array(
							'itemid' => $versionid,
							'selected' => ($issue[$vbulletin->GPC['field']] == $versionid ? 'yes' : 'no')
						));
					}

					$xml->close_group();
				}

				$xml->close_group();
			}
			break;

		// ########### MILESTONE ###########
		case 'milestoneid':
			if (!($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewmilestone'])
				OR !($issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['canchangemilestone'])
			)
			{
				$xml->add_tag('error', '');
			}
			else
			{

				$xml->add_group('items');

				require_once(DIR . '/includes/functions_pt_posting.php');
				$milestones = fetch_milestone_select_list($project['projectid']);

				foreach ($milestones AS $milestone_key => $milestone_container)
				{
					if (!is_array($milestone_container))
					{
						$xml->add_tag('item', $milestone_container, array(
							'itemid' => $milestone_key,
							'selected' => ($issue['milestoneid'] == $milestone_key ? 'yes' : 'no')
						));
					}
					else if (!empty($milestone_container))
					{
						$xml->add_group('itemgroup', array('label' => $milestone_key));

						foreach ($milestone_container AS $milestoneid => $milestone_title)
						{
							$xml->add_tag('item', $milestone_title, array(
								'itemid' => $milestoneid,
								'selected' => ($issue['milestoneid'] == $milestoneid ? 'yes' : 'no')
							));
						}

						$xml->close_group();
					}
				}

				$xml->close_group();
			}
			break;

	// #### COMPLEX MULTI SELECTS ####
		// ########### TAGS ###########
		case 'tags':
			if (!$posting_perms['tags_edit'])
			{
				$xml->add_tag('error', '');
			}
			else
			{
				$xml->add_tag('createnewitem', $posting_perms['can_custom_tag'] ? 'yes' : 'no');

				$tag_data = $db->query_read("
					SELECT tag.tagtext, IF(issuetag.tagid IS NOT NULL, 1, 0) AS isapplied
					FROM " . TABLE_PREFIX . "pt_tag AS tag
					LEFT JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag ON (issuetag.tagid = tag.tagid AND issuetag.issueid = $issue[issueid])
					ORDER BY tag.tagtext
				");

				$unselected = array();
				$selected = array();
				while ($tag = $db->fetch_array($tag_data))
				{
					if ($tag['isapplied'])
					{
						$selected[] = $tag['tagtext'];
					}
					else
					{
						$unselected[] = $tag['tagtext'];
					}
				}

				$xml->add_group('n');
				foreach ($unselected AS $tag)
				{
					$xml->add_tag('item', $tag, array('itemid' => $tag));
				}
				$xml->close_group();

				$xml->add_group('y');
				foreach ($selected AS $tag)
				{
					$xml->add_tag('item', $tag, array('itemid' => $tag));
				}
				$xml->close_group();

				$field = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['field']);
				$templater = vB_Template::create('pt_listbuilder_box');
					$templater->register('field', $field);
				$template = $templater->render();
				$xml->add_tag('template', $template);

				$xml->add_tag('noneword', $vbphrase['none_meta']);
			}
			break;

		// ########### ASSIGNED USERS ###########
		case 'assignusers':
			if (!$posting_perms['assign_dropdown'])
			{
				$xml->add_tag('error', '');
			}
			else
			{
				$xml->add_tag('createnewitem', 'no');

				// assignments
				$assigned_user_list = array();
				$assignment_data = $db->query_read("
					SELECT user.userid
					FROM " . TABLE_PREFIX . "pt_issueassign AS issueassign
					INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issueassign.userid)
					WHERE issueassign.issueid = $issue[issueid]
					ORDER BY user.username
				");
				while ($assignment = $db->fetch_array($assignment_data))
				{
					$assigned_user_list["$assignment[userid]"] = $assignment['userid'];
				}

				$unselected = array();
				$selected = array();
				foreach ($vbulletin->pt_assignable["$project[projectid]"]["$issue[issuetypeid]"] AS $userid => $username)
				{
					if (isset($assigned_user_list["$userid"]))
					{
						$selected["$userid"] = $username;
					}
					else
					{
						$unselected["$userid"] = $username;
					}
				}

				$xml->add_group('n');
				foreach ($unselected AS $userid => $username)
				{
					$xml->add_tag('item', $username, array('itemid' => $userid));
				}
				$xml->close_group();

				$xml->add_group('y');
				foreach ($selected AS $userid => $username)
				{
					$xml->add_tag('item', $username, array('itemid' => $userid));
				}
				$xml->close_group();

				$field = preg_replace('#[^a-z0-9_]#i', '', $vbulletin->GPC['field']);
				$templater = vB_Template::create('pt_listbuilder_box');
					$templater->register('field', $field);
				$template = $templater->render();
				$xml->add_tag('template', $template);

				$xml->add_tag('noneword', $vbphrase['none_meta']);
			}
			break;
		// #### END SWITCH ####
	}

	$xml->close_group();
	$xml->print_xml();
}
?>
