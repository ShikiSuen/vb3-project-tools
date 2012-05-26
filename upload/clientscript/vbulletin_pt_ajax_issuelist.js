/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

var vB_IssueTitle_Editor = null;

if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2))
{
	vB_XHTML_Ready.subscribe(function () { vB_AJAX_IssueList_Init('projectissuelist'); });
}

/**
* Adds ondblclick events to appropriate elements for title editing
*
* @package	vBulletin Project Tools
* @version	$Revision$
* @date		$Date$
*
* @param	string	The ID of the issue listing element (usually 'projectissuelist')
*/
function vB_AJAX_IssueList_Init(issuelistid)
{
	// This check is above outside the function but here as well for any calls to vB_AJAX_IssueList_Init made outside of this file
	if (!YAHOO.util.Dom.get(issuelistid) || !AJAX_Compatible || (typeof(vb_disable_ajax) != "undefined" && vb_disable_ajax >= 2))
	{
		return;
	}

	var issuebits = YAHOO.util.Dom.getElementsByClassName("issuebit", "li", issuelistid);
	for (var i = 0; i < issuebits.length; i++)
	{
		if (issuebits[i].id.match(/^issue_/))
		{
			YAHOO.util.Event.on(issuebits[i], "dblclick", vB_AJAX_IssueList_Events.prototype.issuetitle_doubleclick);
		}
	}
}

// #############################################################################
// vB_AJAX_TitleEdit
// #############################################################################

/**
* Class to handle issue title editing with XML-HTTP
*
* @package	vBulletin Project Tools
* @version	$Revision$
* @date		$Date$
*
* @param	object	The <td> containing the title element
*/
function vB_AJAX_TitleEdit(obj)
{
	this.obj = obj;
	this.issueid = this.obj.id.substr(this.obj.id.lastIndexOf('_') + 1);
	this.linkobj = fetch_object('issue_title_' + this.issueid);
	this.container = this.linkobj.parentNode;
	this.editobj = null;
	this.xml_sender = null;

	this.origtitle = '';
	this.editstate = false;

	this.progress_image = new Image();
	this.progress_image.src = IMGDIR_MISC + "/11x11progress.gif";

	// =============================================================================
	// vB_AJAX_TitleEdit methods

	/**
	* Function to initialize the editor for a thread title
	*/
	this.edit = function()
	{
		if (this.editstate == false)
		{
			// create the new editor input box properties...
			this.inputobj = document.createElement('input');
			this.inputobj.type = 'text';
			this.inputobj.size = 50;
			// read in value for titlemaxchars from $vbulletin->options['titlemaxchars'], specified in template or default to 85
			this.inputobj.maxLength = ((typeof(titlemaxchars) == "number" && titlemaxchars > 0) ? titlemaxchars : 85);
			this.inputobj.style.width = Math.max(this.linkobj.offsetWidth, 250) + 'px';
			this.inputobj.className = 'textbox';
			this.inputobj.value = PHP.unhtmlspecialchars(this.linkobj.innerHTML);
			this.inputobj.title = this.inputobj.value;

			// ... and event handlers
			this.inputobj.onblur = vB_AJAX_IssueList_Events.prototype.titleinput_onblur;
			this.inputobj.onkeypress = vB_AJAX_IssueList_Events.prototype.titleinput_onkeypress;

			// insert the editor box and select it
			this.editobj = this.container.insertBefore(this.inputobj, this.linkobj);
			this.editobj.select();

			// store the original text
			this.origtitle = this.linkobj.innerHTML;

			// hide the link object
			this.linkobj.style.display = 'none';

			// declare that we are in an editing state
			this.editstate = true;
		}
	}

	/**
	* Function to restore a thread title in the editing state
	*/
	this.restore = function()
	{
		if (this.editstate == true)
		{
			// do we actually need to save?
			if (this.editobj.value != this.origtitle)
			{
				this.container.appendChild(this.progress_image);
				this.save(this.editobj.value);
			}
			else
			{
				// set the new contents for the link
				this.linkobj.innerHTML = this.editobj.value;
			}

			// remove the editor box
			this.container.removeChild(this.editobj);

			// un-hide the link
			this.linkobj.style.display = '';

			// declare that we are in a normal state
			this.editstate = false;
			this.obj = null;
		}
	}

	/**
	* Function to save an edited thread title
	*
	* @param	string	Edited title text
	*
	* @return	string	Validated title text
	*/
	this.save = function(titletext)
	{
		YAHOO.util.Connect.asyncRequest("POST", "projectajax.php?do=updateissuetitle&issueid=" + this.issueid, {
			success: this.handle_ajax_response,
			timeout: vB_Default_Timeout,
			scope: this
		}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&do=updateissuetitle&issueid=" + this.issueid + '&title=' + PHP.urlencode(titletext));
	}

	/**
	* Handles AJAX response request
	*
	* @param	object	YUI AJAX
	*/
	this.handle_ajax_response = function(ajax)
	{
		if (ajax.responseXML)
		{
			this.linkobj.innerHTML = ajax.responseXML.getElementsByTagName('linkhtml')[0].firstChild.nodeValue;
			this.linkobj.href = ajax.responseXML.getElementsByTagName('linkhref')[0].firstChild.nodeValue;
		}

		this.container.removeChild(this.progress_image);
		vB_IssueTitle_Editor.obj = null;
	}

	// start the editor
	this.edit();
}

// #############################################################################
// Issuelist event handlers

/**
* Class to handle events in the issuelist
*/
function vB_AJAX_IssueList_Events()
{
}

/**
* Handles double-clicking on issue title cells to initialize title edit
*/
vB_AJAX_IssueList_Events.prototype.issuetitle_doubleclick = function(e)
{
	if (vB_IssueTitle_Editor && vB_IssueTitle_Editor.obj == this)
	{
		return false;
	}
	else
	{
		try
		{
			vB_IssueTitle_Editor.restore();
		}
		catch(e) {}

		vB_IssueTitle_Editor = new vB_AJAX_TitleEdit(this);
	}
};

/**
* Handles blur events on issue title input boxes
*/
vB_AJAX_IssueList_Events.prototype.titleinput_onblur = function(e)
{
	vB_IssueTitle_Editor.restore();
};

/**
* Handles keypress events on issue title input boxes
*/
vB_AJAX_IssueList_Events.prototype.titleinput_onkeypress = function (e)
{
	e = e ? e : window.event;
	switch (e.keyCode)
	{
		case 13: // return / enter
		{
			vB_IssueTitle_Editor.inputobj.blur();
			return false;
		}
		case 27: // escape
		{
			vB_IssueTitle_Editor.inputobj.value = vB_IssueTitle_Editor.origtitle;
			vB_IssueTitle_Editor.inputobj.blur();
			return true;
		}
	}
};