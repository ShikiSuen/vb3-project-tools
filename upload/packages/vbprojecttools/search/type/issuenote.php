<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

/**
 * @package vBulletin Project Tools
 * @subpackage Search
 * @author $Author$
 * @version $Revision$
 * @since $Date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

require_once(DIR . '/vb/search/type.php');
require_once(DIR . '/includes/functions_projecttools.php');
require_once(DIR . '/packages/vbprojecttools/search/result/issuenote.php');
require_once(DIR . '/packages/vbprojecttools/search/result/issue.php');

/**
* There is a type file for each search type. This is the one for issue notes
*
* @package vBulletin Project Tools
* @subpackage Search
*/
class vBProjectTools_Search_Type_IssueNote extends vB_Search_Type
{
	/**
	* This checks to see if we can view this project.
	*
	* @param	integer		$projectid
	*
	* @return	boolean
	**/
	private function verify_project_canread($projectid)
	{
		global $vbulletin;

		$datastores = $vbulletin->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ('pt_bitfields', 'pt_permissions', 'pt_issuestatus', 'pt_issuetype', 'pt_projects', 'pt_categories', 'pt_assignable', 'pt_versions')
		");

		while ($datastore = $vbulletin->db->fetch_array($datastores))
		{
			$title = $datastore['title'];

			if (!is_array($datastore['data']))
			{
				$data = unserialize($datastore['data']);

				if (is_array($data))
				{
					$vbulletin->$title = $data;
				}
			}
			else if ($datastore['data'] != '')
			{
				$vbulletin->$title = $datastore['data'];
			}
		}

		$permissions = fetch_project_permissions($vbulletin->userinfo, $projectid);

		// We get an array, like 'type=>array('perm_type' => 65555,...), ...
		// the types are the three (currently at least) issue types. perm_types
		// are currently generalpermissions, postpermissions, attachpermissions
		// I would say that if we have rights to one of the three issue types we
		// can view the project.

		if ($vbulletin->userinfo['projectpermissions'])
		{
			foreach ($vbulletin->userinfo['projectpermissions'] AS $pjid => $tasks)
			{
				foreach ($tasks AS $key => $value)
				{
					if ($value['generalpermissions'] > 0 OR $value['postpermissions'] > 0)
					{
						return true;
					}
				}
			}
		}

		/*if ($permissions)
		{
			foreach ($permissions AS $type => $permission)
			{
				if ($permission['projectpermissions']["$projectid"] > 0)
				{
					return true;
				}
			}
		}*/
		//If we got here we aren't authorized
		return false;
	}
	
	/**
	* When displaying results we get passed a list of id's. This
	* function determines which are viewable by the user.
	*
	* @param	object	ID of the user
	* @param	array	Issue id's returned from a search
	* @param	array	Project id's for the issues
	*
	* @return	array	Array of viewable issues, array of rejected projects
	*/
	public function fetch_validated_list($user, $ids, $gids)
	{
		global $vbulletin;

		$map = array();

		foreach ($ids AS $i => $id)
		{
			$map[$gids[$i]][] = $id;
		}

		$projects = array_unique($gids);
		$rejected_projects = array();

		foreach ($projects AS $projectid)
		{
			if (!$this->verify_project_canread($projectid))
			{
				$rejected_groups[] = $projectid;
			}
		}

		if (count($ids))
		{
			foreach ($ids AS $id => $issueid)
			{
				if ($issue = verify_issue($issueid, false))
				{
					$list[$issueid] = vBProjectTools_Search_Result_IssueNote::create($issueid);
				}
			}
		}

		return array('list' => $list, 'groups_rejected' => $rejected_groups);
	}

