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
 * @package		calendar
 */

require_code('resource_fs');

class Hook_occle_fs_event extends resource_fs_base
{
	var $folder_resource_type='calendar_type';
	var $file_resource_type='event';

	/**
	 * Standard modular introspection function.
	 *
	 * @return array			The properties available for the resource type
	 */
	function _enumerate_folder_properties()
	{
		return array(
			'logo'=>'URLPATH',
			'external_feed'=>'URLPATH',
		);
	}

	/**
	 * Standard modular date fetch function for resource-fs hooks. Defined when getting an edit date is not easy.
	 *
	 * @param  array			Resource row (not full, but does contain the ID)
	 * @return ?TIME			The edit date or add date, whichever is higher (NULL: could not find one)
	 */
	function _get_folder_edit_date($row)
	{
		$query='SELECT MAX(date_and_time) FROM '.get_table_prefix().'adminlogs WHERE '.db_string_equal_to('param_a',strval($row['id'])).' AND  ('.db_string_equal_to('the_type','ADD_EVENT_TYPE').' OR '.db_string_equal_to('the_type','EDIT_EVENT_TYPE').')';
		return $GLOBALS['SITE_DB']->query_value_if_there($query);
	}

	/**
	 * Standard modular add function for resource-fs hooks. Adds some resource with the given label and properties.
	 *
	 * @param  SHORT_TEXT	Filename OR Resource label
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return ~ID_TEXT		The resource ID (false: error)
	 */
	function folder_add($filename,$path,$properties)
	{
		list($category_resource_type,$category)=$this->folder_convert_filename_to_id($path);
		if ($category!='') return false; // Only one depth allowed for this resource type

		list($properties,$label)=$this->_folder_magic_filter($filename,$path,$properties);

		require_code('calendar2');

		$logo=$this->_default_property_str($properties,'logo');
		$external_feed=$this->_default_property_str($properties,'external_feed');
		$id=add_event_type($label,$logo,$external_feed);
		return strval($id);
	}

	/**
	 * Standard modular load function for resource-fs hooks. Finds the properties for some resource.
	 *
	 * @param  SHORT_TEXT	Filename
	 * @param  string			The path (blank: root / not applicable)
	 * @return ~array			Details of the resource (false: error)
	 */
	function folder_load($filename,$path)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		$rows=$GLOBALS['SITE_DB']->query_select('calendar_types',array('*'),array('id'=>intval($resource_id)),'',1);
		if (!array_key_exists(0,$rows)) return false;
		$row=$rows[0];

