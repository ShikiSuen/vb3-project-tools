<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
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
 * @version		$Rev$
 * @date		$Date$
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

	/*
	 * Don't get: Deleted & moderated issues
	 *
	 */
	public static function populate()
	{
		if (!(vB::$vbulletin->options['as_content'] & vB::$vbulletin->bf_misc_ascontent['project_issuenote']))
		{
			return;
		}

		vB::$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "activitystream
			WHERE
				section = 'project'
					AND
				type = 'issuenote'
		");

		$timespan = TIMENOW - vB::$vbulletin->options['as_expire'] * 60 * 60 * 24;
		vB::$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "activitystream
				(userid, dateline, section, contentid, type, action)
				(SELECT
					issuenote.userid, issuenote.dateline, 'project', issuenote.issuenoteid, 'issuenote', 'create'
				FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
				INNER JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issuenote.issueid = issue.issueid)
				WHERE
					issuenote.dateline >= {$timespan}
						AND
					issuenote.visible IN ('visible', 'private')
						AND
					issuenote.issuenoteid <> issue.firstnoteid
						AND
					issue.state = 'open'
						AND
					issue.visible IN ('visible', 'private')
				)
		");
	}
}

?>