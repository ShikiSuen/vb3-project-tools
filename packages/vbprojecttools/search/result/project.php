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
 * @version $Revision: 30444 $
 * @since $Date: 2009-04-23 15:02:08 -0700 (Thu, 23 Apr 2009) $
 * @copyright Jelsoft Enterprises Ltd.
 */

require_once (DIR . '/vb/search/result.php');
require_once (DIR . '/vb/legacy/forum.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBProjectTools_Search_Result_Project extends vB_Search_Result
{

	public static function create($id)
	{
		$result = new vBProjectTools_Search_Result_Project();
		return $result;
	}

	protected function __construct() {}


	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid("vBProjectTools", "Project");
	}

	public function can_search($user)
	{
		return true;
	}

	public function render($current_user, $criteria)
	{
		return '<b>Got One<b><br />';
	}

	private $forum;
}

