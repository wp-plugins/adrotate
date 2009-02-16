<?php
/*-------------------------------------------------------------
 Name:      adrotate_activate

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_activate() {
	global $wpdb;

	$mysql = false;

	$tables = array(
		$wpdb->prefix . "adrotate", 
		$wpdb->prefix . "adrotate_groups", 
		$wpdb->prefix . "adrotate_tracker",
	);
	
	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}
	
	if(!adrotate_mysql_table_exists($tables[0])) { // Add table if it's not there
		$add1 = "CREATE TABLE ".$tables[0]." (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `title` longtext NOT NULL,
			  `bannercode` longtext NOT NULL,
			  `thetime` int(15) NOT NULL default '0',
			  `updated` int(15) NOT NULL,
			  `author` varchar(60) NOT NULL default '',
			  `active` varchar(4) NOT NULL default 'yes',
			  `startshow` int(15) NOT NULL default '0',
			  `endshow` int(15) NOT NULL default '0',
			  `group` int(15) NOT NULL default '1',
			  `image` varchar(255) NOT NULL,
			  `link` longtext NOT NULL,
			  `tracker` varchar(5) NOT NULL default 'N',
			  `clicks` int(15) NOT NULL default '0',
			  `shown` int(15) NOT NULL default '0',
	  		PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add1) === true) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else { // Or send out epic fail!
		$mysql = false;
	}

	if(!adrotate_mysql_table_exists($tables[1])) {
		$add2 = "CREATE TABLE ".$tables[1]." (
				`id` mediumint(8) unsigned NOT NULL auto_increment, 
				`name` varchar(255) NOT NULL, 
				PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add2) === true ) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else {
		$mysql = true;
	}

	if(!adrotate_mysql_table_exists($tables[2])) {
		$add3 = "CREATE TABLE ".$tables[2]." (
				`id` mediumint(8) unsigned NOT NULL auto_increment, 
				`ipaddress` varchar(255) NOT NULL, 
				`timer` int(15) NOT NULL default '0',
				`bannerid` int(15) NOT NULL default '0', 
				PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add3) === true ) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else {
		$mysql = true;
	}
	
	if(adrotate_mysql_table_exists($tables[0])) { // Upgrade table if it is incomplete
		if (!$result = mysql_query("SHOW COLUMNS FROM `$tables[0]`")) {
		    echo 'Could not run query: ' . mysql_error();
		}
		$i = 0;
	    while ($row = mysql_fetch_assoc($result)) {
			$field_array[] = mysql_field_name($row, $i);
        	$i++;
		}
		
		if (!in_array('startshow', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `startshow` INT( 15 ) NOT NULL DEFAULT '0' AFTER `active`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('endshow', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `endshow` INT( 15 ) NOT NULL DEFAULT '0' AFTER `startshow`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('link', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `link` LONGTEXT NOT NULL AFTER `image`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('tracker', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `tracker` VARCHAR( 5 ) NOT NULL DEFAULT 'N' AFTER `link`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('clicks', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `clicks` INT( 15 ) NOT NULL DEFAULT '0' AFTER `tracker`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('shown', $field_array)) {
			if(mysql_query("ALTER TABLE  `$tables[0]` ADD `shown` INT( 15 ) NOT NULL DEFAULT '0' AFTER `clicks`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$mysql = true;
		}
	} else { // Or send out epic fail!
		$upgrade = false;
	}

	if(adrotate_mysql_table_exists($tables[2])) { // Upgrade table if it is incomplete
		if (!$result = mysql_query("SHOW COLUMNS FROM `$tables[2]`")) {
		    echo 'Could not run query: ' . mysql_error();
		}
		$i = 0;
	    while ($row = mysql_fetch_assoc($result)) {
			$field_array[] = mysql_field_name($row, $i);
        	$i++;
		}
		
		if (!in_array('bannerid', $field_array)) {
			if(mysql_query("ALTER TABLE `$tables[2]` ADD `bannerid` INT( 15 ) NOT NULL DEFAULT '0' AFTER `timer`;") === true) {
				$upgrade = true;
			} else {
				$upgrade = false;
			}
		} else {
			$upgrade = true;
		}
	} else { // Or send out epic fail!
		$upgrade = false;
	}
	
	if(!is_dir(ABSPATH.'/wp-content/banners')) {
		mkdir(ABSPATH.'/wp-content/banners', 0755);
	}

	if($mysql == true) {
		adrotate_send_data('Activate');
	} else if($mysql == true AND $upgrade == true) {
		adrotate_send_data('Upgrade');
	} else {
		adrotate_mysql_warning();
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_deactivate

 Purpose:   Deactivate script
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_deactivate() {
	adrotate_send_data('Deactivate');
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_table_exists($table_name) {
	global $wpdb;

	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	return false;
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_warning

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_warning() {
	echo '<div class="updated"><h3>WARNING! There was an error with MySQL! One or more queries failed. This means the database has not been created or only partly. Seek support at the <a href="http://forum.at.meandmymac.net">meandmymac.net support forums</a>. Please include any errors you saw or anything that might have caused this issue . This helps speed up the process greatly!</h3></div>';
}
?>