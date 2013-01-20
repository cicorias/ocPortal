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
 * @package		occle
 */

class Hook_directory_sizes
{
	/**
	* Standard modular run function for OcCLE hooks.
	*
	* @param  array	The options with which the command was called
	* @param  array	The parameters with which the command was called
	* @param  object  A reference to the OcCLE filesystem object
	* @return array	Array of stdcommand, stdhtml, stdout, and stderr responses
	*/
	function run($options,$parameters,&$occle_fs)
	{
		if ((array_key_exists('h',$options)) || (array_key_exists('help',$options))) return array('',do_command_help('directory_sizes',array('h'),array(true,true)),'','');
		else
		{
			$sizes=array();
			require_code('files2');
			$dirs=get_directory_contents(get_custom_file_base(),'',false,true,false);
			foreach ($dirs as $dir)
			{
				$sizes[$dir]=get_directory_size(get_custom_file_base().'/'.$dir);
			}
			asort($sizes);

			require_code('files');

			$out='';
			$out.='<table class="results_table"><thead><tr><th>'.do_lang('NAME').'</th><th>'.do_lang('SIZE').'</th></tr></thead>';
			foreach ($sizes as $key=>$val)
			{
				$out.='<tr><td>'.escape_html(preg_replace('#^'.preg_quote(get_table_prefix(),'#').'#','',$key)).'</td><td>'.escape_html(clean_file_size($val)).'</td></tr>';
			}
			$out.='</table>';

			return array('',$out,'','');
		}
	}

}

