<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)

This program is free software; you can redistribute it and/or modify it under the terms of 
the GNU General Public License, version 2, as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, visit: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*-------------------------------------------------------------
 Name:      adrotate_activate

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_activate() {
	global $wpdb, $wp_roles;

	// Install tables for AdRotate
	adrotate_database_install();

	// Upgrade tables for AdRotate where nessesary
	adrotate_database_upgrade();
	
	// Run a schedule for email notifications
	if (!wp_next_scheduled('adrotate_ad_notification')) wp_schedule_event(date('U'), '1day', 'adrotate_ad_notification');

	// Run a schedule for caches
	if (!wp_next_scheduled('adrotate_cache_statistics')) wp_schedule_event(date('U'), '6hour', 'adrotate_cache_statistics');

	// Create initial cache of statistics
	adrotate_prepare_cache_statistics();

	// Switch AdRotate roles on or off
	$roles = get_option('adrotate_roles');
	if($roles  = 1) {
		adrotate_add_roles();
	} else {
		update_option('adrotate_roles', '0');
	}
	
	// Sort out new default settings for version 3.0 (existing settings are lost)
	$config = get_option('adrotate_config');
	if (!isset($config['notification_email']) OR $config['notification_email'] == '') {
		delete_option('adrotate_config');
		adrotate_check_config();
	}

	// Attempt to make the banners/ folder
	if(!is_dir(ABSPATH.'/wp-content/banners')) {
		mkdir(ABSPATH.'/wp-content/banners', 0755);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_database_install

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_database_install() {
	global $wpdb, $wp_roles;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$tables = array(
		$wpdb->prefix . "adrotate",				// Since 0.1
		$wpdb->prefix . "adrotate_groups",		// Since 0.2
		$wpdb->prefix . "adrotate_tracker",		// Since 2.0
		$wpdb->prefix . "adrotate_blocks",		// Since 3.0
		$wpdb->prefix . "adrotate_linkmeta",	// Since 3.0
		$wpdb->prefix . "adrotate_stats_cache",	// Since 3.0
	);

	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[0]."'")) { // wp_adrotate
		$sql = "CREATE TABLE `".$tables[0]."` (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `title` longtext NOT NULL,
			  `bannercode` longtext NOT NULL,
			  `thetime` int(15) NOT NULL default '0',
			  `updated` int(15) NOT NULL,
			  `author` varchar(60) NOT NULL default '',
			  `active` varchar(4) NOT NULL default 'yes',
			  `startshow` int(15) NOT NULL default '0',
			  `endshow` int(15) NOT NULL default '0',
			  `image` varchar(255) NOT NULL,
			  `link` longtext NOT NULL,
			  `tracker` varchar(5) NOT NULL default 'N',
			  `clicks` int(15) NOT NULL default '0',
			  `maxclicks` int(15) NOT NULL default '0',
			  `shown` int(15) NOT NULL default '0',
			  `maxshown` int(15) NOT NULL default '0',			  
			  `type` varchar(10) NOT NULL default '0',
	  		PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[1]."'")) { // wp_adrotate_groups
		$sql = "CREATE TABLE `".$tables[1]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL default 'group',
				`fallback` varchar(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[2]."'")) { // wp_adrotate_tracker
		$sql = "CREATE TABLE `".$tables[2]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ipaddress` varchar(255) NOT NULL default '0',
				`timer` int(15) NOT NULL default '0',
				`bannerid` int(15) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[3]."'")) { // wp_adrotate_blocks
		$sql = "CREATE TABLE `".$tables[3]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL default 'Block',
				`adcount` int(3) NOT NULL default '1',
				`columns` int(3) NOT NULL default '1',
				`wrapper_before` longtext NOT NULL,
				`wrapper_after` longtext NOT NULL,
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}
	
	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[4]."'")) { // wp_adrotate_linkmeta
		$sql = "CREATE TABLE `".$tables[4]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ad` int(5) NOT NULL default '0',
				`group` int(5) NOT NULL default '0',
				`block` int(5) NOT NULL default '0',
				`user` int(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}
	
	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables[5]."'")) { // wp_adrotate_stats_cache
		$sql = "CREATE TABLE `".$tables[5]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`user` int(5) NOT NULL default '0',
				`cache` longtext NOT NULL,
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	// REMOVE IF FOR NEXT DB VERSION, ONLY THE ACTION IN ELSE IS REQUIRED ONCE DB CHANGES!
	if(!get_option("adrotate_db_version") AND ADROTATE_VERSION == 303) {
		add_option("adrotate_db_version", '0');
	} else {
		add_option("adrotate_db_version", ADROTATE_DB_VERSION);
	}
	// REMOVE IF FOR NEXT DB VERSION, ONLY THE ACTION IN ELSE IS REQUIRED ONCE DB CHANGES!
}

