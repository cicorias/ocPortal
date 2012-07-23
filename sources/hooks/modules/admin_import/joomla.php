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
 * @package		import
 */

class Hook_joomla
{

	/**
	 * Standard modular info function.
	 *
	 * @return ?array	Map of module info (NULL: module is disabled).
	 */
	function info()
	{
		$info=array();
		$info['supports_advanced_import']=false;
		$info['product']='Joomla 1.5';
		$info['prefix']='jos_';
		$info['import']=array(
									'banners',
									'polls',
									'catalogue_links',
									'catalogue_faqs',
									'news_and_categories',
									'ocf_members',
									'ocf_groups',
									'ocf_topics'
									);
		$info['dependencies']=array();
		$_cleanup_url=build_url(array('page'=>'admin_cleanup'),get_module_zone('admin_cleanup'));
		$cleanup_url=$_cleanup_url->evaluate();
		$info['message']=(get_param('type','misc')!='import' && get_param('type','misc')!='hook')?new ocp_tempcode():do_lang_tempcode('FORUM_CACHE_CLEAR',escape_html($cleanup_url));
		return $info;
	}


	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_ocf_members($db,$table_prefix,$file_base)
	{
		$row_start=0;
		$rows=array();
		do
		{
			$rows=$db->query('SELECT * FROM '.$table_prefix.'users ORDER BY id',200,$row_start);
			foreach ($rows as $row)
			{
				if (import_check_if_imported('member',strval($row['id']))) continue;
				$test=$GLOBALS['OCF_DRIVER']->get_member_from_username($row['username']);
				if (!is_null($test))
				{
					import_id_remap_put('member',strval($row['id']),$test);
					continue;
				}
				$user_type = $row['usertype'];
				$id_new=$GLOBALS['FORUM_DB']->query('SELECT g.id from '.$GLOBALS['FORUM_DB']->get_table_prefix().'f_groups g INNER JOIN '.$GLOBALS['FORUM_DB']->get_table_prefix().'translate t ON g.g_name=t.id WHERE '.db_string_equal_to('text_original',$user_type));
				$group_id=$db->query('SELECT id FROM '.$table_prefix.'core_acl_aro_groups WHERE '.db_string_equal_to('name',$user_type));
				$is_super_admin=($user_type=='Administrator')?1:0;
				$is_super_moderator=($user_type=='Universal Moderator')?1:0;
				if (count($group_id) > 0)
				{
					$gid=$group_id[0]['id'];
					if (count($id_new) == 0)
					{
						$avatar_max_width=100;
						$avatar_max_height=100;
						if (import_check_if_imported('id',strval($gid))) continue;
						$_id_new=ocf_make_group($user_type,0,$is_super_admin,$is_super_moderator,'','',NULL,NULL,NULL,5,0,5,5,$avatar_max_width,$avatar_max_height,30000);
						$id_new=array(array('id'=>$_id_new));
					}
					//privileges
					if ($is_super_moderator==1)
						set_specific_permission($id_new[0]['id'],'allow_html',true);

					if (!import_check_if_imported('group',strval($gid)))
						import_id_remap_put('group',strval($gid),$id_new[0]['id']);

					//add member
					$primary_group=$id_new[0]['id'];
					$custom_fields=array();
					$datetimearr = explode(' ', $row['registerDate']);
					$datearr = explode('-', $datetimearr[0]);
					$timearr = explode(':', $datetimearr[1]);
					$date = $datearr[2];
					$month = $datearr[1];
					$year = $datearr[0];
					$hour = $timearr[0];
					$min = $timearr[1];
					$sec = $timearr[2];
					$register_date = mktime($hour, $min, $sec, $month, $date, $year);
					$datetimearr = explode(' ', $row['lastvisitDate']);
					$datearr = explode('-', $datetimearr[0]);
					$timearr = explode(':', $datetimearr[1]);
					$date = $datearr[2];
					$month = $datearr[1];
					$year = $datearr[0];
					$hour = $timearr[0];
					$min = $timearr[1];
					$sec = $timearr[2];
					$last_visit_date = mktime($hour, $min, $sec, $month, $date, $year);					
					$id=(get_param_integer('keep_preserve_ids',0)==0)?NULL:$row['id'];
					$member_id=ocf_make_member($row['username'],$row['password'],$row['email'],NULL,NULL,NULL,NULL,$custom_fields,NULL,$primary_group,1,$register_date,$last_visit_date,'',NULL,'',0,0,1,$row['name'],'','',1,1,NULL,$row['sendEmail'],$row['sendEmail'],'',NULL,'',false,NULL,'',1,$last_visit_date,$id,0,'*','');
				}
			}

			$row_start+=200;
		}
		while (count($rows)>0);
	}

