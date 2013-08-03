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
 * @package		banners
 */

class Hook_Preview_banner
{

	/**
	 * Find whether this preview hook applies.
	 *
	 * @return array			Triplet: Whether it applies, the attachment ID type, whether the forum DB is used [optional]
	 */
	function applies()
	{
		$applies=(get_param('page','')=='cms_banners') && ((get_param('type')=='_ed') || (get_param('type')=='ad'));
		return array($applies,NULL,false);
	}

	/**
	 * Standard modular run function for preview hooks.
	 *
	 * @return array			A pair: The preview, the updated post Comcode
	 */
	function run()
	{
		require_code('uploads');
		require_lang('banners');

		// Check according to banner type
		$title_text=post_param('title_text','');
		$direct_code=post_param('direct_code','');
		$url_param_name='image_url';
		$file_param_name='file';
		require_code('uploads');
		$is_upload=is_swf_upload() || (array_key_exists($file_param_name,$_FILES)) && (array_key_exists('tmp_name',$_FILES[$file_param_name]) && (is_uploaded_file($_FILES[$file_param_name]['tmp_name'])));
		$_banner_type_rows=$GLOBALS['SITE_DB']->query_select('banner_types',array('*'),array('id'=>post_param('b_type')),'',1);
		if (!array_key_exists(0,$_banner_type_rows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
		$banner_type_row=$_banner_type_rows[0];
		if ($banner_type_row['t_is_textual']==0)
		{
			if ($direct_code=='')
			{
				$urls=get_url($url_param_name,$file_param_name,'uploads/banners',0,$is_upload?OCP_UPLOAD_IMAGE:OCP_UPLOAD_ANYTHING);
				$img_url=fixup_protocolless_urls($urls[0]);
				if ($img_url=='')
				{
					warn_exit(do_lang_tempcode('IMPROPERLY_FILLED_IN_UPLOAD_BANNERS'));
				}
			} else $img_url='';
		} else
		{
			$img_url='';
			if ($title_text=='')
				warn_exit(do_lang_tempcode('IMPROPERLY_FILLED_IN_BANNERS'));

			if (strlen($title_text)>$banner_type_row['t_max_file_size'])
				warn_exit(do_lang_tempcode('BANNER_TOO_LARGE_2',integer_format(strlen($title_text)),integer_format($banner_type_row['t_max_file_size'])));
		}

		require_code('banners');
		$preview=show_banner(post_param('name'),post_param('title_text',''),comcode_to_tempcode(post_param('caption')),post_param('direct_code',''),$img_url,'',post_param('site_url'),post_param('b_type'),get_member());

		return array($preview,NULL);
	}

}
