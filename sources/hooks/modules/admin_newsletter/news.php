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
 * @package		news
 */

class Hook_whats_news_news
{

	/**
	 * Standard modular run function for newsletter hooks.
	 *
	 * @return ?array				Tuple of result details: HTML list of all types that can be choosed, title for selection list (NULL: disabled)
	 */
	function choose_categories()
	{
		if (!addon_installed('news')) return NULL;

		require_lang('news');

		require_code('news');
		return array(nice_get_news_categories(NULL,false,false,true),do_lang('NEWS'));
	}

	/**
	 * Standard modular run function for newsletter hooks.
	 *
	 * @param  TIME				The time that the entries found must be newer than
	 * @param  LANGUAGE_NAME	The language the entries found must be in
	 * @param  string				Category filter to apply
	 * @param  BINARY				Whether to use full article instead of summary
	 * @return array				Tuple of result details
	 */
	function run($cutoff_time,$lang,$filter,$in_full=1)
	{
		if (!addon_installed('news')) return array();

		require_lang('news');

		$new=new ocp_tempcode();

		require_code('ocfiltering');
		$or_list=ocfilter_to_sqlfragment($filter,'news_category');
		$or_list_2=ocfilter_to_sqlfragment($filter,'news_entry_category');
		$rows=$GLOBALS['SITE_DB']->query('SELECT title,news,news_article,id,date_and_time,submitter FROM '.get_table_prefix().'news LEFT JOIN '.get_table_prefix().'news_category_entries ON news_entry=id WHERE validated=1 AND date_and_time>'.strval($cutoff_time).' AND (('.$or_list.') OR ('.$or_list_2.')) ORDER BY date_and_time DESC',300);
		if (count($rows)==300) return array();
		$rows=remove_duplicate_rows($rows,'id');
		foreach ($rows as $row)
		{
			$_url=build_url(array('page'=>'news','type'=>'view','id'=>$row['id']),get_module_zone('news'),NULL,false,false,true);
			$url=$_url->evaluate();
			$name=get_translated_text($row['title'],NULL,$lang);
			$description=get_translated_text($row[($in_full==1)?'news_article':'news'],NULL,$lang);
			if ($description=='')
			{
				$description=get_translated_text($row[($in_full==1)?'news':'news_article'],NULL,$lang);
			}
			$member_id=(is_guest($row['submitter']))?NULL:strval($row['submitter']);
			$new->attach(do_template('NEWSLETTER_NEW_RESOURCE_FCOMCODE',array('_GUID'=>'4eaf5ec00db1f0b89cef5120c2486521','MEMBER_ID'=>$member_id,'URL'=>$url,'NAME'=>$name,'DESCRIPTION'=>$description)));
		}

		return array($new,do_lang('NEWS','','','',$lang));
	}

}


