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

vBulletin.events.systemInit.subscribe(function()
{
	if (AJAX_Compatible)
	{
		vB_Pt_Petition_Watcher = new vB_Pt_Petition_Watcher();
	}
});

function vB_Pt_Petition_Issuenote_Vars(args)
{
	this.init();
}

vB_Pt_Petition_Issuenote_Vars.prototype.init = function()
{
	this.objecttype = "issuenoteid";
	this.getaction = "message";

	this.messagetype = "issuenote_message_";
	this.containertype = "issuenote_";
	this.responsecontainer = "commentbits";
}

/**
* Class to create Petition controls.
*
* @package	vBulletin Project Tools
* @version	$Rev$
* @date		$Date$
*/
function vB_Pt_Petition_Watcher()
{
	this.editorcounter = 0;
	this.controls = new Object();
	this.open_objectid = null;
	this.vars = new Object();
	this.init();
}

/**
 * Initialisation & Creation of objects
 */
vB_Pt_Petition_Watcher.prototype.init = function()
{
	if (vBulletin.elements["vB_Pt_Petition"])
	{
		for (var i = 0; i < vBulletin.elements["vB_Pt_Petition"].length; i++)
		{
			var objectid = vBulletin.elements["vB_Pt_Petition"][i].splice(0, 1)[0];

			var objecttype = vBulletin.elements["vB_Pt_Petition"][i].splice(0, 1)[0];

			var args = vBulletin.elements["vB_Pt_Petition"][i];

			var vartype = '';

			eval("vartype = typeof(vB_Pt_Petition_" + objecttype + "_Vars);");

			if (vartype == 'undefined')
			{
				console.log('not found: ' + 'vB_Pt_Petition_' + objecttype + '_Vars');
				continue;
			}

			var vars = null;

			if (typeof(this.vars[objecttype]) == 'undefined')
			{
				var obj = null;

				eval("obj = new vB_Pt_Petition_" + objecttype + "_Vars(args);");

				this.vars[objecttype] = obj;

				vars = this.vars[objecttype];
			}
			else if (this.vars[objecttype].peritemsettings == true)
			{
				eval ("vars = new vB_Pt_Petition_" + objecttype + "_Vars(args);");
			}
			else
			{
				vars = this.vars[objecttype];
			}

			var editbutton = YAHOO.util.Dom.get(this.vars[objecttype].containertype + "edit_" + objectid);

			if (editbutton)
			{
				this.controls[objecttype + '_' + objectid] = this.fetch_petition_class(objectid, vars, objecttype + '_' + objectid);
				this.controls[objecttype + '_' + objectid].init();
			}
			else
			{
				console.log(vars.containertype + "_edit_" + objectid + " not found");
			}
		}
		vBulletin.elements["vB_Pt_Petition"] = null;
	}
}

/**
 * Function to fetch the correct editor class
 * (Fetches generic class if no specific class found)
 *
 * @param	string	The Object Id
 * @param	string	The Object Type
 * @param	object	The Variables for the Class
 * @param	object	The Edit Button
 *
 * @returns	object
 *
 */
vB_Pt_Petition_Watcher.prototype.fetch_petition_class = function(objectid, vars, controlid)
{
	var obj = null;

	obj = new vB_Pt_Petition_Generic(objectid, this, vars, controlid);

	return obj;
}

/**
 * Closes the Quick Editor
 *
 */
vB_Pt_Petition_Watcher.prototype.close_all = function()
{
	if (this.open_objectid)
	{
		this.controls[this.open_objectid].abort();
	}
}

/**
 * Hides the error prompt
 *
 */
vB_Pt_Petition_Watcher.prototype.hide_errors = function()
{
	if (this.open_objectid)
	{
		this.controls[this.open_objectid].hide_errors();
	}
}

/**
 * Generic Class for Quick Editor
 *
 * @param	string	The Object Id
 * @param	object	The Watcher Class
 * @param	object	The Variables
 * @param	object	The Edit Button
 *
 */
function vB_Pt_Petition_Generic(objectid, watcher, vars, controlid)
{
	this.objectid = objectid;
	this.watcher = watcher;
	this.vars = vars;
	this.controlid = controlid;
	this.originalhtml = null;
	this.ajax_req = null;
	this.show_advanced = true;
	this.messageobj = null;
	this.node = null;
	this.progress_indicator = null;
	this.editbutton = null;
}


/**
 * Initialise/Re-initialise the object
 *
 */
vB_Pt_Petition_Generic.prototype.init = function()
{
	this.originalhtml = null;
	this.ajax_req = null;
	this.show_advanced = true;

	this.messageobj = YAHOO.util.Dom.get(this.vars.messagetype + this.objectid);
	this.node = YAHOO.util.Dom.get(this.vars.containertype + this.objectid);
	this.progress_indicator = YAHOO.util.Dom.get(this.vars.containertype + "petition_progress_" + this.objectid);

	// One of argument is an array of text: confirm[yes] and confirm[no]
	this.confirm_yes = YAHOO.util.Dom.get(this.vars.containertype + "petition_yes_" + this.objectid);
	this.confirm_no = YAHOO.util.Dom.get(this.vars.containertype + "petition_no_" + this.objectid);

	if (this.progress_indicator)
	{
		console.log("found progress image: " + this.vars.containertype + "petition_progress_" + this.objectid);
	}

	YAHOO.util.Event.on(YAHOO.util.Dom.get(this.vars.containertype + "petition_yes_" + this.objectid), "click", this.save_yes, this, true);
	YAHOO.util.Event.on(YAHOO.util.Dom.get(this.vars.containertype + "petition_no_" + this.objectid), "click", this.save_no, this, true);
}

