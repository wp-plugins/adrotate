<?php
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
	echo '<div class="updated"><h3>WARNING! The MySQL table was not created! You cannot store banners. Seek support at meandmymac.net.</h3></div>';
}

/*-------------------------------------------------------------
 Name:      adrotate_activate

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_activate() {
	global $wpdb;

	$current_version = 09;
	$old_version = get_option('adrotate_version');
	
	$table_name1 = $wpdb->prefix . "adrotate";
	$table_name2 = $wpdb->prefix . "adrotate_groups";
	if(!adrotate_mysql_table_exists($table_name1)) {
		$add1 = "CREATE TABLE ".$table_name1." (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `title` longtext NOT NULL,
			  `bannercode` longtext NOT NULL,
			  `thetime` int(15) NOT NULL default '0',
			  `updated` int(15) NOT NULL,
			  `author` varchar(60) NOT NULL default '',
			  `active` varchar(4) NOT NULL default 'yes',
			  `group` int(15) NOT NULL default '1',
			  `image` varchar(255) NOT NULL,
	  		PRIMARY KEY  (`id`)
			);";
	
		if(mysql_query($add1) === true) {
			$table1 = 1;
		}
	} else {
		$table1 = 1;
	}
		
	if(!adrotate_mysql_table_exists($table_name2)) {
		$add2 = "CREATE TABLE ".$table_name2." (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `name` varchar(255) NOT NULL,
	  		PRIMARY KEY  (`id`)
			);";
			
		if(mysql_query($add2) === true ) {
			$table2 = 1;
		}
	} else {
		$table2 = 1;
	}
	
	if($table1 == '1' AND $table2 == '1') {
		return true; //tables exist
	} else {
		adrotate_mysql_warning();
		exit;
	}
}
?>