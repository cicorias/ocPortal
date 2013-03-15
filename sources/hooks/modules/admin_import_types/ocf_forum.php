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
 * @package		ocf_forum
 */

class Hook_admin_import_types_ocf_forum
{

	/**
	 * Standard modular run function.
	 *
	 * @return array		Results
	 */
	function run()
	{
		return array(
			'ocf_post_history'=>'POST_HISTORY',
			'ocf_post_templates'=>'POST_TEMPLATES',
			'ocf_announcements'=>'ANNOUNCEMENTS',
			'ocf_categories'=>'MODULE_TRANS_NAME_admin_ocf_categories',
			'ocf_forums'=>'SECTION_FORUMS',
			'ocf_topics'=>'FORUM_TOPICS',
			'ocf_polls_and_votes'=>'TOPIC_POLLS',
			'ocf_posts'=>'FORUM_POSTS',
			'ocf_post_files'=>'POST_FILES',
			'ocf_multi_moderations'=>'MULTI_MODERATIONS',
			'notifications'=>'NOTIFICATIONS',
			'ocf_personal_topics'=>'PERSONAL_TOPICS',
		);
	}

}


