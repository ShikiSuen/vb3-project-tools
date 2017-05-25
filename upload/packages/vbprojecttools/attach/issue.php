<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_projecttools.php');

/**
* Class for verifying a vBulletin Project Tools issue attachment
*
* @package		vBulletin Project Tools
* @since		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
* @version		$Rev: 897 $
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*
*/
class vB_Attachment_Display_Single_vBProjectTools_Issue extends vB_Attachment_Display_Single
{
	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	public function verify_attachment()
	{
		if (!$this->verify_attachment_specific('vBProjectTools_Issue', array("issue.issuetypeid, issue.projectid, issue.issueid, issue.submituserid, issue.visible AS issue_visible", "issueattach.ispatchfile"), array("LEFT JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = a.contentid)", "LEFT JOIN " . TABLE_PREFIX . "pt_issueattach AS issueattach ON (issueattach.attachmentid = a.attachmentid)")))
		{
			return false;
		}

		$issueinfo = array(
			'projectid'      => $this->attachmentinfo['projectid'],
			'issueid'        =>	$this->attachmentinfo['issueid'],
			'submituserid'   =>	$this->attachmentinfo['submituserid'],
			'issue_visible'  =>	$this->attachmentinfo['issue_visible'],
			'issuetypeid'    =>	$this->attachmentinfo['issuetypeid'],
		);
		unset(
			$this->attachmentinfo['projectid'],
			$this->attachmentinfo['issueid'],
			$this->attachmentinfo['submituserid'],
			$this->attachmentinfo['issue_visible'],
			$this->attachmentinfo['issuetypeid']
		);

		require_once(DIR . '/includes/functions_projecttools.php');
		$project = verify_project($issueinfo['projectid']);
		$issueperms = fetch_project_permissions($this->registry->userinfo, $project['projectid'], $issueinfo['issuetypeid']);

		$this->browsinginfo = array(
			'issueinfo' => array(
				'issueid' => $issueinfo['issueid'],
			),
			'projectinfo' => array(
				'projectid' => $issueinfo['projectid'],
			),
		);

		if ($this->attachmentinfo['contentid'] == 0)
		{
			if ($this->registry->userinfo['userid'] != $this->attachmentinfo['userid'])
			{
				return false;
			}
		}
		else
		{
			# Block attachments belonging to soft deleted posts and threads
			if ($issueinfo['issue_visible'] == 'deleted')
			{
				return false;
			}
			# Block attachments belonging to moderated posts and threads
			if (!$issueinfo['issue_visible'])
			{
				return false;
			}

			if ($this->attachmentinfo['state'] == 'moderation' AND $this->attachmentinfo['userid'] != $this->registry->userinfo['userid'])
			{
				return false;
			}

			$viewpermission = ($issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattachview']);
			$viewthumbpermission = ($issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattachview']);

			if (!($issueperms['generalpermissions'] & $this->registry->pt_bitfields['general']['canview']) OR (!($issueperms['generalpermissions'] & $this->registry->pt_bitfields['general']['canviewothers']) AND ($issueinfo['submituserid'] != $this->registry->userinfo['userid'] OR $this->registry->userinfo['userid'] == 0)))
			{
				return false;
			}
			else if (($this->thumbnail AND !$viewthumbpermission) OR (!$this->thumbnail AND !$viewpermission))
			{
				// Show no permissions instead of invalid ID
				return -1;
			}
		}
		return true;
	}
}

/**
* Class for display of multiple vBulletin Project Tools issue attachments
*
* @package 		vBulletin Project Tools
* @version		$Revision: 897 $
* @date 		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
*
*/
class vB_Attachment_Display_Multiple_vBProjectTools_Issue extends vB_Attachment_Display_Multiple
{
	/**
	* Constructor
	*
	* @param	vB_Registry
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	*
	* @return	void
	*/
	public function __construct(&$registry)
	{
		parent::__construct($registry);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$this->contenttypeid = intval($types->getContentTypeID('vBProjectTools_Issue'));
	}

	/**
	* Return content specific information that relates to the ownership of attachments
	*
	* @param	array		List of attachmentids to query
	*
	* @return	void
	*/
	public function fetch_sql($attachmentids)
	{
		$selectsql = array(
			"issue.issueid, issue.title AS title, issue.submitdate AS dateline, issue.projectid, issue.state",
			"user.username",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	* Fetches the SQL to be queried as part of a UNION ALL od an attachment query, verifying read permissions
	*
	* @param	string	SQL WHERE criteria
	* @param	string	Contents of the SELECT portion of the main query
	*
	* @return	string
	*/
	protected function fetch_sql_ids($criteria, $selectfields)
	{
		$cangetprojectids = $canget = $canviewothers = $canmod = $canmodhidden = $canmodattach = array(1);//0);

		/*foreach ($this->registry->userinfo['forumpermissions'] AS $forumid => $perm)
		{
			if (
				($perm & $this->registry->bf_ugp_forumpermissions['canview'])
					AND
				($perm & $this->registry->bf_ugp_forumpermissions['canviewthreads'])
					AND
				($perm & $this->registry->bf_ugp_forumpermissions['cangetattachment'])
			)
			{
				$cangetforumids["$forumid"] = $forumid;
			}
			else
			{
				continue;
			}

			if ($perm & $this->registry->bf_ugp_forumpermissions['canviewothers'] AND $this->registry->userinfo['userid'])
			{
				$canviewothers["$forumid"] = $forumid;
			}

			if (can_moderate($forumid))
			{
				$canmod["$forumid"] = $forumid;
			}

			if (can_moderate($forumid, 'canmoderateposts'))
			{
				$canmodhidden["$forumid"] = $forumid;
			}

			if (can_moderate($forumid, 'canmoderateattachments'))
			{
				$canmodattach["$forumid"] = $forumid;
			}
		}*/

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "pt_issue AS issue ON (issue.issueid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		// This SQL can be condensed down in some fashion
		// This is not optimized beyond the userid level
		$subwheresql = array(
			"issue.projectid IN (" . implode(", ", $cangetprojectids) . ")",
			"(
				issue.projectid IN (" . implode(", ", $canviewothers) . ")
					OR
				issue.submituserid = {$this->registry->userinfo['userid']}
			)",
			"(
				a.state <> 'moderation'
					OR
				a.userid = {$this->registry->userinfo['userid']}
					OR
				issue.projectid IN (" . implode(", ", $canmodattach) . ")
			)",
			"(
				(
					issue.visible = 'open'
						AND
					issue.visible = 'open'
				)
					OR
				(
					issue.projectid IN (" . implode(", ", $canmodhidden) . ")
				)
					OR
				(
					issue.projectid IN (" . implode(", ", $canmod) . ")
						AND
					issue.state = 'close'
						AND
					issue.visible = 'close'
				)
			)",
		);

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	* Formats $post content for display
	*
	* @param	array		Post information
	*
	* @return	array
	*/
	protected function process_attachment_template($post, $showthumbs = false)
	{
		global $show, $vbphrase;

		if (!$post['title'])
		{
			$post['title'] = '&laquo; ' . $vbphrase['n_a'] . ' &raquo;';
		}

		$show['thumbnail'] = ($this->registry->options['attachthumbs'] AND $showthumbs);
		$show['inprogress'] = $post['inprogress'];

		$show['candelete'] = false;
		$show['canmoderate'] = can_moderate($post['forumid'], 'canmoderateattachments');
		if ($post['inprogress'])
		{
			$show['candelete'] = true;
		}
		else if ($post['open'] OR $this->registry->options['allowclosedattachdel'] OR can_moderate($post['forumid'], 'canopenclose'))
		{
			if (can_moderate($post['forumid'], 'caneditposts'))
			{
				$show['candelete'] = true;
			}
			else
			{
				$forumperms = fetch_permissions($post['forumid']);
				if (($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost'] AND $this->registry->userinfo['userid'] == $post['userid']))
				{
					if ($this->registry->options['allowattachdel'] OR !$this->registry->options['edittimelimit'] OR $post['p_dateline'] >= TIMENOW - $this->registry->options['edittimelimit'] * 60)
					{
						$show['candelete'] = true;
					}
				}
			}
		}

		$issueinfo = array(
			'threadid' => $post['issueid'],
			'title'    => $post['title'],
		);
		$pageinfo = array(
			'p'        => $post['contentid'],
		);

		return array(
			'template'   => 'issue',
			'issue'      => $post,
			'issueinfo'  => $issueinfo,
			'pageinfo'   => $pageinfo,
		);
	}

	/**
	* Return forum post specific url to the owner an attachment
	*
	* @param	array		Content information
	*
	* @return	string
	*/
	protected function fetch_content_url_instance($contentinfo)
	{
		return fetch_seo_url('issue', $contentinfo, array('issueid' => $contentinfo['contentid']), 'issueid', 'title') . "#note$contentinfo[contentid]";
	}
}

/**
* Class for storing a vBulletin Project Tools issue attachment
*
* @package 		vBulletin Project Tools
* @version		$Revision: 897 $
* @date 		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
*
*/
class vB_Attachment_Store_vBProjectTools_Issue extends vB_Attachment_Store
{
	/**
	* Issue info
	*
	* @var	array
	*/
	protected $issueinfo = array();

	/**
	* Project info
	*
	* @var	array
	*/
	protected $projectinfo = array();

	/**
	* Given an attachmentid, retrieve values that verify_permissions needs
	*
	* @param	int	Attachmentid
	*
	* @return	array
	*/
	public function fetch_associated_contentinfo($attachmentid)
	{
		return $this->registry->db->query_first("
			SELECT
				i.issueid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "pt_issue AS i ON (i.issueid = a.contentid)
			WHERE
				a.attachmentid = " . intval($attachmentid) . "
		");
	}

	/**
	* Verifies permissions to attach content to issues
	*
	* @return	boolean
	*/
	public function verify_permissions($info = array())
	{
		global $show;

		$this->issue = verify_issue($this->values['issueid']);
		$project = verify_project($this->issue['projectid']);

		$this->issueperms = fetch_project_permissions($this->registry->userinfo, $project['projectid'], $this->issue['issuetypeid']);

		if (!($this->issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattach']) OR is_issue_closed($this->issue, $this->issueperms))
		{
			return false;
		}

		if ($this->values['issueid'])
		{
			if (!($this->issueinfo = verify_issue($this->values['issueid'])))
			{
				return false;
			}

			if (!($this->projectinfo = verify_project($this->issue['projectid'])))
			{
				return false;
			}

			$this->contentid = $this->issueinfo['issueid'];
			$this->userinfo = fetch_userinfo($this->issueinfo['userid']);
			cache_permissions($this->userinfo);
		}
		else
		{
			if ($userid = intval($this->values['u']) AND $userinfo = fetch_userinfo($userid))
			{
				$this->userinfo = $userinfo;
				cache_permissions($this->userinfo);
			}
			else
			{
				$this->userinfo = $this->registry->userinfo;
			}
		}

		return true;
	}

	/**
	* Verifies permissions to attach content to issues
	*
	* @param	object		vB_Upload
	* @param	array		Information about uploaded attachment
	*
	* @return	void
	*/
	protected function process_upload($upload, $attachment, $imageonly = false)
	{
		if (
			($attachmentid = parent::process_upload($upload, $attachment, $imageonly))
				AND
			$this->registry->userinfo['userid'] != $this->issueinfo['userid']
				AND
			(!
				($this->issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattach'])
					OR
				is_issue_closed($this->issue, $this->issueperms)
			)
		)
		{
			$this->issueinfo['attachmentid'] = $attachmentid;
			$this->issueinfo['projectid'] = $project['projectid'];

			// Need to put some checks and a query for diff/patch files
			

			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($this->issueinfo, 'attachment_uploaded');
		}

		return $attachmentid;
	}
}

/**
* Class for deleting issue attachments
*
* @package 		vBulletin Project Tools issue
* @version		$Revision: 897 $
* @date 		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
*
*/
class vB_Attachment_Dm_vBProjectTools_Issue extends vB_Attachment_Dm
{
	/**
	* pre_approve function - extend if the contenttype needs to do anything
	*
	* @param	array		list of attachment ids to approve
	* @param	boolean		verify permission to approve
	*
	* @return	boolean
	*/
	public function pre_approve($list)
	{
		@ignore_user_abort(true);

		// Verify that we have permission to view these attachmentids
		$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
		$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

		if (count($list) != count($attachments))
		{
			return false;
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress,
				issue.issueid, issue.projectid,
			FROM " . TABLE_PREFIX . "attachment AS a
				LEFT JOIN " . TABLE_PREFIX . "issue AS issue ON (issue.issueid = a.contentid)
			WHERE a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if (!can_moderate($id['projectid'], 'canmoderateattachments'))
			{
				return false;
			}
		}
		return true;
	}

	/**
	* pre_delete function - extend if the contenttype needs to do anything
	*
	* @param	array		list of deleted attachment ids to delete
	* @param	boolean		verify permission to delete
	*
	* @return	boolean
	*/
	public function pre_delete($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'postlist'   => array(),
			'threadlist' => array()
		);

		// Verify that we have permission to view these attachmentids
		$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
		$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

		if (count($list) != count($attachments))
		{
			return false;
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress,
				post.postid, post.threadid, post.dateline AS p_dateline, post.userid AS post_userid,
				thread.forumid, thread.threadid, thread.open,
				editlog.hashistory
			FROM " . TABLE_PREFIX . "attachment AS a
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = a.contentid)
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
				LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			WHERE a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if (!$id['inprogress'])
			{
				if (!$id['open'] AND !can_moderate($id['forumid'], 'canopenclose') AND !$this->registry->options['allowclosedattachdel'])
				{
					return false;
				}
				else if (!can_moderate($id['forumid'], 'caneditposts'))
				{
					$forumperms = fetch_permissions($id['forumid']);

					if (!($forumperms & $this->registry->bf_ugp_forumpermissions['caneditpost']) OR $this->registry->userinfo['userid'] != $id['userid'])
					{
						return false;
					}
					else if(!$this->registry->options['allowattachdel'] AND $this->registry->options['edittimelimit'] AND $id['p_dateline'] < TIMENOW - $this->registry->options['edittimelimit'] * 60)
					{
						return false;
					}
				}
			}

			if ($id['postid'])
			{
				$this->lists['postlist']["{$id['postid']}"]++;

				if ($this->log)
				{
					if (($this->registry->userinfo['permissions']['genericoptions'] & $this->registry->bf_ugp_genericoptions['showeditedby']) AND $id['p_dateline'] < (TIMENOW - ($this->registry->options['noeditedbytime'] * 60)))
					{
						if (empty($replaced["$id[postid]"]))
						{
							/*insert query*/
							$this->registry->db->query_write("
								REPLACE INTO " . TABLE_PREFIX . "editlog
									(postid, userid, username, dateline, hashistory)
								VALUES
									($id[postid],
									" . $this->registry->userinfo['userid'] . ",
									'" . $this->registry->db->escape_string($this->registry->userinfo['username']) . "',
									" . TIMENOW . ",
									" . intval($id['hashistory']) . ")
							");
							$replaced["$id[postid]"] = true;
						}
					}
					if ($this->registry->userinfo['userid'] != $id['post_userid'] AND can_moderate($id['forumid'], 'caneditposts'))
					{
						$postinfo = array(
							'postid'       =>& $id['postid'],
							'threadid'     =>& $id['threadid'],
							'forumid'      =>& $id['forumid'],
							'attachmentid' =>& $id['attachmentid'],
						);
						require_once(DIR . '/includes/functions_log_error.php');
						log_moderator_action($postinfo, 'attachment_removed');
					}
				}
			}
			if ($id['threadid'])
			{
				$this->lists['threadlist']["{$id['threadid']}"]++;
			}
		}
		return true;
	}

	/**
	* post_delete function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_delete(&$attachdm = '')
	{
		// Update attach in the post table
		if (!empty($this->lists['postlist']))
		{
			require_once(DIR . '/includes/class_bootstrap_framework.php');
			require_once(DIR . '/vb/types.php');
			vB_Bootstrap_Framework::init();
			$types = vB_Types::instance();
			$contenttypeid = intval($types->getContentTypeID('vBProjectTools_Issue'));

			// COALASCE() used here due to issue in 5.1.30 (at least) where mysql reports COLUMN CANNOT BE NULL error
			// when the subquery returns a null
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "post AS p
				SET p.attach = COALESCE((
					SELECT COUNT(*)
					FROM " . TABLE_PREFIX . "attachment AS a
					WHERE
						p.postid = a.contentid
							AND
						a.contenttypeid = $contenttypeid
					GROUP BY a.contentid
				), 0)
				WHERE p.postid IN (" . implode(", ", array_keys($this->lists['postlist'])) . ")
			");
		}

		// Update attach in the thread table
		if (!empty($this->lists['threadlist']))
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "thread AS t
				SET t.attach = (
					SELECT SUM(attach)
					FROM " . TABLE_PREFIX . "post AS p
					WHERE p.threadid = t.threadid
					GROUP BY p.threadid
				)
				WHERE t.threadid IN (" . implode(", ", array_keys($this->lists['threadlist'])) . ")
			");
		}
	}
}

class vB_Attachment_Upload_Displaybit_vBProjectTools_Issue extends vB_Attachment_Upload_Displaybit
{
	/**
	*	Parses the appropriate template for contenttype that is to be updated on the calling window during an upload
	*
	* @param	array	Attachment information
	* @param	array	Values array pertaining to contenttype
	* @param	boolean	Disable template comments
	*
	* @return	string
	*/
	public function process_display_template($attach, $values = array(), $disablecomment = false)
	{
		$attach['extension'] = strtolower(file_extension($attach['filename']));
		$attach['filename']  = htmlspecialchars_uni($attach['filename']);
		$attach['filesize']  = vb_number_format($attach['filesize'], 1, true);
		$attach['imgpath']   = vB_Template_Runtime::fetchStyleVar('imgdir_attach') . "/$attach[extension].gif";

		$templater = vB_Template::create('newpost_attachmentbit');
			$templater->register('attach', $attach);
		return $templater->render($disablecomment);
	}
}

/**
* Class for common attachment tasks that are content agnostic
*
* @package 		vBulletin
* @version		$Revision: 897 $
* @date 		$Date: 2016-11-07 23:57:06 +0100 (Mon, 07 Nov 2016) $
*
*/
class vB_Attach_Display_Content_vBProjectTools_Issue
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	protected $registry = null;

	/**
	* Contenttype id
	*
	* @var	integer
	*/
	protected $contenttypeid = 0;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	string			Contenttype
	*/
	public function __construct(&$registry, $contenttype)
	{
		$this->registry =& $registry;

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		$this->contenttypeid = vB_Types::instance()->getContentTypeID($contenttype);
	}

	/**
	* Fetches the contenttypeid
	*
	*	@return	integer
	*/
	public function fetch_contenttypeid()
	{
		return $this->contenttypeid;
	}

	/**
	* Fetches a list of attachments for display on edit or preview
	*
	* @param	string	Posthash of this edit/add
	* @param	integer	Start time of this edit/add
	* @param	array		Combined existing and new attachments belonging to this content
	* @param	integer id of attachments owner
	* @param	string	Content specific values that need to be passed on to the attachment form
	* @param	string	$editorid of the message editor on the page that launched the asset manager
	* @param	integer	Number of fetched attachments, set by this function
	* @param	mixed		Who can view an attachment with no contentid (in progress), other than vbulletin->userinfo
	*
	* @return	string
	*/
	public function fetch_edit_attachments(&$posthash, &$poststarttime, &$postattach, $contentid, $values, $editorid, &$attachcount, $users = null)
	{
		global $show;

		require_once(DIR . '/includes/functions_file.php');
		// $maxattachsize is redundant, never used 
		$attachcount = 0;
		$attachment_js = '';

		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $this->registry->userinfo['userid'] . $this->registry->userinfo['salt']);
		}

		if (empty($postattach))
		{
			$postattach = $this->fetch_postattach($posthash, $contentid, $users);
		}

		if (!empty($postattach))
		{
			$attachdisplaylib =& vB_Attachment_Upload_Displaybit_Library::fetch_library($this->registry, $this->contenttypeid);
			foreach($postattach AS $attachmentid => $attach)
			{
				$attachcount++;
				$attach['html'] = $attachdisplaylib->process_display_template($attach, $values);
				$attachments .= $attach['html'];
				$show['attachmentlist'] = true;
				$attachment_js .= $attachdisplaylib->construct_attachment_add_js($attach);
			}
		}

		$templater = vB_Template::create('pt_newpost_attachment');
			$templater->register('attachments', $attachments);
			$templater->register('attachment_js', $attachment_js);
			$templater->register('editorid', $editorid);
			$templater->register('posthash', $posthash);
			$templater->register('contentid', $contentid);
			$templater->register('poststarttime', $poststarttime);
			$templater->register('attachuserid', $this->registry->userinfo['userid']);
			$templater->register('contenttypeid', $this->contenttypeid);
			$templater->register('values', $values);
		return $templater->render();
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	string		Posthash of this edit/add
	* @param	integer 	id of attachments owner
	* @param	mixed		Who can view an attachment with no contentid (in progress), other than vbulletin->userinfo
	*
	* @return	array
	*/
	public function fetch_postattach($posthash = 0, $contentid = 0, $users = null, $groupbyboth = false, $attachid = 0)
	{
		// if we were passed no information, simply return an empty array
		// to avoid a nasty database error
		if (empty($posthash) AND empty($contentid))
		{
			return array();
		}

		if (!$users)
		{
			$users = array($this->registry->userinfo['userid']);
		}
		else
		{
			if (is_array($users))
			{
				$temp = array_map("intval", $users);
				$users = $temp;
			}
			else if ($userid = intval($users))
			{
				$users = array($userid);
			}
			$users[] = $this->registry->userinfo['userid'];
		}

		$union = array();

		if ($contentid)
		{
			$union[] = "
				SELECT
					fd.thumbnail_dateline, fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_filesize,
					a.dateline, a.state, a.attachmentid, a.counter, a.contentid, a.filename, a.userid, a.settings, a.displayorder,
					at.contenttypes, i.ispatchfile, i.status, u.username
				FROM " . TABLE_PREFIX . "attachment AS a
					INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
					LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)
					LEFT JOIN " . TABLE_PREFIX . "pt_issueattach AS i ON (i.attachmentid = a.attachmentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS u ON (u.userid = a.userid)
				WHERE
					a.contentid = " . intval($contentid) . "
						AND
					a.contenttypeid = " . $this->contenttypeid . "
			";
		}

		if ($posthash)
		{
			$union[] = "
				SELECT
					fd.thumbnail_dateline, fd.filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.thumbnail_filesize,
					a.dateline, a.state, a.attachmentid, a.counter, a.contentid, a.filename, a.userid, a.settings, a.displayorder,
					at.contenttypes, i.ispatchfile, i.status, u.username
				FROM " . TABLE_PREFIX . "attachment AS a
					INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (fd.filedataid = a.filedataid)
					LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS at ON (at.extension = fd.extension)
					LEFT JOIN " . TABLE_PREFIX . "pt_issueattach AS i ON (i.attachmentid = a.attachmentid)
					LEFT JOIN " . TABLE_PREFIX . "user AS u ON (u.userid = a.userid)
				WHERE
					a.posthash = '" . $this->registry->db->escape_string($posthash) . "'
						AND
					a.userid IN (" . implode(',', $users) . ")
						AND
					a.contenttypeid = " . $this->contenttypeid . "
			";
		}

		if (count($union) > 1)
		{
			$unionsql = array(
				"(" . implode(") UNION ALL (", $union) . ")",
				"ORDER BY displayorder",
			);
		}
		else
		{
			$unionsql = array(
				$union[0],
				"ORDER BY a.contentid, a.displayorder",
			);
		}

		$postattach = array();
		$attachments = $this->registry->db->query_read_slave(implode("\r\n", $unionsql));
		while ($attachment = $this->registry->db->fetch_array($attachments))
		{
			$content = @unserialize($attachment['contenttypes']);
			$attachment['newwindow'] = $content[$this->contenttypeid]['n'];

			$postattach["$attachment[attachmentid]"] = $attachment;
		}

		return $postattach;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	array		Information about the content that owns these attachments
	* @param	array		List of attachments belonging to the specifed post
	* @param	boolean 	Display download count
	* @param	boolean 	Viewer has permission to download attachments
	* @param	boolean 	Viewer has permission to get attachments
	* @param	boolean 	Viewer has permission to set thumbnails
	*
	* @return	void
	*/
	function process_attachments(&$post, &$attachments, $hidecounter = false, $canmod = false, $canget = true, $canseethumb = true, $linkonly = false)
	{
		global $show, $vbphrase;

		if (!empty($attachments))
		{
			$show['modattachmentlink'] = ($canmod OR $post['userid'] == $this->registry->userinfo['userid']);
			$show['moderatedattachment'] = $show['thumbnailattachment'] = $show['otherattachment'] = false;
			$show['imageattachment'] = $show['imageattachmentlink'] = false;

			$attachcount = sizeof($attachments);
			$thumbcount = 0;

			if (!$this->registry->options['viewattachedimages'])
			{
				$showimagesprev = $this->registry->userinfo['showimages'];
				$this->registry->userinfo['showimages'] = false;
			}

			foreach ($attachments AS $attachmentid => $attachment)
			{
				if ($canget AND $canseethumb AND $attachment['thumbnail_filesize'] == $attachment['filesize'])
				{
					// This is an image that is already thumbnail sized..
					$attachment['hasthumbnail'] = 0;
					$attachment['forceimage'] = ($this->registry->options['viewattachedimages'] ? $this->registry->userinfo['showimages'] : 0);
				}				
				else if (!$canseethumb)
				{
					$attachment['hasthumbnail'] = 0;
				}

				$show['newwindow'] = $attachment['newwindow'];

				$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
				$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));
				$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);
				$attachment['issueid'] = $post['issueid'];

				if (vB_Template_Runtime::fetchStyleVar('dirmark'))
				{
					$attachment['filename'] .= vB_Template_Runtime::fetchStyleVar('dirmark');
				}

				($hook = vBulletinHook::fetch_hook('postbit_attachment')) ? eval($hook) : false;

				if ($attachment['state'] == 'visible')
				{
					$project = verify_project($post['projectid']);
					$issueperms = fetch_project_permissions($this->registry->userinfo, $project['projectid'], $post['issuetypeid']);

					$show['attachment_obsolete'] = ($attachment['status'] == 'obsolete');
					$show['manage_attach_link'] = (($issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattachedit']) AND (($issueperms['attachpermissions'] & $this->registry->pt_bitfields['attach']['canattacheditothers']) OR $this->registry->userinfo['userid'] == $attachment['userid']));

					if ($attachment['ispatchfile'])
					{
						$attachment['link'] = create_full_url('issue.php?' . $this->registry->session->vars['sessionurl'] . "do=patch&amp;attachmentid=$attachment[attachmentid]");
					}
					else
					{
						$attachment['link'] = create_full_url('attachment.php?' . $this->registry->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]");
					}

					$attachment['attachtime'] = vbdate($this->registry->options['timeformat'], $attachment['dateline']);
					$attachment['attachdate'] = vbdate($this->registry->options['dateformat'], $attachment['dateline'], true);

					$templater = vB_Template::create('pt_attachmentbit');
						$templater->register('attachment', $attachment);
						$templater->register('url', $attachmenturl);
					$post['attachmentbits'] .= $templater->render();
				}
				else
				{
					$templater = vB_Template::create('pt_attachmentbit');
						$templater->register('attachment', $attachment);
						$templater->register('url', $attachmenturl);
					$post['attachmentbits'] .= $templater->render();
				}
			}
		} // No else and defining $show['attachments'] to false - hide the full form and can't add any attachment!
	}
}

?>