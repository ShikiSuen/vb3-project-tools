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
require_once(DIR . '/vb/legacy/project.php');

/**
 * Legacy functions for issues
 *
 * @package		vBulletin Project Tools
 * @since		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
 * @version		$Rev: 897 $
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vB_Legacy_Project extends vB_Legacy_DataObject
{
	/**
	* Create object from and existing record
	*
	* @param int 	$projectinfo
	*
	* @return 		vB_Legacy_Project
	*/
	public static function create_from_record($projectinfo)
	{
		$project = new vB_Legacy_Project();
		$project->set_record($projectinfo);
		return $project;
	}

	/**
	* Load object from an id
	*
	* @param int 	$id
	*
	* @return 		vB_Legacy_Project
	*/
	public static function create_from_id($id)
	{
		//the cache get prefilled with abbreviated data that is *different* from what
		//the query in fetch_foruminfo provides. We can skip the cache, but that means
		//we never cache, even if we want to.
		//this is going to prove to be a problem.
		
		//There is an incomplete copy stored in cache. Not sure why,
		// but it consistently doesn't give me the lastthreadid unless I pass "false"
		// to prevent reading from cache
		require_once(DIR . '/includes/functions_projecttools.php');
		$projectinfo = fetch_project_info($id, false);

		//try to work with bad data integrity.  There are dbs out there
		//with threads that belong to a nonexistant forum.
		if ($projectinfo)
		{
			return self::create_from_record($projectinfo);
		}
		else
		{
			return null;
		}
	}

	/**
	* constructor -- protectd to force use of factory methods.
	*/
	protected function __construct() {}
}

?>