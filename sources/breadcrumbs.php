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
 * @package		breadcrumbs
 */

/**
 * Standard code module initialisation function.
 */
function init__breadcrumbs()
{
	define('REGEXP_CODENAME','[\w\_\-]*');
}

/**
 * Load all breadcrumb substitutions and return them.
 *
 * @param  string			The default breadcrumbs
 * @param  string			The breadcrumb XML data
 * @return array			The breadcrumb substitutions
 */
function load_breadcrumb_substitutions($current_breadcrumb,$data)
{
	global $BREADCRUMB_SUBSTITIONS;
	if ($BREADCRUMB_SUBSTITIONS===NULL)
	{
		$temp=new breadcrumb_substitution_loader();
		$temp->go($current_breadcrumb,$data);
		$BREADCRUMB_SUBSTITIONS=$temp->substitutions;
	}

	return $BREADCRUMB_SUBSTITIONS;
}

/**
 * Breadcrumb composition class.
 * @package		breadcrumbs
 */
class breadcrumb_substitution_loader
{
	// Used during parsing
	var $tag_stack,$attribute_stack,$text_so_far;
	var $substitution_current_match_key,$substitution_current_label,$links,$substitutions;
	var $current_breadcrumbs;
	var $breadcrumb_tpl;

	/**
	 * Run the loader, to load up field-restrictions from the XML file.
	 *
	 * @param  string			The default breadcrumbs
	 * @param  string			The breadcrumb XML data
	 */
	function go($current_breadcrumbs,$data)
	{
		$this->tag_stack=array();
		$this->attribute_stack=array();
		$this->substitution_current_match_key=NULL;
		$this->substitution_current_label=NULL;
		$this->links=array();
		$this->substitutions=array();
		$breadcrumb_tpl=do_template('BREADCRUMB_SEPARATOR');
		$this->breadcrumb_tpl=$breadcrumb_tpl->evaluate();
		$this->current_breadcrumbs=$current_breadcrumbs;

		// Create and setup our parser
		$xml_parser=@xml_parser_create();
		if ($xml_parser===false)
		{
			return; // PHP5 default build on windows comes with this function disabled, so we need to be able to escape on error
		}
		xml_set_object($xml_parser,$this);
		@xml_parser_set_option($xml_parser,XML_OPTION_TARGET_ENCODING,get_charset());
		@xml_parser_set_option($xml_parser,XML_OPTION_CASE_FOLDING,0);
		xml_set_element_handler($xml_parser,'startElement','endElement');
		xml_set_character_data_handler($xml_parser,'startText');

		// Run the parser
		if (@xml_parse($xml_parser,$data,true)==0)
		{
			attach_message('breadcrumbs.xml: '.xml_error_string(xml_get_error_code($xml_parser)),'warn');
			return;
		}
		@xml_parser_free($xml_parser);
	}

	/**
	 * Standard PHP XML parser function.
	 *
	 * @param  object			The parser object (same as 'this')
	 * @param  string			The name of the element found
	 * @param  array			Array of attributes of the element
	 */
	function startElement($parser,$tag,$_attributes)
	{
		unset($parser);

		array_push($this->tag_stack,$tag);
		$tag_attributes=array();
		foreach ($_attributes as $key=>$val)
		{
			$tag_attributes[$key]=$val;
		}
		array_push($this->attribute_stack,$tag_attributes);

		switch ($tag)
		{
			case 'substitution':
				$this->substitution_current_match_key=isset($tag_attributes['match_key'])?$tag_attributes['match_key']:'_WILD:_WILD';
				$this->substitution_current_label=isset($tag_attributes['label'])?$tag_attributes['label']:NULL;
				$this->links=array();
				break;
			case 'link':
				break;
		}
		$this->text_so_far='';
	}

