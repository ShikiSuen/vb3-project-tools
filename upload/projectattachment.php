<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.4                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);
if (@ini_get('output_handler') == 'ob_gzhandler' AND @ob_get_length() !== false)
{	// if output_handler = ob_gzhandler, turn it off and remove the header sent by PHP
	@ob_end_clean();
	header('Content-Encoding:');
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'projectattachment');
define('CSRF_PROTECTION', true);
define('NOHEADER', 1);
define('NOZIP', 1);
define('NOCOOKIES', 1);
define('NOPMPOPUP', 1);
define('PROJECT_SCRIPT', true);

// attachment.php/$attachmentid/file.mp3 -- for podcast and confused clients that determine file type in <enclosure> by the url extension <iTunes, I'm looking in your direction>
if (!$_REQUEST['attachmentid'])
{
	$url_info = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];

	if ($url_info != '')
	{
		preg_match('#projectattachment\.php/(\d+)/#si', $url_info, $matches);
		$_REQUEST['attachmentid'] = intval($matches[1]);
	}
}

if (empty($_REQUEST['attachmentid']))
{
	// return not found header
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 404 Not Found');
	}
	else
	{
		header('HTTP/1.1 404 Not Found');
	}
	exit;
}

if ($_REQUEST['stc'] == 1) // we were called as <img src=> from showthread.php
{
	define('NOSHUTDOWNFUNC', 1);
}

// Immediately send back the 304 Not Modified header if this image is cached, don't load global.php
// 3.5.x allows overwriting of attachments so we add the dateline to attachment links to avoid caching
/*if (!isset($_SERVER['HTTP_RANGE']) AND (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])))
{
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
	}
	// remove the content-type and X-Powered headers to emulate a 304 Not Modified response as close as possible
	header('Content-Type:');
	header('X-Powered-By:');
	if (!empty($_REQUEST['attachmentid']))
	{
		header('ETag: "' . intval($_REQUEST['attachmentid']) . '"');
	}
	exit;
}*/

