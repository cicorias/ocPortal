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
 * @package		user_sync
 */

/*

Customise this code for your particular import scenario.

*/

function get_user_sync_env()
{
	$db_type='mysql';
	$db_host=get_db_site_host();
	$db_name='thecnm';
	$db_user=get_db_site_user();
	$db_password=get_db_site_password();
	$db_table='cnm_students';
	$db_field_delim='`';

	//$username_fields=array('first_name','Surname'); // Fields that forms the username
	$username_fields=array('contact_ref');
	$username_fields_types=array('VARCHAR');

	$time_field='modification';

	$field_remap=array(
		/*'
		e.g.

		OCP FIELD NAME (without prefix)'=>array(
			'LOOKUP TYPE (default, field, or callback)',
			'SOURCE FIELD NAME or VALUE or CALLBACK',
			array(REMAPS [optional]),
			array(REVERSE REMAPS [optional]),
			'DESTINATION TYPE [optional - defaults to something based upon PHP type; expects a standard SQL type name]',
		),
		*/

		'email_address'=>array(
			'field',
			'email_address',
		),
		'groups'=>array(
			'callback',
			'get_user_sync_env__groups',
		),
		'primary_group'=>array(
			'callback',
			'get_user_sync_env__primary_group',
		),
		'on_probation_until'=>array(
			'callback',
			'get_user_sync_env__on_probation_until',
		),

		// CPFs...
		'Title'=>array(
			'field',
			'Title',
		),
		'Forename'=>array(
			'field',
			'first_name',
		),
		'Surname'=>array(
			'field',
			'Surname',
		),
		'Nationality'=>array(
			'field',
			'Nationality',
		),
		'Address 1'=>array(
			'field',
			'Address 1',
		),
		'Address 2'=>array(
			'field',
			'Address 2',
		),
		'Address 3'=>array(
			'field',
			'Address 3',
		),
		'Address 4'=>array(
			'field',
			'town',
		),
		'City'=>array(
			'field',
			'city',
		),
		'County'=>array(
			'field',
			'County',
		),
		'Post Code'=>array(
			'field',
			'Postcode',
		),
		'Country'=>array(
			'field',
			'Country',
		),
		'Phone (landline)'=>array(
			'field',
			'direct_line',
		),
		'Phone (mobile)'=>array(
			'field',
			'Mobile',
		),
		'Phone (work)'=>array(
			'field',
			'Telephone',
		),
		'Fax'=>array(
			'field',
			'fax number',
		),
		'Referring college'=>array(
			'field',
			'ref_col',
		),
		'Training provider'=>array(
			'callback',
			'get_user_sync_env__training_provider',
		),
	);

	$default_password=NULL; // NULL means random password
	$temporary_password=true;

	return array(
		$db_type,
		$db_host,
		$db_name,
		$db_user,
		$db_password,
		$db_table,

		$db_field_delim,

		$username_fields,
		$username_fields_types,
		$time_field,

		$field_remap,

		$default_password,
		$temporary_password,
	);
}

function get_user_sync_env__groups($field_name,$remote_data,$dbh,$member_id)
{
	$groups=_get_user_sync_env__groups($field_name,$remote_data,$dbh,$member_id);
	array_shift($groups); // Remove primary group
	return $groups;
}

function get_user_sync_env__primary_group($field_name,$remote_data,$dbh,$member_id)
{
	$groups=_get_user_sync_env__groups($field_name,$remote_data,$dbh,$member_id);
	return array_shift($groups); // Return primary group
}

