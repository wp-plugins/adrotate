<?php  
/*-------------------------------------------------------------
 Name:      adrotate_banner

 Purpose:   Show a banner as requested in the WP Theme or post
 Receive:   $group_ids, $banner_id, $block, $preview
 Return:    $output
-------------------------------------------------------------*/
function adrotate_banner($group_ids, $banner_id = 0, $block = 0, $preview = false) {
	global $wpdb;

	if($group_ids) {
		$group_ids = explode(",", $group_ids);
		$x = array_rand($group_ids, 1);
		$now = current_time('timestamp');

		if($block > 0 AND $banner_id < 1) {
			$limit = $block;
		} else {
			$limit = 1;
		}

		if($banner_id > 0 AND $block < 1) {
			$select_banner = " AND `id` = '".$banner_id."'";
		} else {
			$select_banner = " ORDER BY rand()";
		}

		if($preview == false) {
			$active_banner = " `active` = 'yes' AND";
			$show_banner = "'$now' >= `startshow` AND '$now' <= `endshow` AND ";
		}

		$SQL = "SELECT `id`, `bannercode`, `image`, `link`, `tracker` FROM `".$wpdb->prefix."adrotate` WHERE ".$show_banner.$active_banner." `group` = '".$group_ids[$x]."' ".$select_banner." LIMIT $limit";
		if($banners = $wpdb->get_results($SQL)) {
			$output = '';
			foreach($banners as $banner) {
				$banner_output = $banner->bannercode;
				if($banner->tracker == "Y") {
					$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate-pro/adrotate-out.php?trackerid='.$banner->id, $banner_output);
				} else {
					$banner_output = str_replace('%link%', $banner->link, $banner_output);
				}
				$banner_output = str_replace('%image%', get_option('siteurl').'/wp-content/banners/'.$banner->image, $banner_output);

				$output .= $banner_output;

				if($preview == false) {
					$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `shown` = `shown` + 1 WHERE `id` = '$banner->id'");
				}
			}
		} else {
			$output = '<!-- The group is empty or the banners in it are disabled/expired! -->';
		}

	} else {
		$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">Error, no group_id specified! Check your syntax or contact an administrator!</span>';
	}

	$output = stripslashes(html_entity_decode($output, ENT_QUOTES));

	return $output;
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
	if(!empty($atts['block'])) $block = $atts['block'];
		else $block = 0;
	if(!empty($atts['banner'])) $banner_id = $atts['banner'];
		else $banner_id = 0;

	return adrotate_banner($group_ids, $block, $banner_id, false);
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
 Name:      adrotate_expired_banners

 Purpose:   Notify user of expired banners
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_expired_banners() {
	global $wpdb;

	$now = current_time('timestamp');
	$count = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $now");
	
	if($count > 0) {
		if($count == 1) $tell = '1 banner is';
		 	else $tell = $count.' banners are';
		
		echo '<div class="updated fade"><p>'.$tell.' expired. <a href="admin.php?page=adrotate">Take action</a>!</p></div>';
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_credits

 Purpose:   Credits
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_credits() {
	echo '<table class="widefat" style="margin-top: .5em">';
	
	echo '<thead>';
	echo '<tr valign="top">';
	echo '	<th>AdRotate for Awesome!</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr>';
	echo '	<td>Find me on <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>.<br />';
	echo '	Need help? <a href="http://forum.at.meandmymac.net" target="_blank">forum.at.meandmymac.net</a>.<br />';
	echo '	Subscribed to the Meandmymac Data Project? Curious? <a href="http://meandmymac.net/plugins/data-project/" target="_blank">More information</a>.<br />';
	echo '	Want to see your stats? <a href="http://meandmymac.net/wp-admin/" target="_blank">Plugin statistics</a>.<br />';
	echo '	Like my software? <a href="http://meandmymac.net/donate/" target="_blank">Show your appreciation</a>. Thanks!</td>';
	echo '</tr>';
	echo '</tbody>';
	
	echo '</table';
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
	$plugin			= 'AdRotate Pro';
	$version		= '2.3';
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
	$buffer = get_option('adrotate_tracker');

	// Prepare Tracker settings
	if(isset($_POST['adrotate_register'])) $tracker['register'] = 'Y';
		else $tracker['register'] = 'N';
	if(isset($_POST['adrotate_anonymous'])) $tracker['anonymous'] = 'Y';
		else $tracker['anonymous'] = 'N';
	if($tracker['register'] == 'N' AND $buffer['register'] == 'Y') { adrotate_send_data('Opt-out'); }

	update_option('adrotate_tracker', $tracker);
}

/*-------------------------------------------------------------
 Name:      adrotate_folder_contents

 Purpose:   List folder contents
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_folder_contents($current) {
	global $wpdb;
	
	if ($handle = opendir(ABSPATH.'/wp-content/banners/')) {
		$output = '';
	    while (false !== ($file = readdir($handle))) {
			$fileinfo = pathinfo($file);
	
	        if ($file != "." && $file != ".." 
	        AND (strtolower($fileinfo['extension']) == "jpg" OR strtolower($fileinfo['extension']) == "gif" 
	        OR strtolower($fileinfo['extension']) == "png" OR strtolower($fileinfo['extension']) == "jpeg"
	        OR strtolower($fileinfo['extension']) == "swf" OR strtolower($fileinfo['extension']) == "flv")) {
	            $output .= "<option ";
	            if($current == $file) { $output .= "selected"; }
	            $output .= ">".$file."</option>";
	        }
	    }
	    closedir($handle);
	}
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_return

 Purpose:   Redirect to various pages
 Receive:   $action, $arg
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_return($action, $arg = null) {
	switch($action) {
		case "new" :
			wp_redirect('admin.php?page=adrotate3&message=created');
		break;
		
		case "magic_new" :
			wp_redirect('admin.php?page=adrotate&message=created');
		break;
		
		case "group_new" :
			wp_redirect('admin.php?page=adrotate4&message=created');
		break;
		
		case "group_edit" :
			wp_redirect('admin.php?page=adrotate4&message=updated');
		break;
		
		case "update" :
			wp_redirect('admin.php?page=adrotate3&message=updated&edit_banner='.$arg[0]);
		break;
		
		case "group_field_error" :
			wp_redirect('admin.php?page=adrotate4&message=field_error&edit_group='.$arg[0]);
		break;
		
		case "magic_field_error" :
			wp_redirect('admin.php?page=adrotate2&message=field_error&step='.$arg[0].'&magic_id='.$arg[1]);
		break;
		
		case "field_error" :
			wp_redirect('admin.php?page=adrotate3&message=field_error');
		break;
		
		case "no_access" :
			wp_redirect('admin.php?page=adrotate&message=no_access');
		break;
		
		case "deactivate" :
			wp_redirect('admin.php?page=adrotate&message=deactivate');
		break;
		
		case "activate" :
			wp_redirect('admin.php?page=adrotate&message=activate');
		break;
		
		case "reset" :
			wp_redirect('admin.php?page=adrotate3&message=reset&edit_banner='.$arg[0]);
		break;
		
		case "resetmultiple" :
			wp_redirect('admin.php?page=adrotate&message=reset');
		break;
		
		case "delete" :
			wp_redirect('admin.php?page=adrotate&message=deleted');
		break;
		
		case "group_delete" :
			wp_redirect('admin.php?page=adrotate4&message=deleted');
		break;
		
		case "error" :
			wp_redirect('admin.php?page=adrotate&message=error');
		break;
		
		case "magic_error" :
			wp_redirect('admin.php?page=adrotate2&step='.$arg[0].'&message=error');
		break;
	}
}
?>