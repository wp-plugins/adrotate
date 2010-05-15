<?php
/*-------------------------------------------------------------
 Name:      adrotate_activate

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_activate() {
	global $wpdb;

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
			  `maxclicks` int(15) NOT NULL default '0',
			  `shown` int(15) NOT NULL default '0',
			  `maxshown` int(15) NOT NULL default '0',			  
			  `magic` int(1) NOT NULL default '0',
	  		PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add1) !== true) {
			adrotate_mysql_warning();
		}
	}

	if(!adrotate_mysql_table_exists($tables[1])) {
		$add2 = "CREATE TABLE ".$tables[1]." (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`name` varchar(255) NOT NULL,
				`fallback` varchar(5) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add2) !== true ) {
			adrotate_mysql_warning();
		}
	}

	if(!adrotate_mysql_table_exists($tables[2])) {
		$add3 = "CREATE TABLE ".$tables[2]." (
				`id` mediumint(8) unsigned NOT NULL auto_increment,
				`ipaddress` varchar(255) NOT NULL,
				`timer` int(15) NOT NULL default '0',
				`bannerid` int(15) NOT NULL default '0',
				PRIMARY KEY  (`id`)
			) ".$charset_collate;
		if(mysql_query($add3) !== true ) {
			adrotate_mysql_warning();
		}
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
			$upgrade = adrotate_update_table('add', $tables[0], 'startshow', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'active');
		}

		if (!in_array('endshow', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'endshow', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'startshow');
		}

		if (!in_array('link', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'link', 'LONGTEXT NOT NULL', 'image');
		}

		if (!in_array('tracker', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'tracker', 'VARCHAR( 5 ) NOT NULL DEFAULT \'N\'', 'link');
		}

		if (!in_array('clicks', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'clicks', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'tracker');
		}

		if (!in_array('maxclicks', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'maxclicks', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'clicks');
		}

		if (!in_array('shown', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'shown', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'maxclicks');
		}

		if (!in_array('maxshown', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'maxshown', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'shown');
		}

		if (!in_array('magic', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[0], 'magic', 'VARCHAR( 1 ) NOT NULL DEFAULT \'0\'', 'maxshown');
		}
		
	} else { // Or send out epic fail!
		adrotate_mysql_warning();
	}

	if(adrotate_mysql_table_exists($tables[1])) { // Upgrade table if it is incomplete
		if (!$result = mysql_query("SHOW COLUMNS FROM `$tables[1]`")) {
		    echo 'Could not run query: ' . mysql_error();
		}
		$i = 0;
	    while ($row = mysql_fetch_assoc($result)) {
			$field_array[] = mysql_field_name($row, $i);
        	$i++;
		}

		if (!in_array('name', $field_array)) {
			$upgrade = adrotate_update_table('add', $tables[1], 'fallback', 'VARCHAR( 5 ) NOT NULL DEFAULT \'0\'', 'name');
		}

	} else { // Or send out epic fail!
		adrotate_mysql_warning();
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
			$upgrade = adrotate_update_table('add', $tables[2], 'bannerid', 'INT( 15 ) NOT NULL DEFAULT \'0\'', 'timer');
		}

	} else { // Or send out epic fail!
		adrotate_mysql_warning();
	}

	if(!is_dir(ABSPATH.'/wp-content/banners')) {
		mkdir(ABSPATH.'/wp-content/banners', 0755);
	}

	delete_option('adrotate_tracker');

}

/*-------------------------------------------------------------
 Name:      adrotate_update_table

 Purpose:   Alter tables on demand, for upgrades
 Receive:   $action, $tablename, $field_to_add, $specs, $after_field
 Return:	Boolean
-------------------------------------------------------------*/
function adrotate_update_table($action, $tablename, $field_to_add, $specs, $after_field) {
	switch($action) {
		case "add" :
			if(mysql_query("ALTER TABLE `$tablename` ADD `$field_to_add` $specs AFTER `$after_field`;") === true) {
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
-------------------------------------------------------------*/
function adrotate_deactivate() {
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
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

/*-------------------------------------------------------------
 Name:      adrotate_mysql_warning

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_warning() {
	echo '<div class="updated"><h3>WARNING! There was an error with MySQL! One or more queries failed. This means the database has not been created or only partly. Seek support at <a href="http://meandmymac.net/support/">meandmymac.net support</a>. Please include any errors you saw or anything that might have caused this issue. This helps speed up the process of solving your issue greatly!</h3></div>';
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_upgrade_error

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_upgrade_error() {
	echo '<div class="updated"><h3>WARNING! The MySQL table was not properly upgraded! AdRotate cannot work without this upgrade. Check your MySQL permissions and see if you have ALTER rights (rights to alter existing tables) contact your webhost/sysadmin if you don\'t know or are unsure. If this brings no answers seek support at <a href="http://meandmymac.net/support/">http://meandmymac.net/support/</a> and mention any errors you saw/got and explain what you were doing!</h3></div>';
}

/*-------------------------------------------------------------
 Name:      adrotate_plugin_uninstall

 Purpose:   Delete the entire database table and remove the options on uninstall.
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_plugin_uninstall() {
	global $wpdb;

	// Deactivate Plugin
	$current = get_settings('active_plugins');
    array_splice($current, array_search( "adrotate/adrotate.php", $current), 1 );
	update_option('active_plugins', $current);
	do_action('deactivate_' . trim( $_GET['plugin'] ));

	// Drop MySQL Tables
	mysql_query("DROP TABLE `".$wpdb->prefix."adrotate`") or die("An unexpected error occured.<br />".mysql_error());
	mysql_query("DROP TABLE `".$wpdb->prefix."adrotate_groups`") or die("An unexpected error occured.<br />".mysql_error());
	mysql_query("DROP TABLE `".$wpdb->prefix."adrotate_tracker`") or die("An unexpected error occured.<br />".mysql_error());

	// Delete Options
	delete_option('adrotate_config');
	delete_option('widget_adrotate_1');
	delete_option('widget_adrotate_2');

	wp_redirect(get_option('siteurl').'/wp-admin/plugins.php?deactivate=true');
}
?>