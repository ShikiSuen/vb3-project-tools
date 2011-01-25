/*!======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.2
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// This code could really use a restructuring into OOP

var qr_repost = false;
var qr_errors_shown = false;
var qr_active = false;
var qr_ajax = null;
var qr_postid = null;
var qr_withquote = null;
var qr_imgsrc = '';
var clickedelm = false;
var qr_require_click = false;

/**
* Initializes the quick reply system
*/
if (typeof(vB_XHTML_Ready) != "undefined")
{
	vB_XHTML_Ready.subscribe(qr_init);
}

function qr_init()
{
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
		qr_disable_controls();
		qr_init_buttons(fetch_object('posts'));
	}
}

/**
* Steps through the given object activating all quick reply buttons it finds
*
* @param	object	HTML object to search
*/
function qr_init_buttons(obj)
{
	// intercept post button clicks to use inline form
	var anchors = fetch_tags(obj, 'a');
	for (var i = 0; i < anchors.length; i++)
	{
		// reply button
		if (anchors[i].id && (anchors[i].id.substr(0, 3) == 'qr_' || anchors[i].id.substr(0, 5) == 'qrwq_'))
		{
			YAHOO.util.Event.on(anchors[i], "click", qr_newreply_activate, this);
			//anchors[i].onclick = function(e) { return qr_newreply_activate(this.id.substr(3), false); };
		}
	}

	// set the "+Reply to Thread" buttons onlclick events
	var replytothreadids = ["newreplylink_top", "newreplylink_bottom"];
	YAHOO.util.Event.on(replytothreadids, "click", qr_replytothread_activate, this);
	YAHOO.util.Event.on(replytothreadids, "dblclick", function(e) { window.location = this.href; }, this);
}

/**
* Disables the controls in the quick reply system
*/
function qr_disable_controls()
{
	if (require_click)
	{
		fetch_object('qr_postid').value = 0;

		vB_Editor[QR_EditorID].disable_editor(vbphrase['click_quick_reply_icon']);

		var qr_sig = fetch_object('cb_signature');
		if (qr_sig != null)
		{
			qr_sig.disabled = true;
		}

		active = false;
		qr_active = false;
	}
	else
	{
		vB_Editor[QR_EditorID].write_editor_contents('');
		qr_active = true;
	}
}

/**
* Activates the controls in the quick reply system
*
* @param	integer	Post ID of the post to which we are replying
*
* @return	boolean	false
*/
function qr_activate(postid, initialtext)
{
	var qr_collapse = fetch_object('collapseobj_quickreply');
	if (qr_collapse && qr_collapse.style.display == "none")
	{
		toggle_collapse('quickreply');
	}

	fetch_object('qr_postid').value = postid;
	if (fetch_object('qr_specifiedpost'))
	{
		fetch_object('qr_specifiedpost').value = 1;
	}

	//fetch_object('qr_preview').select();

	var qr_sig = fetch_object("cb_signature");
	if (qr_sig)
	{
		qr_sig.disabled = false;
		// Workaround for 3.5 Bug # 1618: Set checked as Firefox < 1.5 "forgets" that when checkbox is disabled via JS
		qr_sig.checked = true;
	}

	// prepare the initial text
	initialtext = (initialtext ? initialtext : '');
	//activate the editor with initial text
	vB_Editor[QR_EditorID].enable_editor(initialtext);

	if (!is_ie && vB_Editor[QR_EditorID].wysiwyg_mode)
	{
		fetch_object('qr_scroll').scrollIntoView(false);
	}

	vB_Editor[QR_EditorID].check_focus();

	qr_active = true;
	return false;
}

/**
* Activates the controls for "+Reply to Thread" buttons
*
* @param	integer	Post ID of the post to which we are replying
*
* @return	boolean	false
*/
function qr_replytothread_activate(e)
{
	var href = this.href;
	if (qr_postid == last_post_id && qr_withquote == true)
	{
		window.location = href;
		return true;
	}

	YAHOO.util.Event.preventDefault(e);
	qr_postid = last_post_id;
	qr_withquote = true;

	YAHOO.util.Dom.setStyle("progress_newreplylink_top", "display", "");
	YAHOO.util.Dom.setStyle("progress_newreplylink_bottom", "display", "");
	document.body.style.cursor = 'wait';

	var qr_threadid = YAHOO.util.Dom.get("qr_threadid").value;

	qr_ajax = YAHOO.util.Connect.asyncRequest("POST", "ajax.php", {
		success: qr_replytothread_handle_activate,
		failure: function(ajax) {window.location = href;},
		timeout: vB_Default_Timeout
	}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + '&do=getquotes&t=' + qr_threadid);
}

