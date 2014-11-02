<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Test Widget Item
*
* @package		vBulletin Project Tools
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vBCms_Item_Widget_RecentPTComments extends vBCms_Item_Widget
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'RecentPTComments';

	/** The default configuration **/
	protected $config = array(
		'template_name' => 'vbcms_widget_recentptcomments_page',
		'projectid' => 1,
		'days' => 7,
		'count' => 6,
		'messagemaxchars' => 200,
		'cache_ttl' => 5
	);

}

?>