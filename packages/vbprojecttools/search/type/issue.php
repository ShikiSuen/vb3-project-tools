<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

/**
 * @package vBForum
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 30635 $
 * @since $Date: 2009-05-04 17:00:33 -0700 (Mon, 04 May 2009) $
 * @copyright Jelsoft Enterprises Ltd.
 */


/**
* There is a type file for each search type. This is the one for posts
*
* @package vBulletin
* @subpackage Search
*/
class vBProjectTools_Search_Type_Issue extends vB_Search_Type
{
	/***
	* This checks to see if we can view this project. It
	*
	* @param integer $projectid
	* @return  boolean
	**/
	private function verify_project_canread($projectid)
	{
		global $vbulletin;
		$permissions = fetch_project_permissions($vbulletin->userinfo, $projectid);
		//We get an array, like 'type=>array('perm_type' => 65555,...), ...
		// the types are the three (currently at least) issue types. perm_types
		// are currently generalpermissions, postpermissions, attachpermissions
		//I would say that if we have rights to one of the three issue types we
		// can view the project.
		
		if ($permissions)
		{
			foreach ($permissions as $type => $permission)
			{
				if ($permission['generalpermissions'] > 0
				or $permission['postpermissions'] > 0)
				{
					return true;
				}
			}
		}
		//If we got here we aren't authorized
		return false;
	}
	
/**
* When displaying results we get passed a list of id's. This
* function determines which are viewable by the user.
*
* @param object $user
* @param array $ids : the issue id's returned from a search
* @param array $gids : the project id's for the issues
* @return array (array of viewable issues, array of rejected projects)
*/
	public function fetch_validated_list($user, $ids, $gids)
	{
		require_once(DIR . '/includes/functions_projecttools.php');
		global $vbulletin;
		$map = array();

		foreach ($ids AS $i => $id)
		{
			$map[$gids[$i]][] = $id;
		}

		$projects = array_unique($gids);
		$rejected_projects = array();
		foreach ($projects as $projectid)
		{
			if (! $this->verify_project_canread($projectid))
			{
				$rejected_groups[] = $projectid;
			}
		}

		if (count($ids))
		{
			foreach ($ids AS $id => $issueid)
			{
				if ($issue = verify_issue($issueid ))
				{
					$list[$issueid] = vBProjectTools_Search_Result_Issue::create($issueid);
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
		return $GLOBALS['vbphrase']['project'];
	}

/**
* This is how the type objects are created
*
* @param integer $id
* @return vBProjectTools_Search_Type_Issue object
*/
	public function create_item($id)
	{
		return vBProjectTools_Search_Result_Issue::create($id);
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
			'type'        => 0,
			'status'   => 0,
			'priority'  => 0,
			'query'       => '',
			'searchuser'  => '',
			'exactname'   => '',
			'searchdate'  => 0,
			'beforeafter' => 0,
			'sortby'      => 'dateline',
			'order' 	     => 'descending',
			'tag'         => '');
		
//		query, replycount, votecount, needsattachments, needspendingpetitions,
//			milestoneid, projectcategoryid, appliesversion, addressedversion,
//			searchuser, exactname, userissuesonly,
//			textlocation, priority_type,priority, searchdate, beforeafter,
//			replycount_type, votecount_type, votecount_posneg,
//			sort, sortorder
			
	}

// ###################### Start can_group ######################
/**
* Each search type has some responsibilities, one of which is to tell
* whether it is groupable- Forums, for example are not, but posts are.
* They are naturally grouped by thread.
*
* @return
*/
	public function can_group()
	{
		return true;
	}

// ###################### Start group_by_default ######################
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

// ###################### Start listUi ######################
/**
 * This function generates the search elements for the user to search for posts
 * @param mixed $prefs : the array of user preferences
 * @param mixed $contenttypeid : the content type for which we are going to
 *    search
 * @param array registers : any additional elements to be registered. These are
 * 	just passed to the template
 * @param string $template_name : name of the template to use for display. We have
 *		a default template.
 * @param boolean $groupable : a flag to tell whether the interface should display
 * 	grouping option(s).
 * @return $html: complete html for the search elements
 */
	public function listUi($prefs = null, $contenttypeid = null, $registers = null,
		$template_name = null)
	{
		global $vbulletin, $vbphrase;
		require_once(DIR . '/includes/functions_projecttools.php');
		require_once(DIR . '/includes/functions_pt_search.php');


		if ($_REQUEST['do'] == 'intro')
		{
			$vbulletin->input->clean_array_gpc('r', array(
				'projectid'   => TYPE_UINT,
				'milestoneid' => TYPE_UINT,
				'issuetypeid' => TYPE_NOHTML
			));

			if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
			{
//				print_no_permission();
//				return;
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

			// navbar and output
			$navbits = construct_navbits(array(
				'project.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['projects'],
				'' => $vbphrase['search']
			));
			$navbar = render_navbar_template($navbits);

			($hook = vBulletinHook::fetch_hook('projectsearch_form_complete')) ? eval($hook) : false;

			$templater = vB_Template::create('pt_search');
			$templater->register_page_templates();
			$templater->register('addressedversion_options', $addressedversion_options);
			$templater->register('appliesversion_options', $appliesversion_options);
			$templater->register('assignable_users', $assignable_users);
			$templater->register('category_options', $category_options);
			$templater->register('milestone', $milestone);
			$templater->register('navbar', $navbar);
			$templater->register('project_options', $project_options);
			$templater->register('status_options', $status_options);
			$templater->register('tag_options', $tag_options);
		}
		return $templater->render();
	}
// ###################### Start showForumSelect ######################
/**
 * This function generates the select scrolling list for forums, use in search for posts
 *
 * @param string $name : name for the select element
 * @param string $style_string : something like "style=XXXX" or "class=XXX". Or empty
 * @return $html: complete html for the search interface
 */
	private function showTypeSelect($name, $style_string,
		$forumchoice=array())
	{
		global $vbulletin, $vbphrase, $show;

		//this will fill out $searchforumids as well as set the depth param in $vbulletin->forumcache
		global $searchforumids;
		fetch_search_forumids_array();


		$options = "";
		foreach ($searchforumids AS $forumid)
		{
			$forum = & $vbulletin->forumcache["$forumid"];

			if (trim($forum['link']))
			{
				continue;
			}

			$optionvalue = $forumid;
			$optiontitle = "$forum[depthmark] $forum[title_clean]";

			if ($vbulletin->options['fulltextsearch'] AND
				!($vbulletin->userinfo['forumpermissions'][$forumid] & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
			{
				$optiontitle .= '*';
				$show['cantsearchposts'] = true;
			}

			$optionselected = '';

			if ($forumchoice AND in_array($forumid, $forumchoice))
			{
				$optionselected = 'selected="selected"';
				$haveforum = true;
			}

			$options .= render_option_template($optiontitle, $forumid, $optionselected,
				'fjdpth' . min(4, $forum['depth']));
		}

		$select = "<select name=\"" .$name."[]\" multiple=\"multiple\" size=\"4\" $style_string>\n" .
					render_option_template($vbphrase['search_all_open_forums'], '',
						$haveforum ? '' : 'selected="selected"') .
					render_option_template($vbphrase['search_subscribed_forums'], 'subscribed') .
					$options .
				 	"</select>\r";
		return $select;
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

		if ($registry->GPC['prefixchoice'])
		{
			$this->add_prefix_filter($criteria, $registry->GPC['prefixchoice']);
		}

		if ($registry->GPC_exists['pollidmin'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollidmin'], vB_Search_Core::OP_GT);
		}

		if ($registry->GPC['pollidmax'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollidmin'], vB_Search_Core::OP_LT);
		}

		if ($registry->GPC['pollid'])
		{
			$this->add_pollid_filter($criteria, $registry->GPC['pollid'], vB_Search_Core::OP_EQ);
		}

		if ($registry->GPC['replylimit'])
		{
			$op = $registry->GPC['replyless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('replycount', $op, $registry->GPC['replylimit'], true);

			$criteria->add_display_strings('replycount',
				vB_Search_Searchtools::getCompareString($registry->GPC['replyless'])
				. $registry->GPC['replylimit'] . ' ' . $vbphrase['replies']);
		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['join']['issue'] = sprintf(self::$issue_join, TABLE_PREFIX,
				vB_Types::instance()->getContentTypeId("vBProjectTools_Issue"));

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
		if (! count($tagids))
		{
			$criteria->add_error('invalidid', $vbphrase['tag'], $vbulletin->options['contactuslink']);
			return;
		}
		//if we got here we have an array of integers, so we're good. Now let's get
		// the display information.
		$tag_strings =  vB_Search_Searchtools::getDisplayString('pt_project', $vbphrase['project'], 'tagtext',
			'tagid', $tagids,	vB_Search_Core::OP_EQ, false);
		$criteria->add_filter('tagid', vB_Search_Core::OP_EQ, $tagids,true);
		$criteria->add_display_strings('tagid', $tag_strings) ;
	}


	protected $package = "vBProjectTools";
	protected $class = "Issue";
	protected $group_package = "vBProjectTools";
	protected $group_class = "Project";

	protected $type_globals = array (
		'showposts'      => TYPE_INT,
		'forumchoice'	  => TYPE_ARRAY,
		'starteronly'    => TYPE_INT,
		'prefixchoice'	  => TYPE_ARRAY,
		'childforums'	  => TYPE_BOOL,
		'replyless'  => TYPE_BOOL,
		'replylimit' => TYPE_NOHTML
	);

	private static $tag_join =
		" INNER JOIN %spt_issuetag AS pt_issuetag ON (
			pt_issuetag.issueid = issue.issueid)";
		
	private static $issue_join =
	  " INNER JOIN %sissue AS issue ON (
				searchcore.contenttypeid =%u  AND searchcore.primaryid = issue.issueid)";
	private static $forum_thread_join =
		" INNER JOIN %sforum AS forum ON (thread.forumid = forum.forumid)";
}

