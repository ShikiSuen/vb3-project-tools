/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Attempts to display an issuenote via AJAX, falling back to opening a new window if AJAX not available
*
* @param	integer	Issuenote ID
*
* @return	boolean	False
*/
function display_issuenote(issuenoteid)
{
	if (AJAX_Compatible)
	{
		vB_IssuenoteLoader[issuenoteid] = new vB_AJAX_IssuenoteLoader(issuenoteid);
		vB_IssuenoteLoader[issuenoteid].init();
	}
	else
	{
		openWindow('issue.php?' + SESSIONURL + 'do=gotonote&issuenoteid=' + issuenoteid);
	}
	return false;
};

// #############################################################################
// vB_AJAX_IssuenoteLoader
// #############################################################################

var vB_IssuenoteLoader = new Array();

/**
* Class to load an issue note via AJAX
*
* @package	vBulletin Project Tools
* @version	$Revision$
* @date		$Date$
*
* @param	integer	Issue note ID
*/
function vB_AJAX_IssuenoteLoader(issuenoteid)
{
	this.issuenoteid = issuenoteid;
	this.issuenote = YAHOO.util.Dom.get('issuenote_' + this.issuenoteid);
};

/**
* Initiates the AJAX send to showpost.php
*/
vB_AJAX_IssuenoteLoader.prototype.init = function()
{
	if (this.issuenote)
	{
		issuenoteid = this.issuenoteid;

		YAHOO.util.Connect.asyncRequest("POST", "issuenote.php?issuenoteid=" + this.issuenoteid, {
			success: this.display,
			failure: this.handle_ajax_error,
			timeout: vB_Default_Timeout,
			scope: this
		}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&ajax=1&issuenoteid=" + this.issuenoteid);
	}
};

/**
* Handles AJAX Errors
*
* @param	object	YUI AJAX
*/
vB_AJAX_IssuenoteLoader.prototype.handle_ajax_error = function(ajax)
{
	//TODO: Something bad happened, try again
	vBulletin_AJAX_Error_Handler(ajax);
};

/**
* Takes the AJAX HTML output and replaces the existing issue note placeholder with the new HTML
*
* @param	object	YUI AJAX
*/
vB_AJAX_IssuenoteLoader.prototype.display = function(ajax)
{
	if (ajax.responseXML)
	{
		var issuenotebit = ajax.responseXML.getElementsByTagName("issuenotebit");

		if (issuenotebit.length)
		{
			var newissuenote = string_to_node(issuenotebit[0].firstChild.nodeValue);
			this.issuenote.parentNode.replaceChild(newissuenotebit, this.issuenote);

			//this.container.innerHTML = issuenotebit[0].firstChild.nodeValue;
			IssueNoteBit_Init(newissuenotebit, this.issuenoteid);
		}
		else
		{	// parsing of XML failed, probably IE
			openWindow('issue.php?' + SESSIONURL + 'do=gotonote&issuenoteid=' + this.issuenoteid);
		}
	}
};