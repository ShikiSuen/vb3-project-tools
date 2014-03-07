/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.1                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

vBulletin.events.systemInit.subscribe(function() { new vB_PT_Search_Control(); });

function vB_PT_Search_Control()
{
	this.projects_selected = false;

	this.project_checkboxes = this.init_checkboxes("ptsearch_projectid");
	this.applies_versiongroup_checkboxes = this.init_checkboxes("ptsearch_appliesversionid");
	this.addressed_versiongroup_checkboxes = this.init_checkboxes("ptsearch_addressedversionid");

	this.update_controls();
}

vB_PT_Search_Control.prototype.init_checkboxes = function(root_element)
{
	var checkboxes = YAHOO.util.Dom.getElementsBy((root_element == "ptsearch_projectid" ?
		function(element)
		{
			return element.type == "checkbox";
		} :
		function(element)
		{
			return (element.type == "checkbox" && element.id.match(/versiongroup_(\d+)_(applies|addressed)versions_checkbox/));
		}),
		"input", root_element
	);

	for (var i = 0; i < checkboxes.length; i++)
	{
		YAHOO.util.Event.on(checkboxes[i], "click", this.update_controls, this, true);
	}

	return checkboxes;
};

vB_PT_Search_Control.prototype.update_controls = function()
{
	// handle selected project(s)
	this.projects_selected = false;
	for (var i = 0; i < this.project_checkboxes.length; i++)
	{
		if (this.project_checkboxes[i].checked)
		{
			this.projects_selected = true;
		}
	}
	for (var i = 0; i < this.project_checkboxes.length; i++)
	{
		var show = (!this.projects_selected || this.project_checkboxes[i].checked);

		var categories = YAHOO.util.Dom.get("project_" + this.project_checkboxes[i].value + "_categories");
		if (categories != null)
		{
			categories.style.display = (show ? "" : "none");
		}

		var appliesvers = YAHOO.util.Dom.get("project_" + this.project_checkboxes[i].value + "_appliesversions");
		if (appliesvers != null)
		{
			appliesvers.style.display = (show ? "" : "none");
		}

		var addressedvers = YAHOO.util.Dom.get("project_" + this.project_checkboxes[i].value + "_addressedversions");
		if (addressedvers != null)
		{
			addressedvers.style.display = (show ? "" : "none");
		}
	}

	// handle version groups
	var version_group_types = new Array("applies", "addressed");
	for (var x = 0; x < version_group_types.length; x++)
	{
		var groups = this[version_group_types[x] + "_versiongroup_checkboxes"];

		for (var i = 0; i < this.applies_versiongroup_checkboxes.length; i++)
		{
			var container = YAHOO.util.Dom.get(groups[i].id.replace(/_checkbox/, "_options"));
			var checkboxes = YAHOO.util.Dom.getElementsBy(function(element) { return element.type == "checkbox"; }, "input", container);

			for (var j = 0; j < checkboxes.length; j++)
			{
				// get original state
				if (!checkboxes[j].disabled)
				{
					checkboxes[j].ochecked = checkboxes[j].checked;
				}

				// disable or enable checkboxes depending on group selection
				if (groups[i].checked)
				{
					YAHOO.util.Dom.addClass(checkboxes[j].parentNode, "shade");
				}
				else
				{
					YAHOO.util.Dom.removeClass(checkboxes[j].parentNode, "shade");
				}
				checkboxes[j].disabled = groups[i].checked;
				checkboxes[j].checked = (groups[i].checked ? true : checkboxes[j].ochecked);
			}

			container.disabled = groups[i].checked;
		}
	}
};