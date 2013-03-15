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
 * @package		core
 */

/*

The splurgh functions build up a special compressed format of a tree structure, that is output
as javascript code. Javascript functions in the javascript file interpret the compressed format,
expanding it into a full uncompressed page.

*/

/**
 * Get a splurghified version of the specified item.
 *
 * @param  string			The name of what the key we want to reference is in our array of maps (e.g. 'id')
 * @param  array			A row of maps for data we are splurghing; this is probably just the result of $GLOBALS['SITE_DB']->query_select
 * @param  URLPATH		The stub that links will be passed through
 * @param  ID_TEXT		The page name we will be saving customised HTML under
 * @param  TIME			The time we did our last change to the data being splurghed (so it can see if we can simply decache instead of deriving)
 * @param  ?AUTO_LINK	The ID that is at the root of our tree (NULL: db_get_first_id)
 * @return string			A string of HTML that represents our splurghing (will desplurgh in the users browser)
 */
function splurgh_master_build($key_name,$map,$url_stub,$_cache_file,$last_change_time,$first_id=NULL)
{
	if (is_null($first_id)) $first_id=db_get_first_id();

	if (!array_key_exists($first_id,$map)) return '';

	if (!has_js()) warn_exit(do_lang_tempcode('MSG_JS_NEEDED'));

	require_javascript('javascript_splurgh');

	if (is_browser_decacheing())
		$last_change_time=time();

	$cache_file=zone_black_magic_filterer(get_custom_file_base().'/'.get_zone_name().'/pages/html_custom/'.filter_naughty(user_lang()).'/'.filter_naughty($_cache_file).'.htm');

	if ((!file_exists($cache_file)) || (is_browser_decacheing()) || (filesize($cache_file)==0) || ($last_change_time>filemtime($cache_file)))
	{
		$myfile=@fopen($cache_file,'wt');
		if ($myfile===false) intelligent_write_error($cache_file);
		$fulltable=array();
		$splurgh=_splurgh_do_node($map,$first_id,'',$fulltable,0);
		$page=do_template('SPLURGH',array('_GUID'=>'8775edfc5a386fdf2cec69b0fc889952','KEY_NAME'=>$key_name,'URL_STUB'=>$url_stub,'SPLURGH'=>str_replace('"','\'',$splurgh)));
		$ev=$page->evaluate();
		if (fwrite($myfile,$ev)<strlen($ev)) warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));
		fclose($myfile);
		fix_permissions($cache_file);
		sync_file($cache_file);

		return $ev;
	}

	return file_get_contents($cache_file,FILE_TEXT);
}

/**
 * Build up the splurgh nodes recursively for given details.
 *
 * @param  array			A row of maps for data we are splurghing; this is probably just the result of $GLOBALS['SITE_DB']->query_select
 * @param  AUTO_LINK		The node we are examining
 * @param  string			The chain we have built up during our recursion
 * @param  array			Nodes marked as done (so we don't repeat them in other hierarchy positions if it they get repeated)
 * @param  integer		The level of recursion
 * @return string			A specially encoded string that represents our splurghing
 */
function _splurgh_do_node($map,$node,$chain,&$fulltable,$nest)
{
	$fulltable[$node]=1;

	$title=$map[$node]['title'];
	$children=$map[$node]['children'];

	$out=strval($node).'!'.str_replace('[','&#91;',str_replace(']','&#93;',str_replace(',','&#44;',$title))).',';
	if (count($children)>0)
	{
		$out.='[';
		foreach ($children as $child)
		{
			if ((!array_key_exists($child,$fulltable)) && (array_key_exists($child,$map)))
				$out.=_splurgh_do_node($map,$child,$chain.strval($node).'~',$fulltable,$nest+1);
		}
		$out.='],';
	}

	return $out;
}


