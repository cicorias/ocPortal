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
 * @package		shopping
 */

/**
 * Module page class.
 */
class Module_admin_orders
{

	/**
	 * Standard modular info function.
	 *
	 * @return ?array	Map of module info (NULL: module is disabled).
	 */
	function info()
	{
		$info=array();
		$info['author']='Manuprathap';
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
		return array('misc'=>'ORDERS','show_orders'=>'OUTSTANDING_ORDERS');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('ecommerce');
		require_code('ecommerce');
		require_lang('shopping');
		require_javascript('javascript_shopping');
		require_css('shopping');
		require_code('users_active_actions');

		$type=get_param('type','misc');

		if ($type=='misc') return $this->misc();
		if ($type=='show_orders') return $this->show_orders();
		if ($type=='order_det') 	return $this->order_details();
		if($type=='order_act')
		{
			$action		=	either_param('action');

			if ($action=='add_note') 	return $this->add_note();			
			if ($action=='dispatch') 	return $this->dispatch();
			if ($action=='del_order') 	return $this->delete_order();
			if ($action=='return') 		return $this->return_order();
			if ($action=='hold') 		return $this->hold_order();
		}

		if ($type=='_add_note') 		return $this->_add_note();
		if ($type=='order_export') 	return $this->order_export();
		if ($type=='_order_export') 	return $this->_order_export();

		return new ocp_tempcode();
	}

	/**
	 * The do-next manager for order module
	 * 
	 * @return tempcode		The UI
	 */
	function misc()
	{
		breadcrumb_set_self(do_lang_tempcode('ORDERS'));
		breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce:ecom_usage',do_lang_tempcode('ECOMMERCE'))));

		require_code('templates_donext');
		return do_next_manager(get_page_title('ORDERS'),comcode_lang_string('DOC_ECOMMERCE'),
					array(
						array('show_orders',array('_SELF',array('type'=>'show_orders'),'_SELF'),do_lang('SHOW_ORDERS')),
						array('undispatched',array('_SELF',array('type'=>'show_orders','filter'=>'undispatched'),'_SELF'),do_lang('UNDISPATCHED_ORDERS')),
					),
					do_lang('ORDERS')
		);
	}

	/**
	 * UI to show all orders
	 *
	 * @return tempcode	The interface.
	 */
	function show_orders()
	{
		require_code('shopping');

		$title		=	get_page_title('ORDER_LIST');

		$filter		=	get_param('filter',NULL);

		$search		=	get_param('search','',true);

		$cond		=	"WHERE 1=1";

		if($filter=='undispatched')
		{
			$cond	.=	" AND t1.order_status='ORDER_STATUS_payment_received'";
			$title	 =	get_page_title('UNDISPATCHED_ORDER_LIST');
		}

		$extra_join='';
		if((!is_null($search)) && ($search!=''))
		{
			$GLOBALS['NO_DB_SCOPE_CHECK']=true;

			$cond	.=	" AND (t1.id LIKE '".db_encode_like(str_replace('#','',$search).'%')."' OR t2.m_username LIKE '".db_encode_like(str_replace('#','',$search).'%')."')";
			$extra_join=' JOIN '.get_table_prefix().'f_members t2 ON t2.id=t1.c_member';
		}

		breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce:ecom_usage',do_lang_tempcode('ECOMMERCE')),array('_SELF:_SELF:misc',do_lang_tempcode('ORDERS'))));

		$orders		=	array();
		//pagination
		$start		=	get_param_integer('start',0);

		$max		=	get_param_integer('max',10);

		require_code('templates_results_browser');

		require_code('templates_results_table');

		$sortables=array('t1.id'=>do_lang_tempcode('ECOM_ORDER'),'t1.add_date'=>do_lang_tempcode('ORDERED_DATE'),'t1.c_member'=>do_lang_tempcode('ORDERED_BY'),
		't1.tot_price'=>do_lang_tempcode('ORDER_PRICE_AMT'),'t3.included_tax'=>do_lang_tempcode('TAX_PAID'),'t1.order_status'=>do_lang_tempcode('STATUS'),'t1.transaction_id'=>do_lang_tempcode('TRANSACTION_ID'));

		$query_sort=explode(' ',get_param('sort','t1.add_date ASC'),2);

		if (count($query_sort)==1) $query_sort[]='ASC';

		list($sortable,$sort_order)=$query_sort;		

		if (((strtoupper($sort_order)!='ASC') && (strtoupper($sort_order)!='DESC')) || (!array_key_exists($sortable,$sortables)))
			log_hack_attack_and_exit('ORDERBY_HACK');
		global $NON_CANONICAL_PARAMS;
		$NON_CANONICAL_PARAMS[]='sort';

		$fields_title=results_field_title(
							array(
								do_lang_tempcode('ECOM_ORDER'),
								do_lang_tempcode('THE_PRICE'),
								do_lang_tempcode('TAX_PAID'),
								do_lang_tempcode('ORDERED_DATE'),
								do_lang_tempcode('ORDERED_BY'),
								do_lang_tempcode('TRANSACTION_ID'),
								do_lang_tempcode('STATUS'),
								do_lang_tempcode('ACTIONS')
							),$sortables,'sort',$sortable.' '.$sort_order
						);

		global $NO_DB_SCOPE_CHECK;
		$NO_DB_SCOPE_CHECK=true;
		$max_rows		=	$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.get_table_prefix().'shopping_order t1'.$extra_join.' LEFT JOIN '.get_table_prefix().'shopping_order_details t3 ON t1.id=t3.order_id '.$cond);

