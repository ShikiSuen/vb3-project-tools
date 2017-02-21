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

/**
* Issue note factory. Create/call this when you need to create a number of issue note objects.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueNoteFactory
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Information about the issue this note belongs to
	*
	* @var	array
	*/
	var $issue = array();

	/**
	* Information about the project this note belongs to
	*
	* @var	array
	*/
	var $project = array();

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Issue permissions for the browsing user
	*
	* @var	array
	*/
	var $browsing_perms = array();

	/**
	* Create an issue note object for the specified note
	*
	* @param	array	Note information
	*
	* @return	vB_IssueNote
	*/
	function &create($note)
	{
		switch ($note['type'])
		{
			case 'system':   $class_name = 'vB_Pt_IssueNote_System';   break;
			case 'petition': $class_name = 'vB_Pt_IssueNote_Petition'; break;
			case 'user':
			default:
				$class_name = 'vB_Pt_IssueNote_User';
				break;
		}

		// NOTE: stub objects that get cloned may give a speed boost
		return new $class_name($this->registry, $this, $this->bbcode, $this->issue, $this->project, $note);
	}
}

/**
* Generic issue note class.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueNote
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Factory object that created this object. Used for permission caching.
	*
	* @var	vB_Pt_IssueNoteFactory
	*/
	var $factory = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Information about the issue this note belongs to
	*
	* @var	array
	*/
	var $issue = array();

	/**
	* Information about the project this note belongs to
	*
	* @var	array
	*/
	var $project = array();

	/**
	* Information about this note
	*
	* @var	array
	*/
	var $note = array();

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = '';

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Pt_IssueNoteFactory
	* @param	array			Issue info
	* @param	array			Project info
	* @param	array			Note info
	*/
	function vB_Pt_IssueNote(&$registry, &$factory, &$bbcode, $issue, $project, $note)
	{
		if (!is_subclass_of($this, 'vB_Pt_IssueNote'))
		{
			trigger_error('Direct instantiation of vB_Pt_IssueNote class prohibited. Use the vB_Pt_IssueNoteFactory class.', E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;

		$this->issue = $issue;
		$this->project = $project;
		$this->note = $note;
	}

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		global $show;
		// preparation for display...
		$this->prepare_start();
		$this->process_date();

		if ($this->note['userid'])
		{
			$this->process_registered_user();
		}
		else
		{
			$this->process_unregistered_user();
		}

		$this->process_text();
		$this->prepare_end();

		// actual display...
		$project =& $this->project;
		$issue =& $this->issue;
		$note =& $this->note;

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;

		($hook = vBulletinHook::fetch_hook('project_issue_notebit')) ? eval($hook) : false;

		$templater = vB_Template::create($this->template);
			$templater->register('issue', $issue);
			$templater->register('note', $note);
		return $templater->render();
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		$this->note = array_merge($this->note, convert_bits_to_array($this->note['options'], $this->registry->bf_misc_useroptions));
	}

	/**
	* Any work to process the date info of a note
	*/
	function process_date()
	{
		$this->note['note_date'] = vbdate($this->registry->options['dateformat'], $this->note['dateline'], true);
		$this->note['note_time'] = vbdate($this->registry->options['timeformat'], $this->note['dateline']);

		if ($this->note['lasteditdate'])
		{
			$this->note['lastedit_date'] = vbdate($this->registry->options['dateformat'], $this->note['lasteditdate'], true);
			$this->note['lastedit_time'] = vbdate($this->registry->options['timeformat'], $this->note['lasteditdate']);
		}
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->note);

		if (!isset($this->factory->perm_cache[$this->note['userid']]))
		{
			$this->factory->perm_cache[$this->note['userid']] = cache_permissions($this->note, false);
		}

		// get avatar
		if ($this->note['avatarid'])
		{
			$this->note['avatarurl'] = $this->note['avatarpath'];
		}
		else
		{
			if ($this->note['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$this->note['avatarurl'] = $this->registry->options['avatarurl'] . '/avatar' . $this->note['userid'] . '_' . $this->note['avatarrevision'] . '.gif';
				}
				else
				{
					$this->note['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->note['userid'] . '&amp;dateline=' . $this->note['avatardateline'];
				}
				if ($this->note['avwidth'] AND $this->note['avheight'])
				{
					$this->note['avwidth'] = 'width="' . $this->note['avwidth'] . '"';
					$this->note['avheight'] = 'height="' . $this->note['avheight'] . '"';
				}
				else
				{
					$this->note['avwidth'] = '';
					$this->note['avheight'] = '';
				}
			}
			else
			{
				$this->note['avatarurl'] = '';
			}
		}

		if ( // no avatar defined for this user
			empty($this->note['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->note['avatarid'] AND !($this->factory->perm_cache[$this->note['userid']]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->note['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		$onlinestatus = 0;
		// now decide if we can see the user or not
		$last_activity = ($this->note['user_lastactivity'] ? $this->note['user_lastactivity'] : $this->note['lastactivity']);
		if ($last_activity > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->note['lastvisit'] != $last_activity)
		{
			if ($this->note['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->note['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$onlinestatus = 2;
				}
			}
			else
			{
				// user is online and visible
				$onlinestatus = 1;
			}
		}

		$this->note['onlinestatus'] = $onlinestatus;
		$show['onlinestatus'] = true;

		$show['profile'] = true;
	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		global $show;

		$this->note['rank'] = '';
		$this->note['notesperday'] = 0;
		$this->note['displaygroupid'] = 1;
		$this->note['username'] = $this->note['noteusername'];
		fetch_musername($this->note);
		//$this->note['usertitle'] = $vbphrase['guest'];
		$this->note['usertitle'] =& $this->registry->usergroupcache["0"]['usertitle'];
		$this->note['joindate'] = '';
		$this->note['notes'] = 'n/a';
		$this->note['avatar'] = '';
		$this->note['profile'] = '';
		$this->note['email'] = '';
		$this->note['useremail'] = '';
		$this->note['icqicon'] = '';
		$this->note['aimicon'] = '';
		$this->note['yahooicon'] = '';
		$this->note['msnicon'] = '';
		$this->note['skypeicon'] = '';
		$this->note['homepage'] = '';
		$this->note['findnotes'] = '';
		$this->note['signature'] = '';
		$this->note['reputationdisplay'] = '';

		$show['onlinestatus'] = false;
		$show['profile'] = false;
		$show['avatar'] = false;
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		$this->note['message'] = $this->bbcode->parse($this->note['pagetext'], 'pt');
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;
		$issueperms = $this->factory->browsing_perms;
		$vbulletin =& $this->registry;

		if ($issueperms['generalpermissions'] & $vbulletin->pt_bitfields['general']['canmanage'])
		{
			$this->note['noteipaddress'] = ($this->note['noteipaddress'] ? htmlspecialchars_uni(long2ip($this->note['noteipaddress'])) : '');
		}
		else
		{
			$this->note['noteipaddress'] = '';
		}

		$show['edit_note'] = can_edit_issue_note($this->issue, $this->note, $issueperms);
		$show['edit_history'] = ($this->note['lasteditdate'] AND $show['edit_note']);

		$show['reply_note'] = (
			($this->issue['state'] == 'open' OR $issueperms['postpermissions'] & $vbulletin->pt_bitfields['post']['cancloseissue'])
			AND	$this->note['visible'] != 'deleted'
		);

		$this->note['newflag'] = ($this->note['dateline'] > issue_lastview($this->issue));
	}
}

/**
* Generic issue note class for a user note.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueNote_User extends vB_Pt_IssueNote
{
	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'pt_issuenotebit_user';

	function prepare_end()
	{
		parent::prepare_end();

		global $show;

		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}
}

/**
* Generic issue note class for a petition note.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueNote_Petition extends vB_Pt_IssueNote_User
{
	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'pt_issuenotebit_petition';

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		global $vbphrase;

		parent::prepare_start();

		$this->note['petitionstatus'] = $vbphrase['issuestatus' . $this->note['petitionstatusid']];
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		parent::prepare_end();

		global $show, $vbphrase;
		$show['process_petition'] = ($this->note['petitionresolution'] == 'pending' AND $show['status_edit']);

		$this->note['petition_text'] = construct_phrase($vbphrase['petition_change_x_' . $this->note['petitionresolution']], $this->note['petitionstatus']);
	}
}

/**
* Generic issue note class for a system note.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Pt_IssueNote_System extends vB_Pt_IssueNote
{
	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'pt_issuenotebit_system';

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		global $vbulletin, $db, $show, $vbphrase;

		$changes = unserialize($this->note['pagetext']);
		if (!is_array($changes))
		{
			$this->note['message'] = '';
			return;
		}

		$this->note['message'] = '';

		foreach (translate_system_note($changes) AS $entry)
		{
			$templater = vB_Template::create('pt_issuenotebit_systembit');
				$templater->register('entry', $entry);
			$this->note['message'] .= $templater->render();
		}
	}
}
?>
