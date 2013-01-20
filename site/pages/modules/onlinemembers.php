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
 * @package		core_ocf
 */

/**
 * Module page class.
 */
class Module_onlinemembers
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
		$info['locked']=false;
		return $info;
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('!'=>'USERS_ONLINE');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		if (get_forum_type()!='ocf') warn_exit(do_lang_tempcode('NO_OCF')); else ocf_require_all_forum_stuff();

		$title=get_screen_title('USERS_ONLINE');

		attach_to_screen_header('<meta name="robots" content="noindex" />'); // XHTMLXHTML

		$count=0;
		$members=get_online_members(has_privilege(get_member(),'show_user_browsing'),NULL,$count);
		if ((is_null($members)) && (has_privilege(get_member(),'show_user_browsing')))
		{
			$members=get_online_members(false,NULL,$count);
		}
		if (is_null($members)) warn_exit(do_lang_tempcode('TOO_MANY_USERS_ONLINE'));

		$rows=new ocp_tempcode();
		$members=array_reverse($members);
		sort_maps_by($members,'last_activity');
		$members=array_reverse($members);
		foreach ($members as $row)
		{
			$last_activity=$row['last_activity'];
			$member=$row['member_id'];
			$name=$row['cache_username'];
			$location=$row['the_title'];
			if (($location=='') && ($row['the_type']=='rss'))
			{
				$location='RSS';
				$at_url=make_string_tempcode(find_script('backend'));
			}
			elseif (($location=='') && ($row['the_page']==''))
			{
				$at_url=new ocp_tempcode();
			} else
			{
				$map=array('page'=>$row['the_page']);
				if ($row['the_type']!='') $map['type']=$row['the_type'];
				if ($row['the_id']!='') $map['id']=$row['the_id'];
				$at_url=build_url($map,$row['the_zone']);
			}
			$ip=$row['ip'];
			if (substr($ip,-1)=='*') // sessions IPs are not full
			{
				if (is_guest($member))
				{
					if (addon_installed('stats'))
					{
						$test=$GLOBALS['SITE_DB']->query_select_value_if_there('stats','ip',array('member_id'=>-$row['the_session']));
						if ((!is_null($test)) && ($test!=''))
						{
							$ip=$test;
						} else
						{
							$test=$GLOBALS['SITE_DB']->query_value_if_there('SELECT ip FROM '.get_table_prefix().'stats WHERE ip LIKE \''.db_encode_like(str_replace('*','%',$ip)).'\' ORDER BY date_and_time DESC');
							if ((!is_null($test)) && ($test!='')) $ip=$test;
						}
					}
				} else
				{
					$test=$GLOBALS['FORUM_DRIVER']->get_member_ip($member);
					if ((!is_null($test)) && ($test!='')) $ip=$test;
				}
			}

			$link=$GLOBALS['FORUM_DRIVER']->member_profile_hyperlink($member,false,$name);

			if ($ip!='') // CRON?
				$rows->attach(do_template('OCF_MEMBER_ONLINE_ROW',array('_GUID'=>'2573786f3bccf9e613b125befb3730e8','IP'=>$ip,'AT_URL'=>$at_url,'LOCATION'=>$location,'MEMBER'=>$link,'TIME'=>integer_format(intval((time()-$last_activity)/60)))));
		}

		return do_template('OCF_MEMBERS_ONLINE_SCREEN',array('_GUID'=>'2f63e2926c5a4690d905f97661afe6cc','TITLE'=>$title,'ROWS'=>$rows));
	}

}


