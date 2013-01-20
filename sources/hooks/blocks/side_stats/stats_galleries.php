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

class Hook_stats_galleries
{

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		if (!addon_installed('galleries')) return new ocp_tempcode();

		require_lang('galleries');

		$bits=new ocp_tempcode();
		if (get_option('galleries_show_stats_count_galleries',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'979bcf993db7c01ced08d8f8a696fec0','KEY'=>do_lang_tempcode('GALLERIES'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_select_value('galleries','COUNT(*)')))));
		if (get_option('galleries_show_stats_count_images',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'0f06d6a5e1632bae0101a531912b1c29','KEY'=>do_lang_tempcode('IMAGES'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_select_value('images','COUNT(*)')))));
		if (get_option('galleries_show_stats_count_videos',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'a9274594cde52028fc810b7b780e9942','KEY'=>do_lang_tempcode('VIDEOS'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_select_value('videos','COUNT(*)')))));
		if ($bits->is_empty()) return new ocp_tempcode();
		$section=do_template('BLOCK_SIDE_STATS_SECTION',array('_GUID'=>'128d3b49ad53927dff65252735dd2106','SECTION'=>do_lang_tempcode('GALLERIES'),'CONTENT'=>$bits));

		return $section;
	}

}


