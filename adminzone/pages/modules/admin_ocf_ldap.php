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
 * @package		ldap
 */

/**
 * Module page class.
 */
class Module_admin_ocf_ldap
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
		delete_config_option('ldap_is_enabled');
		delete_config_option('ldap_is_windows');
		delete_config_option('ldap_allow_joining');
		delete_config_option('ldap_hostname');
		delete_config_option('ldap_base_dn');
		delete_config_option('ldap_bind_rdn');
		delete_config_option('ldap_bind_password');
		delete_config_option('windows_auth_is_enabled');
		delete_config_option('ldap_login_qualifier');
		delete_config_option('ldap_group_search_qualifier');
		delete_config_option('ldap_member_search_qualifier');
		delete_config_option('ldap_member_property');
		delete_config_option('ldap_none_bind_logins');
		delete_config_option('ldap_version');
		delete_config_option('ldap_group_class');
		delete_config_option('ldap_member_class');
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
			add_config_option('LDAP_IS_ENABLED','ldap_is_enabled','tick','return \''.(in_array(ocp_srv('HTTP_HOST'),array('localhost','test.ocportal.com'))?'0':'0').'\';','SECTION_FORUMS','LDAP',1);
			add_config_option('LDAP_IS_WINDOWS','ldap_is_windows','tick','return (DIRECTORY_SEPARATOR==\'/\')?\'0\':\'1\';','SECTION_FORUMS','LDAP',1);
			add_config_option('LDAP_ALLOW_JOINING','ldap_allow_joining','tick','return \'0\';','SECTION_FORUMS','LDAP',1);
			add_config_option('LDAP_HOSTNAME','ldap_hostname','line','return \'localhost\';','SECTION_FORUMS','LDAP',1);
			add_config_option('LDAP_BASE_DN','ldap_base_dn','line','return \''.'dc='.str_replace('.',',dc=',ocp_srv('HTTP_HOST')).'\';','SECTION_FORUMS','LDAP',1);
			add_config_option('USERNAME','ldap_bind_rdn','line','return (DIRECTORY_SEPARATOR==\'/\')?\'NotManager\':\'NotAdministrator\';','SECTION_FORUMS','LDAP',1);
			add_config_option('PASSWORD','ldap_bind_password','line','return \'\';','SECTION_FORUMS','LDAP',1);
			add_config_option('WINDOWS_AUTHENTICATION','windows_auth_is_enabled','tick','return \'0\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_LOGIN_QUALIFIER','ldap_login_qualifier','line','return \'\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_GROUP_SEARCH_QUALIFIER','ldap_group_search_qualifier','line','return \'\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_MEMBER_SEARCH_QUALIFIER','ldap_member_search_qualifier','line','return \'\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_MEMBER_PROPERTY','ldap_member_property','line','return (get_option(\'ldap_is_windows\')==\'1\')?\'sAMAccountName\':\'cn\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_NONE_BIND_LOGINS','ldap_none_bind_logins','tick','return \'0\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_VERSION','ldap_version','integer','return \'3\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_GROUP_CLASS','ldap_group_class','line','return (get_option(\'ldap_is_windows\')==\'1\')?\'group\':\'posixGroup\';','SECTION_FORUMS','LDAP');
			add_config_option('LDAP_MEMBER_CLASS','ldap_member_class','line','return (get_option(\'ldap_is_windows\')==\'1\')?\'user\':\'posixAccount\';','SECTION_FORUMS','LDAP');
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'LDAP_SYNC');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		set_helper_panel_pic('pagepics/ldap');
		set_helper_panel_tutorial('tut_ldap');

		if (get_forum_type()!='ocf') warn_exit(do_lang_tempcode('NO_OCF')); else ocf_require_all_forum_stuff();
		require_code('ocf_groups_action');
		require_code('ocf_groups_action2');

		require_lang('ocf');

		global $LDAP_CONNECTION;
		if (is_null($LDAP_CONNECTION)) warn_exit(do_lang_tempcode('LDAP_DISABLED'));

		// Decide what we're doing
		$type=get_param('type','misc');

		if ($type=='misc') return $this->gui();
		if ($type=='actual') return $this->actual();
		return new ocp_tempcode();
	}

	/**
	 * The UI for LDAP synchronisation.
	 *
	 * @return tempcode		The UI
	 */
	function gui()
	{
		$title=get_screen_title('LDAP_SYNC');

		$groups_add=new ocp_tempcode();
		$groups_delete=new ocp_tempcode();
		$members_delete=new ocp_tempcode();

		$all_ldap_groups=ocf_get_all_ldap_groups();
		foreach ($all_ldap_groups as $group)
		{
			if (is_null(ocf_group_ldapcn_to_ocfid($group)))
			{
				$_group=str_replace(' ','_space_',$group);
				$tpl=do_template('OCF_LDAP_LIST_ENTRY',array('_GUID'=>'99aa6dd1a7a4caafd0199f8b5512cf29','NAME'=>'add_group_'.$_group,'NICE_NAME'=>$group));
				$groups_add->attach($tpl);
			}
		}
		$all_ocp_groups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list();
		foreach ($all_ocp_groups as $id=>$group)
		{
			if ((!in_array($group,$all_ldap_groups)) && ($id!=db_get_first_id()+0) && ($id!=db_get_first_id()+1) && ($id!=db_get_first_id()+8))
			{
				$tpl=do_template('OCF_LDAP_LIST_ENTRY',array('_GUID'=>'48de4d176157941a0ce7caa7a1c395fb','NAME'=>'delete_group_'.strval($id),'NICE_NAME'=>$group));
				$groups_delete->attach($tpl);
			}
		}

		if (function_exists('set_time_limit')) @set_time_limit(0);

		$start=0;
		do
		{
			$all_ldap_members=$GLOBALS['FORUM_DB']->query_select('f_members',array('id','m_username'),array('m_password_compat_scheme'=>'ldap'),'',400,$start);
			foreach ($all_ldap_members as $row)
			{
				$id=$row['id'];
				$username=$row['m_username'];

				if (!ocf_is_ldap_member_potential($username))
				{
					$tpl=do_template('OCF_LDAP_LIST_ENTRY',array('_GUID'=>'572c0f1e87a2dbe6cdf31d97fd71d3a4','NAME'=>'delete_member_'.strval($id),'NICE_NAME'=>$username));
					$members_delete->attach($tpl);
				}
			}
			$start+=400;
		}
		while (array_key_exists(0,$all_ldap_members));

		$post_url=build_url(array('page'=>'_SELF','type'=>'actual'),'_SELF');

		return do_template('OCF_LDAP_SYNC_SCREEN',array('_GUID'=>'38c608ce56cf3dbafb1dd1446c65d592','URL'=>$post_url,'TITLE'=>$title,'MEMBERS_DELETE'=>$members_delete,'GROUPS_DELETE'=>$groups_delete,'GROUPS_ADD'=>$groups_add));
	}

	/**
	 * The actualiser for LDAP synchronisation.
	 *
	 * @return tempcode		The UI
	 */
	function actual()
	{
		$title=get_screen_title('LDAP_SYNC');

		$all_ldap_groups=ocf_get_all_ldap_groups();
		foreach ($all_ldap_groups as $group)
		{
			if (post_param_integer('add_group_'.str_replace(' ','_space_',$group),0)==1)
			{
				ocf_make_group($group,0,0,0,'');
			}
		}
		$all_ocp_groups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list();
		foreach ($all_ocp_groups as $id=>$group)
		{
			if (post_param_integer('delete_group_'.strval($id),0)==1)
			{
				ocf_delete_group($id);
			}
		}

		$all_ldap_members=$GLOBALS['FORUM_DB']->query_select('f_members',array('id'),array('m_password_compat_scheme'=>'ldap'));
		require_code('ocf_groups_action');
		require_code('ocf_groups_action2');
		foreach ($all_ldap_members as $row)
		{
			$id=$row['id'];

			if (post_param_integer('delete_member_'.strval($id),0)==1)
			{
				ocf_delete_member($id);
			}
		}

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('LDAP_SYNC'))));
		breadcrumb_set_self(do_lang_tempcode('DONE'));

		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

}


