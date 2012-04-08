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

define('PT_SEARCHGEN_CRITERIA_ADDED', 1);
define('PT_SEARCHGEN_CRITERIA_FAILED', 2);
define('PT_SEARCHGEN_CRITERIA_UNNECESSARY', 3);

/**
* Performs issue searches
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_IssueSearch
{
	/**
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Object that will be used to generate the search query
	*
	* @var	vB_Pt_IssueSearchGenerator
	*/
	var $generator = null;

	/**
	* The effective sort method, to be used in the query. Generally needs a table name.
	*
	* @var	string
	*/
	var $sort = 'lastpost';

	/**
	* The raw sort method that was passed in. Used to save the sort method for later use
	*
	* @var	string
	*/
	var $sort_raw = 'lastpost';

	/**
	* The effective sort order that is to be used
	*
	* @var	string
	*/
	var $sortorder = 'desc';

	/**
	* The raw sort order that was passed in
	*
	* @var	string
	*/
	var $sortorder_raw = 'desc';

	/**
	* Grouping column name (or multiple columns)
	*
	* @var	string
	*/
	var $group = 'issue.issueid';

	/**
	* Raw name of the grouping passed in
	*
	* @var string
	*/
	var $grouping_raw = '';

	/**
	* Column to pull the group ID from
	*
	* @var	string
	*/
	var $groupid_col = '';

	/**
	* The report this search spawned from, if there is one.
	*
	* @var	integer
	*/
	var $issuereportid = 0;

	/**
	* Raw criteria searched for.
	*
	* @var	array	Key: criteria name, value: criteria filter
	*/
	var $criteria_raw = array();

	/**
	* Constructor.
	*
	* @param	vB_Registry
	*/
	function vB_Pt_IssueSearch(&$registry)
	{
		$this->registry =& $registry;
		$this->generator = new vB_Pt_IssueSearchGenerator($registry);
	}

	/**
	* Adds a search criteria
	*
	* @param	string	Name of criteria
	* @param	mixed	How to restrict the criteria
	*
	* @return	boolean	True on success
	*/
	function add($name, $value)
	{
		$raw = $value;
		$genval = $this->generator->add($name, $value);
		if ($genval == PT_SEARCHGEN_CRITERIA_ADDED)
		{
			$this->criteria_raw["$name"] = $raw;
			return true;
		}
		else if ($genval == PT_SEARCHGEN_CRITERIA_UNNECESSARY)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Do we have criteria that we're searching on? Necessary to do a search
	*
	* @return	bool
	*/
	function has_criteria()
	{
		return ((sizeof($this->criteria_raw) > 0) OR ($this->groupid_col AND !empty($this->generator->joins)));
	}

	/**
	* Set the sorting method. Looked up in generator's sort array
	*
	* @param	string	Type of sorting to do
	* @param	string	Direction of sorting (asc/desc)
	*/
	function set_sort($sort, $sortorder)
	{
		if ($this->generator->verify_sort($sort, $sortorder, $sort_raw, $sortorder_raw))
		{
			$this->sort = $sort;
			$this->sort_raw = $sort_raw;
			$this->sortorder = $sortorder;
			$this->sortorder_raw = $sortorder_raw;
		}
	}

	/**
	* Set the grouping. Looked up in the generator's group array
	*
	* @param	string	Type of column to group on
	*/
	function set_group($group)
	{
		if ($this->generator->verify_group($group, $group_raw, $groupid_col))
		{
			$this->group = $group;
			$this->group_raw = $group_raw;
			$this->groupid_col = $groupid_col;
		}
	}

	/**
	* Sets the associated report
	*
	* @param	integer	Report ID
	*/
	function set_issuereportid($issuereportid)
	{
		$this->issuereportid = intval($issuereportid);
	}

	/**
	* Determines whether the current search has errors
	*
	* @return	boolean
	*/
	function has_errors()
	{
		return $this->generator->has_errors();
	}

	/**
	* Executes the current search.
	*
	* @param	string	Additioanl search permissions to take into account
	* @param	array	Array of user info for user to run search as (for private last post joins)
	*
	* @return	false|integer	False on failure to execute, integer on success of the issuesearchid that was inserted/used
	*/
	function execute($search_perms, $user = null)
	{
		if ($this->has_errors())
		{
			return false;
		}

		$db =& $this->registry->db;

		if (!is_array($user))
		{
			$user = $this->registry->userinfo;
		}

		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_issuesearch
				(userid, ipaddress, criteria, sortby, sortorder, groupby, searchtime, resultcount, dateline, completed, issuereportid)
			VALUES
				(" . $this->registry->userinfo['userid'] . ",
				'" . ($this->registry->options['logip'] ? $db->escape_string(IPADDRESS) : '') . "',
				'" . $db->escape_string(serialize($this->criteria_raw)) . "',
				'" . $db->escape_string($this->sort_raw) . "',
				'" . $db->escape_string($this->sortorder_raw) . "',
				'" . $db->escape_string($this->group_raw) . "',
				0,
				0,
				" . TIMENOW . ",
				0,
				" . intval($this->issuereportid) . ")
		");
		$issuesearchid = $db->insert_id();

		$ids = $this->perform_search($search_perms, $user);

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issuesearch SET
				resultcount = " . sizeof($ids) . ",
				completed = 1
			WHERE issuesearchid = $issuesearchid
		");

		if (!$ids)
		{
			$this->generator->error('searchnoresults', '');
			return false;
		}

		$results = array();
		$offset = 0;
		foreach ($ids AS $id)
		{
			$results[] = "($issuesearchid, $id[id], $offset, '" . $db->escape_string($id['groupid']) . "')";
			$offset++;
		}

		$db->query_write("
			INSERT INTO " . TABLE_PREFIX  ."pt_issuesearchresult
				(issuesearchid, issueid, offset, groupid)
			VALUES
				" . implode(',', $results)
		);

		return $issuesearchid;
	}

	/**
	* Performs the actual search
	*
	* @param	string	Search permissions in query form
	* @param	array	Array of user info for user to run search as (for private last post joins)
	*
	* @return	array	Array of matched IDs
	*/
	function perform_search($search_perms, $user = null)
	{
		$db =& $this->registry->db;

		if (!is_array($user))
		{
			$user = $this->registry->userinfo;
		}

		if (!$this->generator->has_join('private_lastpost') OR $user != $this->registry->userinfo)
		{
			build_issue_private_lastpost_sql_all($user, $private_lastpost_join, $devnull);
			if ($private_lastpost_join)
			{
				$this->generator->add_join('private_lastpost', $private_lastpost_join);
			}
		}

		// generate and execute search query
		$criteria = $this->generator->generate();
		if (!$criteria['where'])
		{
			$criteria['where'] = '1=1';
		}

		$replycount_clause = fetch_private_replycount_clause($user);

		$search_results = $db->query_read_slave("
			SELECT issue.issueid AS id
				" . ($this->groupid_col ? ", " . $this->groupid_col . " AS groupid" : '') . "
				" . ($this->generator->has_join('private_lastpost') ? ", IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost) AS lastpost" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			$criteria[joins]
			WHERE $criteria[where]
				" . ($search_perms ? "AND ($search_perms)" : '') . "
			GROUP BY " . $this->group . "
			ORDER BY " . $this->sort . ' ' . $this->sortorder . "
		");

		// prepare results
		$ids = array();
		while ($result = $db->fetch_array($search_results))
		{
			$ids[] = $result;
		}
		$db->free_result($search_results);

		return $ids;
	}
}

