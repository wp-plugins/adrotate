<?php
/*  
Copyright 2010-2012 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_activate

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_activate() {
	global $wpdb, $wp_roles, $adrotate_roles;

	if (version_compare(PHP_VERSION, '5.2.0', '<')) { 
		deactivate_plugins(plugin_basename('adrotate.php'));
		wp_die('AdRotate 3.6 and up requires PHP 5.2 or higher.<br />You likely have PHP 4, which has been discontinued since december 31, 2007. Consider upgrading your server!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
		return; 
	} else {
		if(!current_user_can('activate_plugins')) {
			deactivate_plugins(plugin_basename('adrotate.php'));
			wp_die('You do not have appropriate access to activate this plugin! Contact your administrator!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
			return; 
		} else {
			// Install tables for AdRotate
			adrotate_database_install();
		
			// Set up some schedules
			if (!wp_next_scheduled('adrotate_ad_notification')) 
				// Ad notifications
				wp_schedule_event(date('U'), '1day', 'adrotate_ad_notification');
			if (!wp_next_scheduled('adrotate_clean_trackerdata')) 
				// Periodically clean trackerdata
				wp_schedule_event(date('U'), '3hour', 'adrotate_clean_trackerdata');
			if (!wp_next_scheduled('adrotate_clean_trackerdata')) 
				// Periodically clean trackerdata
				wp_schedule_event(date('U'), 'hourly', 'adrotate_evaluate_ads');
		
			// Set the capabilities for the administrator
			$role = get_role('administrator');		
			$role->add_cap("adrotate_advertiser");
			$role->add_cap("adrotate_global_report");
			$role->add_cap("adrotate_ad_manage");
			$role->add_cap("adrotate_ad_delete");
			$role->add_cap("adrotate_group_manage");
			$role->add_cap("adrotate_group_delete");
			$role->add_cap("adrotate_block_manage");
			$role->add_cap("adrotate_block_delete");
			$role->add_cap("adrotate_moderate");
			$role->add_cap("adrotate_moderate_approve");
		
			// Switch additional roles on or off
			if($adrotate_roles = 1) {
				// Remove old named roles
				adrotate_remove_roles();
				// Set or reset the roles
				adrotate_add_roles();
			} else {
				update_option('adrotate_roles', '0');
			}

			// Set default settings and values
			add_option('adrotate_db_timer', date('U'));
			add_option('adrotate_debug', array('general' => false, 'dashboard' => false, 'userroles' => false, 'userstats' => false, 'stats' => false));

			adrotate_check_config();
	
			// Attempt to make the wp-content/banners/ folder
			if(!is_dir(ABSPATH.'/wp-content/banners')) {
				mkdir(ABSPATH.'/wp-content/banners', 0755);
			}
			if(!is_dir(ABSPATH.'/wp-content/reports')) {
				mkdir(ABSPATH.'/wp-content/reports', 0755);
			}
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_database_install

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
 Since:		3.0.3
-------------------------------------------------------------*/
function adrotate_database_install() {
	global $wpdb;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$tables = adrotate_list_tables();

	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = " DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate']."'")) { // wp_adrotate
		$sql = "CREATE TABLE `".$tables['adrotate']."` (
			  	`id` mediumint(8) unsigned NOT NULL auto_increment,
			  	`title` longtext NOT NULL,
			  	`bannercode` longtext NOT NULL,
			  	`thetime` int(15) NOT NULL default '0',
				`updated` int(15) NOT NULL,
			  	`author` varchar(60) NOT NULL default '',
			  	`imagetype` varchar(9) NOT NULL,
			  	`image` varchar(255) NOT NULL,
			  	`link` longtext NOT NULL,
			  	`tracker` varchar(5) NOT NULL default 'N',
			  	`targetclicks` int(15) NOT NULL default '0',			  
			  	`targetimpressions` int(15) NOT NULL default '0',			  
			  	`timeframe` varchar(6) NOT NULL default '',
			  	`timeframelength` int(15) NOT NULL default '0',
			  	`timeframeclicks` int(15) NOT NULL default '0',
			  	`timeframeimpressions` int(15) NOT NULL default '0',
			  	`type` varchar(10) NOT NULL default '0',
			  	`weight` int(3) NOT NULL default '6',
				`sortorder` int(5) NOT NULL default '0',
	  		PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_groups']."'")) { // wp_adrotate_groups
		$sql = "CREATE TABLE `".$tables['adrotate_groups']."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL default 'group',
				`fallback` varchar(5) NOT NULL default '0',
				`sortorder` int(5) NOT NULL default '0',
				`cat` longtext NOT NULL,
				`cat_loc` tinyint(1) NOT NULL default '0',
				`page` longtext NOT NULL,
				`page_loc` tinyint(1) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_tracker']."'")) { // wp_adrotate_tracker
		$sql = "CREATE TABLE `".$tables['adrotate_tracker']."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ipaddress` varchar(255) NOT NULL default '0',
				`timer` int(15) NOT NULL default '0',
				`bannerid` int(15) NOT NULL default '0',
				`stat` char(1) NOT NULL default 'c',
				`useragent` mediumtext NOT NULL,
				PRIMARY KEY  (`id`),
				INDEX `ipaddress` (`ipaddress`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_blocks']."'")) { // wp_adrotate_blocks
		$sql = "CREATE TABLE `".$tables['adrotate_blocks']."` (
				  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
				  `name` varchar(255) NOT NULL DEFAULT 'Block',
				  `rows` int(3) NOT NULL DEFAULT '2',
				  `columns` int(3) NOT NULL DEFAULT '2',
				  `gridfloat` varchar(7) NOT NULL DEFAULT 'none',
				  `gridpadding` int(2) NOT NULL DEFAULT '0',
				  `gridborder` varchar(20) NOT NULL DEFAULT '0',
				  `adwidth` int(4) NOT NULL DEFAULT '125',
				  `adheight` int(4) NOT NULL DEFAULT '125',
				  `admargin` int(2) NOT NULL DEFAULT '1',
				  `adpadding` int(2) NOT NULL DEFAULT '0',
				  `adborder` varchar(20) NOT NULL DEFAULT '0',
				  `wrapper_before` longtext NOT NULL,
				  `wrapper_after` longtext NOT NULL,
				  `sortorder` int(5) NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`)
				) ".$charset_collate.";";
		dbDelta($sql);
	}
	
	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_linkmeta']."'")) { // wp_adrotate_linkmeta
		$sql = "CREATE TABLE `".$tables['adrotate_linkmeta']."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ad` int(5) NOT NULL default '0',
				`group` int(5) NOT NULL default '0',
				`block` int(5) NOT NULL default '0',
				`user` int(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_stats_tracker']."'")) { // wp_adrotate_stats_tracker
		$sql = "CREATE TABLE `".$tables['adrotate_stats_tracker']."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ad` int(5) NOT NULL default '0',
				`group` int(5) NOT NULL default '0',
				`block` int(5) NOT NULL default '0',
				`thetime` int(15) NOT NULL default '0',
				`clicks` int(15) NOT NULL default '0',
				`impressions` int(15) NOT NULL default '0',
				PRIMARY KEY  (`id`),
				INDEX `ad` (`ad`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_schedule']."'")) { // wp_adrotate_schedule
		$sql = "CREATE TABLE `".$tables['adrotate_schedule']."` (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ad` mediumint(8) NOT NULL default '0',
				`starttime` int(15) NOT NULL default '0',
				`stoptime` int(15) NOT NULL default '0',
				`maxclicks` int(15) NOT NULL default '0',
				`maximpressions` int(15) NOT NULL default '0',
				PRIMARY KEY  (`id`),
				INDEX `ad` (`ad`)
			) ".$charset_collate.";";
		dbDelta($sql);
	}

	add_option("adrotate_version", ADROTATE_VERSION);
	add_option("adrotate_db_version", ADROTATE_DB_VERSION);
}

