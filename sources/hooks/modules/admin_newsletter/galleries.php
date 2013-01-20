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

class Hook_whats_news_galleries
{

	/**
	 * Standard modular run function for newsletter hooks.
	 *
	 * @return ?array				Tuple of result details: HTML list of all types that can be choosed, title for selection list (NULL: disabled)
	 */
	function choose_categories()
	{
		if (!addon_installed('galleries')) return NULL;

		require_lang('galleries');

		require_code('galleries');
		return array(nice_get_gallery_tree(NULL,NULL,false,false,true),do_lang('GALLERIES'));
	}

	/**
	 * Standard modular run function for newsletter hooks.
	 *
	 * @param  TIME				The time that the entries found must be newer than
	 * @param  LANGUAGE_NAME	The language the entries found must be in
	 * @param  string				Category filter to apply
	 * @return array				Tuple of result details
	 */
	function run($cutoff_time,$lang,$filter)
	{
		if (!addon_installed('galleries')) return array();

		require_lang('galleries');

		$new=new ocp_tempcode();

		$count=$GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'galleries WHERE name NOT LIKE \''.db_encode_like('download\_%').'\'');
		if ($count<500)
		{
			$_galleries=$GLOBALS['SITE_DB']->query('SELECT name,fullname FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'galleries WHERE name NOT LIKE \''.db_encode_like('download\_%').'\'',300);
			if (count($_galleries)==300) return array();
			foreach ($_galleries as $i=>$_gallery)
			{
				$_galleries[$i]['text_original']=get_translated_text($_gallery['fullname']);
			}
			$galleries=collapse_2d_complexity('name','text_original',$_galleries);
		} else $galleries=array();
		require_code('ocfiltering');
		$or_list=ocfilter_to_sqlfragment($filter,'cat',NULL,NULL,NULL,NULL,false);
		$rows=$GLOBALS['SITE_DB']->query('SELECT * FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'videos WHERE add_date>'.strval($cutoff_time).' AND validated=1 AND ('.$or_list.') ORDER BY add_date DESC',300/*reasonable limit*/);
		if (count($rows)==300) return array();
		foreach ($rows as $row)
		{
			$_url=build_url(array('page'=>'galleries','type'=>'video','id'=>$row['id']),get_module_zone('galleries'),NULL,false,false,true);
			$url=$_url->evaluate();
			if (!array_key_exists($row['cat'],$galleries))
			{
				$galleries[$row['cat']]=get_translated_text($GLOBALS['SITE_DB']->query_select_value('galleries','fullname',array('name'=>$row['cat'])));
			}
			$name=$galleries[$row['cat']];
			$description=get_translated_text($row['description'],NULL,$lang);
			$member_id=(is_guest($row['submitter']))?NULL:strval($row['submitter']);
			$new->attach(do_template('NEWSLETTER_NEW_RESOURCE_FCOMCODE',array('_GUID'=>'dfe5850aa67c0cd00ff7d465248b87a5','MEMBER_ID'=>$member_id,'URL'=>$url,'NAME'=>$name,'DESCRIPTION'=>$description)));
		}

		return array($new,do_lang('GALLERIES','','','',$lang));
	}

}