function _get_user_sync_env__groups($field_name,$remote_data,$dbh,$member_id)
{
	$student_id=$remote_data['contact_ref'];

	// Cache check
	static $cache=array();
	if (array_key_exists($student_id,$cache))
	{
		return $cache[$student_id];
	}

	if (!is_null($member_id))
	{
		$_secondary_groups=$GLOBALS['FORUM_DB']->query('SELECT g.id FROM '.$GLOBALS['FORUM_DB']->get_table_prefix().'f_group_members m JOIN f_groups g ON m.gm_group_id=g.id WHERE m.gm_member_id='.strval($member_id).' AND m.gm_validated=1 AND '.db_string_not_equal_to('g.g_rank_image',''));
		$secondary_groups=collapse_1d_complexity('gm_group_id',$_secondary_groups);
	} else
	{
		$secondary_groups=array();
	}

	// Do calculation
	if ($remote_data['email_address']=='') // If the member does not have an email address on record
	{
		$groups=array_merge(array(strval(get_first_default_group())),$secondary_groups);
	} else
	{
		$is_student=false;
		$is_graduate=false;

		$sth=$dbh->query('SELECT e.*,c.course_name FROM cnm_enrolements e JOIN cnm_courses c ON c.kp_college_course_id=e.k_college_course_code WHERE student_id='.strval($remote_data['contact_ref']));
		$enrolments=$sth->fetchAll(PDO::FETCH_ASSOC);
		foreach ($enrolments as $enrolment)
		{
			if (is_null($enrolment['course_completed'])) $enrolment['course_completed']='';
			switch ($enrolment['course_completed'])
			{
				case '': // blank means "In progress"
					$is_student=true;
					$secondary_groups[]=trim($enrolment['course_name'],' -'); // Shared usergroup
					$secondary_groups[]=$GLOBALS['FORUM_DB']->query_select_value('f_groups c JOIN catalogue_entry_linkage l ON c.id=l.content_id JOIN '.get_table_prefix().'catalogue_efv_short x ON x.ce_id=l.catalogue_entry_id','c.id',array('x.cv_value'=>$enrolment['kp_college_course_id'],'x.cf_id'=>find_custom_field_id('group','Course ID'),'l.content_type'=>'_group')); // Specific usergroup
					break;

				case 'Completed':
					$is_graduate=true;
					break;

				default: // Some other case, e.g. Lecturer
					break;
			}
		}

		$groups=array();
		if ($is_student)
		{
			$groups[]='Students';
		}
		if ($is_graduate)
		{
			$groups[]='Graduates';
		}
		$groups=array_merge($groups,$secondary_groups);
	}

	// Cache set/return
	$cache[$student_id]=$groups;
	return $groups;
}

function get_user_sync_env__training_provider($field_name,$remote_data,$dbh)
{
	$ref_col=explode(chr(10),$remote_data['ref_col']);
	switch (array_pop($ref_col)/*checks last field*/)
	{
		case 'Cork':
		case 'Dublin':
		case 'Limerick':
		case 'Galway':
			return 'CNM IE';
		case 'Tampa':
			return 'ASNH';
		case 'London':
		case 'Birmingham':
		case 'Brighton':
		case 'Bristol':
		case 'Edinburgh':
		case 'Glasgow':
		case 'Manchester':
		case 'Belfast':
		case 'Exeter':
		default:
			return 'CNM UK';
	}
	return '';
}

function get_user_sync_env__on_probation_until($field_name,$remote_data,$dbh)
{
	if ($remote_data['block_web']=='Yes') return 2147483647; // Maximum timestamp
	return NULL;
}

function get_user_sync__begin($dbh,$since)
{
	// Make sure that we are not going to miss changed enrolments
	if (!is_null($since))
	{
		$sth=$dbh->query('UPDATE cnm_students SET modification=GREATEST(modification,(SELECT MAX(modification) FROM cnm_enrolements WHERE student_id=contact_ref))');
	}
}

