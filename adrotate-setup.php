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
	$table_name1 = $wpdb->prefix . "adrotate";
	$table_name2 = $wpdb->prefix . "adrotate_groups";
	$table_name3 = $wpdb->prefix . "adrotate_tracker";
	
	if(!adrotate_mysql_table_exists($table_name1)) { // Add table if it's not there
		$add1 = "CREATE TABLE ".$table_name1." (
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
			);";
		if(mysql_query($add1) === true) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else if(adrotate_mysql_table_exists($table_name1)) { // Upgrade table if it is incomplete
		if (!$result = mysql_query("SHOW COLUMNS FROM `$table_name1`")) {
		    echo 'Could not run query: ' . mysql_error();
		}
		$i = 0;
	    while ($row = mysql_fetch_assoc($result)) {
			$field_array[] = mysql_field_name($row, $i);
        	$i++;
		}
		
		if (!in_array('startshow', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `startshow` INT( 15 ) NOT NULL DEFAULT '0' AFTER `active`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('endshow', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `endshow` INT( 15 ) NOT NULL DEFAULT '0' AFTER `startshow`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('link', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `link` LONGTEXT NOT NULL AFTER `image`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('tracker', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `tracker` VARCHAR( 5 ) NOT NULL DEFAULT 'N' AFTER `link`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('clicks', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `clicks` INT( 15 ) NOT NULL DEFAULT '0' AFTER `tracker`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
		if (!in_array('shown', $field_array)) {
			if(mysql_query("ALTER TABLE  `$table_name1` ADD `shown` INT( 15 ) NOT NULL DEFAULT '0' AFTER `clicks`;") === true) {
				$myqsl = true;
			} else {
				$mysql = false;
			}
		} else {
			$mysql = true;
		}
	} else { // Or send out epic fail!
		$mysql = false;
	}

	if(!adrotate_mysql_table_exists($table_name2)) {
		$add2 = "CREATE TABLE ".$table_name2." (
				`id` mediumint(8) unsigned NOT NULL auto_increment, 
				`name` varchar(255) NOT NULL, 
				PRIMARY KEY  (`id`)
			);";
		if(mysql_query($add2) === true ) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else {
		$mysql = true;
	}

	if(!adrotate_mysql_table_exists($table_name3)) {
		$add3 = "CREATE TABLE ".$table_name3." (
				`id` mediumint(8) unsigned NOT NULL auto_increment, 
				`ipaddress` varchar(255) NOT NULL, 
				`timer` int(15) NOT NULL default '0', 
				PRIMARY KEY  (`id`)
			);";
		if(mysql_query($add3) === true ) {
			$myqsl = true;
		} else {
			$mysql = false;
		}
	} else {
		$mysql = true;
	}

	if($mysql == true) {
		adrotate_send_data('Activate');
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