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
 * @package		gallery_syndication
 */

class Hook_admin_config_video_sync_transcoding
{

	function run($myrow)
	{
		$list='';
		$list.=static_evaluate_tempcode(form_input_list_entry(do_lang('OTHER',NULL,NULL,NULL,fallback_lang())));

		$hooks=find_all_hooks('modules','video_syndication');
		foreach (array_keys($hooks) as $hook)
		{
			require_code('hooks/modules/video_syndication/'.filter_naughty($hook));
			$ob=object_factory('video_syndication_'.filter_naughty($hook));
			$label=$ob->get_service_title();

			$list.=static_evaluate_tempcode(form_input_list_entry($hook,$hook==get_option($myrow['the_name']),$label));
		}

		return form_input_list(do_lang_tempcode('VIDEO_SYNC_TRANSCODING'),do_lang_tempcode('CONFIG_OPTION_video_sync_transcoding'),'video_sync_transcoding',make_string_tempcode($list));
	}

}


