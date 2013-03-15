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
 * @package		core_ocf
 */

/**
 * Pop-up some rules.
 */
function rules_script()
{
	$id=get_param_integer('id',NULL);

	if (is_null($id))
	{
		require_code('site');
		$output=request_page('rules',true);
		$title=do_lang_tempcode('RULES');
	} else
	{
		if (!has_category_access(get_member(),'forums',strval($id)))
			warn_exit(do_lang_tempcode('ACCESS_DENIED'));

		$forum_rows=$GLOBALS['FORUM_DB']->query_select('f_forums',array('*'),array('id'=>$id),'',1);
		if (!array_key_exists(0,$forum_rows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
		$forum_row=$forum_rows[0];

		require_lang('ocf');

		$question=get_translated_tempcode($forum_row['f_intro_question'],$GLOBALS['FORUM_DB']);
		$answer=$forum_row['f_intro_answer'];

		$output=do_template('OCF_FORUM_INTRO_QUESTION_POPUP',array('_GUID'=>'6f2dc12b616219ff982654b73ef979b2','QUESTION'=>$question,'ANSWER'=>$answer));

		$title=($answer=='')?do_lang_tempcode('FORUM_RULES'):do_lang_tempcode('INTRO_QUESTION');
	}

	$tpl=do_template('POPUP_HTML_WRAP',array('_GUID'=>'26c4dbc7a4737310f089583f1048cb13','TITLE'=>$title,'TARGET'=>'_top','CONTENT'=>$output));
	$tpl->evaluate_echo();
}


