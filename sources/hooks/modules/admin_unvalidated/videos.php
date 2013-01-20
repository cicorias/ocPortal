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
 * @package		galleries
 */

class Hook_unvalidated_videos
{

	/**
	 * Standard modular info function.
	 *
	 * @return ?array	Map of module info (NULL: module is disabled).
	 */
	function info()
	{
		if (!module_installed('galleries')) return NULL;

		require_lang('galleries');

		$info=array();
		$info['db_table']='videos';
		$info['db_identifier']='id';
		$info['db_validated']='validated';
		$info['db_title']='title';
		$info['db_title_dereference']=true;
		$info['db_add_date']='add_date';
		$info['db_edit_date']='edit_date';
		$info['edit_module']='cms_galleries';
		$info['edit_type']='_ev';
		$info['view_module']='galleries';
		$info['view_type']='video';
		$info['edit_identifier']='id';
		$info['title']=do_lang_tempcode('VIDEOS');

		return $info;
	}

}


