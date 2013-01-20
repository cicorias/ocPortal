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
 * @package		pointstore
 */

class Hook_pointstore_pop3
{

	/**
	 * Standard pointstore item initialisation function.
	 */
	function init()
	{
	}

	/**
	 * Standard pointstore item "shop front" function.
	 *
	 * @return array			The "shop fronts"
	 */
	function info()
	{
		return array();
	}

	/**
	 * Standard pointstore item configuration save function.
	 */
	function save_config()
	{
		$pop3=post_param_integer('pop3',-1);
		if ($pop3!=-1)
		{
			$dpop3=post_param('dpop3');
			$GLOBALS['SITE_DB']->query_insert('prices',array('name'=>'pop3_'.$dpop3,'price'=>$pop3));
			log_it('POINTSTORE_ADD_MAIL_POP3',$dpop3);
		}
		$this->_do_price_mail();
	}

	/**
	 * Update an e-mail address from what was chosen in an interface; update or delete each price/cost/item
	 */
	function _do_price_mail()
	{
		$i=0;
		while (array_key_exists('pop3_'.strval($i),$_POST))
		{
			$price=post_param_integer('pop3_'.strval($i));
			$name='pop3_'.post_param('dpop3_'.strval($i));
			$name2='pop3_'.post_param('ndpop3_'.strval($i));
			if (post_param_integer('delete_pop3_'.strval($i),0)==1)
			{
				$GLOBALS['SITE_DB']->query_delete('prices',array('name'=>$name),'',1);
			} else
			{
				$GLOBALS['SITE_DB']->query_update('prices',array('price'=>$price,'name'=>$name2),array('name'=>$name),'',1);
			}

			$i++;
		}
	}

