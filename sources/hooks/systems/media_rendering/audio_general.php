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
 * @package		core_rich_media
 */

class Hook_media_rendering_audio_general
{
	/**
	 * Find the media types this hook serves.
	 *
	 * @return integer	The media type(s), as a bitmask
	 */
	function get_media_type()
	{
		return MEDIA_TYPE_AUDIO;
	}

	/**
	 * See if we can recognise this mime type.
	 *
	 * @param  ID_TEXT	The mime type
	 * @return integer	Recognition precedence
	 */
	function recognises_mime_type($mime_type)
	{
		if ($mime_type=='audio/x-wav') return MEDIA_RECOG_PRECEDENCE_MEDIUM;
		if ($mime_type=='audio/midi') return MEDIA_RECOG_PRECEDENCE_MEDIUM;
		if ($mime_type=='audio/x-aiff') return MEDIA_RECOG_PRECEDENCE_MEDIUM;

		// Some other plugins can play the Microsoft formats
		if ($mime_type=='audio/x-ms-wma') return MEDIA_RECOG_PRECEDENCE_MEDIUM;

		// Plugins may be able to play these formats, although they are preferrably handled in audio_websafe
		if ($mime_type=='audio/ogg') return MEDIA_RECOG_PRECEDENCE_MEDIUM;
		if ($mime_type=='audio/x-mpeg') return MEDIA_RECOG_PRECEDENCE_MEDIUM;

		return MEDIA_RECOG_PRECEDENCE_NONE;
	}

	/**
	 * See if we can recognise this URL pattern.
	 *
	 * @param  URLPATH	URL to pattern match
	 * @return integer	Recognition precedence
	 */
	function recognises_url($url)
	{
		return MEDIA_RECOG_PRECEDENCE_NONE;
	}

	/**
	 * Provide code to display what is at the URL, in the most appropriate way.
	 *
	 * @param  URLPATH	URL to render
	 * @param  array		Attributes (e.g. width, height, length)
	 * @return tempcode	Rendered version
	 */
	function render($url,$attributes)
	{
		// Put in defaults
		if ((!array_key_exists('width',$attributes)) || (!is_numeric($attributes['width'])))
		{
			$attributes['width']=get_option('attachment_default_width');
		}
		if ((!array_key_exists('height',$attributes)) || (!is_numeric($attributes['height'])))
		{
			$attributes['height']='30';
		}

		return do_template('MEDIA_VIDEO_GENERAL',array('HOOK'=>'audio_general')+_create_media_template_parameters($url,$attributes));
	}

}