/**
* Resorts searches based on existing search results
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueSearch_Resort extends vB_Pt_IssueSearch
{
	/**
	* Search ID to base on
	*
	* @var	integer
	*/
	var $issuesearchid = 0;

	/**
	* Performs the actual search
	*
	* @param	string	Search permissions in query form
	* @param	array	Array of user info for user to run search as (for private last post joins)
	*
	* @return	array	Array of matched IDs
	*/
	function perform_search($search_perms, $user = null)
	{
		$db =& $this->registry->db;

		if (!is_array($user))
		{
			$user = $this->registry->userinfo;
		}

		if (!$this->generator->has_join('private_lastpost') OR $user != $this->registry->userinfo)
		{
			build_issue_private_lastpost_sql_all($user, $private_lastpost_join, $devnull);
			if ($private_lastpost_join)
			{
				$this->generator->add_join('private_lastpost', $private_lastpost_join);
			}
		}

		$replycount_clause = fetch_private_replycount_clause($user);

		$search_results = $db->query_read_slave("
			SELECT issuesearchresult.issueid AS id, issuesearchresult.groupid
				" . ($this->generator->has_join('private_lastpost') ? ", IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost) AS lastpost" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
			FROM " . TABLE_PREFIX . "pt_issuesearchresult AS issuesearchresult
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = issuesearchresult.issueid)
			$private_lastpost_join
			WHERE issuesearchresult.issuesearchid = " . intval($this->issuesearchid) . "
				" . ($search_perms ? "AND ($search_perms)" : '') ."
			ORDER BY " . $this->sort . ' ' . $this->sortorder . "
		");

		// prepare results
		$ids = array();
		while ($result = $db->fetch_array($search_results))
		{
			$ids[] = $result;
		}
		$db->free_result($search_results);

		return $ids;
	}

	/**
	* Sets the issue search ID to the source search (to resort from). Copies all the necessary data to this search.
	*
	* @param	integer	Issue search ID
	*/
	function set_issuesearchid($issuesearchid)
	{
		$search = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuesearch
			WHERE issuesearchid = " . intval($issuesearchid)
		);

		if ($search)
		{
			$this->issuesearchid = $search['issuesearchid'];
			$this->criteria_raw = unserialize($search['criteria']);
			$this->sort_raw = $search['sortby'];
			$this->sortorder_raw = $search['sortorder'];
			$this->group_raw = $search['groupby'];
			$this->issuereportid = $search['issuereportid'];
		}
	}
}

