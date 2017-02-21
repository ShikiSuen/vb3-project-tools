/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| #  This is file is subject to the vBulletin Open Source License.   # ||
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