/**
* Handles quick reply activations for "+Reply to Thread" buttons when AJAX comes back with quote bb codes
*
* @param	object	YUI AJAX
*/
function qr_replytothread_handle_activate(ajax)
{
	// put the qr form back to its initial state just to avoid any weirdness
	qr_reset();
	qr_disable_controls();
	qr_hide_errors();

	// if coming from an ajax response,
	// extract the quoted text from the XML
	var quote_text = '';
	if (ajax)
	{
		var quotes = ajax.responseXML.getElementsByTagName('quotes');
		if (quotes.length && quotes[0].firstChild)
		{
			var quote_text = quotes[0].firstChild.nodeValue;
			if (vB_Editor[QR_EditorID].wysiwyg_mode)
			{
				quote_text = quote_text.replace(/\r?\n/g, "<br />");
			}
		}
	}

	// if we are in require click mode, we need to unhide the editor for reply to thread
	if (YAHOO.util.Dom.hasClass('qr_defaultcontainer','qr_require_click'))
	{
		YAHOO.util.Dom.removeClass('qr_defaultcontainer', 'qr_require_click');
		qr_require_click = true;
	}

	// now we activate the quick reply form
	qr_activate(last_post_id, quote_text);

	fetch_object('progress_newreplylink_top').style.display="none";
	fetch_object('progress_newreplylink_bottom').style.display="none";
	document.body.style.cursor = 'auto';

}

/**
* Activates the controls in the quick new reply system
*
* @param	integer	Post ID of the post to which we are replying
*
* @return	boolean	false
*/
function qr_newreply_activate(e)
{
	var withquote = false;
	if (this.id.substr(0, 3) == 'qr_')
	{
		var postid = this.id.substr(3);
	}
	else if (this.id.substr(0, 5) == 'qrwq_')
	{
		var postid = this.id.substr(5);
		withquote = true;
	}
	else
	{
		return true;
	}

	// if we are already editing this post inline with the same quote functionality,
	// take them to the advanced editor for clicking the same button again
	if (qr_postid == postid && qr_withquote == withquote)
	{
		return true;
	}

	YAHOO.util.Event.stopEvent(e);

	// otherwise, store postid id globally
	qr_postid = postid;
	qr_withquote = withquote;

	// displaying progress spinner instead of reply icon, setting cursor to hourglass
	if (YAHOO.util.Dom.get("progress_" + postid))
	{
		var replyimgid = (withquote ? 'quoteimg_' : 'replyimg_') + postid;
		qr_imgsrc = YAHOO.util.Dom.get(replyimgid).getAttribute("src");
		YAHOO.util.Dom.get(replyimgid).setAttribute("src", YAHOO.util.Dom.get("progress_" + postid).getAttribute("src"));
	}
	document.body.style.cursor = 'wait';

	// if we are quoting, grab proper bb codes from server, otherwise simply display quickreply form
	if (withquote)
	{
		qr_ajax = YAHOO.util.Connect.asyncRequest("POST", "ajax.php?do=getquotes&p=" + postid, {
			success: qr_handle_activate,
			failure: vBulletin_AJAX_Error_Handler,
			timeout: vB_Default_Timeout
		}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + '&do=getquotes&p=' + postid);
	}
	else
	{
		// display quickreply form with no quotes
		qr_handle_activate(false);
	}
}

