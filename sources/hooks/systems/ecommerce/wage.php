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
 * @package		ecommerce
 */

class Hook_wage
{

	/**
	 * Function for administrators to pick an identifier (only used by admins, usually the identifier would be picked via some other means in the wider ocPortal codebase).
	 *
	 * @param  ID_TEXT		Product type code.
	 * @return ?tempcode		Input field in standard Tempcode format for fields (NULL: no identifier).
	 */
	function get_identifier_manual_field_inputter($type_code)
	{
		return NULL;
	}

	/**
	 * Get the products handled by this eCommerce hook.
    *
	 * IMPORTANT NOTE TO PROGRAMMERS: This function may depend only on the database, and not on get_member() or any GET/POST values.
    *  Such dependencies will break IPN, which works via a Guest and no dependable environment variables. It would also break manual transactions from the Admin Zone.
	 *
	 * @param  boolean	Whether to make sure the language for item_name is the site default language (crucial for when we read/go to third-party sales systems and use the item_name as a key).
	 * @return array		A map of product name to list of product details.
	 */
	function get_products($site_lang=false)
	{
		$products=array(
			'WAGE'=>array(PRODUCT_OTHER,'?','',array(),do_lang('CUSTOM_PRODUCT_WAGE',NULL,NULL,NULL,$site_lang?get_site_default_lang():user_lang())),
		);
		return $products;
	}

}


