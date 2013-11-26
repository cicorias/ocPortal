<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2014

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		quizzes
 */

/**
 * Module page class.
 */
class Module_quiz
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
		$info['version']=5;
		$info['update_require_upgrade']=1;
		$info['locked']=false;
		return $info;
	}

	/**
	 * Standard modular uninstall function.
	 */
	function uninstall()
	{
		$GLOBALS['SITE_DB']->drop_table_if_exists('quizzes');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_questions');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_question_answers');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_entries');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_member_last_visit');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_winner');
		$GLOBALS['SITE_DB']->drop_table_if_exists('quiz_entry_answer');

		delete_privilege('bypass_quiz_repeat_time_restriction');
	}

	/**
	 * Standard modular install function.
	 *
	 * @param  ?integer	What version we're upgrading from (NULL: new install)
	 * @param  ?integer	What hack version we're upgrading from (NULL: new-install/not-upgrading-from-a-hacked-version)
	 */
	function install($upgrade_from=NULL,$upgrade_from_hack=NULL)
	{
		if ((!is_null($upgrade_from)) && ($upgrade_from<5))
		{
			$GLOBALS['SITE_DB']->add_table_field('quiz_questions','q_required','BINARY');
		}

		if (is_null($upgrade_from))
		{
			$GLOBALS['SITE_DB']->create_table('quiz_member_last_visit',array(
				'id'=>'*AUTO',
				'v_time'=>'TIME',
				'v_member_id'=>'MEMBER',
				'v_quiz_id'=>'AUTO_LINK',
			));

			add_privilege('QUIZZES','bypass_quiz_repeat_time_restriction',false);

			$GLOBALS['SITE_DB']->create_table('quizzes',array(
				'id'=>'*AUTO',
				'q_timeout'=>'?INTEGER', // The number of minutes to complete the test (not secure)
				'q_name'=>'SHORT_TRANS',
				'q_start_text'=>'LONG_TRANS',
				'q_end_text'=>'LONG_TRANS',
				'q_notes'=>'LONG_TEXT', // Staff notes
				'q_percentage'=>'INTEGER', // Percentage required for successful completion, if a test
				'q_open_time'=>'TIME',
				'q_close_time'=>'?TIME',
				'q_num_winners'=>'INTEGER',
				'q_redo_time'=>'?INTEGER', // Number of hours between attempts. NULL implies it may never be re-attempted
				'q_type'=>'ID_TEXT', // COMPETITION, TEST, SURVEY
				'q_add_date'=>'TIME',
				'q_validated'=>'BINARY',
				'q_submitter'=>'MEMBER',
				'q_points_for_passing'=>'INTEGER',
				'q_tied_newsletter'=>'?AUTO_LINK',
				'q_end_text_fail'=>'LONG_TRANS',
			));
			$GLOBALS['SITE_DB']->create_index('quizzes','q_validated',array('q_validated'));

			$GLOBALS['SITE_DB']->create_table('quiz_questions',array( // Note there is only a matching question_answer if it is not a free question. If there is just one answer, then it is not multiple-choice.
				'id'=>'*AUTO',
				'q_long_input_field'=>'BINARY', // Only applies for free questions
				'q_num_choosable_answers'=>'INTEGER', // If >1 then they can do multi-choice
				'q_quiz'=>'AUTO_LINK',
				'q_question_text'=>'LONG_TRANS',
				'q_order'=>'INTEGER',
				'q_required'=>'BINARY',
			));

			$GLOBALS['SITE_DB']->create_table('quiz_question_answers',array(
				'id'=>'*AUTO',
				'q_question'=>'AUTO_LINK',
				'q_answer_text'=>'SHORT_TRANS',
				'q_is_correct'=>'BINARY', // If this is the correct answer; only applies for quizzes
				'q_order'=>'INTEGER',
				'q_explanation'=>'LONG_TRANS',
			));

			$GLOBALS['SITE_DB']->create_table('quiz_winner',array(
				'q_quiz'=>'*AUTO_LINK',
				'q_entry'=>'*AUTO_LINK',
				'q_winner_level'=>'INTEGER',
			));

			$GLOBALS['SITE_DB']->create_table('quiz_entries',array(
				'id'=>'*AUTO',
				'q_time'=>'TIME',
				'q_member'=>'MEMBER',
				'q_quiz'=>'AUTO_LINK',
				'q_results'=>'INTEGER',
			));

			$GLOBALS['SITE_DB']->create_table('quiz_entry_answer',array(
				'id'=>'*AUTO',
				'q_entry'=>'AUTO_LINK',
				'q_question'=>'AUTO_LINK',
				'q_answer'=>'LONG_TEXT', // Either an ID or a textual answer
			));

			$GLOBALS['SITE_DB']->create_index('quizzes','ftjoin_qstarttext',array('q_start_text'));
		}
	}

	/**
	 * Standard modular entry-point finder function.
	 *
	 * @param  boolean	Whether to check permissions.
	 * @param  ?MEMBER	The member to check permissions as (NULL: current user).
	 * @param  boolean	Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
	 * @param  boolean	Whether to avoid any entry-point (or even return NULL to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "misc" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
	 * @return ?array		A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (NULL: disabled).
	 */
	function get_entry_points($check_perms=true,$member_id=NULL,$support_crosslinks=true,$be_deferential=false)
	{
		return array(
			'misc'=>array('QUIZZES','menu/rich_content/quiz'),
		);
	}

	var $title;
	var $id;
	var $quiz;
	var $quiz_name;
	var $title_to_use;
	var $title_to_use_2;

	/**
	 * Standard modular pre-run function, so we know meta-data for <head> before we start streaming output.
	 *
	 * @return ?tempcode		Tempcode indicating some kind of exceptional output (NULL: none).
	 */
	function pre_run()
	{
		$type=get_param('type','misc');

		require_lang('quiz');

		if ($type=='misc')
		{
			$this->title=get_screen_title('QUIZZES');
		}

		if ($type=='do')
		{
			$id=get_param_integer('id');

			$quizzes=$GLOBALS['SITE_DB']->query_select('quizzes',array('*'),array('id'=>$id),'',1);
			if (!array_key_exists(0,$quizzes)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
			$quiz=$quizzes[0];

			if ((get_value('no_awards_in_titles')!=='1') && (addon_installed('awards')))
			{
				require_code('awards');
				$awards=find_awards_for('quiz',strval($id));
			} else $awards=array();

			$quiz_name=get_translated_text($quiz['q_name']);
			$title_to_use=do_lang_tempcode('THIS_WITH',do_lang_tempcode($quiz['q_type']),make_fractionable_editable('quiz',$id,$quiz_name));
			$title_to_use_2=do_lang('THIS_WITH_SIMPLE',do_lang($quiz['q_type']),$quiz_name);
			seo_meta_load_for('quiz',strval($id),$title_to_use_2);

			breadcrumb_set_self(make_string_tempcode(escape_html(get_translated_text($quiz['q_name']))));

			$type='Quiz';
			switch ($quiz['q_type'])
			{
				case 'COMPETITION':
					$type='Competition';
					break;

				case 'SURVEY':
					$type='Survey';
					break;

				case 'TEST':
					$type='Test';
					break;
			}

			set_extra_request_metadata(array(
				'created'=>date('Y-m-d',$quiz['q_add_date']),
				'creator'=>$GLOBALS['FORUM_DRIVER']->get_username($quiz['q_submitter']),
				'publisher'=>'', // blank means same as creator
				'modified'=>'',
				'type'=>$type,
				'title'=>get_translated_text($quiz['q_name']),
				'identifier'=>'_SEARCH:quiz:do:'.strval($id),
				'description'=>get_translated_text($quiz['q_start_text']),
				'image'=>find_theme_image('icons/48x48/menu/rich_content/quiz'),
			));

			$this->id=$id;
			$this->quiz=$quiz;
			$this->quiz_name=$quiz_name;
			$this->title_to_use=$title_to_use;
			$this->title_to_use_2=$title_to_use_2;

			$this->title=get_screen_title(do_lang_tempcode('THIS_WITH',do_lang_tempcode($quiz['q_type']),make_string_tempcode(escape_html(get_translated_text($quiz['q_name'])))),false);
		}

		if ($type=='_do')
		{
			breadcrumb_set_self(do_lang_tempcode('DONE'));
			breadcrumb_set_parents(array(array('_SELF:_SELF:misc',make_string_tempcode(escape_html(get_translated_text($quiz['q_name']))))));

			$id=get_param_integer('id');

			$quizzes=$GLOBALS['SITE_DB']->query_select('quizzes',array('*'),array('id'=>$id),'',1);
			if (!array_key_exists(0,$quizzes)) warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
			$quiz=$quizzes[0];
			$this->enforcement_checks($quiz);

			$this->title=get_screen_title(do_lang_tempcode('THIS_WITH',do_lang_tempcode($quiz['q_type']),make_string_tempcode(escape_html(get_translated_text($quiz['q_name'])))),false);

			$this->id=$id;
			$this->quiz=$quiz;
		}

		return NULL;
	}

	/**
	 * Standard modular run function.
	 *
	 * @return tempcode	The result of execution.
	 */
	function run()
	{
		$type=get_param('type','misc');

		if ($type=='misc') return $this->archive();
		if ($type=='do') return $this->do_quiz();
		if ($type=='_do') return $this->_do_quiz();

		return new ocp_tempcode();
	}

	/**
	 * The UI to browse quizzes/surveys/tests.
	 *
	 * @return tempcode		The UI
	 */
	function archive()
	{
		require_code('quiz');

		$start=get_param_integer('quizzes_start',0);
		$max=get_param_integer('quizzes_max',20);

		$rows=$GLOBALS['SITE_DB']->query('SELECT * FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'quizzes WHERE '.(((!has_privilege(get_member(),'see_unvalidated')) && (addon_installed('unvalidated')))?'q_validated=1 AND ':'').'q_open_time<'.strval(time()).' AND (q_close_time IS NULL OR q_close_time>'.strval(time()).') ORDER BY q_type ASC,id DESC',$max,$start);
		$max_rows=$GLOBALS['SITE_DB']->query_value_if_there('SELECT COUNT(*) FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'quizzes WHERE '.(((!has_privilege(get_member(),'see_unvalidated')) && (addon_installed('unvalidated')))?'q_validated=1 AND ':'').'q_open_time<'.strval(time()).' AND (q_close_time IS NULL OR q_close_time>'.strval(time()).')');
		if (count($rows)==0) inform_exit(do_lang_tempcode('NO_ENTRIES'));
		$content_tests=new ocp_tempcode();
		$content_competitions=new ocp_tempcode();
		$content_surveys=new ocp_tempcode();
		foreach ($rows as $myrow)
		{
			$link=render_quiz_box($myrow,'_SEARCH',false);

			switch ($myrow['q_type'])
			{
				case 'SURVEY':
					$content_surveys->attach($link);
					break;
				case 'TEST':
					$content_tests->attach($link);
					break;
				case 'COMPETITION':
					$content_competitions->attach($link);
					break;
			}
		}

		require_code('templates_pagination');
		$pagination=pagination(do_lang_tempcode('QUIZZES'),$start,'quizzes_start',$max,'quizzes_max',$max_rows);

		$tpl=do_template('QUIZ_ARCHIVE_SCREEN',array('_GUID'=>'3073f74b500deba96b7a3031a2e9c8d8','TITLE'=>$this->title,'CONTENT_SURVEYS'=>$content_surveys,'CONTENT_COMPETITIONS'=>$content_competitions,'CONTENT_TESTS'=>$content_tests,'PAGINATION'=>$pagination));

		require_code('templates_internalise_screen');
		return internalise_own_screen($tpl);
	}

	/**
	 * Make sure the entry rules of a quiz are not being broken. Exits when they may not enter.
	 *
	 * @param  array	The db row of the quiz
	 */
	function enforcement_checks($quiz)
	{
		// Check they are not a guest trying to do a quiz a guest could not do
		if ((is_guest()) && (($quiz['q_points_for_passing']!=0) || (!is_null($quiz['q_redo_time'])) || ($quiz['q_num_winners']!=0)))
			access_denied('NOT_AS_GUEST');

		// Check they are on the necessary newsletter, if appropriate
		if ((!is_null($quiz['q_tied_newsletter'])) && (addon_installed('newsletter')))
		{
			$on=$GLOBALS['SITE_DB']->query_select_value_if_there('newsletter_subscribe','email',array('newsletter_id'=>$quiz['q_tied_newsletter'],'email'=>$GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member())));
			if (is_null($on)) warn_exit(do_lang_tempcode('NOT_ON_NEWSLETTER'));
		}

		// Check it is open
		if (((!is_null($quiz['q_close_time'])) && ($quiz['q_close_time']<time())) || ($quiz['q_open_time']>time()))
			warn_exit(do_lang_tempcode('NOT_OPEN_THIS',do_lang_tempcode($quiz['q_type'])));

		// Check they are allowed to do this (if repeating)
		if ((!has_privilege(get_member(),'bypass_quiz_repeat_time_restriction')) && (!is_null($quiz['q_redo_time'])))
		{
			$last_entry=$GLOBALS['SITE_DB']->query_select_value_if_there('quiz_entries','q_time',array('q_member'=>get_member(),'q_quiz'=>$quiz['id']),'ORDER BY q_time DESC');
			if ((!is_null($last_entry)) && ($last_entry+$quiz['q_redo_time']*60*60>time()) && ((is_null($quiz['q_timeout'])) || (time()-$last_entry>=$quiz['q_timeout']))) // If passed timeout and less than redo time, error
				warn_exit(do_lang_tempcode('REPEATING_TOO_SOON',get_timezoned_date($last_entry+$quiz['q_redo_time']*60*60)));
		}
	}

	/**
	 * The UI for doing a quiz.
	 *
	 * @return tempcode	The result of execution.
	 */
	function do_quiz()
	{
		$id=$this->id;
		$quiz=$this->quiz;
		$quiz_name=$this->quiz_name;
		$title_to_use=$this->title_to_use;
		$title_to_use_2=$this->title_to_use_2;

		$this->enforcement_checks($quiz);

		$last_visit_time=$GLOBALS['SITE_DB']->query_select_value_if_there('quiz_member_last_visit','v_time',array('v_quiz_id'=>$id,'v_member_id'=>get_member()),'ORDER BY v_time DESC');
		if (!is_null($last_visit_time)) // Refresh / new attempt
		{
			$timer_offset=time()-$last_visit_time;
			if ((is_null($quiz['q_timeout'])) || ($timer_offset>=$quiz['q_timeout']*60)) // Treat as a new attempt. Must be within redo time to get here
			{
				$GLOBALS['SITE_DB']->query_delete('quiz_member_last_visit',array(
					'v_member_id'=>get_member(),
					'v_quiz_id'=>$id,
				));
				$GLOBALS['SITE_DB']->query_insert('quiz_member_last_visit',array(
					'v_quiz_id'=>$id,
					'v_time'=>time(),
					'v_member_id'=>get_member(),
				));
				$timer_offset=0;
			}
		} else
		{
			$GLOBALS['SITE_DB']->query_insert('quiz_member_last_visit',array( // First attempt
				'v_quiz_id'=>$id,
				'v_time'=>time(),
				'v_member_id'=>get_member(),
			));
			$timer_offset=0;
		}

		$questions=$GLOBALS['SITE_DB']->query_select('quiz_questions',array('*'),array('q_quiz'=>$id),'ORDER BY q_order');
		// If a test/quiz, randomly order questions
		//if ($quiz['q_type']!='SURVEY') shuffle($questions);			No, could cause problems
		foreach ($questions as $i=>$question)
		{
			$answers=$GLOBALS['SITE_DB']->query_select('quiz_question_answers',array('*'),array('q_question'=>$question['id']),'ORDER BY q_order');
			// If a test/quiz, randomly order answers
			if ($quiz['q_type']!='SURVEY') shuffle($answers);
			$questions[$i]['answers']=$answers;
		}

		require_code('quiz');
		$fields=render_quiz($questions);

		// Validation
		if (($quiz['q_validated']==0) && (addon_installed('unvalidated')))
		{
			if (!has_privilege(get_member(),'jump_to_unvalidated'))
				access_denied('PRIVILEGE','jump_to_unvalidated');

			$warning_details=do_template('WARNING_BOX',array('_GUID'=>'fc690dedf8601cc456e011931dfec595','WARNING'=>do_lang_tempcode((get_param_integer('redirected',0)==1)?'UNVALIDATED_TEXT_NON_DIRECT':'UNVALIDATED_TEXT')));
		} else $warning_details=new ocp_tempcode();

		$edit_url=new ocp_tempcode();
		if ((has_actual_page_access(NULL,'cms_quiz',NULL,NULL)) && (has_edit_permission('mid',get_member(),$quiz['q_submitter'],'cms_quiz',array('quiz',$id))))
		{
			$edit_url=build_url(array('page'=>'cms_quiz','type'=>'_ed','id'=>$id),get_module_zone('cms_quiz'));
		}

		// Display UI: start text, questions. Including timeout
		$start_text=get_translated_tempcode($quiz['q_start_text']);
		$post_url=build_url(array('page'=>'_SELF','type'=>'_do','id'=>$id),'_SELF');
		return do_template('QUIZ_SCREEN',array(
			'_GUID'=>'f390877672938ba62f79f9528bef742f',
			'EDIT_URL'=>$edit_url,
			'TAGS'=>get_loaded_tags('quiz'),
			'ID'=>strval($id),
			'WARNING_DETAILS'=>$warning_details,
			'URL'=>$post_url,
			'TITLE'=>$this->title,
			'START_TEXT'=>$start_text,
			'FIELDS'=>$fields,
			'TIMEOUT'=>is_null($quiz['q_timeout'])?'':strval($quiz['q_timeout']*60-$timer_offset),
		));
	}

	/**
	 * Actualiser: process quiz results.
	 *
	 * @return tempcode	The result of execution.
	 */
	function _do_quiz()
	{
		$id=$this->id;
		$quiz=$this->quiz;

		$last_visit_time=$GLOBALS['SITE_DB']->query_select_value_if_there('quiz_member_last_visit','v_time',array('v_quiz_id'=>$id,'v_member_id'=>get_member()),'ORDER BY v_time DESC');
		if (is_null($last_visit_time)) warn_exit(do_lang_tempcode('QUIZ_TWICE'));
		if (!is_null($quiz['q_timeout']))
		{
			if (time()-$last_visit_time>$quiz['q_timeout']*60+10) warn_exit(do_lang_tempcode('TOO_LONG_ON_SCREEN')); // +10 is for page load time, worst case scenario to be fair
		}

		// Our entry
		$entry_id=$GLOBALS['SITE_DB']->query_insert('quiz_entries',array(
			'q_time'=>time(),
			'q_member'=>get_member(),
			'q_quiz'=>$id,
			'q_results'=>0,
		),true);

		$GLOBALS['SITE_DB']->query_update('quiz_member_last_visit',array( // Say quiz was completed on time limit, to force next attempt to be considered a re-do
			'v_time'=>time()-(is_null($quiz['q_timeout'])?0:$quiz['q_timeout'])*60,
		),array('v_member_id'=>get_member(),'v_quiz_id'=>$id),'',1);

		// Calculate results and store
		$questions=$GLOBALS['SITE_DB']->query_select('quiz_questions',array('*'),array('q_quiz'=>$id));
		foreach ($questions as $i=>$question)
		{
			$answers=$GLOBALS['SITE_DB']->query_select('quiz_question_answers',array('*'),array('q_question'=>$question['id']));
			$questions[$i]['answers']=$answers;
		}
		$marks=0.0;
		$potential_extra_marks=0;
		$out_of=count($questions);
		if ($out_of==0) $out_of=1;
		$results=array();
		$corrections=array();
		$unknowns=array();
		foreach ($questions as $i=>$question)
		{
			$name='q_'.strval($question['id']);
			if ($question['q_num_choosable_answers']==0) // Text box ("free question"). May be an actual answer, or may not be
			{
				if (count($question['answers'])==0)
				{
					$potential_extra_marks++;
					$unknowns[]=array(get_translated_text($question['q_question_text']),post_param($name));
				} else
				{
					$was_right=false;
					$correct_answer=new ocp_tempcode();
					$correct_explanation=NULL;
					foreach ($question['answers'] as $a)
					{
						if ($a['q_is_correct']==1)
						{
							$correct_answer=make_string_tempcode(escape_html(get_translated_text($a['q_answer_text'])));
						}
						if (($a['q_is_correct']==1) && (get_translated_text($a['q_answer_text'])==post_param($name)))
						{
							$marks++;
							$was_right=true;
							break;
						}
						if (get_translated_text($a['q_answer_text'])==post_param($name))
						{
							$correct_explanation=$a['q_explanation'];
						}
					}
					if (!$was_right)
					{
						$correction=array($question['id'],get_translated_text($question['q_question_text']),$correct_answer,post_param($name));
						if (!is_null($correct_explanation))
						{
							$explanation=get_translated_text($correct_explanation);
							if ($explanation!='')
								$correction[]=$explanation;
						}
						$corrections[]=$correction;
					}
				}

				$results[$i]=post_param($name);

				$GLOBALS['SITE_DB']->query_insert('quiz_entry_answer',array(
					'q_entry'=>$entry_id,
					'q_question'=>$question['id'],
					'q_answer'=>$results[$i]
				));
			}
			elseif ($question['q_num_choosable_answers']>1) // Check boxes
			{
				// Vector distance
				$wrongness=0.0;
				$accum=new ocp_tempcode();
				$correct_answer=new ocp_tempcode();
				$correct_explanation=NULL;
				foreach ($question['answers'] as $a)
				{
					$for_this=post_param_integer($name.'_'.strval($a['id']),0);
					$should_be_this=$a['q_is_correct'];
					$dist=$for_this-$should_be_this;
					$wrongness+=$dist*$dist;

					if ($should_be_this==1)
					{
						if (!$correct_answer->is_empty()) $correct_answer->attach(do_lang_tempcode('LIST_SEP'));
						$correct_answer->attach(escape_html(get_translated_text($a['q_answer_text'])));
						$correct_explanation=$a['q_explanation'];
					}

					if ($for_this==1)
					{
						if (!$accum->is_empty()) $accum->attach(do_lang_tempcode('LIST_SEP'));
						$accum->attach(escape_html(get_translated_text($a['q_answer_text'])));

						$GLOBALS['SITE_DB']->query_insert('quiz_entry_answer',array(
							'q_entry'=>$entry_id,
							'q_question'=>$question['id'],
							'q_answer'=>strval($a['id'])
						));
					}
				}
				$wrongness=sqrt($wrongness);
				// Normalise it
				$wrongness/=count($question['answers']);
				// And get our complement
				$correctness=1.0-$wrongness;

				$marks+=$correctness;

				if ($correctness!=1.0)
				{
					$correction=array($question['id'],get_translated_text($question['q_question_text']),$correct_answer,$accum);
					if (!is_null($correct_explanation))
					{
						$explanation=get_translated_text($correct_explanation);
						if ($explanation!='')
							$correction[]=$explanation;
					}
					$corrections[]=$correction;
				}

				$results[$i]=$accum->evaluate();
			} else // Radio buttons
			{
				$was_right=false;
				$correct_answer=new ocp_tempcode();
				$correct_explanation=NULL;
				foreach ($question['answers'] as $a)
				{
					if ($a['q_is_correct']==1)
					{
						$correct_answer=make_string_tempcode(escape_html(get_translated_text($a['q_answer_text'])));
					}

					if (post_param_integer($name,-1)==$a['id'])
					{
						$results[$i]=get_translated_text($a['q_answer_text']);

						if ($a['q_is_correct']==1)
						{
							$was_right=true;

							$marks++;
							break;
						}

						$correct_explanation=$a['q_explanation'];
					}
				}

				$GLOBALS['SITE_DB']->query_insert('quiz_entry_answer',array(
					'q_entry'=>$entry_id,
					'q_question'=>$question['id'],
					'q_answer'=>post_param($name,'')
				));

				if (!array_key_exists($i,$results)) $results[$i]='/';

				if (!$was_right)
				{
					$correction=array($question['id'],get_translated_text($question['q_question_text']),$correct_answer,$results[$i]);
					if (!is_null($correct_explanation))
					{
						$explanation=get_translated_text($correct_explanation);
						if ($explanation!='')
							$correction[]=$explanation;
					}
					$corrections[]=$correction;
				}
			}
		}

		$notification_title=do_lang('QUIZ_NOTIFICATION_TITLE',do_lang($quiz['q_type']),$GLOBALS['FORUM_DRIVER']->get_username(get_member()),strval($entry_id),get_site_default_lang());

		$_corrections=new ocp_tempcode();
		$_corrections_to_show=new ocp_tempcode();
		foreach ($corrections as $correction)
		{
			$this_correction=new ocp_tempcode();
			$this_correction->attach(do_lang('QUIZ_MISTAKE',is_object($correction[1])?$correction[1]->evaluate():$correction[1],is_object($correction[3])?$correction[3]->evaluate():$correction[3],array(is_object($correction[2])?$correction[2]->evaluate():$correction[2],array_key_exists(4,$correction)?$correction[4]:'')));
			if (array_key_exists(4,$correction))
				$_corrections_to_show->attach($this_correction);
			$_corrections->attach($this_correction);
		}

		$_answers=new ocp_tempcode();
		foreach ($results as $i=>$result)
		{
			$_answers->attach(do_lang('QUIZ_RESULT',get_translated_text($questions[$i]['q_question_text']),is_null($result)?'':$result));
		}

		$_unknowns=new ocp_tempcode();
		foreach ($unknowns as $unknown)
		{
			$_unknowns->attach(do_lang('QUIZ_UNKNOWN',$unknown[0],$unknown[1]));
		}

		require_code('notifications');

		// Award points?
		if ($out_of==0) $out_of=1;
		$minimum_percentage=intval(round(100.0*$marks/$out_of));
		$maximum_percentage=intval(round(100.0*($marks+$potential_extra_marks)/$out_of));
		if ((addon_installed('points')) && ($quiz['q_points_for_passing']!=0) && (($quiz['q_type']!='TEST') || ($minimum_percentage>=$quiz['q_percentage'])))
		{
			require_code('points2');
			$points_difference=$quiz['q_points_for_passing'];
			system_gift_transfer(do_lang('POINTS_COMPLETED_QUIZ',get_translated_text($quiz['q_name'])),$points_difference,get_member());
		} else
		{
			$points_difference=0;
		}

		// Give them their result if it is a test.
		if ($quiz['q_type']=='TEST')
		{
			$result=new ocp_tempcode();
			$result->attach(paragraph(do_lang_tempcode('MARKS_OUT_OF',float_format($marks).(($potential_extra_marks==0)?'':('-'.float_format($marks+$potential_extra_marks))),integer_format($out_of),strval($minimum_percentage).(($potential_extra_marks==0)?'':('-'.strval($maximum_percentage)))),'trete9r0itre'));
			$result2=do_lang_tempcode('MAIL_MARKS_OUT_OF',float_format($marks).(($potential_extra_marks==0)?'':('-'.float_format($marks+$potential_extra_marks))),integer_format($out_of),strval($minimum_percentage).(($potential_extra_marks==0)?'':('-'.strval($maximum_percentage))));
			if ($minimum_percentage>=$quiz['q_percentage'])
			{
				$result->attach(paragraph(do_lang_tempcode('TEST_PASS'),'4tfdhdhghh'));
				$result2->attach(do_lang_tempcode('MAIL_TEST_PASS'));

				require_code('activities');
				syndicate_described_activity('quiz:ACTIVITY_PASSED_TEST',get_translated_text($quiz['q_name']),'','','_SEARCH:quiz:do:'.strval($id),'','','quizzes');
			}
			elseif ($maximum_percentage<$quiz['q_percentage'])
			{
				$result->attach(paragraph(do_lang_tempcode('TEST_FAIL'),'5yrgdgsdg'));
				$result2->attach(do_lang_tempcode('MAIL_TEST_FAIL'));
			} else
			{
				$result->attach(paragraph(do_lang_tempcode('TEST_UNKNOWN'),'yteyrthrt'));
				$result2->attach(do_lang_tempcode('MAIL_TEST_UNKNOWN'));
			}

			// Send mail about the result to the staff: include result and corrections, and unknowns
			$mail=do_template('QUIZ_TEST_ANSWERS_MAIL',array('_GUID'=>'a0f8f47cdc1ef83b59c93135ebb5c114','UNKNOWNS'=>$_unknowns,'CORRECTIONS'=>$_corrections,'RESULT'=>$result2,'USERNAME'=>$GLOBALS['FORUM_DRIVER']->get_username(get_member())));
			dispatch_notification('quiz_results',strval($id),$notification_title,$mail->evaluate(get_site_default_lang()));
		}
		// Give them corrections if it is a quiz.
		elseif ($quiz['q_type']=='COMPETITION')
		{
			$result=comcode_to_tempcode($_corrections->evaluate());

			require_code('activities');
			syndicate_described_activity('quiz:ACTIVITY_ENTERED_COMPETITION',get_translated_text($quiz['q_name']),'','','_SEARCH:quiz:do:'.strval($id),'','','quizzes');
		} else
		{
			$result=paragraph(do_lang_tempcode('SURVEY_THANKYOU'),'4rtyrthgf');

			$_answers=do_template('QUIZ_ANSWERS_MAIL',array('_GUID'=>'381f392c8e491b6e078bcae34adc45e8','ANSWERS'=>$_answers,'MEMBER_PROFILE_URL'=>is_guest()?'':$GLOBALS['FORUM_DRIVER']->member_profile_url(get_member(),false,true),'USERNAME'=>$GLOBALS['FORUM_DRIVER']->get_username(get_member())));

			// Send mail of answers to the staff
			dispatch_notification('quiz_results',strval($id),$notification_title,$_answers->evaluate(get_site_default_lang()));

			require_code('activities');
			syndicate_described_activity('quiz:ACTIVITY_FILLED_SURVEY',get_translated_text($quiz['q_name']),'','','_SEARCH:quiz:do:'.strval($id),'','','quizzes');
		}

		// Store results for entry
		$GLOBALS['SITE_DB']->query_update('quiz_entries',array('q_results'=>intval(round($marks))),array('id'=>$entry_id),'',1);

		// Show end text
		$fail_text=get_translated_tempcode($quiz['q_end_text_fail']);
		$message=(($quiz['q_type']!='TEST') || ($minimum_percentage>=$quiz['q_percentage']) || ($fail_text->is_empty()))?get_translated_tempcode($quiz['q_end_text']):get_translated_tempcode($quiz['q_end_text_fail']);
		return do_template('QUIZ_DONE_SCREEN',array(
			'_GUID'=>'fa783f087eca7f8f577b134ec0bdc4ce',
			'CORRECTIONS_TO_SHOW'=>comcode_to_tempcode($_corrections_to_show->evaluate()),
			'POINTS_DIFFERENCE'=>strval($points_difference),
			'RESULT'=>$result,
			'TITLE'=>$this->title,
			'TYPE'=>$quiz['q_type'],
			'MESSAGE'=>$message,
		));
	}
}


