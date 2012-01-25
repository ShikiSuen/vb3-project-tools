<?php

error_reporting(E_ALL & ~E_NOTICE);

$phrasegroups = array();
$actiontemplates = array();
$globaltemplates = array();
$specialtemplates = array();

require_once('./global.php');

$issueidlist = $db->query_read("
	SELECT issueid
	FROM " . TABLE_PREFIX . "pt_issue
");

while ($issue = $db->fetch_array($issueidlist))
{
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "pt_issuemagicselect
			(issueid)
		VALUES
			(" . $issue['issueid'] . ")
	");
}