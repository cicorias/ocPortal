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
 * @package		banners
 */

class Hook_exists_banner_type
{

	/**
	 * Standard modular run function for snippet hooks. Generates XHTML to insert into a page using AJAX.
	 *
	 * @return tempcode  The snippet
	 */
	function run()
	{
		$val=get_param('name');

		$test=$GLOBALS['SITE_DB']->query_select_value_if_there('banner_types','id',array('id'=>$val));
		if (is_null($test)) return new ocp_tempcode();

		return make_string_tempcode(str_replace(array('&lsquo;','&rsquo;','&ldquo;','&rdquo;'),array('"','"','"','"'),html_entity_decode(do_lang('ALREADY_EXISTS',escape_html($val)),ENT_QUOTES)));
	}

}