/*-------------------------------------------------------------
 Name:      adrotate_database_upgrade

 Purpose:   Upgrades database
 Receive:   -none-
 Return:	-none-
 Since:		3.1
-------------------------------------------------------------*/
function adrotate_database_upgrade() {
	global $wpdb;

	$saved_db_version = get_option("adrotate_db_version");
	
	$tables = array(
		$wpdb->prefix . "adrotate",				// Since 0.1
		$wpdb->prefix . "adrotate_groups",		// Since 0.2
		$wpdb->prefix . "adrotate_tracker",		// Since 2.0
		$wpdb->prefix . "adrotate_blocks",		// Since 3.0
		$wpdb->prefix . "adrotate_linkmeta",	// Since 3.0
		$wpdb->prefix . "adrotate_stats_cache",	// Since 3.0
	);

	// Migrate group data - Nov 12 2010 - To accomodate version 3.0 from earlier setups
	if(version_compare($saved_db_version, ADROTATE_DB_VERSION, '<')) {
		$banners = $wpdb->get_results("SELECT `id`, `group` FROM ".$tables[0]." ORDER BY `id` ASC;");
		foreach($banners as $banner) {
			$wpdb->query("INSERT INTO `".$tables[4]."` (`ad`, `group`, `block`, `user`) VALUES (".$banner->id.", ".$banner->group.", 0, 0);");
		}
	}
		
	// Any to 3.0
	if(version_compare($saved_db_version, ADROTATE_DB_VERSION, '<')) {
		adrotate_add_column($tables[0], 'startshow', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `active`');
		adrotate_add_column($tables[0], 'endshow', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `startshow`');
		adrotate_add_column($tables[0], 'link', 'LONGTEXT NOT NULL AFTER `image`');
		adrotate_add_column($tables[0], 'tracker', 'VARCHAR( 5 ) NOT NULL DEFAULT \'N\' AFTER `link`');
		adrotate_add_column($tables[0], 'clicks', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `tracker`');
		adrotate_add_column($tables[0], 'maxclicks', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `clicks`');
		adrotate_add_column($tables[0], 'shown', 'INT( 15 ) NOT NULL DEFAULT \'0\' `maxclicks`');
		adrotate_add_column($tables[0], 'maxshown', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `shown`');
		adrotate_add_column($tables[0], 'type', 'VARCHAR( 10 ) NOT NULL DEFAULT \'manual\' AFTER `maxshown`');
		
		adrotate_add_column($tables[1], 'fallback', 'VARCHAR( 5 ) NOT NULL DEFAULT \'0\' AFTER `name`');
		
		adrotate_add_column($tables[2], 'bannerid', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `timer`');
		
		$wpdb->query("ALTER TABLE `".$tables[2]."` CHANGE `ipaddress` `ipaddress` varchar(255) NOT NULL DEFAULT '0';");

		$wpdb->query("UPDATE `".$tables[0]."` SET `type` = 'manual' WHERE `magic` = '0' AND `title` != '';");
		$wpdb->query("UPDATE `".$tables[0]."` SET `type` = 'manual' WHERE `magic` = '1' AND `title` != '';");
		$wpdb->query("UPDATE `".$tables[0]."` SET `type` = 'empty' WHERE `magic` = '2';");

		$wpdb->query("ALTER TABLE `".$tables[0]."` DROP `magic`;");
		$wpdb->query("ALTER TABLE `".$tables[0]."` DROP `group`;");
	}
	
	update_option("adrotate_db_version", ADROTATE_DB_VERSION);
}

/*-------------------------------------------------------------
 Name:      adrotate_deactivate

 Purpose:   Deactivate script
 Receive:   -none-
 Return:	-none-
 Since:		2.0
-------------------------------------------------------------*/
function adrotate_deactivate() {
	global $wp_roles, $adrotate_roles;
	
	// Clear out roles
	if($adrotate_roles == 1) {
		adrotate_remove_roles();
	}

	// Clear out wp_cron
	wp_clear_scheduled_hook('adrotate_ad_notification');
	wp_clear_scheduled_hook('adrotate_cache_statistics');
}

/*-------------------------------------------------------------
 Name:      adrotate_add_column

 Purpose:   Check if the column exists in the table
 Receive:   $table_name, $column_name, $attributes
 Return:	Boolean
 Since:		3.0.3
-------------------------------------------------------------*/
function adrotate_add_column($table_name, $column_name, $attributes) {
	global $wpdb;
	
	foreach ($wpdb->get_col("SHOW COLUMNS FROM $table_name;") as $column ) {
		if ($column == $column_name) return true;
	}
	
	$wpdb->query("ALTER TABLE $table_name ADD $column_name " . $attributes.";");
	
	foreach ($wpdb->get_col("SHOW COLUMNS FROM $table_name;") as $column ) {
		if ($column == $column_name) return true;
	}
	
	echo("Could not add column $column_name in table $table_name<br />\n");
	return false;
}
?>