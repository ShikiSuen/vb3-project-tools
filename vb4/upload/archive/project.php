<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('SESSION_BYPASS', 1);
define('THIS_SCRIPT', 'archiveproject');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'forum',
	'projecttools'
);

$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
	'pt_issuestatus',
	'pt_issuetype',
	'pt_projects',
	'pt_categories',
	'pt_assignable',
	'pt_versions',
	'smiliecache',
	'bbcodecache',
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_projecttools.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (SLASH_METHOD AND strpos($archive_info, '/archive/project.php') === false)
{
	exec_header_redirect($vbulletin->options['bburl'] . '/archive/project.php');
}

$projectid = 0;
$issueid = 0;
$p = 0; // page number
$output = '';
$title = $vbulletin->options['bbtitle'] . " - $vbphrase[projects]";

// #######################################################################

$endbit = str_replace('.html', '', $archive_info);
if (SLASH_METHOD)
{
	$endbit = substr(strrchr($endbit, '/') , 1);
}
else if (strpos($endbit, '&') !== false)
{
	$endbit = substr(strrchr($endbit, '&') , 1);
}
if ($endbit != '' AND $endbit != 'project.php')
{
	$queryparts = explode('-', $endbit);
	foreach ($queryparts AS $querypart)
	{
		if ($lastpart != '')
		{
			switch ($lastpart)
			{
				case 'projectid': $projectid = intval($querypart); break;
				case 'issueid': $issueid = intval($querypart); break;
				case 'p': $p = intval($querypart); break;
			}

			$lastpart = '';
		}
		else
		{
			switch ($querypart)
			{
				case 'projectid':
				case 'issueid':
				case 'p':
					$lastpart = $querypart;
					break;
				default:
					$lastpart = '';
			}
		}
	}
}
else
{
	$do = 'index';
}

// #######################################################################

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	exec_header_redirect($vbulletin->options['bburl'] . '/archive/index.php');
	exit;
}

if ($issueid)
{
	$do = 'issue';

	$issue = verify_issue($issueid);
	$project = verify_project($issue['projectid']);

	$title = "$issue[title] [$vbphrase[archive]]" . ($p > 1 ? ' - ' . construct_phrase($vbphrase['page_x'], $p) : '') . " - $title";

	$metatags = "<meta name=\"keywords\" content=\"$issue[title], $project[title_clean], project tools, " . $vbulletin->options['keywords'] . "\" />
		<meta name=\"description\" content=\"[$vbphrase[archive]] $issue[title] " . ($p > 1 ? construct_phrase($vbphrase['page_x'], $p) . " " : "") . "\" />
	";
}
else if ($projectid)
{
	$do = 'project';

	$project = verify_project($projectid);
	$perms_query = build_issue_permissions_query($vbulletin->userinfo);
	if (empty($perms_query["$project[projectid]"]))
	{
		exit;
	}

	$title = "$project[title_clean] [$vbphrase[archive]]" . ($p > 1 ? ' - ' . construct_phrase($vbphrase['page_x'], $p) : '') . " - $title";

	$metatags = "<meta name=\"keywords\" content=\"$project[title_clean], project tools, " . $vbulletin->options['keywords'] . "\" />
		<meta name=\"description\" content=\"[$vbphrase[archive]] $project[summary_clean] " . ($p > 1 ? construct_phrase($vbphrase['page_x'], $p) . " " : "") . "\" />
	";
}
else
{
	$do = 'index';
	$metatags = "<meta name=\"keywords\" content=\"project tools, " . $vbulletin->options['keywords'] . "\" />
		<meta name=\"description\" content=\"" . $vbulletin->options['description'] . "\" />
	";
}

($hook = vBulletinHook::fetch_hook('projectarchive_start')) ? eval($hook) : false;

$output .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" dir=\"" . vB_Template_Runtime::fetchStyleVar('textdirection') . "\" lang=\"" . vB_Template_Runtime::fetchStyleVar('languagecode') . "\">
<head>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . vB_Template_Runtime::fetchStyleVar('charset') . "\" />
	$metatags
	<title>$title</title>
	<link rel=\"stylesheet\" type=\"text/css\" href=\"" . $vbulletin->options['bburl'] . "/archive/archive.css\" />
</head>
<body>
<div class=\"pagebody\">
";

($hook = vBulletinHook::fetch_hook('projectarchive_postheader')) ? eval($hook) : false;

// #######################################################################

