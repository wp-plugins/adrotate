<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
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

	if(!current_user_can('activate_plugins')) {
		deactivate_plugins(plugin_basename('adrotate.php'));
		wp_die('You do not have appropriate access to activate this plugin! Contact your administrator!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
		return; 
	} else {
		// Install tables for AdRotate
		adrotate_check_upgrade();

		// Set up some schedules
		$firstrun = date('U') + 3600;
		if (!wp_next_scheduled('adrotate_clean_trackerdata')) // Periodically clean trackerdata
			wp_schedule_event($firstrun, 'twicedaily', 'adrotate_clean_trackerdata');
	
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
	
		// Switch additional roles off
		adrotate_remove_roles();
		update_option('adrotate_roles', '0');

		// Set default settings and values
		add_option('adrotate_db_timer', date('U'));
		add_option('adrotate_debug', array('general' => false, 'dashboard' => false, 'userroles' => false, 'userstats' => false, 'stats' => false, 'track' => false));

		adrotate_check_config();

		// Attempt to make the wp-content/banners/ folder
		if(!is_dir(ABSPATH.'/wp-content/banners')) {
			mkdir(ABSPATH.'/wp-content/banners', 0755);
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

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate']."` (
		  	`id` mediumint(8) unsigned NOT NULL auto_increment,
		  	`title` longtext NOT NULL,
		  	`bannercode` longtext NOT NULL,
		  	`thetime` int(15) NOT NULL default '0',
			`updated` int(15) NOT NULL,
		  	`author` varchar(60) NOT NULL default '',
		  	`imagetype` varchar(10) NOT NULL,
		  	`image` varchar(255) NOT NULL,
		  	`link` longtext NOT NULL,
		  	`tracker` varchar(5) NOT NULL default 'N',
		  	`timeframe` varchar(6) NOT NULL default '',
		  	`timeframelength` int(15) NOT NULL default '0',
		  	`timeframeclicks` int(15) NOT NULL default '0',
		  	`timeframeimpressions` int(15) NOT NULL default '0',
		  	`type` varchar(10) NOT NULL default '0',
		  	`weight` int(3) NOT NULL default '6',
			`sortorder` int(5) NOT NULL default '0',
		  	`cbudget` double NOT NULL default '0',
		  	`ibudget` double NOT NULL default '0',
		  	`crate` double NOT NULL default '0',
		  	`irate` double NOT NULL default '0',
			`cities` text NOT NULL,
			`countries` text NOT NULL,
  		PRIMARY KEY  (`id`)
		) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_blocks']."` (
			  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			  `name` varchar(255) NOT NULL DEFAULT 'Block',
			  `rows` int(3) NOT NULL DEFAULT '2',
			  `columns` int(3) NOT NULL DEFAULT '2',
			  `gridfloat` varchar(7) NOT NULL DEFAULT 'none',
			  `gridpadding` int(2) NOT NULL DEFAULT '0',
			  `adwidth` varchar(6) NOT NULL DEFAULT '125',
			  `adheight` varchar(6) NOT NULL DEFAULT '125',
			  `admargin` int(2) NOT NULL DEFAULT '1',
			  `adborder` varchar(20) NOT NULL DEFAULT '0',
			  `wrapper_before` longtext NOT NULL,
			  `wrapper_after` longtext NOT NULL,
			  `sortorder` int(5) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`)
			) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_groups']."` (
			`id` mediumint(8) unsigned NOT NULL auto_increment,
			`name` varchar(255) NOT NULL default 'group',
			`token` varchar(10) NOT NULL default '0',
			`fallback` varchar(5) NOT NULL default '0',
			`sortorder` int(5) NOT NULL default '0',
			`cat` longtext NOT NULL,
			`cat_loc` tinyint(1) NOT NULL default '0',
			`page` longtext NOT NULL,
			`page_loc` tinyint(1) NOT NULL default '0',
			`geo` tinyint(1) NOT NULL default '0',
			`wrapper_before` longtext NOT NULL,
			`wrapper_after` longtext NOT NULL,
			PRIMARY KEY  (`id`)
		) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_linkmeta']."` (
			`id` mediumint(8) unsigned NOT NULL auto_increment,
			`ad` int(5) NOT NULL default '0',
			`group` int(5) NOT NULL default '0',
			`block` int(5) NOT NULL default '0',
			`user` int(5) NOT NULL default '0',
			PRIMARY KEY  (`id`)
		) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_schedule']."` (
			`id` int(8) unsigned NOT NULL auto_increment,
			`ad` mediumint(8) NOT NULL default '0',
			`starttime` int(15) NOT NULL default '0',
			`stoptime` int(15) NOT NULL default '0',
			`maxclicks` int(15) NOT NULL default '0',
			`maximpressions` int(15) NOT NULL default '0',
			PRIMARY KEY  (`id`),
			INDEX `ad` (`ad`)
		) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_stats']."` (
			`id` bigint(9) unsigned NOT NULL auto_increment,
			`ad` int(5) NOT NULL default '0',
			`group` int(5) NOT NULL default '0',
			`block` int(5) NOT NULL default '0',
			`thetime` int(15) NOT NULL default '0',
			`clicks` int(15) NOT NULL default '0',
			`impressions` int(15) NOT NULL default '0',
			PRIMARY KEY  (`id`),
			INDEX `ad` (`ad`)
		) ".$charset_collate." ENGINE=InnoDB;");

	dbDelta("CREATE TABLE IF NOT EXISTS `".$tables['adrotate_tracker']."` (
			`id` bigint(9) unsigned NOT NULL auto_increment,
			`ipaddress` varchar(255) NOT NULL default '0',
			`timer` int(15) NOT NULL default '0',
			`bannerid` int(15) NOT NULL default '0',
			`stat` char(1) NOT NULL default 'c',
			`useragent` mediumtext NOT NULL,
			PRIMARY KEY  (`id`),
		    KEY `ipaddress` (`ipaddress`),
		    KEY `timer` (`timer`)
		) ".$charset_collate." ENGINE=InnoDB;");
}

