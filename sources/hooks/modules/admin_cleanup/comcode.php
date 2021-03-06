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
 * @package		core_cleanup_tools
 */

class Hook_comcode
{

	/**
	 * Standard modular info function.
	 *
	 * @return ?array	Map of module info (NULL: module is disabled).
	 */
	function info()
	{
		if ($GLOBALS['SITE_DB']->query_value('translate','COUNT(*)')>100000) return NULL; // Too much work. Can be done from upgrader, but people won't go in there so much. People don't really need to go emptying this cache on real sites.

		$info=array();
		$info['title']=do_lang_tempcode('COMCODE_CACHE');
		$info['description']=do_lang_tempcode('DESCRIPTION_COMCODE_CACHE');
		$info['type']='cache';

		return $info;
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	Results
	 */
	function run()
	{
		/*		$rows=$GLOBALS['SITE_DB']->query_select('translate',array('*'));
		foreach ($rows as $row)
		{
			$text_parsed=comcode_to_tempcode($row['text_original'],$row['source_user']);
			$text_parsed=$text_parsed->to_assembly();
			$GLOBALS['SITE_DB']->query_update('translate',array('text_parsed'=>$text_parsed),array('id'=>$row['id'],'language'=>$row['language']));
		}*/
		require_code('view_modes');
		erase_comcode_cache();

		return new ocp_tempcode();
	}

}


