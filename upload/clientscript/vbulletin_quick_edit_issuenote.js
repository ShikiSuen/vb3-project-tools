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