if ($do == 'index')
{
	$perms_query = build_issue_permissions_query($vbulletin->userinfo);
	if (empty($perms_query))
	{
		exit;
	}

	$project_types = array();
	$project_types_query = $db->query_read("
		SELECT projecttype.*
		FROM " . TABLE_PREFIX . "pt_projecttype AS projecttype
		INNER JOIN " . TABLE_PREFIX . "pt_issuetype AS issuetype ON (issuetype.issuetypeid = projecttype.issuetypeid)
		WHERE projecttype.projectid IN (" . implode(',', array_keys($perms_query)) . ")
		ORDER BY issuetype.displayorder
	");
	while ($project_type = $db->fetch_array($project_types_query))
	{
		$project_types["$project_type[projectid]"][] = $project_type;
	}

	$output .= print_archive_navbar(array(
		'' => $vbphrase['projects']
	));
	$output .= "<p class=\"largefont\">$vbphrase[view_full_version]: <a href=\"" . $vbulletin->options['bburl'] . '/project.php">' . $vbphrase['projects'] . "</a></p>\n";
	$output .= "<div id=\"content\">\n";

	// project list
	$projectbits = '';
	foreach ($vbulletin->pt_projects AS $projectgroupid => $projectgroupdata)
	{
		foreach ($projectgroupdata['projects'] AS $project)
		{
			if (!isset($perms_query["$project[projectid]"]) OR !is_array($project_types["$project[projectid]"]))
			{
				continue;
			}
	
			$projectperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid']);
	
			$have_types = false;
			foreach ($project_types["$project[projectid]"] AS $type)
			{
				if (!($projectperms["$type[issuetypeid]"]['generalpermissions'] & $vbulletin->pt_bitfields['general']['canview']))
				{
					continue;
				}
	
				$have_types = true;
			}
	
			if (!$have_types)
			{
				continue;
			}
	
			($hook = vBulletinHook::fetch_hook('projectarchive_index_project')) ? eval($hook) : false;
	
			$projectbits .= "<li><a href=\"" .  $vbulletin->options['bburl'] . '/archive/project.php' . (SLASH_METHOD ? '/' : '?') . "projectid-$project[projectid].html\">$project[title_clean]</a></li>\n";
		}
	}

	if (!$projectbits)
	{
		exit;
	}

	$output .= "<ul><li><strong>$vbphrase[projects]</strong><ul>$projectbits</ul></li></ul></div>\n";
}
else if ($do == 'project')
{
	$output .= print_archive_navbar(array(
		(!SLASH_METHOD ? 'project.php' : './') => $vbphrase['projects'],
		'' => $project['title_clean']
	));

	$output .= "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"" . $vbulletin->options['bburl'] . "/project.php?projectid=$project[projectid]\">$project[title_clean]</a></p>\n<hr />\n";
	$output .= "<div>$project[description]</div>";

	if ($p < 1)
	{
		$p = 1;
	}

	// wrapping this in a do-while allows us to detect if someone goes to a page
	// that's too high and take them back to the last page seamlessly
	do
	{
		if ($p < 1)
		{
			$p = 1;
		}
		$start = ($p - 1) * $vbulletin->options['archive_threadsperpage'];

		// TODO: private replies?

		// issue list
		$issue_results = $db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				issue.*
			FROM " . TABLE_PREFIX . "pt_issue AS issue
			WHERE " . $perms_query["$project[projectid]"] . "
			ORDER BY issue.lastpost DESC
			LIMIT $start, " . $vbulletin->options['archive_threadsperpage']
		);
		list($issue_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $issue_count)
		{
			$p = ceil($issue_count / $vbulletin->options['archive_threadsperpage']);
		}
	}
	while ($start >= $issue_count AND $issue_count);

	$output .= print_archive_page_navigation($issue_count, $vbulletin->options['archive_threadsperpage'], "projectid-$project[projectid]", 'project.php?');

	$issuebits = '';
	while ($issue = $db->fetch_array($issue_results))
	{
		($hook = vBulletinHook::fetch_hook('projectarchive_project_issue')) ? eval($hook) : false;
		$issuebits .= "<li><a href=\"" . $vbulletin->options['bburl'] . '/archive/project.php' . (SLASH_METHOD ? '/' : '?') . "issueid-$issue[issueid].html\">$issue[title]</a> (" . $vbphrase["issuetype_$issue[issuetypeid]_singular"] . ")</li>\n";
	}

	if ($issuebits)
	{
		$output .= "<div id=\"content\">\n";
		$output .= "<ol start=\"" . ($start + 1) . "\">$issuebits</ol>";
		$output .= "</div>\n";
	}
}
else if ($do == 'issue')
{
	$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
	$viewable_note_types = fetch_viewable_note_types($issueperms, $private_text);

	// find total results for each type
	$notetype_counts = array(
		'user' => 0,
		'petition' => 0,
		'system' => 0
	);
	$notetype_counts_query = $db->query_read("
		SELECT issuenote.type, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.issuenoteid <> $issue[firstnoteid]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
		GROUP BY issuenote.type
	");
	while ($notetype_count = $db->fetch_array($notetype_counts_query))
	{
		$notetype_counts["$notetype_count[type]"] = intval($notetype_count['total']);
	}

	$note_count = $notetype_counts['user'] + $notetype_counts['petition'];

	// pagination
	if (!$p)
	{
		$p = 1;
	}
	$start = ($p - 1) * $vbulletin->options['archive_postsperpage'];

	if ($start > $note_count)
	{
		$vbulletin->GPC['pagenumber'] = ceil($note_count / $vbulletin->options['archive_postsperpage']);
		$start = ($p - 1) * $vbulletin->options['archive_postsperpage'];
	}

	$notes = $db->query_read("
		SELECT issuenote.*, issuenote.username AS noteusername, issuenote.ipaddress AS noteipaddress,
			user.*
		FROM " . TABLE_PREFIX . "pt_issuenote AS issuenote
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issuenote.userid)
			WHERE issuenote.issueid = $issue[issueid]
			AND issuenote.issuenoteid <> $issue[firstnoteid]
			AND (issuenote.visible IN (" . implode(',', $viewable_note_types) . ")$private_text)
			AND issuenote.type IN ('user', 'petition')
		ORDER BY issuenote.dateline
		LIMIT $start, " . $vbulletin->options['archive_postsperpage'] . "
	");

	$notebits = '';
	while ($note = $db->fetch_array($notes))
	{
		$note['pagetext_simp'] = strip_bbcode($note['pagetext']);
		$note['postdate'] = vbdate($vbulletin->options['dateformat'], $note['dateline']);
		$note['posttime'] = vbdate($vbulletin->options['timeformat'], $note['dateline']);

		if ($vbulletin->options['pt_wordwrap'] != 0)
		{
			$note['pagetext_simp'] = fetch_word_wrapped_string($note['pagetext_simp'], $vbulletin->options['pt_wordwrap']);
		}

		$note['pagetext_simp'] = fetch_censored_text($note['pagetext_simp']);

		($hook = vBulletinHook::fetch_hook('projectarchive_issue_note')) ? eval($hook) : false;

		$notebits .= "\n<div class=\"post\"><div class=\"posttop\"><div class=\"username\">$note[noteusername]</div><div class=\"date\">$note[postdate], $note[posttime]</div></div>";
		$notebits .= "<div class=\"posttext\">" . nl2br(htmlspecialchars_uni($note['pagetext_simp'])) . "</div></div><hr />\n\n";
	}

	$output .= print_archive_navbar(array(
		(!SLASH_METHOD ? 'project.php' : './') => $vbphrase['projects'],
		(!SLASH_METHOD ? 'project.php?' : '') . "projectid-$project[projectid].html" => $project['title_clean'],
		'' => $issue['title']
	));
	$output .= "<p class=\"largefont\">$vbphrase[view_full_version] : <a href=\"" . $vbulletin->options['bburl'] . "/project.php?issueid=$issue[issueid]\">$issue[title]</a></p>\n<hr />\n";

	$issue['pagetext_simp'] = strip_bbcode($issue['pagetext']);
	$issue['postdate'] = vbdate($vbulletin->options['dateformat'], $issue['dateline']);
	$issue['posttime'] = vbdate($vbulletin->options['timeformat'], $issue['dateline']);

	if ($vbulletin->options['pt_wordwrap'] != 0)
	{
		$issue['pagetext_simp'] = fetch_word_wrapped_string($issue['pagetext_simp'], $vbulletin->options['pt_wordwrap']);
	}

	$issue['pagetext_simp'] = fetch_censored_text($issue['pagetext_simp']);

	$output .= "\n<div class=\"post\"><div class=\"posttop\"><div class=\"username\">$issue[username]</div><div class=\"date\">$issue[postdate], $issue[posttime]</div></div>";
	$output .= "<div class=\"posttext\"><div><strong>$issue[summary]</strong></div>" . nl2br(htmlspecialchars_uni($issue['pagetext_simp'])) . "</div></div>\n\n";
	if ($notebits)
	{
		$output .= "<hr style=\"display: block; visibility: visible\" />\n\n";
	}

	$output .= print_archive_page_navigation($note_count, $vbulletin->options['archive_postsperpage'], "issueid-$issue[issueid]", 'project.php?');
	$output .= $notebits;
}

// #######################################################################

($hook = vBulletinHook::fetch_hook('projectarchive_complete')) ? eval($hook) : false;

$output .= "
<!--<branding>--><div id=\"copyright\">$vbphrase[vbulletin_copyright]</div><!--</branding>-->
</div>
</body>
</html>";

if (defined('NOSHUTDOWNFUNC'))
{
	exec_shut_down();
}

echo $output;

?>