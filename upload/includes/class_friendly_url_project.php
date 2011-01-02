<?php if (!class_exists('vB_Database')) exit;

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

/**
 * Friendly URL for project.php
 */
class vB_Friendly_Url_Project extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'projectid';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'projectid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('projectid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'project.php';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'project';
}

/**
 * Friendly URL for issuelist.php
 */
class vB_Friendly_Url_Issuelist extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'projectid';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'projectid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('projectid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'issuelist.php';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'issuelist';
}


/**
 * Friendly URL for issue.php
 */
class vB_Friendly_Url_Issue extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'issueid';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'issueid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('issueid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'issue.php';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'issue';
}

/**
* Friendly URL for project.php (milestones)
*/
class vB_Friendly_Url_Milestone extends vB_Friendly_Url
{
	/**
	* The request variable for the resource id.
	*
	* @var string
	*/
	protected $idvar = 'milestoneid';

	/**
	* Link into index of the resource id.
	*
	* @var string
	*/
	protected $idkey = 'milestoneid';

	/**
	* Link info index of the title.
	*
	* @var string
	*/
	protected $titlekey = 'title';

	/**
	* Array of pageinfo vars to ignore when building the uri.
	*
	* @var array
	*/
	protected $ignorelist = array('milestoneid');

	/**
	* The name of the script that the URL links to.
	*
	* @var string
	*/
	protected $script = 'projectmilestone.php';

	/**
	* The segment of the uri that identifies this type.
	*
	* @var string
	*/
	protected $rewrite_segment = 'milestone';
}

?>