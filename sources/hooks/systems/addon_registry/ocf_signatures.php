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
 * @package		ocf_signatures
 */

class Hook_addon_registry_ocf_signatures
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
		return 'Member signatures.';
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
			'conflicts_with'=>array()
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
			'sources/hooks/systems/addon_registry/ocf_signatures.php',
			'OCF_EDIT_SIGNATURE_TAB.tpl',
			'sources/hooks/systems/attachments/ocf_signature.php',
			'sources/hooks/systems/preview/ocf_signature.php',
			'sources/hooks/systems/profiles_tabs_edit/signature.php',
			'sources/hooks/systems/notifications/ocf_choose_signature.php'
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
			'OCF_EDIT_SIGNATURE_TAB.tpl'=>'ocf_edit_signature_tab'
		);
	}

	/**
	 * Get a preview(s) of a (group of) template(s), as a full standalone piece of HTML in Tempcode format.
	 * Uses sources/lorem.php functions to place appropriate stock-text. Should not hard-code things, as the code is intended to be declaritive.
	 * Assumptions: You can assume all Lang/CSS/Javascript files in this addon have been pre-required.
	 *
	 * @return array			Array of previews, each is Tempcode. Normally we have just one preview, but occasionally it is good to test templates are flexible (e.g. if they use IF_EMPTY, we can test with and without blank data).
	 */
	function tpl_preview__ocf_edit_signature_tab()
	{
		require_javascript('javascript_swfupload');
		require_javascript('javascript_validation');
		require_javascript('javascript_posting');
		require_lang('comcode');
		require_lang('ocf');
		require_css('ocf');

		$buttons=new ocp_tempcode();
		$_buttons=array(
			'img',
			'thumb',
			'url',
			'page',
			'code',
			'quote',
			'hide',
			'box',
			'block',
			'list',
			'html'
		);
		foreach ($_buttons as $button)
		{
			$buttons->attach(do_lorem_template('COMCODE_EDITOR_BUTTON', array(
				'DIVIDER'=>true,
				'FIELD_NAME'=>lorem_word(),
				'TITLE'=>lorem_phrase(),
				'B'=>$button
			)));
		}

		$micro_buttons=new ocp_tempcode();
		$_micro_buttons=array(
			array(
				't'=>'b'
			),
			array(
				't'=>'i'
			)
		);

		foreach ($_micro_buttons as $button)
		{
			$micro_buttons->attach(do_lorem_template('COMCODE_EDITOR_MICRO_BUTTON', array(
				'FIELD_NAME'=>lorem_word(),
				'TITLE'=>lorem_phrase(),
				'B'=>$button['t']
			)));
		}

		$comcode_editor=do_lorem_template('COMCODE_EDITOR', array(
			'POSTING_FIELD'=>lorem_word(),
			'BUTTONS'=>$buttons,
			'MICRO_BUTTONS'=>$micro_buttons
		));

		$posting_form=do_lorem_template('POSTING_FORM', array(
			'TABINDEX_PF'=>placeholder_number() /*not called TABINDEX due to conflict with FORM_STANDARD_END*/ ,
			'JAVASCRIPT'=>'',
			'PREVIEW'=>true,
			'COMCODE_EDITOR'=>$comcode_editor,
			'COMCODE_EDITOR_SMALL'=>$comcode_editor,
			'CLASS'=>lorem_word(),
			'COMCODE_URL'=>placeholder_url(),
			'EXTRA'=>'',
			'POST_COMMENT'=>lorem_phrase(),
			'EMOTICON_CHOOSER'=>'',
			'SUBMIT_NAME'=>lorem_word(),
			'HIDDEN_FIELDS'=>new ocp_tempcode(),
			'COMCODE_HELP'=>placeholder_url(),
			'URL'=>placeholder_url(),
			'POST'=>lorem_sentence(),
			'DEFAULT_PARSED'=>lorem_sentence(),
			'CONTINUE_URL'=>placeholder_url(),
			'ATTACHMENTS'=>lorem_phrase(),
			'SPECIALISATION'=>new ocp_tempcode(),
			'SPECIALISATION2'=>new ocp_tempcode()
		));

		return array(
			lorem_globalise(do_lorem_template('OCF_EDIT_SIGNATURE_TAB', array(
				'SIZE'=>placeholder_filesize(),
				'SIGNATURE'=>lorem_phrase()
			)), NULL, '', true)
		);
	}

}
