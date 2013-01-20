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
 * @package		news
 */

/**
 * Add a news category of the specified details.
 *
 * @param  SHORT_TEXT	The news category title
 * @param  ID_TEXT		The theme image ID of the picture to use for the news category
 * @param  LONG_TEXT		Notes for the news category
 * @param  ?MEMBER		The owner (NULL: public)
 * @param  ?AUTO_LINK	Force an ID (NULL: don't force an ID)
 * @return AUTO_LINK		The ID of our new news category
 */
function add_news_category($title,$img,$notes,$owner=NULL,$id=NULL)
{
	$map=array('nc_title'=>insert_lang($title,1),'nc_img'=>$img,'notes'=>$notes,'nc_owner'=>$owner);
	if (!is_null($id)) $map['id']=$id;
	$id=$GLOBALS['SITE_DB']->query_insert('news_categories',$map,true);

	log_it('ADD_NEWS_CATEGORY',strval($id),$title);

	decache('side_news_categories');

	return $id;
}

/**
 * Edit a news category.
 *
 * @param  AUTO_LINK			The news category to edit
 * @param  ?SHORT_TEXT		The title (NULL: keep as-is)
 * @param  ?SHORT_TEXT		The image (NULL: keep as-is)
 * @param  ?LONG_TEXT		The notes (NULL: keep as-is)
 * @param  ?MEMBER			The owner (NULL: public)
*/
function edit_news_category($id,$title,$img,$notes,$owner=NULL)
{
	$myrows=$GLOBALS['SITE_DB']->query_select('news_categories',array('nc_title','nc_img','notes'),array('id'=>$id),'',1);
	if (!array_key_exists(0,$myrows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
	$myrow=$myrows[0];

	require_code('urls2');
	suggest_new_idmoniker_for('news','misc',strval($id),$title);

	log_it('EDIT_NEWS_CATEGORY',strval($id),$title);

	if (is_null($title)) $title=get_translated_text($myrow['nc_title']);
	if (is_null($img)) $img=$myrow['nc_img'];
	if (is_null($notes)) $notes=$myrow['notes'];

	$GLOBALS['SITE_DB']->query_update('news_categories',array('nc_title'=>lang_remap($myrow['nc_title'],$title),'nc_img'=>$img,'notes'=>$notes,'nc_owner'=>$owner),array('id'=>$id),'',1);

	require_code('themes2');
	tidy_theme_img_code($img,$myrow['nc_img'],'news_categories','nc_img');

	decache('main_news');
	decache('side_news');
	decache('side_news_archive');
	decache('bottom_news');
	decache('side_news_categories');
}

/**
 * Delete a news category.
 *
 * @param  AUTO_LINK		The news category to delete
 */
function delete_news_category($id)
{
	$rows=$GLOBALS['SITE_DB']->query_select('news_categories',array('nc_title','nc_img'),array('id'=>$id),'',1);
	if (!array_key_exists(0,$rows)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
	$myrow=$rows[0];

	$min=$GLOBALS['SITE_DB']->query_value_if_there('SELECT MIN(id) FROM '.get_table_prefix().'news_categories WHERE id<>'.strval($id));
	if (is_null($min))
	{
		warn_exit(do_lang_tempcode('YOU_MUST_KEEP_ONE_NEWS_CAT'));
	}

	log_it('DELETE_NEWS_CATEGORY',strval($id),get_translated_text($myrow['nc_title']));

	delete_lang($myrow['nc_title']);

	$GLOBALS['SITE_DB']->query_update('news',array('news_category'=>$min),array('news_category'=>$id));
	$GLOBALS['SITE_DB']->query_delete('news_categories',array('id'=>$id),'',1);
	$GLOBALS['SITE_DB']->query_delete('news_category_entries',array('news_entry_category'=>$id));

	$GLOBALS['SITE_DB']->query_delete('group_category_access',array('module_the_name'=>'news','category_name'=>strval($id)));
	$GLOBALS['SITE_DB']->query_delete('group_privileges',array('module_the_name'=>'news','category_name'=>strval($id)));

	require_code('themes2');
	tidy_theme_img_code(NULL,$myrow['nc_img'],'news_categories','nc_img');

	decache('side_news_categories');
}

/**
 * Adds a news entry to the database, and send out the news to any RSS cloud listeners.
 *
 * @param  SHORT_TEXT		The news title
 * @param  LONG_TEXT			The news summary (or if not an article, the full news)
 * @param  ?ID_TEXT			The news author (possibly, a link to an existing author in the system, but does not need to be) (NULL: current username)
 * @param  BINARY				Whether the news has been validated
 * @param  BINARY				Whether the news may be rated
 * @param  SHORT_INTEGER	Whether comments are allowed (0=no, 1=yes, 2=review style)
 * @param  BINARY				Whether the news may have trackbacks
 * @param  LONG_TEXT			Notes for the news
 * @param  LONG_TEXT			The news entry (blank means no entry)
 * @param  ?AUTO_LINK		The primary news category (NULL: personal)
 * @param  ?array				The IDs of the news categories that this is in (NULL: none)
 * @param  ?TIME				The time of submission (NULL: now)
 * @param  ?MEMBER			The news submitter (NULL: current member)
 * @param  integer			The number of views the article has had
 * @param  ?TIME				The edit date (NULL: never)
 * @param  ?AUTO_LINK		Force an ID (NULL: don't force an ID)
 * @param  URLPATH			URL to the image for the news entry (blank: use cat image)
 * @return AUTO_LINK			The ID of the news just added
 */
function add_news($title,$news,$author=NULL,$validated=1,$allow_rating=1,$allow_comments=1,$allow_trackbacks=1,$notes='',$news_article='',$main_news_category=NULL,$news_category=NULL,$time=NULL,$submitter=NULL,$views=0,$edit_date=NULL,$id=NULL,$image='')
{
	if (is_null($author)) $author=$GLOBALS['FORUM_DRIVER']->get_username(get_member());
	if (is_null($news_category)) $news_category=array();
	if (is_null($time)) $time=time();
	if (is_null($submitter)) $submitter=get_member();
	$already_created_personal_category=false;

	require_code('comcode_check');
	check_comcode($news_article,NULL,false,NULL,true);

	if (is_null($main_news_category))
	{
		$main_news_category_id=$GLOBALS['SITE_DB']->query_select_value_if_there('news_categories','id',array('nc_owner'=>$submitter));
		if (is_null($main_news_category_id))
		{
			if (!has_privilege(get_member(),'have_personal_category','cms_news')) fatal_exit(do_lang_tempcode('INTERNAL_ERROR'));

			$p_nc_title=insert_lang(do_lang('MEMBER_CATEGORY',$GLOBALS['FORUM_DRIVER']->get_username($submitter)),2);

			$main_news_category_id=$GLOBALS['SITE_DB']->query_insert('news_categories',array('nc_title'=>$p_nc_title,'nc_img'=>'newscats/community','notes'=>'','nc_owner'=>$submitter),true);
			$already_created_personal_category=true;

			$groups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list(false,true);

			foreach (array_keys($groups) as $group_id)
				$GLOBALS['SITE_DB']->query_insert('group_category_access',array('module_the_name'=>'news','category_name'=>strval($main_news_category_id),'group_id'=>$group_id));
		}
	}
	else $main_news_category_id=$main_news_category;

	if (!addon_installed('unvalidated')) $validated=1;
	$map=array('news_image'=>$image,'edit_date'=>$edit_date,'news_category'=>$main_news_category_id,'news_views'=>$views,'news_article'=>0,'allow_rating'=>$allow_rating,'allow_comments'=>$allow_comments,'allow_trackbacks'=>$allow_trackbacks,'notes'=>$notes,'submitter'=>$submitter,'validated'=>$validated,'date_and_time'=>$time,'title'=>insert_lang_comcode($title,1),'news'=>insert_lang_comcode($news,1),'author'=>$author);
	if (!is_null($id)) $map['id']=$id;
	$id=$GLOBALS['SITE_DB']->query_insert('news',$map,true);

	if (!is_null($news_category))
	{
		foreach ($news_category as $value)
		{
			if ((is_null($value)) && (!$already_created_personal_category))
			{
				$p_nc_title=insert_lang(do_lang('MEMBER_CATEGORY',$GLOBALS['FORUM_DRIVER']->get_username($submitter)),2);
				$news_category_id=$GLOBALS['SITE_DB']->query_insert('news_categories',array('nc_title'=>$p_nc_title,'nc_img'=>'newscats/community','notes'=>'','nc_owner'=>$submitter),true);

				$groups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list(false,true);

				foreach (array_keys($groups) as $group_id)
					$GLOBALS['SITE_DB']->query_insert('group_category_access',array('module_the_name'=>'news','category_name'=>strval($news_category_id),'group_id'=>$group_id));
			}
			else $news_category_id=$value;

			if (is_null($news_category_id)) continue; // Double selected

			$GLOBALS['SITE_DB']->query_insert('news_category_entries',array('news_entry'=>$id,'news_entry_category'=>$news_category_id));
		}
	}

	require_code('attachments2');
	$map=array('news_article'=>insert_lang_comcode_attachments(2,$news_article,'news',strval($id)));
	$GLOBALS['SITE_DB']->query_update('news',$map,array('id'=>$id),'',1);

	log_it('ADD_NEWS',strval($id),$title);

	if (function_exists('xmlrpc_encode'))
	{
		if (function_exists('set_time_limit')) @set_time_limit(0);

		// Send out on RSS cloud
		$GLOBALS['SITE_DB']->query('DELETE FROM '.get_table_prefix().'news_rss_cloud WHERE register_time<'.strval(time()-25*60*60));
		$start=0;
		do
		{
			$listeners=$GLOBALS['SITE_DB']->query_select('news_rss_cloud',array('*'),NULL,'',100,$start);
			foreach ($listeners as $listener)
			{
				$data=$listener['watching_channel'];
				if ($listener['rem_protocol']=='xml-rpc')
				{
					$request=xmlrpc_encode_request($listener['rem_procedure'],$data);
					$length=strlen($request);
					$_length=strval($length);
$packet=<<<END
POST /{$listener['rem_path']} HTTP/1.0
Host: {$listener['rem_ip']}
Content-Type: text/xml
Content-length: {$_length}

{$request}
END;
				}
				$errno=0;
				$errstr='';
				$mysock=@fsockopen($listener['rem_ip'],$listener['rem_port'],$errno,$errstr,6.0);
				if ($mysock!==false)
				{
					@fwrite($mysock,$packet);
					@fclose($mysock);
				}
				$start+=100;
			}
		}
		while (array_key_exists(0,$listeners));
	}

	require_code('seo2');
	seo_meta_set_for_implicit('news',strval($id),array($title,($news=='')?$news_article:$news/*,$news_article*/),($news=='')?$news_article:$news); // News article could be used, but it's probably better to go for the summary only to avoid crap

	if ($validated==1)
	{
		decache('main_news');
		decache('side_news');
		decache('side_news_archive');
		decache('bottom_news');

		dispatch_news_notification($id,$title,$main_news_category_id);
	}

	if (($validated==1) && (get_option('site_closed')=='0') && (ocp_srv('HTTP_HOST')!='127.0.0.1') && (ocp_srv('HTTP_HOST')!='localhost') && (has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(),'news',strval($main_news_category_id))))
	{
		$_ping_url=str_replace('{url}',urlencode(get_base_url()),str_replace('{rss}',urlencode(find_script('backend')),str_replace('{title}',urlencode(get_site_name()),get_option('ping_url'))));
		$ping_urls=explode(chr(10),$_ping_url);
		foreach ($ping_urls as $ping_url)
		{
			$ping_url=trim($ping_url);
			if ($ping_url!='') http_download_file($ping_url,NULL,false);
		}
	}

	return $id;
}

/**
 * Edit a news entry.
 *
 * @param  AUTO_LINK			The ID of the news to edit
 * @param  SHORT_TEXT		The news title
 * @param  LONG_TEXT			The news summary (or if not an article, the full news)
 * @param  ID_TEXT			The news author (possibly, a link to an existing author in the system, but does not need to be)
 * @param  BINARY				Whether the news has been validated
 * @param  BINARY				Whether the news may be rated
 * @param  SHORT_INTEGER	Whether comments are allowed (0=no, 1=yes, 2=review style)
 * @param  BINARY				Whether the news may have trackbacks
 * @param  LONG_TEXT			Notes for the news
 * @param  LONG_TEXT			The news entry (blank means no entry)
 * @param  AUTO_LINK			The primary news category (NULL: personal)
 * @param  ?array				The IDs of the news categories that this is in (NULL: do not change)
 * @param  SHORT_TEXT		Meta keywords
 * @param  LONG_TEXT			Meta description
 * @param  ?URLPATH			URL to the image for the news entry (blank: use cat image) (NULL: don't delete existing)
 * @param  ?TIME				Recorded add time (NULL: leave alone)
 */
function edit_news($id,$title,$news,$author,$validated,$allow_rating,$allow_comments,$allow_trackbacks,$notes,$news_article,$main_news_category,$news_category,$meta_keywords,$meta_description,$image,$time=NULL)
{
	$rows=$GLOBALS['SITE_DB']->query_select('news',array('title','news','news_article','submitter'),array('id'=>$id),'',1);
	$_title=$rows[0]['title'];
	$_news=$rows[0]['news'];
	$_news_article=$rows[0]['news_article'];

	require_code('urls2');

	suggest_new_idmoniker_for('news','view',strval($id),$title);

	require_code('attachments2');
	require_code('attachments3');

	if (!addon_installed('unvalidated')) $validated=1;

	require_code('submit');
	$just_validated=(!content_validated('news',strval($id))) && ($validated==1);
	if ($just_validated)
	{
		send_content_validated_notification('news',strval($id));
	}

	$map=array('news_category'=>$main_news_category,'news_article'=>update_lang_comcode_attachments($_news_article,$news_article,'news',strval($id),NULL,false,$rows[0]['submitter']),'edit_date'=>time(),'allow_rating'=>$allow_rating,'allow_comments'=>$allow_comments,'allow_trackbacks'=>$allow_trackbacks,'notes'=>$notes,'validated'=>$validated,'title'=>lang_remap_comcode($_title,$title),'news'=>lang_remap_comcode($_news,$news),'author'=>$author);

	if (!is_null($time)) $map['date_and_time']=$time;

	if (!is_null($image))
	{
		$map['news_image']=$image;
		require_code('files2');
		delete_upload('uploads/grepimages','news','news_image','id',$id,$image);
	}

	if (!is_null($news_category))
	{
		$GLOBALS['SITE_DB']->query_delete('news_category_entries',array('news_entry'=>$id));

		foreach ($news_category as $value)
		{
			$GLOBALS['SITE_DB']->query_insert('news_category_entries',array('news_entry'=>$id,'news_entry_category'=>$value));
		}
	}

	log_it('EDIT_NEWS',strval($id),$title);

	$GLOBALS['SITE_DB']->query_update('news',$map,array('id'=>$id),'',1);

	$self_url=build_url(array('page'=>'news','type'=>'view','id'=>$id),get_module_zone('news'),NULL,false,false,true);

	if ($just_validated)
	{
		dispatch_news_notification($id,$title,$main_news_category);
	}

	require_code('seo2');
	seo_meta_set_for_explicit('news',strval($id),$meta_keywords,$meta_description);

	decache('main_news');
	decache('side_news');
	decache('side_news_archive');
	decache('bottom_news');

	if (($validated==1) && (has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(),'news',strval($main_news_category))))
	{
		$_ping_url=str_replace('{url}',urlencode(get_base_url()),str_replace('{rss}',urlencode(find_script('backend')),str_replace('{title}',urlencode(get_site_name()),get_option('ping_url'))));
		$ping_urls=explode(',',$_ping_url);
		foreach ($ping_urls as $ping_url)
		{
			$ping_url=trim($ping_url);
			if ($ping_url!='') http_download_file($ping_url,NULL,false);
		}
	}

	require_code('feedback');
	update_spacer_post($allow_comments!=0,'news',strval($id),$self_url,$title,get_value('comment_forum__news'));
}

/**
 * Send out a notification of some new news.
 *
 * @param  AUTO_LINK		The ID of the news
 * @param  SHORT_TEXT	The title
 * @param  AUTO_LINK		The main news category
 */
function dispatch_news_notification($id,$title,$main_news_category)
{
	$self_url=build_url(array('page'=>'news','type'=>'view','id'=>$id),get_module_zone('news'),NULL,false,false,true);

	$is_blog=!is_null($GLOBALS['SITE_DB']->query_select_value('news_categories','nc_owner',array('id'=>$main_news_category)));

	require_code('notifications');
	require_lang('news');
	if ($is_blog)
	{
		$subject=do_lang('BLOG_NOTIFICATION_MAIL_SUBJECT',get_site_name(),$title);
		$mail=do_lang('BLOG_NOTIFICATION_MAIL',comcode_escape(get_site_name()),comcode_escape($title),array($self_url->evaluate()));
		dispatch_notification('news_entry',strval($main_news_category),$subject,$mail);
	} else
	{
		$subject=do_lang('NEWS_NOTIFICATION_MAIL_SUBJECT',get_site_name(),$title);
		$mail=do_lang('NEWS_NOTIFICATION_MAIL',comcode_escape(get_site_name()),comcode_escape($title),array($self_url->evaluate()));
		dispatch_notification('news_entry',strval($main_news_category),$subject,$mail);
	}
}

/**
 * Delete a news entry.
 *
 * @param  AUTO_LINK		The ID of the news to edit
 */
function delete_news($id)
{
	$rows=$GLOBALS['SITE_DB']->query_select('news',array('title','news','news_article'),array('id'=>$id),'',1);
	$title=$rows[0]['title'];
	$news=$rows[0]['news'];
	$news_article=$rows[0]['news_article'];

	$_title=get_translated_text($title);
	log_it('DELETE_NEWS',strval($id),$_title);

	require_code('files2');
	delete_upload('uploads/grepimages','news','news_image','id',$id);

	$GLOBALS['SITE_DB']->query_delete('news',array('id'=>$id),'',1);
	$GLOBALS['SITE_DB']->query_delete('news_category_entries',array('news_entry'=>$id));

	$GLOBALS['SITE_DB']->query_delete('rating',array('rating_for_type'=>'news','rating_for_id'=>$id));
	$GLOBALS['SITE_DB']->query_delete('trackbacks',array('trackback_for_type'=>'news','trackback_for_id'=>$id));

	delete_lang($title);
	delete_lang($news);
	require_code('attachments2');
	require_code('attachments3');
	if (!is_null($news_article)) delete_lang_comcode_attachments($news_article,'news',strval($id));

	require_code('seo2');
	seo_meta_erase_storage('news',strval($id));

	decache('main_news');
	decache('side_news');
	decache('side_news_archive');
	decache('bottom_news');
}

/**
 * Import wordpress db
 */
function import_wordpress_db()
{
	disable_php_memory_limit();

	$data=get_wordpress_data();
	$is_validated=post_param_integer('wp_auto_validate',0);
	$to_own_account=post_param_integer('wp_add_to_own',0);	

	// Create members
	require_code('ocf_members_action');
	require_code('ocf_groups');

	$def_grp_id=get_first_default_group();
	$cat_id=array();	

	$NEWS_CATS_CACHE=$GLOBALS['SITE_DB']->query_select('news_categories',array('*'),array('nc_owner'=>NULL));

	$NEWS_CATS_CACHE=list_to_map('id',$NEWS_CATS_CACHE);

	foreach ($data as $values)
	{
		if (get_forum_type()=='ocf')
		{
			$member_id=$GLOBALS['FORUM_DB']->query_select_value_if_there('f_members','id',array('m_username'=>$values['user_login']));

			if (is_null($member_id))
			{
				if (post_param_integer('wp_import_wordpress_users',0)==1)
				{
					$member_id=ocf_make_member($values['user_login'],$values['user_pass'],'',NULL,NULL,NULL,NULL,array(),NULL,$def_grp_id,1,time(),time(),'',NULL,'',0,0,1,'','','',1,0,NULL,1,1,'','',false,'wordpress');
				} else
				{
					$member_id=$GLOBALS['FORUM_DRIVER']->get_member_from_username('admin');	// Set admin as owner
					if (is_null($member_id)) $member_id=$GLOBALS['FORUM_DRIVER']->get_guest_id()+1;
				}
			}
		}
		else
			$member_id=$GLOBALS['FORUM_DRIVER']->get_guest_id(); // Guest user

		// If post should go to own account
		if ($to_own_account==1)	$member_id=get_member();			

		if (array_key_exists('POSTS',$values))
		{
			// Create posts in blog
			foreach ($values['POSTS'] as $post_id=>$post)
			{	
				if (array_key_exists('category',$post))
				{	
					$cat_id=array();
					foreach ($post['category'] as $cat_code=>$category)
					{	
						$cat_code=NULL;
						if ($category=='Uncategorized')	continue;	// Skip blank category creation
						foreach ($NEWS_CATS_CACHE as $id=>$existing_cat)
						{
							if (get_translated_text($existing_cat['nc_title'])==$category)
							{
								$cat_code=$id;
							}
						}
						if (is_null($cat_code))	// Cound not find existing category, create new
						{
							$cat_code=add_news_category($category,'newscats/community',$category);
							$NEWS_CATS_CACHE=$GLOBALS['SITE_DB']->query_select('news_categories',array('*'));	
							$NEWS_CATS_CACHE=list_to_map('id',$NEWS_CATS_CACHE);
						}
						$cat_id=array_merge($cat_id,array($cat_code));
					}
				}

				$owner_category_id=$GLOBALS['SITE_DB']->query_select_value_if_there('news_categories','id',array('nc_owner'=>$member_id));

				if ($post['post_type']=='post') // Posts
				{
					$id=add_news($post['post_title'],html_to_comcode($post['post_content']),NULL,$is_validated,1,($post['comment_status']=='closed')?0:1,1,'',html_to_comcode($post['post_content']),$owner_category_id,$cat_id,NULL,$member_id,0,time(),NULL,'');
				}
				elseif ($post['post_type']=='page') // Page/articles
				{
					// If dont have permission to write comcode page, skip the post
					if (!has_submit_permission('high',get_member(),get_ip_address(),NULL,NULL))	continue;

					require_code('comcode');
					// Save articles as new comcode pages
					$zone=filter_naughty(post_param('zone','site'));
					$lang=filter_naughty(post_param('lang','EN'));
					$file=preg_replace('/[^A-Za-z0-9]/','_',$post['post_title']); // Filter non alphanumeric charactors
					$parent_page=post_param('parent_page','');
					$fullpath=zone_black_magic_filterer(get_custom_file_base().'/'.$zone.'/pages/comcode_custom/'.$lang.'/'.$file.'.txt');

					// Check existancy of new page
					$submiter=$GLOBALS['SITE_DB']->query_select_value_if_there('comcode_pages','p_submitter',array('the_zone'=>$zone,'the_page'=>$file));

					if (!is_null($submiter)) continue; // Skip existing titled articles	- may need change

					require_code('submit');
					give_submit_points('COMCODE_PAGE_ADD');

					if (!addon_installed('unvalidated')) $is_validated=1;
					$GLOBALS['SITE_DB']->query_insert('comcode_pages',array(
						'the_zone'=>$zone,
						'the_page'=>$file,
						'p_parent_page'=>$parent_page,
						'p_validated'=>$is_validated,
						'p_edit_date'=>NULL,
						'p_add_date'=>strtotime($post['post_date']),
						'p_submitter'=>$member_id,
						'p_show_as_edit'=>0
					));

					if ((!file_exists($fullpath)))
					{
						$_content=html_to_comcode($post['post_content']);
						$myfile=@fopen($fullpath,'wt');
						if ($myfile===false) intelligent_write_error($fullpath);
						if (fwrite($myfile,$_content)<strlen($_content)) warn_exit(do_lang_tempcode('COULD_NOT_SAVE_FILE'));

						fclose($myfile);
						sync_file($fullpath);
					}

					require_code('seo2');
					seo_meta_set_for_explicit('comcode_page',$zone.':'.$file,post_param('meta_keywords',''),post_param('meta_description',''));

					require_code('permissions2');
					set_page_permissions_from_environment($zone,$file);
				}

				$content_url=build_url(array('page'=>'news','type'=>'view','id'=>$id),get_module_zone('news'),NULL,false,false,true);
				$content_title=$post['post_title'];

				// Add comments
				if (post_param_integer('wp_import_blog_comments',0)==1)
				{
					if (array_key_exists('COMMENTS',$post))
					{
						$submitter=NULL;
						foreach ($post['COMMENTS'] as $comment)
						{
							$submitter=$GLOBALS['FORUM_DB']->query_select_value_if_there('f_members','id',array('m_username'=>$comment['comment_author']));

							if (is_null($submitter)) $submitter=$GLOBALS['FORUM_DRIVER']->get_guest_id(); // If comment is made by a non-member, assign comment to guest account

							$forum=(is_null(get_value('comment_forum__news')))?get_option('comments_forum_name'):get_value('comment_forum__news');

							$result=$GLOBALS['FORUM_DRIVER']->make_post_forum_topic(
								$forum,
								'news_'.strval($id),
								$submitter,
								$post['post_title'],
								$comment['comment_content'],
								$content_title,
								do_lang('COMMENT'),
								$content_url,
								NULL,
								NULL,
								1,
								1,
								false
							);
						}
					}
				}
			}
		}
	}
}


/**
 * Get data from wordpress db
 *
 * @return array		Result array
 */
function get_wordpress_data()
{
	$host_name=post_param('wp_host');
	$db_name=post_param('wp_db');
	$db_user=post_param('wp_db_user');
	$db_passwrod=post_param('wp_db_password');
	$db_table_prefix=post_param('wp_table_prefix');

	// Create DB connection
	$db=new database_driver($db_name,$host_name,$db_user,$db_passwrod,$db_table_prefix);

	$row=$db->query('SELECT * FROM '.db_escape_string($db_name).'.'.db_escape_string($db_table_prefix).'_users');

	$data=array();
	foreach ($row as $users)
	{
		$user_id=$users['ID'];
		$data[$user_id]=$users;
		// Fetch user posts
		$row1=$db->query('SELECT * FROM '.$db_table_prefix.'_posts WHERE post_author='.strval($user_id).' AND (post_type=\'post\' OR post_type=\'page\')');	
		foreach ($row1 as $posts)
		{
			$post_id=$posts['ID'];
			$data[$user_id]['POSTS'][$post_id]=$posts;

			// Get categories
			$row3=$db->query('SELECT t1.slug,t1.name FROM '.$db_table_prefix.'_terms t1,'.db_escape_string($db_name).'.'.db_escape_string($db_table_prefix).'_term_relationships t2 WHERE t1.term_id=t2.term_taxonomy_id AND t2.object_id='.strval($post_id));

			foreach ($row3 as $categories)
			{
				$data[$user_id]['POSTS'][$post_id]['category'][$categories['slug']]=$categories['name'];
			}
			// Comments
			$row2=$db->query('SELECT * FROM '.$db_table_prefix.'_comments WHERE comment_post_ID='.strval($post_id).' AND comment_approved=1');
			foreach ($row2 as $comments)
			{
				$comment_id=$comments['comment_ID'];
				$data[$user_id]['POSTS'][$post_id]['COMMENTS'][$comment_id]=$comments;
			}
		}
	}

	return $data;
}


