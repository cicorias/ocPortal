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
 * @package		news
 */

class Hook_content_meta_aware_news_category
{

	/**
	 * Standard modular info function for award hooks. Provides information to allow task reporting, randomisation, and add-screen linking, to function.
	 *
	 * @param  ?ID_TEXT	The zone to link through to (NULL: autodetect).
	 * @return ?array		Map of award content-type info (NULL: disabled).
	 */
	function info($zone=NULL)
	{
		return array(
			'supports_custom_fields'=>false,

			'content_type_label'=>'news:NEWS_CATEGORY',

			'connection'=>$GLOBALS['SITE_DB'],
			'table'=>'news_categories',
			'id_field'=>'id',
			'id_field_numeric'=>true,
			'parent_category_field'=>NULL,
			'parent_category_meta_aware_type'=>'news_category',
			'is_category'=>true,
			'is_entry'=>false,
			'category_type'=>'news', // For category permissions
			'category_field'=>NULL, // For category permissions
			'category_is_string'=>false,

			'title_field'=>'nc_title',
			'title_field_dereference'=>true,

			'view_pagelink_pattern'=>'_SEARCH:news:misc:_WILD',
			'edit_pagelink_pattern'=>'_SEARCH:cms_news:_ec:_WILD',
			'view_category_pagelink_pattern'=>'_SEARCH:news:misc:_WILD',
			'add_url'=>(has_submit_permission('mid',get_member(),get_ip_address(),'cms_news'))?(get_module_zone('cms_news').':cms_news:ad'):NULL,
			'archive_url'=>((!is_null($zone))?$zone:get_module_zone('news')).':news',

			'support_url_monikers'=>true,

			'views_field'=>NULL,
			'submitter_field'=>NULL,
			'add_time_field'=>NULL,
			'edit_time_field'=>NULL,
			'date_field'=>NULL,
			'validated_field'=>NULL,

			'seo_type_code'=>'news_category',

			'feedback_type_code'=>NULL,

			'permissions_type_code'=>'news', // NULL if has no permissions

			'search_hook'=>NULL,

			'addon_name'=>'news',

			'cms_page'=>'cms_news',
			'module'=>'news',

			'occle_filesystem_hook'=>NULL, // TODO, #218 on tracker

			'rss_hook'=>NULL,

			'actionlog_regexp'=>'\w+_NEWS_CATEGORY',
		);
	}

	/**
	 * Standard modular run function for award hooks. Renders a content box for an award/randomisation.
	 *
	 * @param  array		The database row for the content
	 * @param  ID_TEXT	The zone to display in
	 * @param  boolean	Whether to include context (i.e. say WHAT this is, not just show the actual content)
	 * @param  boolean	Whether to include breadcrumbs (if there are any)
	 * @param  ?ID_TEXT	Virtual root to use (NULL: none)
	 * @param  boolean	Whether to copy through any filter parameters in the URL, under the basis that they are associated with what this box is browsing
	 * @param  ID_TEXT	Overridden GUID to send to templates (blank: none)
	 * @return tempcode	Results
	 */
	function run($row,$zone,$give_context=true,$include_breadcrumbs=true,$root=NULL,$attach_to_url_filter=false,$guid='')
	{
		require_code('news');

		return render_news_category_box($row,$zone,$give_context,$attach_to_url_filter,NULL,$guid);
	}

}
