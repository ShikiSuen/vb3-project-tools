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
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 30597 $
 * @since $Date: 2009-04-30 15:25:07 -0700 (Thu, 30 Apr 2009) $
 * @copyright Jelsoft Enterprises Ltd.
 */

require_once (DIR . '/vb/search/type.php');


/**
 * vBProjectTools_Search_Type_Project
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: forum.php 30597 2009-04-30 22:25:07Z ksours $
 * @access public
 */
class vBProjectTools_Search_Type_Project extends vB_Search_Type
{

// ###################### Start create_item ######################
/**
* This creates the type object
*
* @param integer $id
* @return object vBProjectTools_Search_Result_Project
*/
	public function create_item($id)
	{
		return vBProjectTools_Search_Result_Project::create($id);
	}

/**
* This returns the display name
*
* @return string
*/
	public function get_display_name()
	{
		return $GLOBALS['vbphrase']['project'];
	}

/**
* Each search type has some responsibilities, one of which is to tell
* whether it is searchable
*
* @return true
*/
	public function cansearch()
	{
		return true;
	}
/**
 * This prepares the HTML for the user to search for forums
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

	public function add_advanced_search_filters($criteria, $registry)
	{
		if ($registry->GPC['threadlimit'])
		{
			$criteria->add_display_strings('forumthreadlimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['threadless'])
				. $registry->GPC['threadlimit'] . ' ' . $vbphrase['threads']);
			$op = $registry->GPC['threadless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('forumthreadlimit', $op, $registry->GPC['threadlimit'], true);
		}

		if ($registry->GPC['postlimit'])
		{
			$criteria->add_display_strings('forumthreadlimit',
				vB_Search_Searchtools::getCompareString($registry->GPC['postless'])
				. $registry->GPC['postlimit'] . ' ' . $vbphrase['posts']);

			$op = $registry->GPC['postless'] ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('forumpostlimit', $op, $registry->GPC['postlimit'], true);
		}

		if ($registry->GPC['forumdateline'])
		{
			if (is_numeric($registry->GPC['forumdateline']))
			{
				$dateline = TIMENOW - ($this->forumdateline * 86400);
			}
			else
			{
				$current_user = new vB_Legacy_CurrentUser();;
				$dateline = $current_user->get_field('lastvisit');
			}

			$op = $registry->GPC['beforeafter'] == 'before' ? vB_Search_Core::OP_LT : vB_Search_Core::OP_GT;
			$criteria->add_filter('forumpostdateline', $op, $dateline, true);
			$this->set_display_date($criteria, $registry->GPC['forumdateline'], $registry->GPC['beforeafter']);

		}
	}

	public function get_db_query_info($fieldname)
	{
		$result['join']['forum'] = sprintf(self::$forum_join, TABLE_PREFIX,
			vB_Types::instance()->getContentTypeId("vBProjectTools_Project"));
		$result['table'] = 'forum';

		if ($fieldname == 'forumthreadlimit')
		{
			$result['field'] = 'threadcount';
		}
		else if ($fieldname == 'forumpostlimit')
		{
			$result['field'] = 'replycount';
		}
		else if ($fieldname == 'forumdateline')
		{
			$result['field'] = 'lastpost';
		}
		else
		{
			return false;
		}

		return $result;
	}

	/**
	 * This function sets the display date for the forum search.
	 * takes no parameters and returns none
	 *
	 * @return nothing
	 */
	private function set_display_date($criteria, $forumdateline, $beforeafter)
	{
		global $vbphrase, $vbulletin;
		if (isset($beforeafter) AND isset($forumdateline))
		{
			if (is_numeric($forumdateline))
			{
				$dateline = TIMENOW - ($forumdateline * 86400);
				$criteria->add_display_strings('forumpostdateline',
				$vbphrase['last_post'] . ' ' . $vbphrase[$beforeafter] . ' '
					. date($vbulletin->options['dateformat'], $dateline));
			}
			else
			{
				$criteria->add_display_strings('forumpostdateline',
				$vbphrase['last_post'] . ' ' . $vbphrase[$beforeafter] . ' '
					. $vbphrase['last_visit'] );
			}
		}

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
			'beforeafter'  => 'after');
	}

	protected $package = "vBProjectTools";
	protected $class = "Project";

//	private $threadless;
//	private $threadlimit;
//	private $forumdateline;
//	private $postless;
//	private $postlimit;
//	private $beforeafter;

	private static $forum_join =
		"JOIN %sforum AS forum ON (
			searchcore.contenttypeid = %u AND searchcore.primaryid = forum.forumid)
		";

	protected $type_globals = array (
		'threadless'     => TYPE_UINT,
		'threadlimit'    => TYPE_UINT,
		'forumdateline'  => TYPE_NOHTML,
		'postless'       => TYPE_UINT,
		'postlimit'      => TYPE_UINT,
		'beforeafter'    => TYPE_NOHTML);
}

