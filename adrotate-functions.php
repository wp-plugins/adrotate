<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Prepare function requests for calls on shortcodes
 Receive:   $atts, $content
 Return:    Function()
 Since:		0.7
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	/* Changelog:
	// Nov 9 2010 - Rewritten for 3.0, param 'column' obsolete, added 'fallback' override for groups
	// Nov 17 2010 - Added filters for empty values
	// Dec 13 2010 - Improved backward compatibility for single ads and blocks
	// Jan 16 2011 - Added $weight as an override for groups
	// Jan 24 2011 - Added $weight as an override for blocks
	*/

	if(!empty($atts['banner'])) 	$banner_id 	= trim($atts['banner'], "\r\t ");
	if(!empty($atts['group'])) 		$group_ids 	= trim($atts['group'], "\r\t ");
	if(!empty($atts['block']))		$block_id	= trim($atts['block'], "\r\t ");
	if(!empty($atts['fallback']))	$fallback	= trim($atts['fallback'], "\r\t "); // Optional for groups (override)
	if(!empty($atts['weight']))		$weight		= trim($atts['weight'], "\r\t "); // Optional for groups (override)
	if(!empty($atts['column']))		$columns	= trim($atts['column'], "\r\t "); // OBSOLETE

	if($banner_id > 0 AND ($group_ids == 0 OR $group_ids > 0) AND $block_id == 0) // Show one Ad
		return adrotate_ad($banner_id);

	if($banner_id == 0 AND $group_ids > 0 AND $block_id == 0) // Show group 
		return adrotate_group($group_ids, $fallback, $weight);

	if($banner_id == 0 AND $group_ids == 0 AND $block_id > 0) // Show block 
		return adrotate_block($block_id, $weight);
}

/*-------------------------------------------------------------
 Name:      adrotate_banner DEPRECATED

 Purpose:   Compatibility layer for old setups 
 Receive:   $group_ids, $banner_id, $block_id, $column
 Return:    Function()
 Added: 	0.1
-------------------------------------------------------------*/
function adrotate_banner($group_ids = 0, $banner_id = 0, $block_id = 0, $column = 0) {

	/* Changelog:
	// Nov 6 2010 - Changed function to form a compatibility layer for old setups, for ad output see adrotate_ad()
	// Nov 9 2010 - $block, Now accepts Block ID's only. $column OBSOLETE, no longer in use
	// Dec 6 2010 - Function DEPRECATED, maintained for backward compatibility
	*/
	
	if(($banner_id > 0 AND ($group_ids == 0 OR $group_ids == '')) OR ($banner_id > 0 AND $group_ids > 0 AND ($block_id == 0 OR $block_id == ''))) // Show one Ad
		return adrotate_ad($banner_id);

	if($group_ids != 0 AND ($banner_id == 0 OR $banner_id == '')) // Show group 
		return adrotate_group($group_ids);

	if($block_id > 0 AND ($banner_id == 0 OR $banner_id == '') AND ($group_ids == 0 OR $group_ids == '')) // Show block
		return adrotate_block($block_id);
}

/*-------------------------------------------------------------
 Name:      adrotate_weight

 Purpose:   Sort out and pick a random ad based on weight
 Receive:   $selected
 Return:    $key
 Since:		3.1
-------------------------------------------------------------*/
function adrotate_weight($selected) { 
    $rnd = mt_rand(0, array_sum($selected)-1);
    
    foreach($selected as $key => $var) { 
        if($rnd < $var) return $key; 
        $rnd -= $var; 
    } 
} 

