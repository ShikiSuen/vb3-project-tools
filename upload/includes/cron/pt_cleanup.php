<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.3                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// note hashes are only valid for 5 minutes
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "pt_issuenotehash
	WHERE dateline < " . (TIMENOW - 300)
);

// No longer needed as of PT 2.1.x (#100)
// $mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
// define('MYSQL_VERSION', $mysqlversion['version']);

//searches expire after one hour
$vbulletin->db->query_write("
	DELETE issuesearch, issuesearchresult
	FROM " . TABLE_PREFIX . "pt_issuesearch AS issuesearch
	LEFT JOIN " . TABLE_PREFIX . "pt_issuesearchresult AS issuesearchresult ON (issuesearchresult.issuesearchid = issuesearch.issuesearchid)
	WHERE issuesearch.dateline < " . (TIMENOW - 3600)
);

// remove old issue read marking data
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "pt_issueread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);

// remove old project read marking data
$vbulletin->db->query_write("
	DELETE FROM " . TABLE_PREFIX . "pt_projectread
	WHERE readtime < " . (TIMENOW - ($vbulletin->options['markinglimit'] * 86400))
);

log_cron_action('', $nextitem, 1);

?>