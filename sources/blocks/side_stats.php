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
 * @package		stats_block
 */

class Block_side_stats
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
		$info['update_require_upgrade']=1;
		$info['locked']=false;
		$info['parameters']=array();
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		delete_config_option('forum_show_stats_count_members');
		delete_config_option('forum_show_stats_count_topics');
		delete_config_option('forum_show_stats_count_posts');
		delete_config_option('forum_show_stats_count_posts_today');
		delete_config_option('activity_show_stats_count_users_online');
		delete_config_option('activity_show_stats_count_users_online_record');
		delete_config_option('activity_show_stats_count_users_online_forum');
		delete_config_option('activity_show_stats_count_page_views_today');
		delete_config_option('activity_show_stats_count_page_views_this_week');
		delete_config_option('activity_show_stats_count_page_views_this_month');
		delete_config_option('forum_show_stats_count_members_active_today');
		delete_config_option('forum_show_stats_count_members_active_this_week');
		delete_config_option('forum_show_stats_count_members_active_this_month');
		delete_config_option('forum_show_stats_count_members_new_today');
		delete_config_option('forum_show_stats_count_members_new_this_week');
		delete_config_option('forum_show_stats_count_members_new_this_month');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if ((is_null($upgrade_from)) || ($upgrade_from<3))
		{
			add_config_option('COUNT_MEMBERS','forum_show_stats_count_members','tick','return addon_installed(\'stats_block\')?\'1\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_TOPICS','forum_show_stats_count_topics','tick','return addon_installed(\'stats_block\')?\'1\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_POSTS','forum_show_stats_count_posts','tick','return addon_installed(\'stats_block\')?\'1\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_POSTSTODAY','forum_show_stats_count_posts_today','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_ONSITE','activity_show_stats_count_users_online','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_ONSITE_RECORD','activity_show_stats_count_users_online_record','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('COUNT_ONFORUMS','activity_show_stats_count_users_online_forum','tick','return ((get_forum_type()!=\'ocf\') && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('PAGE_VIEWS_TODAY','activity_show_stats_count_page_views_today','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('PAGE_VIEWS_THIS_WEEK','activity_show_stats_count_page_views_this_week','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('PAGE_VIEWS_THIS_MONTH','activity_show_stats_count_page_views_this_month','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_ACTIVE_TODAY','forum_show_stats_count_members_active_today','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_ACTIVE_THIS_WEEK','forum_show_stats_count_members_active_this_week','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_ACTIVE_THIS_MONTH','forum_show_stats_count_members_active_this_month','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_NEW_TODAY','forum_show_stats_count_members_new_today','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_NEW_THIS_WEEK','forum_show_stats_count_members_new_this_week','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('MEMBERS_NEW_THIS_MONTH','forum_show_stats_count_members_new_this_month','tick','return ((get_forum_type()==\'ocf\') && (!has_no_forum()) && (addon_installed(\'stats_block\')))?\'0\':NULL;','BLOCKS','STATISTICS');
		}
	}

	/**
	 * Standard modular cache function.
	 *
	 * @return ?array	Map of cache details (cache_on and ttl) (NULL: module is disabled).
	 */
	function cacheing_environment()
	{
		$info=array();
		$info['cache_on']='';
		$info['ttl']=15;
		return $info;
	}

	/**
	 * Standard modular run function.
	 *
	 * @param  array		A map of parameters.
	 * @return tempcode	The result of execution.
	 */
	function run($map)
	{
		unset($map);

		require_css('side_blocks');

		$full_tpl=new ocp_tempcode();

		// Inbuilt
		$bits=new ocp_tempcode();
		$on_forum=$GLOBALS['FORUM_DRIVER']->get_num_users_forums();
		if (!is_null($on_forum))
		{
			if (get_option('activity_show_stats_count_users_online',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'5ac97313d4c83e8afdeec09a48cea030','KEY'=>do_lang_tempcode('COUNT_ONSITE'),'VALUE'=>integer_format(get_num_users_site()))));
			if (get_option('activity_show_stats_count_users_online_record',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'dc6d0893cf98703a951da168c6a9d0ac','KEY'=>do_lang_tempcode('COUNT_ONSITE_RECORD'),'VALUE'=>integer_format(get_num_users_peak()))));
			if (get_option('activity_show_stats_count_users_online_forum',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'14f2fdbf59e86c34d93cbf16bed3f0eb','KEY'=>do_lang_tempcode('COUNT_ONFORUMS'),'VALUE'=>integer_format($on_forum))));
			$title=do_lang_tempcode('SECTION_USERS');
		} else
		{
			if (get_option('activity_show_stats_count_users_online',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'9c9760b2ed9e985e96b53c91c511e84e','KEY'=>do_lang_tempcode('USERS_ONLINE'),'VALUE'=>integer_format(get_num_users_site()))));
			if (get_option('activity_show_stats_count_users_online_record',true)=='1') $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'d18068d747fe1fe364042133e4b3ba84','KEY'=>do_lang_tempcode('USERS_ONLINE_RECORD'),'VALUE'=>integer_format(get_num_users_peak()))));
			$title=do_lang_tempcode('ACTIVITY');
		}
		if (addon_installed('stats'))
		{
			if (get_option('activity_show_stats_count_page_views_today',true)=='1')
				$bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'fc9760b2ed9e985e96b53c91c511e84e','KEY'=>do_lang_tempcode('PAGE_VIEWS_TODAY'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.get_table_prefix().'stats WHERE date_and_time>'.strval(time()-60*60*24))))));
			if (get_option('activity_show_stats_count_page_views_this_week',true)=='1')
				$bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'gc9760b2ed9e985e96b53c91c511e84e','KEY'=>do_lang_tempcode('PAGE_VIEWS_THIS_WEEK'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.get_table_prefix().'stats WHERE date_and_time>'.strval(time()-60*60*24*7))))));
			if (get_option('activity_show_stats_count_page_views_this_month',true)=='1')
				$bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE',array('_GUID'=>'hc9760b2ed9e985e96b53c91c511e84e','KEY'=>do_lang_tempcode('PAGE_VIEWS_THIS_MONTH'),'VALUE'=>integer_format($GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.get_table_prefix().'stats WHERE date_and_time>'.strval(time()-60*60*24*31))))));
		}
		if (!$bits->is_empty())
			$full_tpl->attach(do_template('BLOCK_SIDE_STATS_SECTION',array('_GUID'=>'e2408c71a7c74f1d14089412d4538b6d','SECTION'=>$title,'CONTENT'=>$bits)));

		$_hooks=find_all_hooks('blocks','side_stats');
		if (array_key_exists('stats_forum',$_hooks)) // Fudge the order
		{
			$forum_hook=$_hooks['stats_forum'];
			unset($_hooks['stats_forum']);
			$_hooks=array_merge(array('stats_forum'=>$forum_hook),$_hooks);
		}
		foreach (array_keys($_hooks) as $hook)
		{
			require_code('hooks/blocks/side_stats/'.filter_naughty_harsh($hook));
			$object=object_factory('Hook_'.filter_naughty_harsh($hook),true);
			if (is_null($object)) continue;
			$bits=$object->run();
			if (!$bits->is_empty()) $full_tpl->attach($bits);
		}

		return do_template('BLOCK_SIDE_STATS',array('_GUID'=>'0e9986c117c2a3c04690840fedcbddcd','CONTENT'=>$full_tpl));
	}

}


