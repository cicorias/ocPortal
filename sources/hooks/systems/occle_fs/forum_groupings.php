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
 * @package		ocf_forum
 */

require_code('resource_fs');

class Hook_occle_fs_forum_groupings extends resource_fs_base
{
	var $file_resource_type='forum_grouping';

	/**
	 * Standard modular function for seeing how many resources are. Useful for determining whether to do a full rebuild.
	 *
	 * @param  ID_TEXT		The resource type
	 * @return integer		How many resources there are
	 */
	function get_resources_count($resource_type)
	{
		return $GLOBALS['FORUM_DB']->query_select_value('f_forum_groupings','COUNT(*)');
	}

	/**
	 * Standard modular function for searching for a resource by label.
	 *
	 * @param  ID_TEXT		The resource type
	 * @param  LONG_TEXT		The resource label
	 * @return array			A list of resource IDs
	 */
	function find_resource_by_label($resource_type,$label)
	{
		$_ret=$GLOBALS['FORUM_DB']->query_select('f_forum_groupings',array('id'),array('c_title'=>$label));
		$ret=array();
		foreach ($_ret as $r)
		{
			$ret[]=strval($r['id']);
		}
		return $ret;
	}

	/**
	 * Whether the filesystem hook is active.
	 *
	 * @return boolean		Whether it is
	 */
	function _is_active()
	{
		return (get_forum_type()=='ocf') && (!is_ocf_satellite_site());
	}

	/**
	 * Standard modular introspection function.
	 *
	 * @return array			The properties available for the resource type
	 */
	function _enumerate_file_properties()
	{
		return array(
			'description'=>'LONG_TEXT',
			'expanded_by_default'=>'BINARY',
		);
	}

	/**
	 * Standard modular date fetch function for resource-fs hooks. Defined when getting an edit date is not easy.
	 *
	 * @param  array			Resource row (not full, but does contain the ID)
	 * @return ?TIME			The edit date or add date, whichever is higher (NULL: could not find one)
	 */
	function _get_file_edit_date($row)
	{
		$query='SELECT MAX(date_and_time) FROM '.get_table_prefix().'adminlogs WHERE '.db_string_equal_to('param_a',strval($row['id'])).' AND  ('.db_string_equal_to('the_type','ADD_FORUM_GROUPING').' OR '.db_string_equal_to('the_type','EDIT_FORUM_GROUPING').')';
		return $GLOBALS['SITE_DB']->query_value_if_there($query);
	}

	/**
	 * Standard modular add function for resource-fs hooks. Adds some resource with the given label and properties.
	 *
	 * @param  LONG_TEXT		Filename OR Resource label
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return ~ID_TEXT		The resource ID (false: error, could not create via these properties / here)
	 */
	function file_add($filename,$path,$properties)
	{
		list($properties,$label)=$this->_file_magic_filter($filename,$path,$properties);

		require_code('ocf_forums_action');

		$description=$this->_default_property_str($properties,'description');
		$expanded_by_default=$this->_default_property_int($properties,'expanded_by_default');

		$id=ocf_make_forum_grouping($label,$description,$expanded_by_default);
		return strval($id);
	}

	/**
	 * Standard modular load function for resource-fs hooks. Finds the properties for some resource.
	 *
	 * @param  SHORT_TEXT	Filename
	 * @param  string			The path (blank: root / not applicable)
	 * @return ~array			Details of the resource (false: error)
	 */
	function file_load($filename,$path)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		$rows=$GLOBALS['FORUM_DB']->query_select('f_forum_groupings',array('*'),array('id'=>intval($resource_id)),'',1);
		if (!array_key_exists(0,$rows)) return false;
		$row=$rows[0];

		return array(
			'label'=>$row['c_title'],
			'description'=>$row['c_description'],
			'expanded_by_default'=>$row['c_expanded_by_default'],
		);
	}

	/**
	 * Standard modular edit function for resource-fs hooks. Edits the resource to the given properties.
	 *
	 * @param  ID_TEXT		The filename
	 * @param  string			The path (blank: root / not applicable)
	 * @param  array			Properties (may be empty, properties given are open to interpretation by the hook but generally correspond to database fields)
	 * @return boolean		Success status
	 */
	function file_edit($filename,$path,$properties)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);
		list($properties,)=$this->_file_magic_filter($filename,$path,$properties);

		require_code('ocf_forums_action2');

		$label=$this->_default_property_str($properties,'label');
		$description=$this->_default_property_str($properties,'description');
		$expanded_by_default=$this->_default_property_int($properties,'expanded_by_default');

		ocf_edit_forum_grouping(intval($resource_id),$label,$description,$expanded_by_default);

		return true;
	}

	/**
	 * Standard modular delete function for resource-fs hooks. Deletes the resource.
	 *
	 * @param  ID_TEXT		The filename
	 * @param  string			The path (blank: root / not applicable)
	 * @return boolean		Success status
	 */
	function file_delete($filename,$path)
	{
		list($resource_type,$resource_id)=$this->file_convert_filename_to_id($filename);

		require_code('ocf_forums_action2');
		ocf_delete_forum_grouping(intval($resource_id));

		return true;
	}
}