/*-------------------------------------------------------------
 Name:      adrotate_database_upgrade

 Purpose:   Upgrades AdRotate where required
 Receive:   -none-
 Return:	-none-
 Since:		3.0.3
-------------------------------------------------------------*/
function adrotate_database_upgrade() {
	global $wpdb, $adrotate_db_version;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	if (version_compare(PHP_VERSION, '5.2.0', '<') == -1) { 
		deactivate_plugins(plugin_basename('adrotate.php'));
		wp_die('AdRotate 3.6 and up requires PHP 5.2 or higher.<br />You likely have PHP 4, which has been discontinued since december 31, 2007. Consider upgrading your server!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
		return; 
	} else {
		// Install tables for AdRotate where required
		adrotate_database_install();

		$tables = adrotate_list_tables();

		// Database: 	1
		if($adrotate_db_version < 1) {
			// Migrate group data to accomodate version 3.0 and up from earlier setups
			$banners = $wpdb->get_results("SELECT `id`, `group` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($banners as $banner) {
				$wpdb->query("INSERT INTO `".$tables['adrotate_linkmeta']."` (`ad`, `group`, `block`, `user`) VALUES (".$banner->id.", ".$banner->group.", 0, 0);");
			}
			unset($banners);
	
			adrotate_add_column($tables['adrotate'], 'startshow', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `active`');
			adrotate_add_column($tables['adrotate'], 'endshow', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `startshow`');
			adrotate_add_column($tables['adrotate'], 'link', 'LONGTEXT NOT NULL AFTER `image`');
			adrotate_add_column($tables['adrotate'], 'tracker', 'VARCHAR( 5 ) NOT NULL DEFAULT \'N\' AFTER `link`');
			adrotate_add_column($tables['adrotate'], 'clicks', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `tracker`');
			adrotate_add_column($tables['adrotate'], 'maxclicks', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `clicks`');
			adrotate_add_column($tables['adrotate'], 'shown', 'INT( 15 ) NOT NULL DEFAULT \'0\' `maxclicks`');
			adrotate_add_column($tables['adrotate'], 'maxshown', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `shown`');
			adrotate_add_column($tables['adrotate'], 'type', 'VARCHAR( 10 ) NOT NULL DEFAULT \'manual\' AFTER `maxshown`');
			
			adrotate_add_column($tables['adrotate_groups'], 'fallback', 'VARCHAR( 5 ) NOT NULL DEFAULT \'0\' AFTER `name`');
			
			adrotate_add_column($tables['adrotate_tracker'], 'bannerid', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `timer`');
			
			$wpdb->query("ALTER TABLE `".$tables['adrotate_tracker']."` CHANGE `ipaddress` `ipaddress` varchar(255) NOT NULL DEFAULT '0';");
	
			$wpdb->query("UPDATE `".$tables['adrotate']."` SET `type` = 'manual' WHERE `magic` = '0' AND `title` != '';");
			$wpdb->query("UPDATE `".$tables['adrotate']."` SET `type` = 'manual' WHERE `magic` = '1' AND `title` != '';");
			$wpdb->query("UPDATE `".$tables['adrotate']."` SET `type` = 'empty' WHERE `magic` = '2';");
	
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `magic`;");
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `group`;");
		}
	
		// Database: 	3
		if($adrotate_db_version < 3) {
			adrotate_add_column($tables['adrotate'], 'weight', 'INT( 3 ) NOT NULL DEFAULT \'6\' AFTER `type`');
		}
		
		// Database: 	5
		if($adrotate_db_version < 5) {
			$today = mktime(0, 0, 0, gmdate("m"), gmdate("d"), gmdate("Y"));
			// Migrate current statistics to accomodate version 3.5s new stats system
			$ads = $wpdb->get_results("SELECT `id`, `clicks`, `shown` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($ads as $ad) {
				$wpdb->query("INSERT INTO `".$tables['adrotate_stats_tracker']."` (`ad`, `thetime`, `clicks`, `impressions`) VALUES (".$ad->id.", ".$today.", ".$ad->clicks.", ".$ad->shown.");");
			}
			unset($ads);

			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `clicks`;");
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `shown`;");
		}
		
		// Database: 	6
		if($adrotate_db_version < 6) {
			$wpdb->query("DROP TABLE `".$tables['adrotate_stats_cache']."`;");
		}
		
		// Database: 	7
		if($adrotate_db_version < 7) {
			adrotate_add_column($tables['adrotate'], 'targetclicks', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `maxshown`');
			adrotate_add_column($tables['adrotate'], 'targetimpressions', 'INT( 15 ) NOT NULL DEFAULT \'0\' AFTER `targetclicks`');
		}
		
		// Database: 	8
		if($adrotate_db_version < 8) {
			// Convert image data to accomodate version 3.6 and up from earlier setups
			$images = $wpdb->get_results("SELECT `id`, `image` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($images as $image) {
				if(strlen($image->image) > 0) {
					if(preg_match("/wp-content\/banners\//i", $image->image)) {
						$wpdb->query("UPDATE `".$tables['adrotate']."` SET `image` = 'dropdown|$image->image' WHERE `id` = '$image->id';");
					} else {
						$wpdb->query("UPDATE `".$tables['adrotate']."` SET `image` = 'field|$image->image' WHERE `id` = '$image->id';");
					}
				}
			}
		}

		// Database: 	10
		// AdRotate: 	3.6.2
		if($adrotate_db_version < 10) {
			adrotate_add_column($tables['adrotate_tracker'], 'stat', 'CHAR(1) NOT NULL DEFAULT \'c\' AFTER `bannerid`');
			$wpdb->query("UPDATE `".$tables['adrotate_tracker']."` SET `stat` = 'c' WHERE `stat` = '';");
		}
		
		// Database: 	11
		// AdRotate: 	3.6.4
		if($adrotate_db_version < 11) {
			adrotate_add_column($tables['adrotate'], 'sortorder', 'int(5) NOT NULL DEFAULT \'0\' AFTER `weight`');
			adrotate_add_column($tables['adrotate_groups'], 'sortorder', 'int(5) NOT NULL DEFAULT \'0\' AFTER `fallback`');
			adrotate_add_column($tables['adrotate_blocks'], 'sortorder', 'int(5) NOT NULL DEFAULT \'0\' AFTER `wrapper_after`');

			// Convert image data to accomodate version 3.6.4 and up from earlier setups
			adrotate_add_column($tables['adrotate'], 'imagetype', 'varchar(10) NOT NULL AFTER `endshow`');

			$images = $wpdb->get_results("SELECT `id`, `image` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($images as $image) {
				if(strlen($image->image) > 0) {
					if(preg_match("/dropdown|/i", $image->image) OR preg_match("/field|/i", $image->image)) {
						$buffer = explode("|", $image->image, 3);
						$wpdb->query("UPDATE `".$tables['adrotate']."` SET `imagetype` = '".$buffer[0]."', `image` = '".$buffer[1]."' WHERE `id` = '$image->id';");
					}
				}
			}
		}

		// Database: 	12
		// AdRotate: 	3.6.5
		if($adrotate_db_version < 12) {
			adrotate_add_column($tables['adrotate_tracker'], 'useragent', 'mediumtext NOT NULL AFTER `stat`');
		}

		// Database: 	13
		// AdRotate:	3.7a1
		if($adrotate_db_version < 13) {
			if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_schedule']."'")) { // wp_adrotate_schedule
				$sql = "CREATE TABLE `".$tables['adrotate_schedule']."` (
						`id` mediumint(8) unsigned NOT NULL auto_increment,
						`ad` mediumint(8) NOT NULL default '0',
						`starttime` int(15) NOT NULL default '0',
						`stoptime` int(15) NOT NULL default '0',
						PRIMARY KEY  (`id`),
						INDEX `ad` (`ad`)
					) ".$charset_collate." ENGINE = 'MyISAM';";
				dbDelta($sql);
			}

			// Upgrade tables with Indexes for faster processing
			$wpdb->query("CREATE INDEX `ad` ON `".$tables['adrotate_stats_tracker']."` (`ad`);");
			$wpdb->query("CREATE INDEX `ipaddress` ON `".$tables['adrotate_tracker']."` (`ipaddress`);");

			// Migrate existing start / end times to new table
			$times = $wpdb->get_results("SELECT `id`, `startshow`, `endshow` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($times as $time) {
				$wpdb->query("INSERT INTO `".$tables['adrotate_schedule']."` (`ad`, `starttime`, `stoptime`) VALUES (".$time->id.", ".$time->startshow.", ".$time->endshow.");");
			}

			// Migrate existing statuses to new field
			$states = $wpdb->get_results("SELECT `id`, `active`, `type` FROM ".$tables['adrotate']." ORDER BY `id` ASC;");
			foreach($states as $state) {
				if($state->active == 'yes' AND $state->type == 'manual') {
					$wpdb->query("UPDATE `".$tables['adrotate']."` SET `type` = 'active' WHERE `active` = 'yes' AND `id` = '".$state->id."';");
				}
				if($state->active == 'no' AND $state->type == 'manual') {
					$wpdb->query("UPDATE `".$tables['adrotate']."` SET `type` = 'disabled' WHERE `active` = 'no' AND `id` = '".$state->id."';");
				}
			}

			// Remove now obsolete fields from table
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `startshow`;");
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `endshow`;");
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `active`;");
		}

		// Database: 	14
		// AdRotate:	3.7a2
		if($adrotate_db_version < 14) {
			// Remove now obsolete fields from table
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `maxclicks`;");
			$wpdb->query("ALTER TABLE `".$tables['adrotate']."` DROP `maxshown`;");
			adrotate_add_column($tables['adrotate_schedule'], 'maxclicks', 'int(15) NOT NULL DEFAULT \'0\' AFTER `stoptime`');
			adrotate_add_column($tables['adrotate_schedule'], 'maximpressions', 'int(15) NOT NULL DEFAULT \'0\' AFTER `maxclicks`');
		}

		// Database: 	15
		// AdRotate:	3.7a3
		if($adrotate_db_version < 15) {
			adrotate_add_column($tables['adrotate'], 'timeframe', 'varchar(6) NOT NULL DEFAULT \'\' AFTER `targetimpressions`');
			adrotate_add_column($tables['adrotate'], 'timeframelength', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframe`');
			adrotate_add_column($tables['adrotate'], 'timeframeclicks', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframelength`');
			adrotate_add_column($tables['adrotate'], 'timeframeimpressions', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframeclicks`');
		}

		// Database: 	16
		// AdRotate:	3.7b3
		if($adrotate_db_version < 16) {
			$engine = $wpdb->get_var("SELECT ENGINE FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '".DB_NAME."' AND `TABLE_NAME` = '".$wpdb->prefix."posts';");
			$engine2 = $wpdb->get_var("SELECT ENGINE FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '".DB_NAME."' AND `TABLE_NAME` = '".$wpdb->prefix."adrotate';");
			if(strtolower($engine) == 'innodb' AND strtolower($engine2) != 'innodb') {
				$wpdb->query("ALTER TABLE `".$tables['adrotate']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_groups']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_tracker']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_linkmeta']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_stats_tracker']."` ENGINE=INNODB;");
				$wpdb->query("ALTER TABLE `".$tables['adrotate_schedule']."` ENGINE=INNODB;");
			}
		}

		// Database: 	17
		// AdRotate:	3.7rc3
		if($adrotate_db_version < 17) {
			adrotate_add_column($tables['adrotate_groups'], 'cat', 'longtext NOT NULL AFTER `sortorder`');
			adrotate_add_column($tables['adrotate_groups'], 'cat_loc', 'tinyint(1) NOT NULL DEFAULT \'0\' AFTER `cat`');
			adrotate_add_column($tables['adrotate_groups'], 'page', 'longtext NOT NULL AFTER `cat_loc`');
			adrotate_add_column($tables['adrotate_groups'], 'page_loc', 'tinyint(1) NOT NULL DEFAULT \'0\' AFTER `page`');
		}

		// Database: 	18
		// AdRotate:	3.7rc5
		if($adrotate_db_version < 18) {
			$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adcount` `rows` INT(3)  NOT NULL  DEFAULT '2';");
			$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `columns` `columns` INT(3)  NOT NULL  DEFAULT '2';");
			adrotate_add_column($tables['adrotate_blocks'], 'gridpadding', 'int(2) NOT NULL DEFAULT \'0\' AFTER `columns`');
			adrotate_add_column($tables['adrotate_blocks'], 'gridborder', 'varchar(20) NOT NULL DEFAULT \'0\' AFTER `gridpadding`');
			adrotate_add_column($tables['adrotate_blocks'], 'adwidth', 'int(4) NOT NULL DEFAULT \'125\' AFTER `gridborder`');
			adrotate_add_column($tables['adrotate_blocks'], 'adheight', 'int(4) NOT NULL DEFAULT \'125\' AFTER `adwidth`');
			adrotate_add_column($tables['adrotate_blocks'], 'admargin', 'int(4) NOT NULL DEFAULT \'1\' AFTER `adheight`');
			adrotate_add_column($tables['adrotate_blocks'], 'adpadding', 'int(4) NOT NULL DEFAULT \'0\' AFTER `admargin`');
			adrotate_add_column($tables['adrotate_blocks'], 'adborder', 'varchar(20) NOT NULL DEFAULT \'0\' AFTER `adpadding`');
		}

		// Database: 	19
		// AdRotate:	3.8
		if($adrotate_db_version < 19) {
			adrotate_add_column($tables['adrotate_blocks'], 'gridfloat', 'varchar(7) NOT NULL DEFAULT \'none\' AFTER `columns`');
			$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adwidth` `adwidth` varchar(6)  NOT NULL  DEFAULT '125';");
			$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adheight` `adheight` varchar(6)  NOT NULL  DEFAULT '125';");
		}

		update_option("adrotate_db_version", ADROTATE_DB_VERSION);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_core_upgrade

 Purpose:   Upgrades AdRotate where required
 Receive:   -none-
 Return:	-none-
 Since:		3.5
-------------------------------------------------------------*/
function adrotate_core_upgrade() {
	global $wp_roles, $adrotate_version;

	if (version_compare(PHP_VERSION, '5.2.0', '<') == -1) { 
		deactivate_plugins(plugin_basename('adrotate.php'));
		wp_die('AdRotate 3.6 and up requires PHP 5.2 or higher.<br />You likely have PHP 4, which has been discontinued since december 31, 2007. Consider upgrading your server!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
		return; 
	} else {
		if($adrotate_version < 323) {
			delete_option('adrotate_notification_timer');
		}
		
		if($adrotate_version < 340) {
			add_option('adrotate_db_timer', date('U'));
		}

		if($adrotate_version < 350) {
			update_option('adrotate_debug', array('general' => false, 'dashboard' => false, 'userroles' => false, 'userstats' => false, 'stats' => false));
		}

		if($adrotate_version < 351) {
			wp_clear_scheduled_hook('adrotate_prepare_cache_statistics');
			delete_option('adrotate_stats');
		}

		if($adrotate_version < 352) {
			adrotate_remove_capability("adrotate_userstatistics"); // OBSOLETE IN 3.5
			adrotate_remove_capability("adrotate_globalstatistics"); // OBSOLETE IN 3.5
			$role = get_role('administrator');		
			$role->add_cap("adrotate_advertiser_report"); // NEW IN 3.5
			$role->add_cap("adrotate_global_report"); // NEW IN 3.5
		}

		if($adrotate_version < 353) {
			if(!is_dir(ABSPATH.'/wp-content/plugins/adrotate/language')) {
				mkdir(ABSPATH.'/wp-content/plugins/adrotate/language', 0755);
			}
		}

		if($adrotate_version < 354) {
			$crawlers = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi","looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory","Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot","www.galaxy.com", "Googlebot", "Scooter", "Slurp","msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz","Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot","Mediapartners-Google", "Sogou web spider", "WebAlta Crawler","bot", "crawler", "yahoo", "msn", "ask", "ia_archiver");
			update_option('adrotate_crawlers', $crawlers);
		}

		if($adrotate_version < 355) {
			if(!is_dir(ABSPATH.'/wp-content/reports')) {
				mkdir(ABSPATH.'/wp-content/reports', 0755);
			}
		}

		if($adrotate_version < 356) {
			adrotate_remove_capability("adrotate_advertiser_report");
			$role = get_role('administrator');		
			$role->add_cap("adrotate_advertiser");
		}
		
		if($adrotate_version < 357) {
			$role = get_role('administrator');		
			$role->add_cap("adrotate_moderate");
			$role->add_cap("adrotate_moderate_approve");
		}
		
		update_option("adrotate_version", ADROTATE_VERSION);
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
	global $adrotate_roles;
	
	// Clear out roles
	if($adrotate_roles == 1) {
		adrotate_remove_roles();
	}

	// Clear up capabilities from ALL users
	adrotate_remove_capability("adrotate_advertiser_report");
	adrotate_remove_capability("adrotate_global_report");
	adrotate_remove_capability("adrotate_ad_manage");
	adrotate_remove_capability("adrotate_ad_delete");
	adrotate_remove_capability("adrotate_group_manage");
	adrotate_remove_capability("adrotate_group_delete");
	adrotate_remove_capability("adrotate_block_manage");
	adrotate_remove_capability("adrotate_block_delete");

	// Clear out wp_cron
	wp_clear_scheduled_hook('adrotate_ad_notification');
	wp_clear_scheduled_hook('adrotate_cache_statistics'); // OBSOLETE IN 3.6 - REMOVE IN 4.0
	wp_clear_scheduled_hook('adrotate_clean_trackerdata');
	wp_clear_scheduled_hook('adrotate_evaluate_ads');
}

/*-------------------------------------------------------------
 Name:      adrotate_uninstall

 Purpose:   Delete the entire database tables and remove the options on uninstall.
 Receive:   -none-
 Return:	-none-
 Since:		2.4.2
-------------------------------------------------------------*/
function adrotate_uninstall() {
	global $wpdb, $wp_roles;

	// Drop MySQL Tables
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_groups`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_tracker`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_blocks`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_linkmeta`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_stats_tracker`");
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_schedule`");

	// Delete Options	
	delete_option('adrotate_config');				// Since 0.1
	delete_option('adrotate_notification_timer'); 	// Since 3.0 - Obsolete in 3.2.3
	delete_option('adrotate_crawlers'); 			// Since 3.0
	delete_option('adrotate_stats');				// Since 3.0 - Obsolete in 3.5
	delete_option('adrotate_roles');				// Since 3.0
	delete_option('adrotate_version');				// Since 3.2.3
	delete_option('adrotate_db_version');			// Since 3.0.3
	delete_option('adrotate_debug');				// Since 3.2
	delete_option('adrotate_advert_status');		// Since 3.7

	// Clear out userroles
	remove_role('adrotate_advertiser');

	// Clear up capabilities from ALL users
	adrotate_remove_capability("adrotate_advertiser");
	adrotate_remove_capability("adrotate_global_report");
	adrotate_remove_capability("adrotate_ad_manage");
	adrotate_remove_capability("adrotate_ad_delete");
	adrotate_remove_capability("adrotate_group_manage");
	adrotate_remove_capability("adrotate_group_delete");
	adrotate_remove_capability("adrotate_block_manage");
	adrotate_remove_capability("adrotate_block_delete");
	adrotate_remove_capability("adrotate_moderate");
	adrotate_remove_capability("adrotate_moderate_approve");
	adrotate_remove_capability("adrotate_moderate_reply");
		
	// Delete cron schedules
	wp_clear_scheduled_hook('adrotate_ad_notification');
	wp_clear_scheduled_hook('adrotate_prepare_cache_statistics'); // OBSOLETE IN 3.6 - REMOVE IN 4.0
	wp_clear_scheduled_hook('adrotate_clean_trackerdata');
	wp_clear_scheduled_hook('adrotate_evaluate_ads');
}

/*-------------------------------------------------------------
 Name:      adrotate_optimize_database

 Purpose:   Optimizes all AdRotate tables
 Receive:   -none-
 Return:    -none-
 Since:		3.4
-------------------------------------------------------------*/
function adrotate_optimize_database() {
	global $wpdb;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$adrotate_db_timer 	= get_option('adrotate_db_timer');

	$now = current_time('timestamp');
	$yesterday = $now - 86400;

	$tables = adrotate_list_tables();

	if($adrotate_db_timer < $yesterday) {
		foreach($tables as $table) {
			if($wpdb->get_var("SHOW TABLES LIKE '".$table."';")) {
				dbDelta("OPTIMIZE TABLE `$table`;");
			}
		}
		update_option('adrotate_db_timer', $now);
		adrotate_return('db_optimized');
	} else {
		adrotate_return('db_timer');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_cleanup_database

 Purpose:   Clean AdRotate tables
 Receive:   -none-
 Return:    -none-
 Since:		3.5
-------------------------------------------------------------*/
function adrotate_cleanup_database() {
	global $wpdb;

	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'empty';");
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` = '';");
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_blocks` WHERE `name` = '';");

	$ads = $wpdb->get_results("SELECT `id` FROM `".$wpdb->prefix."adrotate` ORDER BY `id`;");
	$metas = $wpdb->get_results("SELECT `id`, `ad` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` != '0' ORDER BY `id`;");
	$schedules = $wpdb->get_results("SELECT `id`, `ad` FROM `".$wpdb->prefix."adrotate_schedule` ORDER BY `id`;");
	
	$adverts = $linkmeta = $timeframes = array();
	foreach($ads as $ad) {
		$adverts[$ad->id] = $ad->id;
	}
	foreach($metas as $meta) {
		$linkmeta[$meta->id] = $meta->ad;
	}
	foreach($schedules as $schedule) {
		$timeframes[$schedule->id] = $schedule->ad;
	}

	$result = array_diff($linkmeta, $adverts);
	$result2 = array_diff($timeframes, $adverts);
	foreach($result as $key => $value) {
		$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `id` = $key;");
	}
	unset($value);
	foreach($result2 as $key => $value) {
		$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_schedule` WHERE `id` = $key;");
	}
	unset($value);

	unset($ads, $metas, $schedules, $adverts, $linkmeta, $timeframes);

	adrotate_return('db_cleaned');
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

/*-------------------------------------------------------------
 Name:      adrotate_list_tables

 Purpose:   List tables for AdRotate in an array
 Receive:   -None-
 Return:	-None-
 Since:		3.4
-------------------------------------------------------------*/
function adrotate_list_tables() {
	global $wpdb;

	$tables = array(
		'adrotate' 					=> $wpdb->prefix . "adrotate",					// Since 0.1
		'adrotate_groups' 			=> $wpdb->prefix . "adrotate_groups",			// Since 0.2
		'adrotate_tracker' 			=> $wpdb->prefix . "adrotate_tracker",			// Since 2.0
		'adrotate_blocks' 			=> $wpdb->prefix . "adrotate_blocks",			// Since 3.0
		'adrotate_linkmeta' 		=> $wpdb->prefix . "adrotate_linkmeta",			// Since 3.0
		'adrotate_stats_tracker' 	=> $wpdb->prefix . "adrotate_stats_tracker",	// Since 3.5
		'adrotate_schedule'		 	=> $wpdb->prefix . "adrotate_schedule",			// Since 3.6.11a1
	);

	return $tables;
}
?>