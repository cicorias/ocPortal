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
 * @package		securitylogging
 */

/**
 * Module page class.
 */
class Module_admin_security
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
		$info['version']=4;
		$info['update_require_upgrade']=1;
		$info['locked']=true;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_table_if_exists('hackattack');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if (is_null($upgrade_from))
		{
			$GLOBALS['SITE_DB']->create_table('hackattack',array(
				'id'=>'*AUTO',
				'url'=>'URLPATH',
				'data_post'=>'LONG_TEXT',
				'user_agent'=>'SHORT_TEXT',
				'referer'=>'SHORT_TEXT',
				'user_os'=>'SHORT_TEXT',
				'member_id'=>'MEMBER',
				'date_and_time'=>'TIME',
				'ip'=>'IP',
				'reason'=>'ID_TEXT',
				'reason_param_a'=>'SHORT_TEXT',
				'reason_param_b'=>'SHORT_TEXT'
			));
			$GLOBALS['SITE_DB']->create_index('hackattack','otherhacksby',array('ip'));
			$GLOBALS['SITE_DB']->create_index('hackattack','h_date_and_time',array('date_and_time'));
		}

		if ((!is_null($upgrade_from)) && ($upgrade_from<3))
		{
			$GLOBALS['SITE_DB']->add_table_field('hackattack','user_agent','SHORT_TEXT');
			$GLOBALS['SITE_DB']->add_table_field('hackattack','referer','SHORT_TEXT');
			$GLOBALS['SITE_DB']->add_table_field('hackattack','user_os','SHORT_TEXT');
		}

		if ((!is_null($upgrade_from)) && ($upgrade_from<4))
		{
			$GLOBALS['SITE_DB']->alter_table_field('hackattack','the_user','MEMBER','member_id');
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'SECURITY_LOGGING');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('security');
		require_code('lookup');
		require_all_lang();

		set_helper_panel_pic('pagepics/securitylog');
		set_helper_panel_tutorial('tut_security');

		$type=get_param('type','misc');

		if ($type=='misc') return $this->security_interface();
		if ($type=='clean') return $this->clean_alerts();
		if ($type=='view') return $this->alert_view();

		return new ocp_tempcode();
	}

	/**
	 * The UI to view security logs.
	 *
	 * @return tempcode		The UI
	 */
	function security_interface()
	{
		$title=get_screen_title('SECURITY_LOGGING');

		// Failed logins
		$start=get_param_integer('failed_start',0);
		$max=get_param_integer('failed_max',50);
		$sortables=array('date_and_time'=>do_lang_tempcode('DATE_TIME'),'ip'=>do_lang_tempcode('IP_ADDRESS'));
		$test=explode(' ',get_param('failed_sort','date_and_time DESC'));
		if (count($test)==1) $test[1]='DESC';
		list($_sortable,$sort_order)=$test;
		if (((strtoupper($sort_order)!='ASC') && (strtoupper($sort_order)!='DESC')) || (!array_key_exists($_sortable,$sortables)))
			log_hack_attack_and_exit('ORDERBY_HACK');
		inform_non_canonical_parameter('failed_sort');
		require_code('templates_results_table');
		$fields_title=results_field_title(array(do_lang_tempcode('USERNAME'),do_lang_tempcode('DATE_TIME'),do_lang_tempcode('IP_ADDRESS')),$sortables,'failed_sort',$_sortable.' '.$sort_order);
		$member_id=post_param_integer('member_id',NULL);
		$map=(!is_null($member_id))?array('failed_account'=>$GLOBALS['FORUM_DRIVER']->get_username($member_id)):NULL;
		$max_rows=$GLOBALS['SITE_DB']->query_select_value('failedlogins','COUNT(*)',$map);
		$rows=$GLOBALS['SITE_DB']->query_select('failedlogins',array('*'),$map,'ORDER BY '.$_sortable.' '.$sort_order,$max,$start);
		$fields=new ocp_tempcode();
		foreach ($rows as $row)
		{
			$time=get_timezoned_date($row['date_and_time']);
			$lookup_url=build_url(array('page'=>'admin_lookup','param'=>$row['ip']),'_SELF');
			$fields->attach(results_entry(array(escape_html($row['failed_account']),escape_html($time),hyperlink($lookup_url,$row['ip']))));
		}
		$failed_logins=results_table(do_lang_tempcode('FAILED_LOGINS'),$start,'failed_start',$max,'failed_max',$max_rows,$fields_title,$fields,$sortables,$_sortable,$sort_order,'failed_sort',new ocp_tempcode());

		$member_id=post_param_integer('member_id',NULL);
		$map=(!is_null($member_id))?array('member_id'=>$member_id):NULL;
		$alerts=find_security_alerts($map);

		$post_url=build_url(array('page'=>'_SELF','type'=>'clean','start'=>$start,'max'=>$max),'_SELF');

		$tpl=do_template('SECURITY_SCREEN',array('_GUID'=>'e0b5e6557686b2320a8ce8166df07328','TITLE'=>$title,'FAILED_LOGINS'=>$failed_logins,'ALERTS'=>$alerts,'URL'=>$post_url));

		require_code('templates_internalise_screen');
		return internalise_own_screen($tpl);
	}

	/**
	 * Actualiser to delete some unwanted alerts.
	 *
	 * @return tempcode		The success/redirect screen
	 */
	function clean_alerts()
	{
		$title=get_screen_title('SECURITY_LOGGING');

		// Actualiser
		$count=0;
		foreach (array_keys($_REQUEST) as $key)
		{
			if (substr($key,0,4)=='del_')
			{
				$GLOBALS['SITE_DB']->query_delete('hackattack',array('id'=>intval(substr($key,4))),'',1);
				$count++;
			}
		}

		if ($count==0) warn_exit(do_lang_tempcode('NOTHING_SELECTED'));

		// Redirect
		$url=build_url(array('page'=>'_SELF','type'=>'misc','start'=>get_param_integer('start'),'max'=>get_param_integer('max')),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * The UI to view a security alert.
	 *
	 * @return tempcode		The UI
	 */
	function alert_view()
	{
		$id=get_param_integer('id');
		$rows=$GLOBALS['SITE_DB']->query_select('hackattack',array('*'),array('id'=>$id));
		$row=$rows[0];

		$time=get_timezoned_date($row['date_and_time']);

		$title=get_screen_title('VIEW_ALERT',true,array(escape_html($time)));

		$lookup_url=build_url(array('page'=>'admin_lookup','param'=>$row['ip']),'_SELF');
		$member_url=build_url(array('page'=>'admin_lookup','param'=>$row['member_id']),'_SELF');
		$reason=do_lang($row['reason'],$row['reason_param_a'],$row['reason_param_b']);

		$post=with_whitespace(unixify_line_format($row['data_post']));

		$username=$GLOBALS['FORUM_DRIVER']->get_username($row['member_id']);
		if (is_null($username)) $username=do_lang('UNKNOWN');

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('SECURITY_LOGGING'))));

		return do_template('SECURITY_ALERT_SCREEN',array(
			'_GUID'=>'6c5543151af09c79bf204bea5df61dde',
			'TITLE'=>$title,
			'USER_AGENT'=>$row['user_agent'],
			'REFERER'=>$row['referer'],
			'USER_OS'=>$row['user_os'],
			'REASON'=>$reason,
			'IP'=>hyperlink($lookup_url,$row['ip']),
			'USERNAME'=>hyperlink($member_url,escape_html($username)),
			'POST'=>$post,
			'URL'=>$row['url'],
		));
	}

}


