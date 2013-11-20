<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2014

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		iotds
 */

class Hook_addon_registry_iotds
{
	/**
	 * Get a list of file permissions to set
	 *
	 * @return array			File permissions to set
	 */
	function get_chmod_array()
	{
		return array();
	}

	/**
	 * Get the version of ocPortal this addon is for
	 *
	 * @return float			Version number
	 */
	function get_version()
	{
		return ocp_version_number();
	}

	/**
	 * Get the addon category
	 *
	 * @return string			The category
	 */
	function get_category()
	{
		return 'New Features';
	}

	/**
	 * Get the addon author
	 *
	 * @return string			The author
	 */
	function get_author()
	{
		return 'Chris Graham';
	}

	/**
	 * Find other authors
	 *
	 * @return array			A list of co-authors that should be attributed
	 */
	function get_copyright_attribution()
	{
		return array();
	}

	/**
	 * Get the addon licence (one-line summary only)
	 *
	 * @return string			The licence
	 */
	function get_licence()
	{
		return 'Licensed on the same terms as ocPortal';
	}

	/**
	 * Get the description of the addon
	 *
	 * @return string			Description of the addon
	 */
	function get_description()
	{
		return 'Choose and display Images Of The Day.';
	}

	/**
	 * Get a list of tutorials that apply to this addon
	 *
	 * @return array			List of tutorials
	 */
	function get_applicable_tutorials()
	{
		return array(
			'tut_featured',
		);
	}

	/**
	 * Get a mapping of dependency types
	 *
	 * @return array			File permissions to set
	 */
	function get_dependencies()
	{
		return array(
			'requires'=>array(
			),
			'recommends'=>array(
			),
			'conflicts_with'=>array(
			)
		);
	}

	/**
	 * Explicitly say which icon should be used
	 *
	 * @return URLPATH		Icon
	 */
	function get_default_icon()
	{
		return 'themes/default/images_custom/icons/48x48/menu/rich_content/iotds.png';
	}

	/**
	 * Get a list of files that belong to this addon
	 *
	 * @return array			List of files
	 */
	function get_file_list()
	{
		return array(
			'themes/default/images_custom/icons/24x24/menu/rich_content/iotds.png',
			'themes/default/images_custom/icons/48x48/menu/rich_content/iotds.png',
			'sources/iotds2.php',
			'sources/hooks/systems/notifications/iotd_chosen.php',
			'sources/hooks/systems/config/iotd_update_time.php',
			'sources/hooks/systems/config/points_ADD_IOTD.php',
			'sources/hooks/systems/config/points_CHOOSE_IOTD.php',
			'sources/hooks/systems/content_meta_aware/iotd.php',
			'sources/hooks/systems/occle_fs/iotds.php',
			'sources/hooks/systems/addon_registry/iotds.php',
			'sources/hooks/modules/admin_setupwizard/iotds.php',
			'sources/hooks/modules/admin_import_types/iotds.php',
			'themes/default/templates_custom/IOTD_BOX.tpl',
			'themes/default/templates_custom/IOTD_ENTRY_SCREEN.tpl',
			'themes/default/templates_custom/BLOCK_MAIN_IOTD.tpl',
			'uploads/iotds/index.html',
			'uploads/iotds_thumbs/index.html',
			'themes/default/css_custom/iotds.css',
			'cms/pages/modules/cms_iotds.php',
			'lang/EN/iotds.ini',
			'site/pages/modules/iotds.php',
			'sources/blocks/main_iotd.php',
			'sources/hooks/blocks/main_staff_checklist/iotds.php',
			'sources/hooks/modules/search/iotds.php',
			'sources/hooks/systems/page_groupings/iotds.php',
			'sources/hooks/systems/rss/iotds.php',
			'sources/hooks/systems/trackback/iotds.php',
			'sources/iotds.php',
			'sources/hooks/systems/preview/iotd.php',
			'themes/default/templates_custom/IOTD_ADMIN_CHOOSE_SCREEN.tpl',
			'uploads/iotds/.htaccess',
			'uploads/iotds_thumbs/.htaccess',
			'sources_custom/hooks/systems/sitemap/.htaccess',
			'sources_custom/hooks/systems/sitemap/index.html',
			'sources_custom/hooks/systems/sitemap/iotd.php',
		);
	}


