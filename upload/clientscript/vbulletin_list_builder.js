/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

// #############################################################################
// vB_ListBuilder
// #############################################################################

vBulletin.events.systemInit.subscribe(function()
{
	if (vBulletin.elements["vB_ListBuilder"])
	{
		vBulletin.listBuilders = new Object();

		for (var i = 0; i < vBulletin.elements["vB_ListBuilder"].length; i++)
		{
			var element = vBulletin.elements["vB_ListBuilder"][i];
			if (typeof(element[2]) != "undefined")
			{
				var ajaxurls = element[2];
			}
			else if (vBulletin.ajaxurls[element[1]])
			{
				var ajaxurls = new Array(vBulletin.ajaxurls[element[1]][0], vBulletin.ajaxurls[element[1]][1]);
			}
			else
			{
				var fetchurl = new Array("ajaxreq.php", "do=fetch&field=%1$s");
				var saveurl = new Array("ajaxreq.php", "do=save&field=%1$s%2$s");
				var ajaxurls = new Array(fetchurl, saveurl);
			}

			vBulletin.listBuilders[element[1]] = new vB_ListBuilder(element[0], element[1], ajaxurls);
		}
		vBulletin.elements["vB_ListBuilder"] = null;
	}
});


// =============================================================================

/**
* vBulletin List Builder - Creates a popup containing two multi-selects with values that can be passed between the two to build a list
*
* @param	string	Element next to which the popup button will be placed
* @param	string	Arguments in the form of "int:week start day,string:element basename"
* @param	string	AJAX URLs for loading contents and saving selection
*/
function vB_ListBuilder(element, elementkey, ajaxurls)
{
	vBulletin.console("vB_ListBuilder '%s' :: Creating vB_ListBuilder", elementkey);
	this.element = YAHOO.util.Dom.get(element);
	this.elementkey = elementkey;
	this.display_element = this.element;

	if (typeof(ajaxurls) == "undefined" || !ajaxurls)
	{
		this.useajax = false;
		this.content_ready = true;
		this.init_controls();
	}
	else if (typeof(vBulletin.events["vBmenuShow_" + this.elementkey]) != 'undefined')
	{
		this.fetchurl = ajaxurls[0];
		this.saveurl = ajaxurls[1];

		this.useajax = true;
		this.content_ready = false;
		vBulletin.events["vBmenuShow_" + this.elementkey].subscribe(this.fetch_values, this, true);
	}

	// if this is given a value, that value will be added to the selected list when the next menu opens
	this.select_item_id = null;

	/*this.moo = YAHOO.util.Dom.get(this.elementkey).parentNode.insertBefore(document.createElement("a"), YAHOO.util.Dom.get(this.elementkey).nextSibling);
	this.moo.href = "#";
	this.moo.className = "smallfont";
	this.moo.innerHTML = "Assign Me";
	YAHOO.util.Event.on(this.moo, "click", this.select_item, this, true);*/
}

vB_ListBuilder.prototype.select_item = function(e)
{
	if (e)
	{
		YAHOO.util.Event.stopEvent(e);
	}

	this.select_item_id = 2;

	if (this.content_ready)
	{
		this.clear_selection();
		var do_move = false;

		for (var i = 0; i < this.select_n.options.length; i++)
		{
			if (this.select_n.options[i].value == this.select_item_id)
			{
				this.select_n.options[i].setAttribute("selected", true);
				this.select_n.options[i].selected = true;
				do_move = true;
				break;
			}
		}
		if (do_move)
		{
			this.button_y.click();
		}

		this.select_item_id = null;
		vBmenu.hide();
	}
	else
	{
		vBmenu.menus[this.elementkey].show(vBmenu.menus[this.elementkey].controlobj);
	}
}

/**
* Fetches the list of values via AJAX
*/
vB_ListBuilder.prototype.fetch_values = function()
{
	if (this.content_ready == false)
	{
		YAHOO.util.Connect.asyncRequest("POST", this.fetchurl[0], {
			success: this.populate_selects,
			failure: this.populate_failed,
			timeout: 5000,
			scope: this
		}, construct_phrase(this.fetchurl[1], this.elementkey));

		vBulletin.console("vB_ListBuilder '%s' :: fetch_values()", this.elementkey);
	}
}