/*-------------------------------------------------------------
 Name:      adrotate_array_unique

 Purpose:   Filter out duplicate records in multidimensional arrays
 Receive:   $array
 Return:    $array|$return
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_array_unique($array) {
	if(count($array) > 0) {
		if(is_array($array[0])) {
			$return = array();
			// multidimensional
			foreach($array as $row) {
				if(!in_array($row, $return)) {
					$return[] = $row;
				}
			}
			return $return;
		} else {
			// not multidimensional
			return array_unique($array);
		}
	} else {
		return $array;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_cache_statistics

 Purpose:   Cache statistics for viewing, every 6 hours
 Receive:   -None-
 Return:    -None-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_cache_statistics() {
	global $wpdb;
	
	$stats['lastclicks']			= adrotate_array_unique($wpdb->get_results("SELECT `timer`, `bannerid` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` != 0 ORDER BY `timer` DESC LIMIT 8;", ARRAY_A));
	$stats['thebest']				= $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' AND `type` = 'manual' ORDER BY `clicks` DESC LIMIT 1;", ARRAY_A);
	$stats['theworst']				= $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' AND `type` = 'manual' ORDER BY `clicks` ASC LIMIT 1;", ARRAY_A);
	$stats['banners'] 				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'manual';");
	$stats['tracker']				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['clicks']				= $wpdb->get_var("SELECT SUM(clicks) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['banners_tracker']		= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['impressions']			= $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'N' AND `type` = 'manual';");
	$stats['impressions_tracker']	= $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	
	if(!$stats['lastclicks']) 			array();
	if(!$stats['thebest']) 				array('title' => 0, 'clicks' => 0);
	if(!$stats['theworst']) 			array('title' => 0, 'clicks' => 0);
	if(!$stats['banners']) 				$stats['banners'] = 0;
	if(!$stats['tracker']) 				$stats['tracker'] = 0;
	if(!$stats['clicks']) 				$stats['clicks'] = 0;
	if(!$stats['banners_tracker']) 		$stats['banners_tracker'] = 0;
	if(!$stats['impressions']) 			$stats['impressions'] = 0;
	if(!$stats['impressions_tracker']) 	$stats['impressions_tracker'] = 0;
	
	$stats['total_impressions']		= $stats['impressions'] + $stats['impressions_tracker'];

	update_option('adrotate_stats', $stats);
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_user_cache_statistics

 Purpose:   Cache statistics for viewing, every 24 hours
 Receive:   $user
 Return:    -None-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_user_cache_statistics($user) {
	global $wpdb;

	$now = current_time('timestamp');
	$refresh = $now - 86400;
	$prefix = $wpdb->prefix;

	$ads = $wpdb->get_results("SELECT `ad` FROM `".$prefix."adrotate_linkmeta` WHERE `group` = 0 AND `block` = 0 AND `user` = '$user' ORDER BY `ad` ASC;");
	$timer = $wpdb->get_var("SELECT `timer` FROM `".$prefix."adrotate_tracker` WHERE `bannerid` = '$user';"); // BannerID used for user
	
	if($ads) {
		if($timer < $refresh) {
			$x = 0;
			
			$stats['thebest']	= $wpdb->get_row("SELECT `".$prefix."adrotate`.`title`, `".$prefix."adrotate`.`clicks` 
												FROM `".$prefix."adrotate`,`".$prefix."adrotate_linkmeta` 
												WHERE `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
													AND `".$prefix."adrotate`.`tracker` = 'Y' 
													AND `".$prefix."adrotate`.`active` = 'yes' 
													AND `".$prefix."adrotate`.`type` = 'manual' 
													AND `".$prefix."adrotate_linkmeta`.`user` = '$user' 
												ORDER BY `".$prefix."adrotate`.`clicks` DESC LIMIT 1;"
												, ARRAY_A);
			$stats['theworst']	= $wpdb->get_row("SELECT `".$prefix."adrotate`.`title`, `".$prefix."adrotate`.`clicks` 
												FROM `".$prefix."adrotate`,`".$prefix."adrotate_linkmeta` 
												WHERE `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
													AND `".$prefix."adrotate`.`tracker` = 'Y' 
													AND `".$prefix."adrotate`.`active` = 'yes' 
													AND `".$prefix."adrotate`.`type` = 'manual' 
													AND `".$prefix."adrotate_linkmeta`.`user` = '$user' 
												ORDER BY `".$prefix."adrotate`.`clicks` ASC LIMIT 1;"
												, ARRAY_A);
			$stats['ad_amount']	= count($ads);
	
			foreach($ads as $ad) {
				$meta = $wpdb->get_row("SELECT * FROM `".$prefix."adrotate` WHERE `id` = '$ad->ad' AND `type` = 'manual';");
				
				$adstats[$x]['id']				= $meta->id;			
				$adstats[$x]['title']			= $meta->title;			
				$adstats[$x]['startshow']		= $meta->startshow;
				$adstats[$x]['endshow']			= $meta->endshow;
				$adstats[$x]['clicks']			= $meta->clicks;
				$adstats[$x]['maxclicks']		= $meta->maxclicks;
				$adstats[$x]['impressions']		= $meta->shown;
				$adstats[$x]['maximpressions']	= $meta->maxshown;
		
				$stats['total_clicks']			= $stats['total_clicks'] + $meta->clicks;
				$stats['total_impressions']		= $stats['total_impressions'] + $meta->shown;
	
				$x++;
			}	
			$lastclicks			= adrotate_array_unique($wpdb->get_results("SELECT `".$prefix."adrotate_tracker`.`timer`, `".$prefix."adrotate_tracker`.`bannerid` 
																			FROM `".$prefix."adrotate`, `".$prefix."adrotate_tracker`, `".$prefix."adrotate_linkmeta` 
																			WHERE `".$prefix."adrotate_linkmeta`.`user` = '$user' 
																				AND `".$prefix."adrotate_linkmeta`.`group` = 0 
																				AND `".$prefix."adrotate_linkmeta`.`block` = 0 
																				AND `".$prefix."adrotate_tracker`.`ipaddress` != 0 
																				AND `".$prefix."adrotate_tracker`.`bannerid` = `".$prefix."adrotate_linkmeta`.`ad` 
																				AND `".$prefix."adrotate`.`tracker` = 'Y' 
																			ORDER BY `".$prefix."adrotate_tracker`.`timer` DESC LIMIT 8;"
																			, ARRAY_A));
			
			$stats['ads'] 					= $adstats;
			$stats['last_clicks']			= $lastclicks;
		
			if(!$stats['thebest']) 			$stats['thebest']		= array('title' => 0, 'clicks' => 0);
			if(!$stats['theworst']) 		$stats['theworst']		= array('title' => 0, 'clicks' => 0);
			if(!$stats['ads']) 				$stats['ads'] 			= array();
			if(!$stats['last_clicks']) 		$stats['last_clicks'] 	= array();
		
			$stats = serialize($stats);
		
			$exists = $wpdb->get_var("SELECT COUNT(*) FROM `".$prefix."adrotate_stats_cache` WHERE `user` = '$user';");
	
			if($exists == 0) {
				$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_stats_cache` (`user`, `cache`) VALUES ('$user', '$stats');");
				$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_tracker` (`ipaddress`, `timer`, `bannerid`) VALUES (0, '$now', '$user');");
			}
			
			if($exists == 1) {
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_stats_cache` SET `cache` = '$stats' WHERE `user` = '$user';");
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_tracker` SET `timer` = '$now' WHERE `bannerid` = '$user';");
			}
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_color

 Purpose:   Check if ads are expired and set a color for its end date
 Receive:   $banner_id
 Return:    $result
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_color($enddate) {
	$now = current_time('timestamp');
	$in2days = $now + 172800;
	$in7days = $now + 604800;
	
	if($enddate <= $now) {
		return '#F30'; // red
	} else if($enddate <= $in2days AND $enddate >= $now) {
		return '#F90'; // orange
	} else if($enddate <= $in7days AND $enddate >= $now) {
		return '#FC0'; // yellow
	} else {
		return '#0C0'; // green
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_group_is_in_blocks

 Purpose:   Build list of blocks the group is in (editing)
 Receive:   $id
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_group_is_in_blocks($id) {
	global $wpdb;
	
	$output = '';
	$linkmeta = $wpdb->get_results("SELECT `block` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `group` = '$id' AND `block` > 0 AND `user` = 0 ORDER BY `block` ASC;");
	if($linkmeta) {
		foreach($linkmeta as $meta) {
			$blockname = $wpdb->get_var("SELECT `name` FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '".$meta->block."';");
			$output .= '<a href="'.get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=edit&edit_block='.$meta->block.'" title="Edit Block">'.$blockname.'</a>, ';
		}
	} else {
		$output .= "This group is not in a block!";
	}
	$output = rtrim($output, " ,");
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_clean_trackerdata

 Purpose:   Removes old trackerdata
 Receive:   -none-
 Return:    -none-
 Since:		2.0
-------------------------------------------------------------*/
function adrotate_clean_trackerdata() {
	global $wpdb;

	$removeme = current_time('timestamp') - 86400;
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_tracker` WHERE `timer` < ".$removeme." AND `ipaddress` > 0;");
}

/*-------------------------------------------------------------
 Name:      adrotate_check_banners

 Purpose:   Check if ads are expired, or are about to
 Receive:   -none-
 Return:    $result
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_check_banners() {
	global $wpdb;

	$now = current_time('timestamp');
	$in2days = $now + 172800;
	
	$alreadyexpired = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $now;");
	$expiressoon = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $in2days AND `endshow` >= $now;");
	$error = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'error';");

	$count = $alreadyexpired + $expiressoon + $error;
	
	$result = array('expired' => $alreadyexpired,
					'expiressoon' => $expiressoon,
					'error' => $error,
					'total' => $count);

	return $result;	
}

/*-------------------------------------------------------------
 Name:      adrotate_check_config

 Purpose:   Update the options
 Receive:   -none-
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_check_config() {

	/* Changelog:
	// Jan 3 2011 - Changed to a per setting model
	// Jan 16 2011 - Added notification email conversion to array
	// Jan 20 2011 - Updated user capabilities to work with new access rights system
	// Jan 20 2011 - Added debug switch (defaults to false)
	// Jan 23 2011 - Added option to disable email notifications
	// Jan 24 2011 - Renamed $crawlers to $debug for debugger if()
	*/
	
	$config 	= get_option('adrotate_config');
	$crawlers 	= get_option('adrotate_crawlers');
	$debug 		= get_option('adrotate_debug');

	if($config['userstatistics'] == '' OR !isset($config['userstatistics'])) 		$config['userstatistics']		= 'switch_themes'; 	// Admin
	if($config['globalstatistics'] == '' OR !isset($config['globalstatistics'])) 	$config['globalstatistics']		= 'switch_themes'; 	// Admin
	if($config['ad_manage'] == '' OR !isset($config['ad_manage'])) 					$config['ad_manage'] 			= 'switch_themes'; 	// Admin
	if($config['ad_delete'] == '' OR !isset($config['ad_delete'])) 					$config['ad_delete']			= 'switch_themes'; 	// Admin
	if($config['group_manage'] == '' OR !isset($config['group_manage'])) 			$config['group_manage']			= 'switch_themes'; 	// Admin
	if($config['group_delete'] == '' OR !isset($config['group_delete'])) 			$config['group_delete']			= 'switch_themes'; 	// Admin
	if($config['block_manage'] == '' OR !isset($config['block_manage'])) 			$config['block_manage']			= 'switch_themes'; 	// Admin
	if($config['block_delete'] == '' OR !isset($config['block_delete'])) 			$config['block_delete']			= 'switch_themes'; 	// Admin
	if($config['notification_email_switch'] == '' OR !isset($config['notification_email_switch']))	$config['notification_email_switch']	= 'Y';
	if($config['notification_email'] == '' OR !isset($config['notification_email']) OR !is_array($config['notification_email']))	$config['notification_email']	= array(get_option('admin_email'));
	if($config['advertiser_email'] == '' OR !isset($config['advertiser_email']) OR !is_array($config['advertiser_email']))	$config['advertiser_email']	= array(get_option('admin_email'));
	if($config['credits'] == '' OR !isset($config['credits']))						$config['credits'] 				= 'Y';
	if($config['browser'] == '' OR !isset($config['browser']))						$config['browser'] 				= 'Y';
	if($config['widgetalign'] == '' OR !isset($config['widgetalign']))				$config['widgetalign'] 			= 'N';
	update_option('adrotate_config', $config);
	
	if($crawlers == '' OR !isset($crawlers)) 		$crawlers 	= array("bot", "crawler", "spider", "google", "yahoo", "msn", "ask", "ia_archiver");
	update_option('adrotate_crawlers', $crawlers);

	if($debug == '' OR !isset($debug)) 				$debug 		= false;
	update_option('adrotate_debug', $debug);
}

/*-------------------------------------------------------------
 Name:      adrotate_get_sorted_roles

 Purpose:   Returns all roles and capabilities, sorted by user level. Lowest to highest. (Code based on NextGen Gallery)
 Receive:   -none-
 Return:    $sorted
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_get_sorted_roles() {	
	global $wp_roles;

	/* Changelog:
	// Jan 22 2011 - Dropped get_editable_roles(), function is broken in pre-WP 3.1 versions.
	*/
	
	$editable_roles = apply_filters('editable_roles', $wp_roles->roles);
	$sorted = array();
	
	foreach($editable_roles as $role => $details) {
		$sorted[$details['name']] = get_role($role);
	}

	$sorted = array_reverse($sorted);

	return $sorted;
}

/*-------------------------------------------------------------
 Name:      adrotate_get_role

 Purpose:   Return the lowest roles which has the capabilities (Code borrowed from NextGen Gallery)
 Receive:   $capability
 Return:    Boolean|$check_role->name
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_get_role($capability){
	$check_order = adrotate_get_sorted_roles();
	$args = array_slice(func_get_args(), 1);
	$args = array_merge(array($capability), $args);

	foreach($check_order as $check_role) {
		if(empty($check_role)) return false;
		if(call_user_func_array(array(&$check_role, 'has_cap'), $args)) return $check_role->name;
	}
	return false;
}

/*-------------------------------------------------------------
 Name:      adrotate_set_capability

 Purpose:   Grant or revoke capabilities to a role (Code borrowed from NextGen Gallery)
 Receive:   $lowest_role, $capability
 Return:    -None-
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_set_capability($lowest_role, $capability){

	/* Changelog:
	// Jan 21 2011 - Fixed $the_role to $role
	*/
	
	$check_order = adrotate_get_sorted_roles();
	$add_capability = false;
	
	foreach($check_order as $role) {
		if($lowest_role == $role->name) 
			$add_capability = true;
			
		if(empty($role)) 
			continue;
			
		$add_capability ? $role->add_cap($capability) : $role->remove_cap($capability) ;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_remove_capability

 Purpose:   Remove the $capability from the all roles (Based on NextGen Gallery)
 Receive:   $capability
 Return:    -None-
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_remove_capability($capability){

	/* Changelog:
	// Jan 21 2011 - Fixed $role to $role->name
	*/
	
	$check_order = adrotate_get_sorted_roles();

	foreach($check_order as $role) {
		$role = get_role($role->name);
		$role->remove_cap($capability);
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_notifications_dashboard

 Purpose:   Notify user of expired banners in the dashboard
 Receive:   -none-
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_notifications_dashboard() {

	$data = adrotate_check_banners();

	if($data['total'] > 0) {
		if($data['expired'] > 0 AND $data['expiressoon'] == 0 AND $data['error'] == 0) {
			echo '<div class="error"><p>'.$data['expired'].' ad(s) expired. <a href="admin.php?page=adrotate">Take action now</a>!</p></div>';
		} else if($data['expired'] == 0 AND $data['expiressoon'] > 0 AND $data['error'] == 0) {
			echo '<div class="error"><p>'.$data['expiressoon'].' ad(s) are about to expire. <a href="admin.php?page=adrotate">Check it out</a>!</p></div>';
		} else if($data['expired'] == 0 AND $data['expiressoon'] == 0 AND $data['error'] > 0) {
			echo '<div class="error"><p>There are '.$data['error'].' ad(s) with configuration errors. <a href="admin.php?page=adrotate">Solve this</a>!</p></div>';
		} else {
			echo '<div class="error"><p>'.$data['total'].' ads require your attention. <a href="admin.php?page=adrotate">Fix this as soon as possible</a>!</p></div>';
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_mail_notifications

 Purpose:   Email the manager that his ads need help
 Receive:   -None-
 Return:    -None-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_mail_notifications() {
	global $adrotate_config;
	
	/* Changelog:
	// Jan 3 2011 - Changed to a per setting model
	// Jan 16 2011 - Added support for multiple email addresses
	// Jan 24 2011 - Removed test notification (obsolete), cleaned up code
	*/
	
	$emails = $adrotate_config['notification_email'];
	$x = count($emails);
	if($x == 0) $emails = array(get_option('admin_email'));
	
	$blogname 		= get_option('blogname');
	$siteurl 		= get_option('siteurl');
	$dashboardurl	= $siteurl."/wp-admin/admin.php?page=adrotate";
	$pluginurl		= "http://www.adrotateplugin.com";

	$data = adrotate_check_banners();
	for($i=0;$i<$x;$i++) {
		if($data[2] > 0) {
		    $headers = "MIME-Version: 1.0\n" .
      				 	"From: AdRotate Plugin <".$emails[$i].">\r\n\n" . 
      				  	"Content-Type: text/html; charset=\"" . get_settings('blog_charset') . "\"\n";

			$subject = '[AdRotate Alert] Your ads need your help!';
			
			$message = "<p>Hello,</p>";
			$message .= "<p>This notification is send to you from your website '$blogname'.</p>";
			$message .= "<p>You will receive a notification approximately every 24 hours until the issues are resolved.</p>";
			$message .= "<p>Current issues:<br />";
			if($data[0] > 0) $message .= $data[0]." ad(s) expired. This needs your immediate attention!<br />";
			if($data[1] > 0) $message .= $data[1]." ad(s) will expire in less than 2 days.<br />";
			$message .= "</p>";
			$message .= "<p>A total of ".$data[2]." ad(s) are in need of your care!</p>";
			$message .= "<p>Access your dashboard here: $dashboardurl</p>";
			$message .= "<p>Have a nice day!</p>";
			$message .= "<p>Your AdRotate Notifier<br />";
			$message .= "$pluginurl</p>";

			wp_mail($emails[$i], $subject, $message, $headers);
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_mail_message

 Purpose:   Send advertiser messages
 Receive:   -None-
 Return:    -None-
 Since:		3.1
-------------------------------------------------------------*/
function adrotate_mail_message() {
	global $wpdb, $adrotate_config;

	/* Changelog:
	// Jan 3 2011 - Changed to a per setting model
	// Jan 16 2011 - Added new message type 'issue', array() support for multiple emails
	// Jan 24 2011 - Change to its own email array, updated layout and formatting
	*/
	
	$id 			= $_POST['adrotate_id'];
	$request 		= $_POST['adrotate_request'];
	$author 		= $_POST['adrotate_username'];
	$useremail 		= $_POST['adrotate_email'];
	$text	 		= strip_tags(stripslashes(trim($_POST['adrotate_message'], "\t\n ")));

	if(strlen($text) < 1) $text = "";
	
	$emails = $adrotate_config['advertiser_email'];
	$x = count($emails);
	if($x == 0) $emails = array(get_option('admin_email'));
	
	$siteurl 		= get_option('siteurl');
	$adurl			= $siteurl."/wp-admin/admin.php?page=adrotate&view=edit&edit_ad=".$id;
	$pluginurl		= "http://www.adrotateplugin.com";

	for($i=0;$i<$x;$i++) {
	    $headers 		= "MIME-Version: 1.0\n" .
	      				  "From: $author <".$useremail.">\r\n\n" . 
	      				  "Content-Type: text/html; charset=\"" . get_settings('blog_charset') . "\"\n";
		$now 			= current_time('timestamp');
		
		if($request == "renew") $subject = "[AdRotate] An advertiser has put in a request for renewal!";
		if($request == "remove") $subject = "[AdRotate] An advertiser wants his ad removed.";
		if($request == "report") $subject = "[AdRotate] An advertiser wrote a comment on his ad!";
		if($request == "issue") $subject = "[AdRotate] An advertiser has a problem!";
		
		$message = "<p>Hello,</p>";
	
		if($request == "renew") $message .= "<p>$author requests ad <strong>$id</strong> renewed!</p>";
		if($request == "remove") $message .= "<p>$author requests ad <strong>$id</strong> removed.</p>";
		if($request == "report") $message .= "<p>$author has something to say about ad <strong>$id</strong>.</p>";
		if($request == "issue") $message .= "<p>$author has a problem with AdRotate.</p>";
		
		$message .= "<p>Attached message: $text</p>";
		
		$message .= "<p>You can reply to this message to contact $author.<br />";
		if($request != "issue") $message .= "Review the ad here: $adurl";
		$message .= "</p>";
		
		$message .= "<p>Have a nice day!<br />";
		$message .= "Your AdRotate Notifier<br />";
		$message .= "$pluginurl</p>";
	
		wp_mail($emails[$i], $subject, $message, $headers);
	}

	adrotate_return('mail_sent');
}

/*-------------------------------------------------------------
 Name:      adrotate_reccurences

 Purpose:   Add more reccurances to the wp_cron feature
 Receive:   -none-
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_reccurences() {
	return array(
		'1day' => array(
			'interval' => 86400, 
			'display' => 'Every day'
		),
		'6hour' => array(
			'interval' => 21600, 
			'display' => 'Every 6 hours'
		),
		'weekly' => array(
			'interval' => 604800, 
			'display' => 'Once Weekly'
		),
	);
}

/*-------------------------------------------------------------
 Name:      adrotate_folder_contents

 Purpose:   List folder contents of /wp-content/banners and /wp-content/uploads
 Receive:   $current
 Return:	$output
 Since:		0.4
-------------------------------------------------------------*/
function adrotate_folder_contents($current) {
	global $wpdb, $adrotate_config;

	$output = '';

	// Read /wp-content/banners/
	$files = array();
	if ($handle = opendir(ABSPATH.'/wp-content/banners/')) {
	    while (false !== ($file = readdir($handle))) {
	        if ($file != "." AND $file != ".." AND $file != "index.php") {
	            $files[] = $file;
	        	$i++;
	        }
	    }
	    closedir($handle);
	    if($i==0) {
	    	$output .= "<option disabled>&nbsp;&nbsp;&nbsp;No files found</option>";
		} else {
			sort($files);
			$output .= "<option disabled>-- Banners folder --</option>";
			foreach($files as $file) {
				$fileinfo = pathinfo($file);
		
				if((strtolower($fileinfo['extension']) == "jpg" OR strtolower($fileinfo['extension']) == "gif" OR strtolower($fileinfo['extension']) == "png" 
				OR strtolower($fileinfo['extension']) == "jpeg" OR strtolower($fileinfo['extension']) == "swf" OR strtolower($fileinfo['extension']) == "flv")) {
				    $output .= "<option value='banner|".$file."'";
				    if($current == get_option('siteurl').'/wp-content/banners/'.$file) { $output .= "selected"; }
				    $output .= ">".$file."</option>";
				}
			}
		}
	}

	// Read /wp-content/uploads/ from the WP database
	if($adrotate_config['browser'] == 'Y') {
		$uploadedmedia = $wpdb->get_results("SELECT `guid` FROM ".$wpdb->prefix."posts 
			WHERE `post_type` = 'attachment' 
			AND (`post_mime_type` = 'image/jpeg' 
				OR `post_mime_type` = 'image/gif' 
				OR `post_mime_type` = 'image/png'
				OR `post_mime_type` = 'application/x-shockwave-flash')
			ORDER BY `post_title` ASC");
		
		$output .= "<option disabled>-- Uploaded Media --</option>";
		if($uploadedmedia) {
			foreach($uploadedmedia as $media) {
		        $output .= "<option value='media|".basename($media->guid)."'";
		        if($current == $media->guid) { $output .= "selected"; }
		        $output .= ">".basename($media->guid)."</option>";
			}
		} else {
			$output .= "<option disabled>&nbsp;&nbsp;&nbsp;No media found</option>";
		}
	}
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_return

 Purpose:   Internal redirects
 Receive:   $action, $arg
 Return:    -none-
 Since:		0.2
-------------------------------------------------------------*/
function adrotate_return($action, $arg = null) {

	/* Changelog:
	// Nov ? 2010 - Added block support
	// Jan 24 2011 - Added default action, removed email_timer, mail_sent, renamed mail_request_sent to mail_sent
	*/

	switch($action) {
		// Manage Ads
		case "new" :
			wp_redirect('admin.php?page=adrotate&message=created');
		break;

		case "update" :
			wp_redirect('admin.php?page=adrotate&view=edit&message=updated&edit_ad='.$arg[0]);
		break;

		case "delete" :
			wp_redirect('admin.php?page=adrotate&message=deleted');
		break;

		case "reset" :
			wp_redirect('admin.php?page=adrotate&message=reset');
		break;

		case "renew" :
			wp_redirect('admin.php?page=adrotate&message=renew');
		break;

		case "deactivate" :
			wp_redirect('admin.php?page=adrotate&message=deactivate');
		break;

		case "activate" :
			wp_redirect('admin.php?page=adrotate&message=activate');
		break;

		case "field_error" :
			wp_redirect('admin.php?page=adrotate&message=field_error');
		break;

		// Groups
		case "group_new" :
			wp_redirect('admin.php?page=adrotate-groups&message=created');
		break;

		case "group_edit" :
			wp_redirect('admin.php?page=adrotate-groups&view=edit&message=updated&edit_group='.$arg[0]);
		break;

		case "group_delete" :
			wp_redirect('admin.php?page=adrotate-groups&message=deleted');
		break;

		case "group_delete_banners" :
			wp_redirect('admin.php?page=adrotate-groups&message=deleted_banners');
		break;

		// Blocks
		case "block_new" :
			wp_redirect('admin.php?page=adrotate-blocks&message=created');
		break;

		case "block_edit" :
			wp_redirect('admin.php?page=adrotate-blocks&view=edit&message=updated&edit_block='.$arg[0]);
		break;

		case "block_delete" :
			wp_redirect('admin.php?page=adrotate-blocks&message=deleted');
		break;

		// Misc plugin events
		case "role_add" :
			wp_redirect('admin.php?page=adrotate-settings&message=role_add');
		break;

		case "role_remove" :
			wp_redirect('admin.php?page=adrotate-settings&message=role_remove');
		break;

		case "mail_sent" :
			wp_redirect('admin.php?page=adrotate-userstatistics&message=mail_sent');
		break;

		case "settings_saved" :
			wp_redirect('admin.php?page=adrotate-settings&message=updated');
		break;

		case "db_optimized" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_optimized');
		break;

		case "db_repaired" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_optimized');
		break;

		case "db_timer" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_timer');
		break;

		case "no_access" :
			wp_redirect('admin.php?page=adrotate&message=no_access');
		break;

		case "error" :
			wp_redirect('admin.php?page=adrotate&message=error');
		break;

		default:
			wp_redirect('admin.php?page=adrotate');
		break;

	}
}

/*-------------------------------------------------------------
 Name:      adrotate_error

 Purpose:   Show errors for problems in using AdRotate, should they occur
 Receive:   $action
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_error($action, $arg = null) {

	/* Changelog:
	// Dec 26 2010 - Added more errors from other functions
	*/

	switch($action) {
		// Ads
		case "ad_expired" :
			$result = '<!-- Error, Ad (ID: '.$arg[0].') is expired or does not exist! -->';
			return $result;
		break;
		
		case "ad_unqualified" :
			$result = '<!-- Either there are no banners, they are disabled or none qualified for this location! -->';
			return $result;
		break;
		
		case "ad_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">Error, no Ad ID set! Check your syntax!</span>';
			return $result;
		break;

		case "ad_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">Error, ad could not be found! Make sure it exists.</span>';
			return $result;
		break;

		// Groups
		case "group_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">Error, no group set! Check your syntax!</span>';
			return $result;
		break;

		// Blocks
		case "block_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">Error, Block (ID: '.$arg[0].') does not exist! Check your syntax!</span>';
			return $result;
		break;

		case "block_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">Error, no Block ID set! Check your syntax!</span>';
			return $result;
		break;

		// Database
		case "db_error" :
			$result = '<span style="font-weight: bold; color: #f00;">There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!<br />If this does not solve the issue please seek support at <a href="http://www.adrotateplugin.com/page/support.php">www.adrotateplugin.com/page/support.php</a></span>';
			return $result;
		break;

		// Misc
		default:
			$default = 'An unknown error occured.';
			return $default;
		break;

	}
}
?>