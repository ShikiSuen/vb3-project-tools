<?php
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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NOSHUTDOWNFUNC', 1);
define('SKIP_SESSIONCREATE', 1);
define('DIE_QUIETLY', 1);
define('THIS_SCRIPT', 'projectexternal');
define('CSRF_PROTECTION', true);
define('PROJECT_SCRIPT', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('projecttools');

// get special data templates from the datastore
$specialtemplates = array(
	'pt_bitfields',
	'pt_projects',
	'pt_permissions'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'bbcode_code_printable',
	'bbcode_html_printable',
	'bbcode_php_printable',
	'bbcode_quote_printable',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	exit;
}

require_once(DIR . '/includes/functions_projecttools.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	exit;
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// We don't want no stinkin' sessionhash
$vbulletin->session->vars['sessionurl'] =
$vbulletin->session->vars['sessionurl_q'] =
$vbulletin->session->vars['sessionurl_js'] =
$vbulletin->session->vars['sessionhash'] = '';

if(!$vbulletin->options['externalxml'])
{
	exit;
}

$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'issuetypeid' => TYPE_NOHTML,
	'issuestatusid' => TYPE_UINT,
	'issuereportid' => TYPE_UINT
));

($hook = vBulletinHook::fetch_hook('projectexternal_start')) ? eval($hook) : false;

$projects = array();
$project_viewing = array();

