<?php 
if(!defined('WP_UNINSTALL_PLUGIN'))
	exit();
	
/*-------------------------------------------------------------
 Name:      adrotate_plugin_uninstall

 Purpose:   Delete the entire database tables and remove the options on uninstall.
 Receive:   -none-
 Return:	-none-
 Since:		2.4.2
-------------------------------------------------------------*/
function adrotate_plugin_uninstall() {
	global $wpdb, $wp_roles;

	/* Changelog:
	// Nov 15 2010 - Moved function to work with WP's uninstall system, stripped out unnessesary code
	// Dec 13 2010 - Updated uninstaller to properly remove options for the new installer
	// Dec 18 2010 - Updated for tracker subscriptions
	*/

	// Drop MySQL Tables
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_groups`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_tracker`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_blocks`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_linkmeta`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_stats_cache`");

	// Delete Options
	delete_option('adrotate_config');
	delete_option('adrotate_notification_timer');
	delete_option('adrotate_crawlers');
	delete_option('adrotate_stats');
	delete_option('adrotate_roles');
	delete_option('adrotate_db_version');
//	delete_option('adrotate_tracker');

	// Clear out userroles
	remove_role('adrotate_clientstats');
	$wp_roles->remove_cap('administrator','adrotate_clients');
	$wp_roles->remove_cap('editor','adrotate_clients');
	$wp_roles->remove_cap('author','adrotate_clients');
	$wp_roles->remove_cap('contributor','adrotate_clients');
	$wp_roles->remove_cap('subscriber','adrotate_clients');
		
	// Delete cron schedules
	wp_clear_scheduled_hook('adrotate_ad_notification');
	wp_clear_scheduled_hook('adrotate_prepare_cache_statistics()');
}

adrotate_plugin_uninstall();
?>