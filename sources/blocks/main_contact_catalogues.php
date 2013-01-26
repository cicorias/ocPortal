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
 * @package		catalogues
 */

class Block_main_contact_catalogues
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
		$info['parameters']=array('to','param','subject','body_prefix','body_suffix','subject_prefix','subject_suffix');
		return $info;
	}

	/**
	 * Standard modular cache function.
	 *
	 * @return ?array	Map of cache details (cache_on and ttl) (NULL: module is disabled).
	 */
	function cacheing_environment()
	{
		$info=array();
		$info['cache_on']='(post_param(\'subject\',\'\')!=\'\')?NULL:array(array_key_exists(\'param\',$map)?$map[\'param\']:\'\',array_key_exists(\'to\',$map)?$map[\'to\']:\'\',array_key_exists(\'subject\',$map)?$map[\'subject\']:\'\',array_key_exists(\'body_prefix\',$map)?$map[\'body_prefix\']:\'\',array_key_exists(\'body_suffix\',$map)?$map[\'body_suffix\']:\'\',array_key_exists(\'subject_prefix\',$map)?$map[\'subject_prefix\']:\'\',array_key_exists(\'subject_suffix\',$map)?$map[\'subject_suffix\']:\'\')';
		$info['ttl']=60*24*7;
		return $info;
	}

	/**
	 * Standard modular run function.
	 *
	 * @param  array		A map of parameters.
	 * @return tempcode	The result of execution.
	 */
	function run($map)
	{
		$catalogue_name=array_key_exists('param',$map)?$map['param']:'';
		if ($catalogue_name=='') $catalogue_name=$GLOBALS['SITE_DB']->query_value('catalogues','c_name'); // Random/arbitrary (first one that comes out of the DB)

		$subject=array_key_exists('subject',$map)?$map['subject']:'';
		if ($subject=='')
			$subject=get_translated_text($GLOBALS['SITE_DB']->query_value('catalogues','c_title'));

		$body_prefix=array_key_exists('body_prefix',$map)?$map['body_prefix']:'';
		$body_suffix=array_key_exists('body_suffix',$map)?$map['body_suffix']:'';
		$subject_prefix=array_key_exists('subject_prefix',$map)?$map['subject_prefix']:'';
		$subject_suffix=array_key_exists('subject_suffix',$map)?$map['subject_suffix']:'';

		if (post_param('subject','')!='')
		{
			require_code('mail');
			$to_email=array_key_exists('to',$map)?$map['to']:'';
			if ($to_email=='') $to_email=NULL;
			form_to_email($subject_prefix.$subject.$subject_suffix,$body_prefix,NULL,$to_email,$body_suffix);

			attach_message(do_lang_tempcode('SUCCESS'));
		}

		require_code('form_templates');

		$fields=new ocp_tempcode();

		$special_fields=$GLOBALS['SITE_DB']->query_select('catalogue_fields',array('*'),array('c_name'=>$catalogue_name),'ORDER BY cf_order');

		$field_groups=array();

		$hidden=new ocp_tempcode();

		require_code('fields');
		foreach ($special_fields as $field_num=>$field)
		{
			$ob=get_fields_hook($field['cf_type']);
			$default=$field['cf_default'];

			$_cf_name=get_translated_text($field['cf_name']);
			$field_cat='';
			$matches=array();
			if (strpos($_cf_name,': ')!==false)
			{
				$field_cat=substr($_cf_name,0,strpos($_cf_name,': '));
				if ($field_cat.': '==$_cf_name)
				{
					$_cf_name=$field_cat; // Just been pulled out as heading, nothing after ": "
				} else
				{
					$_cf_name=substr($_cf_name,strpos($_cf_name,': ')+2);
				}
			}
			if (!array_key_exists($field_cat,$field_groups)) $field_groups[$field_cat]=new ocp_tempcode();

			$_cf_description=escape_html(get_translated_text($field['cf_description']));

			$GLOBALS['NO_DEV_MODE_FULLSTOP_CHECK']=true;
			$result=$ob->get_field_inputter($_cf_name,$_cf_description,$field,$default,true,!array_key_exists($field_num+1,$special_fields));
			$GLOBALS['NO_DEV_MODE_FULLSTOP_CHECK']=false;

			if (is_null($result)) continue;

			if (is_array($result))
			{
				$field_groups[$field_cat]->attach($result[0]);
			} else
			{
				$field_groups[$field_cat]->attach($result);
			}

			$hidden->attach(form_input_hidden('label_for__field_'.strval($field['id']),$_cf_name));

			unset($result);
			unset($ob);
		}

		if (array_key_exists('',$field_groups)) // Blank prefix must go first
		{
			$field_groups_blank=$field_groups[''];
			unset($field_groups['']);
			$field_groups=array_merge(array($field_groups_blank),$field_groups);
		}
		foreach ($field_groups as $field_group_title=>$extra_fields)
		{
			if (is_integer($field_group_title)) $field_group_title=($field_group_title==0)?'':strval($field_group_title);

			if ($field_group_title!='')
				$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('_GUID'=>'c0b9f22ef5767da57a1ff65c06af96a1','TITLE'=>$field_group_title)));
			$fields->attach($extra_fields);
		}

		$hidden->attach(form_input_hidden('subject',$subject));

		$url=get_self_url();

		return do_template('FORM',array('_GUID'=>'7dc3957edf3b47399b688d72fae54128','FIELDS'=>$fields,'HIDDEN'=>$hidden,'SUBMIT_NAME'=>do_lang_tempcode('SEND'),'URL'=>$url,'TEXT'=>''));
	}

}
