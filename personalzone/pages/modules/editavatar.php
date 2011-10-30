<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2011

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		ocf_member_avatars
 */

/**
 * Module page class.
 */
class Module_editavatar
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
		return array('misc'=>'EDIT_AVATAR');
	}
	
	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		if (get_forum_type()!='ocf') warn_exit(do_lang_tempcode('NO_OCF')); else ocf_require_all_forum_stuff();
		require_css('ocf');

		$member_id=get_param_integer('id',get_member());
		enforce_personal_access($member_id,NULL,'member_maintenance');

		if ($GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id,'m_password_compat_scheme')=='remote')
		{
			warn_exit(do_lang_tempcode('DISALLOWED_REMOTE_MEMBER_ACTION'));
		}

		$type=get_param('type','misc');
		if ($type=='misc') return $this->gui();
		if ($type=='actual') return $this->actual();
	
		return new ocp_tempcode();
	}
	
	/**
	 * The UI for editing a members avatar.
	 *
	 * @return tempcode	The UI.
	 */
	function gui()
	{
		$title=get_page_title('EDIT_AVATAR');
	
		/*if (get_base_url()!=get_forum_base_url())
		{
			require_code('site2');
			assign_refresh(str_replace(get_base_url(),get_forum_base_url(),get_self_url(true)));
			return do_template('REDIRECT_SCREEN',array('_GUID'=>'208d6426361023c9eef67d64b4bf87aa','URL'=>$url,'TITLE'=>$title,'TEXT'=>do_lang_tempcode('REDIRECTING')));
		}*/

		$member_id=get_param_integer('id',get_member());
	
		$avatar_url=$GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id,'m_avatar_url');

		require_javascript('javascript_multi');

		// UI fields
		$fields=new ocp_tempcode();
		require_code('form_templates');
		require_code('themes2');
		$ids=get_all_image_ids_type('ocf_default_avatars',true);
		$found_it=false;
		foreach ($ids as $id)
		{
			$pos=strpos($avatar_url,'/'.$id);
			$selected=($pos!==false);
			if ($selected) $found_it=true;
		}
		$hidden=new ocp_tempcode();
		if (has_specific_permission(get_member(),'own_avatars'))
		{
			$javascript='standardAlternateFields(\'file\',\'alt_url\',\'stock*\',true);';
			$fields->attach(form_input_upload(do_lang_tempcode('UPLOAD'),do_lang_tempcode('DESCRIPTION_UPLOAD'),'file',false,NULL,NULL,true,str_replace(' ','',get_option('valid_images'))));
			handle_max_file_size($hidden,'image');
			$fields->attach(form_input_line(do_lang_tempcode('ALT_FIELD',do_lang_tempcode('URL')),do_lang_tempcode('DESCRIPTION_ALTERNATE_URL'),'alt_url',$found_it?'':$avatar_url,false));
			$fields->attach(form_input_picture_choose_specific(do_lang_tempcode('ALT_FIELD',do_lang_tempcode('STOCK')),do_lang_tempcode('DESCRIPTION_ALTERNATE_STOCK'),'stock',$ids,$avatar_url,NULL,NULL,true));
		} else
		{
			$javascript='';
			$fields->attach(form_input_picture_choose_specific(do_lang_tempcode('STOCK'),'','stock',$ids,$avatar_url,NULL,NULL,true));
		}

		// Avatar
		if ($avatar_url!='')
		{
			if (url_is_local($avatar_url)) $avatar_url=get_complex_base_url($avatar_url).'/'.$avatar_url;
			$avatar=do_template('OCF_TOPIC_POST_AVATAR',array('_GUID'=>'50a5902f3ab7e384d9cf99577b222cc8','AVATAR'=>$avatar_url));
		} else
		{
			$avatar=do_lang_tempcode('NONE_EM');
		}

		$width=ocf_get_member_best_group_property($member_id,'max_avatar_width');
		$height=ocf_get_member_best_group_property($member_id,'max_avatar_height');

		$submit_name=do_lang_tempcode('SAVE');

		$post_url=build_url(array('page'=>'_SELF','type'=>'actual','id'=>$member_id,'uploading'=>1),'_SELF');

		return do_template('OCF_EDIT_AVATAR_SCREEN',array('_GUID'=>'dbdac6ca3bc752b54d2a24a4c6e69c7c','HIDDEN'=>'','MEMBER_ID'=>strval($member_id),'USERNAME'=>$GLOBALS['FORUM_DRIVER']->get_username($member_id),'TITLE'=>$title,'AVATAR'=>$avatar,'WIDTH'=>integer_format($width),'HEIGHT'=>integer_format($height),'FIELDS'=>$fields,'SUBMIT_NAME'=>$submit_name,'URL'=>$post_url,'JAVASCRIPT'=>$javascript));
	}

	/**
	 * The actualiser for editing a members avatar.
	 *
	 * @return tempcode	The UI.
	 */
	function actual()
	{
		$title=get_page_title('EDIT_AVATAR');
	
		$member_id=get_param_integer('id',get_member());
	
		breadcrumb_set_parents(array(array('_SELF:_SELF:misc:'.strval($member_id),do_lang_tempcode('EDIT_AVATAR'))));

		require_code('uploads');
		if (has_specific_permission(get_member(),'own_avatars'))
		{
			if (((!array_key_exists('file',$_FILES)) || (!is_uploaded_file($_FILES['file']['tmp_name']))) && (!is_swf_upload())) // No upload -> URL or stock or none
			{
				$urls=array();
				$stock=post_param('alt_url','');
				if ($stock=='') // No URL -> Stock or none
				{
					$stock=post_param('stock',NULL);
					if (!is_null($stock)) // Stock
					{
						$urls[0]=find_theme_image($stock,false,true);
					} else $urls[0]=''; // None
				} else
				{
					if ((url_is_local($stock)) && (!$GLOBALS['FORUM_DRIVER']->is_super_admin(get_member())))
					{
						$old=$GLOBALS['FORUM_DB']->query_value('f_members','m_avatar_url',array('id'=>$member_id));
						if ($old!=$stock) access_denied('ASSOCIATE_EXISTING_FILE');
					}
					$urls[0]=$stock; // URL
				}
			} else // Upload
			{
				// We have chosen an upload. Note that we will not be looking at alt_url at this point, even though it is specified below for canonical reasons
				$urls=get_url('alt_url','file',file_exists(get_custom_file_base().'/uploads/avatars')?'uploads/avatars':'uploads/ocf_avatars',0,OCP_UPLOAD_IMAGE,false);
				if (((get_base_url()!=get_forum_base_url()) || ((array_key_exists('on_msn',$GLOBALS['SITE_INFO'])) && ($GLOBALS['SITE_INFO']['on_msn']=='1'))) && ($urls[0]!='') && (url_is_local($urls[0]))) $urls[0]=get_custom_base_url().'/'.$urls[0];
			}

			$avatar_url=$urls[0];
		} else
		{
			$stock=post_param('stock');
			$avatar_url=find_theme_image($stock,false,true);
		}

		require_code('ocf_members_action');
		require_code('ocf_members_action2');
		ocf_member_choose_avatar($avatar_url,$member_id);
	
		breadcrumb_set_self(do_lang_tempcode('DONE'));

		// Send back to start
		$url=$GLOBALS['FORUM_DRIVER']->member_profile_link($member_id,false,true);
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

}


