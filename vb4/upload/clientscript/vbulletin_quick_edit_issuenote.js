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

function vB_QuickEditor_Issuenote_Vars(args)
{
	this.init();
}

vB_QuickEditor_Issuenote_Vars.prototype.init = function()
{
	this.target = "projectpost.php";
	this.postaction = "postreply";

	this.objecttype = "issuenoteid";
	this.getaction = "message";

	this.ajaxtarget = "projectpost.php";
	this.ajaxaction = "quickedit";
	this.deleteaction = "deleteissuenote";

	this.messagetype = "issuenote_message_";
	this.containertype = "issuenote_";
	this.responsecontainer = "commentbits";
}