		$results_browser	=	results_browser(do_lang_tempcode('ORDERS'),NULL,$start,'start',$max,'max',$max_rows,NULL,'show_orders',true,true);

		$rows		=	$GLOBALS['SITE_DB']->query('SELECT t1.*,(t3.p_quantity*t3.included_tax) as tax FROM '.get_table_prefix().'shopping_order t1'.$extra_join.' LEFT JOIN '.get_table_prefix().'shopping_order_details t3 ON t1.id=t3.order_id '.$cond.' GROUP BY t1.id ORDER BY '.db_string_equal_to('t1.order_status','ORDER_STATUS_cancelled').','.$sortable.' '.$sort_order,$max,$start);

		$order_entries	=	new ocp_tempcode();

		foreach ($rows as $row)
		{	
			if($row['purchase_through']=='cart')
			{
				$order_det_url	=	build_url(array('page'=>'_SELF','type'=>'order_det','id'=>$row['id']),'_SELF');

				$order_title	=	do_lang('CART_ORDER',strval($row['id']));
			}
			else
			{
				$res	=	$GLOBALS['SITE_DB']->query_select('shopping_order_details',array('p_id','p_name'),array('order_id'=>$row['id']));

				if (!array_key_exists(0,$res)) continue; // DB corruption
				$product_det	=	$res[0];

				$order_title	=	do_lang('PURCHASE_ORDER',strval($row['id']));

				$order_det_url	=	build_url(array('page'=>'catalogues','type'=>'entry','id'=>$product_det['p_id']),get_module_zone('catalogues'));			
			}

			$submitted_by	=	$GLOBALS['FORUM_DRIVER']->get_username($row['c_member']);

			$order_status	=	do_lang($row['order_status']);

			$ordr_act_submit=	build_url(array('page'=>'_SELF','type'=>'order_act','id'=>$row['id']),'_SELF');	

			$actions	=	do_template('ADMIN_ORDER_ACTIONS',array('ORDER_TITLE'=>$order_title,'ORDR_ACT_URL'=>$ordr_act_submit,'ORDER_STATUS'=>$order_status));	

			$url		=	build_url(array('page'=>'members','type'=>'view','id'=>$row['c_member']),'_SELF');

			$member		=	hyperlink($url,$submitted_by,false,false,do_lang('INDEX'));

			$view_url	=	build_url(array('page'=>'_SELF','type'=>'order_det','id'=>$row['id']),'_SELF');

			$order_date	=	hyperlink($view_url,get_timezoned_date($row['add_date'],true,false,true,true));

			$transaction_details_link	=	build_url(array('page'=>'_SELF','type'=>'order_det','id'=>$row['id']),'_SELF');

			if($row['transaction_id']!='')
			{	
				$transaction_details_link	=	build_url(array('page'=>'admin_ecommerce','type'=>'logs','product'=>$order_title,'id'=>$row['id']),get_module_zone('admin_ecommerce'));

				$transaction_id	=	hyperlink($transaction_details_link,strval($row['transaction_id']));
			}
			else
				$transaction_id	=	do_lang_tempcode('INCOMPLETED_TRANCACTION');

			$order_entries->attach(results_entry(
						array(
							escape_html($order_title),
							ecommerce_get_currency_symbol().escape_html(strval($row['tot_price'])),
							escape_html(float_format($row['tax'],2)),
							$order_date,
							$member,
							$transaction_id,
							$order_status,
							$actions
						),false,NULL
					)
			);			
		}

