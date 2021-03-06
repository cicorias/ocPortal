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
 * @package		core
 */

class Hook_login_provider_httpauth
{
	/**
	 * Standard login provider hook.
	 *
	 * @param  ?MEMBER		Member ID already detected as logged in (NULL: none). May be a guest ID.
	 * @return ?MEMBER		Member ID now detected as logged in (NULL: none). May be a guest ID.
	 */
	function try_login($member)
	{
		// Various kinds of possible HTTP authentication
		// NB: We do even if we already have a session, as parts of the site may be HTTP-auth, and others not - so we need to let it work as an override
		if (get_forum_type()=='ocf')
		{
			if ((function_exists('apache_request_headers')) && (get_value('ntlm')==='1')) // Taken from http://www.wascou.org/wascou/Blogs/Xavier-GOULEY/Alternate-way-to-Kerberos-NTLM-auth-in-pure-PHP
			{
				$headers=apache_request_headers();
				if(!isset($headers['Authorization'])) // step 1
				{
					header("HTTP/1.1 401 Unauthorized"); // step 2
					header("WWW-Authenticate: NTLM");
					exit();
				}
				if(isset($headers['Authorization']) && substr($headers['Authorization'],0,5)=='NTLM ')
				{
					// step 3 to 6
					$chaine=$headers['Authorization'];
					$chaine=substr($chaine,5); // type1 message
					$chained64=base64_decode($chaine);
					if(ord($chained64[8])==1) // step 3
					{
						// check NTLM flag "0xb2",
						// offset 13 in type-1-message :
						if (ord($chained64[13])!=178)
						{
							warn_exit("Please use NTLM compatible browser");
						}
						$ret_auth="NTLMSSP";
						$ret_auth.=chr(0).chr(2).chr(0).chr(0);
						$ret_auth.=chr(0).chr(0).chr(0).chr(0);
						$ret_auth.=chr(0).chr(40).chr(0).chr(0);
						$ret_auth.=chr(0).chr(1).chr(130).chr(0);
						$ret_auth.=chr(0).chr(0).chr(2).chr(2);
						$ret_auth.=chr(2).chr(0).chr(0).chr(0);
						$ret_auth.=chr(0).chr(0).chr(0).chr(0);
						$ret_auth.=chr(0).chr(0).chr(0).chr(0).chr(0);

						$ret_auth64=base64_encode($ret_auth);
						$ret_auth64=trim($ret_auth64);
						header("HTTP/1.1 401 Unauthorized"); // step 4
						header("WWW-Authenticate: NTLM $ret_auth64" );
						exit();
					}
					elseif(ord($chained64[8]) == 3) // step 5
					{
						$lenght_domain=(ord($chained64[31])*256 + ord($chained64[30]));
						$offset_domain=(ord($chained64[33])*256 + ord($chained64[32]));
						$domain=substr($chained64, $offset_domain, $lenght_domain);
						$lenght_login=(ord($chained64[39])*256 + ord($chained64[38]));
						$offset_login=(ord($chained64[41])*256 + ord($chained64[40]));
						$login=substr($chained64, $offset_login, $lenght_login);
						$lenght_host=(ord($chained64[47])*256 + ord($chained64[46]));
						$offset_host=(ord($chained64[49])*256 + ord($chained64[48]));
						$host=substr($chained64, $offset_host, $lenght_host);
					}
				}
				$_SERVER['PHP_AUTH_USER']=strtolower(preg_replace("/(.)(.)/","$1",$login));
			} else
			{
				if (get_option('windows_auth_is_enabled',true)=='1')
				{
					// For Windows auth, we force this always. For httpauth on non-Windows we let the .htaccess file force this, if the webmaster wants it
					require_code('users_inactive_occasionals');
					force_httpauth();
				}

				if ((function_exists('apache_request_headers')) && (get_value('force_admin_auth')==='1') && ($GLOBALS['FORUM_DRIVER']->is_super_admin($GLOBALS['FORUM_DRIVER']->get_member_from_username($_SERVER['PHP_AUTH_USER']))))
				{
					$headers=apache_request_headers();
					if (!isset($headers['Authorization']))
					{
						header('Location: '.get_base_url().'admin_login/index.php');
						exit();
					}
				}
			}

			// Can we try to see if we're httpauth-bound instead?
			// Security note...
			// New httpauth users will be added as members. Don't edit this to make them be added as privileged members, because presence of PHP_AUTH_USER only guarantees an authentication if it passed though an appropriate .htaccess (which would have filtered bad authentications for us). We are ASSUMING here that this is the case and therefore this must not be a permissive thing (all useful modules should also be in a .htaccess or privilege protected zone to stop member spoofing)
			// As an alternative to the above, we will not allow httpauth to the welcome zone, as by convention, this is a place for visitors. If using httpauth, all other zones should have a relevant .htaccess.
			// We could store the password from the first login and authenticate against that: but we do not want to create a sync issue.
			// So to summarise, either:
			//  - Don't assign any special permissions to these kinds of members
			//  - or, lock off all zones with .htaccess other than root (and root has httpauth login denied)

			if ((array_key_exists('PHP_AUTH_USER',$_SERVER)) && (($member===NULL) || (is_guest($member))) && ((get_option('httpauth_is_enabled',true)=='1') || (get_option('windows_auth_is_enabled',true)=='1')))
			{
				require_code('users_inactive_occasionals');
				$member=try_httpauth_login();		
			}
		}

		return $member;
	}
}
