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
 * @package		ocf_forum
 */

class Hook_rss_ocf_private_topics
{

	/**
	 * Standard modular run function for RSS hooks.
	 *
	 * @param  string			A list of categories we accept from
	 * @param  TIME			Cutoff time, before which we do not show results from
	 * @param  string			Prefix that represents the template set we use
	 * @set    RSS_ ATOM_
	 * @param  string			The standard format of date to use for the syndication type represented in the prefix
	 * @param  integer		The maximum number of entries to return, ordering by date
	 * @return ?array			A pair: The main syndication section, and a title (NULL: error)
	 */
	function run($_filters,$cutoff,$prefix,$date_string,$max)
	{
		if (get_forum_type()!='ocf') return NULL;
		if (is_guest()) return NULL;

		$member_id=get_member();

		if (get_forum_type()!='ocf') return NULL;
		if (!has_actual_page_access($member_id,'forumview')) return NULL;
		if (is_guest()) return NULL;

		require_code('ocf_notifications');
		$rows=ocf_get_pp_rows($max);

		$content=new ocp_tempcode();
		foreach ($rows as $row)
		{
			$id=strval($row['p_id']);
			$author=$row['t_cache_first_username'];

			$news_date=date($date_string,$row['t_cache_first_time']);
			$edit_date=date($date_string,$row['t_cache_last_time']);
			if ($edit_date==$news_date) $edit_date='';

			$news_title=xmlentities($row['t_cache_first_title']);
			$_summary=get_translated_tempcode($row['t_cache_first_post']);
			$summary=xmlentities($_summary->evaluate());
			$news='';

			$category=do_lang('NA');
			$category_raw='';

			$_view_url=build_url(array('page'=>'topicview','id'=>$row['t_id']),get_module_zone('forumview'));
			$view_url=$_view_url->evaluate();
			$view_url.='#'.strval($row['p_id']);

			if ($prefix=='RSS_')
			{
				$if_comments=do_template('RSS_ENTRY_COMMENTS',array('_GUID'=>'448f736ecf0154960177c131dde76125','COMMENT_URL'=>$view_url,'ID'=>strval($row['p_id'])));
			} else $if_comments=new ocp_tempcode();

			$content->attach(do_template($prefix.'ENTRY',array('VIEW_URL'=>$view_url,'SUMMARY'=>$summary,'EDIT_DATE'=>$edit_date,'IF_COMMENTS'=>$if_comments,'TITLE'=>$news_title,'CATEGORY_RAW'=>$category_raw,'CATEGORY'=>$category,'AUTHOR'=>$author,'ID'=>$id,'NEWS'=>$news,'DATE'=>$news_date)));
		}

		require_lang('ocf');
		return array($content,do_lang('PRIVATE_TOPICS'));
	}

}


