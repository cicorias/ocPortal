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
 * @package		bookmarks
 */

/**
 * Module page class.
 */
class Module_bookmarks
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
		$info['version']=2;
		$info['locked']=true;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_if_exists('bookmarks');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		$GLOBALS['SITE_DB']->create_table('bookmarks',array(
			'id'=>'*AUTO',
			'b_owner'=>'USER',
			'b_folder'=>'SHORT_TEXT',
			'b_title'=>'SHORT_TEXT',
			'b_page_link'=>'SHORT_TEXT',
		));
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return is_guest()?array():array('misc'=>'MANAGE_BOOKMARKS','ad'=>'ADD_BOOKMARK');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('bookmarks');
		require_code('bookmarks');
		require_css('bookmarks');

		if (is_guest()) access_denied('NOT_AS_GUEST');

		// Decide what we're doing
		$type=get_param('type','misc');

		if ($type=='misc') return $this->manage_bookmarks();
		if ($type=='_manage') return $this->_manage_bookmarks();
		if ($type=='_edit') return $this->_edit_bookmark();
		if ($type=='ad') return $this->ad();
		if ($type=='_ad') return $this->_ad();

		return new ocp_tempcode();
	}

	/**
	 * The UI to manage bookmarks.
	 *
	 * @return tempcode		The UI
	 */
	function manage_bookmarks()
	{
		$title=get_page_title('MANAGE_BOOKMARKS');

		require_code('form_templates');
		require_lang('zones');

		$fields=new ocp_tempcode();
		$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('TITLE'=>do_lang_tempcode('MOVE'))));
		$rows=$GLOBALS['SITE_DB']->query_select('bookmarks',array('DISTINCT b_folder'),array('b_owner'=>get_member()),'ORDER BY b_folder');
		$list=form_input_list_entry('',false,do_lang_tempcode('NA_EM'));
		$list->attach(form_input_list_entry('!',false,do_lang_tempcode('ROOT_EM')));
		foreach ($rows as $row)
		{
			if ($row['b_folder']!='') $list->attach(form_input_list_entry($row['b_folder']));
		}
		$fields->attach(form_input_list(do_lang_tempcode('OLD_BOOKMARK_FOLDER'),do_lang_tempcode('DESCRIPTION_OLD_BOOKMARK_FOLDER'),'folder',$list,NULL,false,false));
		$fields->attach(form_input_line(do_lang_tempcode('NEW_BOOKMARK_FOLDER'),do_lang_tempcode('DESCRIPTION_NEW_BOOKMARK_FOLDER'),'folder_new','',false));
		$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('TITLE'=>do_lang_tempcode('ACTIONS'))));
		$fields->attach(form_input_tick(do_lang_tempcode('DELETE'),do_lang_tempcode('DESCRIPTION_DELETE'),'delete',false));
		$post_url=build_url(array('page'=>'_SELF','type'=>'_manage'),'_SELF');
		$form=do_template('FORM',array('HIDDEN'=>'','FIELDS'=>$fields,'TEXT'=>'','URL'=>$post_url,'SUBMIT_NAME'=>do_lang_tempcode('MOVE_OR_DELETE_BOOKMARKS'),'JAVASCRIPT'=>'standardAlternateFields(\'folder\',\'folder_new\');'));

		$bookmarks=array();
		$_bookmarks=$GLOBALS['SITE_DB']->query_select('bookmarks',array('*'),array('b_owner'=>get_member()),'ORDER BY b_folder');
		foreach ($_bookmarks as $bookmark)
		{
			$bookmarks[]=array('ID'=>strval($bookmark['id']),'CAPTION'=>$bookmark['b_title'],'FOLDER'=>$bookmark['b_folder'],'PAGE_LINK'=>$bookmark['b_page_link']);
		}

		return do_template('BOOKMARKS_SCREEN',array('_GUID'=>'685f020d6407543271ce99b5775bb357','TITLE'=>$title,'FORM_URL'=>$post_url,'FORM'=>$form,'BOOKMARKS'=>$bookmarks));
	}

	/**
	 * The actualiser to manage bookmarks.
	 *
	 * @return tempcode		The UI
	 */
	function _manage_bookmarks()
	{
		$title=get_page_title('MANAGE_BOOKMARKS');

		$bookmarks=$GLOBALS['SITE_DB']->query_select('bookmarks',array('id'),array('b_owner'=>get_member()));
		if (post_param('delete','')!='') // A delete
		{
			foreach ($bookmarks as $bookmark)
			{
				if (get_param_integer('bookmark_'.$bookmark['id'],0)==1)
				{
					$GLOBALS['SITE_DB']->query_delete('bookmarks',array('id'=>$bookmark['id']),'',1);
				}
			}
		} else // Otherwise it's a move
		{
			$folder=post_param('folder_new','');
			if ($folder=='') $folder=post_param('folder');
			if ($folder=='!') $folder='';

			foreach ($bookmarks as $bookmark)
			{
				if (get_param_integer('bookmark_'.$bookmark['id'],0)==1)
				{
					$GLOBALS['SITE_DB']->query_update('bookmarks',array('b_folder'=>$folder),array('id'=>$bookmark['id']),'',1);
				}
			}
		}

		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * The UI to add a bookmark.
	 *
	 * @return tempcode		The UI
	 */
	function ad()
	{
		return add_bookmark_form(build_url(array('page'=>'_SELF','type'=>'_ad','do_redirect'=>(get_param_integer('no_redirect',0)==0)?'1':'0'),'_SELF'));
	}

	/**
	 * The actualiser to add a bookmark.
	 *
	 * @return tempcode		The UI
	 */
	function _ad()
	{
		$title=get_page_title('ADD_BOOKMARK');

		$folder=post_param('folder_new','');
		if ($folder=='') $folder=post_param('folder');
		if ($folder=='!') $folder='';

		add_bookmark(get_member(),$folder,post_param('title'),post_param('page_link'));

		if (get_param_integer('do_redirect')==1)
		{
			$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
			return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
		} else
		{
			return inform_screen($title,do_lang_tempcode('SUCCESS'));
		}
	}

	/**
	 * The actualiser to edit a bookmark.
	 *
	 * @return tempcode		The UI
	 */
	function _edit_bookmark()
	{
		$title=get_page_title('EDIT_BOOKMARK');

		$id=get_param_integer('id');

		if (post_param('delete',NULL)!==NULL) // A delete
		{
			$member=get_member();
			delete_bookmark($id,$member);
		} else
		{
			$caption=post_param('caption');
			$page_link=post_param('page_link');
			$member=get_member();
			edit_bookmark($id,$member,$caption,$page_link);
		}

		$url=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

}


