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

require_once(DIR . '/includes/functions_projecttools.php');

/**
* vBulletin Project Tools Forum Block - Latest PT Issues
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_BlockType_Ptissues extends vB_BlockType
{
	/**
	 * The Productid that this block type belongs to
	 * Set to '' means that it belongs to vBulletin forum
	 *
	 * @var string
	 */
	protected $productid = '';

	/**
	 * The title of the block type
	 * We use it only when reload block types in admincp.
	 * Automatically set in the vB_BlockType constructor.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * The description of the block type
	 * We use it only when reload block types in admincp. So it's static.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * The block settings
	 * It uses the same data structure as forum settings table
	 * e.g.:
	 * <code>
	 * $settings = array(
	 *     'varname' => array(
	 *         'defaultvalue' => 0,
	 *         'optioncode'   => 'yesno'
	 *         'displayorder' => 1,
	 *         'datatype'     => 'boolean'
	 *     ),
	 * );
	 * </code>
	 * @see print_setting_row()
	 *
	 * @var string
	 */
	protected $settings = array(
		'issues_type' => array(
			'defaultvalue' => 0,
			'optioncode'   => 'radio:piped
0|new_started
1|new_replied
2|most_replied',
			'displayorder' => 1,
			'datatype'     => 'integer'
		),
		'issues_limit' => array(
			'defaultvalue' => 5,
			'displayorder' => 2,
			'datatype'     => 'integer'
		),
		'issues_titlemaxchars' => array(
			'defaultvalue' => 35,
			'displayorder' => 3,
			'datatype'     => 'integer'
		),
		'issues_projectids' => array(
			'defaultvalue' => -1,
			'optioncode'   => 'selectmulti:eval
$options = construct_project_chooser_options(0, fetch_phrase("all_projects", "vbblock"));',
			'displayorder' => 4,
			'datatype'     => 'arrayinteger'
		),
		'datecut' => array(
			'defaultvalue' => 30,
			'displayorder' => 5,
			'datatype'     => 'integer'
		)
	);

	public function getData()
	{
		// Prerequired datastore entries
		$datastores = $this->registry->db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ('pt_bitfields', 'pt_permissions', 'pt_projects')
		");

		while ($datastore = $this->registry->db->fetch_array($datastores))
		{
			$title = $datastore['title'];

			if (!is_array($datastore['data']))
			{
				$data = unserialize($datastore['data']);

				if (is_array($data))
				{
					$this->registry->$title = $data;
				}
			}
			else if ($datastore['data'] != '')
			{
				$this->registry->$title = $datastore['data'];
			}
		}

		if ($this->config['issues_projectids'])
		{
			if (in_array(-1, $this->config['issues_projectids']))
			{
				$projectids = array_keys($this->registry->pt_projects);
			}
			else
			{
				$projectids = $this->config['issues_projectids'];
			}
		}
		else
		{
			$projectids = array_keys($this->registry->pt_projects);
		}

		$datecut = TIMENOW - ($this->config['datecut'] * 86400);

		switch (intval($this->config['issues_type']))
		{
			case 0:
				$ordersql = " issue.submitdate DESC";
				$datecutoffsql = " AND issue.submitdate > $datecut";
				break;
			case 1:
				$ordersql = " issue.lastpost DESC";
				$datecutoffsql = " AND issue.lastpost > $datecut";
				break;
			case 2:
				$ordersql = " issue.replycount DESC";
				$datecutoffsql = " AND issue.submitdate > $datecut";
				break;
		}

		foreach ($projectids AS $projectid)
		{
			$projectchoice[] = $projectid;
		}

		if (!empty($projectchoice))
		{
			$projectsql = "AND issue.projectid IN (" . implode(',', $projectchoice) . ")";

			// remove issues from users on the global ignore list if user is not a moderator
			$globalignore = '';

			if (trim($this->registry->options['globalignore']) != '')
			{
				require_once(DIR . '/includes/functions_bigthree.php');
				if ($Coventry = fetch_coventry('string'))
				{
					$globalignore = "AND issue.submituserid NOT IN ($Coventry) ";
				}
			}

			// query last threads from visible / chosen projects
			$issues = $this->registry->db->query_read_slave("
				SELECT issue.issueid, issue.title,
					issue.submitusername, issue.submitdate AS dateline, issue.lastnoteid, issue.lastpost, issue.lastpostuserid, issue.lastpostusername AS lastposter, issue.replycount,
					project.projectid, project.title_clean AS projecttitle,
					issuenote.pagetext AS message, issuenote.issuenoteid,
					user.*
					" . ($this->registry->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				FROM " . TABLE_PREFIX . "pt_issue AS issue
				INNER JOIN " . TABLE_PREFIX . "pt_project AS project ON (project.projectid = issue.projectid)
				LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issuenoteid = issue.firstnoteid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (issue.submituserid = user.userid)
				" . ($this->registry->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE 1=1
				$projectsql
				AND issue.visible = 'visible'
				AND issuenote.visible = 'visible'
				AND issue.state = 'open'
				$datecutoffsql
				$globalignore
				" . ($this->userinfo['ignorelist'] ? "AND issue.submituserid NOT IN (" . implode(',', explode(' ', $this->userinfo['ignorelist'])) . ")": '')
			. "
			ORDER BY$ordersql
			LIMIT 0," . intval($this->config['issues_limit']) . "
			");

			while ($issue = $this->registry->db->fetch_array($issues))
			{
				$issue['url'] = 'project.php?'. $vbulletin->session->vars['sessionurl'] . 'issueid=' . $issue['issueid'] . ''; //fetch_seo_url('issue', $issue);
				// $issue['newposturl'] = fetch_seo_url('issue', $issue, array('goto' => 'newpost'));
				// $issue['lastposturl'] = fetch_seo_url('issue', $issue, array('p' => $issue['lastpostid'])) . '#note' . $issue['lastpostid'];

				// trim the title after fetching the urls
				// $issue['title'] = fetch_trimmed_title($issue['title'], $this->config['issues_titlemaxchars']);

				$issue['date'] = vbdate($this->registry->options['dateformat'], $issue['dateline'], true);
				$issue['time'] = vbdate($this->registry->options['timeformat'], $issue['dateline']);

				$issue['lastpostdate'] = vbdate($this->registry->options['dateformat'], $issue['lastpost'], true);
				$issue['lastposttime'] = vbdate($this->registry->options['timeformat'], $issue['lastpost']);

				// get avatar
				$this->fetch_avatarinfo($issue);

				$issuearray[$issue['issueid']] = $issue;
			}
		}
		return $issuearray;
	}

	public function getHTML($issuearray = false)
	{
		if (!$issuearray)
		{	
			$issuearray = $this->getData();
		}

		if ($issuearray)
		{
			foreach ($issuearray AS $key => $issue)
			{	
				$issuearray[$key]['url'] = 'project.php?' . $session['sessionurl'] . 'projectid=' . $issue['projectid'];
				/*$issuearray[$key]['newissuenoteurl'] = fetch_seo_url('issue', $issue, array('goto' => 'newissuenote'));
				$issuearray[$key]['lastissuenoteurl'] = fetch_seo_url('issue', $issue, array('note' => $issue['lastnoteid'])) . '#post' . $issue['lastnoteid'];*/
				$issuearray[$key]['title'] = fetch_trimmed_title($issue['title'], $this->config['issues_titlemaxchars']);
			}

			$templater = vB_Template::create('block_issues');
				$templater->register('blockinfo', $this->blockinfo);
				$templater->register('issuestype', $this->config['issues_type']);
				$templater->register('issues', $issuearray);
			return $templater->render();
		}
	}

	public function getHash()
	{
		$context = new vB_Context('projectblock' ,
			array(
				'blockid' => $this->blockinfo['blockid'],
				'permissions' => $this->userinfo['projectpermissions'],
				'ignorelist' => $this->userinfo['ignorelist'],
				THIS_SCRIPT)
			);

		return strval($context);
	}
}

?>