	/**
	 * Standard import function.
	 *
	 * @param  object		The DB connection to import from
	 * @param  string		The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_ocf_groups($db,$table_prefix,$file_base)
	{
		$rows=$db->query('SELECT name,id FROM '.$table_prefix.'core_acl_aro_groups');
		$avatar_max_width=100;
		$avatar_max_height=100;
		foreach ($rows as $row)
		{
			if (import_check_if_imported('id',strval($row['id']))) continue;

			$is_super_admin=($row['name']=='Administrator')?1:0;
			$is_super_moderator=($row['name']=='Universal Moderator')?1:0;

			$id_new=$GLOBALS['FORUM_DB']->query_value_null_ok('f_groups g INNER JOIN '.$GLOBALS['FORUM_DB']->get_table_prefix().'translate t ON g.g_name=t.id WHERE '.db_string_equal_to('text_original',$row['name']),'g.id');
			if (is_null($id_new))
			{
				$id_new=ocf_make_group($row['name'],0,$is_super_admin,$is_super_moderator,'','',NULL,NULL,NULL,5,0,5,5,$avatar_max_width,$avatar_max_height,30000);
			}

			// privileges
			if ($is_super_moderator==1)
				set_specific_permission($id_new,'allow_html',true);

         if (!import_check_if_imported('group',strval($row['id'])))
				import_id_remap_put('group',strval($row['id']),$id_new);
		}
	}

	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_ocf_topics($db,$table_prefix,$file_base)
	{
		$row_start=0;
		$rows=array();
		do
		{
			$rows=$db->query("SELECT message_id,user_id_from,user_id_to,subject,state,message,date_time FROM ".$table_prefix."messages AS M INNER JOIN ". $table_prefix ."users AS U ON U.id=M.user_id_from AND U.id=M.user_id_to AND U.usertype='Super Administrator'",200,$row_start);
			foreach ($rows as $row)
			{
				if (import_check_if_imported('topic',strval($row['message_id']))) continue;
				$forum_id=NULL;
				$pt_from=import_id_remap_get('member',strval($row['user_id_from']));
				$pt_to=import_id_remap_get('member',strval($row['user_id_to']));
				$subject=html_to_comcode($row['subject']);
				$message=html_to_comcode($row['message']);
				$id_new=ocf_make_topic($forum_id,$subject,'',1,($row['state']==1)?0:1,0,0,0,$pt_from,$pt_to,false,0);
				$datetimearr = explode(' ', $row['date_time']);
				$datearr = explode('-', $datetimearr[0]);
				$timearr = explode(':', $datetimearr[1]);
				$date = intval($datearr[2]);
				$month = intval($datearr[1]);
				$year = intval($datearr[0]);
				$hour = intval($timearr[0]);
				$min = intval($timearr[1]);
				$sec = intval($timearr[2]);
				$time = mktime($hour, $min, $sec, $month, $date, $year);
				ocf_make_post($id_new,$subject,$message,0,true,1,0,NULL,NULL,$time,$pt_from);
				import_id_remap_put('topic',strval($row['message_id']),$id_new);
			}
			$row_start+=200;
		}
		while (count($rows)>0);
	}


	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_catalogue_links($db,$table_prefix,$old_base_dir)
	{
      $data=array();
		require_code('catalogues2');
		require_code('catalogues');
		$fields=collapse_1d_complexity('id',$GLOBALS['SITE_DB']->query_select('catalogue_fields',array('id'),array('c_name'=>'links')));
		$categories=$db->query("SELECT title,description,id FROM ".$table_prefix."categories WHERE section='com_weblinks'");
		$root_category=$GLOBALS['SITE_DB']->query_value('catalogue_categories','MIN(id)',array('c_name'=>'links'));
		foreach ($categories as $category)
		{
			$cat_title=$category['title'];
			$query="SELECT CC.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."translate AS T INNER JOIN ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_categories AS CC ON T.id=CC.cc_title AND T.text_original='".db_escape_string($cat_title)."' AND CC.c_name='links'";
			$cat_id=$GLOBALS['SITE_DB']->query($query);
			if (count($cat_id) == 0)
			{
				$id=actual_add_catalogue_category('links',$category['title'],$category['description'],'',$root_category,'');
				grant_catalogue_full_access($id);
			} else
			{
				$id=$cat_id[0]['id'];
			}
			$rows=$db->query("SELECT * FROM ".$table_prefix."weblinks WHERE catid=".strval($category['id'])." AND title NOT LIKE '%joomla%' AND url NOT IN ('http://www.ohloh.net/p/joomla', 'http://www.opensourcematters.org')");
			$i=0;
			foreach ($rows as $row)
			{				
				$url=$row['url'];
				$query="SELECT CE.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_entries AS CE INNER JOIN  ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_efv_short AS CES ON CES.ce_id=CE.id AND CES.cv_value='".db_escape_string($url)."' AND CE.c_name='links' AND CE.cc_id=$id";
				$link_id=$GLOBALS['SITE_DB']->query($query);
				if (count($link_id) == 0)
				{
					$data[$i]['title']=$row['title'];
					$data[$i]['url']=$row['url'];
					$data[$i]['description']=$row['description'];
					$data[$i]['validated']=$row['approved'];
					$data[$i]['date']=$this->mysql_time_to_timestamp($row['checked_out_time']);
					$data[$i]['hits']=0;
					$data[$i]['totalvotes']=0;
					$data[$i]['date']=time();
					$i++;
				}
			}
			foreach ($data as $row)
			{
				$member=get_member();
				$map=array($fields[0]=>$row['title'],$fields[1]=>$row['url'],$fields[2]=>$row['description']);
				$new_id=actual_add_catalogue_entry($id,$row['validated'],'',1,1,1,$map,$row['date'],$member);			
			}
		}
	}

	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_catalogue_faqs($db,$table_prefix,$old_base_dir)
	{
		require_code('catalogues2');
		require_code('catalogues');
		$fields=collapse_1d_complexity('id',$GLOBALS['SITE_DB']->query_select('catalogue_fields',array('id'),array('c_name'=>'faqs')));
		$categories=$db->query("SELECT title,id FROM " .$table_prefix."sections WHERE title='FAQs'");
		foreach ($categories as $category)
		{
			$cat_title=$category['title'];
			$cat_id=$GLOBALS['SITE_DB']->query("SELECT CC.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."translate AS T INNER JOIN  ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_categories AS CC ON T.id=CC.cc_title AND T.text_original='".db_escape_string($cat_title)."' AND CC.c_name='faqs'");

			if (count($cat_id) == 0)
			{
				$id=actual_add_catalogue_category('faqs',$category['title'],do_lang('DEFAULT_CATALOGUE_FAQS_DESCRIPTION'),'',NULL,'');
				grant_catalogue_full_access($id);
			} else
			{
				$id=$cat_id[0]['id'];
			}

			$rows=$db->query('SELECT * FROM '.$table_prefix.'content WHERE sectionid='.strval($category['id'])." AND title NOT IN ('Joomla','utf8_general_ci','Uncategorized','Menu Item Manager','remove an Article','Trashing an Article','locale setting','edit window')");
			foreach ($rows as $i=>$row)
			{
				$i=0;
				$val=htmlentities($row['title'], ENT_QUOTES);
				$val_id=$GLOBALS['SITE_DB']->query("SELECT id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."translate WHERE text_original='".db_escape_string($val)."'");

				if (count($val_id) > 0)
				{
					$val = $val_id[0]['id'];
					$query="SELECT CE.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_entries  AS CE INNER JOIN  ".$GLOBALS['SITE_DB']->get_table_prefix()."catalogue_efv_short_trans AS CES ON CES.ce_id=CE.id AND CES.cv_value='".strval($val)."' AND CE.c_name='faqs' AND CE.cc_id=".strval($id);
					$faq_id=$GLOBALS['SITE_DB']->query($query);
				} else
				{
					$faq_id=array();
				}
				if (count($faq_id) == 0)
				{
					$introtext = html_to_comcode($row['introtext']);
					$map=array($fields[0]=>$row['title'],$fields[1]=>$introtext,$fields[2]=>strval($i));
					actual_add_catalogue_entry($id,1,'',1,1,1,$map);
				}
			}
		}
	}


	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_news_and_categories($db,$table_prefix,$old_base_dir)
	{
		require_code('news');		
		$fields=collapse_1d_complexity('id',$GLOBALS['SITE_DB']->query_select('catalogue_fields',array('id'),array('c_name'=>'news')));
		$categories=$db->query("SELECT id,title,description,image FROM " .$table_prefix."sections WHERE title='News'");
		foreach ($categories as $category)
		{
			$title=$category['title'];
			$cat_id = $GLOBALS['SITE_DB']->query_select('news_categories',array('id'),array('nc_title'=>$title),'',1);
			if (count($cat_id) == 0)
			{
				$cat_title=$category['title'];
				$category_id=$GLOBALS['SITE_DB']->query("SELECT N.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."translate  AS T INNER JOIN  ".$GLOBALS['SITE_DB']->get_table_prefix()."news_categories AS N ON T.id=N.nc_title AND " .db_string_equal_to('T.text_original',$cat_title));
				if (count($category_id) == 0)
				{
					$desc=html_to_comcode($category['description']);
					$id=add_news_category($category['title'],$category['image'],$desc,NULL,NULL);
				} else
				{
					$id=$category_id[0]['id'];
				}
			} else
			{
				$id=$cat_id[0]['id'];
			}

			$rows=$db->query('SELECT * FROM '.$table_prefix.'content WHERE sectionid='.strval($category['id']));
			foreach ($rows as $row)
			{
				$val=$row['title'];
				$news_id=$GLOBALS['SITE_DB']->query("SELECT N.id FROM ".$GLOBALS['SITE_DB']->get_table_prefix()."translate  AS T INNER JOIN  ".$GLOBALS['SITE_DB']->get_table_prefix()."news AS N ON T.id=N.title AND ".db_string_equal_to('T.text_original',$val)." AND news_category=".strval($id)." AND news_category<>''");
				if (count($news_id) == 0)
				{
					$title =$row['title'];
					$news=html_to_comcode($row['introtext']);
					$author=$db->query_value_null_ok('users','name',array('id'=>$row['created_by']));
					if (is_null($author)) $author=do_lang('UNKNOWN');
					$access=$row['access'];
					if ($access == 0)
					{
						$validated=1;
					} else
					{
						$validated=0;
					}
					$allow_rating=1;
					$allow_comments=1;
					$allow_trackbacks=1;
					$notes='';
					$news_article='';
					$main_news_category=$id;
					$news_category=NULL;
					$datetimearr = explode(' ', $row['created']);
					$datearr = explode('-', $datetimearr[0]);
					$timearr = explode(':', $datetimearr[1]);
					$date = intval($datearr[2]);
					$month = intval($datearr[1]);
					$year = intval($datearr[0]);
					$hour = intval($timearr[0]);
					$min = intval($timearr[1]);
					$sec = intval($timearr[2]);
					$time = mktime($hour, $min, $sec, $month, $date, $year);
					$submitter=import_id_remap_get('member',strval($row['created_by']));
					$views=$row['hits'];
					$datetimearr = explode(' ', $row['modified']);
					$datearr = explode('-', $datetimearr[0]);
					$timearr = explode(':', $datetimearr[1]);
					$date = intval($datearr[2]);
					$month = intval($datearr[1]);
					$year = intval($datearr[0]);
					$hour = intval($timearr[0]);
					$min = intval($timearr[1]);
					$sec = intval($timearr[2]);
					$edit_date = mktime($hour, $min, $sec, $month, $date, $year);
					$nid=NULL;
					$image='newscats/'.preg_replace('#\..*$#','',$row['images']);
					@mkdir(get_custom_file_base().'/themes/default/images_custom/newscats',0777);
					fix_permissions(get_custom_file_base().'/themes/default/images_custom/newscats',0777);
					sync_file(get_custom_file_base().'/themes/default/images_custom/newscats');
					$newimagepath=get_custom_file_base().'/themes/default/images_custom/newscats/'.rawurldecode($row['images']);
					$oldimagepath = $old_base_dir."/images/stories/".rawurldecode($row['images']);
					@copy($oldimagepath, $newimagepath);
					fix_permissions($newimagepath);
					sync_file($newimagepath);
					add_news($title,$news,$author,$validated,$allow_rating,$allow_comments,$allow_trackbacks,$notes,$news_article,$main_news_category,$news_category,$time,$submitter,$views,$edit_date,$nid,$image);
				}
			}
		}
	}


	/**
	 * Convert a mySQL timestamp to a standard timestamp.
	 *
	 * @param  string			MySQL timestamp
	 * @return TIME			Standard timestamp
	 */
	function mysql_time_to_timestamp($timestamp)
	{
		return strtotime($timestamp);
	}

	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_banners($db,$table_prefix,$old_base_dir)
	{
		require_code('banners2');
		$categories=$db->query("SELECT title,id FROM ".$table_prefix."categories WHERE section='com_banner'");
		foreach ($categories as $category)
		{
			$cat_title = $category['title'];

			$category_exist=$GLOBALS['SITE_DB']->query_value_null_ok('banner_types','id',array('id'=>$category['title']));
			if (is_null($category_exist))
				add_banner_type($cat_title,1,160,600,70,1);

			$rows=$db->query("SELECT b.publish_down ,b.bid,c.title,b.name, b.clickurl, b.imageurl,b.date,bc.contact,bc.extrainfo,bc.email,b.showBanner,b.clicks,b.impmade FROM ".$table_prefix."banner b INNER JOIN " . $table_prefix."bannerclient bc ON b.cid=bc.cid INNER JOIN ".$table_prefix."categories c ON b.catid=c.id AND c.title='".db_escape_string($cat_title)."' AND c.title <> ''");
			foreach ($rows as $row)
			{
				$name=$row['name'].strval($row['bid']);
				$test=$GLOBALS['SITE_DB']->query_value_null_ok('banners','name',array('name'=>$name));
				if (is_null($test))
				{
					if ($row['imageurl']!='')
					{
						$newimagepath=get_custom_file_base().'/uploads/banners/'.rawurldecode($row['imageurl']);
						$newimage = $row['imageurl'];
						$oldimagepath = $old_base_dir."/images/banners/".rawurldecode($row['imageurl']);
						@copy($oldimagepath, $newimagepath);
					} else
					{
						$newimage='';
					}
					$type=0; // Permanent
					$campaignremaining=0; // Irrelevant
					$caption=$row['name'];
					$end_date=$this->mysql_time_to_timestamp($row['publish_down']);
					if ($end_date === false) $end_date=NULL;
					$url=$row['clickurl'];
					$image_url=$newimage;
					$member=$GLOBALS['FORUM_DRIVER']->get_member_from_username($row['contact']);
					if (is_null($member)) $member=get_member();
					$desc=$row['email'].chr(10).$row['extrainfo'];
					$desc=html_to_comcode($desc);
					add_banner($name,$image_url,'',$caption,$campaignremaining,$url,10,$desc,$type,$end_date,$member,1,$cat_title,NULL,0,0,$row['clicks'],0,$row['impmade']);
				}
			}
		}
		$row_start=0;
		$rows=array();
		do
		{
			$rows=$db->query("SELECT u.id, u.username, u.password, u.email, u.id, u.registerDate, u.lastvisitDate, u.sendEmail FROM ".$table_prefix."bannerclient AS b INNER JOIN ".$table_prefix."users AS u ON b.contact=u.name",200,$row_start);
			foreach ($rows as $row)
			{
				if (import_check_if_imported('member',strval($row['id']))) continue;
				$test=$GLOBALS['OCF_DRIVER']->get_member_from_username($row['username']);
				if (!is_null($test))
				{
					import_id_remap_put('member',strval($row['id']),$test);
					continue;
				}
				$primary_group=get_first_default_group();
				$custom_fields=array();
				$datetimearr = explode(' ', $row['registerDate']);
				$datearr = explode('-', $datetimearr[0]);
				$timearr = explode(':', $datetimearr[1]);
				$date = $datearr[2];
				$month = $datearr[1];
				$year = $datearr[0];
				$hour = $timearr[0];
				$min = $timearr[1];
				$sec = $timearr[2];
				$register_date = mktime($hour, $min, $sec, $month, $date, $year);

				$datetimearr = explode(' ', $row['lastvisitDate']);
				$datearr = explode('-', $datetimearr[0]);
				$timearr = explode(':', $datetimearr[1]);
				$date = $datearr[2];
				$month = $datearr[1];
				$year = $datearr[0];
				$hour = $timearr[0];
				$min = $timearr[1];
				$sec = $timearr[2];
				$last_visit_date = mktime($hour, $min, $sec, $month, $date, $year);

				$id=(get_param_integer('keep_preserve_ids',0)==0)?NULL:$row['id'];
				$id_new=ocf_make_member($row['username'],$row['password'],$row['email'],NULL,NULL,NULL,NULL,$custom_fields,NULL,$primary_group,1,$register_date,$last_visit_date,'',NULL,'',0,0,1,$row['name'],'','',1,1,NULL,$row['sendEmail'],$row['sendEmail'],'',NULL,'',FALSE,NULL,'',1,$last_visit_date,$id,0,'*','');

				import_id_remap_put('member',strval($row['id']),$id_new);
			}
			$row_start+=200;
		}
		while (count($rows)>0);	
	}


