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
 * @package		wordfilter
 */

/**
 * Module page class.
 */
class Module_admin_wordfilter
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
		$info['version']=3;
		$info['locked']=true;
		$info['update_require_upgrade']=1;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_table_if_exists('wordfilter');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if (is_null($upgrade_from))
		{
			$GLOBALS['SITE_DB']->create_table('wordfilter',array(
				'word'=>'*SHORT_TEXT',
				'w_replacement'=>'SHORT_TEXT',
				'w_substr'=>'*BINARY'
			));

			$naughties=array('arsehole','asshole','arse','cock','cocked','cocksucker','crap','cunt','cum','bastard',
									'bitch','blowjob','bollocks','bondage','bugger','buggery','dickhead','fuck','fucked','fucking',
									'fucker','gayboy','motherfucker','nigger','piss','pissed','puffter','pussy','shag','shagged',
									'shat','shit','slut','twat','wank','wanker','whore');
			foreach ($naughties as $word)
			{
				$GLOBALS['SITE_DB']->query_insert('wordfilter',array('word'=>$word,'w_replacement'=>'','w_substr'=>0));
			}
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'MANAGE_WORDFILTER');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		set_helper_panel_pic('pagepics/wordfilter');
		set_helper_panel_tutorial('tut_censor');

		require_lang('wordfilter');

		$type=get_param('type','misc');

		if ($type=='add') return $this->add_word();
		if ($type=='remove') return $this->remove_word();
		if ($type=='misc') return $this->word_filter_interface();

		return new ocp_tempcode();
	}

	/**
	 * The UI to choose a filtered-word to edit, or to add a filtered-word.
	 *
	 * @return tempcode		The UI
	 */
	function word_filter_interface()
	{
		$title=get_screen_title('MANAGE_WORDFILTER');

		require_code('form_templates');
		$list=new ocp_tempcode();
		$words=$GLOBALS['SITE_DB']->query_select('wordfilter',array('*'),NULL,'ORDER BY word');
		foreach ($words as $word)
		{
			$word_text=(($word['w_substr']==1)?'*':'').$word['word'].(($word['w_substr']==1)?'*':'');
			if ($word['w_replacement']!='') $word_text.=' -> '.$word['w_replacement'];
			$list->attach(form_input_list_entry($word['word'],false,$word_text));
		}
		if (!$list->is_empty())
		{
			$delete_url=build_url(array('page'=>'_SELF','type'=>'remove'),'_SELF');
			$submit_name=do_lang_tempcode('DELETE_WORDFILTER');
			$fields=form_input_list(do_lang_tempcode('WORD'),'','word',$list);

			$tpl=do_template('FORM',array('_GUID'=>'a752cea5acab633e1cc0781f0e77e0be','TABINDEX'=>strval(get_form_field_tabindex()),'HIDDEN'=>'','TEXT'=>'','FIELDS'=>$fields,'URL'=>$delete_url,'SUBMIT_NAME'=>$submit_name));
		} else $tpl=new ocp_tempcode();

		// Do a form so people can add
		$post_url=build_url(array('page'=>'_SELF','type'=>'add'),'_SELF');
		$submit_name=do_lang_tempcode('ADD_WORDFILTER');
		$fields=new ocp_tempcode();
		$fields->attach(form_input_line(do_lang_tempcode('WORD'),do_lang_tempcode('DESCRIPTION_WORD'),'word_2','',true));
		$fields->attach(form_input_line(do_lang_tempcode('REPLACEMENT'),do_lang_tempcode('DESCRIPTION_REPLACEMENT'),'replacement','',false));
		$fields->attach(form_input_tick(do_lang_tempcode('WORD_SUBSTR'),do_lang_tempcode('DESCRIPTION_WORD_SUBSTR'),'substr',false));
		$add_form=do_template('FORM',array('_GUID'=>'5b1d45b374e15392b9f5496de8db2e1c','TABINDEX'=>strval(get_form_field_tabindex()),'SECONDARY_FORM'=>true,'SKIP_REQUIRED'=>true,'HIDDEN'=>'','TEXT'=>'','FIELDS'=>$fields,'SUBMIT_NAME'=>$submit_name,'URL'=>$post_url));

		return do_template('WORDFILTER_SCREEN',array('_GUID'=>'4b355f5d2cecc0bc26e76a69716cc841','TITLE'=>$title,'TPL'=>$tpl,'ADD_FORM'=>$add_form));
	}

	/**
	 * The actualiser to add a filtered-word.
	 *
	 * @return tempcode		The UI
	 */
	function add_word()
	{
		$title=get_screen_title('ADD_WORDFILTER');

		$word=post_param('word_2');
		$this->_add_word($word,post_param('replacement'),post_param_integer('substr',0));

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Add a filtered-word.
	 *
	 * @param  SHORT_TEXT	The filtered-word
	 * @param  SHORT_TEXT	Replacement (blank: block entirely)
	 * @param  BINARY			Whether to perform a substring match
	 */
	function _add_word($word,$replacement,$substr)
	{
		$test=$GLOBALS['SITE_DB']->query_select_value_if_there('wordfilter','word',array('word'=>$word));
		if (!is_null($test)) warn_exit(do_lang_tempcode('ALREADY_EXISTS',escape_html($word)));

		$GLOBALS['SITE_DB']->query_insert('wordfilter',array('word'=>$word,'w_replacement'=>$replacement,'w_substr'=>$substr));

		log_it('ADD_WORDFILTER',$word);
	}

	/**
	 * The actualiser to delete a filtered-word.
	 *
	 * @return tempcode		The UI
	 */
	function remove_word()
	{
		$title=get_screen_title('DELETE_WORDFILTER');

		$this->_remove_word(post_param('word'));

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Delete a filtered-word.
	 *
	 * @param  SHORT_TEXT		The filtered-word
	 */
	function _remove_word($word)
	{
		$GLOBALS['SITE_DB']->query_delete('wordfilter',array('word'=>$word),'',1);

		log_it('DELETE_WORDFILTER',$word);
	}

}


