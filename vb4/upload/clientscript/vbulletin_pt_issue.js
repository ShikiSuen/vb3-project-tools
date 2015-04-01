/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

function issueattach_submit()
{
	if (!vB_Editor || !vB_Editor['vB_Editor_QR'])
	{
		// nothing to check against
		return true;
	}

	if (stripcode(vB_Editor['vB_Editor_QR'].get_editor_contents(), vB_Editor['vB_Editor_QR'].wysiwyg_mode) != '')
	{
		if (confirm(vbphrase['reply_text_sure_submit_attach']))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	return true;
}

/**
* This function runs all the necessary Javascript code on a IssueNoteBit
* after it has been loaded via AJAX. Don't use this method before a
* complete page load or you'll have problems.
*
* @param	object	Object containing postbits
*/
function IssueNoteBit_Init(obj, issuenoteid)
{
	console.log("IssueNoteBit Init: %s", issuenoteid);

	if (typeof vB_QuickReply != "undefined")
	{
		// init quick reply button
		qr_pt_init_buttons(obj);
	}
}