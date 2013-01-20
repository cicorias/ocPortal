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
 * @package		core
 */

@ini_set('zlib.output_compression','On');

header("Pragma: public");
$time=400*60*60*24;
header('Cache-Control: maxage='.strval(time()+$time));
header('Expires: '.gmdate('D, d M Y H:i:s',time()+$time).' GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s',time()-$time).' GMT');

$since=isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])?$_SERVER['HTTP_IF_MODIFIED_SINCE']:'';
if ($since!='')
{
	header('HTTP/1.0 304 Not Modified');
	exit();
}

global $FILE_BASE,$SITE_INFO;

$domain=$_SERVER['HTTP_HOST'];
$colon_pos=strpos($domain,':');
if ($colon_pos!==false) $domain=substr($domain,0,$colon_pos);
$port=$_SERVER['SERVER_PORT'];
if (($port=='') || ($port=='80') || ($port=='443'))
{
	$base_url='http://'.$domain.str_replace('%2F','/',rawurlencode(preg_replace('#/'.preg_quote($GLOBALS['RELATIVE_PATH'],'#').'$#','',dirname($_SERVER['PHP_SELF']))));
} else
{
	@include($FILE_BASE.'/_config.php');
	if (array_key_exists('base_url',$SITE_INFO))
	{
		$base_url=$SITE_INFO['base_url'];
	} else
	{
		$base_url='http://'.$domain.':'.$port.str_replace('%2F','/',rawurlencode(preg_replace('#/'.preg_quote($GLOBALS['RELATIVE_PATH'],'#').'$#','',dirname($_SERVER['PHP_SELF']))));
	}
}

header('Content-type: text/html');
@ini_set('ocproducts.xss_detect','0');
exit(str_replace(array('{$BASE_URL}'),array($base_url),file_get_contents($FILE_BASE.'/themes/default/templates/QUICK_JS_LOADER.tpl')));