	/**
	* Each search type has some responsibilities, one of which is to give
	* its display name.
	*
	* @return string
	*/
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_issuenotes');
	}

	/**
	* This is how the type objects are created
	*
	* @param	integer		$id
	*
	* @return 	vBProjectTools_Search_Type_IssueNote	object
	*/
	public function create_item($id)
	{
		return vBProjectTools_Search_Result_IssueNote::create($id);
	}

	/**
	* Each search type has some responsibilities, one of which is to tell
	* what the default search preferences are.
	*
	* @return array
	*/
	public function additional_pref_defaults()
	{
		
		return array(
			'type'				=> 0,
			'status'			=> 0,
			'priority'			=> 0,
			'query'				=> '',
			'searchuser'		=> '',
			'exactname'			=> '',
			'searchdate'		=> 0,
			'beforeafter'		=> 0,
			'sortby'			=> 'dateline',
			'order'				=> 'descending',
			'tag'				=> '',
			'showposts'	 		=> 0
		);
		
//		query, replycount, votecount, needsattachments, needspendingpetitions,
//			milestoneid, projectcategoryid, appliesversion, addressedversion,
//			searchuser, exactname, userissuesonly,
//			textlocation, priority_type,priority, searchdate, beforeafter,
//			replycount_type, votecount_type, votecount_posneg,
//			sort, sortorder
			
	}

	/**
	* Each search type has some responsibilities, one of which is to tell
	* whether it is groupable - Forums, for example are not, but posts are.
	* They are naturally grouped by thread.
	*
	* @return
	*/
	public function can_group()
	{
		return true;
	}

	/**
	* Each search type has some responsibilities, one of which is to tell
	* whether it is grouped by default
	*
	* @return
	*/
	public function group_by_default()
	{
		return true;
	}

	/**
	* This function generates the search elements for the user to search for issue notes
	*
	* @param	mixed		Array of user preferences
	* @param	mixed		Content type for which we are going to search
	* @param	array		Any additional elements to be registered. These are just passed to the template
	* @param	string		Name of the template to use for display. We have a default template.
	*
	* @return 	mixed		Complete html for the search elements
	*/
	public function listUi($prefs = null, $contenttypeid = null, $registers = null, $template_name = null)
	{
		global $vbulletin, $vbphrase, $show;

		if (!isset($contenttypeid))
		{
			$contenttypeid = $this->get_contenttypeid();
		}

		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('projecttools'));

		require_once(DIR . '/includes/functions_projecttools.php');
		require_once(DIR . '/includes/functions_pt_search.php');

		$vbulletin->input->clean_array_gpc('r', array(
			'projectid'   => TYPE_UINT,
			'milestoneid' => TYPE_UINT,
			'issuetypeid' => TYPE_NOHTML
		));

		$datastores = $vbulletin->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN('pt_bitfields', 'pt_permissions', 'pt_issuestatus', 'pt_issuetype', 'pt_projects', 'pt_categories', 'pt_assignable', 'pt_versions')
		");

		while ($datastore = $vbulletin->db->fetch_array($datastores))
		{
			$title = $datastore['title'];

			if (!is_array($datastore['data']))
			{
				$data = unserialize($datastore['data']);

				if (is_array($data))
				{
					$vbulletin->$title = $data;
				}
			}
			else if ($datastore['data'] != '')
			{
				$vbulletin->$title = $datastore['data'];
			}
		}

		if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
		{
			print_no_permission();
		}

		($hook = vBulletinHook::fetch_hook('projectsearch_form_start')) ? eval($hook) : false;

		$limit_shown_projectid = null;

		if ($vbulletin->GPC['milestoneid'])
		{
			require_once(DIR . '/includes/functions_pt_milestone.php');

			$milestone = $vbulletin->db->query_first("
				SELECT milestone.*, project.title_clean AS project_title
				FROM " . TABLE_PREFIX . "pt_milestone AS milestone
				INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = milestone.projectid)
				WHERE milestone.milestoneid = " . $vbulletin->GPC['milestoneid']
			);

			if (!$milestone)
			{
				standard_error(fetch_error('invalidid', $vbphrase['milestone'], $vbulletin->options['contactuslink']));
			}

			$projectperms = fetch_project_permissions($vbulletin->userinfo, $milestone['projectid']);

			$milestone_types = fetch_viewable_milestone_types($projectperms);

			if (!$milestone_types)
			{
				print_no_permission();
			}

			$limit_shown_projectid = $milestone['projectid'];
			$vbulletin->GPC['projectid'] = $milestone['projectid'];
		}

		// cache for project names - [projectid] = title_clean
		$project_names = array();

		// project drop down
		$projects = $vbulletin->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_project
			ORDER BY displayorder
		");

		$project_options = '';
		while ($project = $vbulletin->db->fetch_array($projects))
		{
			if (!isset($search_perms["$project[projectid]"]))
			{
				// can't search or view
				continue;
			}

			// add name to project name cache
			$project_names["$project[projectid]"] = $project['title_clean'];

			$optionname = 'projectid[]';
			$optionvalue = $project['projectid'];
			$optiontitle = $project['title_clean'];
			$optionid = "project_$project[projectid]";
			$optionchecked = ($project['projectid'] == $vbulletin->GPC['projectid'] ? ' checked="checked"' : '');

			if ($limit_shown_projectid)
			{
				if ($limit_shown_projectid != $project['projectid'])
				{
					$templater = vB_Template::create('pt_checkbox_option_hidden');
						$templater->register('optionchecked', $optionchecked);
						$templater->register('optionid', $optionid);
						$templater->register('optionname', $optionname);
						$templater->register('optiontitle', $optiontitle);
						$templater->register('optionvalue', $optionvalue);
					$project_options .= $templater->render();
					continue;
				}
				else
				{
					$optionchecked .= ' disabled="disabled"';
				}
			}

			$templater = vB_Template::create('pt_checkbox_option');
				$templater->register('optionchecked', $optionchecked);
				$templater->register('optionid', $optionid);
				$templater->register('optionname', $optionname);
				$templater->register('optiontitle', $optiontitle);
				$templater->register('optionvalue', $optionvalue);
			$project_options .= $templater->render();
		}

		$optionchecked = '';

		// assigned user drop down
		$assign_list = array();
		foreach ($vbulletin->pt_assignable AS $types)
		{
			foreach ($types AS $type)
			{
				$assign_list += $type;
			}
		}
		asort($assign_list);

		$assignable_users = array('col1' => '', 'col2' => '');
		$col_count = ceil(sizeof($assign_list) / 2);
		$i = 0;
		$colid = 'col1';
		foreach ($assign_list AS $optionvalue => $optiontitle)
		{
			$optionname = 'assigneduser[]';
			$optionid = "assigneduser_$optionvalue";

			$templater = vB_Template::create('pt_checkbox_option');
				$templater->register('optionchecked', $optionchecked);
				$templater->register('optionid', $optionid);
				$templater->register('optionname', $optionname);
				$templater->register('optiontitle', $optiontitle);
				$templater->register('optionvalue', $optionvalue);
			$assignable_users[$colid] .= $templater->render();

			if (++$i >= $col_count)
			{
				$colid = 'col2';
			}
		}

		// status options drop down
		$status_options = '';
		foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
		{
			$optgroup_options = fetch_pt_search_issuestatus_options($typeinfo['statuses'], $issue['issuestatusid']);

			$optionid = $issuetypeid;
			$optgroup_name = 'issuetypeid[]';
			$optgroup_value = $issuetypeid;
			$optgroup_label = $vbphrase["issuetype_{$issuetypeid}_singular"];
			$optgroup_id = "issuetype_{$issuetypeid}_statuses";
			$optionchecked = ($issuetypeid == $vbulletin->GPC['issuetypeid'] ? ' checked="checked"' : '');
			$show['optgroup_checkbox'] = true;

			$templater = vB_Template::create('pt_checkbox_optgroup');
				$templater->register('optgroup_id', $optgroup_id);
				$templater->register('optgroup_label', $optgroup_label);
				$templater->register('optgroup_name', $optgroup_name);
				$templater->register('optgroup_options', $optgroup_options);
				$templater->register('optgroup_value', $optgroup_value);
				$templater->register('optionchecked', $optionchecked);
			$status_options .= $templater->render();
		}

		$optionchecked = '';

		// tag drop down
		$tags = $vbulletin->db->query_read("
			SELECT tagtext
			FROM " . TABLE_PREFIX . "pt_tag
			ORDER BY tagtext
		");

		$tag_options = array('col1' => '', 'col2' => '');
		$col_count = ceil($vbulletin->db->num_rows($tags) / 2);
		$i = 0;
		$colid = 'col1';

		$optionclass = '';
		$optionselected = '';
		while ($tag = $vbulletin->db->fetch_array($tags))
		{
			$optionname = 'tag[]';
			$optionvalue = $tag['tagtext'];
			$optiontitle = $tag['tagtext'];
			$optionid = "tag_$i";

			$templater = vB_Template::create('pt_checkbox_option');
				$templater->register('optionchecked', $optionchecked);
				$templater->register('optionid', $optionid);
				$templater->register('optionname', $optionname);
				$templater->register('optiontitle', $optiontitle);
				$templater->register('optionvalue', $optionvalue);
			$tag_options[$colid] .= $templater->render();

			if (++$i >= $col_count)
			{
				$colid = 'col2';
			}
		}

		// setup versions
		fetch_pt_search_versions($appliesversion_options, $addressedversion_options, $project_names);

		// setup categories
		$category_options = fetch_pt_search_categories($project_names);

		// Default template name
		if (!isset($template_name))
		{
			$template_name = 'search_input_ptissuenote';
		}

		// output
		($hook = vBulletinHook::fetch_hook('projectsearch_form_complete')) ? eval($hook) : false;

		$template = vB_Template::create($template_name);
			$template->register('addressedversion_options', $addressedversion_options);
			$template->register('appliesversion_options', $appliesversion_options);
			$template->register('assignable_users', $assignable_users);
			$template->register('category_options', $category_options);
			$template->register('contenttypeid', $contenttypeid);
			$template->register('issue_type', $status_options);
			$template->register('milestone', $milestone);
			$template->register('navbar', $navbar);
			$template->register('project_options', $project_options);
			$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
			$template->register('show', $show);
			$template->register('status_options', $status_options);
			$template->register('tag_options', $tag_options);

			$this->setPrefs(
				$template, $prefs, array(
					'select'=> array(
						'textlocation',
						'priority_type',
						'priority',
						'searchdate',
						'beforeafter',
						'replycount_type',
						'votecount_type',
						'votecount_postneg',
						'sortby',
						'order'
					),
					'cb' => array(
						'userissuesonly',
						'tag',
						'nocache',
						'issuetypeid',
						'issuestatusid',
						'needsattachments',
						'needspendingpetitions',
						'projectid',
						'projectcategoryid',
						'appliesversion',
						'appliesgroup',
						'addressedversion',
						'addressedgroup',
						'assigneduser'
					),
			 		'value' => array(
						'query',
						'searchuser',
						'replycount',
						'votecount'
					),
					'rb' => array(
						'showposts'
					)
				)
			);

		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);

		if (isset($registers) AND is_array($registers))
		{
			foreach ($registers AS $key => $value)
			{
				$template->register($key, htmlspecialchars_uni($value));
			}
		}

		return $template->render();
	}

	// ###################### Start listSearchGlobals ######################
	/**
	 * vB_Search_Type::list_SearchGlobals()
	 * The globals is a list of variables we'll try to pull from the input.
	 * They should be here because we want to use them in searchcommon and ajax,
	 * and probably elsewhere as we proceed.
	 *
	 * @return array
	 */
	public function listSearchGlobals()
	{
		return $this->form_globals;
	}

	/**
	* Tell what type of object this is for inline moderation
	*
	* @return string
	*/
	public function get_inlinemod_type()
	{
		return '';
	}

	public function get_inlinemod_action()
	{
		return '';
	}

	public function add_advanced_search_filters($criteria, $registry)
	{
		if ($registry->GPC['projecttags'])
		{
			$this->add_tagid_filter($criteria, $registry->GPC['projecttags']);
		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['join']['issue'] = sprintf(self::$issue_join, TABLE_PREFIX, vB_Types::instance()->getContentTypeId("vBProjectTools_Issue"));

		$result['table'] = 'issue';

		$fields = array('issuestatusid', 'priority');

		if (in_array($fieldname, $fields))
		{
			$result['field'] = $fieldname;
		}
		else if ($fieldname == 'tag')
		{
			$result['join']['pt_issuetag'] = sprintf(self::$tag_join, TABLE_PREFIX);
			$result['table'] = 'pt_issuetag';
			$result['field'] = 'tagid';
		}
		else
		{
			return false;
		}

		return $result;
	}

	/**
	*	Add a filter for tags. We'll get them as an array. We should verify
	*  that each is an integer
	*
	*	@param array $forumids
	*/
	protected function add_tagid_filter($criteria, $tagids )
	{
		global $vbulletin, $vbphrase;

		if (in_array(' ', $tagids) OR in_array('', $tagids))
		{
			return;
		}

		$tagids = array_unique($tagids);

		foreach ($tagids as $key => $tagid)
		{
			if (! is_numeric($tagid))
			{
				unset($tagids[$key]);
			}
		}

		if (!count($tagids))
		{
			$criteria->add_error('invalidid', $vbphrase['tag'], $vbulletin->options['contactuslink']);
			return;
		}
		//if we got here we have an array of integers, so we're good. Now let's get
		// the display information.
		$tag_strings =  vB_Search_Searchtools::getDisplayString('pt_project', $vbphrase['project'], 'tagtext', 'tagid', $tagids, vB_Search_Core::OP_EQ, false);
		$criteria->add_filter('tagid', vB_Search_Core::OP_EQ, $tagids,true);
		$criteria->add_display_strings('tagid', $tag_strings) ;
	}

	protected $package = "vBProjectTools";
	protected $class = "IssueNote";
	protected $group_package = "vBProjectTools";
	protected $group_class = "Issue";

	protected $type_globals = array (
		'starteronly' => TYPE_INT,

		'issuetypeid' => TYPE_ARRAY_NOHTML,
		'issuestatusid'	=> TYPE_ARRAY_UINT,

		'priority'        => TYPE_INT,
		'priority_type'   => TYPE_STR,

		'replycount'      => TYPE_INT,
		'replycount_type' => TYPE_STR,

		'votecount'        => TYPE_INT,
		'votecount_type'   => TYPE_STR,
		'votecount_posneg' => TYPE_STR,

		'needsattachments'      => TYPE_UINT,
		'needspendingpetitions' => TYPE_UINT,

		'addressedversion' => TYPE_ARRAY_INT,
		'appliesversion'   => TYPE_ARRAY_INT,

		'projectid'     => TYPE_ARRAY_UINT,
		'milestoneid'   => TYPE_UINT,

		'assigneduser'  => TYPE_ARRAY_UINT,
		'tag'           => TYPE_ARRAY_STR,

		'groupby'   => TYPE_NOHTML,

		'showposts'	=> TYPE_INT
	);

	private static $tag_join = " INNER JOIN %spt_issuetag AS pt_issuetag ON (pt_issuetag.issueid = issue.issueid)";
	private static $issue_join = " INNER JOIN %spt_issue AS pt_issue ON (searchcore.contenttypeid =%u  AND searchcore.primaryid = issue.issueid)";
}