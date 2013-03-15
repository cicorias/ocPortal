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
 * @package		tester
 */

/*
Tips...

Query to find test sections not yet started:
SELECT s.* FROM `ocp4_test_sections` s WHERE NOT EXISTS(SELECT * FROM `ocp4_tests` WHERE t_section=s.id AND t_status<>0)
AND s_assigned_to<>4 AND s_assigned_to<>89
ORDER BY s_assigned_to

UPDATE ocp4_test_sections SET s_assigned_to=4 WHERE id IN (2,18,19,20,21,22,34,35,36,72,73,77,78,79,80,81,82,83,84,85,86,89,90,91,92,93,94,95,96,97,98,99,100,101,103)
*/

/**
 * Module page class.
 */
class Module_tester
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
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_if_exists('test_sections');
		$GLOBALS['SITE_DB']->drop_if_exists('tests');

		delete_specific_permission('perform_tests');
		delete_specific_permission('add_tests');
		delete_specific_permission('edit_own_tests');

		delete_config_option('tester_forum_name');
		delete_config_option('bug_report_text');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		$GLOBALS['SITE_DB']->create_table('test_sections',array(
			'id'=>'*AUTO',
			's_section'=>'SHORT_TEXT',
			's_notes'=>'LONG_TEXT',
			's_inheritable'=>'BINARY',
			's_assigned_to'=>'?USER' // NULL: no assignee, as it's meant to be inherited
		));

		$GLOBALS['SITE_DB']->create_table('tests',array(
			'id'=>'*AUTO',
			't_section'=>'AUTO_LINK',
			't_test'=>'LONG_TEXT',
			't_assigned_to'=>'?USER', // NULL: section assignee
			't_enabled'=>'BINARY',
			't_status'=>'INTEGER', // 0=not done, 1=success, 2=failure
			't_inherit_section'=>'?AUTO_LINK' // NULL: none
		));

		add_specific_permission('TESTER','perform_tests',false);
		add_specific_permission('TESTER','add_tests',true);
		add_specific_permission('TESTER','edit_own_tests',true);

		add_config_option('TESTER_FORUM_NAME','tester_forum_name','forum','return do_lang(\'DEFAULT_TESTER_FORUM\');','FEATURE','TESTER');
		add_config_option('BUG_REPORT_TEXT','bug_report_text','text','return do_lang(\'DEFAULT_BUG_REPORT_TEMPLATE\');','FEATURE','TESTER');
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @return ?array	A map of entry points (type-code=>language-code) (NULL: disabled).
	 */
	function get_entry_points()
	{
		return array('add_test'=>'ADD_TEST','ad'=>'ADD_TEST_SECTION','ed'=>'EDIT_TEST_SECTION','go'=>'RUN_THROUGH_TESTS','stats'=>'TEST_STATISTICS');
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		require_lang('tester');
		require_css('tester');
		check_specific_permission('perform_tests');

		// Decide what we're doing
		$type=get_param('type','go');

		if ($type=='stats') return $this->stats();
		if ($type=='go') return $this->go();
		if ($type=='_go') return $this->_go();
		if ($type=='report') return $this->report();
		if ($type=='add_test') return $this->add_test();
		if ($type=='_add_test') return $this->_add_test();
		if ($type=='ad') return $this->ad();
		if ($type=='_ad') return $this->_ad();
		if ($type=='ed') return $this->ed();
		if ($type=='_ed') return $this->_ed();
		if ($type=='__ed') return $this->__ed();

		return new ocp_tempcode();
	}

	/**
	 * Show statistics on test progress.
	 *
	 * @return tempcode	The result of execution.
	 */
	function stats()
	{
		$title=get_page_title('TEST_STATISTICS');

		$num_tests_successful=$GLOBALS['SITE_DB']->query_value('tests','COUNT(*)',array('t_status'=>1,'t_enabled'=>1));
		$num_tests_failed=$GLOBALS['SITE_DB']->query_value('tests','COUNT(*)',array('t_status'=>2,'t_enabled'=>1));
		$num_tests_incomplete=$GLOBALS['SITE_DB']->query_value('tests','COUNT(*)',array('t_status'=>0,'t_enabled'=>1));
		$num_tests=$num_tests_successful+$num_tests_failed+$num_tests_incomplete;

		$testers=new ocp_tempcode();
		$_testers1=collapse_1d_complexity('s_assigned_to',$GLOBALS['SITE_DB']->query_select('test_sections',array('DISTINCT s_assigned_to')));
		$_testers2=collapse_1d_complexity('t_assigned_to',$GLOBALS['SITE_DB']->query_select('tests',array('DISTINCT t_assigned_to')));
		$_testers=array_unique(array_merge($_testers1,$_testers2));
		foreach ($_testers as $tester)
		{
			$t_username=$GLOBALS['FORUM_DRIVER']->get_username($tester);
			if (is_null($t_username)) continue;

			$num_tests_successful=$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'tests t LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'test_sections s ON t.t_section=s.id WHERE t.t_enabled=1 AND t.t_status=1 AND (t.t_assigned_to='.strval((integer)$tester).' OR (t.t_assigned_to IS NULL AND s.s_assigned_to='.strval((integer)$tester).'))');
			$num_tests_failed=$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'tests t LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'test_sections s ON t.t_section=s.id WHERE t.t_enabled=1 AND t.t_status=2 AND (t.t_assigned_to='.strval((integer)$tester).' OR (t.t_assigned_to IS NULL AND s.s_assigned_to='.strval((integer)$tester).'))');
			$num_tests_incomplete=$GLOBALS['SITE_DB']->query_value_null_ok_full('SELECT COUNT(*) FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'tests t LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'test_sections s ON t.t_section=s.id WHERE t.t_enabled=1 AND t.t_status=0 AND (t.t_assigned_to='.strval((integer)$tester).' OR (t.t_assigned_to IS NULL AND s.s_assigned_to='.strval((integer)$tester).'))');
			$num_tests=$num_tests_successful+$num_tests_failed+$num_tests_incomplete;

			$t=do_template('TESTER_STATISTICS_MEMBER',array('_GUID'=>'80778fd574859f966686212566ba67bc','TESTER'=>$t_username,'NUM_TESTS'=>integer_format($num_tests),'NUM_TESTS_SUCCESSFUL'=>integer_format($num_tests_successful),'NUM_TESTS_FAILED'=>integer_format($num_tests_failed),'NUM_TESTS_INCOMPLETE'=>integer_format($num_tests_incomplete)));
			$testers->attach($t);
		}

		return do_template('TESTER_STATISTICS_SCREEN',array('_GUID'=>'3f4bcbccbdc2e60ad7324cb28ed942b5','TITLE'=>$title,'TESTERS'=>$testers,'NUM_TESTS'=>integer_format($num_tests),'NUM_TESTS_SUCCESSFUL'=>integer_format($num_tests_successful),'NUM_TESTS_FAILED'=>integer_format($num_tests_failed),'NUM_TESTS_INCOMPLETE'=>integer_format($num_tests_incomplete)));
	}

	/**
	 * Run through tests.
	 *
	 * @return tempcode	The result of execution.
	 */
	function go()
	{
		require_code('comcode_renderer');

		$title=get_page_title('RUN_THROUGH_TESTS');

		$show_for_all=get_param_integer('show_for_all',0);
		$show_successful=get_param_integer('show_successful',0);

		$tester=get_member();
		if ($GLOBALS['FORUM_DRIVER']->is_super_admin(get_member())) $tester=get_param_integer('tester',get_member());
		if ($show_for_all==0)
			$where='(t.t_assigned_to='.strval((integer)$tester).' OR (t.t_assigned_to IS NULL AND s.s_assigned_to='.strval((integer)$tester).'))';
		else $where='s.id IS NOT NULL';
		if ($show_successful==0) $where.=' AND t.t_status<>1';
		$where.=' AND s.s_inheritable=0';

		$sections=new ocp_tempcode();
		$query='SELECT *,t.id AS id FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'tests t LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'test_sections s ON t.t_section=s.id WHERE '.$where.' ORDER BY s.s_section,t.id';
		$_tests=$GLOBALS['SITE_DB']->query($query);
		$current=NULL;
		$current_2=NULL;
		$current_3=NULL;
		$tests=new ocp_tempcode();
		foreach ($_tests as $test)
		{
			if ((!is_null($current)) && ($current!=$test['t_section']))
			{
				$edit_test_section_url=new ocp_tempcode();
				if ((has_specific_permission(get_member(),'edit_own_tests')) && (($test['s_assigned_to']==get_member()) || ($GLOBALS['FORUM_DRIVER']->is_staff(get_member()))))
				{
					$edit_test_section_url=build_url(array('page'=>'_SELF','type'=>'_ed','id'=>$current),'_SELF');
				}

				$sections->attach(do_template('TESTER_GO_SECTION',array('_GUID'=>'5ac788f72b881e403f75f76815706032','ID'=>strval($current),'EDIT_TEST_SECTION_URL'=>$edit_test_section_url,'NOTES'=>$current_3,'SECTION'=>$current_2,'TESTS'=>$tests)));
				$tests=new ocp_tempcode();
			}
			$current=$test['t_section'];
			$current_2=$test['s_section'];
			$current_3=$test['s_notes'];

			$a_test=make_string_tempcode(escape_html($test['t_test']));
			if (!is_null($test['t_inherit_section']))
			{
				$_tests_2=$GLOBALS['SITE_DB']->query_select('tests',array('*'),array('t_section'=>$test['t_inherit_section']));
				if (count($_tests_2)!=0)
				{
					$section_notes=$GLOBALS['SITE_DB']->query_value('test_sections','s_notes',array('id'=>$test['t_inherit_section']));
					if ($section_notes!='') $a_test->attach(paragraph(escape_html($section_notes)));

					$a_test->attach(do_template('TESTER_TEST_SET',array('TESTS'=>map_keys_to_upper($_tests_2))));
				}
			}

			$bug_report_url=build_url(array('page'=>'_SELF','type'=>'report','id'=>$test['id']),'_SELF');
			$tests->attach(do_template('TESTER_GO_TEST',array('_GUID'=>'1e719a51201d27eff7aed58b7f730251','BUG_REPORT_URL'=>$bug_report_url,'TEST'=>$a_test,'ID'=>strval($test['id']),'VALUE'=>strval($test['t_status']))));
		}
		if (($tests->is_empty()) && ($sections->is_empty()))
		{
			$sections=paragraph(do_lang_tempcode('NO_ENTRIES'),'4tregerg344');
		} else
		{
			$edit_test_section_url=new ocp_tempcode();
			if ((has_specific_permission(get_member(),'edit_own_tests')) && (($test['s_assigned_to']==get_member()) || ($GLOBALS['FORUM_DRIVER']->is_staff(get_member()))))
			{
				$edit_test_section_url=build_url(array('page'=>'_SELF','type'=>'_ed','id'=>$test['t_section']),'_SELF');
			}

			$sections->attach(do_template('TESTER_GO_SECTION',array('_GUID'=>'9bd53d8b0f0aab1a683660fac2b6ad85','ID'=>strval($test['t_section']),'EDIT_TEST_SECTION_URL'=>$edit_test_section_url,'NOTES'=>$test['s_notes'],'SECTION'=>$test['s_section'],'TESTS'=>$tests)));
		}

		$add_test_section_url=new ocp_tempcode();
		if (has_specific_permission(get_member(),'add_tests'))
		{
			$add_test_section_url=build_url(array('page'=>'_SELF','type'=>'ad'),'_SELF');
		}

		$post_url=build_url(array('page'=>'_SELF','type'=>'_go'),'_SELF');

		return do_template('TESTER_GO_SCREEN',array('_GUID'=>'22b3b626cb510e64a795d95acc0ad8a2','ADD_TEST_SECTION_URL'=>$add_test_section_url,'SHOW_SUCCESSFUL'=>strval($show_successful),'SHOW_FOR_ALL'=>strval($show_for_all),'TITLE'=>$title,'SECTIONS'=>$sections,'URL'=>$post_url));
	}

	/**
	 * A bug report for a test.
	 *
	 * @return tempcode	The result of execution.
	 */
	function report()
	{
		$id=get_param_integer('id');
		$test_row=$GLOBALS['SITE_DB']->query_select('tests t LEFT JOIN '.$GLOBALS['SITE_DB']->get_table_prefix().'test_sections s ON t.t_section=s.id',array('*'),array('t.id'=>$id),'',1);
		if (!array_key_exists(0,$test_row))
		{
			warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
		}

		$section=$test_row[0]['s_section'];
		$test=$test_row[0]['t_test'];

		$self_title=$section.'/'.substr($test,0,20);

		$title=get_page_title('BUG_REPORT_FOR',true,array(escape_html($self_title)));

		require_code('feedback');

		$self_url=get_self_url();
		$forum=get_option('tester_forum_name');
		actualise_post_comment(true,'bug_report',strval($id),$self_url,$self_title,$forum);

		$comment_text=str_replace('{1}',$test,get_option('bug_report_text'));
		$comments=get_comments('bug_report',true,strval($id),false,$forum,$comment_text);

		return do_template('TESTER_REPORT',array('_GUID'=>'0c223a0a29a2c5289d71fbb69b0fe40d','TITLE'=>$title,'TEST'=>$test,'COMMENTS'=>$comments));
	}

	/**
	 * Save test run through results.
	 *
	 * @return tempcode	The result of execution.
	 */
	function _go()
	{
		$title=get_page_title('RUN_THROUGH_TESTS');

		foreach ($_POST as $key=>$val)
		{
			if ((substr($key,0,5)=='test_') && (is_numeric(substr($key,5))) && (is_numeric($val)))
			{
				$id=intval(substr($key,5));
				$GLOBALS['SITE_DB']->query_update('tests',array('t_status'=>intval($val)),array('id'=>$id),'',1);
			}
		}

		// Show it worked / Refresh
		$show_for_all=post_param_integer('show_for_all',0);
		$show_successful=post_param_integer('show_successful',0);
		$url=build_url(array('page'=>'_SELF','type'=>'go','show_for_all'=>$show_for_all,'show_successful'=>$show_successful),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Get a list to choose a section.
	 *
	 * @param  ?AUTO_LINK	The section to select by default (NULL: no specific default)
	 * @param  boolean		Whether to only select inheritable sections
	 * @return tempcode		The list
	 */
	function get_section_list($it=NULL,$unassigned=false)
	{
		$list2=new ocp_tempcode();

		$where=NULL;
		if ($unassigned) $where=array('s_inheritable'=>1);
		$sections=$GLOBALS['SITE_DB']->query_select('test_sections',array('*'),$where,'ORDER BY s_assigned_to,s_section');
		foreach ($sections as $_section)
		{
			$section=$_section['s_section'];
			$id=$_section['id'];
			$count=$GLOBALS['SITE_DB']->query_value('tests','COUNT(*)',array('t_section'=>$id));
			$extra=new ocp_tempcode();
			if (!$unassigned)
			{
				$username=$GLOBALS['FORUM_DRIVER']->get_username($_section['s_assigned_to']);
				if (is_null($username)) $username=do_lang('UNKNOWN');
				$extra=(is_null($_section['s_assigned_to']))?do_lang_tempcode('UNASSIGNED'):make_string_tempcode($username);
			}
			$list2->attach(form_input_list_entry(strval($id),$it==$id,do_lang_tempcode('TEST_SECTION_ASSIGNMENT',make_string_tempcode(escape_html($section)),make_string_tempcode(integer_format($count)),$extra)));
		}

		return $list2;
	}

	/**
	 * Get a list to choose a tester.
	 *
	 * @param  ?MEMBER		The member to select by default (NULL: Select N/A)
	 * @return tempcode		The list
	 */
	function get_tester_list($it)
	{
		$tester_groups=collapse_1d_complexity('group_id',$GLOBALS['SITE_DB']->query_select('gsp',array('group_id'),array('specific_permission'=>'perform_tests')));
		$admin_groups=$GLOBALS['FORUM_DRIVER']->get_super_admin_groups();
		$moderator_groups=$GLOBALS['FORUM_DRIVER']->get_moderator_groups();

		$groups=array_unique(array_merge($tester_groups,$admin_groups,$moderator_groups));

		$members=$GLOBALS['FORUM_DRIVER']->member_group_query($groups,2000);

		$list=form_input_list_entry('-1',is_null($it),do_lang_tempcode('NA_EM'));
		foreach ($members as $member=>$details)
		{
			$username=$GLOBALS['FORUM_DRIVER']->pname_name($details);
			$list->attach(form_input_list_entry(strval($member),$member==$it,$username));
		}

		return $list;
	}

	/**
	 * Get tempcode for a test adding/editing form.
	 *
	 * @param  string			A short stub to prefix the field name
	 * @param  SHORT_TEXT	The text of the test
	 * @param  ?MEMBER		The member the test is assigned to (NULL: test section member)
	 * @param  BINARY			Whether the test is enabled
	 * @param  string			The section this test inherits from (blank: none)
	 * @return tempcode		The tempcode for the visible fields
	 */
	function get_test_form_fields($stub,$test='',$assigned_to=NULL,$enabled=1,$inherit_from='')
	{
		require_code('form_templates');

		$fields=new ocp_tempcode();
		$fields->attach(form_input_line(do_lang_tempcode('DESCRIPTION'),do_lang_tempcode('DESCRIPTION_DESCRIPTION'),$stub.'_test',$test,true));
		$list=$this->get_tester_list($assigned_to);
		$fields->attach(form_input_list(do_lang_tempcode('TESTER'),do_lang_tempcode('DESCRIPTION_TESTER_2'),$stub.'_assigned_to',$list));
		$fields->attach(form_input_tick(do_lang_tempcode('ENABLED'),do_lang_tempcode('DESCRIPTION_ENABLED'),$stub.'_enabled',$enabled==1));
		$list2=form_input_list_entry('-1',is_null($inherit_from),do_lang_tempcode('NA_EM'));
		$list2->attach($this->get_section_list($inherit_from,true));
		$fields->attach(form_input_list(do_lang_tempcode('INHERIT_FROM'),do_lang_tempcode('DESCRIPTION_INHERIT_FROM'),$stub.'_inherit_section',$list2));

		return $fields;
	}

	/**
	 * Get tempcode for a test section adding/editing form.
	 *
	 * @param  SHORT_TEXT	The name of the section
	 * @param  LONG_TEXT		Notes for the section
	 * @param  ?MEMBER		The member the tests are assigned to (NULL: not a normal section, one that gets inherited into tests)
	 * @param  BINARY			Whether this test section is intended to be inherited, not used by itself
	 * @return tempcode		The tempcode for the visible fields
	 */
	function get_test_section_form_fields($section='',$notes='',$assigned_to=NULL,$inheritable=0)
	{
		require_code('form_templates');

		$fields=new ocp_tempcode();

		$fields->attach(form_input_line(do_lang_tempcode('NAME'),do_lang_tempcode('DESCRIPTION_NAME'),'section',$section,true));
		$fields->attach(form_input_text(do_lang_tempcode('NOTES'),do_lang_tempcode('DESCRIPTION_NOTES'),'notes',$notes,false));
		$list=$this->get_tester_list($assigned_to);
		$fields->attach(form_input_list(do_lang_tempcode('TESTER'),do_lang_tempcode('DESCRIPTION_TESTER_1'),'assigned_to',$list));
		$fields->attach(form_input_tick(do_lang_tempcode('INHERITABLE'),do_lang_tempcode('DESCRIPTION_INHERITABLE'),'inheritable',$inheritable==1));

		return $fields;
	}

	/**
	 * Inteface to add a test.
	 *
	 * @return tempcode	The result of execution.
	 */
	function add_test()
	{
		$title=get_page_title('ADD_TEST');

		check_specific_permission('add_tests');

		$list=$this->get_section_list(get_param_integer('id',-1));
		if ($list->is_empty())
		{
			inform_exit(do_lang_tempcode('NO_CATEGORIES'));
		}

		require_code('form_templates');
		$fields=form_input_list(do_lang_tempcode('CATEGORY'),'','id',$list,NULL,true);
		$fields->attach($this->get_test_form_fields('add_1'));

		$post_url=build_url(array('page'=>'_SELF','type'=>'_add_test'),'_SELF');

		return do_template('FORM_SCREEN',array('_GUID'=>'133ed356bc7cf270d9763f8cdc7f1d41','TITLE'=>$title,'SUBMIT_NAME'=>do_lang_tempcode('ADD_TEST'),'URL'=>$post_url,'FIELDS'=>$fields,'TEXT'=>'','HIDDEN'=>''));
	}

	/**
	 * Actualiser to add a test.
	 *
	 * @return tempcode	The result of execution.
	 */
	function _add_test()
	{
		$title=get_page_title('ADD_TEST');

		check_specific_permission('add_tests');

		$section_id=post_param_integer('id');
		$this->_add_new_tests($section_id);

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'add_test','id'=>$section_id),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Inteface to add a test section.
	 *
	 * @return tempcode	The result of execution.
	 */
	function ad()
	{
		$title=get_page_title('ADD_TEST_SECTION');

		check_specific_permission('add_tests');

		$fields=$this->get_test_section_form_fields();
		$add_template=do_template('TESTER_TEST_GROUP_NEW',array('_GUID'=>'8a7642944a36d2f9d1ee8c076a516f43','ID'=>'add_-REPLACEME-','FIELDS'=>$this->get_test_form_fields('add_-REPLACEME-')));

		$post_url=build_url(array('page'=>'_SELF','type'=>'_ad'),'_SELF');

		$tests='';

		return do_template('TESTER_ADD_SECTION_SCREEN',array('_GUID'=>'49172fc2c5ace05a632f9a5fdd91abd0','TITLE'=>$title,'SUBMIT_NAME'=>do_lang_tempcode('ADD_TEST_SECTION'),'TESTS'=>$tests,'URL'=>$post_url,'FIELDS'=>$fields,'ADD_TEMPLATE'=>$add_template));
	}

	/**
	 * Add in any new tests added in the form.
	 *
	 * @param  AUTO_LINK	The section to put the tests in.
	 */
	function _add_new_tests($section_id)
	{
		foreach (array_keys($_POST) as $key)
		{
			$matches=array();
			if (preg_match('#add_(\d+)_test#A',$key,$matches)!=0)
			{
				$id=$matches[1];

				$assigned_to=post_param_integer('add_'.strval($id).'_assigned_to');
				if ($assigned_to==-1) $assigned_to=NULL;

				$inherit_section=post_param_integer('add_'.strval($id).'_inherit_section');
				if ($inherit_section==-1) $inherit_section=NULL;

				$GLOBALS['SITE_DB']->query_insert('tests',array(
					't_section'=>$section_id,
					't_test'=>post_param('add_'.strval($id).'_test'),
					't_assigned_to'=>$assigned_to,
					't_enabled'=>post_param_integer('add_'.strval($id).'_enabled',0),
					't_status'=>0,
					't_inherit_section'=>$inherit_section
				));
			}
		}
	}

	/**
	 * Actualiser to add a test section.
	 *
	 * @return tempcode	The result of execution.
	 */
	function _ad()
	{
		$title=get_page_title('ADD_TEST_SECTION');

		check_specific_permission('add_tests');

		$assigned_to=post_param_integer('assigned_to');
		if ($assigned_to==-1) $assigned_to=NULL;

		$section_id=$GLOBALS['SITE_DB']->query_insert('test_sections',array(
			's_section'=>post_param('section'),
			's_notes'=>post_param('notes'),
			's_inheritable'=>post_param_integer('inheritable',0),
			's_assigned_to'=>$assigned_to
		),true);

		$this->_add_new_tests($section_id);

		// Show it worked / Refresh
		$url=build_url(array('page'=>'_SELF','type'=>'ad'),'_SELF');
		return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
	}

	/**
	 * Choose a test section to edit.
	 *
	 * @return tempcode	The result of execution.
	 */
	function ed()
	{
		$title=get_page_title('EDIT_TEST_SECTION');

		check_specific_permission('edit_own_tests');
		if (!$GLOBALS['FORUM_DRIVER']->is_staff(get_member())) access_denied('STAFF_ONLY');

		$list=$this->get_section_list();
		if ($list->is_empty())
		{
			inform_exit(do_lang_tempcode('NO_ENTRIES'));
		}

		$text=paragraph(do_lang_tempcode('CHOOSE_EDIT_LIST'));
		$post_url=build_url(array('page'=>'_SELF','type'=>'_ed'),'_SELF',NULL,false,true);
		require_code('form_templates');
		$fields=form_input_list(do_lang_tempcode('NAME'),'','id',$list,NULL,true);
		$submit_name=do_lang_tempcode('PROCEED');

		return do_template('FORM_SCREEN',array('_GUID'=>'37f70ba9d23204bceda6e84375b52270','GET'=>true,'SKIP_VALIDATION'=>true,'HIDDEN'=>'','TITLE'=>$title,'TEXT'=>$text,'URL'=>$post_url,'FIELDS'=>$fields,'SUBMIT_NAME'=>$submit_name));
	}

	/**
	 * Interface to edit a test section.
	 *
	 * @return tempcode	The result of execution.
	 */
	function _ed()
	{
		$title=get_page_title('EDIT_TEST_SECTION');

		check_specific_permission('edit_own_tests');

		$id=get_param_integer('id');
		$rows=$GLOBALS['SITE_DB']->query_select('test_sections',array('*'),array('id'=>$id),'',1);
		if (!array_key_exists(0,$rows))
		{
			warn_exit('MISSING_RESOURCE');
		}
		$section=$rows[0];

		if (!((has_specific_permission(get_member(),'edit_own_tests')) && (($section['s_assigned_to']==get_member()) || ($GLOBALS['FORUM_DRIVER']->is_staff(get_member())))))
			access_denied('ACCESS_DENIED');

		$fields=$this->get_test_section_form_fields($section['s_section'],$section['s_notes'],$section['s_assigned_to'],$section['s_inheritable']);
		$fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('TITLE'=>do_lang_tempcode('ACTIONS'))));
		$fields->attach(form_input_tick(do_lang_tempcode('DELETE'),do_lang_tempcode('DESCRIPTION_DELETE'),'delete',false));

		$add_template=do_template('TESTER_TEST_GROUP_NEW',array('_GUID'=>'3d0e12fdff0aef8f8aa5818e441238ee','ID'=>'add_-REPLACEME-','FIELDS'=>$this->get_test_form_fields('add_-REPLACEME-')));

		$_tests=$GLOBALS['SITE_DB']->query_select('tests',array('*'),array('t_section'=>$id));
		$tests=new ocp_tempcode();
		foreach ($_tests as $test)
		{
			$_fields=$this->get_test_form_fields('edit_'.strval($test['id']),$test['t_test'],$test['t_assigned_to'],$test['t_enabled'],$test['t_inherit_section']);
			$_fields->attach(do_template('FORM_SCREEN_FIELD_SPACER',array('TITLE'=>do_lang_tempcode('ACTIONS'))));
			$_fields->attach(form_input_tick(do_lang_tempcode('DELETE'),do_lang_tempcode('DESCRIPTION_DELETE'),'edit_'.strval($test['id']).'_delete',false));
			$_test=do_template('TESTER_TEST_GROUP',array('_GUID'=>'620b45c5ff5bf26417442865e6bcb045','ID'=>'edit_'.strval($test['id']),'FIELDS'=>$_fields));
			$tests->attach($_test);
		}

		$post_url=build_url(array('page'=>'_SELF','type'=>'__ed','id'=>$id),'_SELF');

		return do_template('TESTER_ADD_SECTION_SCREEN',array('_GUID'=>'ee10a568b6dacd8baf1efeac3e7bcb40','TITLE'=>$title,'SUBMIT_NAME'=>do_lang_tempcode('SAVE'),'TESTS'=>$tests,'URL'=>$post_url,'FIELDS'=>$fields,'ADD_TEMPLATE'=>$add_template));
	}

	/**
	 * Actualiser to edit a test section.
	 *
	 * @return tempcode	The result of execution.
	 */
	function __ed()
	{
		check_specific_permission('edit_own_tests');

		$id=get_param_integer('id');
		$rows=$GLOBALS['SITE_DB']->query_select('test_sections',array('*'),array('id'=>$id),'',1);
		if (!array_key_exists(0,$rows))
		{
			warn_exit('MISSING_RESOURCE');
		}
		$section=$rows[0];

		if (!((has_specific_permission(get_member(),'edit_own_tests')) && (($section['s_assigned_to']==get_member()) || ($GLOBALS['FORUM_DRIVER']->is_staff(get_member())))))
			access_denied('ACCESS_DENIED');

		if (post_param_integer('delete',0)==1)
		{
			$title=get_page_title('DELETE_TEST_SECTION');

			$GLOBALS['SITE_DB']->query_delete('test_sections',array('id'=>$id),'',1);
			$GLOBALS['SITE_DB']->query_delete('tests',array('t_section'=>$id));

			return inform_screen($title,do_lang_tempcode('SUCCESS'));
		} else
		{
			$title=get_page_title('EDIT_TEST_SECTION');

			// New tests
			$this->_add_new_tests($id);

			$assigned_to=post_param_integer('assigned_to');
			if ($assigned_to==-1) $assigned_to=NULL;

			$GLOBALS['SITE_DB']->query_update('test_sections',array(
				's_section'=>post_param('section'),
				's_notes'=>post_param('notes'),
				's_inheritable'=>post_param_integer('inheritable',0),
				's_assigned_to'=>$assigned_to
			),array('id'=>get_param_integer('id')),'',1);

			// Tests that are edited/deleted (or possibly unchanged, but we count that as edited)
			foreach (array_keys($_POST) as $key)
			{
				$matches=array();
				if (preg_match('#edit_(\d+)_test#',$key,$matches)!=0)
				{
					$tid=$matches[1];
					$delete=post_param_integer('edit_'.$tid.'_delete',0);
					if ($delete==1)
					{
						$GLOBALS['SITE_DB']->query_delete('tests',array('id'=>$tid),'',1);
					} else
					{
						$assigned_to=post_param_integer('edit_'.$tid.'_assigned_to');
						if ($assigned_to==-1) $assigned_to=NULL;

						$inherit_section=post_param_integer('edit_'.$tid.'_inherit_section');
						if ($inherit_section==-1) $inherit_section=NULL;

						$GLOBALS['SITE_DB']->query_update('tests',array(
							't_test'=>post_param('edit_'.$tid.'_test'),
							't_assigned_to'=>$assigned_to,
							't_enabled'=>post_param_integer('edit_'.$tid.'_enabled',0),
							't_inherit_section'=>$inherit_section
						),array('id'=>$tid),'',1);
					}
				}
			}

			// Show it worked / Refresh
			$url=build_url(array('page'=>'_SELF','type'=>'go'),'_SELF');
			return redirect_screen($title,$url,do_lang_tempcode('SUCCESS'));
		}
	}
}