/**
* Identifies and activates all controls
*/
vB_ListBuilder.prototype.init_controls = function()
{
	vBulletin.console("vB_ListBuilder '%s' :: init_controls()", this.elementkey);

	this.select_n = YAHOO.util.Dom.get(this.elementkey + "_select_n");
		YAHOO.util.Event.addListener(this.select_n, "dblclick", this.move_items, this, true);
	this.select_y = YAHOO.util.Dom.get(this.elementkey + "_select_y");
		YAHOO.util.Event.addListener(this.select_y, "dblclick", this.move_items, this, true);
	this.button_n = YAHOO.util.Dom.get(this.elementkey + "_button_n");
		YAHOO.util.Event.addListener(this.button_n, "click", this.move_items, this, true);
	this.button_y = YAHOO.util.Dom.get(this.elementkey + "_button_y");
		YAHOO.util.Event.addListener(this.button_y, "click", this.move_items, this, true);
	this.custom_i = YAHOO.util.Dom.get(this.elementkey + "_custom");
	if (this.custom_i)
	{
		this.custom_i.setAttribute("autocomplete", "off");
		YAHOO.util.Event.addListener(this.custom_i, "focus", this.clear_selection, this, true);
	}
	this.progress = YAHOO.util.Dom.get(this.elementkey + "_progress_image");

	if (this.select_n.form)
	{
		vBulletin.console("vB_ListBuilder '%s' :: Found form", this.elementkey);
		YAHOO.util.Event.addListener(this.select_n.form, "submit", this.select_all, this, true);
	}
}

/**
* Selects all options in the 'yes' select
*
* @param	event	Event object
*/
vB_ListBuilder.prototype.select_all = function(e)
{
	for (var i = 0; i < this.select_y.options.length; i++)
	{
		this.select_y.options[i].setAttribute("selected", true);
		this.select_y.options[i].selected = true;
	}
}

/**
* Activates all controls and populates the select elements via ajax request
*
* @param	object	Yahoo connection AJAX object
*/
vB_ListBuilder.prototype.populate_selects = function(ajax)
{
	vBulletin.console("vB_ListBuilder '%s' :: populate_selects()", this.elementkey);
	document.getElementById(this.elementkey + "_container").innerHTML = ajax.responseXML.getElementsByTagName("template")[0].firstChild.nodeValue;
	this.content_ready = true;

	this.init_controls();

	var itemtypes = {"n": "n", "y": "y"};
	for (var itemtype in itemtypes)
	{
		if (!YAHOO.lang.hasOwnProperty(itemtypes, itemtype))
		{
			continue;
		}

		var select_element = this["select_" + itemtype];
		while (select_element.hasChildNodes())
		{
			select_element.removeChild(select_element.firstChild);
		}
		var items = ajax.responseXML.getElementsByTagName(itemtype)[0].getElementsByTagName("item");
		for (var i = 0; i < items.length; i++)
		{
			var opt = document.createElement("option");
			opt.value = items[i].getAttribute("itemid");
			opt.innerHTML = items[i].firstChild.nodeValue;
			select_element.appendChild(opt);
		}
	}

	vBulletin.console("vB_ListBuilder '%s' :: Activate custom input? %s", this.elementkey, ajax.responseXML.getElementsByTagName("createnewitem")[0].firstChild.nodeValue);
	if (ajax.responseXML.getElementsByTagName("createnewitem")[0].firstChild.nodeValue == "yes")
	{
		this.select_n.style.height = this.select_n.offsetHeight - this.custom_i.offsetHeight + "px";
	}
	else if (this.custom_i.parentNode)
	{
		this.custom_i.parentNode.removeChild(this.custom_i);
	}

	this.fix_select_widths();

	if (this.select_item_id)
	{
		this.select_item();
	}
}

/**
* Equalizes the widths of the two selects
*/
vB_ListBuilder.prototype.fix_select_widths = function()
{
	this.select_n.style.width = this.select_y.style.width = this.custom_i.style.width = "auto";
	this.select_n.style.width = this.select_y.style.width = this.custom_i.style.width = Math.max(this.select_n.offsetWidth, this.select_y.offsetWidth, 120) + "px";
	vBmenu.menus[this.elementkey].set_menu_position(this.element.parentNode);
}

/**
* Clears the selection from the two inputs
*/
vB_ListBuilder.prototype.clear_selection = function()
{
	this.select_n.selectedIndex = this.select_y.selectedIndex = -1;
}