/**
* Generates issue search criteria. Atom is issue.issueid. That table must be available in the final query.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueSearchGenerator
{
	/**
	* List of valid criteria names. Key: criteria name, value: add method
	*
	* @var	array
	*/
	var $valid_fields = array(
		'projectid'     => 'add_projectid',   // int/array
		'milestoneid'   => 'add_milestoneid', // int

		'issuetypeid'   => 'add_issuetypeid',     // string/array
		'issuestatusid' => 'add_issuestatusid',   // int/array
		'typestatusmix' => 'add_typestatusmix',   // array

		'assigneduser'  => 'add_assigneduser', // int/array

		'text'        => 'add_text',        // string - fulltext search
		'issuetext'   => 'add_issuetext',   // string - issue title/summary only search
		'firsttext'   => 'add_firsttext',   // string - issue title/summary + first post search

		'user'       => 'add_user',       // string - post by user
		'user_issue' => 'add_user_issue', // string - issue started by user

		'searchdate_gteq' => 'add_searchdate_gteq', // int - search date >=
		'searchdate_lteq' => 'add_searchdate_lteq', // int - search date <=

		'replycount_gteq' => 'add_replycount_gteq', // int - reply count >=
		'replycount_lteq' => 'add_replycount_lteq', // int - reply count <=

		'votecount_pos_gteq' => 'add_votecount_pos_gteq', // int - positive vote count >=
		'votecount_pos_lteq' => 'add_votecount_pos_lteq', // int - positive vote count <=
		'votecount_neg_gteq' => 'add_votecount_neg_gteq', // int - negative vote count >=
		'votecount_neg_lteq' => 'add_votecount_neg_lteq', // int - negative vote count <=

		'needsattachments' => 'add_needs_attachments',            // int - whether the issue must have attachments
		'needspendingpetitions' => 'add_needs_pending_petitions', // int - whether the issue must have pending petitions

		'appliesversion' => 'add_appliesversion',
		'appliesgroup'   => 'add_appliesgroup',
		'appliesmix'     => 'add_appliesmix',

		'addressedversion' => 'add_addressedversion',
		'addressedgroup'   => 'add_addressedgroup',
		'addressedmix'     => 'add_addressedmix',

		'projectcategoryid' => 'add_projectcategoryid',

		'projectpriorityid' => 'add_projectpriorityid',

		'tag' => 'add_tag', // string/array - has tags

		'newonly' => 'add_newonly', // boolean(0/1)
	);

	/**
	* List of valid sorting fields.
	* Key is the unique ID (from the form), value is the column name
	*
	* @var	array
	*/
	var $valid_sort = array(
		'lastpost'     => 'lastpost',
		'title'        => 'issue.title',
		'priority'     => 'issue.priority',
		'replies'      => 'replycount',
		'submitdate'   => 'issue.submitdate',
		'votepositive' => 'issue.votepositive',
		'votenegative' => 'issue.votenegative'
	);

	/**
	* Valid grouping fields.
	* Key is the unique ID (from the form), value is a callback method
	*
	* @var	array
	*/
	var $valid_groups = array(
		'assignment'         => 'add_group_assignment',
		'issuestatusid'      => 'add_group_issuestatusid',
		'issuetypeid'        => 'add_group_issuetypeid',
		'projectid'          => 'add_group_projectid',
		'tag'                => 'add_group_tag',
		'appliesversionid'   => 'add_group_appliesversionid',
		'addressedversionid' => 'add_group_addressedversionid',
		'projectcategoryid'  => 'add_group_projectcategoryid',
		'projectpriorityid'  => 'add_group_projectpriorityid'
	);

	/**
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* List of errors (DM style)
	*
	* @var	array
	*/
	var $errors = array();

	/**
	* Where clause pieces. Will be ANDed together
	*
	* @var	array
	*/
	var $where = array();

	/**
	* List of joins necessary
	*
	* @var	array
	*/
	var $joins = array();

	/**
	* Constructor.
	*
	* @param	vB_Registry
	*/
	function vB_Pt_IssueSearchGenerator(&$registry)
	{
		$this->registry = $registry;
	}

	/**
	* Determines whether the current search has errors
	*
	* @return	boolean
	*/
	function has_errors()
	{
		return !empty($this->errors);
	}

	/**
	* Verifies the sorting field and grabs the necessary data
	*
	* @param	string	(In/Out) The specified sort field, translated to the column name
	* @param	string	(In/Out) The specified sort order, translated to the appropriate safe value
	* @param	string	(Output) Raw sort field passed in
	* @param	string	(Output) Raw sort order passed in
	*
	* @return	bool	Returns true unless something fails. (Always returns true right now)
	*/
	function verify_sort(&$sort, &$sortorder, &$sort_raw, &$sortorder_raw)
	{
		$sort_raw = $sort;
		$sortorder_raw = $sortorder;

		if (!isset($this->valid_sort["$sort"]))
		{
			$sort = 'lastpost';
			$sort_raw = 'lastpost';
		}
		else
		{
			$sort = $this->valid_sort["$sort"];
		}

		switch (strtolower($sortorder))
		{
			case 'asc':
			case 'desc':
				break;
			default:
				$sortorder = 'desc';
				$sortorder_raw = 'desc';
		}

		if ($sort_raw == 'priority')
		{
			// priority sorting needs to be flipped (as 1 is highest)
			$sortorder = ($sortorder == 'desc' ? 'asc' : 'desc');
		}

		return true;
	}

	/**
	* Verifies the grouping field and grabs the necessary data
	*
	* @param	string	(In/Out) The specified grouping, translated to the column name
	* @param	string	(Output) Raw grouping passed in
	* @param	string	(Output) Column to pull the group ID value from
	*
	* @return	bool	Returns true unless something fails. (Always returns true right now)
	*/
	function verify_group(&$group, &$group_raw, &$groupid_col)
	{
		$group_raw = $group;
		if (!isset($this->valid_groups["$group"]))
		{
			$group = 'issue.issueid';
			$group_raw = '';
			$groupid_col = '';
		}
		else
		{
			$add_method = $this->valid_groups["$group"];
			$this->$add_method($group, $groupid_col);
		}

		return true;
	}

	/**
	* Adds a search criteria
	*
	* @param	string	Name of criteria
	* @param	mixed	How to restrict the criteria
	*
	* @return	boolean	True on success
	*/
	function add($name, $value)
	{
		if (!isset($this->valid_fields["$name"]))
		{
			$this->error('pt_search_field_x_unknown', htmlspecialchars_uni($name));
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$raw = $value;
		$add_method = $this->valid_fields["$name"];
		return $this->$add_method($name, $value);
	}

	/**
	* Adds a join to the final query. Used externally.
	*
	* @param	string	Name of the join (for identifying)
	* @param	string	Join text
	* @param	boolean	True to overwrite (on name)
	*
	* @return	boolean	True if the join is added
	*/
	function add_join($name, $join, $overwrite = true)
	{
		if (!$this->has_join($name) OR $overwrite)
		{
			$this->joins["$name"] = $join;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Determines if we already have a join by this name.
	*
	* @param	string	Join to check
	*
	* @return	boolean
	*/
	function has_join($name)
	{
		return isset($this->joins["$name"]);
	}

	/**
	* Adds an error to the list, phrased for the current user. 1 or more arguments
	*
	* @param	string	Error phrase name
	*/
	function error($errorphrase)
	{
		$args = func_get_args();

		if (is_array($errorphrase))
		{
			$error = fetch_error($errorphrase);
		}
		else
		{
			$error = call_user_func_array('fetch_error', $args);
		}

		$this->errors[] = $error;
	}

	/**
	* Generates the search query bits
	*
	* @return	array|false	False if error, array consisting of joins and where clause otherwise
	*/
	function generate()
	{
		if (!$this->has_errors())
		{
			return array('joins' => implode("\n", $this->joins), 'where' => implode("\nAND ", $this->where));
		}
		else
		{
			return false;
		}
	}

	/**
	* Prepares a criteria that may either be a scalar or an array
	*
	* @param	mixed		Value to process
	* @param	callback	Callback function to call on each value
	* @param	string		Text to implode the array with
	*
	* @return	mixed		Returns true if the array is empty, otherwise the processed values
	*/
	function prepare_scalar_array($value, $callback = '', $array_splitter = ',')
	{
		if (is_array($value))
		{
			if ($callback)
			{
				$value = array_map($callback, $value);
			}

			$value = array_values($value);
			if (count($value) == 0 OR (count($value) == 1 AND empty($value[0])))
			{
				return call_user_func($callback, '');
			}
			else
			{
				return implode($array_splitter, $value);
			}
		}
		else if ($callback)
		{
			return call_user_func($callback, $value);
		}
		else
		{
			return $value;
		}
	}

	/**
	* Adds project ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_projectid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['projectid'] = "issue.projectid IN ($id)";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds milestone ID criteria. Only allows one milestone to be specified.
	*
	* @param	string
	* @param	integer
	*/
	function add_milestoneid($name, $value)
	{
		$id = intval($value);
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$milestone = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_milestone
			WHERE milestoneid = $id
		");
		if (!$milestone)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		require_once(DIR . '/includes/functions_pt_milestone.php');
		$projectperms = fetch_project_permissions($this->registry->userinfo, $milestone['projectid']);
		$milestone_types = fetch_viewable_milestone_types($projectperms);

		if (!$milestone_types)
		{
			// no permission, give a condition with no matches
			$this->where['milestoneid'] = "1=0";
		}
		else
		{
			$this->where['milestoneid'] = "
				issue.milestoneid = $id
				AND issue.projectid = $milestone[projectid]
				AND issue.issuetypeid IN ('" . implode("','", $milestone_types) . "')
			";
		}

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds category ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_projectcategoryid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$ids = explode(',', $id);
		if (($unknown_value = array_search(-1, $ids)) !== false)
		{
			// -1 is the "unknown" entry, which actually needs to be 0
			$ids["$unknown_value"] = 0;
		}

		$this->where['projectcategoryid'] = "issue.projectcategoryid IN (" . implode(',', $ids) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds priority ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_projectpriorityid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$ids = explode(',', $id);
		if (($unknown_value = array_search(-1, $ids)) !== false)
		{
			// -1 is the "unknown" entry, which actually needs to be 0
			$ids["$unknown_value"] = 0;
		}

		$this->where['projectpriorityid'] = "issue.priority IN (" . implode(',', $ids) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds tag criteria
	*
	* @param	string
	* @param	string|array
	*
	* @return	boolean	True on success
	*/
	function add_tag($name, $value)
	{
		$id = $this->prepare_scalar_array($value, array(&$this->registry->db, 'escape_string'), "','");
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->joins['inner_tag'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_tag AS tag ON (tag.tagtext IN ('$id'))
		");
		$this->joins['inner_issuetag_tagid'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag_tagid ON (issuetag_tagid.issueid = issue.issueid AND issuetag_tagid.tagid = tag.tagid)
		");
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds issue type ID criteria
	*
	* @param	string
	* @param	string|array
	*
	* @return	boolean	True on success
	*/
	function add_issuetypeid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, array(&$this->registry->db, 'escape_string'), "','");
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['issuetypeid'] = "issue.issuetypeid IN ('$id')";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds issue status ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_issuestatusid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['issuestatusid'] = "issue.issuestatusid IN ($id)";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds a mix of types and statuses, allowing to select type 1 OR status 1.
	*
	* @param	string
	* @param	array	Keys: issuetypeid, issuestatusid
	*
	* @return	integer	Whether the criteria was added, failed, or unnecessary
	*/
	function add_typestatusmix($name, $value)
	{
		$clause = array();

		$id = $this->prepare_scalar_array($value['issuetypeid'], array(&$this->registry->db, 'escape_string'), "','");
		if ($id)
		{
			$clause[] = "issue.issuetypeid IN ('$id')";
		}

		$id = $this->prepare_scalar_array($value['issuestatusid'], 'intval', ',');
		if ($id)
		{
			$clause[] = "issue.issuestatusid IN ($id)";
		}

		if (!$clause)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['typestatusmix'] = "(" . implode(" OR ", $clause) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds assigned user ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_assigneduser($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		if (isset($this->joins['inner_issueassign']))
		{
			unset($this->joins['inner_issueassign']);
		}

		$this->joins['inner_issueassign_userid'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON (issueassign.issueid = issue.issueid AND issueassign.userid IN ($id))
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Prepares the search text for use in a full-text query
	*
	* @param	string	Raw query text with AND, OR, and NOT
	* @param	array	(Output) Array of errors
	*
	* @return	string	Full-text query
	*/
	function prepare_search_text($query_text, &$errors)
	{
		$old_ft_search = $this->registry->options['fulltextsearch'];
		$this->registry->options['fulltextsearch'] = 1;

		// look for entire words that consist of "&#1234;". MySQL boolean
		// search will tokenize them seperately. Wrap them in quotes if they're
		// not already to emulate search for exactly that word.
		$query = explode('"', $query_text);
		$query_part_count = count($query);

		$query_text = '';
		for ($i = 0; $i < $query_part_count; $i++)
		{
			// exploding by " means the 0th, 2nd, 4th... entries in the array
			// are outside of quotes
			if ($i % 2 == 1)
			{
				// 1st, 3rd.. entry = in quotes
				$query_text .= '"' . $query["$i"] . '"';
			}
			else
			{
				// look for words that are entirely &#1234;
				$query_text .= preg_replace(
					'/(?<=^|\s)((&#[0-9]+;)+)(?=\s|$)/',
					'"$1"',
					$query["$i"]
				);
			}
		}

		$query_text = preg_replace(
			'#"([^"]+)"#sie',
			"stripslashes(str_replace(' ' , '*', '\\0'))",
			$query_text
		);

		require_once(DIR . '/includes/functions_search.php');
		$query_text = sanitize_search_query($query_text, $errors);

		if (!$errors)
		{
			// a tokenizing based approach to building a search query
			preg_match_all('#("[^"]*"|[^\s]+)#', $query_text, $matches, PREG_SET_ORDER);
			$new_query_text = '';
			$token_joiner = null;
			foreach ($matches AS $match)
			{
				if ($match[1][0] == '-')
				{
					// NOT has already been converted
					$new_query_text = "($new_query_text) $match[1]";
					continue;
				}

				switch (strtoupper($match[1]))
				{
					case 'OR':
					case 'AND':
					case 'NOT':
						// this isn't a searchable word, but a joiner
						$token_joiner = strtoupper($match[1]);
						break;

					default:
						verify_word_allowed($match[1]);

						if ($new_query_text !== '')
						{
							switch ($token_joiner)
							{
								case 'OR':
									// OR is no operator
									$new_query_text .= " $match[1]";
									break;

								case 'NOT':
									// NOT this, but everything before it
									$new_query_text = "($new_query_text) -$match[1]";
									break;

								case 'AND':
								default:
									// if we didn't have a joiner, default to and
									$new_query_text = "+($new_query_text) +$match[1]";
									break;
							}
						}
						else
						{
							$new_query_text = $match[1];
						}

						$token_joiner = null;
				}
			}

			$query_text = $new_query_text;

		}

		$this->registry->options['fulltextsearch'] = $old_ft_search;

		return trim($query_text);
	}

	/**
	* Adds fulltext search criteria
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_text($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$value = $this->prepare_search_text($value, $errors);
		if ($errors)
		{
			foreach ($errors AS $error)
			{
				$this->error($error);
			}
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$this->joins['inner_issuenote'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issueid = issue.issueid)
		");

		$value = $this->registry->db->escape_string($value);

		$this->where['text'] = trim("
			(MATCH(issue.title, issue.summary) AGAINST ('$value' IN BOOLEAN MODE) OR MATCH(issuenote.pagetext) AGAINST ('$value' IN BOOLEAN MODE))
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds issue-table-only search criteria (title, summary)
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_issuetext($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		// verify search text
		$value = $this->prepare_search_text($value, $errors);
		if ($errors)
		{
			foreach ($errors AS $error)
			{
				$this->error($error);
			}
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$value = $this->registry->db->escape_string($value);

		$this->where['text'] = trim("
			MATCH(issue.title, issue.summary) AGAINST ('$value' IN BOOLEAN MODE)
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds fulltext search criteria
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_firsttext($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		// verify search text
		$value = $this->prepare_search_text($value, $errors);
		if ($errors)
		{
			foreach ($errors AS $error)
			{
				$this->error($error);
			}
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$this->joins['inner_issuenote_first'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issue.firstnoteid = issuenote.issuenoteid)
		");

		$value = $this->registry->db->escape_string($value);

		$this->where['text'] = trim("
			(MATCH(issue.title, issue.summary) AGAINST ('$value' IN BOOLEAN MODE) OR MATCH(issuenote.pagetext) AGAINST ('$value' IN BOOLEAN MODE))
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds reply count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_replycount_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$replycount_clause = fetch_private_replycount_clause($this->registry->userinfo);
		if (!$replycount_clause)
		{
			$replycount_clause = 'issue.replycount';
		}

		$this->where['replycount'] = trim("
			$replycount_clause >= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds reply count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_replycount_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$replycount_clause = fetch_private_replycount_clause($this->registry->userinfo);

		$this->where['replycount'] = trim("
			$replycount_clause <= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds search date >= criteria. -1 means last visit, else means >= X days ago
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_searchdate_gteq($name, $value)
	{
		$value = intval($value);

		if ($value == 0 OR $value < -1)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
		else if ($value == -1)
		{
			$value = intval($this->registry->userinfo['lastvisit']);
		}
		else
		{
			// value is a number of days to look back at
			$value = TIMENOW - ($value * 86400);
		}

		// TODO: private replies?
		$this->where['lastpost'] = trim("
			issue.lastpost >= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds search date <= criteria. -1 means last visit, else means <= X days ago
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_searchdate_lteq($name, $value)
	{
		$value = intval($value);

		if ($value == 0 OR $value < -1)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
		else if ($value == -1)
		{
			$value = intval($this->registry->userinfo['lastvisit']);
		}
		else
		{
			// value is a number of days to look back at
			$value = TIMENOW - ($value * 86400);
		}

		// TODO: private replies?
		$this->where['lastpost'] = trim("
			issue.lastpost <= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds issue note user limits
	*
	* @param	string
	* @param	string	User name, should be htmlspecialchars'd already
	*
	* @return	boolean	True on success
	*/
	function add_user($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$user = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $this->registry->db->escape_string($value) . "'
		");
		if (!$user)
		{
			$this->error('invalid_user_specified');
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$this->joins['inner_issuenote'] = trim("
			INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issueid = issue.issueid)
		");

		$this->where['noteuserid'] = trim("
			issuenote.userid = $user[userid] AND issuenote.type <> 'system'
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds issue user limits
	*
	* @param	string
	* @param	string	User name, should be htmlspecialchars'd already
	*
	* @return	boolean	True on success
	*/
	function add_user_issue($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$user = $this->registry->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $this->registry->db->escape_string($value) . "'
		");
		if (!$user)
		{
			$this->error('invalid_user_specified');
			return PT_SEARCHGEN_CRITERIA_FAILED;
		}

		$this->where['submituserid'] = trim("
			issue.submituserid = $user[userid]
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds applies version criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_appliesversion($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$ids = explode(',', $id);
		if (($any_value = array_search(0, $ids)) !== false)
		{
			// 0 is the "any" key in the search, not "unknown"
			unset($ids["$any_value"]);
		}
		if (($unknown_value = array_search(-1, $ids)) !== false)
		{
			// -1 is the "unknown" entry, which actually needs to be 0
			$ids["$unknown_value"] = 0;
		}

		if (!$ids)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['appliesverionsid'] = "issue.appliesversionid IN (" . implode(',', $ids) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds applies group criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_appliesgroup($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->joins['inner_appliesversion'] = trim("
			LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS appliesversion ON (issue.appliesversionid = appliesversion.projectversionid)
		");

		$this->where['appliesgroup'] = "appliesversion.projectversiongroupid IN ($id)";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds applies group and version criteria (mixed)
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_appliesmix($name, $value)
	{
		$clause = array();

		$versionid = $this->prepare_scalar_array($value['appliesversion'], 'intval', ',');
		if ($versionid)
		{
			$versionids = explode(',', $versionid);
			if (($any_value = array_search(0, $versionids)) !== false)
			{
				// 0 is the "any" key in the search, not "unknown"
				unset($versionids["$any_value"]);
			}
			if (($unknown_value = array_search(-1, $versionids)) !== false)
			{
				// -1 is the "unknown" entry, which actually needs to be 0
				$versionids["$unknown_value"] = 0;
			}

			if ($versionids)
			{
				$clause[] = "issue.appliesversionid IN (" . implode(',', $versionids) . ")";
			}
		}

		$groupid = $this->prepare_scalar_array($value['appliesgroup'], 'intval', ',');
		if ($groupid)
		{
			$this->joins['inner_appliesversion'] = trim("
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS appliesversion ON (issue.appliesversionid = appliesversion.projectversionid)
			");
			$clause[] = "appliesversion.projectversiongroupid IN ($groupid)";
		}

		if (!$clause)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['appliesmix'] = "(" . implode(" OR ", $clause) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds addressed version criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_addressedversion($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$unaddressed_sql = '';

		$ids = explode(',', $id);
		if (($any_value = array_search(0, $ids)) !== false)
		{
			// 0 is the "any" key in the search, not "unknown"
			unset($ids["$any_value"]);
		}
		if (($unaddressed_value = array_search(-2, $ids)) !== false)
		{
			// -2 is the "unaddressed" entry
			unset($ids["$unaddressed_value"]);
			$unaddressed_sql = "OR (issue.isaddressed = 0 AND issue.addressedversionid = 0)";
		}
		if (($next_value = array_search(-1, $ids)) !== false)
		{
			// -1 is the "next release" entry, which looks for 0
			$ids["$next_value"] = 0;
		}

		if (!$ids AND !$unaddressed_sql)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		if ($ids)
		{
			$this->where['addressedversionid'] = "((issue.addressedversionid IN (" . implode(',', $ids) . ") AND issue.isaddressed = 1) $unaddressed_sql)";
		}
		else
		{
			$this->where['addressedversionid'] = "(1=0 $unaddressed_sql)";
		}
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds addressed group criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_addressedgroup($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->joins['inner_addressedversion'] = trim("
			LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS addressedversion ON (issue.addressedversionid = addressedversion.projectversionid)
		");

		$this->where['addressedgroup'] = "addressedversion.projectversiongroupid IN ($id)";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds applies group and version criteria (mixed)
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_addressedmix($name, $value)
	{
		$clause = array();

		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$versionid = $this->prepare_scalar_array($value['addressedversion'], 'intval', ',');
		if ($versionid)
		{
			$unaddressed_sql = '';

			$versionids = explode(',', $versionid);
			if (($any_value = array_search(0, $versionids)) !== false)
			{
				// 0 is the "any" key in the search, not "unknown"
				unset($versionids["$any_value"]);
			}
			if (($unaddressed_value = array_search(-2, $versionids)) !== false)
			{
				// -2 is the "unaddressed" entry
				unset($versionids["$unaddressed_value"]);
				$unaddressed_sql = "OR (issue.isaddressed = 0 AND issue.addressedversionid = 0)";
			}
			if (($next_value = array_search(-1, $versionids)) !== false)
			{
				// -1 is the "next release" entry, which looks for 0
				$versionids["$next_value"] = 0;
			}

			if ($versionids OR $unaddressed_sql)
			{
				if ($versionids)
				{
					$clause[] = "((issue.addressedversionid IN (" . implode(',', $versionids) . ") AND issue.isaddressed = 1) $unaddressed_sql)";
				}
				else
				{
					$clause[] = "(1=0 $unaddressed_sql)";
				}
			}
		}

		$groupid = $this->prepare_scalar_array($value['addressedgroup'], 'intval', ',');
		if ($groupid)
		{
			$this->joins['inner_addressedversion'] = trim("
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS addressedversion ON (issue.addressedversionid = addressedversion.projectversionid)
			");
			$clause[] = "addressedversion.projectversiongroupid IN ($groupid)";
		}

		if (!$clause)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['addressedmix'] = "(" . implode(" OR ", $clause) . ")";
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds requirement for attachments
	*
	* @param	string
	* @param	boolean
	*
	* @return	boolean	True on success
	*/
	function add_needs_attachments($name, $value)
	{
		if ($value)
		{
			$this->where['pendingpetitions'] = trim("
				issue.attachcount > 0
			");
			return PT_SEARCHGEN_CRITERIA_ADDED;
		}
		else
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
	}

	/**
	* Adds requirement for pending petitions
	*
	* @param	string
	* @param	boolean
	*
	* @return	boolean	True on success
	*/
	function add_needs_pending_petitions($name, $value)
	{
		if ($value)
		{
			$this->where['pendingpetitions'] = trim("
				issue.pendingpetitions > 0
			");
			return PT_SEARCHGEN_CRITERIA_ADDED;
		}
		else
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
	}

	/**
	* Adds positive vote count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_votecount_pos_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['votecount_pos'] = trim("
			issue.votepositive >= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds positive vote count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_votecount_pos_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['votecount_pos'] = trim("
			issue.votepositive <= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds negative vote count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_votecount_neg_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['votecount_neg'] = trim("
			issue.votenegative >= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds negative vote count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_votecount_neg_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['votecount_neg'] = trim("
			issue.votenegative <= $value
		");

		return PT_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds conditions to get new/unread issues only.
	*
	* @param	string
	* @param	boolean	True if you want to include this
	*
	* @return	boolean	True on success
	*/
	function add_newonly($name, $value)
	{
		if (!$value)
		{
			return PT_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$vbulletin =& $this->registry;
		$marking_limit = intval(TIMENOW - ($vbulletin->options['markinglimit'] * 86400));

		build_issue_private_lastpost_sql_all($this->registry->userinfo, $private_lastpost_join, $devnull);
		if ($private_lastpost_join)
		{
			$this->joins['private_lastpost'] = $private_lastpost_join;
		}

		$lastpost_col = ($private_lastpost_join ?
			'IF(issueprivatelastpost.lastpost IS NOT NULL, issueprivatelastpost.lastpost, issue.lastpost)' :
			'issue.lastpost'
		);

		if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
		{
			$this->joins['left_issueread'] = trim("
				LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $vbulletin->userinfo['userid'] . ")
			");
			$this->joins['left_projectread'] = trim("
				LEFT JOIN " . TABLE_PREFIX . "pt_projectread as projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
			");

			$this->where['newonly'] = trim("
				($lastpost_col > IF(issueread.readtime AND issueread.readtime > $marking_limit, issueread.readtime, $marking_limit)
				AND $lastpost_col > IF(projectread.readtime AND projectread.readtime > $marking_limit, projectread.readtime, $marking_limit)
				AND $lastpost_col > $marking_limit)
			");
		}
		else
		{
			$this->where['issueread'] = trim("
				$lastpost_col > " . intval($vbulletin->userinfo['lastvisit']) . "
			");
		}
		return PT_SEARCHGEN_CRITERIA_ADDED;
	}


	/**
	* Add grouping by assigned user
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_assignment(&$group, &$groupid_col)
	{
		$group = 'issueassign.userid, issue.issueid';
		$groupid_col = 'issueassign.userid';

		if (!isset($this->joins['inner_issueassign_userid']))
		{
			$this->joins['inner_issueassign'] = trim("
				INNER JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON (issueassign.issueid = issue.issueid)
			");
		}
	}

	/**
	* Add grouping by issue status
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_issuestatusid(&$group, &$groupid_col)
	{
		$group = 'issue.issuestatusid, issue.issueid';
		$groupid_col = 'issue.issuestatusid';
	}

	/**
	* Add grouping by issue type
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_issuetypeid(&$group, &$groupid_col)
	{
		$group = 'issue.issuetypeid, issue.issueid';
		$groupid_col = 'issue.issuetypeid';
	}

	/**
	* Add grouping by issue project
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_projectid(&$group, &$groupid_col)
	{
		$group = 'issue.projectid, issue.issueid';
		$groupid_col = 'issue.projectid';
	}

	/**
	* Add grouping by tag
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_tag(&$group, &$groupid_col)
	{
		if (isset($this->joins['inner_issuetag_tagid']))
		{
			$group = 'issuetag_tagid.tagid, issue.issueid';
			$groupid_col = 'issuetag_tagid.tagid';
		}
		else
		{
			$this->joins['inner_issuetag'] = trim("
				INNER JOIN " . TABLE_PREFIX . "pt_issuetag AS issuetag ON (issuetag.issueid = issue.issueid)
			");
			$group = 'issuetag.tagid, issue.issueid';
			$groupid_col = 'issuetag.tagid';
		}
	}

	/**
	* Add grouping by applicable version
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_appliesversionid(&$group, &$groupid_col)
	{
		$group = 'issue.appliesversionid, issue.issueid';
		$groupid_col = 'issue.appliesversionid';
	}

	/**
	* Add grouping by addressed version
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_addressedversionid(&$group, &$groupid_col)
	{
		$group = 'IF(issue.isaddressed = 0, -1, issue.addressedversionid), issue.issueid';
		$groupid_col = 'IF(issue.isaddressed = 0, -1, issue.addressedversionid)';
	}

	/**
	* Add grouping by category
	*
	* @param	string	(Output) Grouping method
	* @param	string	(Output) Column for group ID value
	*/
	function add_group_projectcategoryid(&$group, &$groupid_col)
	{
		$group = 'issue.projectcategoryid, issue.issueid';
		$groupid_col = 'issue.projectcategoryid';
	}

	/**
	* Add grouping by priority
	*
	* @param	string	(Output) Grouping method
	* @param	qtring	(Output) Column for group ID value
	*/
	function add_group_projectpriorityid(&$groupid, &$groupid_col)
	{
		$group = 'issue.priority, issue.issueid';
		$groupid_col = 'issue.priority';
	}
}
?>
