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
// vB_MagicSelect
// call using:
// vBulletin.register_control("vB_MagicSelect", html_element_id, database_fieldname, database_item_id)
// #############################################################################

vBulletin.events.systemInit.subscribe(function()
{
	new vB_MagicSelect_Factory();
});

// =============================================================================

/**
* Factory to build Magic Select objects
*/
function vB_MagicSelect_Factory()
{
	this.controls = new Array();
	this.open_fieldname = null;
	this.ltr_mode = (document.getElementsByTagName("html")[0].getAttribute("dir").toLowerCase() == "ltr");
	this.init();
};

/**
* Initialises the system and registers AJAX load/save URLs
*/
vB_MagicSelect_Factory.prototype.init = function()
{
	if (vBulletin.elements["vB_MagicSelect"])
	{
		for (var i = 0; i < vBulletin.elements["vB_MagicSelect"].length; i++)
		{
			var element = vBulletin.elements["vB_MagicSelect"][i];
			if (vBulletin.ajaxurls[element[1]])
			{
				var fetchurl = vBulletin.ajaxurls[element[1]][0];
				var saveurl = vBulletin.ajaxurls[element[1]][1];

				this.controls[element[1]] = new vB_MagicSelect(element[0], element[1], element[2], fetchurl, saveurl, this);
			}
		}
		vBulletin.elements["vB_MagicSelect"] = null;
	}
};

/**
* Closes all open <select> menus
*/
vB_MagicSelect_Factory.prototype.close_all = function()
{
	if (this.open_fieldname)
	{
		// close identified active menu
		this.controls[this.open_fieldname].deactivate_control();
		this.controls[this.open_fieldname].close_menu();
	}
	else
	{
		// force all menus to close
		for (var i in this.controls)
		{
			if (!YAHOO.lang.hasOwnProperty(i, this.controls))
			{
				this.controls[i].deactivate_control();
				this.controls[i].close_menu();
			}
		}
	}
};

/**
* Records the name of the currently-opened magic select
*/
vB_MagicSelect_Factory.prototype.set_open_fieldname = function(value)
{
	vBulletin.console("vB_MagicSelect (Factory) :: set_open_fieldname(%s)", value);
	this.open_fieldname = value;
};

// #############################################################################

/**
* vBulletin 'Magic Select' class - turns static values into pseudo-<select> elements with AJAX save function
*
* @param	element	HTML element to be converted into a magic select
* @param	string	Fieldname of the value to be saved (postid, username etc.)
* @param	mixed	Database item ID of the item to be modified
* @param	string	URL to be loaded to return XML list of values for the select
* @param	string	URL to be loaded to save the selected value of the select
* @param	vB_MagicSelect_Factory	Factory class to which to register this object
*/
function vB_MagicSelect(htmlelement, fieldname, itemid, fetchurl, saveurl, factory)
{
	this.htmlelement = YAHOO.util.Dom.get(htmlelement);
	this.fieldname = fieldname;
	this.itemid = itemid;
	this.fetchurl = fetchurl;
	this.saveurl = saveurl;
	this.factory = factory;
	this.selectedIndex = -1;
	this.menuopen = false;

	YAHOO.util.Dom.removeClass(this.htmlelement, "vB_MagicSelect_preload");
	YAHOO.util.Dom.addClass(this.htmlelement, "vB_MagicSelect");
	YAHOO.util.Dom.addClass(this.htmlelement, "vB_MagicSelectCursor");

	var shadyspans = YAHOO.util.Dom.getElementsByClassName("shade", "span", this.htmlelement);
	if (shadyspans.length)
	{
		this.labeltext = document.createTextNode(shadyspans[0].hasChildNodes() ? PHP.trim(shadyspans[0].firstChild.nodeValue) : "");
		this.htmlelement.removeChild(shadyspans[0]);
		this.valuetext = document.createTextNode(this.htmlelement.hasChildNodes() ? PHP.trim(this.htmlelement.firstChild.nodeValue) : "");
		while (this.htmlelement.hasChildNodes())
		{
			this.htmlelement.removeChild(this.htmlelement.firstChild);
		}

		var table = document.createElement("table");
			table.setAttribute("width", "100%");
			table.setAttribute("cellPadding", 0);
			table.setAttribute("cellSpacing", 0);
			table.setAttribute("border", 0);
			var tbody = table.appendChild(document.createElement("tbody"));
				var tr = tbody.appendChild(document.createElement("tr"));
					var td1 = tr.appendChild(document.createElement("td"));
						var label = td1.appendChild(document.createElement("span"));
							label.className = "shade";
							label.appendChild(this.labeltext);
						td1.appendChild(document.createTextNode(" "));
							this.value_container = td1.appendChild(document.createElement("span"));
							this.value_container.appendChild(this.valuetext);
						td1.className = "smallfont";
						td1.style.whiteSpace = "nowrap";
					var td2 = tr.appendChild(document.createElement("td"));
						td2.setAttribute("align", (this.factory.ltr_mode ? "right" : "left"));
						td2.style.whiteSpace = "nowrap";
							this.button = td2.appendChild(this.create_button());
		this.htmlelement.appendChild(table);
	}
	else
	{
		var span_html = this.htmlelement.innerHTML;
		this.htmlelement.innerHTML = "";
		this.value_container = this.htmlelement.appendChild(document.createElement("span"));
		this.value_container.innerHTML = span_html;
		this.button = this.htmlelement.appendChild(this.create_button());
	}

	YAHOO.util.Event.addListener(this.htmlelement, "mouseover", this.control_mouseover, this, true);
	YAHOO.util.Event.addListener(this.htmlelement, "mouseout", this.control_mouseout, this, true);
	YAHOO.util.Event.addListener(this.htmlelement, "click", this.control_click, this, true);
	YAHOO.util.Event.addListener(window, "resize", this.handle_resize, this, true);
};

