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
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= "COLLATE $wpdb->collate";
	}

	// ---------------------------
	// Add tables if non existant
	if(!adrotate_mysql_table_exists($tables[0])) { // wp_adrotate
		$add1 = "CREATE TABLE `".$tables[0]."` (
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
		mysql_query($add1);
	}

	if(!adrotate_mysql_table_exists($tables[1])) { // wp_adrotate_groups
		$add2 = "CREATE TABLE `".$tables[1]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL default 'group',
				`fallback` varchar(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		mysql_query($add2);
	}

	if(!adrotate_mysql_table_exists($tables[2])) { // wp_adrotate_tracker
		$add3 = "CREATE TABLE `".$tables[2]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ipaddress` varchar(255) NOT NULL default '0',
				`timer` int(15) NOT NULL default '0',
				`bannerid` int(15) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		mysql_query($add3);
	}

	if(!adrotate_mysql_table_exists($tables[3])) { // wp_adrotate_blocks
		$add4 = "CREATE TABLE `".$tables[3]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL default 'Block',
				`adcount` int(3) NOT NULL default '1',
				`columns` int(3) NOT NULL default '1',
				`wrapper_before` longtext NOT NULL,
				`wrapper_after` longtext NOT NULL,
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		mysql_query($add4);
	}
	
	if(!adrotate_mysql_table_exists($tables[4])) { // wp_adrotate_linkmeta
		$add5 = "CREATE TABLE `".$tables[4]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ad` int(5) NOT NULL default '0',
				`group` int(5) NOT NULL default '0',
				`block` int(5) NOT NULL default '0',
				`user` int(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		mysql_query($add5);
	}
	
	if(!adrotate_mysql_table_exists($tables[5])) { // wp_adrotate_stats_cache
		$add6 = "CREATE TABLE `".$tables[5]."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`user` int(5) NOT NULL default '0',
				`cache` longtext NOT NULL,
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		mysql_query($add6);
	}
	
	// END Add
	// ---------------------------

	// ---------------------------
	// Upgrade database if required
	if(adrotate_mysql_table_exists($tables[0])) {

		// Migrate group data - Nov 12 2010 - To accomodate version 3.0
		$banners = $wpdb->get_results("SELECT `id`, `group` FROM ".$tables[0]." ORDER BY `id` ASC;");
		foreach($banners as $banner) {
			$count = $wpdb->get_var("SELECT COUNT(*) FROM ".$tables[4]." WHERE `ad` = ".$banner->id." ORDER BY `id` ASC;");
			if($count == 0) {
   				$wpdb->query("INSERT INTO `".$tables[4]."` (`ad`, `group`, `block`, `user`) VALUES (".$banner->id.", ".$banner->group.", 0, 0);");
   			}
		}
		
		adrotate_alter_table('add', $tables[0], 'startshow', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'active');
		adrotate_alter_table('add', $tables[0], 'endshow', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'startshow');
		adrotate_alter_table('add', $tables[0], 'link', '', 'LONGTEXT NOT NULL', 'image');
		adrotate_alter_table('add', $tables[0], 'tracker', '', 'VARCHAR( 5 ) NOT NULL DEFAULT \'N\'', 'link');
		adrotate_alter_table('add', $tables[0], 'clicks', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'tracker');
		adrotate_alter_table('add', $tables[0], 'maxclicks', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'clicks');
		adrotate_alter_table('add', $tables[0], 'shown', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'maxclicks');
		adrotate_alter_table('add', $tables[0], 'maxshown', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'shown');
		adrotate_alter_table('add', $tables[0], 'type', '', 'VARCHAR( 10 ) NOT NULL DEFAULT \'manual\'', 'maxshown');
		adrotate_alter_table('update_query', $tables[0], '', '', '`type` = \'manual\' WHERE `magic` = \'1\' AND `title` != \'\'', '');
		adrotate_alter_table('update_query', $tables[0], '', '', '`type` = \'manual\' WHERE `magic` = \'2\' AND `title` != \'\'', '');
		adrotate_alter_table('update_query', $tables[0], '', '', '`type` = \'empty\' WHERE `magic` = \'3\'', '');
		adrotate_alter_table('delete', $tables[0], 'magic', '', '', '');		
		adrotate_alter_table('delete', $tables[0], 'group', '', '', '');
	}

	if(adrotate_mysql_table_exists($tables[1])) {
		adrotate_alter_table('add', $tables[1], 'fallback', '', 'VARCHAR( 5 ) NOT NULL DEFAULT \'0\'', 'name');
	}

	if(adrotate_mysql_table_exists($tables[2])) {
		adrotate_alter_table('add', $tables[2], 'bannerid', '', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'timer');
		adrotate_alter_table('change', $tables[2], 'ipaddress', 'ipaddress', 'varchar(255) NOT NULL default \'0\'', '');
	}
	// END Upgrade
	// ---------------------------
	
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
 Name:      adrotate_alter_table

 Purpose:   Alter tables on demand, for upgrades
 Receive:   $action, $table, $column, $newcolumnname, $attributes, $after
 Return:	Boolean
 Since:		2.5
-------------------------------------------------------------*/
function adrotate_alter_table($action, $table, $column, $newcolumnname, $attributes, $after) {

    $exists = false;
    $columns = mysql_query("SHOW COLUMNS FROM $table");
    while($result = mysql_fetch_assoc($columns)) {
        if($result['Field'] == $column) {
            $exists = true;
            break;
        }
    }
    
	switch($action) {
		case "add" :
		    if(!$exists) {
				if(mysql_query("ALTER TABLE `$table` ADD `$column` $attributes AFTER `$after`;") === true) {
					return true;
				} else {
					adrotate_mysql_upgrade_error();
				}
			}
		break;
			
		case "change" :
			if(mysql_query("ALTER TABLE `$table` CHANGE `$column` `$newcolumnname` $attributes;") === true) {
				return true;
			} else {
				adrotate_mysql_upgrade_error();
			}
		break;
		
		case "delete" :
			if(mysql_query("ALTER TABLE `$table` DROP `$column`;") === true) {
				return true;
			} else {
				adrotate_mysql_upgrade_error();
			}
		break;
		
		case "update_query" :
			if(mysql_query("UPDATE `$table` SET $attributes;") === true) {
			return true;
			} else {
				adrotate_mysql_upgrade_error();
			}
		break;
    }
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
 Name:      adrotate_mysql_upgrade_error

 Purpose:   Error script
 Receive:   -none-
 Return:	-none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_mysql_upgrade_error() {
	mysql_error();
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_mysql_table_exists($tablename) {
	global $wpdb;

	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $tablename) {
			return true;
		}
	}
	return false;
}
?>