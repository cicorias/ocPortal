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
 * @package		search
 */

class Hook_realtime_rain_search
{

	/**
	 * Standard modular run function for realtime-rain hooks.
	 *
	 * @param  TIME			Start of time range.
	 * @param  TIME			End of time range.
	 * @return array			A list of template parameter sets for rendering a 'drop'.
	 */
	function run($from,$to)
	{
		$drops=array();

		if (has_actual_page_access(get_member(),'admin_stats'))
		{
			$rows=$GLOBALS['SITE_DB']->query('SELECT s_primary,s_member_id AS member_id,s_time AS timestamp FROM '.$GLOBALS['SITE_DB']->get_table_prefix().'searches_logged WHERE s_time BETWEEN '.strval($from).' AND '.strval($to));

			foreach ($rows as $row)
			{
				$timestamp=$row['timestamp'];
				$member_id=$row['member_id'];

				$drops[]=rain_get_special_icons(NULL,$timestamp)+array(
					'TYPE'=>'search',
					'FROM_MEMBER_ID'=>strval($member_id),
					'TO_MEMBER_ID'=>NULL,
					'TITLE'=>rain_truncate_for_title($row['s_primary']),
					'IMAGE'=>find_theme_image('bigicons/search'),
					'TIMESTAMP'=>strval($timestamp),
					'RELATIVE_TIMESTAMP'=>strval($timestamp-$from),
					'TICKER_TEXT'=>NULL,
					'URL'=>build_url(array('page'=>'search','type'=>'misc','content'=>$row['s_primary']),'_SEARCH'),
					'IS_POSITIVE'=>false,
					'IS_NEGATIVE'=>false,

					// These are for showing connections between drops. They are not discriminated, it's just three slots to give an ID code that may be seen as a commonality with other drops.
					'FROM_ID'=>'member_'.strval($member_id),
					'TO_ID'=>NULL,
					'GROUP_ID'=>'search_'.$row['s_primary'],
				);
			}
		}

		return $drops;
	}

}
