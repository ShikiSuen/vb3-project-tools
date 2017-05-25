<?php if (!defined('VB_ENTRY')) die('Access denied.');

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

if (!class_exists('vB_Search_Core', false))
{
	exit;
}

require_once(DIR . '/vb/legacy/dataobject.php');
require_once(DIR . '/vb/legacy/issue.php');

/**
 * Legacy functions for issue notes
 *
 * @package		vBulletin Project Tools
 * @since		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
 * @version		$Rev: 897 $
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vB_Legacy_IssueNote extends vB_Legacy_DataObject
{
	/**
	 * Get issue note fields
	 *
	 * @return 	array	Array list of the pt_issuenote table
	 */
	public static function get_field_names()
	{
		return array(
			'issuenoteid', 'issueid', 'dateline', 'pagetext', 'userid', 'username', 'type', 'ispending',
			'visible', 'lasteditdate', 'isfirstnote', 'ipaddress', 'reportthreadid'
		);
	}

	/**
	 * Public factory method to create a issue note object
	 *
	 * @param	array 		A issue note record to set to this object.
	 * @param	array 		vB_Legacy_Issue - If the issue for this issue note has already been loaded
	 * @return	vB_Legacy_IssueNote
	 */
	public static function create_from_record($record, $issue = null)
	{
		$issuenote = new vB_Legacy_IssueNote();
		$issuenote->set_record($record);

		if (is_array($issue))
		{
			$issuenote->set_issue(vB_Legacy_Issue::create_from_record($issue));
		}
		else if ($issue instanceof vB_Legacy_Issue)
		{
			$issuenote->set_issue($issue);
		}

		return $issuenote;
	}

	/**
	 * Public factory method to create an issue note object
	 *
	 * @param	integer		Id of the issuenote
	 * @return	array		Array of the result
	 */
	public static function create_from_id($id)
	{
		global $vbulletin;

		$issuenote = $vbulletin->db->query_first_slave("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_issuenote
			WHERE issuenoteid = " . intval($id) . "
		");

		if (!$issuenote)
		{
			return false;
		}
		else
		{
			return self::create_from_record($issuenote);
		}
	}

	/**
	 * Select all informations for issue notes from the database
	 * With corresponding issueids
	 *
	 * @param	array		Array of issuenote ids
	 *
	 * @return	array		Array of issuenote informations
	 */
	public static function create_array($ids)
	{
		global $vbulletin;

		if (empty($ids))
		{
			return array();
		}

		$select = array();
		$joins = array();
		$where = array();

		$select[] = 'issuenote.*';
		$select[] = 'userfield.*, usertextfield.*, user.*, IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid';

		$joins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'user AS user ON (user.userid = issuenote.userid)';
		$joins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'userfield AS userfield ON (user.userid = userfield.userid)';
		$joins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'usertextfield AS usertextfield ON (user.userid = usertextfield.userid)';

		$where[] = 'issuenote.issuenoteid IN (' . implode(',', $ids) . ')';

		if ($vbulletin->options['avatarenabled'])
		{
			$select[] = 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar,
						customavatar.dateline AS avatardateline, customavatar.width AS width, customavatar.height AS height,
						customavatar.height_thumb AS height_thumb, customavatar.width_thumb AS width_thumb, customavatar.filedata_thumb';
			$joins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'avatar AS avatar ON (avatar.avatarid = user.avatarid)';
			$joins[] = 'LEFT JOIN ' . TABLE_PREFIX . 'customavatar AS customavatar ON (customavatar.userid = user.userid)';
		}

		// Get all the issue note and user data in one go
		$set = $vbulletin->db->query_read_slave("
			SELECT " . implode(",", $select) . "
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				" . implode("\n", $joins) . "
			WHERE " . implode (' AND ', $where) . "
		");

		//ensure that $items is in the same order as $ids
		$items = array_fill_keys($ids, false);
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$issue = isset($issue_map[$row['issueid']]) ? $issue_map[$row['issueid']] : null;
			$items[$row['issuenoteid']] = vB_Legacy_IssueNote::create_from_record($row, $issue);
		}

		$items = array_filter($items);
		return $items;
	}

	/**
	 * Constructor -- protectd to force use of factory methods.
	 */
	protected function __construct()
	{
		$this->registry = $GLOBALS['vbulletin'];
	}

	/**
	 * Is this the first issue note in the issue
	 *
	 * @return boolean
	 */
	public function is_first()
	{
		if (is_null($this->var_is_first))
		{
			// find out if first issue note
			$getpost = $this->registry->db->query_first("
				SELECT issuenoteid
				FROM " . TABLE_PREFIX . "pt_issuenote
				WHERE issueid = " . intval($this->record['issueid']) . "
				ORDER BY dateline
				LIMIT 1
			");
			$this->var_is_first = ($getpost['issuenoteid'] == $this->record["issuenoteid"]);
		}
		return $this->var_is_first;
	}

	/**
	 * Get the user for this issue note
	 *
	 * @return		vB_Legacy_User
	 */
	public function get_user()
	{
		if (is_null($this->user))
		{
			return vB_Legacy_User::createFromId($this->record['userid']);
		}

		return $this->user;
	}

	/**
	 * Get the poster's ip address as a string
	 *
	 * @return sting
	 */
	public function get_ipstring()
	{
		return $this->record['ipaddress'];
	}

	/**
	 * Get the poster's ip address as an integer(long)
	 *
	 * @return int
	 */
	public function get_iplong()
	{
		return ip2long($this->record['ipaddress']);
	}

	/**
	 * Get the summary text for the issue note
	 *
	 * @param int $length maximum length of the summary text
	 * @return string
	 */
	public function get_summary($length)
	{
		$strip_quotes = true;
		$page_text = $this->get_pagetext_noquote();

		// Deal with the case that quote was the only content of the issue note
		if (trim($page_text) == '')
		{
			$page_text = $this->get_field('pagetext');
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes, false, false), $length))));
	}

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function get_pagetext_noquote()
	{
		//figure out how to handle the 'cancelwords'
		$display['highlight'] = array();
		/*return preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe', "process_quote_removal('\\3', \$display['highlight'])", $this->get_field('pagetext'));*/
		return preg_replace_callback('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siU', "process_quote_removal_callback", $this->get_field('pagetext'));
	}


	/**
	 * Return title string for display. If there is no title given, we'll construct one from the issue note text
	 *
	 * @return		string		Title of the issue note
	 */
	public function get_display_title()
	{
		$title = $this->get_field('title');
		if ($title == '')
		{
			$title = fetch_trimmed_title(strip_bbcode($this->get_field('pagetext'), true, false, true), 50);
		}
		else
		{
			$title = fetch_censored_text($title);
		}

		return $title;
	}

	/**
	 * Can the user view the issue note as a search result
	 *
	 * @param		mixed		vB_Legacy_CurrentUser: Current user informations
	 *
	 * @return		boolean
	 */
	public function can_search($user)
	{
		return true;
	}

	/**
	 * Get the issue containing the issue note
	 *
	 * @return		mixed		vB_Legacy_Issue: All infos about the issue
	 */
	public function get_issue()
	{
		if (is_null($this->issue))
		{
			$this->issue = vB_Legacy_Issue::create_from_id($this->get_field('issueid'));
		}

		return $this->issue;
	}

	/**
	 * The issue containing the issue note
	 *
	 * @param vB_Legacy_Issue $issue
	 */
	protected function set_issue($issue)
	{
		$this->issue = $issue;
	}

	/**
	 * @var vB_Registry
	 */
	protected $registry = null;
	protected $user = null;
	protected $issue = null;
}

?>