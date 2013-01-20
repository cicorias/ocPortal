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
 * @package		catalogues
 */

class Hook_whats_news_catalogues
{

	/**
	 * Standard modular run function for newsletter hooks.
	 *
	 * @return ?array				Tuple of result details: HTML list of all types that can be choosed, title for selection list (NULL: disabled)
	 */
	function choose_categories()
	{
		if (!addon_installed('catalogues')) return NULL;

		require_lang('catalogues');

		require_code('catalogues');
		return array(nice_get_catalogues(NULL,true),do_lang('CATALOGUE_ENTRIES'));
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
		if (!module_installed('catalogues')) return array();

		require_lang('catalogues');

		$new=new ocp_tempcode();

		require_code('ocfiltering');
		$or_list=ocfilter_to_sqlfragment($filter,'c_name',NULL,NULL,NULL,NULL,false);
		$rows=$GLOBALS['SITE_DB']->query('SELECT cc_id,id,ce_submitter FROM '.get_table_prefix().'catalogue_entries WHERE ce_validated=1 AND ce_add_date>'.strval($cutoff_time).' AND ('.$or_list.') ORDER BY ce_add_date DESC',300);
		if (count($rows)==300) return array();
		foreach ($rows as $row)
		{
			$c_name=$GLOBALS['SITE_DB']->query_select_value_if_there('catalogue_categories','c_name',array('id'=>$row['cc_id']));
			if (is_null($c_name)) continue; // Corruption
			$c_title=$GLOBALS['SITE_DB']->query_select_value('catalogues','c_title',array('c_name'=>$c_name));
			$fields=$GLOBALS['SITE_DB']->query_select('catalogue_fields',array('id','cf_type'),array('c_name'=>$c_name),'ORDER BY id',1);
			$name='';
			switch ($fields[0]['cf_type'])
			{
				case 'short_trans':
					$_name=$GLOBALS['SITE_DB']->query_select_value('catalogue_efv_short_trans','cv_value',array('ce_id'=>$row['id'],'cf_id'=>$fields[0]['id']));
					$name=get_translated_text($_name,NULL,$lang);
					break;
				case 'short_text':
					$name=$GLOBALS['SITE_DB']->query_select_value('catalogue_efv_short','cv_value',array('ce_id'=>$row['id'],'cf_id'=>$fields[0]['id']));
					break;
				case 'float':
					$name=float_to_raw_string($GLOBALS['SITE_DB']->query_select_value('catalogue_efv_float','cv_value',array('ce_id'=>$row['id'],'cf_id'=>$fields[0]['id'])));
					break;
				case 'integer':
					$name=strval($GLOBALS['SITE_DB']->query_select_value('catalogue_efv_integer','cv_value',array('ce_id'=>$row['id'],'cf_id'=>$fields[0]['id'])));
					break;
			}

			$_url=build_url(array('page'=>'catalogues','type'=>'entry','id'=>$row['id']),get_module_zone('catalogues'),NULL,false,false,true);
			$url=$_url->evaluate();
			$catalogue=get_translated_text($c_title,NULL,$lang);
			$member_id=(is_guest($row['ce_submitter']))?NULL:strval($row['ce_submitter']);
			$new->attach(do_template('NEWSLETTER_NEW_RESOURCE_FCOMCODE',array('_GUID'=>'4ae604e5d0e9cf4d28e7d811dc4558e5','MEMBER_ID'=>$member_id,'URL'=>$url,'CATALOGUE'=>$catalogue,'NAME'=>$name)));
		}

		return array($new,do_lang('CATALOGUE_ENTRIES','','','',$lang));
	}

}