/*-------------------------------------------------------------
 Name:      adrotate_check_upgrade

 Purpose:   Checks if the plugin needs to upgrade stuff upon activation
 Receive:   -none-
 Return:	-none-
 Since:		3.7.3
-------------------------------------------------------------*/
function adrotate_check_upgrade() {
	global $wpdb;
	
	if(version_compare(PHP_VERSION, '5.2.0', '<') == -1) { 
		deactivate_plugins(plugin_basename('adrotate.php'));
		wp_die('AdRotate 3.6 and up requires PHP 5.2 or higher.<br />You likely have PHP 4, which has been discontinued since december 31, 2007. Consider upgrading your server!<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to plugins</a>.'); 
		return; 
	} else {
		$adrotate_db_version = get_option("adrotate_db_version");
		$adrotate_version = get_option("adrotate_version");
	
		// Check if there are tables with AdRotate in the name
		if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate%';")) {
			// Old version? Upgrade
			if((is_array($adrotate_db_version) AND $adrotate_db_version['current'] < ADROTATE_DB_VERSION) OR ($adrotate_db_version < ADROTATE_DB_VERSION OR $adrotate_db_version == '')) {
				adrotate_database_upgrade();
			}
		} else {
			// Install new database
			adrotate_database_install();
			
			// Set defaults for internal versions
			add_option('adrotate_db_version', array('current' => ADROTATE_DB_VERSION, 'previous' => ''));
			add_option('adrotate_version', array('current' => ADROTATE_VERSION, 'previous' => ''));
		}
	
		// Check if there are changes to core that need upgrading
		if((is_array($adrotate_version) AND $adrotate_version['current'] < ADROTATE_VERSION) OR ($adrotate_version < ADROTATE_VERSION	OR $adrotate_version == '')) {
			adrotate_core_upgrade();
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_database_upgrade

 Purpose:   Upgrades AdRotate where required
 Receive:   -none-
 Return:	-none-
 Since:		3.0.3
-------------------------------------------------------------*/
function adrotate_database_upgrade() {
	global $wpdb;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$tables = adrotate_list_tables();

	$adrotate_db_version = get_option("adrotate_db_version");
	// Legacy compatibility (Support 3.7.4 and older)
	if(!is_array($adrotate_db_version)) $adrotate_db_version = array('current' => $adrotate_db_version, 'previous' => 0);

	// Database: 	12
	// AdRotate: 	3.6.5
	if($adrotate_db_version['current'] < 12) {
		adrotate_add_column($tables['adrotate_tracker'], 'useragent', 'mediumtext NOT NULL AFTER `stat`');
	}

	// Database: 	13
	// AdRotate:	3.7a1
	if($adrotate_db_version['current'] < 13) {
		if(!$wpdb->get_var("SHOW TABLES LIKE '".$tables['adrotate_schedule']."'")) { // wp_adrotate_schedule
			$sql = "CREATE TABLE `".$tables['adrotate_schedule']."` (
					`id` mediumint(8) unsigned NOT NULL auto_increment,
					`ad` mediumint(8) NOT NULL default '0',
					`starttime` int(15) NOT NULL default '0',
					`stoptime` int(15) NOT NULL default '0',
					PRIMARY KEY  (`id`),
					INDEX `ad` (`ad`)
				) ".$charset_collate.";";
			dbDelta($sql);
		}

		// Upgrade tables with Indexes for faster processing
		$wpdb->query("CREATE INDEX `ad` ON `".$tables['adrotate_stats']."` (`ad`);");
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
		adrotate_del_column($tables['adrotate'], 'startshow');
		adrotate_del_column($tables['adrotate'], 'endshow');
		adrotate_del_column($tables['adrotate'], 'active');
	}

	// Database: 	14
	// AdRotate:	3.7a2
	if($adrotate_db_version['current'] < 14) {
		// Remove now obsolete fields from table
		adrotate_del_column($tables['adrotate'], 'maxclicks');
		adrotate_del_column($tables['adrotate'], 'maxshown');
		adrotate_add_column($tables['adrotate_schedule'], 'maxclicks', 'int(15) NOT NULL DEFAULT \'0\' AFTER `stoptime`');
		adrotate_add_column($tables['adrotate_schedule'], 'maximpressions', 'int(15) NOT NULL DEFAULT \'0\' AFTER `maxclicks`');
	}

	// Database: 	15
	// AdRotate:	3.7a3
	if($adrotate_db_version['current'] < 15) {
		adrotate_add_column($tables['adrotate'], 'timeframe', 'varchar(6) NOT NULL DEFAULT \'\' AFTER `targetimpressions`');
		adrotate_add_column($tables['adrotate'], 'timeframelength', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframe`');
		adrotate_add_column($tables['adrotate'], 'timeframeclicks', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframelength`');
		adrotate_add_column($tables['adrotate'], 'timeframeimpressions', 'int(15) NOT NULL DEFAULT \'0\' AFTER `timeframeclicks`');
	}

	// Database: 	16
	// AdRotate:	3.7b3
	if($adrotate_db_version['current'] < 16) {
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
	if($adrotate_db_version['current'] < 17) {
		adrotate_add_column($tables['adrotate_groups'], 'cat', 'longtext NOT NULL AFTER `sortorder`');
		adrotate_add_column($tables['adrotate_groups'], 'cat_loc', 'tinyint(1) NOT NULL DEFAULT \'0\' AFTER `cat`');
		adrotate_add_column($tables['adrotate_groups'], 'page', 'longtext NOT NULL AFTER `cat_loc`');
		adrotate_add_column($tables['adrotate_groups'], 'page_loc', 'tinyint(1) NOT NULL DEFAULT \'0\' AFTER `page`');
	}

	// Database: 	18
	// AdRotate:	3.7rc5
	if($adrotate_db_version['current'] < 18) {
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
	// AdRotate:	3.7.2
	if($adrotate_db_version['current'] < 19) {
		adrotate_add_column($tables['adrotate_blocks'], 'gridfloat', 'varchar(7) NOT NULL DEFAULT \'none\' AFTER `columns`');
		$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adwidth` `adwidth` varchar(6)  NOT NULL  DEFAULT '125';");
		$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adheight` `adheight` varchar(6)  NOT NULL  DEFAULT '125';");
	}

	// Database: 	24
	// AdRotate:	3.8b412
	if($adrotate_db_version['current'] < 24) {
		if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_stats_tracker'")) {
			$wpdb->query("RENAME TABLE `".$wpdb->prefix."adrotate_stats_tracker` TO `".$wpdb->prefix."adrotate_stats`;");
		}
		adrotate_del_column($tables['adrotate_blocks'], 'gridborder');
		adrotate_del_column($tables['adrotate_blocks'], 'adpadding');
	}

	// Database: 	25
	// AdRotate:	3.8b413
	if($adrotate_db_version['current'] < 25) {
		$wpdb->query("CREATE INDEX `timer` ON `".$tables['adrotate_tracker']."` (timer);");
		$wpdb->query("CREATE INDEX `ipaddress` ON `".$tables['adrotate_tracker']."` (ipaddress);");
		$wpdb->query("CREATE INDEX `ad` ON `".$tables['adrotate_stats']."` (ad);");
		$wpdb->query("CREATE INDEX `thetime` ON `".$tables['adrotate_stats']."` (thetime);");
		$wpdb->query("CREATE INDEX `ad` ON `".$tables['adrotate_schedule']."` (ad);");
	}

	// Database: 	26
	// AdRotate:	3.8.1
	if($adrotate_db_version['current'] < 26) {
		adrotate_add_column($tables['adrotate'], 'cbudget', 'double NOT NULL default \'0\' AFTER `sortorder`');
		adrotate_add_column($tables['adrotate'], 'ibudget', 'double NOT NULL default \'0\' AFTER `cbudget`');
		adrotate_add_column($tables['adrotate'], 'crate', 'double NOT NULL default \'0\' AFTER `ibudget`');
		adrotate_add_column($tables['adrotate'], 'irate', 'double NOT NULL default \'0\' AFTER `crate`');
	}

	// Database: 	27
	// AdRotate:	3.8.3.1
	if($adrotate_db_version['current'] < 27) {
		$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adwidth` `adwidth` varchar(6)  NOT NULL  DEFAULT '125';");
		$wpdb->query("ALTER TABLE `".$tables['adrotate_blocks']."` CHANGE `adheight` `adheight` varchar(6)  NOT NULL  DEFAULT '125';");
	}

	// Database: 	30
	// AdRotate:	3.8.3.4
	if($adrotate_db_version['current'] < 30) {
		adrotate_add_column($tables['adrotate_groups'], 'wrapper_before', 'longtext NOT NULL AFTER `page_loc`');
		adrotate_add_column($tables['adrotate_groups'], 'wrapper_after', 'longtext NOT NULL AFTER `wrapper_before`');
	}

	// Database: 	31
	// AdRotate:	3.8.4
	if($adrotate_db_version['current'] < 31) {
		adrotate_add_column($tables['adrotate_groups'], 'token', 'varchar(10) NOT NULL default \'0\' AFTER `name`');
	}

	// Database: 	32
	// AdRotate:	3.8.4.4
	if($adrotate_db_version['current'] < 32) {
		adrotate_add_column($tables['adrotate'], 'cities', 'text NOT NULL AFTER `irate`');
		adrotate_add_column($tables['adrotate'], 'countries', 'text NOT NULL AFTER `cities`');
		$geo_array = serialize(array());
		$wpdb->query("UPDATE `".$tables['adrotate']."` SET `cities` = '$geo_array' WHERE `cities` = '';");
		$wpdb->query("UPDATE `".$tables['adrotate']."` SET `countries` = '$geo_array' WHERE `countries` = '';");
		adrotate_add_column($tables['adrotate_groups'], 'geo', 'tinyint(1) NOT NULL default \'0\' AFTER `page_loc`');
	}

	update_option("adrotate_db_version", array('current' => ADROTATE_DB_VERSION, 'previous' => $adrotate_db_version['current']));
}

/*-------------------------------------------------------------
 Name:      adrotate_core_upgrade

 Purpose:   Upgrades AdRotate where required
 Receive:   -none-
 Return:	-none-
 Since:		3.5
-------------------------------------------------------------*/
function adrotate_core_upgrade() {
	global $wp_roles;

	$adrotate_version = get_option("adrotate_version");
	// Legacy compatibility (Support 3.7.4 and older)
	if(!is_array($adrotate_version)) $adrotate_version = array('current' => $adrotate_version, 'previous' => 0);

	if($adrotate_version['current'] < 323) {
		delete_option('adrotate_notification_timer');
	}
	
	if($adrotate_version['current'] < 340) {
		add_option('adrotate_db_timer', date('U'));
	}

	if($adrotate_version['current'] < 350) {
		update_option('adrotate_debug', array('general' => false, 'dashboard' => false, 'userroles' => false, 'userstats' => false, 'stats' => false));
	}

	if($adrotate_version['current'] < 351) {
		wp_clear_scheduled_hook('adrotate_prepare_cache_statistics');
		delete_option('adrotate_stats');
	}

	if($adrotate_version['current'] < 352) {
		adrotate_remove_capability("adrotate_userstatistics"); // OBSOLETE IN 3.5
		adrotate_remove_capability("adrotate_globalstatistics"); // OBSOLETE IN 3.5
		$role = get_role('administrator');		
		$role->add_cap("adrotate_advertiser_report"); // NEW IN 3.5
		$role->add_cap("adrotate_global_report"); // NEW IN 3.5
	}

	if($adrotate_version['current'] < 353) {
		if(!is_dir(ABSPATH.'/wp-content/plugins/adrotate/language')) {
			mkdir(ABSPATH.'/wp-content/plugins/adrotate/language', 0755);
		}
	}

	if($adrotate_version['current'] < 354) {
		$crawlers = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi","looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory","Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot","www.galaxy.com", "Googlebot", "Scooter", "Slurp","msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz","Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot","Mediapartners-Google", "Sogou web spider", "WebAlta Crawler","bot", "crawler", "yahoo", "msn", "ask", "ia_archiver");
		update_option('adrotate_crawlers', $crawlers);
	}

	if($adrotate_version['current'] < 355) {
		if(!is_dir(ABSPATH.'/wp-content/reports')) {
			mkdir(ABSPATH.'/wp-content/reports', 0755);
		}
	}

	if($adrotate_version['current'] < 356) {
		adrotate_remove_capability("adrotate_advertiser_report");
		$role = get_role('administrator');		
		$role->add_cap("adrotate_advertiser");
	}
	
	if($adrotate_version['current'] < 357) {
		$role = get_role('administrator');		
		$role->add_cap("adrotate_moderate");
		$role->add_cap("adrotate_moderate_approve");
	}
	
	// 3.8.3.3
	if($adrotate_version['current'] < 363) {
		// Set defaults for internal versions
		$adrotate_db_version = get_option("adrotate_db_version");
		if(empty($adrotate_db_version)) update_option('adrotate_db_version', array('current' => ADROTATE_DB_VERSION, 'previous' => $adrotate_db_version['current']));
	}

	// 3.8.4
	if($adrotate_version['current'] < 364) {
		// Reset wp-cron tasks
		wp_clear_scheduled_hook('adrotate_ad_notification');
		wp_clear_scheduled_hook('adrotate_prepare_cache_statistics'); // OBSOLETE IN 3.6 - REMOVE IN 4.0
		wp_clear_scheduled_hook('adrotate_clean_trackerdata');
		wp_clear_scheduled_hook('adrotate_evaluate_ads');

		$firstrun = date('U') + 3600;
		if(!wp_next_scheduled('adrotate_clean_trackerdata')) wp_schedule_event($firstrun, 'twicedaily', 'adrotate_clean_trackerdata');
	}

	update_option("adrotate_version", array('current' => ADROTATE_VERSION, 'previous' => $adrotate_version['current']));
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

	// Clean up capabilities from ALL users
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

	// Clear out wp_cron
	wp_clear_scheduled_hook('adrotate_ad_notification');
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
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_stats_tracker`"); // Obsolete starting 3.8
	$wpdb->query("DROP TABLE `".$wpdb->prefix."adrotate_stats`");
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

	$now = adrotate_now();
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

	// Delete empty ads, groups and blocks
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'empty' OR `type` = 'a_empty';");
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` = '';");
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_blocks` WHERE `name` = '';");

	// Clean up meta data and schedules
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
	
	// Clean up tracker data
	adrotate_clean_trackerdata();

	adrotate_return('db_cleaned');
}

/*-------------------------------------------------------------
 Name:      adrotate_clean_trackerdata

 Purpose:   Removes old statistics
 Receive:   -none-
 Return:    -none-
 Since:		2.0
-------------------------------------------------------------*/
function adrotate_clean_trackerdata() {
	global $wpdb;

	$removeme = adrotate_now() - 86400;
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_tracker` WHERE `timer` < ".$removeme.";");
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress`  = 'unknown' OR `ipaddress`  = '';");
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
	
	foreach($wpdb->get_col("SHOW COLUMNS FROM $table_name;") as $column) {
		if($column == $column_name) return true;
	}
	
	$wpdb->query("ALTER TABLE $table_name ADD $column_name " . $attributes.";");
	
	foreach($wpdb->get_col("SHOW COLUMNS FROM $table_name;") as $column) {
		if($column == $column_name) return true;
	}
	
	return false;
}

/*-------------------------------------------------------------
 Name:      adrotate_del_column

 Purpose:   Check if the column exists in the table remove if it does
 Receive:   $table_name, $column_name
 Return:	Boolean
 Since:		3.8.3.3
-------------------------------------------------------------*/
function adrotate_del_column($table_name, $column_name) {
	global $wpdb;
	
	foreach($wpdb->get_col("SHOW COLUMNS FROM $table_name;") as $column) {
		if($column == $column_name) {
			$wpdb->query("ALTER TABLE $table_name DROP $column;");
			return true;
		}
	}
	
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
		'adrotate_stats' 			=> $wpdb->prefix . "adrotate_stats",			// Since 3.5 (renamed in 3.8)
		'adrotate_schedule'		 	=> $wpdb->prefix . "adrotate_schedule",			// Since 3.6.11a1
	);

	return $tables;
}
?>