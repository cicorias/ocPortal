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
 * @package		core_adminzone_frontpage
 */

class Hook_checklist_cron
{

	/**
	 * Standard modular run function.
	 *
	 * @return array		An array of tuples: The task row to show, the number of seconds until it is due (or NULL if not on a timer), the number of things to sort out (or NULL if not on a queue), The name of the config option that controls the schedule (or NULL if no option).
	 */
	function run()
	{
		$last_cron=get_value('last_cron');

		if ((is_null($last_cron)) || (intval($last_cron)<time()-60*60*24))
		{
			$status=0;
			$info=NULL;
			$url=brand_base_url().'/docs'.strval(ocp_version()).'/pg/tut_configuration';
		} else
		{
			$status=1;
			$date=get_timezoned_date(intval($last_cron),true,true,false,true);
			$mails_sent=$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) AS cnt FROM '.get_table_prefix().'logged_mail_messages WHERE m_queued=0 AND m_date_and_time>'.strval(time()-60*60*24));
			$mails_queued=$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) AS cnt FROM '.get_table_prefix().'logged_mail_messages WHERE m_queued=1');
			$info=do_lang_tempcode('LAST_RAN_AT',escape_html($date),escape_html(integer_format($mails_sent)),escape_html(integer_format($mails_queued)));
			$url='';
		}

		$_status=($status==0)?do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_0'):do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_1');
		$tpl=do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM',array('INFO'=>$info,'URL'=>'','STATUS'=>$_status,'TASK'=>urlise_lang(do_lang('NAG_SETUP_CRON'),$url)));
		return array(array($tpl,($status==0)?-1:0,1,NULL));
	}

}

