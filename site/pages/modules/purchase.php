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
 * @package		ecommerce
 */

/**
 * Module page class.
 */
class Module_purchase
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
		$info['locked']=false;
		$info['update_require_upgrade']=1;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_if_exists('transactions');
		$GLOBALS['SITE_DB']->drop_if_exists('trans_expecting');
		delete_config_option('currency');
		delete_config_option('ecommerce_test_mode');
		delete_config_option('ipn_test');
		delete_config_option('ipn');
		delete_config_option('ipn_password');
		delete_config_option('vpn_password');
		delete_config_option('vpn_username');
		delete_config_option('ipn_digest');
		delete_config_option('payment_gateway');
		delete_config_option('use_local_payment');
		delete_config_option('pd_address');
		delete_config_option('pd_email');
		delete_config_option('pd_number');
		delete_config_option('callback_password');
		delete_specific_permission('access_ecommerce_in_test_mode');

		delete_menu_item_simple('_SELF:purchase:type=misc');
		delete_menu_item_simple('_SELF:invoices:type=misc');
		delete_menu_item_simple('_SEARCH:purchase:type=misc');
		delete_menu_item_simple('_SEARCH:invoices:type=misc');
		delete_menu_item_simple('_SEARCH:subscriptions:type=misc');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if ((!is_null($upgrade_from)) && ($upgrade_from<3))
		{
			$GLOBALS['SITE_DB']->alter_table_field('transactions','currency','ID_TEXT','t_currency');
		}

		if ((!is_null($upgrade_from)) && ($upgrade_from<4))
		{
			$GLOBALS['SITE_DB']->add_table_field('transactions','t_via','ID_TEXT','paypal');
		}

		if ((is_null($upgrade_from)) || ($upgrade_from<4))
		{
			add_config_option('PAYMENT_GATEWAY','payment_gateway','line','return \'paypal\';','ECOMMERCE','ECOMMERCE');
			add_config_option('USE_LOCAL_PAYMENT','use_local_payment','tick','return \'0\';','ECOMMERCE','ECOMMERCE');
			add_config_option('IPN_PASSWORD','ipn_password','line','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay test account: 'secpay'/'secpay'
			add_config_option('IPN_DIGEST','ipn_digest','line','return \'\';','ECOMMERCE','ECOMMERCE');
			add_config_option('VPN_USERNAME','vpn_username','line','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay: not needed
			add_config_option('VPN_PASSWORD','vpn_password','line','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay test account: 'secpay'/'secpay'
			add_config_option('CALLBACK_PASSWORD','callback_password','line','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay: not needed
			add_config_option('POSTAL_ADDRESS','pd_address','text','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay: not needed
			add_config_option('EMAIL_ADDRESS','pd_email','line','return get_option(\'staff_address\');','ECOMMERCE','ECOMMERCE'); // SecPay: not needed
			add_config_option('PHONE_NUMBER','pd_number','line','return \'\';','ECOMMERCE','ECOMMERCE'); // SecPay: not needed
			add_specific_permission('ECOMMERCE','access_ecommerce_in_test_mode',false);

			$GLOBALS['SITE_DB']->create_table('trans_expecting',array(
				'id'=>'*ID_TEXT',
				'e_purchase_id'=>'ID_TEXT',
				'e_item_name'=>'SHORT_TEXT',
				'e_member_id'=>'USER',
				'e_amount'=>'SHORT_TEXT',
				'e_ip_address'=>'IP',
				'e_session_id'=>'INTEGER',
				'e_time'=>'TIME',
				'e_length'=>'?INTEGER',
				'e_length_units'=>'ID_TEXT',
			));

			require_code('currency');
			$cpf=array('currency'=>array(3,'list',implode('|',array_keys(get_currency_map()))));
			foreach ($cpf as $f=>$l)
				$GLOBALS['FORUM_DRIVER']->install_create_custom_field($f,$l[0],1,0,1,0,'',$l[1],0,$l[2]);
			$cpf=array('payment_cardholder_name'=>array(100,'short_text',''),'payment_type'=>array(26,'list','American Express|Delta|Diners Card|JCB|Master Card|Solo|Switch|Visa'),'payment_card_number'=>array(20,'integer',''),'payment_card_start_date'=>array(5,'short_text','mm/yy'),'payment_card_expiry_date'=>array(5,'short_text','mm/yy'),'payment_card_issue_number'=>array(2,'short_text',''),'payment_card_cv2'=>array(4,'short_text',''));
			foreach ($cpf as $f=>$l)
				$GLOBALS['FORUM_DRIVER']->install_create_custom_field($f,$l[0],1,0,1,0,'',$l[1],1,$l[2]);

			require_lang('ecommerce');
			add_menu_item_simple('ecommerce_features',NULL,'PURCHASING','_SEARCH:purchase:type=misc');
			add_menu_item_simple('ecommerce_features',NULL,'INVOICES','_SEARCH:invoices:type=misc');
			add_menu_item_simple('ecommerce_features',NULL,'MODULE_TRANS_NAME_subscriptions','_SEARCH:subscriptions:type=misc');
		}

		if ((is_null($upgrade_from)) || ($upgrade_from<3))
		{
			add_config_option('CURRENCY','currency','line','return \'GBP\';','ECOMMERCE','ECOMMERCE');
			add_config_option('ECOMMERCE_TEST_MODE','ecommerce_test_mode','tick','return \'0\';','ECOMMERCE','ECOMMERCE');
			add_config_option('IPN_ADDRESS_TEST','ipn_test','line','return get_option(\'staff_address\');','ECOMMERCE','ECOMMERCE');
			add_config_option('IPN_ADDRESS','ipn','line','return get_option(\'staff_address\');','ECOMMERCE','ECOMMERCE');
		}

		if (is_null($upgrade_from))
		{
			$GLOBALS['SITE_DB']->create_table('transactions',array(
				'id'=>'*ID_TEXT',
				'purchase_id'=>'ID_TEXT',
				'status'=>'SHORT_TEXT',
				'reason'=>'SHORT_TEXT',
				'amount'=>'SHORT_TEXT',
				't_currency'=>'ID_TEXT',
				'linked'=>'ID_TEXT',
				't_time'=>'*TIME',
				'item'=>'SHORT_TEXT',
				'pending_reason'=>'SHORT_TEXT',
				't_memo'=>'LONG_TEXT',
				't_via'=>'ID_TEXT'
			));
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array		A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'PURCHASING');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode		The result of execution.
	 */
	function run()
	{
		@ignore_user_abort(true); // Must keep going till completion

		require_lang('ecommerce');
		require_code('ecommerce');
		require_lang('config');
		require_css('ecommerce');

		// Kill switch
		if ((ecommerce_test_mode()) && (!$GLOBALS['IS_ACTUALLY_ADMIN']) && (!has_specific_permission(get_member(),'access_ecommerce_in_test_mode')))
			warn_exit(do_lang_tempcode('PURCHASE_DISABLED'));

		$type=get_param('type','misc');

		$title=get_screen_title('PURCHASING_TITLE',true,array(do_lang_tempcode('PURCHASE_STAGE_'.$type)));

		// Recognise join operations
		$new_username=post_param('username',NULL);
		if (!is_null($new_username))
		{
			require_code('ocf_join');
			list($messages)=ocf_join_actual(true,false,false,true,false,false,false,true);
			if (!$messages->is_empty())
				return inform_screen($title,$messages);
		}

		// Normal processing
		$tpl=new ocp_tempcode();
		if ($type=='misc') $tpl=$this->choose($title);
		if ($type=='message') $tpl=$this->message($title);
		if ($type=='licence') $tpl=$this->licence($title);
		if ($type=='details') $tpl=$this->details($title);
		if ($type=='pay') $tpl=$this->pay($title);
		if ($type=='finish') $tpl=$this->finish($title);
		return $tpl;
	}

	/**
	 * Wrap-up so as to remove redundancy in templates.
	 *
	 * @param  tempcode		To wrap.
	 * @param  tempcode		The title to use.
	 * @param  ?mixed			URL (NULL: no next URL).
	 * @param  boolean		Whether it is a GET form
	 * @return tempcode		Wrapped.
	 */
	function _wrap($content,$title,$url,$get=false)
	{
		if (is_null($url)) $url='';
		require_javascript('javascript_validation');
		return do_template('PURCHASE_WIZARD_SCREEN',array('_GUID'=>'a32c99acc28e8ad05fd9b5e2f2cda029','GET'=>$get?true:NULL,'TITLE'=>$title,'CONTENT'=>$content,'URL'=>$url));
	}

	/**
	 * Choose product step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function choose($title)
	{
		breadcrumb_set_self(do_lang_tempcode('PURCHASING'));

		$url=build_url(array('page'=>'_SELF','type'=>'message','id'=>get_param_integer('id',-1)),'_SELF',NULL,true,true);

		require_code('form_templates');

		$list=new ocp_tempcode();
		$filter=get_param('filter','');
		$products=find_all_products();

		foreach ($products as $product=>$details)
		{
			if ($filter!='')
			{
				if ((!is_string($product)) || (substr($product,0,strlen($filter))!=$filter)) continue;
			}

			$wizard_supported=(($details[0]==PRODUCT_PURCHASE_WIZARD) || ($details[0]==PRODUCT_SUBSCRIPTION) || ($details[0]==PRODUCT_CATALOGUE));

			$is_available=false; // Anything without is_available is not meant to be purchased directly
			if (method_exists($details[count($details)-1],'is_available'))
			{
				$availability_status=$details[count($details)-1]->is_available($product,get_member());
				$is_available=($availability_status==ECOMMERCE_PRODUCT_AVAILABLE) || ($availability_status==ECOMMERCE_PRODUCT_NO_GUESTS);
			}

			if ($wizard_supported && $is_available)
			{
				require_code('currency');
				$currency=get_option('currency');
				$price=currency_convert(floatval($details[1]),$currency,NULL,true);

				$description=$details[4];
				if (strpos($details[4],(strpos($details[4],'.')===false)?preg_replace('#\.00($|[^\d])#','',$price):$price)===false)
					$description.=(' ('.$price.')');
				$list->attach(form_input_list_entry($product,false,protect_from_escaping($description)));
			}
		}
		if ($list->is_empty()) inform_exit(do_lang_tempcode('NO_CATEGORIES'));
		$fields=form_input_huge_list(do_lang_tempcode('PRODUCT'),'','product',$list,NULL,true);

		return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_CHOOSE',array('_GUID'=>'47c22d48313ff50e6323f05a78342eae','FIELDS'=>$fields,'TITLE'=>$title)),$title,$url,true);
	}

	/**
	 * Message about product step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function message($title)
	{
		require_code('form_templates');

		$product=get_param('product');

		$text=new ocp_tempcode();
		$object=find_product($product);
		if (is_null($object))
			warn_exit(do_lang_tempcode('MISSING_RESOURCE'));

		$test=$this->_check_availability($product);
		if (!is_null($test)) return $test;

		// Work out what next step is
		$licence=method_exists($object,'get_agreement')?$object->get_agreement($product):'';
		$fields=method_exists($object,'get_needed_fields')?$object->get_needed_fields($product):NULL;
		if ((!is_null($fields)) && ($fields->is_empty())) $fields=NULL;
		$url=build_url(array('page'=>'_SELF','type'=>($licence=='')?(is_null($fields)?'pay':'details'):'licence','product'=>$product,'id'=>get_param_integer('id',-1)),'_SELF',NULL,true);

		if (method_exists($object,'product_info'))
		{
			$text->attach($object->product_info(get_param_integer('product'),$title));
		} else
		{
			if (!method_exists($object,'get_message'))
			{
				// Ah, not even a message to show - jump ahead
				return redirect_screen($title,$url,'');
			}
			$text->attach(paragraph($object->get_message($product)));
		}

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('PURCHASING'))));

		return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_MESSAGE',array('_GUID'=>'8667b6b544c4cea645a52bb4d087f816','TITLE'=>'','TEXT'=>$text)),$title,$url);
	}

	/**
	 * Licence agreement step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function licence($title)
	{
		require_lang('installer');

		require_code('form_templates');

		$product=get_param('product');

		$object=find_product($product);

		$test=$this->_check_availability($product);
		if (!is_null($test)) return $test;

		// Work out what next step is
		$licence=$object->get_agreement($product);
		$fields=$object->get_needed_fields($product);
		if ((!is_null($fields)) && ($fields->is_empty())) $fields=NULL;
		$url=build_url(array('page'=>'_SELF','type'=>is_null($fields)?'pay':'details','product'=>$product,'id'=>get_param_integer('id',-1)),'_SELF',NULL,true,true);

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('PURCHASING'))));

		return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_LICENCE',array('_GUID'=>'55c7bc550bb327535db1aebdac9d85f2','TITLE'=>$title,'URL'=>$url,'LICENCE'=>$licence)),$title,NULL);
	}

	/**
	 * Details about purchase step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function details($title)
	{
		require_code('form_templates');

		$product=get_param('product');

		$object=find_product($product);

		$test=$this->_check_availability($product);
		if (!is_null($test)) return $test;

		// Work out what next step is
		$fields=$object->get_needed_fields($product,get_param_integer('id',-1));
		$url=build_url(array('page'=>'_SELF','type'=>'pay','product'=>$product),'_SELF',NULL,true);

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('PURCHASING'))));

		return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_DETAILS',array('_GUID'=>'7fcbb0be5e90e52163bfec01f22f4ea0','TEXT'=>is_array($fields)?$fields[1]:'','FIELDS'=>is_array($fields)?$fields[0]:$fields)),$title,$url);
	}

	/**
	 * Payment step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function pay($title)
	{
		$product=get_param('product');
		$object=find_product($product);

		$test=$this->_check_availability($product);
		if (!is_null($test)) return $test;

		$temp=$object->get_products(true,$product);
		$price=$temp[$product][1];
		$item_name=$temp[$product][4];

		if (method_exists($object,'set_needed_fields'))
			$purchase_id=$object->set_needed_fields($product);
		else
			$purchase_id=strval(get_member());

		if ($temp[$product][0]==PRODUCT_SUBSCRIPTION)
		{
			$GLOBALS['SITE_DB']->query_delete('subscriptions',array(
				's_type_code'=>$product,
				's_member_id'=>get_member(),
				's_state'=>'new'
			));

			$purchase_id=strval($GLOBALS['SITE_DB']->query_insert('subscriptions',array(
				's_type_code'=>$product,
				's_member_id'=>get_member(),
				's_state'=>'new',
				's_amount'=>$temp[$product][1],
				's_special'=>$purchase_id,
				's_time'=>time(),
				's_auto_fund_source'=>'',
				's_auto_fund_key'=>'',
				's_via'=>get_option('payment_gateway'),
			),true));

			$length=array_key_exists('length',$temp[$product][3])?$temp[$product][3]['length']:1;
			$length_units=array_key_exists('length_units',$temp[$product][3])?$temp[$product][3]['length_units']:'m';
		} else
		{
			$length=NULL;
			$length_units='';

			//Add cataloue item order to shopping_orders
			if (method_exists($object,'add_purchase_order'))
			{
				$purchase_id=strval($object->add_purchase_order($product,$temp[$product]));
			}
		}

		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('PURCHASING'))));

		if ($price=='0')
		{
			$payment_status='Completed';
			$reason_code='';
			$pending_reason='';
			$mc_currency=get_option('currency');
			$txn_id='manual-'.substr(uniqid('',true),0,10);
			$parent_txn_id='';
			$memo='Free';
			$mc_gross='';
			handle_confirmed_transaction($purchase_id,$item_name,$payment_status,$reason_code,$pending_reason,$memo,$mc_gross,$mc_currency,$txn_id,$parent_txn_id);
			return inform_screen($title,do_lang_tempcode('FREE_PURCHASE'));
		}

		if (!array_key_exists(4,$temp[$product])) $item_name=do_lang('CUSTOM_PRODUCT_'.$product,NULL,NULL,NULL,get_site_default_lang());

		if (!perform_local_payment()) // Pass through to the gateway's HTTP server
		{
			if ($temp[$product][0]==PRODUCT_SUBSCRIPTION)
			{
				$transaction_button=make_subscription_button($product,$item_name,$purchase_id,floatval($price),$length,$length_units,get_option('currency'));
			} else
			{
				$transaction_button=make_transaction_button($product,$item_name,$purchase_id,floatval($price),get_option('currency'));
			}
			$tpl=($temp[$product][0]==PRODUCT_SUBSCRIPTION)?'PURCHASE_WIZARD_STAGE_SUBSCRIBE':'PURCHASE_WIZARD_STAGE_PAY';
			$logos=method_exists($object,'get_logos')?$object->get_logos():new ocp_tempcode();
			$result=do_template($tpl,array('LOGOS'=>$logos,'TRANSACTION_BUTTON'=>$transaction_button,'CURRENCY'=>get_option('currency'),'ITEM_NAME'=>$item_name,'TITLE'=>$title,'LENGTH'=>is_null($length)?'':strval($length),'LENGTH_UNITS'=>$length_units,'PURCHASE_ID'=>$purchase_id,'PRICE'=>float_to_raw_string(floatval($price))));
		} else // Handle the transaction internally
		{
			if (((ocp_srv('HTTPS')=='') || (ocp_srv('HTTPS')=='off')) && (!ecommerce_test_mode()))
			{
				warn_exit(do_lang_tempcode('NO_SSL_SETUP'));
			}

			$fields=get_transaction_form_fields(NULL,$purchase_id,$item_name,float_to_raw_string($price),($temp[$product][0]==PRODUCT_SUBSCRIPTION)?intval($length):NULL,($temp[$product][0]==PRODUCT_SUBSCRIPTION)?$length_units:'');

			/*$via=get_option('payment_gateway');
			require_code('hooks/systems/ecommerce_via/'.filter_naughty_harsh($via));
			$object=object_factory('Hook_'.$via);
			$ipn_url=$object->get_ipn_url();*/
			$finish_url=build_url(array('page'=>'_SELF','type'=>'finish'),'_SELF');

			$result=do_template('PURCHASE_WIZARD_STAGE_TRANSACT',array('_GUID'=>'15cbba9733f6ff8610968418d8ab527e','FIELDS'=>$fields));
			return $this->_wrap($result,$title,$finish_url);
		}
		return $this->_wrap($result,$title,NULL);
	}

	/**
	 * Finish step.
	 *
	 * @param  tempcode		The page title.
	 * @return tempcode		The result of execution.
	 */
	function finish($title)
	{
		breadcrumb_set_parents(array(array('_SELF:_SELF:misc',do_lang_tempcode('PURCHASING'))));

		$message=get_param('message',NULL,true);

		if (get_param_integer('cancel',0)==0)
		{
			if (perform_local_payment()) // We need to try and run the transaction
			{
				$trans_id=post_param('trans_id');
				$transaction_rows=$GLOBALS['SITE_DB']->query_select('trans_expecting',array('*'),array('id'=>$trans_id),'',1);
				if (!array_key_exists(0,$transaction_rows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
				$transaction_row=$transaction_rows[0];
				$amount=$transaction_row['e_amount'];
				$length=$transaction_row['e_length'];
				$length_units=$transaction_row['e_length_units'];

				$via=get_option('payment_gateway');
				require_code('hooks/systems/ecommerce_via/'.filter_naughty_harsh($via));
				$object=object_factory('Hook_'.$via);

				$name=post_param('name');
				$card_number=post_param('card_number');
				$expiry_date=str_replace('/','',post_param('expiry_date'));
				$issue_number=post_param_integer('issue_number',NULL);
				$start_date=str_replace('/','',post_param('start_date'));
				$card_type=post_param('card_type');
				$cv2=post_param('cv2');

				list($success,,$message,$message_raw)=$object->do_transaction($trans_id,$name,$card_number,$amount,$expiry_date,$issue_number,$start_date,$card_type,$cv2,$length,$length_units);

				if (($success) || (!is_null($length)))
				{
					$status=((!is_null($length)) && (!$success))?'SCancelled':'Completed';
					handle_confirmed_transaction($transaction_row['e_purchase_id'],$transaction_row['e_item_name'],$status,$message_raw,'','',$amount,get_option('currency'),$trans_id,'',$via,is_null($length)?'':strtolower(strval($length).' '.$length_units));
				}

				if ($success)
				{
					$member_id=$transaction_row['e_member_id'];
					require_code('notifications');
					dispatch_notification('payment_received',NULL,do_lang('PAYMENT_RECEIVED_SUBJECT',$trans_id),do_lang('PAYMENT_RECEIVED_BODY',float_format(floatval($amount)),get_option('currency'),get_site_name()),array($member_id),A_FROM_SYSTEM_PRIVILEGED);
				}
			}

			$product=get_param('product','');
			if ($product!='')
			{
				if (count($_POST)!=0)
				{
					handle_transaction_script();
				}

				attach_message(do_lang_tempcode('SUCCESS'),'inform');

				$object=find_product($product);
				if (method_exists($object,'get_finish_url'))
				{
					return redirect_screen($title,$object->get_finish_url($product),$message);
				}
			}

			return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH',array('_GUID'=>'43f706793719ea893c280604efffacfe','TITLE'=>$title,'MESSAGE'=>$message)),$title,NULL);
		}

		if (!is_null($message))
		{
			return $this->_wrap(do_template('PURCHASE_WIZARD_STAGE_FINISH',array('_GUID'=>'859c31e8f0f02a2a46951be698dd22cf','TITLE'=>$title,'MESSAGE'=>$message)),$title,NULL);
		}

		return inform_screen(get_screen_title('PURCHASING'),do_lang_tempcode('PRODUCT_PURCHASE_CANCEL'));
	}

	/**
	 * Check to see if a product is available to the current user.
	 *
	 * @param  ID_TEXT		The product code.
	 * @return ?tempcode		Error screen (NULL: no error).
	 */
	function _check_availability($product)
	{
		$object=find_product($product);
		if (!method_exists($object,'is_available')) warn_exit(do_lang_tempcode('INTERNAL_ERROR'));

		$availability_status=$object->is_available($product,get_member());

		switch ($availability_status)
		{
			case ECOMMERCE_PRODUCT_ALREADY_HAS:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('ECOMMERCE_PRODUCT_ALREADY_HAS'));

			case ECOMMERCE_PRODUCT_DISABLED:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('ECOMMERCE_PRODUCT_DISABLED'));

			case ECOMMERCE_PRODUCT_PROHIBITED:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('ECOMMERCE_PRODUCT_PROHIBITED'));

			case ECOMMERCE_PRODUCT_OUT_OF_STOCK:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('ECOMMERCE_PRODUCT_OUT_OF_STOCK'));

			case ECOMMERCE_PRODUCT_MISSING:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('ECOMMERCE_PRODUCT_MISSING'));

			case ECOMMERCE_PRODUCT_INTERNAL_ERROR:
				return warn_screen(get_screen_title('PURCHASING'),do_lang_tempcode('INTERNAL_ERROR'));

			case ECOMMERCE_PRODUCT_NO_GUESTS:
				if ((is_guest()) && (get_forum_type()!='ocf'))
				{
					access_denied('NOT_AS_GUEST');
				}

				require_code('ocf_join');

				$url=get_self_url();

				list($javascript,$form)=ocf_join_form($url,true,false,false,false);

				$hidden=build_keep_post_fields();

				$join_screen=do_template('PURCHASE_WIZARD_STAGE_GUEST',array(
					'_GUID'=>'accf475a1457f73d7280b14d774acc6e',
					'TEXT'=>do_lang_tempcode('PURCHASE_NOT_LOGGED_IN',escape_html(get_site_name())),
					'JAVASCRIPT'=>$javascript,
					'FORM'=>$form,
					'HIDDEN'=>$hidden,
				));

				return $this->_wrap($join_screen,get_screen_title('PURCHASING'),NULL);
		}

		return NULL;
	}
}
