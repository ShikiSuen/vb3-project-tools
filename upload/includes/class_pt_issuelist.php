<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Generic handler for displaying a list of issues.
* Currently, this can only display of a list of issues within one project.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_Pt_IssueList
{
	/**
	* Main vB_Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Array of project information
	*
	* @var	array
	*/
	var $project = array();

	/**
	* A string of extra fields to select from the tables.
	*
	* @var	string
	*/
	var $extra_fields = '';

	/**
	* A string of extra joins to make in the query.
	*
	* @var	string
	*/
	var $extra_joins = '';

	/**
	* Key representing the sort field. This does not necessarily
	* map directly onto a DB field.
	*
	* @var	string
	*/
	var $sort_field = 'lastpost';

	/**
	* The primary sort field that is put into the ORDER BY clause. As an array,
	* can sort on multiple fields (all in the sort order direction).
	*
	* @var	string|array
	*/
	var $sort_field_sql = 'lastpost';

	/**
	* The sort order, asc or desc. Used in the ORDER BY directly.
	*
	* @var	string
	*/
	var $sort_order = 'desc';

	/**
	* The DB result set resource. Null before the query is run.
	*
	* @var	resource|null
	*/
	var $result = null;

	/**
	* The real page number we're actually on. The value passed in may be
	* modified if it is out of range. Null before the query is run.
	*
	* @var	integer|null
	*/
	var $real_pagenumber = null;

	/**
	* Whether or not to calculate the total number of rows in the result set.
	*
	* @var	boolean
	*/
	var $calc_total_rows = true;

	/**
	* The total number of rows in the result set. Null if not calculated.
	*
	* @var	integer|null
	*/
	var $total_rows = null;

	/**
	* Constructor.
	*
	* @param	array		Project info
	* @param	vB_Registry	Registry info
	*/
	function vB_Pt_IssueList($project, &$registry)
	{
		$this->project = $project;
		$this->registry =& $registry;
	}

	/**
	* Adds fields to the query. Handles adding leading commas where necessary.
	*
	* @param	string	Fields to add
	*/
	function add_fields($fields)
	{
		if ($this->extra_fields)
		{
			$this->extra_fields .= ", ";
		}

		$this->extra_fields .= $fields;
	}

	/**
	* Adds joins to the query.
	*
	* @param	string	Joins to add
	*/
	function add_joins($joins)
	{
		if ($this->extra_joins)
		{
			$this->extra_joins .= "\n";
		}

		$this->extra_joins .= $joins;
	}

	/**
	* Sets the sort order. Has a hard-coded mapping of sort field keys to
	* actual columns.
	*
	* @param	string	Sort field key
	* @param	string	Sort order (asc/desc)
	*/
	function set_sort($sort_field, $sort_order)
	{
		switch ($sort_field)
		{
			case 'priority':
				$this->sort_field = 'priority';
				// Nigel says our priority goes to 11, which really just makes "unknown" come last
				$this->sort_field_sql = "IF(priority = 0, 11, priority)";
				break;

			case 'lastpost':
				$this->sort_field = 'lastpost';
				$this->sort_field_sql = 'lastpost';
				break;

			case 'replycount':
				$this->sort_field = 'replycount';
				$this->sort_field_sql = 'replycount';
				break;

			case 'issuestatusid':
				$this->sort_field = 'issuestatusid';
				$this->sort_field_sql = array('issuestatus.displayorder', 'issuestatus.issuestatusid');
				break;

			// these are the simple sorts
			case 'title':
			case 'submitusername':
				$this->sort_field = $sort_field;
				$this->sort_field_sql = "issue." . $sort_field;
				break;

			default:
				$handled = false;
				// TODO: hook

				if (!$handled)
				{
					$this->sort_field = 'lastpost';
					$this->sort_field_sql = 'lastpost';
				}
		}

		if (strtolower($sort_order) != 'asc')
		{
			$this->sort_order = 'desc';
		}
		else
		{
			$this->sort_order = 'asc';
		}
	}

	/**
	* Fetches an array of info about the sort arrows.
	*
	* @param	string	Base sort URL text. Must have a ? in it. Sort and order added automatically.
	* @param	string	Name of the template to use for the sort arrow.
	*
	* @return	array	Array of sort arrow info
	*/
	function fetch_sort_arrow_array($sort_url_base, $template_name = 'pt_issuelist_arrow')
	{
		global $vbphrase, $show;

		$opposite_sort = ($this->sort_order == 'asc' ? 'desc' : 'asc');

		$sort_url = $sort_url_base . "&amp;sort={$this->sort_field}&amp;order=$opposite_sort";

		$sort_arrow = array(
			'title' => '',
			'submitusername' => '',
			'issuestatusid' => '',
			'priority' => '',
			'replycount' => '',
			'lastpost' => '',
		);

		$templater = vB_Template::create($template_name);
			$templater->register('opposite_sort', $opposite_sort);
			$templater->register('sort_url', $sort_url);
		$sort_arrow[$this->sort_field] = $templater->render();

		return $sort_arrow;
	}

	/**
	* Executes the issue list query with the specified criteria.
	*
	* @param	string	Criteria to limit results to
	* @param	integer	Page number to fetch
	* @param	integer	Results to fetch per page
	*/
	function exec_query($criteria, $pagenumber, $perpage)
	{
		build_issue_private_lastpost_sql_project($this->registry->userinfo, $this->project['projectid'],
			$private_lastpost_join, $private_lastpost_fields
		);

		$replycount_clause = fetch_private_replycount_clause($this->registry->userinfo, $this->project['projectid']);

		$marking = ($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']);

		if (!$criteria)
		{
			$criteria = '1=1';
		}

		if ($this->sort_field == 'issuestatusid')
		{
			$status_join = "LEFT JOIN " . TABLE_PREFIX . "pt_issuestatus AS issuestatus ON
				(issuestatus.issuestatusid = issue.issuestatusid)
			";
		}
		else
		{
			$status_join = '';
		}

		if (is_array($this->sort_field_sql))
		{
			$this->sort_field_sql = implode(" {$this->sort_order}, ", $this->sort_field_sql);
		}

		do
		{
			if (!$pagenumber)
			{
				$pagenumber = 1;
			}
			$start = ($pagenumber - 1) * $perpage;

			// issue list
			$this->result = $this->registry->db->query_read("
				SELECT
					" . ($this->calc_total_rows ? "SQL_CALC_FOUND_ROWS" : '') . "
					issue.*, issuedeletionlog.reason AS deletionreason
					" . ($this->registry->userinfo['userid'] ? ", issuesubscribe.subscribetype, IF(issueassign.issueid IS NULL, 0, 1) AS isassigned" : '') . "
					" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
					" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
					" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
					{$this->extra_fields}
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				$status_join
				LEFT JOIN " . TABLE_PREFIX . "pt_issuedeletionlog AS issuedeletionlog ON
					(issuedeletionlog.primaryid = issue.issueid AND issuedeletionlog.type = 'issue')
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS projectversion ON
					(projectversion.projectversionid = issue.appliesversionid)
				" . ($this->registry->userinfo['userid'] ? "
					LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON
						(issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . $this->registry->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON
						(issueassign.issueid = issue.issueid AND issueassign.userid = " . $this->registry->userinfo['userid'] . ")
				" : '') . "
				" . ($marking ? "
					LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . $this->registry->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "pt_projectread as projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . $this->registry->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
				" : '') . "
					$private_lastpost_join
					{$this->extra_joins}
				WHERE $criteria
				ORDER BY {$this->sort_field_sql} {$this->sort_order}, issue.lastpost DESC
				LIMIT $start, $perpage
			");

			if (!$this->calc_total_rows)
			{
				break;
			}

			list($this->total_rows) = $this->registry->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

			if ($start >= $this->total_rows)
			{
				$pagenumber = ceil($this->total_rows / $perpage);
			}
		}
		while ($start >= $this->total_rows AND $this->total_rows);

		$this->real_pagenumber = $pagenumber;
	}
}
?>
