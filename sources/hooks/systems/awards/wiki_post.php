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
 * @package		wiki
 */

class Hook_awards_wiki_post
{

	/**
	 * Standard modular info function for award hooks. Provides information to allow task reporting, randomisation, and add-screen linking, to function.
	 *
	 * @param  ?ID_TEXT	The zone to link through to (NULL: autodetect).
	 * @return ?array		Map of award content-type info (NULL: disabled).
	 */
	function info($zone=NULL)
	{
		$info=array();
		$info['connection']=$GLOBALS['SITE_DB'];
		$info['table']='wiki_posts';
		$info['date_field']='date_and_time';
		$info['id_field']='id';
		$info['add_url']=(has_submit_permission('low',get_member(),get_ip_address(),'wiki'))?build_url(array('page'=>'wiki','type'=>'add_post'),get_module_zone('wiki')):new ocp_tempcode();
		$info['category_field']='page_id';
		$info['category_type']='wiki_page';
		$info['parent_spec__table_name']='wiki_children';
		$info['parent_spec__parent_name']='parent_id';
		$info['parent_spec__field_name']='child_id';
		$info['parent_field_name']='page_id';
		$info['submitter_field']='the_user';
		$info['id_is_string']=false;
		require_lang('wiki');
		$info['title']=do_lang_tempcode('WIKI_POSTS');
		$info['validated_field']='validated';
		$info['category_is_string']=false;
		$info['archive_url']=build_url(array('page'=>'wiki'),(!is_null($zone))?$zone:get_module_zone('wiki'));
		$info['cms_page']='wiki';
		$info['supports_custom_fields']=true;

		return $info;
	}

	/**
	 * Standard modular run function for award hooks. Renders a content box for an award/randomisation.
	 *
	 * @param  array		The database row for the content
	 * @param  ID_TEXT	The zone to display in
	 * @return tempcode	Results
	 */
	function run($row,$zone)
	{
		require_code('wiki');

		return render_wiki_post_box($row,$zone);
	}

}

