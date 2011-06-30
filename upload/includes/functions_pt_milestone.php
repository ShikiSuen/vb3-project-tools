<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Fetches milestone count information for specified criteria.
*
* @param	string	SQL criteria
*
* @return	array	[milestoneid][type] => info
*/
function fetch_milestone_count_data($criteria)
{
	global $vbulletin;

	if (!$criteria)
	{
		$criteria = '1=1';
	}

	$counts = array();
	$count_data = $vbulletin->db->query_read("
		SELECT milestonetypecount.*
		FROM " . TABLE_PREFIX . "pt_milestone AS milestone
		INNER JOIN " . TABLE_PREFIX . "pt_milestonetypecount AS milestonetypecount ON
			(milestone.milestoneid = milestonetypecount.milestoneid)
		WHERE $criteria
	");
	while ($count = $vbulletin->db->fetch_array($count_data))
	{
		$counts["$count[milestoneid]"]["$count[issuetypeid]"] = $count;
	}

	return $counts;
}

/**
* Fetch effective counts for an entire milestone.
*
* @param	array	Array of counts, grouped by issue type. Results from DB.
* @param	array	Array of project permissions.
*
* @param	array	Array of counter information
*/
function fetch_milestone_counts($typecounts, $projectperms)
{
	global $vbulletin;

	if (!is_array($typecounts))
	{
		$typecounts = array();
	}

	$total_issues_raw = 0;
	$total_completed_raw = 0;

	foreach ($typecounts AS $issuetypeid => $counts)
	{
		if (!($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
		{
			continue;
		}

		$total_issues_raw += $counts['activepublic'] + $counts['completepublic'];
		$total_completed_raw += $counts['completepublic'];

		if ($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewprivateothers'])
		{
			$total_issues_raw += $counts['activeprivate'] + $counts['completeprivate'];
			$total_completed_raw += $counts['completeprivate'];
		}
	}

	$total_active_raw = $total_issues_raw - $total_completed_raw;
	$percent_completed_raw = ($total_issues_raw ? round(100 * $total_completed_raw / $total_issues_raw) : 0);

	return array(
		'total_issues' => $total_issues_raw,
		'total_completed' => $total_completed_raw,
		'total_active' => $total_active_raw,
		'percent_completed' => $percent_completed_raw
	);
}

/**
* Creates the milestone stats array (target date, total issues, progress).
* Array contains formatted data.
*
* @param	array	Array of milestone info
* @param	array	Array of raw count info
*
* @return	array	Formatted stats data
*/
function prepare_milestone_stats($milestone, $raw_counts)
{
	global $vbulletin;

	$stats = array(
		'total_issues' => vb_number_format($raw_counts['total_issues']),
		'total_completed' => vb_number_format($raw_counts['total_completed']),
		'total_active' => vb_number_format($raw_counts['total_active']),
		'percent_completed' => vb_number_format($raw_counts['percent_completed']),
	);

	if ($milestone['completeddate'])
	{
		$stats['completed_date'] = vbdate($vbulletin->options['dateformat'], $milestone['completeddate']);
		$stats['milestone_overdue'] = false;
	}
	else
	{
		$stats['target_date'] = vbdate($vbulletin->options['dateformat'], $milestone['targetdate']);
		$stats['milestone_overdue'] = ($milestone['targetdate'] AND $milestone['targetdate'] < TIMENOW);
	}

	return $stats;
}

/**
* Fetches the list of viewable milestone issue types for the selected permissions.
*
* @param	array	Array of project permissions
*
* @param	array	List of issue types that you can view milestone info from
*/
function fetch_viewable_milestone_types($projectperms)
{
	global $vbulletin;

	$milestone_types = array();

	foreach ($vbulletin->pt_issuetype AS $issuetypeid => $typeinfo)
	{
		if ($projectperms["$issuetypeid"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']
			AND $projectperms["$issuetypeid"]['generalpermissions'] &$vbulletin->pt_bitfields['general']['canviewmilestone'])
		{
			$milestone_types[] = $issuetypeid;
		}
	}

	return $milestone_types;
}

/**
* Verifies that a milestone is valid and returns it. Errors if invalid.
*
* @param	integer	Milestone ID
*
* @return	array	Milestone info
*/
function verify_milestone($milestoneid)
{
	global $vbulletin, $vbphrase;

	$milestoneid = intval($milestoneid);
	$milestone = $vbulletin->db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_milestone
		WHERE milestoneid = $milestoneid
	");

	if (!$milestone)
	{
		standard_error(fetch_error('invalidid', $vbphrase['milestone'], $vbulletin->options['contactuslink']));
	}

	$milestone['title'] = $milestone['title_clean'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_name'];
	$milestone['description'] = $vbphrase['milestone_' . $milestone['milestoneid'] . '_description'];

	return $milestone;
}

?>