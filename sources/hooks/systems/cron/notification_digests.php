<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2012

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		core_notifications
 */

class Hook_cron_notifications_digests
{

	/**
	 * Standard modular run function for CRON hooks. Searches for tasks to perform.
	 */
	function run()
	{
		require_code('notifications');
		foreach (array(
			A_DAILY_EMAIL_DIGEST=>60*60*24,
			A_WEEKLY_EMAIL_DIGEST=>60*60*24*7,
			A_MONTHLY_EMAIL_DIGEST=>60*60*24*31
		) as $frequency=>$timespan)
		{
			$start=0;
			do
			{
				// Find where not tint-in-tin
				$members=$GLOBALS['SITE_DB']->query('SELECT DISTINCT d_to_member_id FROM '.get_table_prefix().'digestives_consumed c JOIN '.get_table_prefix().'digestives_tin t ON c.c_member_id=t.d_to_member_id AND c.c_frequency='.strval($frequency).' WHERE c_time<'.strval(time()-$timespan).' AND c_frequency='.strval($frequency),100,$start);

				foreach ($members as $member)
				{
					require_lang('notifications');

					$to_member_id=$member['d_to_member_id'];
					$to_name=$GLOBALS['FORUM_DRIVER']->get_username($to_member_id);
					$to_email=$GLOBALS['FORUM_DRIVER']->get_member_email_address($to_member_id);

					$messages=$GLOBALS['SITE_DB']->query_select('digestives_tin',array('d_subject','d_message','d_date_and_time'),array(
						'd_to_member_id'=>$to_member_id,
						'd_frequency'=>$frequency,
					),'ORDER BY d_date_and_time');
					$GLOBALS['SITE_DB']->query_delete('digestives_tin',array(
						'd_to_member_id'=>$to_member_id,
						'd_frequency'=>$frequency,
					));

					$_message='';
					foreach ($messages as $message)
					{
						if ($_message!='') $_message.=chr(10);
						$_message.=do_lang('DIGEST_EMAIL_INDIVIDUAL_MESSAGE_WRAP',comcode_escape($message['d_subject']),$message['d_message'],array(comcode_escape(get_site_name()),get_timezoned_date($message['d_date_and_time'])));
					}
					$wrapped_subject=do_lang('DIGEST_EMAIL_SUBJECT_'.strval($frequency),comcode_escape(get_site_name()));
					$wrapped_message=do_lang('DIGEST_EMAIL_MESSAGE_WRAP',$_message,comcode_escape(get_site_name()));

					require_code('mail');
					mail_wrap($wrapped_subject,$wrapped_message,array($to_email),$to_name,get_option('staff_address'),get_site_name(),3,NULL,true,A_FROM_SYSTEM_UNPRIVILEGED,false);
				}

				$start+=100;
			}
			while (count($members)==100);
		}
	}

}
