<?php if (!defined('VB_ENTRY')) die('Access denied.');

/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

require_once (DIR . '/vb/search/type.php');

/**
 * vBProjectTools_Search_Type_Project
 *
 * @package		vBulletin Project Tools
 * @since		$Date$
 * @version		$Rev$
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vBProjectTools_Search_Type_Project extends vB_Search_Type
{
	/**
	 * This creates the type object
	 *
	 * @param integer $id
	 *
	 * @return object vBProjectTools_Search_Result_Project
	 */
	public function create_item($id)
	{
		global $vbulletin;

		$datastores = $vbulletin->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN('pt_bitfields','pt_permissions','pt_issuestatus','pt_issuetype','pt_projects','pt_categories','pt_assignable','pt_versions')
		");

		while ($datastore = $vbulletin->db->fetch_array($datastores))
		{
			$title = $datastore['title'];
			$data = $datastore['data'];
			if (!is_array($data))
			{
				$data = unserialize($data);
				if (is_array($data))
				{
					$vbulletin->$title = $data;
				}
			}
			else if ($data != '')
			{
				$vbulletin->$title = $data;
			}
		}

		return vBProjectTools_Search_Result_Project::create($id);
	}

	/**
	 * This returns the display name
	 *
	 * @return string
	 */
	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_projects');
	}

	/**
	 * Each search type has some responsibilities, one of which is to tell
	 * whether it is searchable
	 *
	 * @return true
	 */
	public function cansearch()
	{
		$cansearch = $db->query_read("
			SELECT cansearch
			FROM " . TABLE_PREFIX . "contenttype
			WHERE contenttypeid = " . vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'Issue') . "
		");

		if (!$cansearch)
		{
			return false;
		}

		return true;
	}
	/**
	 * This prepares the HTML for the user to search for forums
	 *
	 * @param	array	Preferences
	 *
	 * @return $html: complete html for the search elements
	 */
	public function listUi($prefs = null)
	{
		global $vbulletin, $show;

		$template = vB_Template::create('search_input_project');
			$template->register('securitytoken', $vbulletin->userinfo['securitytoken']);
			$template->register('contenttypeid', vB_Search_Core::get_instance()->get_contenttypeid('vBProjectTools', 'Project'));
			$template->register('show', $show);

			$this->setPrefs($template, $prefs,  array(
				'select'=> array('titleonly', 'threadless', 'forumdateline', 'beforeafter', 'postless'),
				'cb' => array('nocache'),
				'value' => array('query', 'threadlimit', 'postlimit') ) );

		vB_Search_Searchtools::searchIntroRegisterHumanVerify($template);

		return $template->render();
	}

	/**
	 * Each search type has some responsibilities, one of which is to tell
	 * what are its defaults
	 *
	 * @return array
	 */
	public function additional_pref_defaults()
	{
		return array(
			'textlocation' => '',
			'query'    => '',
			'tags'     => 0,
			'nocache'  => '',
			'lastpost' => 0,
			'beforeafter'  => 'after'
		);
	}

	protected $package = "vBProjectTools";
	protected $class = "Project";

	protected $type_globals = array(
		'threadless'     => TYPE_UINT,
		'threadlimit'    => TYPE_UINT,
		'forumdateline'  => TYPE_NOHTML,
		'postless'       => TYPE_UINT,
		'postlimit'      => TYPE_UINT,
		'beforeafter'    => TYPE_NOHTML
	);
}

