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
 * @package		core
 */

/**
 * Standard code module initialisation function.
 */
function init__config()
{
	global $VALUE_OPTIONS_CACHE,$IN_MINIKERNEL_VERSION;
	if ($IN_MINIKERNEL_VERSION==0)
	{
		load_options();

		$VALUE_OPTIONS_CACHE=persistent_cache_get('VALUES');
		if ($VALUE_OPTIONS_CACHE===NULL)
		{
			$VALUE_OPTIONS_CACHE=$GLOBALS['SITE_DB']->query_select('values',array('*'));
			$VALUE_OPTIONS_CACHE=list_to_map('the_name',$VALUE_OPTIONS_CACHE);
			persistent_cache_set('VALUES',$VALUE_OPTIONS_CACHE);
		}
	} else $VALUE_OPTIONS_CACHE=array();

	global $GET_OPTION_LOOP;
	$GET_OPTION_LOOP=0;

	global $MULTI_LANG_CACHE;
	$MULTI_LANG_CACHE=NULL;

	// Enforce XML db synching
	if ((get_db_type()=='xml') && (!running_script('xml_db_import')) && (is_file(get_file_base().'/data_custom/xml_db_import.php')) && (is_dir(get_file_base().'/.svn')))
	{
		$last_xml_import=get_value('last_xml_import');
		$mod_time=filemtime(get_file_base().'/.svn');
		if ((is_null($last_xml_import)) || (intval($last_xml_import)<$mod_time))
		{
			set_value('last_xml_import',strval(time()));

			header('Location: '.get_base_url().'/data_custom/xml_db_import.php');
			exit();
		}
	}
}

/**
 * Find whether to run in multi-lang mode.
 *
 * @return boolean		Whether to run in multi-lang mode.
 */
function multi_lang()
{
	global $MULTI_LANG_CACHE;
	if ($MULTI_LANG_CACHE!==NULL) return $MULTI_LANG_CACHE;
	$MULTI_LANG_CACHE=false;
	if (get_option('allow_international',true)!=='1') return false;

	require_code('config2');
	return _multi_lang();
}

/**
 * Load all config options.
 */
function load_options()
{
	global $CONFIG_OPTIONS_CACHE,$CONFIG_OPTIONS_BEING_CACHED;
	$CONFIG_OPTIONS_BEING_CACHED=true;
	$CONFIG_OPTIONS_CACHE=function_exists('persistent_cache_get')?persistent_cache_get('OPTIONS'):NULL;
	if (is_array($CONFIG_OPTIONS_CACHE)) return;
	$CONFIG_OPTIONS_BEING_CACHED=false;
	if (strpos(get_db_type(),'mysql')!==false)
	{
		global $SITE_INFO;
		$CONFIG_OPTIONS_CACHE=$GLOBALS['SITE_DB']->query_select('config c LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'translate t ON (c.config_value=t.id AND '.db_string_equal_to('t.language',array_key_exists('default_lang',$SITE_INFO)?$SITE_INFO['default_lang']:'EN').' AND ('.db_string_equal_to('c.the_type','transtext').' OR '.db_string_equal_to('c.the_type','transline').'))',array('c.the_name','c.config_value','c.the_type','c.c_set','t.text_original AS config_value_translated','(case c_set when 0 then eval else \'\' end) AS eval'),array(),'',NULL,NULL,true);
	} else
	{
		$CONFIG_OPTIONS_CACHE=$GLOBALS['SITE_DB']->query_select('config',array('the_name','config_value','the_type','c_set'),NULL,'',NULL,NULL,true);
	}

	if ($CONFIG_OPTIONS_CACHE===NULL) critical_error('DATABASE_FAIL');
	$CONFIG_OPTIONS_CACHE=list_to_map('the_name',$CONFIG_OPTIONS_CACHE);
	if (function_exists('persistent_cache_set')) persistent_cache_set('OPTIONS',$CONFIG_OPTIONS_CACHE);
}

/**
 * Find a specified long value. Long values are either really long strings, or just ones you don't want on each page load (i.e. it takes a query to read them, because you don't always need them).
 *
 * @param  ID_TEXT		The name of the value
 * @return ?SHORT_TEXT	The value (NULL: value not found)
 */
