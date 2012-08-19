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
 * @package		core_ocf
 */

/**
 * Give error if OCF-joining is not possible on this site.
 */
function check_joining_allowed()
{
	if (get_forum_type()!='ocf') warn_exit(do_lang_tempcode('NO_OCF'));

	// Check RBL's/stopforumspam
	$spam_check_level=get_option('spam_check_level',true);
	if (($spam_check_level==='EVERYTHING') || ($spam_check_level==='ACTIONS') || ($spam_check_level==='GUESTACTIONS') || ($spam_check_level==='JOINING'))
	{
		require_code('antispam');
		check_rbls();
		check_stopforumspam();
	}

	global $LDAP_CONNECTION;
	if ((!is_null($LDAP_CONNECTION)) && (get_option('ldap_allow_joining',true)==='0'))
		warn_exit(do_lang_tempcode('JOIN_DISALLOW'));
}

/**
 * Get the join form.
 *
 * @param  tempcode		URL to direct to
 * @param  boolean		Whether to handle CAPTCHA (if enabled at all)
 * @param  boolean		Whether to ask for intro messages (if enabled at all)
 * @param  boolean		Whether to check for invites (if enabled at all)
 * @param  boolean		Whether to check email-address restrictions (if enabled at all)
 * @return array			A tuple: Necessary Javascript code, the form
 */
function ocf_join_form($url,$captcha_if_enabled=true,$intro_message_if_enabled=true,$invites_if_enabled=true,$one_per_email_address_if_enabled=true)
{
	ocf_require_all_forum_stuff();

	require_css('ocf');
	require_code('ocf_members_action');
	require_code('ocf_members_action2');
	require_code('form_templates');

	$hidden=new ocp_tempcode();
	$hidden->attach(build_keep_post_fields());

	$groups=ocf_get_all_default_groups(true);
	$additional_group=either_param_integer('additional_group',-1);
	if (($additional_group!=-1) && (!in_array($additional_group,$groups)))
	{
		// Check security
		$test=$GLOBALS['FORUM_DB']->query_select_value('f_groups','g_is_presented_at_install',array('id'=>$additional_group));
		if ($test==1)
		{
			$groups=ocf_get_all_default_groups(false);
			$hidden=form_input_hidden('additional_group',strval($additional_group));
			$groups[]=$additional_group;
		}
	}

	list($fields,$_hidden)=ocf_get_member_fields(true,NULL,$groups);
	$hidden->attach($_hidden);

	if ($intro_message_if_enabled)
	{
		$forum_id=get_option('intro_forum_id');
		if ($forum_id!='')
		{
			$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('TITLE'=>do_lang_tempcode('INTRODUCE_YOURSELF'))));
			$fields->attach(form_input_line(do_lang_tempcode('TITLE'),'','intro_title',do_lang('INTRO_POST_DEFAULT','___'),false));
			$fields->attach(form_input_text_comcode(do_lang_tempcode('POST_COMMENT'),do_lang_tempcode('DESCRIPTION_INTRO_POST'),'intro_post','',false));
		}
	}

	$text=do_lang_tempcode('ENTER_PROFILE_DETAILS');

	if ($captcha_if_enabled)
	{
		if (addon_installed('captcha'))
		{
			require_code('captcha');
			if (use_captcha())
			{
				$fields->attach(form_input_captcha());
				$text->attach(' ');
				$text->attach(do_lang_tempcode('FORM_TIME_SECURITY'));
			}
		}
	}

	$submit_name=do_lang_tempcode('PROCEED');

	require_javascript('javascript_ajax');

	$script=find_script('username_check');
	$javascript="
		var form=document.getElementById('username').form;
		form.elements['username'].onchange=function()
		{
			if (form.elements['intro_title'])
				form.elements['intro_title'].value='".addslashes(do_lang('INTRO_POST_DEFAULT'))."'.replace(/\{1\}/g,form.elements['username'].value);
		}
		form.old_submit=form.onsubmit;
		form.onsubmit=function()
			{
				if ((form.elements['email_address_confirm']) && (form.elements['email_address_confirm'].value!=form.elements['email_address'].value))
				{
					window.fauxmodal_alert('".php_addslashes(do_lang('EMAIL_ADDRESS_MISMATCH'))."');
					return false;
				}
				if ((form.elements['password_confirm']) && (form.elements['password_confirm'].value!=form.elements['password'].value))
				{
					window.fauxmodal_alert('".php_addslashes(do_lang('PASSWORD_MISMATCH'))."');
					return false;
				}
				document.getElementById('submit_button').disabled=true;
				var url='".addslashes($script)."?username='+window.encodeURIComponent(form.elements['username'].value);
				if (!do_ajax_field_test(url,'password='+window.encodeURIComponent(form.elements['password'].value)))
				{
					document.getElementById('submit_button').disabled=false;
					return false;
				}
	";
	$script=find_script('snippet');
	if ($invites_if_enabled)
	{
		if (get_option('is_on_invites')=='1')
		{
			$javascript.="
					url='".addslashes($script)."?snippet=invite_missing&name='+window.encodeURIComponent(form.elements['email_address'].value);
					if (!do_ajax_field_test(url))
					{
						document.getElementById('submit_button').disabled=false;
						return false;
					}
			";
		}
	}
	if ($one_per_email_address_if_enabled)
	{
		if (get_option('one_per_email_address')=='1')
		{
			$javascript.="
					url='".addslashes($script)."?snippet=email_exists&name='+window.encodeURIComponent(form.elements['email_address'].value);
					if (!do_ajax_field_test(url))
					{
						document.getElementById('submit_button').disabled=false;
						return false;
					}
			";
		}
	}
	if ($captcha_if_enabled)
	{
		if (addon_installed('captcha'))
		{
			require_code('captcha');
			if (use_captcha())
			{
				$javascript.="
						url='".addslashes($script)."?snippet=captcha_wrong&name='+window.encodeURIComponent(form.elements['captcha'].value);
						if (!do_ajax_field_test(url))
						{
							document.getElementById('submit_button').disabled=false;
							return false;
						}
				";
			}
		}
	}
	$javascript.="
				document.getElementById('submit_button').disabled=false;
				if (typeof form.old_submit!='undefined' && form.old_submit) return form.old_submit();
				return true;
			};
	";

	$form=do_template('FORM',array('TEXT'=>'','HIDDEN'=>$hidden,'FIELDS'=>$fields,'SUBMIT_NAME'=>$submit_name,'URL'=>$url));

	return array($javascript,$form);
}

