function vB_QuickEditor_Issue_Vars(args)
{
	this.init();
}

vB_QuickEditor_Issue_Vars.prototype.init = function()
{
	this.target = "projectpost.php";
	this.postaction = "postissue";

	this.objecttype = "issueid";
	this.getaction = "message";

	this.ajaxtarget = "projectpost.php";
	this.ajaxaction = "quickeditissue";
	this.deleteaction = "deleteissue";

	this.messagetype = "issue_message_";
	this.containertype = "issue_";
	this.responsecontainer = "commentbits";
}