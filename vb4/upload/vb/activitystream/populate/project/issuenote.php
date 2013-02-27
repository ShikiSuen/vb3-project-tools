<?php
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

/**
 * Class to populate the activity stream from existing content
 *
 * @package		vBulletin Project Tools
 * @since		$Date$
 * @version		$Rev$
 * @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
 */
class vB_ActivityStream_Populate_Project_IssueNote extends vB_ActivityStream_Populate_Base
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
	 * Don't get: Deleted & moderated issues
	 */
	public function populate()
	{
		if (!vB::$vbulletin->products['vbprojecttools'])
		{
			return;
		}

		$typeid = vB::$vbulletin->activitystream['project_issuenote']['typeid'];
		$this->delete($typeid);

		if (!vB::$vbulletin->activitystream['project_issuenote']['enabled'])
		{
			return;
		}

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, contentid, typeid, action)
				(SELECT
					isn.userid, isn.dateline, isn.issuenoteid, '{$typeid}', 'create'
				FROM " . TABLE_PREFIX . "pt_issuenote AS isn
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS i ON (isn.issueid = i.issueid)
				WHERE
					isn.dateline >= {$timespan}
						AND
					isn.visible IN ('visible', 'private')
						AND
					isn.issuenoteid <> i.firstnoteid
						AND
					isn.type IN ('user', 'system', 'petition')
						AND
					i.state = 'open'
						AND
					i.visible IN ('visible', 'private')
				)
		");
	}
}

?>