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

// This code could really use a restructuring into OOP

var qr_pt_repost = false;
var qr_inited = false;
var qr_pt_errors_shown = false;
var qr_pt_active = false;
var qr_pt_ajax = null;
//var qr_issuenoteid = null;
var qr_imgsrc = '';
var clickedelm = false;
var qr_require_click = false;
var QR_EditorID = '';

/**
* Initializes the quick reply system
*/
if (typeof(vB_XHTML_Ready) != "undefined")
{
	vB_XHTML_Ready.subscribe(qr_pt_init);
}

function qr_pt_init()
{
	if (qr_inited)
	{
		return true;
	}

	qr_inited = true;

	QR_EditorID = 'vB_Editor_QR';

	if (typeof(vBulletin.attachinfo) == "undefined")
	{
		vBulletin.attachinfo = {
			posthash      : "",
			poststarttime : ""
		};
	}

	// make sure quick reply form is there before attempting initializaion
	if (fetch_object('quick_reply'))
	{
		qr_pt_disable_controls();
		qr_pt_init_buttons(fetch_object('issuenotes'));
	}
}

/**
* Steps through the given object activating all quick reply buttons it finds
*
* @param	object	HTML object to search
*/
function qr_pt_init_buttons(obj)
{
	// intercept post button clicks to use inline form
	var anchors = fetch_tags(obj, 'a');
	for (var i = 0; i < anchors.length; i++)
	{
		// reply button
		if (anchors[i].id && (anchors[i].id.substr(0, 3) == 'qr_' || anchors[i].id.substr(0, 5) == 'qrwq_'))
		{
			YAHOO.util.Event.on(anchors[i], "click", qr_pt_newreply_activate, this);
			//anchors[i].onclick = function(e) { return qr_pt_newreply_activate(this.id.substr(3), false); };
		}
	}
}

/**
* Disables the controls in the quick reply system
*/
function qr_pt_disable_controls()
{
	if (require_click)
	{
		//fetch_object('qr_issuenoteid').value = 0;

		vB_Editor[QR_EditorID].disable_editor(vbphrase['click_quick_reply_icon']);

		active = false;
		qr_pt_active = false;
	}
	else
	{
		vB_Editor[QR_EditorID].write_editor_contents('');
		qr_pt_active = true;
	}
}

/**
* Activates the controls in the quick reply system
*
* @param	integer	Post ID of the post to which we are replying
*
* @return	boolean	false
*/
function qr_pt_activate(issuenoteid, initialtext)
{
	var qr_collapse = fetch_object('collapseobj_quickreply');

	if (qr_collapse && qr_collapse.style.display == "none")
	{
		toggle_collapse('quickreply');
	}

	//fetch_object('qr_issuenoteid').value = issuenoteid;

	if (fetch_object('qr_specifiedissuenote'))
	{
		fetch_object('qr_specifiedissuenote').value = 1;
	}

	//fetch_object('qr_preview').select();

	// prepare the initial text
	initialtext = (initialtext ? initialtext : '');
	//activate the editor with initial text
	vB_Editor[QR_EditorID].enable_editor(initialtext);

	if (!is_ie && vB_Editor[QR_EditorID].wysiwyg_mode)
	{
		fetch_object('qr_scroll').scrollIntoView(false);
	}

	vB_Editor[QR_EditorID].check_focus();

	qr_pt_active = true;
	return false;
}

/**
* Handles quick reply activations when AJAX comes back with quote bb codes
*
* @param	object	YUI AJAX
*/
function qr_pt_handle_activate(ajax)
{
	// grab the issue note id set globally before ajax call
	//var issuenoteid = qr_issuenoteid;

	// put the qr form back to its initial state just to avoid any weirdness
	qr_pt_reset();
	qr_pt_disable_controls();
	qr_pt_hide_errors();


	// reset the global id, since we are sill currently editing this issuenoteid
	//qr_issuenoteid = issuenoteid;

	// make the cancel button visible
	var cancelbtn = fetch_object('qr_cancelbutton');
	cancelbtn.style.display = '';

	// add form into container below the post we are replying to
	var qrobj = document.createElement("li");
	qrobj.id = "qr_" + issuenoteid;
	var issuenote = YAHOO.util.Dom.get("issuenote_" + issuenoteid);
	var qr_container = issuenote.parentNode.insertBefore(qrobj, issuenote.nextSibling);
	var qr_form = fetch_object('quick_reply');
	qr_container.appendChild(qr_form);

	// now we activate the quick reply form
	qr_pt_activate(issuenoteid);

	// hide the progress spinner and set hourglass back to default
	if (YAHOO.util.Dom.get("progress_" + issuenoteid))
	{
		var replyimgid = (qr_withquote ? 'quoteimg_' : 'replyimg_') + postid;
		YAHOO.util.Dom.get(replyimgid).setAttribute("src", qr_imgsrc);
	}
	document.body.style.cursor = 'auto';
}