	/**
	 * Standard import function.
	 *
	 * @param  object			The DB connection to import from
	 * @param  string			The table prefix the target prefix is using
	 * @param  PATH			The base directory we are importing from
	 */
	function import_polls($db,$table_prefix,$old_base_dir)
	{
		global $M_SORT_KEY;
		require_code('polls');
		$polls=$db->query('SELECT * FROM '.$table_prefix.'polls');
		foreach ($polls as $row)
		{
			$pollid = $row['id'];
			// Find options on poll
			$data=$db->query('SELECT text,hits,id FROM '.$table_prefix.'poll_data WHERE pollid='.strval($pollid).' ORDER BY hits',10);
			$M_SORT_KEY = 'id';
			usort($data,'multi_sort');
			$num_options=0;
			$i=0;
			$optionlist=array();
			foreach ($data as $option)
			{
				if ($option['text']!='')
				{
					$optionlist[$i]['text'] = $option['text'];
					$optionlist[$i]['hits'] = $option['hits'];
					$num_options++;
				} else
				{
					$optionlist[$i]['text'] = $option['text'];
					$optionlist[$i]['hits'] = $option['hits'];
            }
				$i++;
			}
			$datetimearr = explode(' ', $row['checked_out_time']);
			$datearr = explode('-', $datetimearr[0]);
			$timearr = explode(':', $datetimearr[1]);
			$date = intval($datearr[2]);
			$month = intval($datearr[1]);
			$year = intval($datearr[0]);
			$hour = intval($timearr[0]);
			$min = intval($timearr[1]);
			$sec = intval($timearr[2]);
			$time = mktime($hour, $min, $sec, $month, $date, $year);
			// Add poll
			$id=add_poll($row['title'],$optionlist[0]['text'],$optionlist[1]['text'],$optionlist[2]['text'],$optionlist[3]['text'],$optionlist[4]['text'],$optionlist[5]['text'],$optionlist[6]['text'],$optionlist[7]['text'],$optionlist[8]['text'],$optionlist[9]['text'],$num_options,1,1,1,1,'',$time,get_member(),$time,$optionlist[0]['hits'],$optionlist[1]['hits'],$optionlist[2]['hits'],$optionlist[3]['hits'],$optionlist[4]['hits'],$optionlist[5]['hits'],$optionlist[6]['hits'],$optionlist[7]['hits'],$optionlist[8]['hits'],$optionlist[9]['hits']);		
		}
	}	
}
