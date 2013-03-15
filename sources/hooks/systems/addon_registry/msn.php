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
 * @package		msn
 */

class Hook_addon_registry_msn
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
	 * Get the description of the addon
	 *
	 * @return string			Description of the addon
	 */
	function get_description()
	{
		return 'Features to support multi-site-networks (networks of linked sites that usually share a common member system).';
	}

	/**
	 * Get a mapping of dependency types
	 *
	 * @return array			File permissions to set
	 */
	function get_dependencies()
	{
		return array(
			'requires'=>array(),
			'recommends'=>array(),
			'conflicts_with'=>array(),
		);
	}

	/**
	 * Get a list of files that belong to this addon
	 *
	 * @return array			List of files
	 */
	function get_file_list()
	{
		return array(

			'sources/hooks/systems/config_default/network_links.php',
			'sources/hooks/systems/addon_registry/msn.php',
			'sources/hooks/blocks/main_notes/msn.php',
			'BLOCK_SIDE_NETWORK.tpl',
			'NETLINK.tpl',
			'adminzone/pages/comcode/EN/netlink.txt',
			'text/netlink.txt',
			'netlink.php',
			'sources/hooks/systems/do_next_menus/msn.php',
			'themes/default/images/bigicons/multisitenetwork.png',
			'themes/default/images/pagepics/multisitenetworking.png',
			'sources/multi_site_networks.php',
			'sources/blocks/side_network.php',
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
				'BLOCK_SIDE_NETWORK.tpl'=>'block_side_network',
				'NETLINK.tpl'=>'netlink',
				);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__block_side_network()
	{
		return array(
			lorem_globalise(
				do_lorem_template('BLOCK_SIDE_NETWORK',array(
					'CONTENT'=>lorem_phrase(),
						)
			),NULL,'',true),
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__netlink()
	{
		$content = new ocp_tempcode();
		$url=placeholder_url();
		foreach (placeholder_array() as $key=>$value)
		{
			$content->attach(form_input_list_entry($url->evaluate(),false,lorem_word()));
		}

		return array(
			lorem_globalise(
				do_lorem_template('NETLINK',array(
					'CONTENT'=>$content,
						)
			),NULL,'',true),
		);
	}
}
