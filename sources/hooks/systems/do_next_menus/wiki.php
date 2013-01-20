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
 * @package		wiki
 */


class Hook_do_next_menus_wiki
{

	/**
	 * Standard modular run function for do_next_menu hooks. They find links to put on standard navigation menus of the system.
	 *
	 * @return array			Array of links and where to show
	 */
	function run()
	{
		if (!addon_installed('wiki')) return array();

		return array(
			array('cms','wiki',array('cms_wiki',array('type'=>'misc'),get_module_zone('cms_wiki')),do_lang_tempcode('ITEMS_HERE',do_lang_tempcode('WIKI'),make_string_tempcode(escape_html(integer_format($GLOBALS['SITE_DB']->query_select_value_if_there('wiki_pages','COUNT(*)',NULL,'',true))))),('DOC_WIKI')),
		);
	}

}