// if $_POST['ajax'] is set, we need to set a $_REQUEST['do'] so we can precache the lightbox template
if (!empty($_POST['ajax']) AND isset($_POST['uniqueid']))
{
	$_REQUEST['do'] = 'lightbox';
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array(
	'pt_bitfields',
	'pt_projects',
	'pt_permissions',
	'pt_issuestatus',
	'pt_issuetype',
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
if (empty($vbulletin->products['vbprojecttools']))
{
	standard_error(fetch_error('product_not_installed_disabled'));
}

require_once(DIR . '/includes/functions_projecttools.php');

if (!($vbulletin->userinfo['permissions']['ptpermissions'] & $vbulletin->bf_ugp_ptpermissions['canviewprojecttools']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('projectattachment_start')) ? eval($hook) : false;

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'attachmentid' => TYPE_UINT,
	'thumb'        => TYPE_BOOL,
));

$vbulletin->input->clean_array_gpc('p', array(
	'ajax'     => TYPE_BOOL,
	'uniqueid' => TYPE_UINT
));

$idname = $vbphrase['attachment'];

$imagetype = !empty($vbulletin->GPC['thumb']) ? 'thumbnail' : 'filedata';

if (!$attachmentinfo = $db->query_first_slave("
	SELECT issueattach.filename, issueattach.issueid, issueattach.userid, issueattach.attachmentid,
		" . ((!empty($vbulletin->GPC['thumb'])
			? 'issueattach.thumbnail AS filedata, thumbnail_dateline AS dateline, thumbnail_filesize AS filesize,'
			: 'issueattach.dateline, SUBSTRING(filedata, 1, 2097152) AS filedata, filesize,')) . "
		issueattach.visible, issueattach.extension
	FROM " . TABLE_PREFIX . "pt_issueattach AS issueattach
	WHERE issueattach.attachmentid = " . $vbulletin->GPC['attachmentid'] . "
"))
{
	eval(standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink'])));
}

$issue = verify_issue($attachmentinfo['issueid']);
$project = verify_project($issue['projectid']);

$issueperms = fetch_project_permissions($vbulletin->userinfo, $project['projectid'], $issue['issuetypeid']);
if (!($issueperms['attachpermissions'] & $vbulletin->pt_bitfields['attach']['canattachview']))
{
	print_no_permission();
}

// handle lightbox requests
if ($_REQUEST['do'] == 'lightbox')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'width'      => TYPE_UINT,
		'height'     => TYPE_UINT,
		'first'      => TYPE_BOOL,
	    'last'       => TYPE_BOOL,
		'current'    => TYPE_UINT,
	    'total'      => TYPE_UINT
	));
	$width = $vbulletin->GPC['width'];
	$height = $vbulletin->GPC['height'];
	$first = $vbulletin->GPC['first'];
	$last = $vbulletin->GPC['last'];
	$current = $vbulletin->GPC['current'];
	$total = $vbulletin->GPC['total'];

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	if (in_array(strtolower($attachmentinfo['extension']), array('jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp')))
	{
		$uniqueid = $vbulletin->GPC['uniqueid'];
		$imagelink = 'projectattachment.php?' . $vbulletin->session->vars['sessionurl'] . 'attachmentid=' . $attachmentinfo['attachmentid'] . '&d=' . $attachmentinfo['dateline'];
		$attachmentinfo['date_string'] = vbdate($vbulletin->options['dateformat'], $attachmentinfo['dateline']);
		$attachmentinfo['time_string'] = vbdate($vbulletin->options['timeformat'], $attachmentinfo['dateline']);
		$show['newwindow'] = ($attachmentinfo['newwindow'] ? true : false);

		($hook = vBulletinHook::fetch_hook('attachment_lightbox')) ? eval($hook) : false;

		$templater = vB_Template::create('lightbox');
			$templater->register('attachmentinfo', $attachmentinfo);
			$templater->register('current', $current);
			$templater->register('first', $first);
			$templater->register('height', $height);
			$templater->register('imagelink', $imagelink);
			$templater->register('last', $last);
			$templater->register('total', $total);
			$templater->register('uniqueid', $uniqueid);
			$templater->register('width', $width);
		$html = $templater->render(true);

		$xml->add_group('img');
		$xml->add_tag('html', process_replacement_vars($html));
		$xml->add_tag('link', $imagelink);
		$xml->add_tag('name', $attachmentinfo['filename']);
		$xml->add_tag('date', $attachmentinfo['date_string']);
		$xml->add_tag('time', $attachmentinfo['time_string']);
		$xml->close_group();
	}
	else
	{
		$xml->add_group('errormessage');
		$xml->add_tag('error', 'notimage');
		$xml->add_tag('extension', $attachmentinfo['extension']);
		$xml->close_group();
	}
	$xml->print_xml();
}

if ($vbulletin->options['pt_attachfile'])
{
	require_once(DIR . '/includes/functions_file.php');
	if ($vbulletin->GPC['thumb'])
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], true, $vbulletin->options['pt_attachpath']);
	}
	else
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], false, $vbulletin->options['pt_attachpath']);
	}

	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		if (!($fp = fopen($attachpath, 'rb')))
		{
			exit;
		}
	}
	else if (!($fp = @fopen($attachpath, 'rb')))
	{
		$filedata = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
		$filesize = strlen($filedata);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
		header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
		header('Pragma: no-cache');                                   // HTTP/1.0
		header("Content-disposition: inline; filename=clear.gif");
		header('Content-transfer-encoding: binary');
		header("Content-Length: $filesize");
		header('Content-type: image/gif');
		echo $filedata;
		exit;
	}
}

$startbyte = 0;
$lastbyte = $attachmentinfo['filesize'] - 1;

if (isset($_SERVER['HTTP_RANGE']))
{
	preg_match('#^bytes=(-?([0-9]+))(-([0-9]*))?$#', $_SERVER['HTTP_RANGE'], $matches);

	if (intval($matches[1]) < 0)
	{ // its negative so we want to take this value from last byte
		$startbyte = $attachmentinfo['filesize'] - $matches[2];
	}
	else
	{
		$startbyte = intval($matches[2]);
		if ($matches[4])
		{
			$lastbyte = $matches[4];
		}
	}

	if ($startbyte < 0 OR $startbyte >= $attachmentinfo['filesize'])
	{
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 416 Requested Range Not Satisfiable');
		}
		else
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
		}
		header('Accept-Ranges: bytes');
		header('Content-Range: bytes */'. $attachmentinfo['filesize']);
		exit;
	}
}