/**
 * Removes click handler, and prevents memory leakage
 *
 */
vB_Pt_Petition_Generic.prototype.remove_clickhandler = function()
{
	YAHOO.util.Event.purgeElement();
}

/**
 * Handles unspecified AJAX error when saving
 *
 * @param	object	YUI AJAX
 *
 */
vB_Pt_Petition_Generic.prototype.handle_save_error = function(ajax)
{
	vBulletin_AJAX_Error_Handler(ajax);

	alert("Error when saving");
}

/**
 * Destroy the petition form, and use the specified text as the post contents
 *
 * @param	string	Text of post
 *
 */
vB_Pt_Petition_Generic.prototype.restore = function(post_html, type)
{
	this.hide_errors(true);

	/*if (this.editorid && vB_Editor[this.editorid] && vB_Editor[this.editorid].initialized)
	{
		vB_Editor[this.editorid].destroy();
	}*/

	if (type == 'node')
	{
		// Usually called when message is saved
		var newnode = string_to_node(post_html);
		this.node.parentNode.replaceChild(newnode, this.node);
	}
	else
	{
		// Usually called when message edit is cancelled
		this.messageobj.innerHTML = post_html;
	}

	this.watcher.open_objectid = null;
};

/**
 * Save the petition decision via AJAX
 *
 * @param	event	Event Object
 *
 */
vB_Pt_Petition_Generic.prototype.save_yes = function(e)
{
	YAHOO.util.Event.stopEvent(e);

	document.body.style.cursor = 'wait';

	this.ajax_req = YAHOO.util.Connect.asyncRequest("POST", "projectpost.php?do=processpetition&confirm[yes]=" + vbphrase["yes"] + "&issuenoteid=" + this.objectid,{
		success: this.update,
		faulure: this.handle_save_error,
		timeout: vB_Default_Timeout,
		scope: this
	}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&do=processpetition&ajax=1&issuenoteid="
		+ this.objectid
		+ '&confirm[yes]=' + vbphrase["yes"]
		+ '&relpath=' + PHP.urlencode(RELPATH)
	);

	this.pending = true;
};

/**
 * Save the petition decision via AJAX
 *
 * @param	event	Event Object
 *
 */
vB_Pt_Petition_Generic.prototype.save_no = function(e)
{
	YAHOO.util.Event.stopEvent(e);

	document.body.style.cursor = 'wait';

	this.ajax_req = YAHOO.util.Connect.asyncRequest("POST", "projectpost.php?do=processpetition&confirm[no]=" + vbphrase["no"] + "&issuenoteid=" + this.objectid,{
		success: this.update,
		faulure: this.handle_save_error,
		timeout: vB_Default_Timeout,
		scope: this
	}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + "&do=processpetition&ajax=1&issuenoteid="
		+ this.objectid
		+ '&confirm[no]=' + vbphrase["no"]
		+ '&relpath=' + PHP.urlencode(RELPATH)
	);

	this.pending = true;
};

/**
 * Check for errors etc. and initialize restore when AJAX says save() is complete
 *
 * @param	object	YUI AJAX
 *
 * @return	boolean	false
 *
 */
vB_Pt_Petition_Generic.prototype.update = function(ajax)
{
	if (ajax.responseXML)
	{
		this.pending = false;
		document.body.style.cursor = 'auto';

		// this is the nice error handler, of which Safari makes a mess
		if (fetch_tag_count(ajax.responseXML, 'error'))
		{
			var errors = fetch_tags(ajax.responseXML, 'error');

			var error_html = '<ol>';

			for (var i = 0; i < errors.length; i++)
			{
				error_html += '<li>' + errors[i].firstChild.nodeValue + '</li>';
			}
			error_html += '</ol>';

			this.show_errors(error_html);
		}
		else
		{
			var message = ajax.responseXML.getElementsByTagName("message");
			this.restore(message[0].firstChild.nodeValue, 'node');
			this.remove_clickhandler(); // To stop memory leaks
			this.init();
		}
	}
	return false;
}

/**
 * Pop up a window showing errors
 *
 * @param	string	Error HTML
 *
 */
vB_Pt_Petition_Generic.prototype.show_errors = function(errortext)
{
	YAHOO.util.Dom.get('ajax_post_errors_message').innerHTML = errortext;
	var errortable = YAHOO.util.Dom.get('ajax_post_errors');
	errortable.style.width = '400px';
	errortable.style.zIndex = 500;
	var measurer = (is_saf ? 'body' : 'documentElement');
	errortable.style.left = (is_ie ? document.documentElement.clientWidth : self.innerWidth) / 2 - 200 + document[measurer].scrollLeft + 'px';
	errortable.style.top = (is_ie ? document.documentElement.clientHeight : self.innerHeight) / 2 - 150 + document[measurer].scrollTop + 'px';
	YAHOO.util.Dom.removeClass(errortable, "hidden");
}

/**
 * Hide the error Window
 *
 */
vB_Pt_Petition_Generic.prototype.hide_errors = function(skip_focus_check)
{
	this.errors = false;
	var errors = YAHOO.util.Dom.get("ajax_post_errors")
	if (errors)
	{
		YAHOO.util.Dom.addClass(errors, "hidden");
	}
	if (skip_focus_check != true)
	{
		vB_Editor[this.editorid].check_focus();
	}
}