/**
* Creates the HTML element to act as a button to open the select
*
* @return	element	HTML button element
*/
vB_MagicSelect.prototype.create_button = function()
{
	var button = document.createElement("img");
		button.src = IMGDIR_MISC + "/black_downward_arrow.png";
		button.className = "vB_MagicSelect_button";
	return button;
}

/**
* Creates an option within the select
*
* @param	string	Value of the option
* @param	string	Text of the option
* @param	boolean	Select this option
*
* @return	element	HTML option element
*/
vB_MagicSelect.prototype.create_option = function(value, html, selected)
{
	var option = document.createElement("option");

	option.value = value;
	option.innerHTML = html;
	if (selected == "yes" || selected == true)
	{
		option.selected = true;
		option.setAttribute("selected", true);
	}
	else
	{
		option.selected = false;
		option.removeAttribute("selected");
	}
	return option;
};

/**
* Reads a list of values from an AJAX request and creates a <select> containing those values
*
* @param	object	AJAX object from YAHOO.util.Connect
* @param	boolean	If false, do not rebuild the select
*/
vB_MagicSelect.prototype.populate_menu = function(ajax, nocreate)
{
	vBulletin.console("vB_MagicSelect (%s) :: Populate Menu Starting (%s)", this.fieldname, (nocreate ? "Save" : "Load"));

	if (!nocreate)
	{
		if (this.menu)
		{
			return;
		}

		this.menu = document.body.appendChild(document.createElement("select"));
		this.menu.style.position = "absolute";
		this.menu.style.top = "0px";
		this.menu.style.left = "0px";
		this.menu.style.display = "none";
		this.menu.style.zIndex = 10;

		YAHOO.util.Event.addListener(this.menu, "click", this.menu_click, this, true);
		YAHOO.util.Event.addListener(this.menu, "blur", this.menu_blur, this, true);
		YAHOO.util.Event.addListener(this.menu, "keypress", this.menu_keypress, this, true);
	}
	else
	{
		var error = ajax.responseXML.getElementsByTagName("error");
		if (error[0])
		{
			vBulletin.console("vB_MagicSelect (%s) :: Error: %s \nRevert value to %s", this.fieldname, error[0].firstChild.nodeValue, this.menu.options[this.selectedIndex].innerHTML);
			alert(error[0].firstChild.nodeValue);
		}
	}

	// remove contents
	while (this.menu.hasChildNodes())
	{
		this.menu.removeChild(this.menu.firstChild);
	}

	if (is_ie && !is_ie7)
	{
		// this tricks IE into doing a redraw, which prevents a display glitch (see #22163)
		this.menu.style.display = "";
		this.menu.style.display = "none";
	}

	// populate contents
	var groups = ajax.responseXML.getElementsByTagName("items")[0].childNodes;
	for (var i = 0; i < groups.length; i++)
	{
		if (groups[i].nodeType == 1) // <node>
		{
			if (groups[i].tagName == "itemgroup")
			{
				var optgroup = document.createElement("optgroup");
				optgroup.label = groups[i].getAttribute("label");
				var groupoptions = groups[i].getElementsByTagName("item");
				for (var j = 0; j < groupoptions.length; j++)
				{
					optgroup.appendChild(this.create_option(groupoptions[j].getAttribute("itemid"), groupoptions[j].firstChild.nodeValue, groupoptions[j].getAttribute("selected")));
				}
				this.menu.appendChild(optgroup);
			}
			else if (groups[i].tagName == "item")
			{
				this.menu.appendChild(this.create_option(groups[i].getAttribute("itemid"), groups[i].firstChild.nodeValue, groups[i].getAttribute("selected")));
			}
		}
	}

	this.set_value(this.menu.selectedIndex);

	this.menu.setAttribute("size", Math.min((this.menu.options.length + this.menu.getElementsByTagName("optgroup").length), 11));

	if (!nocreate)
	{
		this.open_menu();
	}

	vBulletin.console("vB_MagicSelect (%s) :: Populate Menu Completed (%s)", this.fieldname, (nocreate ? "Save" : "Load"));
};