$project_query = $db->query_read("
	SELECT *
	FROM " . TABLE_PREFIX . "pt_project
	" . ($vbulletin->GPC['projectid'] ? "WHERE projectid = " . $vbulletin->GPC['projectid'] : '') . "
	ORDER BY displayorder
");
while ($project = $db->fetch_array($project_query))
{
	$viewable = array();
	$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
	foreach ($projectperms AS $issuetypeid => $issueperms)
	{
		if (($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview'])
			AND ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canviewothers']))
		{
			$viewable["$issuetypeid"] = "'" . $db->escape_string($issuetypeid) . "'";
		}
	}

	if ($viewable)
	{
		$projects["$project[projectid]"] = $project;
		$project_viewing["$project[projectid]"] = $viewable;
	}
}

if (empty($projects))
{	// no access to view selected forums
	exit;
}

if ($vbulletin->GPC['issuereportid'])
{
	if (!$search_perms = build_issue_permissions_query($vbulletin->userinfo, 'cansearch'))
	{
		exit;
	}

	$report = $db->query_first_slave("
		SELECT issuereport.*, IF(issuereportsubscribe.issuesearchid IS NOT NULL, 1, 0) AS issubscribed,
			issuesearch.issuesearchid
		FROM " . TABLE_PREFIX . "pt_issuereport AS issuereport
		LEFT JOIN " . TABLE_PREFIX . "pt_issuereportsubscribe AS issuereportsubscribe ON
			(issuereportsubscribe.issuereportid = issuereport.issuereportid AND issuereportsubscribe.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "pt_issuesearch AS issuesearch ON
			(issuesearch.issuesearchid = issuereportsubscribe.issuesearchid)
		WHERE issuereport.issuereportid = " . $vbulletin->GPC['issuereportid'] . "
			AND issuereport.public = 1
	");
	if (!$report)
	{
		exit;
	}
}

if (!$vbulletin->options['externalcount'])
{
	$vbulletin->options['externalcount'] = 15;
}
$count = $vbulletin->options['externalcount'];

if (!intval($vbulletin->options['externalcache']) OR $vbulletin->options['externalcache'] > 1440)
{
	$externalcache = 60;
}
else
{
	$externalcache = $vbulletin->options['externalcache'];
}

$cachetime = $externalcache * 60;
$cachehash = md5(
	$vbulletin->options['externalcutoff'] . '|' .
	$externalcache . '|' .
	$count . '|' .
	$vbulletin->GPC['projectid'] . '|' .
	$vbulletin->GPC['issuetypeid'] . '|' .
	$vbulletin->GPC['issuestatusid'] . '|' .
	$vbulletin->GPC['issuereportid'] . '|' .
	serialize($project_viewing)
);

if ($_SERVER['HTTP_IF_NONE_MATCH'] == "\"$cachehash\"" AND !empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
{
	$timediff = strtotime(gmdate('D, d M Y H:i:s') . ' GMT') - strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
	if ($timediff <= $cachetime)
	{
		$db->close();
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 304 Not Modified');
		}
		else
		{
			header('HTTP/1.1 304 Not Modified');
		}
		exit;
	}
}

if ($foundcache = $db->query_first_slave("
	SELECT text, headers, dateline
	FROM " . TABLE_PREFIX . "externalcache
	WHERE cachehash = '" . $db->escape_string($cachehash) . "' AND
		 dateline >= " . (TIMENOW - $cachetime) . "
"))
{
	$db->close();
	if (!empty($foundcache['headers']))
	{
		$headers = unserialize($foundcache['headers']);
		if (!empty($headers))
		{
			foreach($headers AS $header)
			{
				header($header);
			}
		}
	}
	echo $foundcache['text'];
	exit;
}

$cutoff = (!$vbulletin->options['externalcutoff']) ? 0 : TIMENOW - $vbulletin->options['externalcutoff'] * 86400;

if ($vbulletin->GPC['issuereportid'])
{
	// we have the ability to pull any report into an RSS feed
	require_once(DIR . '/includes/class_pt_issuesearch.php');
	$search = new vB_Pt_IssueSearch($vbulletin);

	foreach (unserialize($report['criteria']) AS $name => $value)
	{
		$search->add($name, $value);
	}

	$search->set_sort($report['sortby'], $report['sortorder']);
	$search->set_group($report['groupby']);

	$criteria = $search->generator->generate();
	if (!$criteria['where'])
	{
		$criteria['where'] = '1=1';
	}

	$search_results = $db->query_read_slave("
		SELECT issue.issueid AS id
		FROM " . TABLE_PREFIX . "pt_issue AS issue
		$criteria[joins]
		WHERE $criteria[where]
			AND issue.visible = 'visible'
			" . ($search_perms ? "AND ((" . implode(') OR (', $search_perms) . "))" : '') . "
		ORDER BY " . $search->sort . ' ' . $search->sortorder . "
		LIMIT $count
	");

	$condition = 'issue.issueid IN (0';
	while ($result = $db->fetch_array($search_results))
	{
		$condition .= ",$result[id]";
	}
	$condition .= ')';

	$order_by = $search->sort . ' ' . $search->sortorder;
}
else
{
	// build the where clause
	if ($vbulletin->GPC['issuetypeid'])
	{
		$condition = "issue.issuetypeid = '" . $db->escape_string($vbulletin->GPC['issuetypeid']) . "' AND (1=0 ";
	}
	else
	{
		$condition = '(1=0 ';
	}

	foreach ($project_viewing AS $projectid => $viewable)
	{
		$condition .= "OR (issue.projectid = $projectid AND issue.issuetypeid IN (" . implode(',', $viewable) . "))";
	}
	$condition .= ")";

	if ($vbulletin->GPC['issuestatusid'])
	{
		$condition .= "AND issue.issuestatusid = " . $vbulletin->GPC['issuestatusid'];
	}

	$order_by = 'issue.lastpost DESC';
}

$issuecache = array();
$issues = $db->query_read_slave("
	SELECT issue.*,
		project.title_clean AS project_title_clean,
		issuenote.pagetext AS firstnote_text
	FROM " . TABLE_PREFIX . "pt_issue AS issue
	INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
	INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issuenoteid = issue.firstnoteid)
	WHERE $condition
		AND issue.visible = 'visible'
	ORDER BY $order_by
	LIMIT $count
");

$expires = TIMENOW + $cachetime;

$output = '';
$headers = array();

// setup the board title

$rsstitle = $vbulletin->options['bbtitle'] . " - Project Tools";
if (!empty($report['title']))
{
	$rsstitle .= " - $report[title]";
}
$rssicon = create_full_url(vB_Template_Runtime::fetchStyleVar('imgdir_misc') . '/rss.jpg');

$headers[] = 'Cache-control: max-age=' . $expires;
$headers[] = 'Expires: ' . gmdate("D, d M Y H:i:s", $expires) . ' GMT';
//$headers[] = 'Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT';
$headers[] = 'ETag: "' . $cachehash . '"';
$headers[] = 'Content-Type: text/xml' . (vB_Template_Runtime::fetchStyleVar('charset') != '' ? '; charset=' .  vB_Template_Runtime::fetchStyleVar('charset') : '');

$output = '<?xml version="1.0" encoding="' . vB_Template_Runtime::fetchStyleVar('charset') . '"?>' . "\r\n\r\n";

require_once(DIR . '/includes/class_xml.php');
$xml = new vB_XML_Builder($vbulletin);
$rsstag = array(
	'version'       => '2.0',
	'xmlns:dc'      => 'http://purl.org/dc/elements/1.1/',
	'xmlns:content' => 'http://purl.org/rss/1.0/modules/content/'
);
$xml->add_group('rss', $rsstag);
	$xml->add_group('channel');
		$xml->add_tag('title', $rsstitle);
		$xml->add_tag('link', $vbulletin->options['bburl'] . "/project.php", array(), false, true);
		//$xml->add_tag('description', $description);
		$xml->add_tag('language', vB_Template_Runtime::fetchStyleVar('languagecode'));
		$xml->add_tag('lastBuildDate', gmdate('D, d M Y H:i:s') . ' GMT');
		#$xml->add_tag('pubDate', gmdate('D, d M Y H:i:s') . ' GMT');
		$xml->add_tag('generator', 'vBulletin');
		$xml->add_tag('ttl', $externalcache);
		$xml->add_group('image');
			$xml->add_tag('url', $rssicon);
			$xml->add_tag('title', $rsstitle);
			$xml->add_tag('link', $vbulletin->options['bburl'] . "/project.php", array(), false, true);
		$xml->close_group('image');

require_once(DIR . '/includes/class_bbcode_alt.php');

$i = 0;
$viewattachedimages = $vbulletin->options['viewattachedimages'];
$attachthumbs = $vbulletin->options['attachthumbs'];

// list returned threads
while ($issue = $db->fetch_array($issues))
{
	$xml->add_group('item');
		$xml->add_tag('title', unhtmlspecialchars($issue['title']));
		$xml->add_tag('link', $vbulletin->options['bburl'] . "/project.php?issueid=$issue[issueid]", array(), false, true);
		$xml->add_tag('pubDate', gmdate('D, d M Y H:i:s', $issue['submitdate']) . ' GMT');

	$plaintext_parser = new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$plainmessage = $plaintext_parser->parse($issue['firstnote_text'], 'pt');
	unset($plaintext_parser);

	if ($vbulletin->GPC['fulldesc'])
	{
		$xml->add_tag('description', $plainmessage);
	}
	else
	{
		$xml->add_tag('description', fetch_trimmed_title($plainmessage, $vbulletin->options['threadpreview']));
	}

	$xml->add_tag('category', unhtmlspecialchars($issue['project_title_clean']), array('domain' => $vbulletin->options['bburl'] . "/project.php?projectid=$issue[projectid]"));
	$xml->add_tag('dc:creator', unhtmlspecialchars($issue['submitusername']));
	$xml->add_tag('guid', $vbulletin->options['bburl'] . "/project.php?issueid=$issue[issueid]", array('isPermaLink' => 'true'));

	$xml->close_group('item');
}

	$xml->close_group('channel');
$xml->close_group('rss');
$output .= $xml->output();
unset($xml);

$db->query_write("
	REPLACE INTO " . TABLE_PREFIX . "externalcache
		(cachehash, dateline, text, headers, forumid)
	VALUES
		(
			'" . $db->escape_string($cachehash) . "',
			" . TIMENOW . ",
			'" . $db->escape_string($output) . "',
			'" . $db->escape_string(serialize($headers)) . "',
			0
		)
");
$db->close();

foreach ($headers AS $header)
{
	header($header);
}
echo $output;

($hook = vBulletinHook::fetch_hook('projectexternal_complete')) ? eval($hook) : false;
?>
