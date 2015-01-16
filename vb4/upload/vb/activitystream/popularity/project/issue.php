<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Class to update the popularity score of stream items
 *
 * @package		vBulletin Project Tools
 * @since		$Date$
 * @version		$Rev$
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vB_ActivityStream_Popularity_Project_Issue extends vB_ActivityStream_Popularity_Base
{
	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct()
	{
		return parent::__construct();
	}

	/**
	 * Update popularity score
	 */
	public static function updateScore()
	{
		if (!vB::$vbulletin->products['vbprojecttools'])
		{
			return;
		}

		if (!vB::$vbulletin->activitystream['project_issue']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issue']['typeid'];

		vB::$db->query_write("
			UPDATE " . TABLE_PREFIX . "activitystream AS a
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS i ON (a.contentid = i.issueid)
			SET
				a.score = (1 + ((i.replycount) / 10) + (i.votepositive + i.votenegative / 100) )
			WHERE
				a.typeid = {$typeid}
		");
	}
}

?>