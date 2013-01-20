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
 * @package		awards
 */

class Hook_checklist_awards
{

	/**
	 * Standard modular run function.
	 *
	 * @return array		An array of tuples: The task row to show, the number of seconds until it is due (or NULL if not on a timer), the number of things to sort out (or NULL if not on a queue), The name of the config option that controls the schedule (or NULL if no option).
	 */
	function run()
	{
		$award_types=$GLOBALS['SITE_DB']->query_select('award_types',array('*'));

		$out=array();

		foreach ($award_types as $award)
		{
			// Find out how many submissions we've had since the last award was given
			if ((!file_exists(get_file_base().'/sources/hooks/systems/content_meta_aware/'.filter_naughty_harsh($award['a_content_type']).'.php')) && (!file_exists(get_file_base().'/sources_custom/hooks/systems/content_meta_aware/'.filter_naughty_harsh($award['a_content_type']).'.php')))
				continue;

			require_code('hooks/systems/content_meta_aware/'.$award['a_content_type']);
			$hook_object=object_factory('Hook_content_meta_aware_'.$award['a_content_type'],true);
			if (is_null($hook_object)) continue;
			$details=$hook_object->info();
			if (!is_null($details))
			{
				$date=$GLOBALS['SITE_DB']->query_select_value_if_there('award_archive','date_and_time',array('a_type_id'=>$award['id']),'ORDER BY date_and_time DESC');

				$seconds_ago=mixed();
				$limit_hours=$award['a_update_time_hours'];
				if (!is_null($date))
				{
					$seconds_ago=time()-$date;
					$status=($seconds_ago>$limit_hours*60*60)?0:1;
				} else
				{
					$status=0;
				}

				$config_url=build_url(array('page'=>'admin_awards','type'=>'_ed','id'=>$award['id']),get_module_zone('admin_awards'));

				$_status=($status==0)?do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_0'):do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM_STATUS_1');

				$url=$details['add_url'];
				if (!is_null($url))
				{
					list($url_zone,$url_map,$url_hash)=page_link_decode($url);
					$url=static_evaluate_tempcode(build_url($url_map,$url_zone,NULL,false,false,false,$url_hash));
				} else $url='';
				$url=str_replace('=!','_ignore=1',$url);

				$task=do_lang_tempcode('_GIVE_AWARD',escape_html(get_translated_text($award['a_title'])));

				if ((!is_null($date)) && (!is_null($details['date_field'])))
				{
					$where=filter_naughty_harsh($details['date_field']).'>'.strval(intval($date));
					$num_queue=$details['connection']->query_value_if_there('SELECT COUNT(*) FROM '.$details['connection']->get_table_prefix().str_replace('1=1',$where,$details['table']).' r WHERE '.$where);
					$_num_queue=integer_format($num_queue);
					$num_new_since=do_lang_tempcode('NUM_NEW_SINCE',$_num_queue);
				} else $num_new_since=new ocp_tempcode();

				list($info,$seconds_due_in)=staff_checklist_time_ago_and_due($seconds_ago,$limit_hours);
				$info->attach($num_new_since);
				$tpl=do_template('BLOCK_MAIN_STAFF_CHECKLIST_ITEM',array('_GUID'=>'4049affae5a6f38712ee3e0237a2e18e','CONFIG_URL'=>$config_url,'URL'=>$url,'STATUS'=>$_status,'TASK'=>$task,'INFO'=>$info));
				$out[]=array($tpl,$seconds_due_in,NULL,NULL);
			}
		}

		return $out;
	}

}


