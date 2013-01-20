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
 * @package		stats_block
 */

class Hook_config_default_activity_show_stats_count_users_online_forum
{

	/**
	 * Gets the details relating to the config option.
	 *
	 * @return ?array		The details (NULL: disabled)
	 */
	function get_details()
	{
		return array(
			'human_name'=>'COUNT_ONFORUMS',
			'the_type'=>'tick',
			'the_page'=>'BLOCKS',
			'section'=>'STATISTICS',
			'explanation'=>'CONFIG_OPTION_activity_show_stats_count_users_online_forum',
			'shared_hosting_restricted'=>'0',
			'c_data'=>'',

			'addon'=>'stats_block',
		);
	}

	/**
	 * Gets the default value for the config option.
	 *
	 * @return ?string		The default value (NULL: option is disabled)
	 */
	function get_default()
	{
		return ((get_forum_type()!='ocf') && (addon_installed('stats_block')))?'0':NULL;
	}

}


