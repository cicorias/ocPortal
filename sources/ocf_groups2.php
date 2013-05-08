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
 * @package		core_ocf
 */

/**
 * Get a count of members in a (or more full details if $non_validated is true).
 *
 * @param  GROUP		The ID of the group.
 * @param  boolean	Whether to include those in the as a primary member.
 * @param  boolean	Whether to include those applied to join the, but not validated in.
 * @param  boolean	Whether to include those in the as a secondary member.
 * @param  boolean	Whether to include those members who are not validated as site members at all yet (parameter currently ignored).
 * @return integer	The count.
 */
function ocf_get_group_members_raw_count($group_id,$include_primaries=true,$non_validated=false,$include_secondaries=true,$include_unvalidated_members=true)
{
	// Find for conventional members
	$where=array('gm_group_id'=>$group_id);
	if (!$non_validated) $where['gm_validated']=1;
	$a=$GLOBALS['FORUM_DB']->query_value('f_group_members','COUNT(*)',$where,'ORDER BY gm_member_id');
	if ($include_primaries)
	{
		$map=array('m_primary_group'=>$group_id);
		if (!$include_unvalidated_members)
		{
			//$map['m_validated_confirm_code']='';
			$map['m_validated']=1;
		}
		$b=$GLOBALS['FORUM_DB']->query_value('f_members','COUNT(*)',$map);
	} else $b=0;

	// Now implicit usergroup hooks
	if ($include_secondaries)
	{
		$hooks=find_all_hooks('systems','ocf_implicit_usergroups');
		foreach (array_keys($hooks) as $hook)
		{
			require_code('hooks/systems/ocf_implicit_usergroups/'.$hook);
			$ob=object_factory('Hook_implicit_usergroups_'.$hook);
			if ($ob->get_bound_group_id()==$group_id)
			{
				$c=$ob->get_member_list_count();
				if (!is_null($c))
					$a+=$c;
			}
		}
	}

	// Find for LDAP members
	global $LDAP_CONNECTION;
	if (!is_null($LDAP_CONNECTION))
	{
		$members=array();
		ocf_get_group_members_raw_ldap($members,$group_id,$include_primaries,$non_validated,$include_secondaries);
		$c=count($members);
	} else $c=0;

	return $a+$b+$c;
}

/**
 * Get a list of members in a (or more full details if $non_validated is true).
 *
 * @param  GROUP		The ID of the group.
 * @param  boolean	Whether to include those in the as a primary member.
 * @param  boolean	Whether to include those applied to join the, but not validated in (also causes it to return maps that contain this info).
 * @param  boolean	Whether to include those in the as a secondary member.
 * @param  boolean	Whether to include those members who are not validated as site members at all yet (parameter currently ignored).
 * @param  ?integer	Return up to this many entries for primary members and this many entries for secondary members and all LDAP members (NULL: no limit, only use no limit if querying very restricted usergroups!)
 * @param  integer	Return primary members after this offset and secondary members after this offset
 * @return array		The list.
 */
function ocf_get_group_members_raw($group_id,$include_primaries=true,$non_validated=false,$include_secondaries=true,$include_unvalidated_members=true,$max=NULL,$start=0)
{
	// Find for conventional members
	$where=array('gm_group_id'=>$group_id);
	if (!$non_validated) $where['gm_validated']=1;
	$_members=$GLOBALS['FORUM_DB']->query_select('f_group_members',array('gm_member_id','gm_validated'),$where,'ORDER BY gm_member_id',$max,$start);
	$members=array();
	if ($include_secondaries)
	{
		foreach ($_members as $member)
		{
			$members[]=$non_validated?$member:$member['gm_member_id'];
		}
	}
	if ($include_primaries)
	{
		$map=array('m_primary_group'=>$group_id);
		if (!$include_unvalidated_members)
		{
			//$map['m_validated_confirm_code']='';
			$map['m_validated']=1;
		}
		$_members2=$GLOBALS['FORUM_DB']->query_select('f_members',array('id','m_username'),$map,'',$max,$start);
		foreach ($_members2 as $member)
		{
			$members[]=$non_validated?array('gm_member_id'=>$member['id'],'gm_validated'=>1,'m_username'=>$member['m_username']):$member['id'];
		}
	}

	// Now implicit usergroup hooks
	if ($include_secondaries)
	{
		$hooks=find_all_hooks('systems','ocf_implicit_usergroups');
		foreach (array_keys($hooks) as $hook)
		{
			require_code('hooks/systems/ocf_implicit_usergroups/'.$hook);
			$ob=object_factory('Hook_implicit_usergroups_'.$hook);
			if ($ob->get_bound_group_id()==$group_id)
			{
				$c=$ob->get_member_list();
				if (!is_null($c))
				{
					foreach ($c as $member_id=>$member_row)
					{
						$members[]=$non_validated?array('gm_member_id'=>$member_id,'gm_validated'=>1,'m_username'=>$member_row['m_username']):$member_id;
					}
				}
			}
		}
	}

	// Find for LDAP members
	global $LDAP_CONNECTION;
	if (!is_null($LDAP_CONNECTION))
	{
		ocf_get_group_members_raw_ldap($members,$group_id,$include_primaries,$non_validated,$include_secondaries);
	}

	return $members;
}