// send jpeg header for PDF, BMP, TIF, TIFF, and PSD thumbnails as they are jpegs
if ($vbulletin->GPC['thumb'] AND in_array($attachmentinfo['extension'], array('bmp', 'tif', 'tiff', 'psd', 'pdf')))
{
	$attachmentinfo['filename'] = preg_replace('#.(bmp|tiff?|psd|pdf)$#i', '.jpg', $attachmentinfo['filename']);
	$mimetype = array('Content-type: image/jpeg');
}
else
{
	$mimetype = unserialize($attachmentinfo['mimetype']);
}

header('Cache-control: max-age=31536000');
header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attachmentinfo['dateline']) . ' GMT');
header('ETag: "' . $attachmentinfo['attachmentid'] . '"');
header('Accept-Ranges: bytes');

// look for entities in the file name, and if found try to convert
// the filename to UTF-8
$filename = $attachmentinfo['filename'];
if (preg_match('~&#([0-9]+);~', $filename))
{
	if (function_exists('iconv'))
	{
		$filename_conv = @iconv(vB_Template_Runtime::fetchStyleVar('charset'), 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	$filename = preg_replace(
		'~&#([0-9]+);~e',
		"convert_int_to_utf8('\\1')",
		$filename
	);
	$filename_charset = 'utf-8';
}
else
{
	$filename_charset = vB_Template_Runtime::fetchStyleVar('charset');
}

$filename = preg_replace('#[\r\n]#', '', $filename);

// Opera and IE have not a clue about this, mozilla puts on incorrect extensions.
if (is_browser('mozilla'))
{
	$filename = "filename*=" . $filename_charset . "''" . rawurlencode($filename);
}
else
{
	// other browsers seem to want names in UTF-8
	if ($filename_charset != 'utf-8' AND function_exists('iconv'))
	{
		$filename_conv = iconv($filename_charset, 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	if (is_browser('opera') OR is_browser('konqueror') OR is_browser('safari'))
	{
		// Opera / Konqueror does not support encoded file names
		$filename = 'filename="' . str_replace('"', '', $filename) . '"';
	}
	else
	{
		// encode the filename to stay within spec
		$filename = 'filename="' . rawurlencode($filename) . '"';
	}
}

if (in_array($attachmentinfo['extension'], array('jpg', 'jpe', 'jpeg', 'gif', 'png')))
{
	header("Content-disposition: inline; $filename");
	header('Content-transfer-encoding: binary');
}
else
{
	// force files to be downloaded because of a possible XSS issue in IE
	header("Content-disposition: attachment; $filename");
}

if ($startbyte != 0 OR $lastbyte != ($attachmentinfo['filesize'] - 1))
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 206 Partial Content');
	}
	else
	{
		header('HTTP/1.1 206 Partial Content');
	}
	header('Content-Range: bytes '. $startbyte .'-'. $lastbyte .'/'. $attachmentinfo['filesize']);
}

header('Content-Length: ' . (($lastbyte + 1) - $startbyte));

$mime_types = array(
	'jpg' => 'image/jpeg',
	'jpe' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'gif' => 'image/gif',
	'png' => 'image/png'
);

if (!empty($mime_types["$attachmentinfo[extension]"]))
{
	header('Content-type: ' . $mime_types["$attachmentinfo[extension]"]);
}
else
{
	header('Content-type: unknown/unknown');
}

($hook = vBulletinHook::fetch_hook('projectattachment_output')) ? eval($hook) : false;

if ($vbulletin->options['pt_attachfile'])
{
	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}

	if ($startbyte > 0)
	{
		fseek($fp, $startbyte);
	}

	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{	// You can limit bandwidth by decreasing the values in the read size call, they must be equal.
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 1048576) ? 1048576 : $size + 1;
		echo @fread($fp, $readsize);
		$startbyte += $readsize;
		flush();
	}
	@fclose($fp);
}
else
{
	// start grabbing the filedata in batches of 2mb
	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 2097152) ? 2097152 : $size + 1;

		$attachmentinfo = $db->query_first_slave("
			SELECT attachmentid, SUBSTRING(" . ((!empty($vbulletin->GPC['thumb']) ? 'thumbnail' : 'filedata')) . ", $startbyte + 1, $readsize) AS filedata
			FROM " . TABLE_PREFIX . "pt_issueattach
			WHERE attachmentid = $attachmentinfo[attachmentid]
		");
		echo $attachmentinfo['filedata'];
		$startbyte += $readsize;
		flush();
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}
}

?>