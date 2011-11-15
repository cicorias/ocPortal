<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2011

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		core_menus
 */

/**
 * Build a bookmarks menu for the current member.
 *
 * @return array			Faked database rows
 */
function build_bookmarks_menu()
{
	require_lang('bookmarks');

	$items=array();
	$rows=$GLOBALS['SITE_DB']->query_select('bookmarks',array('*'),array('b_owner'=>get_member()),'ORDER BY b_folder');

	// For managing existing bookmarks
	if (count($rows)!=0)
	{
		$rand_id=mt_rand(0,1000000);
		$_url=build_url(array('page'=>'bookmarks','type'=>'misc'),get_module_zone('bookmarks'));
		$items[]=array('id'=>$rand_id,'i_parent'=>NULL,'cap'=>do_lang('MANAGE_BOOKMARKS'),'i_url'=>$_url,'i_check_permissions'=>0,'i_expanded'=>0,'i_new_window'=>1,'i_page_only'=>'');
	}
	// For adding a new bookmark
	$self_url=get_param('url','');
	if ($self_url=='') $self_url=get_self_url(true);
	$rand_id=mt_rand(0,1000000);
	//$url=build_url(array('page'=>'bookmarks','type'=>'ad','url'=>$self_url,'title'=>get_param('title','',true)),get_module_zone('bookmarks'));
	$keep=symbol_tempcode('KEEP');
	$url=find_script('bookmarks').'?no_redirect=1&type=ad&url='.urlencode(base64_encode($self_url)).'&title='.urlencode(get_param('title','',true)).$keep->evaluate();
	$items[]=array('id'=>$rand_id,'i_parent'=>NULL,'cap'=>do_lang('ADD_BOOKMARK'),'i_popup'=>1,'i_width'=>600,'i_height'=>500,'i_url'=>$url,'i_check_permissions'=>0,'i_expanded'=>0,'i_new_window'=>1,'i_page_only'=>'');

	// Existing bookmarks
	if (count($rows)!=0)
	{
		// Spacer
		$items[]=array('id'=>$rand_id,'i_parent'=>NULL,'cap'=>'','i_url'=>'','i_check_permissions'=>0,'i_expanded'=>0,'i_new_window'=>1,'i_page_only'=>'');

		// Make our folders first
		$parents=array(''=>NULL);
		foreach ($rows as $row)
		{
			if (!array_key_exists($row['b_folder'],$parents))
			{
				$rand_id=mt_rand(0,1000000);
				$parents[$row['b_folder']]=$rand_id;
				$items[]=array('id'=>$rand_id,'i_parent'=>NULL,'cap'=>$row['b_folder'],'i_url'=>'','i_check_permissions'=>0,'i_expanded'=>0,'i_new_window'=>0,'i_page_only'=>'');
			}
		}

		foreach ($rows as $row)
		{
			$parent=$parents[$row['b_folder']];
			list($zone,$attributes,$hash)=page_link_decode($row['b_page_link']);
			$_url=build_url($attributes,$zone,NULL,false,false,false,$hash);
			$items[]=array('id'=>$row['id'],'i_parent'=>$parent,'cap'=>$row['b_title'],'i_url'=>$_url,'i_check_permissions'=>0,'i_expanded'=>0,'i_new_window'=>0,'i_page_only'=>'');
		}
	}
	
	return $items;
}

