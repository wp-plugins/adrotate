<?php
/*-------------------------------------------------------------
 Name:      adrotate_banner

 Purpose:   Show a banner as requested in the WP Theme or post
 Receive:   $group_ids, $banner_id, $preview, $shortcode
 Return:    $output
-------------------------------------------------------------*/
function adrotate_banner($group_ids, $banner_id = 0, $preview = false, $shortcode = false) {
	global $wpdb;

	if($group_ids) {
	
		$group_ids = explode(",", $group_ids);
		$x = array_rand($group_ids, 1);
		$now = current_time('timestamp');
		
		if($banner_id != 0) {
			$select_banner = " AND `id` = '".$banner_id."'";
		} else {
			$select_banner = " ORDER BY rand()";
		}
		
		if($preview == false) {
			$active_banner = " `active` = 'yes' AND";
			$show_banner = "'$now' >= `startshow` AND '$now' <= `endshow` AND ";
		}
		
		$SQL = "SELECT `id`, `bannercode`, `image`, `link`, `tracker` FROM `".$wpdb->prefix."adrotate` 
			WHERE ".$show_banner.$active_banner." `group` = '".$group_ids[$x]."' ".$select_banner." LIMIT 1";
		
		if($banner = $wpdb->get_row($SQL)) {
			$output = $banner->bannercode;
			if($banner->tracker == "Y") {
				$output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?trackerid='.$banner->id, $output);
			} else {
				$output = str_replace('%link%', $banner->link, $output);				
			}
			$output = str_replace('%image%', get_option('siteurl').'/wp-content/banners/'.$banner->image, $output);
			
			if($preview == false) {
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `shown` = `shown` + 1 WHERE `id` = '$banner->id'");
			}
		} else { 
			$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">The group is empty or the banners in it are disabled/expired!</span>';
		}
		
	} else {
	
		$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">Error, no group_id specified! Check your syntax!</span>';
	
	}
	
	$output = stripslashes(html_entity_decode($output, ENT_QUOTES));
	
	if($shortcode != false) {
		return $output;
	} else {
		echo $output;
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Show a banner as requested in a post or page using shortcodes
 Receive:   $atts, $content
 Return:    adrotate_banner()
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	if(!empty($atts['group'])) $group_ids = $atts['group'];
		else $group_ids = ''; 
	if(!empty($atts['banner'])) $banner_id = $atts['banner']; 
		else $banner_id = 0;
		
	return adrotate_banner($group_ids, $banner_id, false, true);
	
}

/*-------------------------------------------------------------
 Name:      adrotate_clean_trackerdata

 Purpose:   Removes old trackerdata
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_clean_trackerdata() {
	global $wpdb;

	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$remote_ip = $_SERVER["REMOTE_ADDR"];
	} else {
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	
	$buffer = explode(',', $remote_ip, 2);

	$removeme = current_time('timestamp') - 86400;
	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_tracker` WHERE `timer` < ".$removeme." AND `ipaddress` = '$buffer[0]'");
}

/*-------------------------------------------------------------
 Name:      adrotate_send_data

 Purpose:   Register events at meandmymac.net's database
 Receive:   $action
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_send_data($action) {
	$adrotate_tracker = get_option('adrotate_tracker');

	// Prepare data
	$date			= date('U');
	$plugin			= 'AdRotate';
	$version		= '2.0';
	//$action -> pulled from function args

	// User choose anonymous?
	if($adrotate_tracker['anonymous'] == 'Y') {
		$ident 		= 'Anonymous';
		$blogname 	= 'Anonymous';
		$blogurl	= 'Anonymous';
		$email		= 'Anonymous';
	} else {
		$ident 		= md5(get_option('siteurl'));
		$blogname	= get_option('blogname');
		$blogurl	= get_option('siteurl');
		$email		= get_option('admin_email');
	}

	// Build array of data
	$post_data = array (
		'headers'	=> null,
		'body'		=> array(
			'ident'		=> $ident,
			'blogname' 	=> base64_encode($blogname),
			'blogurl'	=> base64_encode($blogurl),
			'email'		=> base64_encode($email),
			'date'		=> $date,
			'plugin'	=> $plugin,
			'version'	=> $version,
			'action'	=> $action,
		),
	);

	// Destination
	$url = 'http://stats.meandmymac.net/receiver.php';

	wp_remote_post($url, $post_data);
}

/*-------------------------------------------------------------
 Name:      adrotate_check_config

 Purpose:   Create or update the options
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_check_config() {
	if ( !$tracker = get_option('adrotate_tracker') ) {
		$tracker['register']		 		= 'Y';
		$tracker['anonymous'] 				= 'N';
		update_option('adrotate_tracker', $tracker);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_options_submit

 Purpose:   Save options from dashboard
 Receive:   $_POST
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_options_submit() {	
	// Prepare Tracker settings
	if(isset($_POST['adrotate_register'])) $tracker['register'] = 'Y';			
		else $tracker['register'] = 'N';
	if(isset($_POST['adrotate_anonymous'])) $tracker['anonymous'] = 'Y';			
		else $tracker['anonymous'] = 'N';
	update_option('adrotate_tracker', $tracker);
}
?>