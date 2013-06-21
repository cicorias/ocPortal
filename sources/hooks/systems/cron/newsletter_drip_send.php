<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2013

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		newsletter
 */

class Hook_cron_newsletter_drip_send
{

	/**
	 * Standard modular run function for CRON hooks. Searches for tasks to perform.
	 */
	function run()
	{
		if (!addon_installed('newsletter')) return;

		$minutes_between_sends=intval(get_option('minutes_between_sends'));
		$mails_per_send=intval(get_option('mails_per_send'));

		$time=time();
		$last_time=intval(get_value('last_newsletter_drip_send'));
		if (($last_time>time()-$minutes_between_sends*60) && (!/*we do allow an admin to force it by CRON URL*/$GLOBALS['FORUM_DRIVER']->is_super_admin(get_member()))) return;
		set_value('last_newsletter_drip_send',strval($time));

		$to_send=$GLOBALS['SITE_DB']->query_select('newsletter_drip_send',array('*'),NULL,'ORDER BY d_inject_time DESC',$mails_per_send);
		if (count($to_send)!=0)
		{
			// Quick cleanup to minimise race-condition possibility
			$id_list='';
			foreach ($to_send as $mail)
			{
				if ($id_list!='') $id_list.=' OR ';
				$id_list.='id='.strval($mail['id']);
			}
			$GLOBALS['SITE_DB']->query('DELETE FROM '.get_table_prefix().'newsletter_drip_send WHERE '.$id_list,NULL,NULL,false,true);

			// Send
			require_code('mail');
			foreach ($to_send as $mail)
			{
				mail_wrap($mail['d_subject'],$mail['d_message'],array($mail['d_to_email']),array($mail['d_to_name']),$mail['d_from_email'],$mail['d_from_name'],$mail['d_priority'],NULL,true,NULL,true,$mail['d_html_only']==1,false,$mail['d_template']);
			}
		}
	}

}