	/**
	 * Standard PHP XML parser function.
	 *
	 * @param  object			The parser object (same as 'this')
	 */
	function endElement($parser)
	{
		$tag=array_pop($this->tag_stack);
		$tag_attributes=array_pop($this->attribute_stack);

		switch ($tag)
		{
			case 'substitution':
				list($zone,$attributes,$hash)=page_link_decode($this->substitution_current_match_key);
				if ($zone=='_WILD') $zone=REGEXP_CODENAME;
				if (!isset($attributes['page'])) $attributes['page']='';
				/*
				Commented for performance. This isn't user-data, so we're safe
				$zone=str_replace('~','\~',preg_quote($zone)); // We are using '~' as deliminators for the regexp, as the usual '/' and '#' feature in URL separators
				$hash=str_replace('~','\~',preg_quote($hash));
				*/
				if ($attributes['page']=='_WILD_NOT_START')
				{
					$zones=find_all_zones(false,true);
					if (isset($zones[$zone]))
						$attributes['page']='(?!'.$zones[$zone][3].')'.REGEXP_CODENAME;
					else
						$attributes['page']='_WILD';
				}
				if ($attributes['page']=='_WILD') $attributes['page']=REGEXP_CODENAME;
				foreach ($attributes as $key=>$val)
				{
					$attributes[$key]=/*Actually let's allow regexps so we can do binding str_replace('~','\~',preg_quote(*/$val/*))*/;
				}
				$_source_url=build_url($attributes,$zone,NULL,false,false,true,$hash);
				$source_url=$_source_url->evaluate();
				$source_url=str_replace('\\','/',$source_url); // Should not be needed, but can happen on misconfiguration and cause an error
				$source_url=urldecode(urldecode($source_url)); // urldecode because we don't want our regexp syntax mangled. Highly unlikely our sub's are going to really use special characters as parts of the URL
				if ((strpos($source_url,'.htm')===false) && (strpos($source_url,'.php')===false))
					$source_url.='(?:/index\.php)?';
				$source_url1= // this is kinda like preg_quote, but allows some regexp stuff through because we want to support some of it, without making it hard to write out URLs
					str_replace(
						array('.htm',	'?',	'(\?',	')\?',	'&',																							get_base_url().'/'.REGEXP_CODENAME.'/'),
						array('\.htm',	'\?',	'(?',		')?',		'(?:&[^<>]*)*&'/*Match-key like behaviour, allow extra URL clauses*/,	get_base_url().'/?'.REGEXP_CODENAME.'/'),
						$source_url
					)
					.
					'(?:[&\?][^<>]*)*';
				$escaped_source_url=escape_html($source_url);
				if ($source_url==$escaped_source_url) // optimisation
				{
					$source_url2=$source_url1;
				} else
				{
					$source_url2= // this is kinda like preg_quote, but allows some regexp stuff through because we want to support some of it, without making it hard to write out URLs
						str_replace(
							array('.htm',	'?',	'(\?',	')\?',	'&',																							get_base_url().'/'.REGEXP_CODENAME.'/'),
							array('\.htm',	'\?',	'(?',		')?',		'(?:&[^<>]*)*&'/*Match-key like behaviour, allow extra URL clauses*/,	get_base_url().'/?'.REGEXP_CODENAME.'/'),
							$escaped_source_url
						)
						.
						'(?:[&\?][^<>]*)*';
				}
				$from='^.*<a[^<>]*\shref="('.$source_url2.')"[^<>]*>(<abbr[^<>]*>)?([^<>]*)(</abbr>)?</a>';
				$regexp='#^'.$source_url1.'$#';
				$have_url_match=(preg_match($regexp,get_self_url(true))!=0); // we either bind rule via URL match, or finding it in the defined breadcrumb chain
				if ($have_url_match && (preg_match('~'.$from.'~Us',$this->current_breadcrumbs)==0))
				{ // Probably it's a non-link chain in the breadcrumbs, so try to bind to the <span> portion too (possibly nested)
					$from='^.*(<span>(<span[^<>]*>)?|<a[^<>]*\shref="('.$source_url2.')"[^<>]*>)(<abbr[^<>]*>)?([^<>]*)(</abbr>)?((</(span)>)?</(a|span)>)';
					$from_non_link=true;
				} else $from_non_link=false;
				$to='';
				foreach (array_reverse($this->links) as $link)
				{
					list($zone,$attributes,$hash)=page_link_decode($link[0]);
					$_target_url=build_url($attributes,$zone,NULL,false,false,false,$hash);
					$_link_title=($link[1]===NULL)?do_lang('UNKNOWN'):$link[1];
					$link_title=(preg_match('#\{\!|\{\?|\{\$|\[#',$_link_title)==0)?$_link_title:static_evaluate_tempcode(comcode_to_tempcode($_link_title));
					$_target_url=$_target_url->evaluate();
					$target_url=str_replace('\\','/',$_target_url); // Should not be needed, but can happen on misconfiguration and cause an error
					if ($target_url=='')
					{
						$to.=$link_title.$this->breadcrumb_tpl;
					} else
					{
						$to.='<a title="'.do_lang('GO_BACKWARDS_TO',escape_html(strip_tags($link_title))).'" href="'.escape_html($target_url).'">'.$link_title.'</a>'.$this->breadcrumb_tpl;
					}
				}
				$_target_url=$from_non_link?'${3}':'${1}';
				$existing_label=$from_non_link?'${5}':'${3}';
				$_link_title=($this->substitution_current_label===NULL)?$existing_label:$this->substitution_current_label;
				$link_title=(preg_match('#(\{\!)|(\{\?)|(\{\$)|(\[)#',$_link_title)==0)?$_link_title:static_evaluate_tempcode(comcode_to_tempcode($_link_title));
				if ($from_non_link)
				{
					$to.='${1}'.$link_title.'${7}';
				} else
				{
					$to.='<a title="'.do_lang('GO_BACKWARDS_TO',escape_html(strip_tags($link_title))).'" href="'.escape_html($_target_url).'">${2}'.$link_title.'${4}</a>';
				}
				$this->substitutions[$from]=$to;
				break;
			case 'link':
				$text=trim(str_replace('\n',chr(10),$this->text_so_far));
				$this->links[]=array($text,isset($tag_attributes['label'])?$tag_attributes['label']:NULL);
				break;
		}
	}

	/**
	 * Standard PHP XML parser function.
	 *
	 * @param  object			The parser object (same as 'this')
	 * @param  string			The text
	 */
	function startText($parser,$data)
	{
		unset($parser);

		$this->text_so_far.=$data;
	}

}