	/**
	 * Get fields for adding/editing one of these.
	 *
	 * @return tempcode		The fields
	 */
	function get_fields()
	{
		$fields=new ocp_tempcode();
		$fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'),do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'),'dpop3','',true));
		$fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'),do_lang_tempcode('_DESCRIPTION_MAIL_COST'),'pop3',NULL,true));
		return $fields;
	}

	/**
	 * Standard pointstore item configuration function.
	 *
	 * @return ?array		A tuple: list of [fields to shown, hidden fields], title for add form, add form (NULL: disabled)
	 */
	function config()
	{
		$rows=$GLOBALS['SITE_DB']->query('SELECT price,name FROM '.get_table_prefix().'prices WHERE name LIKE \''.db_encode_like('pop3_%').'\'');
		$out=array();
		foreach ($rows as $i=>$row)
		{
			$fields=new ocp_tempcode();
			$hidden=new ocp_tempcode();
			$domain=substr($row['name'],strlen('pop3_'));
			$hidden->attach(form_input_hidden('dpop3_'.strval($i),$domain));
			$fields->attach(form_input_line(do_lang_tempcode('MAIL_DOMAIN'),do_lang_tempcode('DESCRIPTION_MAIL_DOMAIN'),'ndpop3_'.strval($i),substr($row['name'],5),true));
			$fields->attach(form_input_integer(do_lang_tempcode('MAIL_COST'),do_lang_tempcode('DESCRIPTION_MAIL_COST',escape_html('pop3'),escape_html($domain)),'pop3_'.strval($i),$row['price'],true));
			$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('_GUID'=>'9e37f41f134eecae630bfbf32da7b9ec','TITLE'=>do_lang_tempcode('ACTIONS'))));
			$fields->attach(form_input_tick(do_lang_tempcode('DELETE'),do_lang_tempcode('DESCRIPTION_DELETE'),'delete_pop3_'.strval($i),false));
			$out[]=array($fields,$hidden,do_lang_tempcode('EDIT_POP3_DOMAIN'));
		}

		return array($out,do_lang_tempcode('ADD_NEW_POP3_DOMAIN'),$this->get_fields());
	}

	/**
	 * Standard pointstore introspection.
	 *
	 * @return tempcode		The UI
	 */
	function pop3info()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_POP3');

		$test=$GLOBALS['SITE_DB']->query_select_value_if_there('sales','details',array('memberid'=>get_member(),'purchasetype'=>'pop3'));
		if (is_null($test))
		{
			$quota=new ocp_tempcode();
			$activate_url=build_url(array('page'=>'_SELF','type'=>'newpop3','id'=>'pop3'),'_SELF');
			$activate=do_template('POINTSTORE_POP3_ACTIVATE',array('_GUID'=>'2af73c37855846947aae8935391154cf','ACTIVATE_URL'=>$activate_url,'INITIAL_QUOTA'=>integer_format(intval(get_option('initial_quota')))));
		} else
		{
			$activate=new ocp_tempcode();
			$quota_url=build_url(array('page'=>'_SELF','type'=>'buyquota','id'=>'pop3'),'_SELF');
			$quota=do_template('POINTSTORE_POP3_QUOTA',array('_GUID'=>'d0345bb481155e92aaee889cb742ab5a','MAX_QUOTA'=>integer_format(intval(get_option('max_quota'))),'QUOTA_URL'=>$quota_url));
		}

		return do_template('POINTSTORE_POP3_SCREEN',array('_GUID'=>'80a09c6bc30ab9d2821f12b77ee75ae8','TITLE'=>$title,'ACTIVATE'=>$activate,'QUOTA'=>$quota,'INITIAL_QUOTA'=>integer_format(intval(get_option('initial_quota')))));
	}

	/**
	 * Standard stage of pointstore item purchase.
	 *
	 * @return tempcode		The UI
	 */
	function newpop3()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_NEWPOP3');

		pointstore_handle_error_already_has('pop3');

		// What addresses are there?
		$member_id=get_member();
		$pointsleft=available_points($member_id); // the number of points this member has left
		$list=get_mail_domains('pop3_',$pointsleft);
		if ($list->is_empty())
		{
			return warn_screen($title,do_lang_tempcode('NO_POP3S'));
		}

		// Build up fields
		$fields=new ocp_tempcode();
		require_code('form_templates');
		$fields->attach(form_input_line(do_lang_tempcode('ADDRESS_DESIRED_STUB'),'','email-prefix',$GLOBALS['FORUM_DRIVER']->get_username(get_member()),true));
		$fields->attach(form_input_list(do_lang_tempcode('ADDRESS_DESIRED_DOMAIN'),'','esuffix',$list));
		$fields->attach(form_input_password(do_lang_tempcode('PASSWORD'),'','pass1',true));
		$fields->attach(form_input_password(do_lang_tempcode('CONFIRM_PASSWORD'),'','pass2',true));

		$javascript="
			var form=document.getElementById('pass1').form;
			form.old_submit=form.onsubmit;
			form.onsubmit=function()
				{
					if ((form.elements['pass1'].value!=form.elements['pass2'].value))
					{
						window.fauxmodal_alert('".php_addslashes(do_lang('PASSWORD_MISMATCH'))."');
						return false;
					}
					if (typeof form.old_submit!='undefined' && form.old_submit) return form.old_submit();
					return true;
				};
		";

		// Return template
		$newpop_url=build_url(array('page'=>'_SELF','type'=>'_newpop3','id'=>'pop3'),'_SELF');
		return do_template('FORM_SCREEN',array(
			'_GUID'=>'addf1563770845ba5fe4aaf2e60ca6fc',
			'JAVASCRIPT'=>$javascript,
			'HIDDEN'=>'',
			'TITLE'=>$title,
			'TEXT'=>paragraph(do_lang_tempcode('ADDRESSES_ABOUT')),
			'URL'=>$newpop_url,
			'SUBMIT_NAME'=>do_lang_tempcode('PURCHASE'),
			'FIELDS'=>$fields,
		));
	}

	/**
	 * Standard stage of pointstore item purchase.
	 *
	 * @return tempcode		The UI
	 */
	function _newpop3()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_NEWPOP3');

		// Getting User Information
		$member_id=get_member();
		$pointsleft=available_points($member_id);

		// So we don't need to call these big ugly names, again...
		$_suffix=post_param('esuffix');
		$prefix=post_param('email-prefix');
		$pass1=post_param('pass1');
		$pass2=post_param('pass2');

		// Which suffix have we chosen?
		$suffix='pop3_'.$_suffix;

		$_suffix_price=get_price($suffix);
		$points_after=$pointsleft-$_suffix_price;

		pointstore_handle_error_already_has('pop3');

		if (($points_after<0) && (!has_privilege(get_member(),'give_points_self')))
		{
			return warn_screen($title,do_lang_tempcode('NOT_ENOUGH_POINTS',escape_html($_suffix)));
		}

		// Password checking (to see if both 'passwords' are the same)
		if ($pass1!=$pass2)
		{
			return warn_screen($title,do_lang_tempcode('PASSWORD_MISMATCH'));
		}

		// Does the prefix contain valid characters?
		require_code('type_validation');
		if (!is_valid_email_address($prefix.'@'.$_suffix))
		{
			return warn_screen($title,do_lang_tempcode('INVALID_EMAIL_PREFIX'));
		}

		pointstore_handle_error_taken($prefix,$_suffix);

		// Return
		$proceed_url=build_url(array('page'=>'_SELF','type'=>'__newpop3','id'=>'pop3'),'_SELF');
		$keep=new ocp_tempcode();
		$keep->attach(form_input_hidden('prefix',$prefix));
		$keep->attach(form_input_hidden('suffix',$_suffix));
		$keep->attach(form_input_hidden('password',$pass1));
		return do_template('POINTSTORE_CONFIRM_SCREEN',array(
			'_GUID'=>'099ab9d87fb6e68d74de27e7d41d50c0',
			'MESSAGE'=>paragraph($prefix.'@'.$_suffix),
			'TITLE'=>$title,
			'ACTION'=>do_lang_tempcode('TITLE_NEWPOP3'),
			'KEEP'=>$keep,
			'COST'=>integer_format($_suffix_price),
			'POINTS_AFTER'=>integer_format($points_after),
			'PROCEED_URL'=>$proceed_url,
			'CANCEL_URL'=>build_url(array('page'=>'_SELF'),'_SELF'),
		));
	}

	/**
	 * Standard stage of pointstore item purchase.
	 *
	 * @return tempcode		The UI
	 */
	function __newpop3()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_NEWPOP3');

		$member_id=get_member();
		$pointsleft=available_points($member_id); // the number of points this member has left
		$time=time();

		// So we don't need to call these big ugly names, again...
		$prefix=post_param('prefix');
		$_suffix=post_param('suffix');
		$password=trim(post_param('password'));

		$suffix='pop3_'.$_suffix;
		$suffix_price=get_price($suffix);

		pointstore_handle_error_already_has('pop3');

		// If the price is more than we can afford...
		if (($suffix_price>$pointsleft) && (!has_privilege(get_member(),'give_points_self')))
		{
			return warn_screen($title,do_lang_tempcode('NOT_ENOUGH_POINTS',escape_html($_suffix)));
		}

		pointstore_handle_error_taken($prefix,$_suffix);

		// Add us to the database
		$sale_id=$GLOBALS['SITE_DB']->query_insert('sales',array('date_and_time'=>$time,'memberid'=>get_member(),'purchasetype'=>'pop3','details'=>$prefix,'details2'=>'@'.$_suffix),true);

		$mail_server=get_option('mail_server');
		$pop3_url=get_option('pop_url');
		$initial_quota=intval(get_option('initial_quota'));
		$login=$prefix.'@'.$_suffix;
		$email=$GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member());

		// Mail off the order form
		$encoded_reason=do_lang('TITLE_NEWPOP3');
		$message_raw=do_template('POINTSTORE_POP3_MAIL',array(
			'_GUID'=>'19022c49d0bdde39735245850d04fca7',
			'EMAIL'=>$email,
			'ENCODED_REASON'=>$encoded_reason,
			'LOGIN'=>$login,
			'QUOTA'=>integer_format($initial_quota),
			'MAIL_SERVER'=>$mail_server,
			'PASSWORD'=>$password,
			'PREFIX'=>$prefix,
			'SUFFIX'=>$_suffix,
			'POP3_URL'=>$pop3_url,
			'SUFFIX_PRICE'=>integer_format($suffix_price),
		));
		require_code('notifications');
		dispatch_notification('pointstore_request_pop3','pop3_'.strval($sale_id),do_lang('MAIL_REQUEST_POP3',NULL,NULL,NULL,get_site_default_lang()),$message_raw->evaluate(get_site_default_lang(),false),NULL,NULL,3,true);

		$text=do_lang_tempcode('ORDER_POP3_DONE',escape_html($prefix.'@'.$_suffix));
		return inform_screen($title,$text);
	}

	/**
	 * Standard stage of pointstore item purchase.
	 *
	 * @return tempcode		The UI
	 */
	function buyquota()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_QUOTA');

		$member_id=get_member();
		$pointsleft=available_points($member_id);
		$price=intval(get_option('quota'));
		$topamount=intval(get_option('max_quota'));

		if ($price==0) $topamount=$pointsleft; else $topamount=intval(round($pointsleft/$price));
		$details=$GLOBALS['SITE_DB']->query_select('sales',array('details','details2'),array('memberid'=>$member_id,'purchasetype'=>'pop3'),'',1);

		// If we don't own a POP3 account, stop right here.
		if (!array_key_exists(0,$details))
		{
			return warn_screen($title,do_lang_tempcode('NO_POP3'));
		}

		$prefix=$details[0]['details'];
		$suffix=$details[0]['details2'];

		// Screen
		$submit_name=do_lang_tempcode('TITLE_QUOTA');
		$post_url=build_url(array('page'=>'_SELF','type'=>'_buyquota','id'=>'pop3'),'_SELF');
		$text=do_template('POINTSTORE_QUOTA',array('_GUID'=>'1282fae968b4919bcd0ba1e3ca169fe8','POINTS_LEFT'=>integer_format($pointsleft),'PRICE'=>integer_format($price),'TOP_AMOUNT'=>integer_format($topamount),'EMAIL'=>$prefix.$suffix));
		require_code('form_templates');
		$fields=form_input_integer(do_lang_tempcode('QUOTA'),do_lang_tempcode('QUOTA_DESCRIPTION'),'quota',100,true);
		return do_template('FORM_SCREEN',array('_GUID'=>'1c82c713beaa03d1e3045e50295c722c','HIDDEN'=>'','URL'=>$post_url,'TITLE'=>$title,'FIELDS'=>$fields,'TEXT'=>$text,'SUBMIT_NAME'=>$submit_name));
	}

	/**
	 * Standard stage of pointstore item purchase.
	 *
	 * @return tempcode		The UI
	 */
	function _buyquota()
	{
		if (get_option('is_on_pop3_buy')=='0') return new ocp_tempcode();

		$title=get_screen_title('TITLE_QUOTA');

		$member_id=get_member();
		$pointsleft=available_points($member_id);
		$price=intval(get_option('quota'));
		$quota=post_param_integer('quota');

		$details=$GLOBALS['SITE_DB']->query_select('sales',array('details','details2'),array('memberid'=>$member_id,'purchasetype'=>'pop3'),'',1);
		$prefix=$details[0]['details'];
		$suffix=$details[0]['details2'];

		// If we don't own a POP3 account, stop right here.
		if (!array_key_exists(0,$details))
		{
			return warn_screen($title,do_lang_tempcode('NO_POP3'));
		}

		// Stop if we can't afford this much quota
		if ((($quota*$price)>$pointsleft)  && (!has_privilege(get_member(),'give_points_self')))
		{
			return warn_screen($title,do_lang_tempcode('CANT_AFFORD'));
		}

		// Mail off the order form
		$quota_url=get_option('quota_url');
		$_price=$quota*$price;
		$encoded_reason=do_lang('TITLE_QUOTA');
		$message_raw=do_template('POINTSTORE_QUOTA_MAIL',array('_GUID'=>'5a4e0bb5e53e6ccf8e57581c377557f4','ENCODED_REASON'=>$encoded_reason,'QUOTA'=>integer_format($quota),'EMAIL'=>$prefix.$suffix,'QUOTA_URL'=>$quota_url,'PRICE'=>integer_format($_price)));
		require_code('notifications');
		dispatch_notification('pointstore_request_quota','quota_'.uniqid(''),do_lang('MAIL_REQUEST_QUOTA',NULL,NULL,NULL,get_site_default_lang()),$message_raw->evaluate(get_site_default_lang(),false),NULL,NULL,3,true);

		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('ORDER_QUOTA_DONE'));
	}

}


