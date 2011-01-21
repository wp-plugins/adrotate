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
	// Jan 21 2011 - Added capability cleanup
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
	delete_option('adrotate_debug');

	// Clear out userroles
	remove_role('adrotate_advertiser');

	// Clear up capabilities from ALL users
	adrotate_remove_capability("adrotate_userstatistics");
	adrotate_remove_capability("adrotate_globalstatistics");
	adrotate_remove_capability("adrotate_ad_manage");
	adrotate_remove_capability("adrotate_ad_delete");
	adrotate_remove_capability("adrotate_group_manage");
	adrotate_remove_capability("adrotate_group_delete");
	adrotate_remove_capability("adrotate_block_manage");
	adrotate_remove_capability("adrotate_block_delete");
		
	// Delete cron schedules
	wp_clear_scheduled_hook('adrotate_ad_notification');
	wp_clear_scheduled_hook('adrotate_prepare_cache_statistics()');
}

adrotate_plugin_uninstall();
?>