<?php /*

 ocPortal
 Copyright (c) ocProducts, 2004-2014

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license		http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright	ocProducts Ltd
 * @package		unit_testing
 */

/**
 * ocPortal test case class (unit testing).
 */
class google_appengine_test_set extends ocp_test_case
{
	function testPregConstraint()
	{
		require_code('files');
		require_code('files2');
		$files=get_directory_contents(get_file_base(),'',true);
		foreach ($files as $file)
		{
			if ((substr($file,-4)=='.php') && (!should_ignore_file($file,IGNORE_BUNDLED_VOLATILE | IGNORE_NONBUNDLED_SCATTERED | IGNORE_CUSTOM_DIR_CONTENTS)))
			{
				$contents=file_get_contents(get_file_base().'/'.$file);

				if (preg_match('#preg_(replace|replace_callback|match|match_all|grep|split)\(\'(.)[^\']*(?<!\\\\)\\2[^\']*e#',$contents)!=0)
				{
					$this->assertTrue(false,'regexp /e not allowed (in '.$file.')');
				}

				if ((strpos($contents,'\'PHP_SELF\'')!==false) && (basename($file)!='phpstub.php') && (basename($file)!='lost_password.php'))
					$this->assertTrue(false,'PHP_SELF does not work stably across platforms (in '.$file.')');

				if ((strpos($contents,'\'SCRIPT_FILENAME\'')!==false) && (basename($file)!='phpstub.php'))
					$this->assertTrue(false,'SCRIPT_FILENAME does not work stably across platforms (in '.$file.')');
			}
		}
	}

	// We must be under 1000 templates, due to a GAE limit
	function testAdviceConstraint()
	{
		$tpl_counts=array();
		$file_counts=array();
		$directory_counts=array();
		$hooks=find_all_hooks('systems','addon_registry');
		foreach (array_keys($hooks) as $hook)
		{
			if (in_array($hook,array(
				'installer',
				'devguide',
				'backup',
				'uninstaller',
				'ldap',
				'themewizard',
				'setupwizard',
				'stats',
				'import',
				'iotds', // TODO: This is here because it will be removed to custom
				'community_billboard', // TODO: This is here because it will be removed to custom
			))) continue;

			require_code('hooks/systems/addon_registry/'.$hook);
			$hook_ob=object_factory('Hook_addon_registry_'.$hook);

			$files=$hook_ob->get_file_list();
			$file_counts[$hook]=count($files);

			$tpl_count=0;
			foreach ($files as $file)
			{
				if (substr($file,-4)=='.tpl') $tpl_count++;

				if (!isset($directory_counts[dirname($file)])) $directory_counts[dirname($file)]=0;
				$directory_counts[dirname($file)]++;

				$path=get_file_base().'/'.$file;
				if (is_file($path))
				{
					$this->assertTrue(filesize($path)<=32*1024*1024,'32MB is the maximum file size: '.$file.' is '.integer_format(filesize($path)));
				}
			}
			$tpl_counts[$hook]=$tpl_count;
		}

		$tpl_total=1;
		$file_total=100; // Just an arbitrary amount that we will assume are not in any particular addon
		foreach ($tpl_counts as $hook=>$tpl_count)
		{
			$tpl_total+=$tpl_count;
			$file_total+=$file_counts[$hook];
		}

		// Any large directories?
		foreach ($directory_counts as $dir=>$count)
		{
			if ($dir=='.') continue; // Templates/CSS usually, and we account for templates separately ; certainly not a lot of CSS or root files, in general (it'd get noticed ;-) )
			$this->assertTrue($count<=1000,'Must be less than 1000 files in any directory (except templates, which is checked separately): '.$dir);
		}

		// The user is advised they must take one big away and one small (or another big)
		$set_big=array(
			'calendar',
			'chat',
			'ecommerce',
			'shopping',
			'galleries',
			'pointstore',
		);
		$set_small=array(
			'authors',
			'banners',
			'downloads',
			'polls',
			'quizzes',
			'tickets',
			'newsletter',
			'wiki',
		);
		foreach ($set_big as $big)
		{
			foreach ($set_small as $small)
			{
				$custom_tpl_total=$tpl_total-$tpl_counts[$big]-$tpl_counts[$small];
				$custom_file_total=$file_total-$file_counts[$big]-$file_counts[$small];

				$this->assertTrue($custom_tpl_total<=1000,'Must be less than 1000 templates for given addon advice (removing unsupported and also '.$big.'&'.$small.')');

				$this->assertTrue($custom_file_total<=10000,'Must be less than 10000 files for given addon advice (removing unsupported and also '.$big.'&'.$small.')');
			}
		}
	}
}