		return array(
			'label'=>$row['t_title'],
			'logo'=>$row['t_logo'],
			'external_feed'=>$row['t_external_feed'],
		);
	}

	/**
	 * Standard modular edit function for resource-fs hooks. Edits the resource to the given properties.
	 *
	 * @param  ID_TEXT		The filename
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return boolean		Success status
	 */
	function folder_edit($filename,$path,$properties)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		require_code('calendar2');

		$label=$this->_default_property_str($properties,'label');
		$logo=$this->_default_property_str($properties,'logo');
		$external_feed=$this->_default_property_str($properties,'external_feed');

		edit_event_type(intval($resource_id),$label,$logo,$external_feed);

		return true;
	}

	/**
	 * Standard modular delete function for resource-fs hooks. Deletes the resource.
	 *
	 * @param  ID_TEXT		The filename
	 * @return boolean		Success status
	 */
	function folder_delete($filename)
	{
		list($resource_type,$resource_id)=$this->folder_convert_filename_to_id($filename);

		require_code('calendar2');
		delete_event_type(intval($resource_id));

		return true;
	}

	/**
	 * Standard modular introspection function.
	 *
	 * @return array			The properties available for the resource type
	 */
	function _enumerate_file_properties()
	{
		return array(
			'description'=>'LONG_TRANS',
			'start_year'=>'SHORT_INTEGER',
			'start_month'=>'SHORT_INTEGER',
			'start_day'=>'SHORT_INTEGER',
			'start_monthly_spec_type'=>'ID_TEXT',
			'start_hour'=>'?SHORT_INTEGER',
			'start_minute'=>'?SHORT_INTEGER',
			'end_year'=>'?SHORT_INTEGER',
			'end_month'=>'?SHORT_INTEGER',
			'end_day'=>'?SHORT_INTEGER',
			'end_monthly_spec_type'=>'ID_TEXT',
			'end_hour'=>'?SHORT_INTEGER',
			'end_minute'=>'?SHORT_INTEGER',
			'timezone'=>'ID_TEXT',
			'do_timezone_conv'=>'BINARY',
			'recurrence'=>'SHORT_TEXT',
			'recurrences'=>'?INTEGER',
			'seg_recurrences'=>'BINARY',
			'priority'=>'SHORT_INTEGER',
			'is_public'=>'BINARY',
			'validated'=>'BINARY',
			'allow_rating'=>'BINARY',
			'allow_comments'=>'SHORT_INTEGER',
			'allow_trackbacks'=>'BINARY',
			'notes'=>'LONG_TEXT',
			'views'=>'INTEGER',
			'meta_keywords'=>'LONG_TRANS',
			'meta_description'=>'LONG_TRANS',
			'submitter'=>'member',
			'add_date'=>'TIME',
			'edit_date'=>'?TIME',
		);
	}

	/**
	 * Standard modular date fetch function for resource-fs hooks. Defined when getting an edit date is not easy.
	 *
	 * @param  array			Resource row (not full, but does contain the ID)
	 * @return ?TIME			The edit date or add date, whichever is higher (NULL: could not find one)
	 */
	function _get_file_edit_date($row)
	{
		$query='SELECT MAX(date_and_time) FROM '.get_table_prefix().'adminlogs WHERE '.db_string_equal_to('param_a',strval($row['id'])).' AND  ('.db_string_equal_to('the_type','ADD_CALENDAR_EVENT').' OR '.db_string_equal_to('the_type','EDIT_CALENDAR_EVENT').')';
		return $GLOBALS['SITE_DB']->query_value_if_there($query);
	}

	/**
	 * Standard modular add function for resource-fs hooks. Adds some resource with the given label and properties.
	 *
	 * @param  SHORT_TEXT	Filename OR Resource label
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return ~ID_TEXT		The resource ID (false: error, could not create via these properties / here)
	 */
	function file_add($filename,$path,$properties)
	{
		list($category_resource_type,$category)=$this->folder_convert_filename_to_id($path);
		list($properties,$label)=$this->_file_magic_filter($filename,$path,$properties);

		if ($category=='') return false;

		require_code('calendar2');

		$type=$this->_integer_category($category);
		$recurrence=$this->_default_property_str($properties,'recurrence');
		$recurrences=$this->_default_property_int_null($properties,'recurrences');
		$seg_recurrences=$this->_default_property_int($properties,'seg_recurrences');
		$content=$this->_default_property_str($properties,'description');
		$priority=$this->_default_property_int_null($properties,'priority');
		if ($priority===NULL) $priority=3;
		$is_public=$this->_default_property_int_null($properties,'is_public');
		if ($is_public===NULL) $is_public=1;
		$start_year=$this->_default_property_int_null($properties,'start_year');
		if ($start_year===NULL) $start_year=intval(date('Y'));
		$start_month=$this->_default_property_int_null($properties,'start_month');
		if ($start_month===NULL) $start_month=intval(date('m'));
		$start_day=$this->_default_property_int_null($properties,'start_day');
		if ($start_day===NULL) $start_day=intval(date('d'));
		$start_monthly_spec_type=$this->_default_property_str($properties,'start_monthly_spec_type');
		if ($start_monthly_spec_type=='') $start_monthly_spec_type='day_of_month';
		$start_hour=$this->_default_property_int_null($properties,'start_hour');
		$start_minute=$this->_default_property_int_null($properties,'start_minute');
		$end_year=$this->_default_property_int_null($properties,'end_year');
		$end_month=$this->_default_property_int_null($properties,'end_month');
		$end_day=$this->_default_property_int_null($properties,'end_day');
		$end_monthly_spec_type=$this->_default_property_str($properties,'end_monthly_spec_type');
		if ($end_monthly_spec_type=='') $end_monthly_spec_type='day_of_month';
		$end_hour=$this->_default_property_int_null($properties,'end_hour');
		$end_minute=$this->_default_property_int_null($properties,'end_minute');
		$timezone=$this->_default_property_str_null($properties,'timezone');
		$do_timezone_conv=$this->_default_property_int($properties,'do_timezone_conv');
		$validated=$this->_default_property_int_null($properties,'validated');
		if (is_null($validated)) $validated=1;
		$allow_rating=$this->_default_property_int_modeavg($properties,'allow_rating','calendar_events',1);
		$allow_comments=$this->_default_property_int_modeavg($properties,'allow_comments','calendar_events',1);
		$allow_trackbacks=$this->_default_property_int_modeavg($properties,'allow_trackbacks','calendar_events',1);
		$notes=$this->_default_property_str($properties,'notes');
		$submitter=$this->_default_property_int_null($properties,'submitter');
		$views=$this->_default_property_int($properties,'views');
		$add_time=$this->_default_property_int_null($properties,'add_date');
		$edit_time=$this->_default_property_int_null($properties,'edit_date');
		$meta_keywords=$this->_default_property_str($properties,'meta_keywords');
		$meta_description=$this->_default_property_str($properties,'meta_description');
		$id=add_calendar_event($type,$recurrence,$recurrences,$seg_recurrences,$label,$content,$priority,$is_public,$start_year,$start_month,$start_day,$start_monthly_spec_type,$start_hour,$start_minute,$end_year,$end_month,$end_day,$end_monthly_spec_type,$end_hour,$end_minute,$timezone,$do_timezone_conv,$validated,$allow_rating,$allow_comments,$allow_trackbacks,$notes,$submitter,$views,$add_time,$edit_time,NULL,$meta_keywords,$meta_description);
		return strval($id);
	}

	/**
	 * Standard modular load function for resource-fs hooks. Finds the properties for some resource.
	 *
	 * @param  SHORT_TEXT	Filename
	 * @param  string			The path (blank: root / not applicable)
	 * @return ~array			Details of the resource (false: error)
	 */
	function file_load($filename,$path)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		$rows=$GLOBALS['SITE_DB']->query_select('calendar_events',array('*'),array('id'=>intval($resource_id)),'',1);
		if (!array_key_exists(0,$rows)) return false;
		$row=$rows[0];

		list($meta_keywords,$meta_description)=seo_meta_get_for('events',strval($row['id']));

		return array(
			'label'=>$row['e_title'],
			'description'=>$row['e_description'],
			'start_year'=>$row['e_start_year'],
			'start_month'=>$row['e_start_month'],
			'start_day'=>$row['e_start_day'],
			'start_monthly_spec_type'=>$row['e_start_monthly_spec_type'],
			'start_hour'=>$row['e_start_hour'],
			'start_minute'=>$row['e_start_minute'],
			'end_year'=>$row['e_end_year'],
			'end_month'=>$row['e_end_month'],
			'end_day'=>$row['e_end_day'],
			'end_monthly_spec_type'=>$row['e_end_monthly_spec_type'],
			'end_hour'=>$row['e_end_hour'],
			'end_minute'=>$row['e_end_minute'],
			'timezone'=>$row['e_timezone'],
			'do_timezone_conv'=>$row['e_do_timezone_conv'],
			'recurrence'=>$row['e_recurrence'],
			'recurrences'=>$row['e_recurrences'],
			'seg_recurrences'=>$row['e_seg_recurrences'],
			'priority'=>$row['e_priority'],
			'is_public'=>$row['e_is_public'],
			'validated'=>$row['e_validated'],
			'allow_rating'=>$row['e_allow_rating'],
			'allow_comments'=>$row['e_allow_comments'],
			'allow_trackbacks'=>$row['e_allow_trackbacks'],
			'notes'=>$row['e_notes'],
			'views'=>$row['e_views'],
			'meta_keywords'=>$meta_keywords,
			'meta_description'=>$meta_description,
			'submitter'=>$row['e_submitter'],
			'add_date'=>$row['e_add_date'],
			'edit_date'=>$row['e_edit_date'],
		);
	}

	/**
	 * Standard modular edit function for resource-fs hooks. Edits the resource to the given properties.
	 *
	 * @param  ID_TEXT		The filename
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return boolean		Success status
	 */
	function file_edit($filename,$path,$properties)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);
		list($category_resource_type,$category)=$this->folder_convert_filename_to_id($path);
		list($properties,)=$this->_file_magic_filter($filename,$path,$properties);

		require_code('calendar2');

		$label=$this->_default_property_str($properties,'label');
		$type=$this->_integer_category($category);
		$recurrence=$this->_default_property_str($properties,'recurrence');
		$recurrences=$this->_default_property_int_null($properties,'recurrences');
		$seg_recurrences=$this->_default_property_int($properties,'seg_recurrences');
		$content=$this->_default_property_str($properties,'description');
		$priority=$this->_default_property_int_null($properties,'priority');
		if ($priority===NULL) $priority=3;
		$is_public=$this->_default_property_int_null($properties,'is_public');
		if ($is_public===NULL) $is_public=1;
		$start_year=$this->_default_property_int_null($properties,'start_year');
		if ($start_year===NULL) $start_year=intval(date('Y'));
		$start_month=$this->_default_property_int_null($properties,'start_month');
		if ($start_month===NULL) $start_month=intval(date('m'));
		$start_day=$this->_default_property_int_null($properties,'start_day');
		if ($start_day===NULL) $start_day=intval(date('d'));
		$start_monthly_spec_type=$this->_default_property_str($properties,'start_monthly_spec_type');
		if ($start_monthly_spec_type=='') $start_monthly_spec_type='day_of_month';
		$start_hour=$this->_default_property_int_null($properties,'start_hour');
		$start_minute=$this->_default_property_int_null($properties,'start_minute');
		$end_year=$this->_default_property_int_null($properties,'end_year');
		$end_month=$this->_default_property_int_null($properties,'end_month');
		$end_day=$this->_default_property_int_null($properties,'end_day');
		$end_monthly_spec_type=$this->_default_property_str($properties,'end_monthly_spec_type');
		if ($end_monthly_spec_type=='') $end_monthly_spec_type='day_of_month';
		$end_hour=$this->_default_property_int_null($properties,'end_hour');
		$end_minute=$this->_default_property_int_null($properties,'end_minute');
		$timezone=$this->_default_property_str_null($properties,'timezone');
		$do_timezone_conv=$this->_default_property_int($properties,'do_timezone_conv');
		$validated=$this->_default_property_int_null($properties,'validated');
		if (is_null($validated)) $validated=1;
		$allow_rating=$this->_default_property_int_modeavg($properties,'allow_rating','calendar_events',1);
		$allow_comments=$this->_default_property_int_modeavg($properties,'allow_comments','calendar_events',1);
		$allow_trackbacks=$this->_default_property_int_modeavg($properties,'allow_trackbacks','calendar_events',1);
		$notes=$this->_default_property_str($properties,'notes');
		$submitter=$this->_default_property_int_null($properties,'submitter');
		$views=$this->_default_property_int($properties,'views');
		$add_time=$this->_default_property_int_null($properties,'add_date');
		$edit_time=$this->_default_property_int_null($properties,'edit_date');
		$meta_keywords=$this->_default_property_str($properties,'meta_keywords');
		$meta_description=$this->_default_property_str($properties,'meta_description');

		edit_calendar_event(intval($resource_id),$type,$recurrence,$recurrences,$seg_recurrences,$label,$content,$priority,$is_public,$start_year,$start_month,$start_day,$start_monthly_spec_type,$start_hour,$start_minute,$end_year,$end_month,$end_day,$end_monthly_spec_type,$end_hour,$end_minute,$timezone,$do_timezone_conv,$meta_keywords,$meta_description,$validated,$allow_rating,$allow_comments,$allow_trackbacks,$notes,$edit_time,$add_time,$views,$submitter,$null_is_literal);

		return true;
	}

	/**
	 * Standard modular delete function for resource-fs hooks. Deletes the resource.
	 *
	 * @param  ID_TEXT		The filename
	 * @return boolean		Success status
	 */
	function file_delete($filename)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		require_code('calendar2');
		delete_calendar_event(intval($resource_id));

		return true;
	}
}
