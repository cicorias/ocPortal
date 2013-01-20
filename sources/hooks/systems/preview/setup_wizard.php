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
 * @package		setupwizard
 */

class Hook_Preview_setup_wizard
{

	/**
	 * Find whether this preview hook applies.
	 *
	 * @return array			Triplet: Whether it applies, the attachment ID type, whether the forum DB is used [optional]
	 */
	function applies()
	{
		$applies=(get_param('page','')=='admin_setupwizard') && (get_param('type')=='step8');
		return array($applies,NULL,false);
	}

	/**
	 * Standard modular run function for preview hooks.
	 *
	 * @return array			A pair: The preview, the updated post Comcode
	 */
	function run()
	{
		$_GET['keep_theme_seed']=post_param('seed_hex');
		$_GET['keep_theme_dark']=post_param('dark','0');
		$_GET['keep_theme_source']='default';
		$_GET['keep_theme_algorithm']='equations';

		$preview=request_page($GLOBALS['SITE_DB']->query_select_value('zones','zone_default_page'),true,'');

		return array($preview,NULL);
	}

}