/**
* Puts the quick reply form back to its initial spot in the DOM
*
* @return	boolean	false
*/
function qr_pt_reset()
{
	// set the current issuenoteid back to null
	qr_issuenoteid = null;

	// reset the issue note id to last issue note id
	//fetch_object('qr_issuenoteid').value = last_issuenote_id;

	// remove the quick form element from the DOM
	var qr_form = fetch_object('quick_reply');
	var qr_container = fetch_object('qr_defaultcontainer');
	if (qr_form.parentNode != qr_container)
	{
		var qr_form_parent = qr_form.parentNode;

		qr_container.appendChild(qr_form);
		qr_form_parent.parentNode.removeChild(qr_form_parent);
	}

	// hide the cancel button
	var cancelbtn = fetch_object('qr_cancelbutton');
	cancelbtn.style.display = 'none';

	// re-enable the editor after moving it, with no inner text
	if (!require_click)
	{
		vB_Editor[QR_EditorID].enable_editor('');
	}

	// re-hide the editor if we are in require click mode
	if (qr_require_click && !YAHOO.util.Dom.hasClass('qr_defaultcontainer','qr_require_click'))
	{
		YAHOO.util.Dom.addClass('qr_defaultcontainer', 'qr_require_click');
	}

	return false;
}

/**
* Checks the contents of the new reply and decides whether or not to allow it through
*
* @param	object	<form> object containing quick reply
* @param	integer	Minimum allowed characters in message
*
* @return	boolean
*/
function qr_pt_prepare_submit(formobj, minchars)
{
	if (qr_pt_repost == true)
	{
		return true;
	}

	// it's possible to submit before qr_pt_init completes
	if (!qr_inited)
	{
		qr_pt_init();
	}

	if (!allow_ajax_qr || !AJAX_Compatible)
	{
		// not last page, or threaded mode - do not attempt to use AJAX

		// images uploaded with the quick reply insert image button
		formobj.posthash.value = vBulletin.attachinfo.posthash;
		formobj.poststarttime.value = vBulletin.attachinfo.poststarttime;

		return qr_pt_check_data(formobj, minchars);
	}
	else if (qr_pt_check_data(formobj, minchars))
	{
		if (typeof vb_disable_ajax != 'undefined' && vb_disable_ajax > 0)
		{
			// couldn't initialize, return true to allow click to go through
			return true;
		}

		if (is_ie && userAgent.indexOf('msie 5.') != -1)
		{
			// IE 5 has problems with non-ASCII characters being returned by
			// AJAX. Don't universally disable it, but if we're going to be sending
			// non-ASCII, let's not use AJAX.
			if (PHP.urlencode(formobj.message.value).indexOf('%u') != -1)
			{
				return true;
			}
		}

		if (YAHOO.util.Connect.isCallInProgress(qr_pt_ajax))
		{
			return false;
		}

		formobj.posthash.value = vBulletin.attachinfo.posthash;
		formobj.poststarttime.value = vBulletin.attachinfo.poststarttime;

		if (clickedelm == formobj.preview.value)
		{
			return true;
		}
		else
		{

			var submitstring = 'ajax=1';
			if (typeof ajax_last_issuenote != 'undefined')
			{
				submitstring += '&ajax_lastissuenote=' + PHP.urlencode(ajax_last_issuenote);
			}

			for (var i = 0; i < formobj.elements.length; i++)
			{
				var obj = formobj.elements[i];

				if (obj.name && !obj.disabled)
				{
					switch (obj.type)
					{
						case 'text':
						case 'textarea':
						case 'hidden':
							submitstring += '&' + obj.name + '=' + PHP.urlencode(obj.value);
							break;
						case 'checkbox':
						case 'radio':
							submitstring += obj.checked ? '&' + obj.name + '=' + PHP.urlencode(obj.value) : '';
							break;
						case 'select-one':
							submitstring += '&' + obj.name + '=' + PHP.urlencode(obj.options[obj.selectedIndex].value);
							break;
						case 'select-multiple':
							for (var j = 0; j < obj.options.length; j++)
							{
								submitstring += (obj.options[j].selected ? '&' + obj.name + '=' + PHP.urlencode(obj.options[j].value) : '');
							}
							break;
					}
				}
			}

			fetch_object('qr_posting_msg').style.display = '';
			document.body.style.cursor = 'wait';

			qr_pt_ajax_post(formobj.action, submitstring);
			return false;
		}
	}
	else
	{
		return false;
	}
}

/**
* Submit handler for resubmit after a failed AJAX attempt.
* Adds an extra input to note the failure on the PHP side.
*/
function qr_pt_resubmit()
{
	qr_pt_repost = true;

	var extra_input = document.createElement('input');
	extra_input.type = 'hidden';
	extra_input.name = 'ajaxqrfailed';
	extra_input.value = '1';

	var form = YAHOO.util.Dom.get('quick_reply');
	//TODO backwards compatibility with legacy style -- remove
	if (!form)
	{
		form = YAHOO.util.Dom.get('qrform');
	}

	form.appendChild(extra_input);
	form.submit();
}