function get_user_sync__finish($dbh,$since)
{
	require_code('resource_fs');
	$resourcefs=get_resource_occlefs_object('catalogue_entry');

	// Colleges
	$root_college_cat=get_catalogue_root_filename($resourcefs,'fm-colleges');
	$sql='SELECT * FROM cnm_colleges';
	if (!is_null($since))
		$sql.=' WHERE `modification timestamp`>=\''.date('Y-m-d H:i:s',$since).'\'';
	$sth=$dbh->query($sql);
	$course_college_cats=array();
	while (($row=$sth->fetch(PDO::FETCH_ASSOC))!==false)
	{
		// Add colleges entry

		if ((is_null($row['address_ref_no'])) || ($row['address_ref_no']==''))
		{
			resourcefs_logging('Blank/NULL address_ref_no found','warn');
			continue;
		}

		$path='fm-colleges/'.$root_college_cat;
		if ($row['co_country']!='')
		{
			$path.='/'.convert_label_to_filename($row['co_country'],$path,'catalogue_category',false);
			if ($row['co_city']!='')
			{
				$path.='/'.convert_label_to_filename($row['co_city'],$path,'catalogue_category',false);
				$path.='/'.$row['co_city'];
			}
		}
		// ^ NB: Intentionally no view permissions to the catalogue categories implied above

		$label=$row['address_ref_no'];

		$properties=array(
			'College name'=>$row['company_name'],
			'Address 1'=>$row['co_address_1'],
			'Address 2'=>$row['co_address_2'],
			'Address 3'=>$row['co_address_3'],
			'Address 4'=>$row['co_town'],
			'City'=>$row['co_city'],
			'County'=>$row['co_county'],
			'Post Code'=>$row['co_postcode'],
			'Country'=>$row['co_country'],
			'Phone number'=>$row['telephone'],
			'Fax number'=>$row['fax_number'],
			'Email address'=>str_replace('*','',$row['co_email']),
		);

		$more_properties=array(
			'edit_date'=>strtotime($row['modification timestamp']),
		);

		$resourcefs->file_save($label,$path,_key_remap($properties)+$more_properties,'catalogue_entry','fm-colleges/*');

		// Add category structure within other catalogues
		foreach (array('fn-courses') as $catalogue_name)
		{
			$path=$catalogue_name.'/'.get_catalogue_root_filename($resourcefs,$catalogue_name);

			$label=$row['company_name'];
			// ^^ NB: If the college name is changed, new categories will be made -- but we will automatically end up moving entries into the new categories and cleaning up the old ones

			$properties=array();

			$more_properties=array(
				'edit_date'=>strtotime($row['modification timestamp']),
			);

			$cat_id=$resourcefs->folder_save($label,$path,$properties+$more_properties,'catalogue_category');
			$course_college_cats[$row['address_ref_no']]=$cat_id;
		}
	}

	$enrolment_course_cats=array();
	$lecture_course_cats=array();
	$schedule_course_cats=array();

	// Courses
	$root_course_cat=get_catalogue_root_filename($resourcefs,'fm-courses');
	$sql='SELECT c.*,cc.company_name FROM cnm_courses c JOIN cnm_colleges cc ON cc.address_ref_no=c.kp_college_course_id';
	if (!is_null($since))
		$sql.=' WHERE `modification timestamp`>=\''.date('Y-m-d H:i:s',$since).'\'';
	$sth=$dbh->query($sql);
	$course_course_cats=array();
	while (($row=$sth->fetch(PDO::FETCH_ASSOC))!==false)
	{
		$course_name=is_null($row['course_name'])?'':trim($row['course_name'],' -');

		if ((is_null($row['kp_college_course_id'])) || ($row['kp_college_course_id']==''))
		{
			resourcefs_logging('Blank/NULL kp_college_course_id found','warn');
			continue;
		}

		if ($row['course_name']=='')
		{
			resourcefs_logging('Blank/NULL course name found','warn');
			continue;
		}

		$path='fm-courses/'.$root_course_cat;
		$path.='/'.convert_label_to_filename($row['company_name'],$path,'catalogue_category',false);

		// Ensure the category for the course name exists (there may be multiple courses with the same name - which is what we WANT)

		if (!isset($course_course_cats[$course_name]))
		{
			$path2='fm-courses/'.get_catalogue_root_filename($resourcefs,'fm-courses');
			if (!isset($course_college_cats[$row['kp_college_course_id']]))
			{
				$course_college_cats[$row['kp_college_course_id']]=convert_label_to_filename($row['company_name'],$path2,'catalogue_category',false);
			}
			$path2.='/'.$course_college_cats[$row['kp_college_course_id']];

			$label=$course_name;

			$properties=array();

			$cat_id=$resourcefs->folder_save($label,$path2,$properties,'catalogue_category');

			$course_course_cats[$course_name]=$cat_id;
		}

		// Add courses entry

		$path.='/'.$course_course_cats[$course_name];

		$label=$row['kp_college_course_id'];

		$college_id=convert_label_to_id($row['k_college_id'],'fm-colleges/'.$root_college_cat.'/*','catalogue_entry'); // Set to auto-create if missing, but will be logged

		$properties=array(
			'Course description'=>$row['course_description'],
			'Schedule'=>$row['course_schedule_description'],
			'Course name'=>$course_name,
			'Exams'=>strval($row['course_number_of_exams']),
			'Blocks'=>strval($row['course_blocks']),
			'Location'=>$college_id,
			'Online course?'=>(strpos(strtolower($row['course_name']),'(online)')!==false)?'1':'0',
		);

		$more_properties=array(
			'edit_date'=>strtotime($row['modification timestamp']),
		);

		$resourcefs->file_save($label,$path,_key_remap($properties)+$more_properties,'catalogue_entry','fm-courses/*');

		// Ensure a usergroup "Course name" (e.g. "Nutrition") exists. This is shared between anything with the same course name.
		$label=$course_name;
		$resourcefs_groups=get_resource_occlefs_object('group');
		$properties=array(
			'Course ID'=>'hyperclass',
		);
		$course_shared_group_filename=$resourcefs_groups->folder_save($label,'',_key_remap($properties),'group');
		$course_shared_group_id=$resourcefs_groups->convert_filename_to_id('group',$course_shared_group_filename);

		// Create another usergroup "<College name> - <Course name> - <Course description> - <Course ID>".
		$label=$row['company_name'].' - '.$course_name;
		if ($row['course_description']!='') $label.=' - '.$row['course_description'];
		$label.=' - '.$row['kp_college_course_id'];
		$resourcefs_groups=get_resource_occlefs_object('group');
		$properties=array(
			'Course ID'=>$row['kp_college_course_id'],
		);
		$course_group_filename=$resourcefs_groups->folder_save($label,'',_key_remap($properties),'group');
		$course_group_id=$resourcefs_groups->convert_filename_to_id('group',$course_group_filename);

		// Create a new forum for each unique "Course name", with the "Course name" being the name of the forum. This is shared between anything with the same course name. Assign permissions.
		$resourcefs_forums=get_resource_occlefs_object('forum');
		$resourcefs_forum_groupings=get_resource_occlefs_object('forum_grouping');
		$root_forum_filename=$resourcefs_forums->convert_id_to_filename('forum',strval(db_get_first_id()));
		$label=$course_name;
		$properties=array(
			'forum_grouping_id'=>convert_label_to_id('Students','','forum_grouping')
		);
		$course_forum_filename=$resourcefs_forums->folder_save($label,$root_forum_filename,_key_remap($properties),'forum');
		$resourcefs_forums->set_resource_access($course_forum_filename,array($course_group_id,$course_shared_group_id)); // NB: No reset call, so it will be added on for each course group
		$resourcefs_forums->set_resource_privileges_from_preset($course_forum_filename,array($course_group_id=>4,$course_shared_group_id=>4));

		// Create a subforum that is not shared
		/*$label=$row['kp_college_course_id'];
		$resourcefs_forums=get_resource_occlefs_object('forum');
		$properties=array(
			'forum_grouping_id'=>convert_label_to_id('Students','','forum_grouping')
		);
		$course_subforum_filename=$resourcefs_forums->folder_save($label,$root_forum_filename.'/'.$course_forum_filename,_key_remap($properties),'forum');
		$resourcefs_forums->reset_resource_access($course_forum_filename);
		$resourcefs_forums->set_resource_access($course_forum_filename,array($course_group_id));
		$resourcefs_forums->reset_resource_privileges($course_forum_filename);
		$resourcefs_forums->set_resource_privileges_from_preset($course_forum_filename,array($course_group_id=>4));*/

		// Add category structure within other catalogues
		foreach (array('fn-lectures','fn-enrolments','fn-schedules') as $catalogue_name)
		{
			$path=$catalogue_name.'/'.get_catalogue_root_filename($resourcefs,$catalogue_name);

			$label=$row['course_name'];
			if ($row['course_description']!='') $label.=' - '.$row['course_description'];
			$label.=' - '.$row['kp_college_course_id'];
			// ^^ NB: If the course name or description is changed, new categories will be made -- but we will automatically end up moving entries into the new categories and cleaning up the old ones

			$properties=array();
			$properties['custom__'.fix_id('Course ID')]=$row['kp_college_course_id'];

			$more_properties=array(
				'edit_date'=>strtotime($row['modification timestamp']),
			);

			$new_cat=$resourcefs->folder_save($label,$path,$properties+$more_properties,'catalogue_category');
			switch ($catalogue_name)
			{
				case 'fn-lectures':
					$lecture_course_cats[$row['kp_college_course_id']]=$new_cat;
					break;
				case 'fn-enrolments':
					$enrolment_course_cats[$row['kp_college_course_id']]=$new_cat;
					break;
				case 'fn-schedules':
					$schedule_course_cats[$row['kp_college_course_id']]=$new_cat;
					break;
			}

			// Assign view permissions to the catalogue category appropriately
			if (($catalogue_name=='fn-lectures') || ($catalogue_name=='fn-schedules'))
			{
				$resourcefs->reset_resource_access($new_cat);
				$resourcefs->set_resource_access($new_cat,array($course_group_id));
			}
		}
	}

	// Lectures
	$root_lecture_cat=get_catalogue_root_filename($resourcefs,'fm-lectures');
	$sql='SELECT * FROM college_course_schedule';
	if (!is_null($since))
		$sql.=' WHERE `modification timestamp`>=\''.date('Y-m-d H:i:s',$since).'\'';
	$sth=$dbh->query($sql);
	while (($row=$sth->fetch(PDO::FETCH_ASSOC))!==false)
	{
		if ((is_null($row['kp_college_course_schedule_id'])) || ($row['kp_college_course_schedule_id']==''))
		{
			resourcefs_logging('Blank/NULL kp_college_course_schedule_id found','warn');
			continue;
		}

		if ((is_null($row['lecture_subject'])) || ($row['lecture_subject']==''))
		{
			resourcefs_logging('Blank/NULL lecture_subject found','warn');
			continue;
		}

		$course_id=convert_label_to_id($row['k_college_course_id'],'fm-courses/'.$root_course_cat.'/*','catalogue_entry',true);
		if (is_null($course_id))
		{
			resourcefs_logging('Missing course ID for enrolment ('.$row['k_college_course_id'].')','warn');
			continue;
		}

		if (!isset($lecture_course_cats[$row['k_college_course_id']]))
		{
			$cat_id=$GLOBALS['SITE_DB']->query_select_value('catalogue_categories c JOIN catalogue_entry_linkage l ON c.id=l.content_id JOIN '.get_table_prefix().'catalogue_efv_short x ON x.ce_id=l.catalogue_entry_id','c.id',array('x.cv_value'=>$row['k_college_course_id'],'x.cf_id'=>find_custom_field_id('catalogue_category','Course ID'),'l.content_type'=>'_catalogue_category','c.c_name'=>'fm-colleges'));
			$lecture_course_cats[$row['k_college_course_id']]=$resourcefs->convert_id_to_filename('catalogue_category',$cat_id);
		}

		$path='fm-lectures/'.$root_lecture_cat.'/'.$lecture_course_cats[$row['k_college_course_code']];

		$label=$row['kp_college_course_schedule_id'];

		$properties=array(
			'Lecture name'=>$row['lecture_subject'],
			'Lecture date'=>is_null($row['schedule_date'])?'':date('Y-m-d H:i:s',strtotime($row['schedule_date'])),
			'Course ID'=>$course_id,
			'Lecturer'=>$row['lecturer'],
			'Room'=>$row['college_room'],
		);

		$more_properties=array(
			'edit_date'=>strtotime($row['modification timestamp']),
		);

		$resourcefs->file_save($label,$path,_key_remap($properties)+$more_properties,'catalogue_entry','fm-lectures/*');
	}

	$cat_course_id_field=$GLOBALS['SITE_DB']->query_select_value('catalogue_fields f JOIN '.get_table_prefix().'translate t ON t.id=f.cf_name','f.id',array('f.c_name'=>'_catalogue_category','t.text_original'=>'Course ID'));

	// Enrolments
	$root_enrolment_cat=get_catalogue_root_filename($resourcefs,'fm-enrolments');
	$sql='SELECT * FROM cnm_colleges';
	if (!is_null($since))
		$sql.=' WHERE `modification timestamp`>=\''.date('Y-m-d H:i:s',$since).'\'';
	$sth=$dbh->query($sql);
	while (($row=$sth->fetch(PDO::FETCH_ASSOC))!==false)
	{
		if ((is_null($row['kp_student_course_id'])) || ($row['kp_student_course_id']==''))
		{
			resourcefs_logging('Blank/NULL kp_student_course_id found','warn');
			continue;
		}

		$course_id=convert_label_to_id($row['k_college_course_code'],'fm-courses/'.$root_course_cat.'/*','catalogue_entry',true);
		if (is_null($course_id))
		{
			resourcefs_logging('Missing course ID for enrolment ('.$row['k_college_course_code'].')','warn');
			continue;
		}

		if ((is_null($row['student_id'])) || ($row['student_id']==''))
		{
			resourcefs_logging('Blank/NULL student_id found for an enrolment','warn');
			continue;
		}

		if (!isset($enrolment_course_cats[$row['k_college_course_code']]))
		{
			$cat_id=$GLOBALS['SITE_DB']->query_select_value('catalogue_categories c JOIN catalogue_entry_linkage l ON c.id=l.content_id JOIN '.get_table_prefix().'catalogue_efv_short x ON x.ce_id=l.catalogue_entry_id','c.id',array('x.cv_value'=>$row['k_college_course_code'],'x.cf_id'=>find_custom_field_id('catalogue_category','Course ID'),'l.content_type'=>'_catalogue_category','c.c_name'=>'fm-enrolments'));
			$enrolment_course_cats[$row['k_college_course_code']]=$resourcefs->convert_id_to_filename('catalogue_category',$cat_id);
		}

		$path='fm-enrolments/'.$root_enrolment_cat.'/'.$enrolment_course_cats[$row['k_college_course_code']];

		$label=$row['kp_student_course_id'];

		$properties=array(
			'Student ID'=>$row['student_id'],
			'Course ID'=>$course_id,
			'Status'=>$row['course_completed'],
		);

		$more_properties=array(
			'edit_date'=>strtotime($row['modification timestamp']),
		);

		$resourcefs->file_save($label,$path,_key_remap($properties)+$more_properties,'catalogue_entry','fm-enrolments/*');
	}

	// Schedules
	$root_schedule_cat=get_catalogue_root_filename($resourcefs,'fm-schedules');
	$sql='SELECT * FROM cnm_colleges';
	if (!is_null($since))
		$sql.=' WHERE modification_date>=\''.date('Y-m-d H:i:s',$since).'\'';
	$sth=$dbh->query($sql);
	while (($row=$sth->fetch(PDO::FETCH_ASSOC))!==false)
	{
		if ((is_null($row['kp_student_course_attendance_id'])) || ($row['kp_student_course_attendance_id']==''))
		{
			resourcefs_logging('Blank/NULL kp_student_course_attendance_id found','warn');
			continue;
		}

		$course_id=convert_label_to_id($row['k_college_course_id'],'fm-courses/'.$root_course_cat.'/*','catalogue_entry',true);
		if (is_null($course_id))
		{
			resourcefs_logging('Missing course ID for schedule ('.$row['k_college_course_id'].')','warn');
			continue;
		}

		if ((is_null($row['contact_ref'])) || ($row['contact_ref']==''))
		{
			resourcefs_logging('Blank/NULL contact_ref found for a schedule','warn');
			continue;
		}

		$lecture_id=convert_label_to_id($row['kp_college_course_schedule_id'],'fm-lectures/'.$root_lecture_cat.'/*','catalogue_entry',true);
		if (is_null($lecture_id))
		{
			resourcefs_logging('Missing lecture ID for schedule ('.$row['kp_college_course_schedule_id'].')','warn');
			continue;
		}

		if (!isset($schedule_course_cats[$row['k_college_course_id']]))
		{
			$cat_id=$GLOBALS['SITE_DB']->query_select_value('catalogue_categories c JOIN catalogue_entry_linkage l ON c.id=l.content_id JOIN '.get_table_prefix().'catalogue_efv_short x ON x.ce_id=l.catalogue_entry_id','c.id',array('x.cv_value'=>$row['k_college_course_id'],'x.cf_id'=>find_custom_field_id('catalogue_category','Course ID'),'l.content_type'=>'_catalogue_category','c.c_name'=>'fm-schedules'));
			$schedule_course_cats[$row['k_college_course_id']]=$resourcefs->convert_id_to_filename('catalogue_category',$cat_id);
		}

		$path='fm-schedules/'.$root_schedule_cat.'/'.$schedule_course_cats[$row['k_college_course_code']];;

		$label=$row['kp_student_course_attendance_id'];

		$properties=array(
			'Lecture ID'=>$lecture_id,
			'Lecture date'=>is_null($row['scheduled_date'])?'':date('Y-m-d H:i:s',strtotime($row['scheduled_date'])),
			'Course ID'=>$course_id,
			'Student ID'=>$row['contact_ref'],
		);

		$more_properties=array(
			'edit_date'=>strtotime($row['modification_date']),
		);

		$resourcefs->file_save($label,$path,_key_remap($properties)+$more_properties,'catalogue_entry','fm-schedules/*');
	}


	// Delete empty categories
	require_code('catalogues2');
	$sql='SELECT id FROM '.get_table_prefix().'catalogue_categories c WHERE NOT EXISTS (SELECT id FROM '.get_table_prefix().'catalogue_entries e WHERE e.cc_id=c.id) AND NOT EXISTS (SELECT id FROM '.get_table_prefix().'catalogue_categories cc WHERE cc.cc_parent_id=c.id)';
	$sql.=' AND c_name IN (\'fm-colleges\')';
	$GLOBALS['SITE_DB']->query($sql);
	actual_delete_catalogue_category($row['id']);
}

function _key_remap($properties)
{
	$_properties=array();
	foreach ($properties as $key=>$val)
	{
		$_properties[fix_id($key)]=$val;
	}
	return $_properties;
}

function get_catalogue_root_filename($resourcefs,$catalogue_name)
{
	static $cache=array();
	if (isset($cache[$catalogue_name])) return $cache[$catalogue_name];
	$root_category_id=$GLOBALS['SITE_DB']->query_select_value('catalogue_categories','MIN(id)',array('cc_parent_id'=>NULL,'c_name'=>$catalogue_name));
	$ret=$resourcefs->convert_id_to_filename('catalogue_category',strval($root_category_id));
	$cache[$catalogue_name]=$ret;
	return $ret;
}

function find_custom_field_id($type,$name)
{
	return $GLOBALS['SITE_DB']->query_select_value('catalogue_fields f JOIN '.get_table_prefix().'translate t ON t.id=f.cf_name','f.id',array('t.text_original'=>$name,'f.c_name'=>'_'.$type));
}