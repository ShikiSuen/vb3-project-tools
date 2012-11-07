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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for PT issue changes.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Revision$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_IssueChange extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'issuechangeid' => array(TYPE_UINT,     REQ_INCR),
		'issueid'       => array(TYPE_UINT,     REQ_YES),
		'userid'        => array(TYPE_UINT,     REQ_YES),
		'dateline'      => array(TYPE_UNIXTIME, REQ_AUTO),
		'issuenoteid'   => array(TYPE_UINT,     REQ_AUTO),
		'field'         => array(TYPE_STR,      REQ_YES),
		'oldvalue'      => array(TYPE_STR,      REQ_NO),
		'newvalue'      => array(TYPE_STR,      REQ_YES)
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array(
		'create_sytem_note' => true,
		'roll_post_time_limit' => 120
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_issuechange';

	/**
	* Arrays to store stuff to save to admin-related tables
	*
	* @var	array
	*/
	var $pt_issuechange = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('issuechangeid = %1$d', 'issuechangeid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_IssueChange(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_issuechangedata_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition AND !isset($this->pt_issuechange['dateline']))
		{
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_issuechangedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($this->info['create_sytem_note'])
		{
			// create system note -- find a note to join to if there's a recent change
			if ($this->info['roll_post_time_limit'] > 0)
			{
				$last_note = $this->registry->db->query_first("
					SELECT pt_issuenote.*, pt_issuechange.userid AS changeuserid
					FROM " . TABLE_PREFIX . "pt_issuechange AS pt_issuechange
					INNER JOIN " . TABLE_PREFIX . "pt_issuenote AS pt_issuenote ON
						(pt_issuenote.issuenoteid = pt_issuechange.issuenoteid)
					WHERE pt_issuechange.dateline > " . ($this->fetch_field('dateline') - $this->info['roll_post_time_limit']) . "
						AND pt_issuechange.dateline <= " . $this->fetch_field('dateline') . "
						AND pt_issuechange.issueid = " . $this->fetch_field('issueid') . "
					ORDER BY dateline DESC
					LIMIT 1
				");
				if (empty($last_note) OR $last_note['changeuserid'] != $this->fetch_field('userid'))
				{
					$last_note = array();
				}

				if ($last_note)
				{
					// find some info on the issue
					$issue = $this->registry->db->query_first("
						SELECT *
						FROM " . TABLE_PREFIX . "pt_issue
						WHERE issueid = " . $this->fetch_field('issueid')
					);
					if ($issue['lastpost'] > $last_note['dateline'])
					{
						// only fold these changes if there isn't another note that would go between them
						$last_note = array();
					}
				}
			}
			else
			{
				$last_note = array();
			}

			$notedata =& datamanager_init('Pt_IssueNote_System', $this->registry, ERRTYPE_SILENT, 'pt_issuenote');

			if ($last_note)
			{
				// update the associated post
				$notedata->set_existing($last_note);
				$note_text = unserialize($last_note['pagetext']);

				$old_value = $this->fetch_field('oldvalue');
				$insert_pos = sizeof($note_text);
				foreach ($note_text AS $id => $entry)
				{
					if ($entry['field'] == $this->fetch_field('field'))
					{
						$old_value = $entry['oldvalue'];
						$insert_pos = $id;
						break;
					}
				}
			}
			else
			{
				// insert a new system note
				$notedata->set('issueid', $this->fetch_field('issueid'));
				$notedata->set('visible', 'visible');
				$notedata->set('userid', $this->fetch_field('userid'));
				$note_text = array();

				$old_value = $this->fetch_field('oldvalue');
				$insert_pos = 0;
			}

			$changeuser = fetch_userinfo($this->fetch_field('userid'));

			$note_text["$insert_pos"] = array(
				'field' => $this->fetch_field('field'),
				'oldvalue' => $old_value,
				'newvalue' => $this->fetch_field('newvalue')
			);
			$notedata->set('pagetext', serialize($note_text));
			$noteid = $notedata->save();

			if ($last_note)
			{
				// we updated, the associated note is actually $last_note
				$noteid = $last_note['issuenoteid'];
			}

			// need to refer to the issuenote now
			if ($noteid)
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "pt_issuechange SET
						issuenoteid = $noteid
					WHERE issuechangeid = " . $this->fetch_field('issuechangeid')
				);
			}
		}

		($hook = vBulletinHook::fetch_hook('pt_issuechangedata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// try to remove the entry from the issue note that this change is listed in
		if ($this->fetch_field('issuenoteid'))
		{
			$note = $this->registry->db->query_first("
				SELECT * FROM " . TABLE_PREFIX . "pt_issuenote
				WHERE issuenoteid = " . $this->fetch_field('issuenoteid')
			);

			$notedata =& datamanager_init('Pt_IssueNote', $this->registry, ERRTYPE_SILENT);
			$notedata->set_existing($note);

			$note_values = unserialize($note['pagetext']);

			foreach ($note_values AS $key => $value)
			{
				if ($value['field'] == $this->fetch_field('field')
					AND $value['oldvalue'] == $this->fetch_field('oldvalue')
					AND $value['newvalue'] == $this->fetch_field('newvalue')
				)
				{
					// we matched the change we're deleting, remove the entry
					unset($note_values["$key"]);
				}
			}

			if ($note_values)
			{
				// there is still at least one change, just update the text
				$notedata->set('pagetext', serialize($note_values));
				$notedata->save();
			}
			else
			{
				$notedata->delete();
			}

		}

		($hook = vBulletinHook::fetch_hook('pt_issuechangedata_delete')) ? eval($hook) : false;
		return true;
	}
}
?>