		$width		=	array('110','70','80','200','120','180','180','200');

		$results_table	=	results_table(do_lang_tempcode('ORDERS'),0,'start',$max_rows,'max',$max_rows,$fields_title,$order_entries,$sortables,$sortable,$sort_order,'sort',NULL,$width,'cart');

		if (is_null($order_entries)) inform_exit(do_lang_tempcode('NO_ENTRIES'));

		$hidden		=	build_keep_form_fields('_SELF',true,array('filter'));

		$search_url	=	get_self_url(true);

		return do_template('ECOM_ADMIN_ORDERS_SCREEN',array('TITLE'=>$title,'CURRENCY'=>get_option('currency'),'ORDERS'=>$orders,'RESULTS_BROWSER'=>$results_browser,'RESULT_TABLE'=>$results_table,'SEARCH_URL'=>$search_url,'HIDDEN'=>$hidden,'SEARCH_VAL'=>$search));	
	}

	/**
	 * UI to show details of an order
	 *
	 * @return tempcode	The interface.
	 */
	function order_details()
	{
		$id=get_param_integer('id');

		$title		=	get_page_title('MY_ORDER_DETAILS');

		$order_title	=	do_lang('CART_ORDER',$id);

		//pagination
		$start		=	get_param_integer('start',0);

		$max		=	get_param_integer('max',10);

		require_code('templates_results_browser');

		require_code('templates_results_table');

		$sortables=array();

		$query_sort=explode(' ',get_param('sort','p_name ASC'),2);

		if (count($query_sort)==1) $query_sort[]='ASC';

		list($sortable,$sort_order)=$query_sort;

		$fields_title=results_field_title(
							array(
								do_lang_tempcode('SLNO'),
								do_lang_tempcode('PRODUCT_NAME'),
								do_lang_tempcode('THE_PRICE'),
								do_lang_tempcode('QUANTITY'),
								do_lang_tempcode('STATUS'),
							),$sortables,'sort',$sortable.' '.$sort_order
						);

		$max_rows	=	$GLOBALS['SITE_DB']->query_value_null_ok('shopping_order_details','COUNT(*)',array('order_id'=>$id));

		$results_browser	=	results_browser(do_lang_tempcode('ORDERS'),NULL,$start,'start',$max,'max',$max_rows,NULL,'show_orders',true,true);

		$rows		=	$GLOBALS['SITE_DB']->query_select('shopping_order_details',array('*'),array('order_id'=>$id),'ORDER BY '.$sortable.' '.$sort_order,$max,$start);

		$product_entries	=	new ocp_tempcode();	

		breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce:ecom_usage',do_lang_tempcode('ECOMMERCE')),array('_SELF:_SELF:misc',do_lang_tempcode('ORDERS')),array('_SELF:_SELF:show_orders',do_lang_tempcode('ORDER_LIST'))));

		foreach ($rows as $row)
		{
			$product_info_url	=	build_url(array('page'=>'catalogues','type'=>'entry','id'=>$row['p_id']),get_module_zone('catalogues'));

			$product_name	=	$row['p_name'];

			$product	=	hyperlink($product_info_url,$product_name,false,false,do_lang('INDEX'));

			$product_entries->attach(results_entry(
					array(
						escape_html(strval($row['p_id'])),
						$product,
						ecommerce_get_currency_symbol().escape_html(strval($row['p_price'])),
						escape_html(strval($row['p_quantity'])),
						do_lang($row['dispatch_status'])
					),false,NULL
				)
			);
		}

		$text	=	do_lang_tempcode('ORDER_DETAILS_TEXT');

		//Collecting order details
		$rows		=	$GLOBALS['SITE_DB']->query_select('shopping_order',array('*'),array('id'=>$id),'',1);

		if (!array_key_exists(0,$rows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));

		$data		=	$rows[0];

		$results_table	=	results_table(do_lang_tempcode('PRODUCTS'),0,'start',$max_rows,'max',$max_rows,$fields_title,$product_entries,$sortables,$sortable,$sort_order,'sort',NULL,NULL,'cart');

		$ordered_by_member_id = $data['c_member'];

		$ordered_by_username	=	$GLOBALS['FORUM_DRIVER']->get_username($data['c_member']);

		$self_url		=	get_self_url(true,true);

		$ordr_act_submit	=	build_url(array('page'=>'_SELF','type'=>'order_act','id'=>$id,'redirect'=>$self_url),'_SELF');	

		$order_actions	=	do_template('ADMIN_ORDER_ACTIONS',array('ORDER_TITLE'=>$order_title,'ORDR_ACT_URL'=>$ordr_act_submit,'ORDER_STATUS'=>do_lang($data['order_status'])));

		//Shipping address display
		$row	=	$GLOBALS['SITE_DB']->query_select('shopping_order_addresses',array('*'),array('order_id'=>$id),'',1);

		if(array_key_exists(0,$row))
		{
			$address		=	$row[0];
			$shipping_address	=	do_template('SHIPPING_ADDRESS',array('ADDRESS_NAME'=>$address['address_name'],'ADDRESS_STREET'=>$address['address_street'],'ADDRESS_CITY'=>$address['address_city'],'ADDRESS_ZIP'=>$address['address_zip'],'ADDRESS_COUNTRY'=>$address['address_country'],'RECEIVER_EMAIL'=>$address['receiver_email']));	
		}
		else
			$shipping_address	=	new ocp_tempcode();

		return do_template('ECOM_ADMIN_ORDERS_DETAILS_SCREEN',array('TITLE'=>$title,'TEXT'=>$text,'CURRENCY'=>get_option('currency'),'RESULT_TABLE'=>$results_table,'RESULTS_BROWSER'=>$results_browser,'ORDER_NUMBER'=>strval($id),'ADD_DATE'=>get_timezoned_date($data['add_date'],true,false,true,true),'TOTAL_PRICE'=>strval($data['tot_price']),'ORDERED_BY_MEMBER_ID'=>strval($ordered_by_member_id),'ORDERED_BY_USERNAME'=>$ordered_by_username,'ORDER_STATUS'=>do_lang($data['order_status']),'NOTES'=>$data['notes'],'PURCHASED_VIA'=>$data['purchase_through'],'ORDER_ACTIONS'=>$order_actions,'SHIPPING_ADDRESS'=>$shipping_address));	
	}

	/**
	 * UI to add note to an order
	 *
	 * @return tempcode	The interface.
	 */
	function add_note()
	{
		require_code('form_templates');

		$id		=	get_param_integer('id');

		$redirect_url	=	get_param('redirect',NULL);

		$last_action	=	get_param('last_act',NULL);

		breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce:ecom_usage',do_lang_tempcode('ECOMMERCE')),array('_SELF:_SELF:misc',do_lang_tempcode('ORDERS')),array('_SELF:_SELF:show_orders',do_lang_tempcode('ORDER_LIST'))));

		$update_url	=	build_url(array('page'=>'_SELF','type'=>'_add_note','redirect'=>$redirect_url),'_SELF');

		$fields		=	new ocp_tempcode();

		$note		=	$GLOBALS['SITE_DB']->query_value('shopping_order','notes',array('id'=>$id));

		if(!is_null($last_action))
		{
			$note	.=	do_lang('ADD_NOTE_UPPEND_TEXT',get_timezoned_date(time(),true,false,true,true),do_lang('ORDER_STATUS_'.$last_action));
		}

		$fields->attach(form_input_text(do_lang_tempcode('NOTE'),do_lang_tempcode('NOTE_DESCRIPTION'),'note',$note,true));

		$fields->attach(form_input_hidden('order_id',strval($id)));

		$title		=	get_page_title('ADD_NOTE_TITLE',true,array($id));

		if($last_action=='dispatched')
		{	
			//Display dispatch mail preview
			$res		=	$GLOBALS['SITE_DB']->query_select('shopping_order',array('*'),array('id'=>$id),'',1);

			$order_det	=	$res[0];

			$member_name	=	$GLOBALS['FORUM_DRIVER']->get_username($order_det['c_member']);

			$message	=do_lang('ORDER_DISPATCHED_MAIL_MESSAGE',comcode_escape(get_site_name()),comcode_escape($member_name),array(strval($id)),get_lang($order_det['c_member']));

			$fields->attach(form_input_text(do_lang_tempcode('DISPATCH_MAIL_PREVIEW'),do_lang_tempcode('DISPATCH_MAIL_PREVIEW_DESCRIPTION'),'dispatch_mail_content',$message,true));
		}

		return do_template('FORM_SCREEN',array('TITLE'=>$title,'TEXT'=>do_lang_tempcode('NOTE_DESCRIPTION'),'HIDDEN'=>'','FIELDS'=>$fields,'URL'=>$update_url,'SUBMIT_NAME'=>do_lang_tempcode('ADD_NOTE')));
	}

	/**
	 * Actualizer to add not to an order
	 *
	 * @return tempcode	The interface.
	 */
	function _add_note()
	{
		$id			=	post_param_integer('order_id');

		$title		=	get_page_title('ADD_NOTE_TITLE',true,array($id));

		$notes		=	post_param('note');

		$redirect	=	get_param('redirect',NULL);

		$GLOBALS['SITE_DB']->query_update('shopping_order',array('notes'=>$notes),array('id'=>$id),'',1);

		//Send dispatch notification mail
		$this->send_dispatch_notification($id);

		if(is_null($redirect))	//If redirect url is not passed, redirect to order list
		{
			$_redirect	=	build_url(array('page'=>'_SELF','type'=>'show_orders'),get_module_zone('admin_orders'));
			$redirect = $_redirect->evaluate();
		}

		return redirect_screen($title,$redirect,do_lang_tempcode('SUCCESS'));	
	}

	/**
	 * Function to dispatch an order
	 *
	 * @return tempcode	The interface.
	 */
	function dispatch()
	{
		$title=get_page_title('ORDER_STATUS_dispatched');

		$id	=	get_param_integer('id');

		$GLOBALS['SITE_DB']->query_update('shopping_order',array('order_status'=>'ORDER_STATUS_dispatched'),array('id'=>$id),'',1);

		$GLOBALS['SITE_DB']->query_update('shopping_order_details',array('dispatch_status'=>'ORDER_STATUS_dispatched'),array('order_id'=>$id)); //There may be more than one items to update status

		require_code('shopping');
		update_stock($id);

		$add_note_url	=	build_url(array('page'=>'_SELF','type'=>'order_act','action'=>'add_note','last_act'=>'dispatched','id'=>$id),get_module_zone('admin_orders'));

		return redirect_screen($title,$add_note_url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Function to dispatch an order
	 *
	 * @param	AUTO_LINK	Order Id
	 */
	function send_dispatch_notification($order_id)
	{
		//Mail dispatch notification to customer

		$message			=	post_param('dispatch_mail_content',NULL);

		if(is_null($message)) return;

		$res		=	$GLOBALS['SITE_DB']->query_select('shopping_order',array('*'),array('id'=>$order_id),'',1);

		$order_det	=	$res[0];

		//$message	=do_lang('ORDER_DISPATCHED_MAIL_MESSAGE',comcode_escape(get_site_name()),comcode_escape($member_name),array(strval($order_id)));

		require_code('notifications');

		dispatch_notification('order_dispatched',NULL,do_lang('ORDER_DISPATCHED_MAIL_SUBJECT',get_site_name(),strval($order_id),NULL,get_lang($order_det['c_member'])),$message,array($order_det['c_member']),A_FROM_SYSTEM_PRIVILEGED);
	}

	/**
	 * Function to delete order
	 *
	 * @return tempcode	The interface.
	 */
	function delete_order()
	{
		$title=get_page_title('ORDER_STATUS_cancelled');

		$id	=	get_param_integer('id');

		$GLOBALS['SITE_DB']->query_update('shopping_order',array('order_status'=>'ORDER_STATUS_cancelled'),array('id'=>$id),'',1);

		$GLOBALS['SITE_DB']->query_update('shopping_order_details',array('dispatch_status'=>'ORDER_STATUS_cancelled'),array('order_id'=>$id),'',1);

		$add_note_url	=	build_url(array('page'=>'_SELF','type'=>'order_act','action'=>'add_note','last_act'=>'cancelled','id'=>$id),get_module_zone('admin_orders'));

		return redirect_screen($title,$add_note_url,do_lang_tempcode('SUCCESS'));		
	}

	/**
	 * Function to return order items
	 *
	 * @return tempcode	The interface.
	 */
	function return_order()
	{
		$title=get_page_title('ORDER_STATUS_returned');

		$id	=	get_param_integer('id');

		$GLOBALS['SITE_DB']->query_update('shopping_order',array('order_status'=>'ORDER_STATUS_returned'),array('id'=>$id),'',1);

		$GLOBALS['SITE_DB']->query_update('shopping_order_details',array('dispatch_status'=>'ORDER_STATUS_returned'),array('order_id'=>$id),'',1);

		$add_note_url	=	build_url(array('page'=>'_SELF','type'=>'order_act','action'=>'add_note','last_act'=>'returned','id'=>$id),get_module_zone('admin_orders'));

		return redirect_screen($title,$add_note_url,do_lang_tempcode('SUCCESS'));		
	}

	/**
	 * Function to hold an order
	 *
	 * @return tempcode	The interface.
	 */
	function hold_order()
	{
		$title=get_page_title('ORDER_STATUS_onhold');

		$id	=	get_param_integer('id');

		$GLOBALS['SITE_DB']->query_update('shopping_order',array('order_status'=>'ORDER_STATUS_onhold'),array('id'=>$id),'',1);

		$GLOBALS['SITE_DB']->query_update('shopping_order_details',array('dispatch_status'=>'ORDER_STATUS_onhold'),array('order_id'=>$id),'',1);

		$add_note_url	=	build_url(array('page'=>'_SELF','type'=>'order_act','action'=>'add_note','last_act'=>'onhold','id'=>$id),get_module_zone('admin_orders'));

		return redirect_screen($title,$add_note_url,do_lang_tempcode('SUCCESS'));		
	}

	/**
	 * Function to display export order list filters
	 *
	 * @return tempcode	The interface.
	 */
	function order_export()
	{
		require_code('shopping');

		require_code('form_templates');

		$title	=	get_page_title('EXPORT_ORDER_LIST');

		breadcrumb_set_parents(array(array('_SEARCH:admin_ecommerce:ecom_usage',do_lang_tempcode('ECOMMERCE')),array('_SELF:_SELF:misc',do_lang_tempcode('ORDERS')),array('_SELF:_SELF:show_orders',do_lang_tempcode('ORDER_LIST'))));

		$fields	=	new ocp_tempcode();

		$order_status_list	=	get_order_status_list();

		$fields->attach(form_input_list(do_lang_tempcode('ORDER_STATUS'),do_lang_tempcode('ORDER_STATUS_FILTER_DESCRIPTION'),'order_status',$order_status_list,NULL,false,false));

		// Dates
		$start_year=intval(date('Y'))-1;
		$start_month=intval(date('m'));
		$start_day=intval(date('d'));
		$start_hour=intval(date('H'));
		$start_minute=intval(date('i'));

		$end_year=$start_year+1;
		$end_month=$start_month;
		$end_day=$start_day;
		$end_hour=$start_hour;
		$end_minute=$start_minute;

		$fields->attach(form_input_date(do_lang_tempcode('ST_START_PERIOD'),do_lang_tempcode('ST_START_PERIOD_DESCRIPTION'),'start_date',false,false,true,array($start_minute,$start_hour,$start_month,$start_day,$start_year)));

		$fields->attach(form_input_date(do_lang_tempcode('ST_END_PERIOD'),do_lang_tempcode('ST_END_PERIOD_DESCRIPTION'),'end_date',false,false,true,array($end_minute,$end_hour,$end_month,$end_day,$end_year)));

		return	do_template('FORM_SCREEN',array('SKIP_VALIDATION'=>true,'TITLE'=>$title,'SUBMIT_NAME'=>do_lang_tempcode('EXPORT_ORDER_LIST'),'TEXT'=>paragraph(do_lang_tempcode('EXPORT_ORDER_LIST_TEXT')),'URL'=>build_url(array('page'=>'_SELF','type'=>'_order_export'),'_SELF'),'HIDDEN'=>'','FIELDS'=>$fields));
	}

	/**
	 * Actulizer to build csv from the selected filters
	 *
	 * @param  boolean	Whether to avoid exit (useful for unit test).
	 */
	function _order_export($inline=false)
	{
		require_code('shopping');

		$start_date		=	get_input_date('start_date',true);
		$end_date		=	get_input_date('end_date',true);
		$order_status	=	post_param('order_status');

		$filename		=	'Orders_'.$order_status.'__'.get_timezoned_date($start_date,false,false,false,true).'-'.get_timezoned_date($end_date,false,false,false,true).'.csv';

		$orders			=	array();
		$data				=	array();

		$cond				=	"t1.add_date BETWEEN ".strval($start_date)." AND ".strval($end_date);

		if($order_status!='all')
				$cond		.=	" AND t1.order_status='".db_escape_string($order_status)."'";

		$qry				=	"SELECT t1.*,(t2.included_tax*t2.p_quantity) as 	
								tax_amt,t3.address_name,t3.address_street,t3.address_city,t3.address_zip,
								t3.address_country,t3.receiver_email
								FROM ".get_table_prefix()."shopping_order t1
								LEFT JOIN ".get_table_prefix()."shopping_order_details t2 ON t1.id = t2.order_id
								LEFT JOIN ".get_table_prefix()."shopping_order_addresses t3 ON t1.id = t3.order_id
								WHERE ".$cond;

		$row				=	$GLOBALS['SITE_DB']->query($qry);
		remove_duplicate_rows($row);

		foreach($row as $order)
		{
			$orders[do_lang('ORDER_NUMBER')]			=	strval($order['id']);
			$orders[do_lang('ORDERED_DATE')]			=	get_timezoned_date($order['add_date'],true,false,true,true);
			$orders[do_lang('ORDER_PRICE')]			=	$order['tot_price'];
			$orders[do_lang('ORDER_STATUS')]			=	do_lang($order['order_status']);
			$orders[do_lang('ORDER_TAX_OPT_OUT')]	=	($order['tax_opted_out'])? do_lang('YES'):do_lang('NO');
			$orders[do_lang('TOTAL_TAX_PAID')]		=	is_null($order['tax_amt'])?float_format(0.0):float_format($order['tax_amt']);
			$orders[do_lang('ORDERED_PRODUCTS')]	=	get_ordered_product_list_string($order['id']);
			$orders[do_lang('ORDERED_BY')]			=	$GLOBALS['FORUM_DRIVER']->get_username($order['c_member']);

			$address=array();
			$address['name']=	(array_key_exists('address_name',$order))?$order['address_name']:NULL;
			$address['city']=	(array_key_exists('address_city',$order))?$order['address_city']:NULL;
			$address['zip']=	(array_key_exists('address_zip',$order))?$order['address_zip']:NULL;
			$address['country']=	(array_key_exists('address_country',$order))?$order['address_country']:NULL;

			if(!is_null($address['name']))
				$full_address	=	implode(chr(10),$address);
			else
				$full_address	=	"";

			$orders[do_lang('FULL_ADDRESS')]			=	$full_address;

			$data[]	=	$orders;
		}

		require_code('files2');
		make_csv($data,$filename,!$inline,!$inline);
	}
}