	/**
	 * Get mapping between template names and the method of this class that can render a preview of them
	 *
	 * @return array			The mapping
	 */
	function tpl_previews()
	{
		return array(
			'IOTD_ADMIN_CHOOSE_SCREEN.tpl'=>'administrative__iotd_admin_choose_screen',
			'BLOCK_MAIN_IOTD.tpl'=>'block_main_iotd',
			'IOTD_BOX.tpl'=>'iotd_view_screen_iotd',
			'IOTD_ENTRY_SCREEN.tpl'=>'iotd_view_screen'
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__administrative__iotd_admin_choose_screen()
	{
		$current_iotd=do_lorem_template('IOTD_BOX',array(
			'IS_CURRENT'=>placeholder_number(),
			'THUMB_URL'=>placeholder_image_url(),
			'IMAGE_URL'=>placeholder_image_url(),
			'VIEWS'=>placeholder_number(),
			'THUMB'=>placeholder_image(),
			'DATE'=>placeholder_time(),
			'DATE_RAW'=>placeholder_date_raw(),
			'VIEW_URL'=>placeholder_url(),
			'ID'=>placeholder_id(),
			'EDIT_URL'=>placeholder_url(),
			'DELETE_URL'=>placeholder_url(),
			'CHOOSE_URL'=>placeholder_url(),
			'I_TITLE'=>lorem_phrase(),
			'CAPTION'=>lorem_paragraph(),
			'SUBMITTER'=>placeholder_id(),
			'USERNAME'=>lorem_word(),
			'GIVE_CONTEXT'=>true,
		));
		$unused_iotd=$current_iotd;
		$used_iotd=$current_iotd;

		return array(
			lorem_globalise(do_lorem_template('IOTD_ADMIN_CHOOSE_SCREEN',array(
				'SHOWING_OLD'=>lorem_phrase(),
				'TITLE'=>lorem_title(),
				'USED_URL'=>placeholder_url(),
				'CURRENT_IOTD'=>$current_iotd,
				'UNUSED_IOTD'=>$unused_iotd,
				'USED_IOTD'=>$used_iotd
			)), NULL, '', true)
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__block_main_iotd()
	{
		return array(
			lorem_globalise(do_lorem_template('BLOCK_MAIN_IOTD',array(
				'SUBMITTER'=>placeholder_number(),
				'THUMB_URL'=>placeholder_image_url(),
				'FULL_URL'=>placeholder_image_url(),
				'I_TITLE'=>lorem_phrase(),
				'CAPTION'=>lorem_paragraph(),
				'IMAGE'=>placeholder_image(),
				'VIEW_URL'=>placeholder_url(),
				'SUBMIT_URL'=>placeholder_url(),
				'ARCHIVE_URL'=>placeholder_url(),
				'ID'=>placeholder_id()
			)), NULL, '', true)
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__iotd_view_screen_iotd()
	{
		$content=new ocp_tempcode();
		$content->attach(do_lorem_template('IOTD_BOX',array(
			'SUBMITTER'=>placeholder_id(),
			'ID'=>placeholder_id(),
			'VIEWS'=>placeholder_number(),
			'THUMB'=>placeholder_image(),
			'DATE'=>placeholder_time(),
			'DATE_RAW'=>placeholder_date_raw(),
			'URL'=>placeholder_url(),
			'CAPTION'=>lorem_phrase()
		)));

		return array(
			lorem_globalise(do_lorem_template('PAGINATION_SCREEN',array(
				'TITLE'=>lorem_title(),
				'CONTENT'=>$content,
			)), NULL, '', true)
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__iotd_view_screen()
	{
		require_lang('ocf');
		require_lang('captcha');

		require_lang('trackbacks');
		$trackbacks=new ocp_tempcode();
		foreach (placeholder_array(1) as $k=>$v)
		{
			$trackbacks->attach(do_lorem_template('TRACKBACK',array(
				'ID'=>placeholder_id(),
				'TIME_RAW'=>placeholder_date_raw(),
				'TIME'=>placeholder_date(),
				'URL'=>placeholder_url(),
				'TITLE'=>lorem_phrase(),
				'EXCERPT'=>lorem_paragraph(),
				'NAME'=>lorem_phrase()
			)));
		}
		$trackback_details=do_lorem_template('TRACKBACK_WRAPPER',array(
			'TRACKBACKS'=>$trackbacks,
			'TRACKBACK_PAGE'=>placeholder_id(),
			'TRACKBACK_ID'=>placeholder_id(),
			'TRACKBACK_TITLE'=>lorem_phrase()
		));

		$rating_details=new ocp_tempcode();

		$review_titles=array();
		$review_titles[]=array(
			'REVIEW_TITLE'=>lorem_word(),
			'REVIEW_RATING'=>make_string_tempcode(float_format(10.0))
		);

		$comments='';

		$form=do_lorem_template('COMMENTS_POSTING_FORM',array(
			'JOIN_BITS'=>lorem_phrase_html(),
			'FIRST_POST_URL'=>placeholder_url(),
			'FIRST_POST'=>lorem_paragraph_html(),
			'TYPE'=>'downloads',
			'ID'=>placeholder_id(),
			'REVIEW_RATING_CRITERIA'=>$review_titles,
			'USE_CAPTCHA'=>true,
			'GET_EMAIL'=>false,
			'EMAIL_OPTIONAL'=>true,
			'GET_TITLE'=>true,
			'POST_WARNING'=>do_lang('POST_WARNING'),
			'COMMENT_TEXT'=>get_option('comment_text'),
			'EM'=>placeholder_emoticon_chooser(),
			'DISPLAY'=>'block',
			'COMMENT_URL'=>placeholder_url(),
			'TITLE'=>lorem_word(),
			'MAKE_POST'=>true,
			'CREATE_TICKET_MAKE_POST'=>true
		));

		$comment_details=do_lorem_template('COMMENTS_WRAPPER',array(
			'TYPE'=>lorem_phrase(),
			'ID'=>placeholder_id(),
			'REVIEW_RATING_CRITERIA'=>$review_titles,
			'AUTHORISED_FORUM_URL'=>placeholder_url(),
			'FORM'=>$form,
			'COMMENTS'=>$comments,
			'SORT'=>'relevance',
		));

		return array(
			lorem_globalise(do_lorem_template('IOTD_ENTRY_SCREEN',array(
				'TITLE'=>lorem_title(),
				'SUBMITTER'=>placeholder_id(),
				'I_TITLE'=>lorem_phrase(),
				'CAPTION'=>lorem_phrase(),
				'DATE_RAW'=>placeholder_date_raw(),
				'ADD_DATE_RAW'=>placeholder_date_raw(),
				'EDIT_DATE_RAW'=>placeholder_date_raw(),
				'DATE'=>placeholder_time(),
				'ADD_DATE'=>placeholder_time(),
				'EDIT_DATE'=>placeholder_time(),
				'VIEWS'=>placeholder_number(),
				'TRACKBACK_DETAILS'=>$trackback_details,
				'RATING_DETAILS'=>$rating_details,
				'COMMENT_DETAILS'=>$comment_details,
				'EDIT_URL'=>placeholder_url(),
				'URL'=>placeholder_image_url()
			)), NULL, '', true)
		);
	}
}