/**
* Moves items from one select to the other
*
* @param	event	Event
*/
vB_ListBuilder.prototype.move_items = function(e)
{
	YAHOO.util.Event.stopEvent(e);
	var eventsource = YAHOO.util.Event.getTarget(e);

	if (eventsource == this.button_n || eventsource == this.select_y || YAHOO.util.Dom.isAncestor(this.select_y, eventsource))
	{
		var to = this.select_n; var from = this.select_y;
		vBulletin.console("vB_ListBuilder '%s' :: move_items(Y->N)", this.elementkey);
	}
	else
	{
		var to = this.select_y; var from = this.select_n;
		vBulletin.console("vB_ListBuilder '%s' :: move_items(N->Y)", this.elementkey);

		if (this.custom_i && this.custom_i.parentNode && (this.custom_i.value = PHP.trim(this.custom_i.value)) != "")
		{
			vBulletin.console("vB_ListBuilder '%s' :: Found custom value of '%s'.", this.custom_i.value);

			var opt = document.createElement("option");
			opt.innerHTML = this.custom_i.value;
			opt.value = this.custom_i.value;
			opt.setAttribute("selected", true);
			opt.selected = true;
			this.select_n.appendChild(opt);

			this.custom_i.value = "";
		}
	}

	var moves = new Array();
	for (var i = 0; i < from.options.length; i++)
	{
		if (from.options[i].selected)
		{
			moves.push(i);
		}
	}

	this.clear_selection();

	while (typeof(i = moves.pop()) != "undefined")
	{
		var new_option = document.createElement("option");
		new_option.innerHTML = from.options[i].innerHTML;
		new_option.value = from.options[i].value;
		new_option.setAttribute("selected", true);
		new_option.selected = true;
		from.remove(i);

		for (var j = 0; j < to.options.length; j++)
		{
			if (new_option.innerHTML.toLowerCase() <= to.options[j].innerHTML.toLowerCase())
			{
				to.insertBefore(new_option, to.options[j]);
				new_option = null;
				break;
			}
		}

		if (new_option)
		{
			to.appendChild(new_option);
		}
	}

	this.save_values();
}

/**
* Saves the specified value
*/
vB_ListBuilder.prototype.save_values = function()
{
	if (this.useajax)
	{
		var submitstring = "";
		for (var i = 0; i < this.select_y.options.length; i++)
		{
			submitstring += construct_phrase("&value[%2$s]=%3$s", this.elementkey, this.select_y.options[i].getAttribute("value"), PHP.urlencode(this.select_y.options[i].innerHTML));
		}
		if (submitstring == "")
		{
			submitstring = construct_phrase("&%1$s=0", this.elementkey);
		}

		vBulletin.console("vB_ListBuilder '%s' :: save_values() (%s)", this.elementkey, submitstring);

		if (!this.saver)
		{
			this.set_temp_values();

			this.saver = YAHOO.util.Connect.asyncRequest("POST", this.saveurl[0], {
				success: this.save_complete,
				failure: this.buggerup,
				timeout: 5000,
				scope:   this
			}, construct_phrase(this.saveurl[1], PHP.urlencode(this.elementkey), submitstring));
		}
		else
		{
			vBulletin.console("vB_ListBuilder '%s' :: save_values() ALREADY IN PROGRESS");
		}
	}
}

/**
* Sets the temporary value of the calling field
*/
vB_ListBuilder.prototype.set_temp_values = function()
{
	var temp_values = this.fetch_select_options_array(this.select_y, "text").join(", ");
	vBulletin.console("vB_ListBuilder '%s' :: set temp values(%s)", this.elementkey, temp_values);
	this.progress.style.visibility = "visible";
	this.select_n.setAttribute("disabled", true);
	this.select_y.setAttribute("disabled", true);
	this.button_n.setAttribute("disabled", true);
	this.button_y.setAttribute("disabled", true);
	YAHOO.util.Dom.addClass(this.display_element, "shade");
	this.display_element.innerHTML = temp_values;
}

/**
* Fires after saving is complete to update the page
*
* @param	object	YUI AJAX object
*/
vB_ListBuilder.prototype.save_complete = function(ajax)
{
	vBulletin.console("vB_ListBuilder '%s' :: save_complete()", this.elementkey);

	YAHOO.util.Dom.removeClass(this.display_element, "shade");
	this.display_element.innerHTML = this.fetch_select_options_array(this.select_y, "text").join(", ");
	if (this.display_element.innerHTML == "")
	{
		this.display_element.innerHTML = ajax.responseXML.getElementsByTagName("noneword")[0].firstChild.nodeValue;
	}

	this.fix_select_widths();

	this.select_n.removeAttribute("disabled");
	this.select_y.removeAttribute("disabled");
	this.select_y.disabled = false;
	this.button_n.removeAttribute("disabled");
	this.button_y.removeAttribute("disabled");
	this.progress.style.visibility = "hidden";

	this.saver = null;
}

/**
* Fetches a list of options from the specified select element
*
* @param	element	HTML select element
* @param	string	Fetches either values:option values; text:option text values; both:both!
*
* @return	array
*/
vB_ListBuilder.prototype.fetch_select_options_array = function(select_object, type)
{
	var output = new Array();
	for (var i = 0; i < select_object.options.length; i++)
	{
		switch (type)
		{
			case "values":
				output[output.length] = select_object.options[i].getAttribute("value");
				break;
			case "both":
				output[select_object.options[i].getAttribute("value")] = select_object.options[i].innerHTML;
				break;
			case "text":
			default:
				output[output.length] = select_object.options[i].innerHTML;
				break;
		}
	}
	return output;
}
