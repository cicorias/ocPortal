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
 * @package		downloads
 */

/**
 * Module page class.
 */
class Module_downloads
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
		$info['version']=6;
		$info['update_require_upgrade']=1;
		$info['locked']=false;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_if_exists('download_categories');
		$GLOBALS['SITE_DB']->drop_if_exists('download_downloads');
		$GLOBALS['SITE_DB']->drop_if_exists('download_logging');
		$GLOBALS['SITE_DB']->drop_if_exists('download_licences');

		delete_config_option('maximum_download');
		delete_config_option('is_on_downloads');
		delete_config_option('show_dload_trees');
		delete_config_option('points_ADD_DOWNLOAD');
		delete_config_option('downloads_show_stats_count_total');
		delete_config_option('downloads_show_stats_count_archive');
		delete_config_option('downloads_show_stats_count_downloads');
		delete_config_option('downloads_show_stats_count_bandwidth');
		delete_config_option('immediate_downloads');
		delete_config_option('download_gallery_root');

		$GLOBALS['SITE_DB']->query_delete('group_category_access',array('module_the_name'=>'downloads'));

		$GLOBALS['SITE_DB']->query_delete('trackbacks',array('trackback_for_type'=>'downloads'));

		delete_value('download_bandwidth');
		delete_value('archive_size');
		delete_value('num_archive_downloads');
		delete_value('num_downloads_downloaded');

		deldir_contents(get_custom_file_base().'/uploads/downloads',true);

		delete_menu_item_simple('_SEARCH:downloads:type=misc');
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
			require_lang('downloads');

			$GLOBALS['SITE_DB']->create_table('download_categories',array(
				'id'=>'*AUTO',
				'category'=>'SHORT_TRANS',
				'parent_id'=>'?AUTO_LINK',
				'add_date'=>'TIME',
				'notes'=>'LONG_TEXT',
				'description'=>'LONG_TRANS',	// Comcode
				'rep_image'=>'URLPATH'
			));

			$lang_key=lang_code_to_default_content('DOWNLOADS_HOME');
			$id=$GLOBALS['SITE_DB']->query_insert('download_categories',array('rep_image'=>'','parent_id'=>NULL,'add_date'=>time(),'notes'=>'','description'=>insert_lang_comcode('',3),'category'=>$lang_key),true);
			$groups=$GLOBALS['FORUM_DRIVER']->get_usergroup_list(false,true);
			foreach (array_keys($groups) as $group_id)
				$GLOBALS['SITE_DB']->query_insert('group_category_access',array('module_the_name'=>'downloads','category_name'=>strval($id),'group_id'=>$group_id));

			$GLOBALS['SITE_DB']->create_index('download_categories','child_find',array('parent_id'));

			$GLOBALS['SITE_DB']->create_table('download_downloads',array(
				'id'=>'*AUTO',
				'category_id'=>'AUTO_LINK',
				'name'=>'SHORT_TRANS',
				'url'=>'URLPATH',
				'description'=>'LONG_TRANS',	// Comcode
				'author'=>'ID_TEXT',
				'comments'=>'LONG_TRANS',	// Comcode
				'num_downloads'=>'INTEGER',
				'out_mode_id'=>'?AUTO_LINK',
				'add_date'=>'TIME',
				'edit_date'=>'?TIME',
				'validated'=>'BINARY',
				'default_pic'=>'INTEGER',
				'file_size'=>'?INTEGER',
				'allow_rating'=>'BINARY',
				'allow_comments'=>'SHORT_INTEGER',
				'allow_trackbacks'=>'BINARY',
				'notes'=>'LONG_TEXT',
				'download_views'=>'INTEGER',
				'download_cost'=>'INTEGER',
				'download_submitter_gets_points'=>'BINARY',
				'submitter'=>'USER',
				'original_filename'=>'SHORT_TEXT',
				'rep_image'=>'URLPATH',
				'download_licence'=>'?AUTO_LINK',
				'download_data_mash'=>'LONG_TEXT'
			));

			$GLOBALS['SITE_DB']->create_index('download_downloads','download_views',array('download_views'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','category_list',array('category_id'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','recent_downloads',array('add_date'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','top_downloads',array('num_downloads'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','downloadauthor',array('author'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','dds',array('submitter'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','ddl',array('download_licence')); // For when deleting a download license
			$GLOBALS['SITE_DB']->create_index('download_downloads','dvalidated',array('validated'));

			$GLOBALS['SITE_DB']->create_table('download_logging',array(
				'id'=>'*AUTO_LINK',
				'the_user'=>'*USER',
				'ip'=>'IP',
				'date_and_time'=>'TIME'
			));

			$GLOBALS['SITE_DB']->create_index('download_logging','calculate_bandwidth',array('date_and_time'));

			add_config_option('MAXIMUM_DOWNLOAD','maximum_download','integer','return \'15\';','SITE','CLOSED_SITE');
			add_config_option('SHOW_DLOAD_TREES','show_dload_trees','tick','return \'0\';','FEATURE','SECTION_DOWNLOADS',1);
			add_config_option('ADD_DOWNLOAD','points_ADD_DOWNLOAD','integer','return addon_installed(\'points\')?\'150\':NULL;','POINTS','COUNT_POINTS_GIVEN');

			require_lang('downloads');
			add_menu_item_simple('main_content',NULL,'SECTION_DOWNLOADS','_SEARCH:downloads:type=misc');

			$GLOBALS['SITE_DB']->create_index('download_downloads','ftjoin_dname',array('name'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','ftjoin_ddescrip',array('description'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','ftjoin_dcomments',array('comments'));
			$GLOBALS['SITE_DB']->create_index('download_categories','ftjoin_dccat',array('category'));
			$GLOBALS['SITE_DB']->create_index('download_categories','ftjoin_dcdescrip',array('description'));
		}

		if ((!is_null($upgrade_from)) && ($upgrade_from<3))
		{
			$GLOBALS['SITE_DB']->add_table_field('download_downloads','allow_trackbacks','BINARY',1);
			$GLOBALS['SITE_DB']->add_table_field('download_categories','rep_image','URLPATH');
		}

		if ((!is_null($upgrade_from)) && ($upgrade_from<5))
		{
			$GLOBALS['SITE_DB']->add_table_field('download_downloads','download_licence','?AUTO_LINK',NULL);
			$GLOBALS['SITE_DB']->add_table_field('download_downloads','download_data_mash','LONG_TEXT');
			delete_config_option('is_on_downloads');
		}

		if ((is_null($upgrade_from)) || ($upgrade_from<5))
		{
			$GLOBALS['SITE_DB']->create_index('download_downloads','#download_data_mash',array('download_data_mash'));
			$GLOBALS['SITE_DB']->create_index('download_downloads','#original_filename',array('original_filename'));

			$GLOBALS['SITE_DB']->create_table('download_licences',array(
				'id'=>'*AUTO',
				'l_title'=>'SHORT_TEXT',
				'l_text'=>'LONG_TEXT'
			));

			add_config_option('_SECTION_DOWNLOADS','downloads_show_stats_count_total','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('TOTAL_DOWNLOADS_IN_ARCHIVE','downloads_show_stats_count_archive','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('_COUNT_DOWNLOADS','downloads_show_stats_count_downloads','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('_COUNT_BANDWIDTH','downloads_show_stats_count_bandwidth','tick','return addon_installed(\'stats_block\')?\'0\':NULL;','BLOCKS','STATISTICS');
			add_config_option('IMMEDIATE_DOWNLOADS','immediate_downloads','tick','return \'0\';','FEATURE','SECTION_DOWNLOADS');
		}

		if ((is_null($upgrade_from)) || ($upgrade_from<6))
		{
			add_config_option('DOWNLOAD_GALLERY_ROOT','download_gallery_root','line','return is_null($old=get_value(\'download_gallery_root\'))?(addon_installed(\'galleries\')?\'root\':NULL):$old;','FEATURE','SECTION_DOWNLOADS');
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('misc'=>'DOWNLOADS_HOME','tree_view'=>'TREE');
	}

	/**
	 * Standard modular page-link finder function (does not return the main entry-points that are not inside the tree).
	 *
	 * @param  ?integer  The number of tree levels to computer (NULL: no limit)
	 * @param  boolean	Whether to not return stuff that does not support permissions (unless it is underneath something that does).
	 * @param  ?string	Position to start at in the tree. Does not need to be respected. (NULL: from root)
	 * @param  boolean	Whether to avoid returning categories.
	 * @return ?array	 	A tuple: 1) full tree structure [made up of (pagelink, permission-module, permissions-id, title, children, ?entry point for the children, ?children permission module, ?whether there are children) OR a list of maps from a get_* function] 2) permissions-page 3) optional base entry-point for the tree 4) optional permission-module 5) optional permissions-id (NULL: disabled).
	 */
	function get_page_links($max_depth=NULL,$require_permission_support=false,$start_at=NULL,$dont_care_about_categories=false)
	{
		unset($require_permission_support);

		$permission_page='cms_downloads';

		require_code('downloads');

		$category_id=NULL;
		if (!is_null($start_at))
		{
			$matches=array();
			if (preg_match('#[^:]*:downloads:type=misc:id=(.*)#',$start_at,$matches)!=0) $category_id=intval($matches[1]);
		}

		$adjusted_max_depth=is_null($max_depth)?NULL:(is_null($category_id)?($max_depth-1):$max_depth);
		return array($dont_care_about_categories?array():get_download_category_tree($category_id,NULL,NULL,false,false,$adjusted_max_depth,false),$permission_page,'_SELF:_SELF:type=misc:id=!','downloads');
	}

	/**
	 * Standard modular new-style deep page-link finder function (does not return the main entry-points).
	 *
	 * @param  string  	Callback function to send discovered page-links to.
	 * @param  MEMBER		The member we are finding stuff for (we only find what the member can view).
	 * @param  integer	Code for how deep we are tunnelling down, in terms of whether we are getting entries as well as categories.
	 * @param  string		Stub used to create page-links. This is passed in because we don't want to assume a zone or page name within this function.
	 * @param  ?string	Where we're looking under (NULL: root of tree). We typically will NOT show a root node as there's often already an entry-point representing it.
	 * @param  integer	Our recursion depth (used to calculate importance of page-link, used for instance by Google sitemap). Deeper is typically less important.
	 * @param  ?array		Non-standard for API [extra parameter tacked on] (NULL: yet unknown). Contents of database table for performance.
	 * @param  ?array		Non-standard for API [extra parameter tacked on] (NULL: yet unknown). Contents of database table for performance.
	 */
	function get_sitemap_pagelinks($callback,$member_id,$depth,$pagelink_stub,$parent_pagelink=NULL,$recurse_level=0,$category_data=NULL,$entry_data=NULL)
	{
		// This is where we start
		if (is_null($parent_pagelink))
		{
			$parent_pagelink=$pagelink_stub.':misc'; // This is the entry-point we're under
			$parent_attributes=array('id'=>strval(db_get_first_id()));
		} else
		{
			list(,$parent_attributes,)=page_link_decode($parent_pagelink);
		}

		// We read in all data for efficiency
		if (is_null($category_data))
			$category_data=$GLOBALS['SITE_DB']->query_select('download_categories d LEFT JOIN '.get_table_prefix().'translate t ON '.db_string_equal_to('language',user_lang()).' AND t.id=d.category',array('d.id','t.text_original AS title','parent_id','add_date AS edit_date'));

		// Subcategories
		foreach ($category_data as $row)
		{
			if ((!is_null($row['parent_id'])) && (strval($row['parent_id'])==$parent_attributes['id']))
			{
				$pagelink=$pagelink_stub.'misc:'.strval($row['id']);
				if (__CLASS__!='')
				{
					$this->get_sitemap_pagelinks($callback,$member_id,$depth,$pagelink_stub,$pagelink,$recurse_level+1,$category_data,$entry_data); // Recurse
				} else
				{
					call_user_func_array(__FUNCTION__,array($callback,$member_id,$depth,$pagelink_stub,$pagelink,$recurse_level+1,$category_data,$entry_data)); // Recurse
				}
				if (has_category_access($member_id,'downloads',strval($row['id'])))
				{
					call_user_func_array($callback,array($pagelink,$parent_pagelink,NULL,$row['edit_date'],max(0.7-$recurse_level*0.1,0.3),$row['title'])); // Callback
				} else // Not accessible: we need to copy the node through, but we will flag it 'Unknown' and say it's not accessible.
				{
					call_user_func_array($callback,array($pagelink,$parent_pagelink,NULL,$row['edit_date'],max(0.7-$recurse_level*0.1,0.3),do_lang('UNKNOWN'),false)); // Callback
				}
			}
		}

		// Entries
		if (($depth>=DEPTH__ENTRIES) && (has_category_access($member_id,'downloads',$parent_attributes['id'])))
		{
			$start=0;
			do
			{
				$entry_data=$GLOBALS['SITE_DB']->query_select('download_downloads d LEFT JOIN '.get_table_prefix().'translate t ON '.db_string_equal_to('language',user_lang()).' AND t.id=d.name',array('d.id','t.text_original AS title','category_id','add_date','edit_date'),array('category_id'=>intval($parent_attributes['id'])),'',500,$start);

				foreach ($entry_data as $row)
				{
					$pagelink=$pagelink_stub.'entry:'.strval($row['id']);
					call_user_func_array($callback,array($pagelink,$parent_pagelink,$row['add_date'],$row['edit_date'],0.2,$row['title'])); // Callback
				}

				$start+=500;
			}
			while (array_key_exists(0,$entry_data));
		}
	}

	/**
	 * Convert a page link to a category ID and category permission module type.
	 *
	 * @param  string	The page link
	 * @return array	The pair
	 */
	function extract_page_link_permissions($page_link)
	{
		$matches=array();
		preg_match('#^([^:]*):([^:]*):type=misc:id=(.*)$#',$page_link,$matches);
		return array($matches[3],'downloads');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_code('downloads');
		require_code('feedback');
		require_lang('downloads');
		require_css('downloads');

		$type=get_param('type','misc');

		// Decide what to do
		if ($type=='tree_view') return $this->tree_view_screen();
		if ($type=='entry') return $this->dloadinfo_screen();
		if ($type=='misc') return $this->category_screen();
		if ($type=='index') return $this->show_all_downloads();

		return new ocp_tempcode();
	}

	/**
	 * The UI to view a download category.
	 *
	 * @return tempcode		The UI
	 */
	function category_screen()
	{
		$id=get_param_integer('id',db_get_first_id());
		$GLOBALS['FEED_URL']=find_script('backend').'?mode=downloads&filter='.strval($id);

		$root=get_param_integer('root',db_get_first_id(),true);

		$rows=$GLOBALS['SITE_DB']->query_select('download_categories',array('*'),array('id'=>$id),'',1);
		if (!array_key_exists(0,$rows))
		{
			return warn_screen(get_page_title('DOWNLOAD_CATEGORY'),do_lang_tempcode('MISSING_RESOURCE'));
		}
		$category=$rows[0];

		$description=get_translated_tempcode($category['description']);

		if (!has_category_access(get_member(),'downloads',strval($id))) access_denied('CATEGORY_ACCESS');

		$cat_order=get_param('cat_order','t.text_original ASC');
		$order=get_param('order',NULL);

		$subcategories=get_download_sub_categories($id,$root,get_zone_name(),$cat_order);
		$downloads=get_category_downloads($id,$root,$order);

		$subdownloads=new ocp_tempcode();
		require_code('ocfiltering');
		$filter_where=ocfilter_to_sqlfragment(strval($id).'*','id','download_categories','parent_id','category_id','id');
		$all_rows=$GLOBALS['SITE_DB']->query('SELECT d.*,text_original FROM '.get_table_prefix().'download_downloads d LEFT JOIN '.get_table_prefix().'translate t ON '.db_string_equal_to('language',user_lang()).' AND d.name=t.id WHERE '.$filter_where,20);
		shuffle($all_rows);
		require_code('images');
		foreach ($all_rows as $d_row)
		{
			if ($GLOBALS['RECORD_LANG_STRINGS_CONTENT'] || is_null($d_row['text_original'])) $d_row['text_original']=get_translated_text($d_row['description']);
			$d_url=build_url(array('page'=>'_SELF','type'=>'entry','id'=>$d_row['id']),'_SELF');
			if (addon_installed('galleries'))
			{
				$i_rows=$GLOBALS['SITE_DB']->query_select('images',array('url','thumb_url','id'),array('cat'=>'download_'.strval($d_row['id'])),'',1,$d_row['default_pic']-1);
				if (array_key_exists(0,$i_rows))
				{
					$thumb_url=ensure_thumbnail($i_rows[0]['url'],$i_rows[0]['thumb_url'],'galleries','images',$i_rows[0]['id']);
					$subdownloads->attach(hyperlink($d_url,do_image_thumb($thumb_url,get_download_html($d_row,false))));
				}
			}
		}

		$title_to_use=get_translated_text($category['category']);

		if (addon_installed('awards'))
		{
			require_code('awards');
			$awards=find_awards_for('download_category',strval($id));
		} else $awards=array();
		$title=get_page_title('_DOWNLOAD_CATEGORY',true,array($title_to_use),NULL,$awards);

		$tree=download_breadcrumbs($id,$root,true,get_zone_name());
		if (!$tree->is_empty())
		{
			$tree->attach(do_template('BREADCRUMB_ESCAPED'));
		}
		if (has_specific_permission(get_member(),'open_virtual_roots'))
		{
			$url=get_self_url(false,false,array('root'=>$id));
			$tree->attach(hyperlink($url,escape_html($title_to_use),false,false,do_lang_tempcode('VIRTUAL_ROOT')));
		} else $tree->attach($title_to_use);

		seo_meta_load_for('downloads_category',strval($id),$title_to_use);

		// Sorting
		$_selectors=array(
			't.text_original ASC'=>'ALPHABETICAL_FORWARD',
			't.text_original DESC'=>'ALPHABETICAL_BACKWARD',
			'file_size ASC'=>'SMALLEST_FIRST',
			'file_size DESC'=>'LARGEST_FIRST',
			'num_downloads DESC'=>'POPULARITY',
			'add_date ASC'=>'OLDEST_FIRST',
			'add_date DESC'=>'NEWEST_FIRST'
		);
		$selectors=new ocp_tempcode();
		foreach ($_selectors as $selector_value=>$selector_name)
		{
			$selected=($order==$selector_value);
			$selectors->attach(do_template('RESULTS_BROWSER_SORTER',array('_GUID'=>'af660c0ebf014bb296d576b2854aa911','SELECTED'=>$selected,'NAME'=>do_lang_tempcode($selector_name),'VALUE'=>$selector_value)));
		}
		$sort_url=get_self_url(false,false,array('order'=>NULL),false,true);
		$sorting=do_template('RESULTS_BROWSER_SORT',array('_GUID'=>'f4112dcd72d1dd04afbe7277a3871399','SORT'=>'order','RAND'=>uniqid(''),'URL'=>$sort_url,'SELECTORS'=>$selectors));

		if (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',strval($id)),'submit_midrange_content'))
		{
			$submit_url=build_url(array('page'=>'cms_downloads','type'=>'ad','cat'=>$id),get_module_zone('cms_downloads'));
		} else $submit_url=new ocp_tempcode();
		if (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',strval($id)),'submit_cat_midrange_content'))
		{
			$add_cat_url=build_url(array('page'=>'cms_downloads','type'=>'ac','parent_id'=>$id),get_module_zone('cms_downloads'));
		} else $add_cat_url=new ocp_tempcode();
		if (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',strval($id)),'edit_cat_midrange_content'))
		{
			$edit_cat_url=build_url(array('page'=>'cms_downloads','type'=>'_ec','id'=>$id),get_module_zone('cms_downloads'));
		} else $edit_cat_url=new ocp_tempcode();

		$GLOBALS['META_DATA']+=array(
			'created'=>date('Y-m-d',$category['add_date']),
			'creator'=>'',
			'publisher'=>'', // blank means same as creator
			'modified'=>'',
			'type'=>'Download category',
			'title'=>$title_to_use,
			'identifier'=>'_SEARCH:downloads:misc:'.strval($id),
			'description'=>get_translated_text($category['description']),
		);

		$rep_image=$category['rep_image'];
		if ($rep_image!='')
		{
			$GLOBALS['META_DATA']+=array(
				'image'=>(url_is_local($rep_image)?(get_custom_base_url().'/'):'').$rep_image,
			);
		}

		breadcrumb_add_segment($tree);
		return do_template('DOWNLOAD_CATEGORY_SCREEN',array('_GUID'=>'ebb3c8708695f6a30dbd4a03f8632047','ID'=>strval($id),'SUBDOWNLOADS'=>$subdownloads,'TAGS'=>get_loaded_tags('download_categories'),'TITLE'=>$title,'SUBMIT_URL'=>$submit_url,'ADD_CAT_URL'=>$add_cat_url,'EDIT_CAT_URL'=>$edit_cat_url,'DESCRIPTION'=>$description,'SUBCATEGORIES'=>$subcategories,'DOWNLOADS'=>$downloads,'SORTING'=>$sorting));
	}

	/**
	 * The UI to view a download index.
	 *
	 * @return tempcode		The UI
	 */
	function show_all_downloads()
	{
		$title=get_page_title('SECTION_DOWNLOADS');

		$id=get_param('id',strval(db_get_first_id()));

		require_code('ocfiltering');
		$sql_filter=ocfilter_to_sqlfragment(is_numeric($id)?($id.'*'):$id,'p.category_id','download_categories','parent_id','p.category_id','id'); // Note that the parameters are fiddled here so that category-set and record-set are the same, yet SQL is returned to deal in an entirely different record-set (entries' record-set)

		if ($GLOBALS['SITE_DB']->query_value('download_downloads','COUNT(*)')>1000)
			warn_exit(do_lang_tempcode('TOO_MANY_TO_CHOOSE_FROM'));

		$cats=array();
		$rows=$GLOBALS['SITE_DB']->query('SELECT p.*,text_original FROM '.get_table_prefix().'download_downloads p LEFT JOIN '.get_table_prefix().'translate t ON t.id=p.name AND '.db_string_equal_to('language',user_lang()).' WHERE validated=1 AND ('.$sql_filter.') ORDER BY text_original ASC');
		foreach ($rows as $row)
		{
			if ($GLOBALS['RECORD_LANG_STRINGS_CONTENT'] || is_null($row['text_original'])) $row['text_original']=get_translated_text($row['name']);
			$letter=strtoupper(substr($row['text_original'],0,1));

			if (!has_category_access(get_member(),'downloads',strval($row['category_id']))) continue;

			if (!array_key_exists($letter,$cats)) $cats[$letter]=array();
			$cats[$letter][]=$row;
		}
		unset($rows);

		$subcats=array();

		foreach ($cats as $letter=>$rows)
		{
			if (!is_string($letter)) $letter=strval($letter); // Numbers come out as numbers not strings, even if they went in as strings- darned PHP

			$has_download=false;

			$data=array();
			$data['CAT_TITLE'] = $letter;
			$data['LETTER'] = $letter;

			$out=new ocp_tempcode();

			foreach ($rows as $myrow)
			{
				$out->attach(get_download_html($myrow));
				$has_download=true;
			}

			$data['DOWNLOADS']=$out;

			$subcats[]=$data;
		}

		if ((is_numeric($id)) && (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',$id),'submit_midrange_content')))
		{
			$submit_url=build_url(array('page'=>'cms_downloads','type'=>'ad','cat'=>$id),get_module_zone('cms_downloads'));
		} else $submit_url=new ocp_tempcode();
		if ((is_numeric($id)) && (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',$id),'submit_cat_midrange_content')))
		{
			$add_cat_url=build_url(array('page'=>'cms_downloads','type'=>'ac','parent_id'=>$id),get_module_zone('cms_downloads'));
		} else $add_cat_url=new ocp_tempcode();
		if ((is_numeric($id)) && (has_actual_page_access(NULL,'cms_downloads',NULL,array('downloads',$id),'edit_cat_midrange_content')))
		{
			$edit_cat_url=build_url(array('page'=>'cms_downloads','type'=>'_ec','id'=>$id),get_module_zone('cms_downloads'));
		} else $edit_cat_url=new ocp_tempcode();

		return do_template('DOWNLOAD_ALL_SCREEN',array('TITLE'=>$title,'SUBMIT_URL'=>$submit_url,'ADD_CAT_URL'=>$add_cat_url,'EDIT_CAT_URL'=>$edit_cat_url,'SUB_CATEGORIES'=>$subcats));
	}


	/**
	 * The UI to view a download.
	 *
	 * @return tempcode		The UI
	 */
	function dloadinfo_screen()
	{
		$id=get_param_integer('id');

		$root=get_param_integer('root',db_get_first_id(),true);

		// Basic Init
		$rows=$GLOBALS['SITE_DB']->query_select('download_downloads',array('*'),array('id'=>$id),'',1);
		if (!array_key_exists(0,$rows))
		{
			return warn_screen(get_page_title('SECTION_DOWNLOADS'),do_lang_tempcode('MISSING_RESOURCE'));
		}
		$myrow=$rows[0];
		$GLOBALS['FEED_URL']=find_script('backend').'?mode=downloads&filter='.strval($myrow['category_id']);

		if (!has_category_access(get_member(),'downloads',strval($myrow['category_id']))) access_denied('CATEGORY_ACCESS');

		$name=get_translated_text($myrow['name']);

		list($rating_details,$comment_details,$trackback_details)=embed_feedback_systems(
			get_page_name(),
			strval($id),
			$myrow['allow_rating'],
			$myrow['allow_comments'],
			$myrow['allow_trackbacks'],
			$myrow['validated'],
			$myrow['submitter'],
			build_url(array('page'=>'_SELF','type'=>'entry','id'=>$id),'_SELF',NULL,false,false,true),
			$name,
			get_value('comment_forum__downloads')
		);

		// Views
		if (get_db_type()!='xml')
		{
			$myrow['download_views']++;
			$GLOBALS['SITE_DB']->query_update('download_downloads',array('download_views'=>$myrow['download_views']),array('id'=>$id),'',1,NULL,false,true);
		}

		// Tree
		$tree=download_breadcrumbs($myrow['category_id'],$root,false,get_zone_name());

		$title_to_use=do_lang_tempcode('DOWNLOAD_TITLE',escape_html($name));
		$title_to_use_2=do_lang('DOWNLOAD_TITLE',$name);
		if (addon_installed('awards'))
		{
			require_code('awards');
			$awards=find_awards_for('download',strval($id));
		} else $awards=array();
		$title=get_page_title($title_to_use,false,NULL,NULL,$awards);

		seo_meta_load_for('downloads_download',strval($id),$title_to_use_2);

		$warning_details=new ocp_tempcode();

		// Validation
		if ($myrow['validated']==0)
		{
			if (!has_specific_permission(get_member(),'jump_to_unvalidated'))
				access_denied('SPECIFIC_PERMISSION','jump_to_unvalidated');

			$warning_details->attach(do_template('WARNING_TABLE',array('_GUID'=>'5b1781b8fbb1ef9b8f47693afcff02b9','WARNING'=>do_lang_tempcode((get_param_integer('redirected',0)==1)?'UNVALIDATED_TEXT_NON_DIRECT':'UNVALIDATED_TEXT'))));
		}

		// Cost warning
		if (($myrow['download_cost']!=0) && (addon_installed('points')))
		{
			require_lang('points');
			$warning_details->attach(do_template('WARNING_TABLE',array('_GUID'=>'05fc448bf79b373385723c5af5ec93af','WARNING'=>do_lang_tempcode('WILL_COST',integer_format($myrow['download_cost'])))));
		}

		// Admin functions
		$edit_url=new ocp_tempcode();
		$add_img_url=new ocp_tempcode();
		if ((has_actual_page_access(NULL,'cms_downloads',NULL,NULL)) && (has_edit_permission('mid',get_member(),$myrow['submitter'],'cms_downloads',array('downloads',$myrow['category_id']))))
		{
			$edit_url=build_url(array('page'=>'cms_downloads','type'=>'_ed','id'=>$id),get_module_zone('cms_downloads'));
		}
		if (addon_installed('galleries'))
		{
			if ((has_actual_page_access(NULL,'cms_galleries',NULL,NULL)) && (has_edit_permission('mid',get_member(),$myrow['submitter'],'cms_galleries',array('galleries','download_'.strval($id)))))
			{
				require_lang('galleries');
				$add_img_url=build_url(array('page'=>'cms_galleries','type'=>'ad','cat'=>'download_'.strval($id)),get_module_zone('cms_galleries'));
			}
		}

		// Outmoding
		if (!is_null($myrow['out_mode_id']))
		{
			$outmode_url=build_url(array('page'=>'_SELF','type'=>'entry','id'=>$myrow['out_mode_id'],'root'=>($root==db_get_first_id())?NULL:$root),'_SELF');
		} else $outmode_url=new ocp_tempcode();

		// Stats
		$add_date=get_timezoned_date($myrow['add_date'],false);

		// Additional information
		$additional_details=get_translated_tempcode($myrow['comments']);

		// Edit date
		if (!is_null($myrow['edit_date']))
		{
			$edit_date=make_string_tempcode(get_timezoned_date($myrow['edit_date'],false));
		} else $edit_date=new ocp_tempcode();

		$images_details=new ocp_tempcode();
		$image_url='';
		$counter=0;
		if (addon_installed('galleries'))
		{
			// Images
			require_lang('galleries');
			$cat='download_'.strval($id);
			$map=array('cat'=>$cat);
			if (!has_specific_permission(get_member(),'see_unvalidated')) $map['validated']=1;
			$rows=$GLOBALS['SITE_DB']->query_select('images',array('*'),$map,'ORDER BY id',200/*Stop sillyness, could be a DOS attack*/);
			$div=2;
			$_out=new ocp_tempcode();
			$_row=new ocp_tempcode();
			require_code('images');
			while (array_key_exists($counter,$rows))
			{
				$row=$rows[$counter];

		//		$view_url=build_url(array('page'=>'galleries','type'=>'image','wide'=>1,'id'=>$row['id']),get_module_zone('galleries'));
				$view_url=$row['url'];
				if ($image_url=='') $image_url=$row['url'];
				if (url_is_local($view_url)) $view_url=get_custom_base_url().'/'.$view_url;
				$thumb_url=ensure_thumbnail($row['url'],$row['thumb_url'],'galleries','images',$row['id']);
				$comment=get_translated_tempcode($row['comments']);
				$thumb=do_image_thumb($thumb_url,'');
				if ((has_actual_page_access(NULL,'cms_galleries',NULL,NULL)) && (has_edit_permission('mid',get_member(),$row['submitter'],'cms_galleries',array('galleries','download_'.strval($id)))))
				{
					$iedit_url=build_url(array('page'=>'cms_galleries','type'=>'_ed','id'=>$row['id']),get_module_zone('cms_galleries'));
				} else $iedit_url=new ocp_tempcode();
				$_content=do_template('DOWNLOAD_SCREEN_IMAGE',array('_GUID'=>'fba0e309aa0ae04891e32c65a625b177','ID'=>strval($row['id']),'VIEW_URL'=>$view_url,'EDIT_URL'=>$iedit_url,'THUMB'=>$thumb,'COMMENT'=>$comment));

				$_row->attach(do_template('DOWNLOAD_GALLERY_IMAGE_CELL',array('_GUID'=>'8400a832dbed64bb63f264eb3a038895','CONTENT'=>$_content)));

				if (($counter%$div==1) && ($counter!=0))
				{
					$_out->attach(do_template('DOWNLOAD_GALLERY_ROW',array('_GUID'=>'205c4f5387e98c534d5be1bdfcccdd7d','CELLS'=>$_row)));
					$_row=new ocp_tempcode();
				}

				$counter++;
			}
			if (!$_row->is_empty())
				$_out->attach(do_template('DOWNLOAD_GALLERY_ROW',array('_GUID'=>'e9667ca2545ac72f85a873f236cbbd6f','CELLS'=>$_row)));
			$images_details=$_out;
		}

		// Download link
		$author=$myrow['author'];
		$author_url=addon_installed('authors')?build_url(array('page'=>'authors','type'=>'misc','id'=>$author),get_module_zone('authors')):new ocp_tempcode();

		// Licence
		$licence_title=NULL;
		$licence_url=NULL;
		$licence_hyperlink=NULL;
		$licence=$myrow['download_licence'];
		if (!is_null($licence))
		{
			$licence_title=$GLOBALS['SITE_DB']->query_value_null_ok('download_licences','l_title',array('id'=>$licence));
			if (!is_null($licence_title))
			{
				$keep=symbol_tempcode('KEEP');
				$licence_url=find_script('download_licence').'?id='.strval($licence).$keep->evaluate();
				$licence_hyperlink=do_template('HYPERLINK_POPUP_WINDOW',array('_GUID'=>'10582f28c37ee7e9e462fdbd6a2cb8dd','TITLE'=>'','CAPTION'=>$licence_title,'URL'=>$licence_url,'WIDTH'=>'600','HEIGHT'=>'500','REL'=>'license'));
			} else
			{
				$licence=NULL; // Orphaned
			}
		}

		breadcrumb_add_segment($tree,$title_to_use);

		$GLOBALS['META_DATA']+=array(
			'created'=>date('Y-m-d',$myrow['add_date']),
			'creator'=>$myrow['author'],
			'publisher'=>$GLOBALS['FORUM_DRIVER']->get_username($myrow['submitter']),
			'modified'=>is_null($myrow['edit_date'])?'':date('Y-m-d',$myrow['edit_date']),
			'type'=>'Download',
			'title'=>get_translated_text($myrow['name']),
			'identifier'=>'_SEARCH:downloads:view:'.strval($id),
			'description'=>get_translated_text($myrow['description']),
			'image'=>$image_url,
		);

		return do_template('DOWNLOAD_SCREEN',array('_GUID'=>'a9af438f84783d0d38c20b5f9a62dbdb','ORIGINAL_FILENAME'=>$myrow['original_filename'],'URL'=>$myrow['url'],'NUM_IMAGES'=>strval($counter),'TAGS'=>get_loaded_tags('downloads'),'LICENCE'=>is_null($licence)?NULL:strval($licence),'LICENCE_TITLE'=>$licence_title,'LICENCE_HYPERLINK'=>$licence_hyperlink,'SUBMITTER'=>strval($myrow['submitter']),'EDIT_DATE'=>$edit_date,'EDIT_DATE_RAW'=>is_null($myrow['edit_date'])?'':strval($myrow['edit_date']),'VIEWS'=>integer_format($myrow['download_views']),'NAME'=>$name,'DATE'=>$add_date,'DATE_RAW'=>strval($myrow['add_date']),'NUM_DOWNLOADS'=>integer_format($myrow['num_downloads']),'TITLE'=>$title,'OUTMODE_URL'=>$outmode_url,'WARNING_DETAILS'=>$warning_details,'EDIT_URL'=>$edit_url,'ADD_IMG_URL'=>$add_img_url,'DESCRIPTION'=>get_translated_tempcode($myrow['description']),'ADDITIONAL_DETAILS'=>$additional_details,'IMAGES_DETAILS'=>$images_details,'ID'=>strval($id),'FILE_SIZE'=>clean_file_size($myrow['file_size']),'AUTHOR_URL'=>$author_url,'AUTHOR'=>$author,'TRACKBACK_DETAILS'=>$trackback_details,'RATING_DETAILS'=>$rating_details,'COMMENTS_DETAILS'=>$comment_details));
	}

	/**
	 * The UI to view a download category tree.
	 *
	 * @return tempcode		The UI
	 */
	function tree_view_screen()
	{
		$GLOBALS['FEED_URL']=find_script('backend').'?mode=downloads&filter=';

		require_code('splurgh');

		if ($GLOBALS['SITE_DB']->query_value('download_categories','COUNT(*)')>1000)
			warn_exit(do_lang_tempcode('TOO_MANY_TO_CHOOSE_FROM'));

		$url_stub=build_url(array('page'=>'_SELF','type'=>'misc'),'_SELF',NULL,false,false,true);
		$last_change_time=$GLOBALS['SITE_DB']->query_value_null_ok('download_categories','MAX(add_date)');

		$category_rows=$GLOBALS['SITE_DB']->query_select('download_categories',array('id','category','parent_id'));
		$map=array();
		foreach ($category_rows as $category)
		{
			if ($category['category']!=db_get_first_id())
			{
				if (!has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(),'downloads',strval($category['id']))) continue;
			}

			$id=$category['id'];

			$map[$id]['title']=get_translated_text($category['category']);
			$children=array();
			foreach ($category_rows as $child)
			{
				if ($child['parent_id']==$id) $children[]=$child['id'];
			}
			$map[$id]['children']=$children;
		}

		$content=splurgh_master_build('id',$map,$url_stub->evaluate(),'download_tree_made',$last_change_time);

		$title=get_page_title('DOWNLOADS_TREE');
		return do_template('SPLURGH_SCREEN',array('_GUID'=>'4efab542cfa3d48a3b23d60b04798a37','TITLE'=>$title,'CONTENT'=>$content));
	}

}