/**
 * Actualise the join form.
 *
 * @param  boolean		Whether to handle CAPTCHA (if enabled at all)
 * @param  boolean		Whether to ask for intro messages (if enabled at all)
 * @param  boolean		Whether to check for invites (if enabled at all)
 * @param  boolean		Whether to check email-address restrictions (if enabled at all)
 * @param  boolean		Whether to require staff confirmation (if enabled at all)
 * @param  boolean		Whether to force email address validation (if enabled at all)
 * @param  boolean		Whether to do COPPA checks (if enabled at all)
 * @param  boolean		Whether to instantly log the user in
 * @return array			A tuple: Messages to show (currently nothing else in tuple)
 */
function ocf_join_actual($captcha_if_enabled=true,$intro_message_if_enabled=true,$invites_if_enabled=true,$one_per_email_address_if_enabled=true,$confirm_if_enabled=true,$validate_if_enabled=true,$coppa_if_enabled=true,$instant_login=true)
{
	ocf_require_all_forum_stuff();

	require_css('ocf');
	require_code('ocf_members_action');
	require_code('ocf_members_action2');

	// Read in data
	$username=trim(post_param('username'));
	ocf_check_name_valid($username,NULL,NULL,true); // Adjusts username if needed
	$password=trim(post_param('password'));
	$password_confirm=trim(post_param('password_confirm'));
	if ($password!=$password_confirm) warn_exit(make_string_tempcode(escape_html(do_lang('PASSWORD_MISMATCH'))));
	$confirm_email_address=post_param('email_address_confirm',NULL);
	$email_address=trim(post_param('email_address'));
	if (!is_null($confirm_email_address))
	{
		if (trim($confirm_email_address)!=$email_address) warn_exit(make_string_tempcode(escape_html(do_lang('EMAIL_ADDRESS_MISMATCH'))));
	}
	require_code('type_validation');
	if (!is_valid_email_address($email_address)) warn_exit(do_lang_tempcode('INVALID_EMAIL_ADDRESS'));
	if ($invites_if_enabled) // code branch also triggers general tracking of referrals
	{
		if (get_option('is_on_invites')=='1')
		{
			$test=$GLOBALS['FORUM_DB']->query_select_value_if_there('f_invites','i_inviter',array('i_email_address'=>$email_address,'i_taken'=>0));
			if (is_null($test)) warn_exit(do_lang_tempcode('NO_INVITE'));
		}

		$GLOBALS['FORUM_DB']->query_update('f_invites',array('i_taken'=>1),array('i_email_address'=>$email_address,'i_taken'=>0),'',1);
	}
	$dob_day=post_param_integer('dob_day',NULL);
	$dob_month=post_param_integer('dob_month',NULL);
	$dob_year=post_param_integer('dob_year',NULL);
	$reveal_age=post_param_integer('reveal_age',0);
	$timezone=post_param('timezone',get_users_timezone());
	$language=post_param('language',get_site_default_lang());
	$allow_emails=post_param_integer('allow_emails',0);
	$allow_emails_from_staff=post_param_integer('allow_emails_from_staff',0);
	$groups=ocf_get_all_default_groups(true);
	$additional_group=post_param_integer('additional_group',-1);
	if (($additional_group!=-1) && (!in_array($additional_group,$groups)))
	{
		// Check security
		$test=$GLOBALS['FORUM_DB']->query_select_value('f_groups','g_is_presented_at_install',array('id'=>$additional_group));
		if ($test==1)
		{
			$groups=ocf_get_all_default_groups(false);
			$groups[]=$additional_group;
		} else $additional_group=-1;
	} else $additional_group=-1;
	if ($additional_group==-1)
	{
		$test=$GLOBALS['FORUM_DB']->query_select_value_if_there('f_groups','id',array('g_is_presented_at_install'=>1));
		if (!is_null($test)) $additional_group=$test;
	}
	$custom_fields=ocf_get_all_custom_fields_match($groups,NULL,NULL,NULL,NULL,NULL,NULL,0,true);
	$actual_custom_fields=ocf_read_in_custom_fields($custom_fields);

	// Check that the given address isn't already used (if one_per_email_address on)
	$member_id=NULL;
	if ($one_per_email_address_if_enabled)
	{
		if (get_option('one_per_email_address')=='1')
		{
			$test=$GLOBALS['FORUM_DB']->query_select('f_members',array('id','m_username'),array('m_email_address'=>$email_address),'',1);
			if (array_key_exists(0,$test))
			{
				if ($test[0]['m_username']!=$username)
				{
					$reset_url=build_url(array('page'=>'lostpassword','email_address'=>$email_address),get_module_zone('lostpassword'));
					warn_exit(do_lang_tempcode('EMAIL_ADDRESS_IN_USE',escape_html(get_site_name()),escape_html($reset_url->evaluate())));
				}
				$member_id=$test[0]['id'];
			}
		}
	}

	// Check RBL's/stopforumspam
	$spam_check_level=get_option('spam_check_level',true);
	if (($spam_check_level==='EVERYTHING') || ($spam_check_level==='ACTIONS') || ($spam_check_level==='GUESTACTIONS') || ($spam_check_level==='JOINING'))
	{
		require_code('antispam');
		check_rbls();
		check_stopforumspam($username,$email_address);
	}

	if ($captcha_if_enabled)
	{
		if (addon_installed('captcha'))
		{
			require_code('captcha');
			enforce_captcha();
		}
	}

	if (addon_installed('ldap'))
	{
		require_code('ocf_ldap');
		if (ocf_is_ldap_member_potential($username))
			warn_exit(do_lang_tempcode('DUPLICATE_JOIN_AUTH'));
	}

	// Add member
	$skip_confirm=(get_option('skip_email_confirm_join')=='1');
	if (!$confirm_if_enabled) $skip_confirm=true;
	$validated_email_confirm_code=$skip_confirm?'':strval(mt_rand(1,32000));
	$require_new_member_validation=get_option('require_new_member_validation')=='1';
	if (!$validate_if_enabled) $require_new_member_validation=false;
	$coppa=(get_option('is_on_coppa')=='1') && (utctime_to_usertime(time()-mktime(0,0,0,$dob_month,$dob_day,$dob_year))/31536000.0<13.0);
	if (!$coppa_if_enabled) $coppa=false;
	$validated=($require_new_member_validation || $coppa)?0:1;
	if (is_null($member_id)) $member_id=ocf_make_member($username,$password,$email_address,$groups,$dob_day,$dob_month,$dob_year,$actual_custom_fields,$timezone,($additional_group!=-1)?$additional_group:NULL,$validated,time(),time(),'',NULL,'',0,(get_option('default_preview_guests')=='1')?1:0,$reveal_age,'','','',1,(get_value('no_auto_notifications')==='1')?0:1,$language,$allow_emails,$allow_emails_from_staff,get_ip_address(),$validated_email_confirm_code,true,'','');

	// Send confirm mail
	if (!$skip_confirm)
	{
		$zone=get_module_zone('join');
		if ($zone!='') $zone.='/';
		$_url=build_url(array('page'=>'join','type'=>'step4','email'=>$email_address,'code'=>$validated_email_confirm_code),$zone,NULL,false,false,true);
		$url=$_url->evaluate();
		$_url_simple=build_url(array('page'=>'join','type'=>'step4'),$zone,NULL,false,false,true);
		$url_simple=$_url_simple->evaluate();
		$redirect=get_param('redirect','');
		if ($redirect!='') $url.='&redirect='.ocp_url_encode($redirect);
		$message=do_lang('OCF_SIGNUP_TEXT',comcode_escape(get_site_name()),comcode_escape($url),array($url_simple,$email_address,strval($validated_email_confirm_code)),$language);
		require_code('mail');
		if (!$coppa) mail_wrap(do_lang('CONFIRM_EMAIL_SUBJECT',get_site_name(),NULL,NULL,$language),$message,array($email_address),$username);
	}

	// Send COPPA mail
	if ($coppa)
	{
		$fields_done=do_lang('THIS_WITH_COMCODE',do_lang('USERNAME'),$username)."\n\n";
		foreach ($custom_fields as $custom_field)
		{
			if ($custom_field['cf_type']!='upload')
			{
				$fields_done.=do_lang('THIS_WITH_COMCODE',$custom_field['trans_name'],post_param('custom_'.$custom_field['id'].'_value'))."\n";
			}
		}
		$_privacy_url=build_url(array('page'=>'privacy'),'_SEARCH',NULL,false,false,true);
		$privacy_url=$_privacy_url->evaluate();
		$message=do_lang('COPPA_MAIL',comcode_escape(get_option('site_name')),comcode_escape(get_option('privacy_fax')),array(comcode_escape(get_option('privacy_postal_address')),comcode_escape($fields_done),comcode_escape($privacy_url)),$language);
		require_code('mail');
		mail_wrap(do_lang('COPPA_JOIN_SUBJECT',$username,get_site_name(),NULL,$language),$message,array($email_address),$username);
	}

	// Send 'validate this member' mail
	if ($require_new_member_validation)
	{
		require_code('notifications');
		$_validation_url=build_url(array('page'=>'members','type'=>'view','id'=>$member_id),get_module_zone('members'),NULL,false,false,true,'tab__edit');
		$validation_url=$_validation_url->evaluate();
		$message=do_lang('VALIDATE_NEW_MEMBER_MAIL',comcode_escape($username),comcode_escape($validation_url),comcode_escape(strval($member_id)),get_site_default_lang());
		dispatch_notification('ocf_member_needs_validation',NULL,do_lang('VALIDATE_NEW_MEMBER_SUBJECT',$username,NULL,NULL,get_site_default_lang()),$message,NULL,A_FROM_SYSTEM_PRIVILEGED);
	}

	// Intro post
	if ($intro_message_if_enabled)
	{
		$forum_id=get_option('intro_forum_id');
		if ($forum_id!='')
		{
			if (!is_numeric($forum_id)) $forum_id=strval($GLOBALS['FORUM_DB']->query_select_value('f_forums','id',array('f_name'=>$forum_id)));

			$intro_title=post_param('intro_title','');
			$intro_post=post_param('intro_post','');
			if ($intro_post!='')
			{
				require_code('ocf_topics_action');
				if ($intro_title=='') $intro_title=do_lang('INTRO_POST_DEFAULT',$username);
				$topic_id=ocf_make_topic(intval($forum_id));
				require_code('ocf_posts_action');
				ocf_make_post($topic_id,$intro_title,$intro_post,0,true,NULL,0,NULL,NULL,NULL,$member_id);
			}
		}
	}

	// Alert user to situation
	$message=new ocp_tempcode();
	if ($coppa)
	{
		if (!$skip_confirm) $message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL'));
		$message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL_COPPA'));
	}
	elseif ($require_new_member_validation)
	{
		if (!$skip_confirm) $message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL'));
		$message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL_VALIDATED',escape_html(get_custom_base_url())));
	}
	elseif ($skip_confirm)
	{
		if ($instant_login) // Automatic instant log in
		{
			require_code('users_active_actions');
			handle_active_login($username);
		} else // Invite them to explicitly instant log in
		{
			$_login_url=build_url(array('page'=>'login','redirect'=>get_param('redirect',NULL)),get_module_zone('login'));
			$login_url=$_login_url->evaluate();
			$message->attach(do_lang_tempcode('OCF_LOGIN_INSTANT',escape_html($login_url)));
		}
	} else
	{
		if (!$skip_confirm) $message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL'));
		$message->attach(do_lang_tempcode('OCF_WAITING_CONFIRM_MAIL_INSTANT'));
	}
	$message=protect_from_escaping($message);

	return array($message);
}
