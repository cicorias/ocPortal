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
 * @package		staff
 */

/**
 * Module page class.
 */
class Module_admin_staff
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
		$info['update_require_upgrade']=1;
		$info['version']=3;
		$info['locked']=true;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		delete_config_option('staff_text');
		delete_config_option('is_on_staff_filter');
		delete_config_option('is_on_sync_staff');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if ($upgrade_from==2)
		{
			$GLOBALS['SITE_DB']->query_update('config',array('eval'=>'return do_lang(\'POST_STAFF\');'),array('the_name'=>'staff_text'),'',1);
			if ($GLOBALS['CONFIG_OPTIONS_CACHE']['staff_text']['c_set']==1)
			{
				set_option('staff_text','[html]'.get_option('staff_text').'[/html]');
			} else
			{
				set_option('staff_text',do_lang('POST_STAFF'));
			}
			return;
		}

		add_config_option('PAGE_TEXT','staff_text','transtext','return do_lang(\'POST_STAFF\');','SECURITY','STAFF');
		add_config_option('MEMBER_FILTER','is_on_staff_filter','tick','return \'0\';','SECURITY','STAFF',1);
		add_config_option('SYNCHRONISATION','is_on_sync_staff','tick','return \'0\';','SECURITY','STAFF',1);

		$usergroups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list(false,true);
		foreach (array_keys($usergroups) as $id)
		{
			$GLOBALS['SITE_DB']->query_insert('group_page_access',array('page_name'=>'admin_staff','zone_name'=>'adminzone','group_id'=>$id));
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'MANAGE_STAFF');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('staff');

		set_helper_panel_pic('pagepics/staff');
		set_helper_panel_tutorial('tut_staff');

		$type=get_param('type','misc');

		if ($type=='edit') return $this->staff_edit();
		if ($type=='misc') return $this->staff_interface();

		return new ocp_tempcode();
	}

	/**
	 * The UI for editing staff information.
	 *
	 * @return tempcode		The UI
	 */
	function staff_interface()
	{
		if (get_forum_type()=='none') warn_exit(do_lang_tempcode('NO_MEMBER_SYSTEM_INSTALLED'));

		$title=get_screen_title('MANAGE_STAFF');

		if (get_option('is_on_staff_filter')=='0') $text=do_lang_tempcode('STAFF_FILTER_OFF'); else $text=do_lang_tempcode('STAFF_FILTER_ON');

		$admin_groups=array_merge($GLOBALS['FORUM_DRIVER']->get_super_admin_groups(),$GLOBALS['FORUM_DRIVER']->get_moderator_groups());
		$staff=$GLOBALS['FORUM_DRIVER']->member_group_query($admin_groups,400);
		if (count($staff)>=400)
			warn_exit(do_lang_tempcode('TOO_MANY_TO_CHOOSE_FROM'));
		$available=new ocp_tempcode();
		require_code('form_templates');
		foreach ($staff as $row_staff)
		{
			$id=$GLOBALS['FORUM_DRIVER']->pname_id($row_staff);
			$name=$GLOBALS['FORUM_DRIVER']->pname_name($row_staff);
			$role=get_ocp_cpf('role',$id);
			$fullname=get_ocp_cpf('fullname',$id);

			$fields=form_input_line(do_lang_tempcode('REALNAME'),'','fullname_'.strval($id),$fullname,false);
			$fields->attach(form_input_line(do_lang_tempcode('ROLE'),do_lang_tempcode('DESCRIPTION_ROLE'),'role_'.strval($id),$role,false));

			if (get_option('is_on_staff_filter')=='1')
			{
				if ($GLOBALS['FORUM_DRIVER']->is_staff($id))
				{
					$submit_name=do_lang_tempcode('REMOVE');
					$submit_type='remove';
				}
				else
				{
					$submit_name=do_lang_tempcode('ADD');
					$submit_type='add';
				}

				$fields->attach(form_input_tick($submit_name,'',$submit_type.'_'.strval($id),false));
			}

			$form=do_template('FORM_GROUP',array('_GUID'=>'0e7d362817a7f3ae190536adf632fe59','HIDDEN'=>form_input_hidden('staff_'.strval($id),strval($id)),'FIELDS'=>$fields));

			$available->attach(do_template('STAFF_EDIT_WRAPPER',array('_GUID'=>'ab0516dba94c20b4d97f68677053b20d','FORM'=>$form,'NAME'=>$name)));
		}
		if (!$available->is_empty())
		{
			$post_url=build_url(array('page'=>'_SELF','type'=>'edit'),'_SELF');
			$available=do_template('FORM_GROUPED',array('_GUID'=>'5b74208b6c420edcdeb34bb49f1e9dcb','TEXT'=>'','URL'=>$post_url,'FIELD_GROUPS'=>$available,'SUBMIT_NAME'=>do_lang_tempcode('SAVE')));
		}

		return do_template('STAFF_ADMIN_SCREEN',array('_GUID'=>'101087b0dbe5d679a55bb661ad7350fa','TITLE'=>$title,'TEXT'=>$text,'FORUM_STAFF'=>$available));
	}

	/**
	 * The actualiser for editing staff information.
	 *
	 * @return tempcode		The UI
	 */
	function staff_edit()
	{
		$title=get_screen_title('EDIT_STAFF');
		foreach ($_POST as $key=>$val)
		{
			if (!is_string($val)) continue;
			if (substr($key,0,6)=='staff_')
			{
				$id=intval($val); // e.g. $key=staff_2, $val=2	- so could also say $id=intval(substr($key,6));

				$this->_staff_edit($id,post_param('role_'.strval($id)),post_param('fullname_'.strval($id)));

				if ((post_param_integer('remove_'.strval($id),0)==1) && (get_option('is_on_staff_filter')=='1')) $this->_staff_remove($id);
				elseif (post_param_integer('add_'.strval($id),0)==1) $this->_staff_add($id);
			}
		}

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Edit a member of staff.
	 *
	 * @param  MEMBER			The member ID of the staff being edited
	 * @param  SHORT_TEXT	The role of the staff member
	 * @param  SHORT_TEXT	The full-name of the staff member
	 */
	function _staff_edit($id,$role,$fullname)
	{
		$GLOBALS['FORUM_DRIVER']->set_custom_field($id,'role',$role);
		$GLOBALS['FORUM_DRIVER']->set_custom_field($id,'fullname',$fullname);

		log_it('EDIT_STAFF',strval($id));
	}

	/**
	 * Add a member of staff.
	 *
	 * @param  MEMBER		The ID of the member to add as staff
	 */
	function _staff_add($id)
	{
		$sites=get_ocp_cpf('sites',$id);
		if ($sites!='') $sites.=', ';
		$sites.=substr(get_site_name(),0,200);
		$GLOBALS['FORUM_DRIVER']->set_custom_field($id,'sites',$sites);

		log_it('ADD_STAFF',strval($id));
	}

	/**
	 * Remove a member of staff.
	 *
	 * @param  MEMBER		The ID of the member to remove from the staff
	 */
	function _staff_remove($id)
	{
		$sites=get_ocp_cpf('sites',$id);

		// Lets try to cleanly remove it
		$sites=str_replace(', '.substr(get_site_name(),0,200),'',$sites);
		$sites=str_replace(','.substr(get_site_name(),0,200),'',$sites);
		$sites=str_replace(substr(get_site_name(),0,200).', ','',$sites);
		$sites=str_replace(substr(get_site_name(),0,200).',','',$sites);
		$sites=str_replace(substr(get_site_name(),0,200),'',$sites);
		if (substr($sites,0,2)==', ') $sites=substr($sites,2);

		$GLOBALS['FORUM_DRIVER']->set_custom_field($id,'sites',$sites);

		log_it('REMOVE_STAFF',strval($id));
	}

}


