<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
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
 * @package vBulletin Project Tools
 * @subpackage Legacy
 * @author $author$
 * @version $revision$
 * @since $date$
 * @copyright http://www.vbulletin.org/open_source_license_agreement.php
 */

/**
 * Legacy functions for issue notes
 *
 * @package vBulletin Project Tools
 * @subpackage Legacy
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
	* Load object from an id
	*
	* @param	integer		Id of the issuenote
	* @return	array		Array of the result
	*/
	public static function create_from_id($id)
	{
		$list = array_values(self::create_array(array($id)));

		if (!count($list))
		{
			return null;
		}
		else
		{
			return array_shift($list);
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

		$select = array();
		$joins = array();
		$where = array();

		$select[] = "issuenote.*";
		$where[] = "issuenote.issuenoteid IN (" . implode(',', array_map('intval', $ids)) . ")";

		$set = $vbulletin->db->query("
			SELECT " . implode(",", $select) . "
			FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				" . implode("\n", $joins) . "
			WHERE " . implode (' AND ', $where) . "
		");

		$issuenotes = array();
		while ($issuenote = $vbulletin->db->fetch_array($set))
		{
			$issuenotes[$issuenote['issuenoteid']] = $issuenote;
		}

		return $issuenotes;
	}

	/**
	 * constructor -- protectd to force use of factory methods.
	 */
	protected function __construct() {}

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
		return preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe', "process_quote_removal('\\3', \$display['highlight'])", $this->get_field('pagetext'));
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

	//*********************************************************************************
	// High level permissions

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

	//*********************************************************************************
	// Related data/data objects

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

	//*********************************************************************************
	// Internal Setters for initializer functions

	/**
	 * The issue containing the issue note
	 *
	 * @param vB_Legacy_Issue $issue
	 */
	protected function set_issue($issue)
	{
		$this->issue = $issue;
	}

	//*********************************************************************************
	// Data

	/**
	 * @var vB_Registry
	 */
	protected $registry = null;

	//some lazy loading storage.
	protected $user = null;
	protected $issue = null;
}

?>