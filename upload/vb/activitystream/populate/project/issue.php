<?php
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

/**
 * Class to populate the activity stream from existing content
 *
 * @package		vBulletin Project Tools
 * @since		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
 * @version		$Rev: 897 $
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vB_ActivityStream_Populate_Project_Issue extends vB_ActivityStream_Populate_Base
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
	 * Don't get: Deleted & Moderated issues
	 */
	public function populate()
	{
		if (!vB::$vbulletin->products['vbprojecttools'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issue']['typeid'];
		$this->delete($typeid);

		if (!vB::$vbulletin->activitystream['project_issue']['enabled'])
		{
			return;
		}

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					submituserid, submitdate, issueid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "pt_issue
				WHERE
					submitdate >= {$timespan}
						AND
					state = 'open'
						AND
					visible IN ('visible', 'private')
				)
		");
	}

	/**
	 * Rebuild stream for one or more issues
	 *
	 * @param	array	list of issueids
	 */
	public static function rebuild_issue($issueids)
	{
		if (!is_array($issueids) OR empty($issueids))
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issue']['typeid'];

		// Delete issue data
		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				typeid = {$typeid}
					AND
				contentid IN (" . implode(",", $issueids) . ")
		");

		$typeid = vB::$vbulletin->activitystream['project_issuenote']['typeid'];

		// Delete issuenote data
		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				typeid = {$typeid}
					AND
				contentid IN (SELECT issuenoteid FROM " . TABLE_PREFIX . "pt_issuenote WHERE issueid IN(" . implode(",", $issueids) . "))
		");

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;

		if (!vB::$vbulletin->activitystream['project_issue']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issue']['typeid'];

		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					submituserid, submitdate, issueid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "pt_issue
				WHERE
					submitdate >= {$timespan}
						AND
					state = 'open'
						AND
					issueid IN (" . implode(",", $issueids) . ")
				)
		");

		if (!vB::$vbulletin->activitystream['project_issuenote']['enabled'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issuenote']['typeid'];

		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, '{typeid}', action)
				(SELECT
					isn.userid, isn.dateline, isn.issuenoteid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "pt_issuenote AS isn
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS i ON (isn.issueid = i.issueid)
				WHERE
					isn.dateline >= {$timespan}
						AND
					isn.issuenoteid <> i.firstnoteid
						AND
					isn.type = 'user'
						AND
					i.state = 'open'
						AND
					i.issueid IN (" . implode(",", $issueids) . ")
				)
		");
	}
}

?>