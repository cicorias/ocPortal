<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2014

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

class Hook_Profiles_Tabs_Edit_notifications
{
	/**
	 * Find whether this hook is active.
	 *
	 * @param  MEMBER			The ID of the member who is being viewed
	 * @param  MEMBER			The ID of the member who is doing the viewing
	 * @return boolean		Whether this hook is active
	 */
	function is_active($member_id_of,$member_id_viewing)
	{
		return (($member_id_of==$member_id_viewing) || (has_privilege($member_id_viewing,'assume_any_member')) || (has_privilege($member_id_viewing,'member_maintenance')));
	}

	/**
	 * Standard modular render function for profile tabs edit hooks.
	 *
	 * @param  MEMBER			The ID of the member who is being viewed
	 * @param  MEMBER			The ID of the member who is doing the viewing
	 * @param  boolean		Whether to leave the tab contents NULL, if tis hook supports it, so that AJAX can load it later
	 * @return ?array			A tuple: The tab title, the tab body text (may be blank), the tab fields, extra Javascript (may be blank) the suggested tab order, hidden fields (optional) (NULL: if $leave_to_ajax_if_possible was set), the icon
	 */
	function render_tab($member_id_of,$member_id_viewing,$leave_to_ajax_if_possible=false)
	{
		require_lang('notifications');
		$title=do_lang_tempcode('NOTIFICATIONS');

		$order=100;

		if (strtoupper(ocp_srv('REQUEST_METHOD'))=='POST')
		{
			$auto_monitor_contrib_content=post_param_integer('auto_monitor_contrib_content',0);
			$GLOBALS['FORUM_DB']->query_update('f_members',array('m_auto_monitor_contrib_content'=>$auto_monitor_contrib_content),array('id'=>$member_id_of),'',1);

			$smart_topic_notification_content=post_param_integer('smart_topic_notification_content',0);
			$GLOBALS['FORUM_DRIVER']->set_custom_field($member_id_of,'smart_topic_notification',$smart_topic_notification_content);

			// Decache from run-time cache
			unset($GLOBALS['FORUM_DRIVER']->MEMBER_ROWS_CACHED[$member_id_of]);
			unset($GLOBALS['MEMBER_CACHE_FIELD_MAPPINGS'][$member_id_of]);
		}

		if (($leave_to_ajax_if_possible) && (strtoupper(ocp_srv('REQUEST_METHOD'))!='POST')) return NULL;

		require_code('notifications2');

		$text=notifications_ui($member_id_of);
		if ($text->is_empty()) return NULL;

		$javascript='';

		return array($title,new ocp_tempcode(),$text,$javascript,$order,NULL,'tool_buttons/notifications2');
	}
}