/**
* Handles a window resize. Resizing can break some of the caching we have.
*/
vB_MagicSelect.prototype.handle_resize = function()
{
	if (this.menu)
	{
		this.close_menu();
		this.deactivate_control();

		this.menu.parentElement.removeChild(this.menu);
		this.menu = null;
	}
}

/**
* Fetch offset of an object
*
* @param	object	The object to be measured
*
* @return	array	The measured offsets left/top
*/
vB_MagicSelect.prototype.fetch_offset = function(obj)
{
	var left_offset = obj.offsetLeft;
	var top_offset = obj.offsetTop;

	while ((obj = obj.offsetParent) != null)
	{
		left_offset += obj.offsetLeft;
		top_offset += obj.offsetTop;
	}

	return { 0 : left_offset, 1 : top_offset };
};

/**
* Opens the selected menu
*/
vB_MagicSelect.prototype.open_menu = function()
{
	vBulletin.console("vB_MagicSelect (%s) :: open_menu()", this.fieldname);

	if (this.menu)
	{
		this.activate_control();

		this.menu.style.display = "";

		this.menu.style.width = Math.max(this.menu.offsetWidth, this.htmlelement.offsetWidth) + "px";

		if (is_opera && YAHOO.env.getVersion('dom').build <= 204)
		{
			// workaround bug in YUI 2.2.2: #22422, YUI #1576570
			var obj = this.htmlelement;
			var left_offset = obj.offsetLeft;
			var top_offset = obj.offsetTop;

			while ((obj = obj.offsetParent) != null)
			{
				left_offset += obj.offsetLeft;
				top_offset += obj.offsetTop;
			}

			var spanpos = { 0 : left_offset, 1 : top_offset };
		}
		else
		{
			var spanpos = YAHOO.util.Dom.getXY(this.htmlelement);
		}

		spanpos[1] += this.htmlelement.offsetHeight;
		if (this.factory.ltr_mode)
		{
			spanpos[0] += this.htmlelement.offsetWidth - this.menu.offsetWidth;
		}
		YAHOO.util.Dom.setXY(this.menu, spanpos);

		this.menu.focus();
		this.factory.set_open_fieldname(this.fieldname);
	}
	else
	{
		YAHOO.util.Connect.asyncRequest("POST", this.fetchurl[0], {
			success: this.populate_menu,
			failure: this.request_timeout,
			timeout: 5000,
			scope: this
		}, construct_phrase(this.fetchurl[1], PHP.urlencode(this.itemid), PHP.urlencode(this.fieldname)));
	}

	return false;
};

/**
* Closes the active menu
*/
vB_MagicSelect.prototype.close_menu = function()
{
	if (this.menu)
	{
		this.menu.style.display = "none";
	}

	this.factory.set_open_fieldname(null);
	return false;
};

/**
* Activates the selected control
*/
vB_MagicSelect.prototype.activate_control = function()
{
	YAHOO.util.Dom.replaceClass(this.htmlelement, "vB_MagicSelect", "vB_MagicSelect_hover");
};

/**
* Deactivates the active control
*/
vB_MagicSelect.prototype.deactivate_control = function()
{
	YAHOO.util.Dom.replaceClass(this.htmlelement, "vB_MagicSelect_hover", "vB_MagicSelect");
};

