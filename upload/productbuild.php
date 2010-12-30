<?php

/* vBulletin Product Build Cron Job Script
Written by Jeremy Dentel, Copyright vBulletin Project Tools Development Team, 2010

To be used to create a vBulletin product.xml file. Highly integrated with vBulletin.

DIRECTORY STRUCTURE:

product/plugins/ - Main plugin directory, he <plugins></plugin> section will be pulled from here.
product/plugins/hook/ - Specific directories for hooks, will be used to build the <hookname></hookname> property of individual plugins.
product/plugins/hook/plugin_name.php - Individual files, the file name (minus .php) will be used for <title></title> and the content of the file will be used for <phpcode></phpcode

product/templates/ - Main templates directory.
product/templates/filename.html AND templates/filename.css - Content will be used for the <![CDATA[]]> section, name and other attributes will be used to fill in the <template> tag.

product/phrases/ - Main Phrases Directory
product/phrases/phrasegroup/phrasename.html - File content becomes phrase text, file name becomes phrase name, and directory becomes the phrasegroup.

product/product.xml - XML File containing following data:
- Version
- Title
- Description
- Dependencies
- Url
- Version Check URL

product/installcodes/ - Install code directory
product/installcodes/versionnumber/ - sets <code version> attribute.
product/installcodes/versionnumber/install_1.php AND installcodes/versionnumber/uninstall_2.php - Install and Unisntall codes respectively.

product/options/varname.xml - XML file for each option. XML code just copied and pasted.

product/helptopics.xml - XML file of <helptopics></helptopics>. XML code just copied.

product/cronentries/varname.xml - XML file containing all relevant information.

product/faqentries.xml - XML file of <faqentries></faqentries> */

// ############# BASE URL REQUIRED TO WORK #################
define('BASE', '/Users/Jeremy/Sites/vb4/');

// ############# Verify includes/xml/ is writable ##########

if(!is_writable(BASE . 'includes/xml'))
{
	print 'Error: /includes/xml/ is not writable. Cannot create XML file.';
	die;
}

// ############# REQUIRED BACKEND ##########################
require_once(BASE . 'global.php');
require_once(BASE . 'includes/class_xml.php');

// ############# Verify if /product/ dir exsts. ############
if(!is_dir(BASE . 'product'))
{
	print 'Error: Product directory not found...';
	die;
}

// ############# Verify if .xml files exist. ###############
if(!file_exists(BASE . 'product/product.xml'))
{
	print 'Error: Product File does not exist.';
	die;
}

if(!file_exists(BASE . 'product/help_topics.xml'))
{
	print 'Error: Help Topics file does not exist.';
	die;
}

if(!file_exists(BASE . 'product/faqentries.xml'))
{
	print 'Error: FAQ Entries file does not exist.';
	die;
}

// ############# Verify if directories files exist. ########
if(!is_dir(BASE . 'product/plugins'))
{
	print 'Error: Plugins directory does not exist.';
	die;
}

if(!is_dir(BASE . 'product/templates'))
{
	print 'Error: Templates directory does not exist.';
	die;
}

if(!is_dir(BASE . 'product/phrases'))
{
	print 'Error: Phrases directory does not exist.';
	die;
}

if(!is_dir(BASE . 'product/installcodes'))
{
	print 'Error: Install Codes directory does not exist.';
	die;
}

if(!is_dir(BASE . 'product/options'))
{
	print 'Error: Options directory does not exist.';
	die;
}

if(!is_dir(BASE . 'product/cronentries'))
{
	print 'Error: Cron Entries directory does not exist.';
	die;
}

// ############# Passed Tests. Init XML functions. ###############

$xml = new vB_XML_Builder($vbulletin);
$productfile = BASE . 'includes/xml/product-vbprojecttools.xml';
$productfile_content = '';

// ############# Start Build Script ##############################

$product_file = fopen(BASE . 'product/product.xml', 'r');
while (!feof($product_file))
{
	$line = fgets($file_handle);
	$productfile_contnt = $productfile_content . $line;
}
fclose($product_file);

?>