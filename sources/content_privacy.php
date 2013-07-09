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
 * @package		content_privacy
 */

/**
 * Get the SQL extension clauses for implementing privacy.
 *
 * @param  ID_TEXT	The content type
 * @param  ID_TEXT	The table alias in the main query
 * @param  ?MEMBER	Viewing member to check privacy against (NULL: current member)
 * @param  string		Additional OR clause for letting the user through
 * @param  ?MEMBER	Member owning the content (NULL: do dynamically in query via content hook). Usually pass as NULL
 * @return array		A tuple: extra JOIN clause, extra WHERE clause, table clause (rarely used), direct table WHERE clause (rarely used)
 */
function get_privacy_where_clause($content_type,$table_alias,$viewing_member_id=NULL,$additional_or='',$submitter=NULL)
{
	if (is_null($viewing_member_id)) $viewing_member_id=get_member();

	if ($content_type[0]!='_')
	{
		require_code('content');
		require_code('hooks/systems/content_meta_aware/'.$content_type);
		$cma_ob=object_factory('Hook_content_meta_aware_'.$content_type);
		$cma_info=$cma_ob->info();

		if ((!isset($cma_info['supports_privacy'])) || (!$cma_info['supports_privacy'])) return array('','','','');

		$override_page=$cma_info['cms_page'];
		if (has_specific_permission($viewing_member_id,'view_private_content',$override_page)) return array('','','','');

		$join=' LEFT JOIN '.get_table_prefix().'content_privacy priv ON priv.content_id='.$table_alias.'.'.$cma_info['id_field'].' AND '.db_string_equal_to('priv.content_type',$content_type);
	} else
	{
		if (has_specific_permission($viewing_member_id,'view_private_content')) return array('','','','');

		$join='';
	}

	$where=' AND (';
	$where.='priv.content_id IS NULL';
	$where.=' OR priv.guest_view=1';
	if (!is_guest($viewing_member_id))
	{
		$where.=' OR priv.member_view=1';
		$where.=' OR priv.friend_view=1 AND EXISTS(SELECT * FROM '.get_table_prefix().'chat_buddies f WHERE f.member_liked='.(is_null($submitter)?($table_alias.'.'.$cma_info['submitter_field']):strval($submitter)).' AND f.member_likes='.strval($viewing_member_id).')';
		$where.=' OR '.(is_null($submitter)?($table_alias.'.'.$cma_info['submitter_field']):strval($submitter)).'='.strval($viewing_member_id);
		$where.=' OR EXISTS(SELECT * FROM '.get_table_prefix().'content_primary__members pm WHERE pm.member_id='.strval($viewing_member_id).' AND pm.content_id='.(is_null($submitter)?($table_alias.'.'.$cma_info['id_field']):strval($submitter)).' AND '.db_string_equal_to('pm.content_type',$content_type).')';
		if ($additional_or!='') $where.=' OR '.$additional_or;
	}
	$where.=')';

	$table=get_table_prefix().'content_privacy priv';

	$table_where=db_string_equal_to('priv.content_type',$content_type).$where;

	return array($join,$where,$table,$table_where);
}

/**
 * Check to see if some content may be viewed.
 *
 * @param  ID_TEXT	The content type
 * @param  ID_TEXT	The content ID
 * @param  ?MEMBER	Viewing member to check privacy against (NULL: current member)
 * @return boolean	Whether there is access
 */
function has_privacy_access($content_type,$content_id,$viewing_member_id=NULL)
{
	if (is_null($viewing_member_id)) $viewing_member_id=get_member();

	if ($content_type[0]=='_') // Special case, not tied to a content row
	{
		if (has_specific_permission($viewing_member_id,'view_private_content')) return true;

		list(,,$privacy_table,$privacy_where)=get_privacy_where_clause($content_type,'e',$viewing_member_id,'',intval($content_id));

		$query='SELECT * FROM '.$privacy_table.' WHERE '.$privacy_where.' AND '.db_string_equal_to('priv.content_id',$content_id);
		$results=$GLOBALS['SITE_DB']->query($query,1,NULL,false,true);

		if (array_key_exists(0,$results)) return true;
		if (is_null($GLOBALS['SITE_DB']->query_value_null_ok('content_privacy','content_id',array('content_type'=>$content_type,'content_id'=>$content_id)))) return true; // Maybe there was no privacy row, default to access on
		return false;
	}

	require_code('content');
	require_code('hooks/systems/content_meta_aware/'.$content_type);
	$cma_ob=object_factory('Hook_content_meta_aware_'.$content_type);
	$cma_info=$cma_ob->info();

	if ((!isset($cma_info['supports_privacy'])) || (!$cma_info['supports_privacy'])) return true;

	$override_page=$cma_info['cms_page'];
	if (has_specific_permission($viewing_member_id,'view_private_content',$override_page)) return true;

	list($privacy_join,$privacy_where)=get_privacy_where_clause($content_type,'e',$viewing_member_id);

	if ($cma_info['id_field_numeric'])
	{
		$where='e.'.$cma_info['id_field'].'='.strval(intval($content_id));
	} else
	{
		$where=db_string_equal_to('e.'.$cma_info['id_field'],$content_id);
	}
	$query='SELECT * FROM '.get_table_prefix().$cma_info['table'].' e'.$privacy_join.' WHERE '.$where.$privacy_where;
	$results=$GLOBALS['SITE_DB']->query($query,1);

	return array_key_exists(0,$results);
}

/**
 * Check to see if some content may be viewed. Exit with an access denied if not.
 *
 * @param  ID_TEXT	The content type
 * @param  ID_TEXT	The content ID
 * @param  ?MEMBER	Viewing member to check privacy against (NULL: current member)
 */
function check_privacy($content_type,$content_id,$viewing_member_id=NULL)
{
	if (!has_privacy_access($content_type,$content_id,$viewing_member_id))
	{
		require_lang('content_privacy');
		access_denied('PRIVACY_BREACH');
	}
}

/**
 * Find list of members who may view some content.
 *
 * @param  ID_TEXT	The content type
 * @param  ID_TEXT	The content ID
 * @param  boolean	Whether to get a full list including friends even when there are over a thousand friends
 * @return ?array		A list of member IDs that have access (NULL: no restrictions)
 */
function privacy_limits_for($content_type,$content_id,$strict_all=false)
{
	$rows=$GLOBALS['SITE_DB']->query_select('content_privacy',array('*'),array('content_type'=>$content_type,'content_id'=>$content_id),'',1);
	if (!array_key_exists(0,$rows)) return NULL;

	$row=$rows[0];

	if ($row['guest_view']==1) return NULL;
	if ($row['member_view']==1) return NULL;

	$members=array();

	require_code('content');
	list(,$content_submitter)=content_get_details($content_type,$content_id);

	$members[]=$content_submitter;

	if ($row['friend_view']==1)
	{
		$cnt=$GLOBALS['SITE_DB']->query_value('chat_buddies','COUNT(*)',array('chat_likes'=>$content_submitter));
		if (($strict_all) || ($cnt<=1000/*safety limit*/))
		{
			$friends=$GLOBALS['SITE_DB']->query_select('chat_buddies',array('chat_liked'),array('chat_likes'=>$content_submitter));
			$members=array_merge($members,collapse_1d_complexity('member_liked',$friends));
		}
	}

	$GLOBALS['SITE_DB']->query_select('content_primary__members',array('member_id'),array('content_type'=>$content_type,'content_id'=>$content_id));
	$members=array_merge($members,collapse_1d_complexity('member_id',$friends));

	return $members;
}
