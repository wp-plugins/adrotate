<?php
/**
 * Changing things here breaks things!
 */

/**
 * This is the Broadstreet partner configuration file.
 */

if(!defined('BROADSTREET_PARTNER_NAME')) define('BROADSTREET_PARTNER_NAME', 'AJdGSolutions');
if(!defined('BROADSTREET_PARTNER_TYPE')) define('BROADSTREET_PARTNER_TYPE', 'wordpress');
if(!defined('BROADSTREET_PARTNER_PLUGIN')) define('BROADSTREET_PARTNER_PLUGIN', '/adrotate');
if(!defined('BROADSTREET_VENDOR_PATH')) define('BROADSTREET_VENDOR_PATH', '/library/broadstreet');
if(!defined('BROADSTREET_SHOW_CODE')) define('BROADSTREET_SHOW_CODE', false);
if(!defined('BROADSTREET_AD_TAG_SELECTOR')) define('BROADSTREET_AD_TAG_SELECTOR', '[name="adrotate_bannercode"]');
if(!defined('BROADSTREET_DEBUG')) define('BROADSTREET_DEBUG', false);

/**
 * *****************************************************************************
 * Database Area: Only use this if you plan to use database-back storage. If
 *  you're using Wordpress, you don't need this.
 * - Useful for load-balanced applications
 * - Persisting data between browser sessions
 * *****************************************************************************
 */

/* Use the database? */
if(!defined('BROADSTREET_USE_DATABASE')) define('BROADSTREET_USE_DATABASE', false);

/* Database config */
if(!defined('BROADSTREET_DB_HOST')) define('BROADSTREET_DB_HOST', '');
if(!defined('BROADSTREET_DB_NAME')) define('BROADSTREET_DB_NAME', '');
if(!defined('BROADSTREET_DB_USER')) define('BROADSTREET_DB_USER', '');
if(!defined('BROADSTREET_DB_PASS')) define('BROADSTREET_DB_PASS', '');
if(!defined('BROADSTREET_DB_TABLE')) define('BROADSTREET_DB_TABLE', 'bs_options');
?>