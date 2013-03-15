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
 * @package		backup
 */

/**
 * Module page class.
 */
class Module_admin_backup
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
		$info['version']=3;
		$info['locked']=false;
		return $info;
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'BACKUP');
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		delete_value('last_backup');
		delete_value('backup_max_size');
		delete_value('backup_schedule_time');
		delete_value('backup_recurrance_days');
		delete_value('backup_max_size');
		delete_value('backup_b_type');
		delete_config_option('backup_time');
		delete_config_option('backup_time');
		delete_config_option('backup_server_hostname');
		delete_config_option('backup_server_port');
		delete_config_option('backup_server_user');
		delete_config_option('backup_server_password');
		delete_config_option('backup_server_path');
		delete_config_option('backup_overwrite');

		//deldir_contents(get_custom_file_base().'/exports/backups',true);
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if ((is_null($upgrade_from)) || ($upgrade_from<3))
		{
			// Have to be careful here, as we had a bug before where we added these options and forgot to properly handle the upgrade. So the version number is down on some upgraded v3 installs even though the options may be there)
			if (is_null(get_option('backup_time',true))) add_config_option('BACKUP_REGULARITY','backup_time','integer','return \'168\';','ADMIN','CHECK_LIST',1);
			if (is_null(get_option('backup_server_hostname',true))) add_config_option('BACKUP_SERVER_HOSTNAME','backup_server_hostname','line','return \'\';','FEATURE','BACKUP');
			if (is_null(get_option('backup_server_port',true))) add_config_option('BACKUP_SERVER_PORT','backup_server_port','integer','return \'21\';','FEATURE','BACKUP');
			if (is_null(get_option('backup_server_user',true))) add_config_option('BACKUP_SERVER_USER','backup_server_user','line','return \'\';','FEATURE','BACKUP');
			if (is_null(get_option('backup_server_password',true))) add_config_option('BACKUP_SERVER_PASSWORD','backup_server_password','line','return \'\';','FEATURE','BACKUP');
			if (is_null(get_option('backup_server_path',true))) add_config_option('BACKUP_SERVER_PATH','backup_server_path','line','return \'\';','FEATURE','BACKUP');
			if (is_null(get_option('backup_overwrite',true))) add_config_option('BACKUP_OVERWRITE','backup_overwrite','tick','return \'0\';','FEATURE','BACKUP');
		}
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('backups');

		$GLOBALS['HELPER_PANEL_PIC']='pagepics/backups';
		$GLOBALS['HELPER_PANEL_TUTORIAL']='tut_backup';
		$GLOBALS['HELPER_PANEL_TEXT']=comcode_lang_string('DOC_BACKUPS_2');

		if (get_file_base()!=get_custom_file_base()) warn_exit(do_lang_tempcode('SHARED_INSTALL_PROHIBIT'));

		decache('main_staff_checklist');

		require_code('backup');

		$type=get_param('type','misc');

		if ($type=='make_backup') return $this->make_backup();
		if ($type=='confirm_delete') return $this->confirm_delete();
		if ($type=='delete') return $this->delete();
		if ($type=='misc') return $this->backup_interface();

		return new ocp_tempcode();
	}

	/**
	 * The UI to do a backup.
	 *
	 * @return tempcode		The UI
	 */
	function backup_interface()
	{
		$title=get_page_title('BACKUP');

		require_javascript('javascript_ajax');

		$last_backup=intval(get_value('last_backup'));
		if ($last_backup==0)
		{
			$text=do_lang_tempcode('NO_LAST_BACKUP');
		}
		elseif (date('Y/m/d',utctime_to_usertime($last_backup))==date('Y/m/d',utctime_to_usertime()))
		{
			$text=do_lang_tempcode('LAST_BACKUP_TODAY');
		}
		elseif (date('Y/m/d',utctime_to_usertime($last_backup))==date('Y/m/d',utctime_to_usertime(time()-60*60*24)))
		{
			$text=do_lang_tempcode('LAST_BACKUP_YESTERDAY');
		} else
		{
			$text=do_lang_tempcode('LAST_BACKUP',
				integer_format(
					intval(round(
						(time()-$last_backup)/(60*60*24)
					))
				)
			);
		}

		$url=build_url(array('page'=>'_SELF','type'=>'make_backup'),'_SELF');

		$max_size=intval(get_value('backup_max_size'));
		if ($max_size==0) $max_size=100;

		require_code('form_templates');
		$content=new ocp_tempcode();
		$content->attach(form_input_radio_entry('b_type','full',true,do_lang_tempcode('FULL_BACKUP')));
		$content->attach(form_input_radio_entry('b_type','incremental',false,do_lang_tempcode('INCREMENTAL_BACKUP')));
		$content->attach(form_input_radio_entry('b_type','sql',false,do_lang_tempcode('SQL_BACKUP')));
		$fields=form_input_radio(do_lang_tempcode('TYPE'),do_lang_tempcode('BACKUP_TYPE'),'b_type',$content);
		$fields->attach(form_input_integer(do_lang_tempcode('MAXIMUM_SIZE_INCLUSION'),do_lang_tempcode('MAX_FILE_SIZE'),'max_size',$max_size,false));
		if (addon_installed('calendar'))
		{
			$fields->attach(form_input_date__scheduler(do_lang_tempcode('SCHEDULE_TIME'),do_lang_tempcode('DESCRIPTION_SCHEDULE_TIME'),'schedule',true,true,true));
			$_recurrence_days=get_value('backup_recurrance_days');
			$recurrance_days=is_null($_recurrence_days)?NULL:intval($_recurrence_days);
			if (cron_installed()) $fields->attach(form_input_integer(do_lang_tempcode('RECURRANCE_DAYS'),do_lang_tempcode('DESCRIPTION_RECURRANCE_DAYS'),'recurrance_days',$recurrance_days,false));
		}

		$javascript='';
		if (addon_installed('calendar'))
		{
			if (cron_installed()) $javascript='var d_ob=[document.getElementById(\'schedule_day\'),document.getElementById(\'schedule_month\'),document.getElementById(\'schedule_year\'),document.getElementById(\'schedule_hour\'),document.getElementById(\'schedule_minute\')]; var hide_func=function () { document.getElementById(\'recurrance_days\').disabled=((d_ob[0].selectedIndex+d_ob[1].selectedIndex+d_ob[2].selectedIndex+d_ob[3].selectedIndex+d_ob[4].selectedIndex)>0); }; d_ob[0].onchange=hide_func; d_ob[1].onchange=hide_func; d_ob[2].onchange=hide_func; d_ob[3].onchange=hide_func; d_ob[4].onchange=hide_func; hide_func();';
		}

		$form=do_template('FORM',array('_GUID'=>'64ae569b2cce398e89d1b4167f116193','HIDDEN'=>'','JAVASCRIPT'=>$javascript,'TEXT'=>'','FIELDS'=>$fields,'SUBMIT_NAME'=>do_lang_tempcode('BACKUP'),'URL'=>$url));

		$results=$this->get_results();

		return do_template('BACKUP_LAUNCH_SCREEN',array('_GUID'=>'26a82a0627632db79b35055598de5d23','TITLE'=>$title,'TEXT'=>$text,'RESULTS'=>$results,'FORM'=>$form));
	}

	/**
	 * Helper function to find information about past backups.
	 *
	 * @return tempcode		The UI
	 */
	function get_results()
	{
		// Find all files in the incoming directory
		$path=get_custom_file_base().'/exports/backups/';
		if (!file_exists($path))
		{
			mkdir($path,0777);
			fix_permissions($path,0777);
			sync_file($path);
		}
		$handle=opendir($path);
		$entries=array();
		while (false!==($file=readdir($handle)))
		{
			if ((!is_dir($path.$file)) && ((get_file_extension($file)=='tar') || (get_file_extension($file)=='txt') || (get_file_extension($file)=='gz') || (get_file_extension($file)=='')) && (is_file($path.$file)))
			{
				$entries[]=array('file'=>$file,'size'=>filesize($path.$file),'mtime'=>filemtime($path.$file));
			}
		}
		closedir($handle);
		global $M_SORT_KEY;
		$M_SORT_KEY='mtime';
		uasort($entries,'multi_sort');

		if (count($entries)!=0)
		{
			require_code('templates_table_table');
			$header_row=table_table_header_row(array(do_lang_tempcode('FILENAME'),do_lang_tempcode('TYPE'),do_lang_tempcode('SIZE'),do_lang_tempcode('DATE_TIME'),new ocp_tempcode()));

			$rows=new ocp_tempcode();
			foreach ($entries as $entry)
			{
				$delete_url=build_url(array('page'=>'_SELF','type'=>'confirm_delete','file'=>$entry['file']),'_SELF');
				$link=get_custom_base_url().'/exports/backups/'.$entry['file'];

				$actions=do_template('TABLE_TABLE_ACTION_DELETE_ENTRY',array('_GUID'=>'23a8b5d5d345d8fdecc74b01fe5a9042','NAME'=>$entry['file'],'URL'=>$delete_url));

				$type=do_lang_tempcode('UNKNOWN');
				switch (get_file_extension($entry['file']))
				{
					case 'gz':
						$type=do_lang_tempcode('BACKUP_FILE_COMPRESSED');
						break;
					case 'tar':
						$type=do_lang_tempcode('BACKUP_FILE_UNCOMPRESSED');
						break;
					case 'txt':
						$type=do_lang_tempcode('BACKUP_FILE_LOG');
						break;
					case '':
						$type=do_lang_tempcode('BACKUP_FILE_UNFINISHED');
						break;
				}

				$rows->attach(table_table_row(array(hyperlink($link,escape_html($entry['file'])),$type,clean_file_size($entry['size']),get_timezoned_date($entry['mtime']),$actions)));
			}

			$files=do_template('TABLE_TABLE',array('_GUID'=>'726070efa71843236e975d87d4a17dae','HEADER_ROW'=>$header_row,'ROWS'=>$rows));

		} else $files=new ocp_tempcode();

		return $files;
	}

	/**
	 * The UI to confirm deletion of a backup file.
	 *
	 * @return tempcode		The UI
	 */
	function confirm_delete()
	{
		$title=get_page_title('DELETE');

		$file=get_param('file');

		$preview=do_lang_tempcode('CONFIRM_DELETE',escape_html($file));
		$url=build_url(array('page'=>'_SELF','type'=>'delete'),'_SELF');

		$fields=form_input_hidden('file',$file);

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('BACKUP'))));
		breadcrumb_set_self(do_lang_tempcode('DELETE'));

		return do_template('CONFIRM_SCREEN',array('_GUID'=>'fa69bb63385525921c75954c03a3aa43','TITLE'=>$title,'PREVIEW'=>$preview,'URL'=>$url,'FIELDS'=>$fields));
	}

	/**
	 * The actualiser to delete a backup file.
	 *
	 * @return tempcode		The UI
	 */
	function delete()
	{
		$title=get_page_title('DELETE');

		$file=post_param('file');

		$path=get_custom_file_base().'/exports/backups/'.filter_naughty($file);
		if (!@unlink($path))
		{
			warn_exit(do_lang_tempcode('WRITE_ERROR',escape_html($path)));
		}
		sync_file('exports/backups/'.$file);

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * The actualiser to start a backup.
	 *
	 * @return tempcode		The UI
	 */
	function make_backup()
	{
		$title=get_page_title('BACKUP');

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('BACKUP'))));
		breadcrumb_set_self(do_lang_tempcode('START'));

		$b_type=post_param('b_type','full');
		if ($b_type=='full')
		{
			$file='Backup_full_'.date('Y-m-d',utctime_to_usertime()).'__'.uniqid(''); // The last bit is unfortunate, but we need to stop URL guessing
			/*if (
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file)) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.txt')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.tar')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.gz'))
				)
				$file='Backup_full_'.uniqid('');*/
		}
		elseif ($b_type=='incremental')
		{
			$file='Backup_incremental'.date('Y-m-d',utctime_to_usertime()).'__'.uniqid(''); // The last bit is unfortunate, but we need to stop URL guessing
			/*if (
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file)) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.txt')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.tar')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.gz'))
				)
				$file='Backup_incremental_'.uniqid('');*/
		}
		elseif ($b_type=='sql')
		{
			$file='Backup_database'.date('Y-m-d',utctime_to_usertime()).'__'.uniqid(''); // The last bit is unfortunate, but we need to stop URL guessing
			/*if (
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file)) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.txt')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.tar')) ||
				 (file_exists(get_custom_file_base().'/exports/backups/'.$file.'.gz'))
				)
				$file='Backup_database_'.uniqid('');*/
		}
		else exit();

		$max_size=post_param_integer('max_size',0);
		if (($max_size==0) || (!is_numeric($max_size))) $max_size=1000000000;

		if (addon_installed('calendar'))
		{
			$schedule=get_input_date('schedule');
			if (!is_null($schedule))
			{
				set_value('backup_schedule_time',strval($schedule));
				set_value('backup_recurrance_days',strval(post_param_integer('recurrance_days',0)));
				set_value('backup_max_size',strval($max_size));
				set_value('backup_b_type',$b_type);
				return inform_screen($title,do_lang_tempcode('SUCCESSFULLY_SCHEDULED_BACKUP'));
			}
		}

		$instant=get_param_integer('keep_backup_instant',0); // Toggle this to true when debugging
		$max_time=intval(round(floatval(ini_get('max_execution_time'))/1.5));
		if ($max_time<60*4)
		{
			if (function_exists('set_time_limit')) @set_time_limit(0) OR warn_exit(do_lang_tempcode('SAFE_MODE'));
		}
		if ($instant==1)
		{
			make_backup_2($file,$b_type,$max_size);
		} else
		{
			global $MB2_FILE,$MB2_B_TYPE,$MB2_MAX_SIZE;
			$MB2_FILE=$file;
			$MB2_B_TYPE=$b_type;
			$MB2_MAX_SIZE=$max_size;
			@ignore_user_abort(true);
			register_shutdown_function('make_backup_2');
		}

		$url=build_url(array('page'=>'_SELF'),'_SELF');
		redirect_screen($title,$url,do_lang_tempcode('BACKUP_INFO_1',$file));
		return new ocp_tempcode();
	}

}


