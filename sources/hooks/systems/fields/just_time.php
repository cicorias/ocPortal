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
 * @package		core_fields
 */

class Hook_fields_just_time
{

	// ==============
	// Module: search
	// ==============

	/**
	 * Get special Tempcode for inputting this field.
	 *
	 * @param  array			The row for the field to input
	 * @return ?array			List of specially encoded input detail rows (NULL: nothing special)
	 */
	function get_search_inputter($row)
	{
		return NULL;
	}

	/**
	 * Get special SQL from POSTed parameters for this field.
	 *
	 * @param  array			The row for the field to input
	 * @param  integer		We're processing for the ith row
	 * @return ?array			Tuple of SQL details (array: extra trans fields to search, array: extra plain fields to search, string: an extra table segment for a join, string: the name of the field to use as a title, if this is the title, extra WHERE clause stuff) (NULL: nothing special)
	 */
	function inputted_to_sql_for_search($row,$i)
	{
		return exact_match_sql($row,$i);
	}

	// ===================
	// Backend: fields API
	// ===================

	/**
	 * Get some info bits relating to our field type, that helps us look it up / set defaults.
	 *
	 * @param  ?array			The field details (NULL: new field)
	 * @param  ?boolean		Whether a default value cannot be blank (NULL: don't "lock in" a new default value)
	 * @param  ?string		The given default value as a string (NULL: don't "lock in" a new default value)
	 * @return array			Tuple of details (row-type,default-value-to-use,db row-type)
	 */
	function get_field_value_row_bits($field,$required=NULL,$default=NULL)
	{
		if (!is_null($required))
		{
			if (($required) && ($default=='')) $default=date('H:i:s',utctime_to_usertime());
		}
		return array('short_unescaped',$default,'short');
	}

	/**
	 * Convert a field value to something renderable.
	 *
	 * @param  array			The field details
	 * @param  mixed			The raw value
	 * @return mixed			Rendered field (tempcode or string)
	 */
	function render_field_value($field,$ev)
	{
		if (is_object($ev)) return $ev;

		if ($ev!='')
		{
			if (strpos(strtolower($ev),'now')!==false)
			{
				$time=time();
			} else
			{
				// Y-m-d H:i:s
				$time_bits=explode(':',$ev,3);
				if (!array_key_exists(1,$time_bits)) $time_bits[1]='00';
				if (!array_key_exists(2,$time_bits)) $time_bits[2]='00';
				$time=mktime(intval($time_bits[0]),intval($time_bits[1]),intval($time_bits[2]));
			}
			$ev=get_timezoned_time($time,false,NULL,true);
		}
		return escape_html($ev);
	}

	// ======================
	// Frontend: fields input
	// ======================

	/**
	 * Get form inputter.
	 *
	 * @param  string			The field name
	 * @param  string			The field description
	 * @param  array			The field details
	 * @param  ?string		The actual current value of the field (NULL: none)
	 * @param  boolean		Whether this is for a new entry
	 * @return ?tempcode		The Tempcode for the input field (NULL: skip the field - it's not input)
	 */
	function get_field_inputter($_cf_name,$_cf_description,$field,$actual_value,$new)
	{
		$time=mixed();

		if ((is_null($actual_value)) || ($actual_value==''))
		{
			$time=NULL;
		} elseif (strpos(strtolower($actual_value),'now')!==false)
		{
			$time=time();
		}
		else
		{
			// H:i:s
			$time_bits=explode(':',$actual_value,3);
			if (!array_key_exists(1,$time_bits)) $time_bits[1]='00';
			if (!array_key_exists(2,$time_bits)) $time_bits[2]='00';

			$time=array(intval($time_bits[1]),intval($time_bits[0]),intval(date('m')),intval(date('d')),intval(date('Y')));
		}
		return form_input_date($_cf_name,$_cf_description,'field_'.strval($field['id']),$field['cf_required']==0,($field['cf_required']==0) && ($actual_value==''),true,$time,1,1900,NULL,$field['cf_required']==1,false);
	}

	/**
	 * Find the posted value from the get_field_inputter field
	 *
	 * @param  boolean		Whether we were editing (because on edit, it could be a fractional edit)
	 * @param  array			The field details
	 * @param  string			Where the files will be uploaded to
	 * @param  ?string		Former value of field (NULL: none)
	 * @return string			The value
	 */
	function inputted_to_field_value($editing,$field,$upload_dir='uploads/catalogues',$old_value=NULL)
	{
		$id=$field['id'];
		$stub='field_'.strval($id);

		$hour=post_param_integer($stub.'_hour',0);
		$minute=post_param_integer($stub.'_minute',0);

		return strval($hour).':'.strval($minute);
	}

}