/**
* Works with form data to decide what to do
*
* @param	object	<form> object containing quick reply
* @param	integer	Minimum allowed characters in message
*
* @return	boolean
*/
function qr_pt_check_data(formobj, minchars)
{
	/*switch (fetch_object('qr_issuenoteid').value)
	{
		case '0':
		{
			// quick reply form will now default to replying to
			// last issuen ote on the current page
			fetch_object('qr_issuenoteid').value = last_issuenote_id;
		}

		case 'who cares':
		{
			if (typeof formobj.quickreply != 'undefined')
			{
				formobj.quickreply.checked = false;
			}
			break;
		}
	}*/

	if (clickedelm == formobj.preview.value)
	{
		minchars = 0;
	}

	return vB_Editor[QR_EditorID].prepare_submit(0, minchars);
}

/**
* Sends quick reply data to projectpost.php via AJAX
*
* @param	string	GET string for action (projectpost.php)
* @param	string	String representing form data ('x=1&y=2&z=3' etc.)
*/
function qr_pt_ajax_post(submitaction, submitstring)
{
	if (YAHOO.util.Connect.isCallInProgress(qr_pt_ajax))
	{
		YAHOO.util.Connect.abort(qr_pt_ajax);
	}

	qr_pt_repost = false;

	qr_pt_ajax = YAHOO.util.Connect.asyncRequest("POST", submitaction, {
		success: qr_pt_do_ajax_post,
		failure: qr_pt_handle_error,
		//scope: this,
		timeout: vB_Default_Timeout
	}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + '&' + submitstring);
}

/**
* Handles an unspecified AJAX error
*
* @param	object	YUI AJAX
*/
function qr_pt_handle_error(ajax)
{
	vBulletin_AJAX_Error_Handler(ajax);

	fetch_object('qr_posting_msg').style.display = 'none';
	document.body.style.cursor = 'default';

	qr_pt_resubmit();
}

/**
* Handles quick reply data when AJAX says qr_pt_ajax_post() is complete
*
* @param	object	YUI AJAX
*/
function qr_pt_do_ajax_post(ajax)
{
	if (ajax.responseXML)
	{
		document.body.style.cursor = 'auto';
		fetch_object('qr_posting_msg').style.display = 'none';
		var i;

		if (fetch_tag_count(ajax.responseXML, 'issuenotebit'))
		{
			// put the qr form back to its initial state
			qr_pt_reset();

			ajax_last_issuenote = ajax.responseXML.getElementsByTagName('time')[0].firstChild.nodeValue;
			qr_pt_disable_controls();
			qr_pt_hide_errors();

			var issuenotebits = ajax.responseXML.getElementsByTagName('issuenotebit');
			for (i = 0; i < issuenotebits.length; i++)
			{
				var newdiv = document.createElement('div');
				newdiv.innerHTML = issuenotebits[i].firstChild.nodeValue;
				var newissuenote = newdiv.getElementsByTagName('li')[0];

				var issuenotes = YAHOO.util.Dom.get('issuenotes');

				if (newissuenote)
				{
					var issuenotebit = issuenotes.appendChild(newissuenote);
					IssueNoteBit_Init(issuenotebit, issuenotebits[i].getAttribute('issuenoteid'));
					// scroll to the area where the newest issue note appeared
					newissuenote.scrollIntoView(false);
				}
			}

			// unfocus the qr_submit button to prevent a space from resubmitting
			if (fetch_object('qr_submit'))
			{
				fetch_object('qr_submit').blur();
			}
		}
		else
		{
			if (!is_saf)
			{
				// this is the nice error handler, of which Safari makes a mess
				var errors = ajax.responseXML.getElementsByTagName('error');
				if (errors.length)
				{
					var error_html = '<ol>';
					for (i = 0; i < errors.length; i++)
					{
						error_html += '<li>' + errors[i].firstChild.nodeValue + '</li>';
					}
					error_html += '</ol>';

					qr_pt_show_errors(error_html);

					return false;
				}
			}

			qr_pt_resubmit();
		}
	}
	else
	{
		qr_pt_resubmit();
	}
}

/**
* Un-hides the quick reply errors element
*
* @param	string	Error(s) to show
*
* @return	boolean	false
*/
function qr_pt_show_errors(errortext)
{
	qr_pt_errors_shown = true;
	fetch_object('qr_error_td').innerHTML = errortext;
	YAHOO.util.Dom.removeClass("qr_error_tbody", "hidden");
	vB_Editor[QR_EditorID].check_focus();
	return false;
}

/**
* Hides the quick reply errors element
*
* @return	boolean	false
*/
function qr_pt_hide_errors()
{
	if (qr_pt_errors_shown)
	{
		qr_pt_errors_shown = true;
		YAHOO.util.Dom.addClass("qr_error_tbody", "hidden");
		return false;
	}
}

var vB_QuickReply = true;