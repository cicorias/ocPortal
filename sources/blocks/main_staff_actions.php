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
 * @package		core_adminzone_frontpage
 */

class Block_main_staff_actions
{

	/**
	 * Standard modular info function.
	 *
	 * @return ?array	Map of module info (NULL: module is disabled).
	 */
	function info()
	{
		$info=array();
		$info['author']='Chris Graham';
		$info['organisation']='ocProducts';
		$info['hacked_by']=NULL;
		$info['hack_version']=NULL;
		$info['version']=2;
		$info['locked']=true;
		$info['parameters']=array('max');
		return $info;
	}

	/**
	 * Standard modular cache function.
	 *
	 * @return ?array	Map of cache details (cache_on and ttl) (NULL: module is disabled).
	 */
	function cacheing_environment()
	{
		$info=array();
		$info['cache_on']='array(get_param_integer(\'sa_start\',0),array_key_exists(\'max\',$map)?intval($map[\'max\']):get_param_integer(\'sa_max\',10),get_param(\'sa_sort\',\'date_and_time\'),get_param(\'sort_order\',\'DESC\'))';
		$info['ttl']=(get_value('no_block_timeout')==='1')?60*60*24*365*5/*5 year timeout*/:60*5;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_table_if_exists('adminlogs');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		$GLOBALS['SITE_DB']->create_table('adminlogs',array(
			'id'=>'*AUTO',
			'the_type'=>'ID_TEXT',
			'param_a'=>'ID_TEXT',
			'param_b'=>'SHORT_TEXT',
			'member_id'=>'MEMBER',
			'ip'=>'IP',
			'date_and_time'=>'TIME'
		));

		$GLOBALS['SITE_DB']->create_index('adminlogs','xas',array('member_id'));
		$GLOBALS['SITE_DB']->create_index('adminlogs','ts',array('date_and_time'));
		$GLOBALS['SITE_DB']->create_index('adminlogs','aip',array('ip'));
		$GLOBALS['SITE_DB']->create_index('adminlogs','athe_type',array('the_type'));
	}

	/**
	 * Standard modular run function.
	 *
	 * @param  array		A map of parameters.
	 * @return tempcode	The result of execution.
	 */
	function run($map)
	{
		require_all_lang();

		require_css('adminzone_frontpage');

		require_code('actionlog');

		$start=get_param_integer('sa_start',0);
		$max=array_key_exists('max',$map)?intval($map['max']):get_param_integer('sa_max',10);
		$sortables=array('date_and_time'=>do_lang_tempcode('DATE_TIME'),/*Not enough space 'ip'=>do_lang_tempcode('IP_ADDRESS'),*/'the_type'=>do_lang_tempcode('ACTION'));
		$test=explode(' ',get_param('sa_sort','date_and_time DESC'),2);
		if (count($test)==1) $test[1]='DESC';
		list($sortable,$sort_order)=$test;
		if (((strtoupper($sort_order)!='ASC') && (strtoupper($sort_order)!='DESC')) || (!array_key_exists($sortable,$sortables)))
			log_hack_attack_and_exit('ORDERBY_HACK');
		inform_non_canonical_parameter('sa_sort');

		require_code('templates_results_table');
		$fields_title=results_field_title(array(do_lang_tempcode('USERNAME'),/*do_lang_tempcode('IP_ADDRESS'),*/do_lang_tempcode('DATE_TIME'),do_lang_tempcode('ACTION'),do_lang_tempcode('PARAMETER_A'),do_lang_tempcode('PARAMETER_B')),$sortables,'sa_sort',$sortable.' '.$sort_order);

		$max_rows=$max;//Don't want to encourage pagination (there's a better module they can go to) $GLOBALS['SITE_DB']->query_select_value('adminlogs','COUNT(*)');
		$rows=$GLOBALS['SITE_DB']->query_select('adminlogs',array('the_type','param_a','param_b','member_id','ip','date_and_time'),NULL,'ORDER BY '.$sortable.' '.$sort_order,$max,$start);
		$fields=new ocp_tempcode();
		foreach ($rows as $myrow)
		{
			$username=$GLOBALS['FORUM_DRIVER']->get_username($myrow['member_id']);
			if (is_null($username)) $username=do_lang('UNKNOWN');
			$date=get_timezoned_date($myrow['date_and_time']);

			if (!is_null($myrow['param_a'])) $a=$myrow['param_a']; else $a='';
			if (!is_null($myrow['param_b'])) $b=$myrow['param_b']; else $b='';

			require_code('templates_interfaces');
			$_a=tpl_crop_text_mouse_over($a,8);
			$_b=tpl_crop_text_mouse_over($b,15);

			$type_str=do_lang($myrow['the_type'],$_a,$_b,NULL,NULL,false);
			if (is_null($type_str)) $type_str=$myrow['the_type'];

			$test=actionlog_linkage($myrow['the_type'],$a,$b,$_a,$_b);
			if (!is_null($test)) list($_a,$_b)=$test;

			$ip=tpl_crop_text_mouse_over($myrow['ip'],12);

			$fields->attach(results_entry(array(escape_html($username)/*Not enough space ,$ip*/,escape_html($date),$type_str,$_a,$_b)));
		}

		$content=results_table(do_lang_tempcode('ACTIONS'),$start,'sa_start',$max,'sa_max',$max_rows,$fields_title,$fields,$sortables,$sortable,$sort_order,'sa_sort',new ocp_tempcode(),NULL,NULL,5);

		// Render block wrapper template around actions table
		return do_template('BLOCK_MAIN_STAFF_ACTIONS',array(
			'_GUID'=>'16a5b384015504a6a57fc4ddedbe91a7',
			'BLOCK_PARAMS'=>block_params_arr_to_str($map),
			'CONTENT'=>$content,
		));
	}

}


