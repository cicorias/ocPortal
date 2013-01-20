<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2013

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		catalogues
 */

class Hook_Notification_catalogue_view_reports extends Hook_Notification
{
	/**
	 * Get a list of all the notification codes this hook can handle.
	 * (Addons can define hooks that handle whole sets of codes, so hooks are written so they can take wide authority)
	 *
	 * @return array			List of codes (mapping between code names, and a pair: section and labelling for those codes)
	 */
	function list_handled_codes()
	{
		$list=array();
		$catalogues=$GLOBALS['SITE_DB']->query('SELECT c_name,c_title FROM '.get_table_prefix().'catalogues WHERE '.db_string_not_equal_to('c_send_view_reports','never'));
		foreach ($catalogues as $catalogue)
		{
			$list['catalogue_view_reports__'.$catalogue['c_name']]=array(do_lang('GENERAL'),do_lang('NOTIFICATION_TYPE_catalogue_view_reports',get_translated_text($catalogue['c_title'])));
		}
		return $list;
	}

	/**
	 * Get a list of members who have enabled this notification (i.e. have permission to AND have chosen to or are defaulted to).
	 *
	 * @param  ID_TEXT		Notification code
	 * @param  ?SHORT_TEXT	The category within the notification code (NULL: none)
	 * @param  ?array			List of member IDs we are restricting to (NULL: no restriction). This effectively works as a intersection set operator against those who have enabled.
	 * @param  integer		Start position (for pagination)
	 * @param  integer		Maximum (for pagination)
	 * @return array			A pair: Map of members to their notification setting, and whether there may be more
	 */
	function list_members_who_have_enabled($notification_code,$category=NULL,$to_member_ids=NULL,$start=0,$max=300)
	{
		$members=$this->_all_members_who_have_enabled($notification_code,$category,$to_member_ids,$start,$max);
		$members=$this->_all_members_who_have_enabled_with_page_access($members,'cms_catalogues',$notification_code,$category,$to_member_ids,$start,$max);

		return $members;
	}
}
