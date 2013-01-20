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

/**
 * Cache Driver.
 * @package		core
 */
class eacceleratorcache
{
	/**
	 * (Plug-in replacement for memcache API) Get data from the persistent cache.
	 *
	 * @param  mixed			Key
	 * @param  ?TIME			Minimum timestamp that entries from the cache may hold (NULL: don't care)
	 * @return ?mixed			The data (NULL: not found / NULL entry)
	 */
	function get($key,$min_cache_date=NULL)
	{
		if (function_exists('eaccelerator_get'))
		{
			$data=eaccelerator_get($key);
		} elseif (function_exists('mmcache_get'))
		{
			$data=mmcache_get($key);
		}
		if (is_null($data)) return NULL;
		if ((!is_null($min_cache_date)) && ($data[0]<$min_cache_date)) return NULL;
		return unserialize($data[1]);
	}

	/**
	 * (Plug-in replacement for memcache API) Put data into the persistent cache.
	 *
	 * @param  mixed			Key
	 * @param  mixed			The data
	 * @param  integer		Various flags (parameter not used)
	 * @param  integer		The expiration time in seconds.
	 */
	function set($key,$data,$flags,$expire_secs)
	{
		// Update list of e-objects
		global $PERSISTENT_CACHE_OBJECTS_CACHE;
		if (!array_key_exists($key,$PERSISTENT_CACHE_OBJECTS_CACHE))
		{
			$PERSISTENT_CACHE_OBJECTS_CACHE[$key]=1;
			if (function_exists('eaccelerator_put'))
			{
				eaccelerator_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
			} elseif (function_exists('mmcache_put'))
			{
				mmcache_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
			}
		}

		if (function_exists('eaccelerator_put'))
		{
			eaccelerator_put($key,array(time(),serialize($data)),$expire_secs);
		} elseif (function_exists('mmcache_put'))
		{
			mmcache_put($key,array(time(),serialize($data)),$expire_secs);
		}
	}

	/**
	 * (Plug-in replacement for memcache API) Delete data from the persistent cache.
	 *
	 * @param  mixed			Key name
	 */
	function delete($key)
	{
		// Update list of e-objects
		global $PERSISTENT_CACHE_OBJECTS_CACHE;
		unset($PERSISTENT_CACHE_OBJECTS_CACHE[$key]);

		if (function_exists('eaccelerator_put'))
		{
			eaccelerator_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
		} elseif (function_exists('mmcache_put'))
		{
			mmcache_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
		}

		if (function_exists('eaccelerator_rm'))
		{
			eaccelerator_rm($key);
		} elseif (function_exists('mmcache_rm'))
		{
			mmcache_rm($key);
		}
	}

	/**
	 * (Plug-in replacement for memcache API) Remove all data from the persistent cache.
	 */
	function flush()
	{
		global $PERSISTENT_CACHE_OBJECTS_CACHE;
		$PERSISTENT_CACHE_OBJECTS_CACHE=array();
		if (function_exists('eaccelerator_rm'))
		{
			foreach (array_keys($PERSISTENT_CACHE_OBJECTS_CACHE) as $obkey)
			{
				eaccelerator_rm($obkey);
			}
		} elseif (function_exists('mmcache_rm'))
		{
			foreach (array_keys($PERSISTENT_CACHE_OBJECTS_CACHE) as $obkey)
			{
				mmcache_rm($obkey);
			}
		}
		if (function_exists('eaccelerator_put'))
		{
			eaccelerator_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
		} elseif (function_exists('mmcache_put'))
		{
			mmcache_put(get_file_base().'PERSISTENT_CACHE_OBJECTS',$PERSISTENT_CACHE_OBJECTS_CACHE,0);
		}
	}
}