/**
* Saves the specified value
*
* @param	integer	Selected index of the chosen value
*/
vB_MagicSelect.prototype.save_value = function(selectedIndex)
{
	var option = this.menu.options[selectedIndex];

	vBulletin.console("vB_MagicSelect (%s) :: save_value(%s)", this.fieldname, option.value);

	this.deactivate_control();
	this.close_menu();

	if (this.selectedIndex != selectedIndex && !this.saver)
	{
		this.set_temp_value(option.innerHTML);

		this.saver = YAHOO.util.Connect.asyncRequest("POST", this.saveurl[0], {
			success: this.save_complete,
			failure: this.request_timeout,
			timeout: 5000,
			scope:   this
		}, construct_phrase(this.saveurl[1], PHP.urlencode(this.itemid), PHP.urlencode(this.fieldname), PHP.urlencode(option.value), PHP.urlencode(option.innerHTML)));
	}
};

/**
* Runs when save is complete and updates the select
*/
vB_MagicSelect.prototype.save_complete = function(ajax)
{
	vBulletin.console("vB_MagicSelect (%s) :: save_complete()", this.fieldname);

	this.populate_menu(ajax, true);

	this.saver = null;
};

/**
* Sets the permanent value of the control to the specified value
*/
vB_MagicSelect.prototype.set_value = function(selectedIndex)
{
	var option = this.menu.options[this.menu.selectedIndex]

	vBulletin.console("vB_MagicSelect (%s) :: set_value(%s) = %s", this.fieldname, selectedIndex, option.innerHTML);

	this.selectedIndex = selectedIndex;
	this.value_container.innerHTML = option.innerHTML;
	this.button.src = IMGDIR_MISC + "/black_downward_arrow.png";
	YAHOO.util.Dom.removeClass(this.value_container, "shade");
};

/**
* Sets the temporary value of the control to the specified value
*/
vB_MagicSelect.prototype.set_temp_value = function(value)
{
	vBulletin.console("vB_MagicSelect (%s) :: set_temp_value(%s)", this.fieldname, value);

	this.button.src = IMGDIR_MISC + "/progress.gif";
	this.button.setAttribute('style', 'height: 21px')
	this.value_container.innerHTML = value;
	YAHOO.util.Dom.addClass(this.value_container, "shade");
};

/**
* Mouseover events for control
*/
vB_MagicSelect.prototype.control_mouseover = function(e)
{
	e = do_an_e(e);

	if (!this.factory.open_fieldname && !this.saver)
	{
		YAHOO.util.Dom.replaceClass(this.htmlelement, "vB_MagicSelect", "vB_MagicSelect_hover");
	}

	return false;
};

/**
* Mouseout events for control
*/
vB_MagicSelect.prototype.control_mouseout = function(e)
{
	e = do_an_e(e);

	if (!this.factory.open_fieldname && !this.saver)
	{
		YAHOO.util.Dom.replaceClass(this.htmlelement, "vB_MagicSelect_hover", "vB_MagicSelect");
	}

	return false;
};

/**
* Click events for button
*/
vB_MagicSelect.prototype.control_click = function(e)
{
	e = do_an_e(e);

	if (!this.saver)
	{
		if (this.factory.open_fieldname)
		{
			if (this.factory.open_fieldname == this.fieldname)
			{
				this.factory.close_all();
			}
			else
			{
				this.factory.close_all();
				this.open_menu();
			}
		}
		else
		{
			this.open_menu();
		}
	}
};

/**
* Click events for menu
*/
vB_MagicSelect.prototype.menu_click = function(e)
{
	e = do_an_e(e);

	this.save_value(this.menu.selectedIndex);
};

/**
* Blur events for menu
*/
vB_MagicSelect.prototype.menu_blur = function(e)
{
	e = do_an_e(e);

	this.save_value(this.menu.selectedIndex);
};

/**
* Keypress events for menu
*/
vB_MagicSelect.prototype.menu_keypress = function(e)
{
	switch (e.keyCode)
	{
		case 13: // return or enter
			this.save_value(this.menu.selectedIndex);
			this.close_menu();
			return true;
		case 27: // escape
			this.set_value(this.selectedIndex);
			this.close_menu();
			return true;
		default:
			return true;
	}
};

/**
* Fires when the AJAX stuff populator fails
*/
vB_MagicSelect.prototype.request_timeout = function()
{
	if (typeof(vbphrase['request_timed_out_refresh']) != 'undefined')
	{
		alert(vbphrase['request_timed_out_refresh']);
	}
};

vBulletin.console("Loaded vBulletin Magic Selects");
