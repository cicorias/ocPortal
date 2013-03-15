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
 * @package		ocf_forum
 */

class Hook_attachments_ocf_post
{

	/**
	 * Standard modular run function for attachment hooks. They see if permission to an attachment of an ID relating to this content is present for the current member.
	 *
	 * @param  ID_TEXT		The ID
	 * @param  object			The database connection to check on
	 * @return boolean		Whether there is permission
	 */
	function run($id,$connection)
	{
		if (get_forum_type()!='ocf') return false; // Shouldn't be here, but maybe it's left over somehow
		require_code('ocf_forums');
		require_code('ocf_topics');
		$info=$GLOBALS['FORUM_DB']->query_select('f_posts',array('p_cache_forum_id','p_intended_solely_for','p_poster','p_topic_id'),array('id'=>intval($id)),'',1);
		if (!array_key_exists(0,$info)) return false;
		$forum_id=$info[0]['p_cache_forum_id'];
		$poster=$info[0]['p_poster'];
		$forum_id_parent=is_null($forum_id)?NULL:$GLOBALS['FORUM_DB']->query_value('f_forums','f_parent_forum',array('id'=>$forum_id));
		$forum_id_parent_parent=is_null($forum_id_parent)?NULL:$GLOBALS['FORUM_DB']->query_value('f_forums','f_parent_forum',array('id'=>$forum_id_parent));
		$intended_solely_for=$info[0]['p_intended_solely_for'];
		if ((!is_null($intended_solely_for)) && ($poster!=get_member()) && ($intended_solely_for!=get_member())) return false;
		if (is_null($forum_id))
		{
			$topic_info=$GLOBALS['FORUM_DB']->query_select('f_topics',array('t_pt_to','t_pt_from'),array('id'=>$info[0]['p_topic_id']),'',1);
			return (($topic_info[0]['t_pt_to']==get_member()) || ($topic_info[0]['t_pt_from']==get_member()) || (ocf_has_special_pt_access($info[0]['p_topic_id'])));
		}
		if (addon_installed('tickets'))
		{
			$tf=get_option('ticket_forum_name',true);
			if (!is_null($tf)) $forum2=$GLOBALS['FORUM_DRIVER']->forum_id_from_name($tf); else $forum2=NULL;
			if (($forum2===$forum_id) || ($forum2===$forum_id_parent) || ($forum2===$forum_id_parent_parent))
			{
				$title=$GLOBALS['FORUM_DB']->query_value('f_topics','t_cache_first_title',array('id'=>$info[0]['p_topic_id']));
				if (substr($title,0,strlen(strval(get_member()))+1)==strval(get_member()).'_') return true;
				require_lang('tickets');

				$description=$GLOBALS['FORUM_DB']->query_value('f_topics','t_description',array('id'=>$info[0]['p_topic_id']));
				if (substr($description,0,strlen(do_lang('SUPPORT_TICKET').': #'.strval(get_member()))+1)==do_lang('SUPPORT_TICKET').': #'.strval(get_member()).'_') return true;
			}
		}
		return (has_category_access(get_member(),'forums',strval($forum_id)));
	}

}