/**
* Handles quick reply activations when AJAX comes back with quote bb codes
*
* @param	object	YUI AJAX
*/
function qr_handle_activate(ajax)
{
	// grab the poast id set globally before ajax call
	var postid = qr_postid;

	// put the qr form back to its initial state just to avoid any weirdness
	qr_reset();
	qr_disable_controls();
	qr_hide_errors();

	// reset the global id, since we are sill currently editing this postid
	qr_postid = postid;

	// if coming from an ajax response,
	// extract the quoted text from the XML
	var quote_text = '';
	if (ajax)
	{
		var quotes = ajax.responseXML.getElementsByTagName('quotes');
		if (quotes)
		{
			var quote_text = quotes[0].firstChild.nodeValue;
			if (vB_Editor[QR_EditorID].wysiwyg_mode)
			{
				quote_text = quote_text.replace(/\r?\n/g, "<br />");
			}
		}
	}

	// make the cancel button visible
	var cancelbtn = fetch_object('qr_cancelbutton');
	cancelbtn.style.display = '';

	// add form into container below the post we are replying to
	var qrobj = document.createElement("li");
	qrobj.id = "qr_" + postid;
	var post = YAHOO.util.Dom.get("post_" + postid);
	var qr_container = post.parentNode.insertBefore(qrobj, post.nextSibling);
	var qr_form = fetch_object('quick_reply');
	qr_container.appendChild(qr_form);

	// now we activate the quick reply form
	qr_activate(postid, quote_text);

	// hide the progress spinner and set hourglass back to default
	if (YAHOO.util.Dom.get("progress_" + postid))
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
function qr_reset()
{
	// set the current postid back to null
	qr_postid = null;

	// reset the post id to last post id
	fetch_object('qr_postid').value = last_post_id;

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
function qr_prepare_submit(formobj, minchars)
{
	if (qr_repost == true)
	{
		return true;
	}

	if (!allow_ajax_qr || !AJAX_Compatible)
	{
		// not last page, or threaded mode - do not attempt to use AJAX

		// images uploaded with the quick reply insert image button
		formobj.posthash.value = vBulletin.attachinfo.posthash;
		formobj.poststarttime.value = vBulletin.attachinfo.poststarttime;

		return qr_check_data(formobj, minchars);
	}
	else if (qr_check_data(formobj, minchars))
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
		
		// check if we need to update the page to reflect the thread 
		/// being open or closed or stickied or unstickied
		// if so don't run the new post through ajax
		var cb_openclose = fetch_object('cb_openclose');
		var cb_stickunstick = fetch_object('cb_stickunstick');
		if ((cb_openclose && cb_openclose.checked) || (cb_stickunstick && cb_stickunstick.checked))
		{
			return true;
		}

		if (YAHOO.util.Connect.isCallInProgress(qr_ajax))
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
			if (typeof ajax_last_post != 'undefined')
			{
				submitstring += '&ajax_lastpost=' + PHP.urlencode(ajax_last_post);
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

			qr_ajax_post(formobj.action, submitstring);
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
function qr_resubmit()
{
	qr_repost = true;

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
function qr_check_data(formobj, minchars)
{
	switch (fetch_object('qr_postid').value)
	{
		case '0':
		{
			// quick reply form will now default to replying to
			// last post on the current page
			fetch_object('qr_postid').value = last_post_id;
		}

		case 'who cares':
		{
			if (typeof formobj.quickreply != 'undefined')
			{
				formobj.quickreply.checked = false;
			}
			break;
		}
	}

	if (clickedelm == formobj.preview.value)
	{
		minchars = 0;
	}

	return vB_Editor[QR_EditorID].prepare_submit(0, minchars);
}

/**
* Sends quick reply data to newreply.php via AJAX
*
* @param	string	GET string for action (newreply.php)
* @param	string	String representing form data ('x=1&y=2&z=3' etc.)
*/
function qr_ajax_post(submitaction, submitstring)
{
	if (YAHOO.util.Connect.isCallInProgress(qr_ajax))
	{
		YAHOO.util.Connect.abort(qr_ajax);
	}

	qr_repost = false;

	qr_ajax = YAHOO.util.Connect.asyncRequest("POST", submitaction, {
		success: qr_do_ajax_post,
		failure: qr_handle_error,
		//scope: this,
		timeout: vB_Default_Timeout
	}, SESSIONURL + "securitytoken=" + SECURITYTOKEN + '&' + submitstring);
}

/**
* Handles an unspecified AJAX error
*
* @param	object	YUI AJAX
*/
function qr_handle_error(ajax)
{
	vBulletin_AJAX_Error_Handler(ajax);

	fetch_object('qr_posting_msg').style.display = 'none';
	document.body.style.cursor = 'default';

	qr_resubmit();
}

/**
* Handles quick reply data when AJAX says qr_ajax_post() is complete
*
* @param	object	YUI AJAX
*/
function qr_do_ajax_post(ajax)
{
	if (ajax.responseXML)
	{
		document.body.style.cursor = 'auto';
		fetch_object('qr_posting_msg').style.display = 'none';
		var i;

		if (fetch_tag_count(ajax.responseXML, 'postbit'))
		{
			// put the qr form back to its initial state
			qr_reset();

			ajax_last_post = ajax.responseXML.getElementsByTagName('time')[0].firstChild.nodeValue;
			qr_disable_controls();
			qr_hide_errors();

			var postbits = ajax.responseXML.getElementsByTagName('postbit');
			for (i = 0; i < postbits.length; i++)
			{
				var newdiv = document.createElement('div');
				newdiv.innerHTML = postbits[i].firstChild.nodeValue;
				var newpost = newdiv.getElementsByTagName('li')[0];

				var posts = YAHOO.util.Dom.get('posts');

				if (newpost)
				{
					var postbit = posts.appendChild(newpost);
					PostBit_Init(postbit, postbits[i].getAttribute('postid'));
					// scroll to the area where the newest post appeared
					newpost.scrollIntoView(false);
				}
			}

			// unselect all multiquoted posts on the page
			// because the server already killed those cookies
			if (typeof mq_unhighlight_all == 'function')
			{
				mq_unhighlight_all();
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

					qr_show_errors(error_html);

					return false;
				}
			}

			qr_resubmit();
		}
	}
	else
	{
		qr_resubmit();
	}
}

/**
* Un-hides the quick reply errors element
*
* @param	string	Error(s) to show
*
* @return	boolean	false
*/
function qr_show_errors(errortext)
{
	qr_errors_shown = true;
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
function qr_hide_errors()
{
	if (qr_errors_shown)
	{
		qr_errors_shown = true;
		YAHOO.util.Dom.addClass("qr_error_tbody", "hidden");
		return false;
	}
}

var vB_QuickReply = true;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 03:28, Tue Jan 25th 2011
|| # CVS: $RCSfile$ - $Revision: 38280 $
|| ####################################################################
\*======================================================================*/