function get_long_value($name)
{
	return $GLOBALS['SITE_DB']->query_select_value_if_there('long_values','the_value',array('the_name'=>$name),'',running_script('install'));
}

/**
 * Find the specified configuration option if it is younger than a specified time.
 *
 * @param  ID_TEXT		The name of the value
 * @param  TIME			The cutoff time (an absolute time, not a relative "time ago")
 * @return ?SHORT_TEXT	The value (NULL: value newer than not found)
 */
function get_long_value_newer_than($name,$cutoff)
{
	return $GLOBALS['SITE_DB']->query_value_if_there('SELECT the_value FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'long_values WHERE date_and_time>'.strval($cutoff).' AND '.db_string_equal_to('the_name',$name));
}

/**
 * Set the specified situational value to the specified long value. Long values are either really long strings, or just ones you don't want on each page load (i.e. it takes a query to read them, because you don't always need them).
 *
 * @param  ID_TEXT		The name of the value
 * @param  ?SHORT_TEXT	The value (NULL: delete it)
 */
function set_long_value($name,$value)
{
	$GLOBALS['SITE_DB']->query_delete('long_values',array('the_name'=>$name),'',1);
	if ($value!==NULL)
	{
		$GLOBALS['SITE_DB']->query_insert('long_values',array('date_and_time'=>time(),'the_value'=>$value,'the_name'=>$name));
	}
}

/**
 * Find a specified value.
 *
 * @param  ID_TEXT		The name of the value
 * @param  ?ID_TEXT		Value to return if value not found (NULL: return NULL)
 * @param  boolean		Whether to also check server environmental variables
 * @return ?SHORT_TEXT	The value (NULL: value not found and default is NULL)
 */
function get_value($name,$default=NULL,$env_also=false)
{
	global $IN_MINIKERNEL_VERSION,$VALUE_OPTIONS_CACHE;
	if ($IN_MINIKERNEL_VERSION==1) return $default;

	if (isset($VALUE_OPTIONS_CACHE[$name])) return $VALUE_OPTIONS_CACHE[$name]['the_value'];

	if ($env_also)
	{
		$value=getenv($name);
		if (($value!==false) && ($value!='')) return $value;
	}

	return $default;
}

/**
 * Find the specified configuration option if it is younger than a specified time.
 *
 * @param  ID_TEXT		The name of the value
 * @param  TIME			The cutoff time (an absolute time, not a relative "time ago")
 * @return ?SHORT_TEXT	The value (NULL: value newer than not found)
 */
function get_value_newer_than($name,$cutoff)
{
	$cutoff-=mt_rand(0,200); // Bit of scattering to stop locking issues if lots of requests hit this at once in the middle of a hit burst (whole table is read each page requests, and mysql will lock the table on set_value - causes horrible out-of-control buildups)

	global $VALUE_OPTIONS_CACHE;
	if ((array_key_exists($name,$VALUE_OPTIONS_CACHE)) && ($VALUE_OPTIONS_CACHE[$name]['date_and_time']>$cutoff)) return $VALUE_OPTIONS_CACHE[$name]['the_value'];
	return NULL;
}

/**
 * Set the specified situational value to the specified value.
 *
 * @param  ID_TEXT		The name of the value
 * @param  SHORT_TEXT	The value
 */
function set_value($name,$value)
{
	global $VALUE_OPTIONS_CACHE;
	$existed_before=array_key_exists($name,$VALUE_OPTIONS_CACHE);
	$VALUE_OPTIONS_CACHE[$name]['the_value']=$value;
	$VALUE_OPTIONS_CACHE[$name]['date_and_time']=time();
	if ($existed_before)
	{
		$GLOBALS['SITE_DB']->query_update('values',array('date_and_time'=>time(),'the_value'=>$value),array('the_name'=>$name),'',1,NULL,false,true);
	} else
	{
		$GLOBALS['SITE_DB']->query_insert('values',array('date_and_time'=>time(),'the_value'=>$value,'the_name'=>$name),false,true); // Allow failure, if there is a race condition
	}
	if (function_exists('persistent_cache_set')) persistent_cache_set('VALUES',$VALUE_OPTIONS_CACHE);
}

/**
 * Delete a situational value.
 *
 * @param  ID_TEXT		The name of the value
 */
function delete_value($name)
{
	$GLOBALS['SITE_DB']->query_delete('values',array('the_name'=>$name),'',1);
	if (function_exists('persistent_cache_delete')) persistent_cache_delete('VALUES');
	global $VALUE_OPTIONS_CACHE;
	unset($VALUE_OPTIONS_CACHE[$name]);
}

/**
 * Find the value of the specified configuration option.
 *
 * @param  ID_TEXT		The name of the option
 * @param  boolean		Where to accept a missing option (and return NULL)
 * @return ?SHORT_TEXT	The value (NULL: either null value, or no option found whilst $missing_ok set)
 */
function get_option($name,$missing_ok=false)
{
	global $CONFIG_OPTIONS_CACHE;

	if (!isset($CONFIG_OPTIONS_CACHE[$name]))
	{
		if ($missing_ok) return NULL;
		require_code('config2');
		find_lost_option($name);
	}

	$option=&$CONFIG_OPTIONS_CACHE[$name];

	// The master of redundant quick exit points. Has to be after the above IF due to weird PHP isset/NULL bug on some 5.1.4 (and possibly others)
	if (isset($option['config_value_translated']))
	{
		if ($option['config_value_translated']=='<null>') return NULL;
		return $option['config_value_translated'];
	}

	// Redundant, quick exit points
	$type=$option['the_type'];
	if (!isset($option['c_set'])) $option['c_set']=($option['config_value']===NULL)?0:1; // for compatibility during upgrades
	if (($option['c_set']==1) && ($type!='transline') && ($type!='transtext'))
	{
		$option['config_value_translated']=$option['config_value']; // Allows slightly better code path next time
		if ($option['config_value_translated']===NULL) $option['config_value_translated']='<null>';
		$CONFIG_OPTIONS_CACHE[$name]=$option;
		global $CONFIG_OPTIONS_BEING_CACHED;
		if ($CONFIG_OPTIONS_BEING_CACHED) persistent_cache_set('OPTIONS',$CONFIG_OPTIONS_CACHE);
		if ($option['config_value']=='<null>') return NULL;
		return $option['config_value'];
	}

	global $GET_OPTION_LOOP;
	$GET_OPTION_LOOP=1;

	// Find default if not set
	if ($option['c_set']==0)
	{
		require_code('config2');
		return _get_default_option($option,$type,$name);
	}

	// Translations if needed
	if (($type=='transline') || ($type=='transtext'))
	{
		if (!isset($option['config_value_translated']))
		{
			$option['config_value_translated']=get_translated_text(intval($option['config_value']));
			$CONFIG_OPTIONS_CACHE[$name]=$option;
			persistent_cache_set('OPTIONS',$CONFIG_OPTIONS_CACHE);
		}
		// Answer
		$GET_OPTION_LOOP=0;
		return $option['config_value_translated'];
	}

	// Answer
	$GET_OPTION_LOOP=0;
	return $option['config_value'];
}

/**
 * Increment the specified stored value, by the specified amount.
 *
 * @param  ID_TEXT		The codename for the stat
 * @param  integer		What to increment the statistic by
 */
function update_stat($stat,$increment)
{
	if (running_script('stress_test_loader')) return;

	$current=get_value($stat);
	if (is_null($current)) $current='0';
	$new=intval($current)+$increment;
	set_value($stat,strval($new));
}

/**
 * Very simple function to invert the meaning of an old hidden option. We often use this when we've promoted a hidden option into a new proper option but inverted the meaning in the process - we use this in the default value generation code, as an in-line aid to preserve existing hidden option settings.
 *
 * @param  ID_TEXT		The old value
 * @set 0 1
 * @return ID_TEXT		The inverted value
 */
function invert_value($old)
{
	if ($old=='1') return '0';
	return '1';
}

