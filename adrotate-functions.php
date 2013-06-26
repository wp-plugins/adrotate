<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Prepare function requests for calls on shortcodes
 Receive:   $atts, $content
 Return:    Function()
 Since:		0.7
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {
	global $adrotate_config;

	$banner_id = $group_ids = $block_id = $fallback = $weight = $columns = '';
	if(!empty($atts['banner'])) $banner_id = trim($atts['banner'], "\r\t ");
	if(!empty($atts['group'])) $group_ids = trim($atts['group'], "\r\t ");
	if(!empty($atts['block'])) $block_id = trim($atts['block'], "\r\t ");
	if(!empty($atts['fallback'])) $fallback	= trim($atts['fallback'], "\r\t "); // Optional for groups (override)
	if(!empty($atts['weight']))	$weight	= trim($atts['weight'], "\r\t "); // Optional for groups (override)

	$output = '';

	if($adrotate_config['w3caching'] == "Y") $output .= '<!-- mfunc -->';

	if($banner_id > 0 AND ($group_ids == 0 OR $group_ids > 0) AND $block_id == 0) { // Show one Ad
		if($adrotate_config['supercache'] == "Y") $output .= '<!--mfunc echo adrotate_ad( $banner_id ) -->';
		$output .= adrotate_ad($banner_id);
		if($adrotate_config['supercache'] == "Y") $output .= '<!--/mfunc-->';
	}

	if($banner_id == 0 AND $group_ids > 0 AND $block_id == 0) { // Show group 
		if($adrotate_config['supercache'] == "Y") $output .= '<!--mfunc echo adrotate_group( $group_ids, $fallback, $weight ) -->';
		$output .= adrotate_group($group_ids, $fallback, $weight);
		if($adrotate_config['supercache'] == "Y") $output .= '<!--/mfunc-->';
	}

	if($banner_id == 0 AND $group_ids == 0 AND $block_id > 0) { // Show block 
		if($adrotate_config['supercache'] == "Y") $output .= '<!--mfunc echo adrotate_block( $block_id, $weight ) -->';
		$output .= adrotate_block($block_id, $weight);
		if($adrotate_config['supercache'] == "Y") $output .= '<!--/mfunc-->';
	}

	if($adrotate_config['w3caching'] == "Y") $output .= '<!-- /mfunc -->';

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_filter_schedule

 Purpose:   Weed out ads that are over the limit of their schedule
 Receive:   $selected, $banner
 Return:    $selected
 Since:		3.6.11
-------------------------------------------------------------*/
function adrotate_filter_schedule($selected, $banner) { 
	global $wpdb, $adrotate_config, $adrotate_debug;

	$now = adrotate_now();
	$prefix = $wpdb->prefix;

	if($adrotate_debug['general'] == true) {
		echo "<p><strong>[DEBUG][adrotate_filter_schedule()] Filtering banner</strong><pre>";
		print_r($banner->id); 
		echo "</pre></p>"; 
	}
	
	// Get schedules for advert
	$schedules = $wpdb->get_results("SELECT `id`, `starttime`, `stoptime`, `maxclicks`, `maximpressions` FROM `".$prefix."adrotate_schedule` WHERE `ad` = '".$banner->id."' ORDER BY `starttime` ASC LIMIT 1 ;");

	$current = array();
	foreach($schedules as $schedule) {
		
		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_filter_schedule()] Schedule (id: ".$schedule->id.")</strong><pre>";
			echo "<br />Start: ".$schedule->starttime." (".date("F j, Y, g:i a", $schedule->starttime).")";
			echo "<br />End: ".$schedule->stoptime." (".date("F j, Y, g:i a", $schedule->stoptime).")";
			echo "</pre></p>";
		}

		if($adrotate_config['enable_stats'] == 'Y') {
			$stat = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$prefix."adrotate_stats` WHERE `ad` = ".$banner->id." AND `thetime` >= ".$schedule->starttime." AND `thetime` <= ".$schedule->stoptime.";");

			// Ad exceeded max clicks?
			if($stat->clicks >= $schedule->maxclicks AND $schedule->maxclicks > 0 AND $banner->tracker == "Y") {
				$selected = array_diff_key($selected, array($banner->id => 0));
			}
		
			// Ad exceeded max impressions?
			if($stat->impressions >= $schedule->maximpressions AND $schedule->maximpressions > 0) {
				$selected = array_diff_key($selected, array($banner->id => 0));
			}
		}

		if($schedule->starttime > $now OR $schedule->stoptime < $now) {
			$current[] = 0;
		} else {
			$current[] = 1;
		}
	}
	
	if($adrotate_debug['general'] == true) {
		echo "<p><strong>[DEBUG][adrotate_filter_schedule()] Current</strong><pre>";
		print_r($current); 
		echo "</pre></p>"; 
	}
	
	// Remove advert from array if all schedules are false (0)
	if(!in_array(1, $current)) {
		$selected = array_diff_key($selected, array($banner->id => 0));
	}
	unset($current);
	
	return $selected;
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
 Name:      adrotate_rand

 Purpose:   Generate a random string
 Receive:   $length
 Return:    $result
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_rand($length = 8) {
	$available_chars = "abcdefghijklmnopqrstuvwxyz";	

	$result = '';
	$size = strlen($available_chars);
	for($i = 0; $i < $length; $i++) {
		$result .= $available_chars[rand(0, $size - 1)];
	}

	return $result;
}

/*-------------------------------------------------------------
 Name:      adrotate_select_categories

 Purpose:   Create scrolling menu of all categories.
 Receive:   $savedcats, $count, $child_of, $parent
 Return:    $output
 Since:		3.8.4
-------------------------------------------------------------*/
function adrotate_select_categories($savedcats, $count = 2, $child_of = 0, $parent = 0) {
	if(!is_array($savedcats)) $savedcats = explode(',', $savedcats);
	$categories = get_categories(array('child_of' => $parent, 'parent' => $parent,  'orderby' => 'id', 'order' => 'asc', 'hide_empty' => 0));

	if(!empty($categories)) {
		$output = '';
		foreach($categories as $category) {
			if($category->parent > 0) {
				if($category->parent != $child_of) { 
					$count = $count + 1;
				}
				$indent = '&nbsp;'.str_repeat('-', $count * 2).'&nbsp;';
			} else {
				$indent = '';
			}
			$output .= '<input type="checkbox" name="adrotate_categories[]" value="'.$category->cat_ID.'"';
			if(in_array($category->cat_ID, $savedcats)) {
				$output .= ' checked';
			}
			$output .= '>&nbsp;&nbsp;'.$indent.$category->name.' ('.$category->category_count.')<br />';
			$output .= adrotate_select_categories($savedcats, $count, $category->parent, $category->cat_ID);
			$child_of = $parent;
		}
		return $output;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_select_pages

 Purpose:   Create scrolling menu of all pages.
 Receive:   $savedpages, $count, $child_of, $parent
 Return:    $output
 Since:		3.8.4
-------------------------------------------------------------*/
function adrotate_select_pages($savedpages, $count = 2, $child_of = 0, $parent = 0) {
	if(!is_array($savedpages)) $savedpages = explode(',', $savedpages);
	$pages = get_pages(array('child_of' => $parent, 'parent' => $parent, 'sort_column' => 'ID', 'sort_order' => 'asc'));

	if(!empty($pages)) {
		$output = '';
		foreach($pages as $page) {
			if($page->post_parent > 0) {
				if($page->post_parent != $child_of) {
					$count = $count + 1;
				}
				$indent = '&nbsp;'.str_repeat('-', $count * 2).'&nbsp;';
			} else {
				$indent = '';
			}
			$output .= '<input type="checkbox" name="adrotate_pages[]" value="'.$page->ID.'"';
			if(in_array($page->ID, $savedpages)) {
				$output .= ' checked';
			}
			$output .= '>&nbsp;&nbsp;'.$indent.$page->post_title.'<br />';
			$output .= adrotate_select_pages($savedpages, $count, $page->post_parent, $page->ID);
			$child_of = $parent;
		}
		return $output;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_evaluate_ads

 Purpose:   Initiate evaluations for errors and determine the ad status
 Receive:   -None-
 Return:    -None-
 Since:		3.6.5
-------------------------------------------------------------*/
function adrotate_prepare_evaluate_ads($return = '') {
	global $wpdb;
	
	// Fetch ads
	$ads = $wpdb->get_results("SELECT `id`, `type` FROM `".$wpdb->prefix."adrotate` WHERE `type` != 'disabled' AND `type` != 'empty' ORDER BY `id` ASC;");

	// Determine error states
	$error = $expired = $expiressoon = $normal = $unknown = 0;
	foreach($ads as $ad) {
		$result = adrotate_evaluate_ad($ad->id);
		if($result == 'error') {
			$error++;
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = 'error' WHERE `id` = '".$ad->id."';");
		} 

		if($result == 'expired') {
			$expired++;
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = 'expired' WHERE `id` = '".$ad->id."';");
		} 
		
		if($result == '2days') {
			$expiressoon++;
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = '2days' WHERE `id` = '".$ad->id."';");
		}
		
		if($result == '7days') {
			$normal++;
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = '7days' WHERE `id` = '".$ad->id."';");
		}
		
		if($result == 'active') {
			$normal++;
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = 'active' WHERE `id` = '".$ad->id."';");
		}
		
		if($result == 'unknown') {
			$unknown++;
		}
	}

	$count = $expired + $expiressoon + $error;
	$result = array('error' => $error,
					'expired' => $expired,
					'expiressoon' => $expiressoon,
					'normal' => $normal,
					'total' => $count,
					'unknown' => $unknown
					);

	update_option('adrotate_advert_status', serialize($result));
	if($return == '') adrotate_return('db_evaluated');
}

/*-------------------------------------------------------------
 Name:      adrotate_evaluate_ad

 Purpose:   Evaluates ads for errors
 Receive:   $ad_id
 Return:    boolean
 Since:		3.6.5
-------------------------------------------------------------*/
function adrotate_evaluate_ad($ad_id) {
	global $wpdb;
	
	$now = adrotate_now();
	$in2days = $now + 172800;
	$in7days = $now + 604800;

	// Fetch ad
	$ad = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `imagetype`, `image`, `cbudget`, `ibudget`, `crate`, `irate` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $ad_id));
	$advertiser = $wpdb->get_var("SELECT `user` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '".$ad->id."' AND `group` = 0 AND `block` = 0 AND `user` > 0;");
	$schedules = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_schedule` WHERE `ad` = '".$ad->id."';");
	$stoptime = $wpdb->get_var("SELECT `stoptime` FROM `".$wpdb->prefix."adrotate_schedule` WHERE `ad` = '".$ad->id."' ORDER BY `stoptime` DESC LIMIT 1;");

	// Determine error states
	if(
		strlen($ad->bannercode) < 1 																	// AdCode empty
		OR ($ad->tracker == 'N' AND strlen($ad->link) < 1 AND $advertiser > 0) 							// Didn't enable click-tracking, didn't provide a link, DID set a advertiser
		OR ($ad->tracker == 'Y' AND strlen($ad->link) < 1) 												// Enabled clicktracking but provided no url (link)
		OR ($ad->tracker == 'N' AND strlen($ad->link) > 0) 												// Didn't enable click-tracking but did provide an url (link)
		OR (!preg_match("/%link%/i", $ad->bannercode) AND $ad->tracker == 'Y')							// Didn't use %link% but enabled clicktracking
		OR (preg_match("/%link%/i", $ad->bannercode) AND $ad->tracker == 'N')							// Did use %link% but didn't enable clicktracking
		OR (!preg_match("/%image%/i", $ad->bannercode) AND $ad->image != '' AND $ad->imagetype != '')	// Didn't use %image% but selected an image
		OR (preg_match("/%image%/i", $ad->bannercode) AND $ad->image == '' AND $ad->imagetype == '')	// Did use %image% but didn't select an image
		OR ($ad->image == '' AND $ad->imagetype != '')													// Image and Imagetype mismatch
		OR $schedules < 1																				// No Schedules
	) {
		return 'error';
	} else if(
		$stoptime <= $now 																				// Past the enddate
		OR ($ad->crate > 0 AND $ad->cbudget < 1)														// Ad ran out of of click budget
		OR ($ad->irate > 0 AND $ad->ibudget < 1)														// Ad ran out of of impression budget
	){
		return 'expired';
	} else if($stoptime <= $in2days AND $stoptime >= $now){												// Expires in 2 days
		return '2days';
	} else if($stoptime <= $in7days AND $stoptime >= $now){												// Expires in 7 days
		return '7days';
	} else {
		return 'active';
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
	$now = adrotate_now();
	$in2days = $now + 172800;
	$in7days = $now + 604800;
	
	if($enddate <= $now) {
		return '#CC2900'; // red
	} else if($enddate <= $in2days AND $enddate >= $now) {
		return '#F90'; // orange
	} else if($enddate <= $in7days AND $enddate >= $now) {
		return '#E6B800'; // yellow
	} else {
		return '#009900'; // green
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
			$output .= '<a href="'.admin_url('admin.php?page=adrotate-blocks&view=edit&edit_block='.$meta->block).'" title="'.__('Edit Block', 'adrotate').'">'.$blockname.'</a>, ';
		}
	} else {
		$output .= __('This group is not in a block!', 'adrotate');
	}
	$output = rtrim($output, " ,");
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_ad_is_in_groups

 Purpose:   Build list of groups the ad is in (overview)
 Receive:   $id
 Return:    $output
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_ad_is_in_groups($id) {
	global $wpdb;

	$output = '';
	$groups	= $wpdb->get_results("
		SELECT 
			`".$wpdb->prefix."adrotate_groups`.`name` 
		FROM 
			`".$wpdb->prefix."adrotate_groups`, 
			`".$wpdb->prefix."adrotate_linkmeta` 
		WHERE 
			`".$wpdb->prefix."adrotate_linkmeta`.`ad` = '".$id."'
			AND `".$wpdb->prefix."adrotate_linkmeta`.`group` = `".$wpdb->prefix."adrotate_groups`.`id`
			AND `".$wpdb->prefix."adrotate_linkmeta`.`block` = 0
			AND `".$wpdb->prefix."adrotate_linkmeta`.`user` = 0
		;");
	if($groups) {
		foreach($groups as $group) {
			$output .= $group->name.", ";
		}
	}
	$output = rtrim($output, ", ");
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_head

 Purpose:   Add jQuery/JS code to <head>
 Receive:   -none-
 Return:    -none-
 Since:		3.6.9
-------------------------------------------------------------*/
function adrotate_head() {

}   

/*-------------------------------------------------------------
 Name:      adrotate_colorpicker

 Purpose:   Load scripts for the colorpicker
 Receive:   -none-
 Return:    -none-
 Since:		3.7rc6
-------------------------------------------------------------*/
function adrotate_colorpicker() {
  	wp_enqueue_style( 'farbtastic' );
  	wp_enqueue_script( 'farbtastic' );
}

/*-------------------------------------------------------------
 Name:      adrotate_check_config

 Purpose:   Update the options
 Receive:   -none-
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_check_config() {
	
	$config 	= get_option('adrotate_config');
	$crawlers 	= get_option('adrotate_crawlers');
	$debug 		= get_option('adrotate_debug');

	if(empty($config)) $config = array();
	if(empty($crawlers)) $crawlers = array();
	if(empty($debug)) $debug = array();
	
	if(empty($config['advertiser'])) $config['advertiser'] = 'switch_themes'; // Admin
	if(empty($config['global_report'])) $config['global_report'] = 'switch_themes'; // Admin
	if(empty($config['ad_manage'])) $config['ad_manage'] = 'switch_themes'; // Admin
	if(empty($config['ad_delete'])) $config['ad_delete'] = 'switch_themes'; // Admin
	if(empty($config['group_manage'])) $config['group_manage'] = 'switch_themes'; // Admin
	if(empty($config['group_delete'])) $config['group_delete'] = 'switch_themes'; // Admin
	if(empty($config['block_manage'])) $config['block_manage'] = 'switch_themes'; // Admin
	if(empty($config['block_delete'])) $config['block_delete'] = 'switch_themes'; // Admin
	if(empty($config['moderate'])) $config['moderate'] = 'switch_themes'; // Admin
	if(empty($config['moderate_approve'])) $config['moderate_approve'] = 'switch_themes'; // Admin

	if(empty($config['enable_advertisers'])) $config['enable_advertisers'] = 'Y';
	if(empty($config['enable_editing'])) $config['enable_editing'] = 'N';
	if(empty($config['enable_stats'])) $config['enable_stats'] = 'Y';
	if(empty($config['enable_loggedin_impressions'])) $config['enable_loggedin_impressions'] = 'Y';
	if(empty($config['enable_loggedin_clicks'])) $config['enable_loggedin_clicks'] = 'Y';

	if(empty($config['banner_folder'])) $config['banner_folder'] = "/wp-content/banners/";
	if(empty($config['notification_email_switch']))	$config['notification_email_switch'] = 'Y';
	if((empty($config['notification_email']) OR !is_array($config['notification_email'])) AND $config['notification_email_switch'] == 'Y') $config['notification_email'] = array(get_option('admin_email'));
	if(empty($config['advertiser_email']) OR !is_array($config['advertiser_email'])) $config['advertiser_email'] = array(get_option('admin_email'));
	if(empty($config['widgetalign'])) $config['widgetalign'] = 'N';
	if(empty($config['w3caching'])) $config['w3caching'] = 'N';
	if(empty($config['supercache'])) $config['supercache'] = 'N';
	if(empty($config['impression_timer'])) $config['impression_timer'] = '10';
	update_option('adrotate_config', $config);

	if(empty($crawlers)) $crawlers = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi","looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory","Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot","www.galaxy.com", "Googlebot", "Scooter", "Slurp","msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz","Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot","Mediapartners-Google", "Sogou web spider", "WebAlta Crawler","bot", "crawler", "yahoo", "msn", "ask", "ia_archiver");
	update_option('adrotate_crawlers', $crawlers);

	if(empty($debug['general'])) $debug['general'] = false;
	if(empty($debug['dashboard'])) $debug['dashboard'] = false;
	if(empty($debug['userroles'])) $debug['userroles'] = false;
	if(empty($debug['userstats'])) $debug['userstats'] = false;
	if(empty($debug['stats'])) $debug['stats'] = false;
	if(empty($debug['timers'])) $debug['timers'] = false;
	if(empty($debug['track'])) $debug['track'] = false;
	update_option('adrotate_debug', $debug);

}

/*-------------------------------------------------------------
 Name:      adrotate_get_remote_ip

 Purpose:   Get the remote IP from the visitor
 Receive:   -None-
 Return:    $buffer[0]
 Since:		3.6.2
-------------------------------------------------------------*/
function adrotate_get_remote_ip(){
	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$remote_ip = $_SERVER["REMOTE_ADDR"];
	} else {
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	$buffer = explode(',', $remote_ip, 2);

	return $buffer[0];
}

/*-------------------------------------------------------------
 Name:      adrotate_get_sorted_roles

 Purpose:   Returns all roles and capabilities, sorted by user level. Lowest to highest.
 Receive:   -none-
 Return:    $sorted
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_get_sorted_roles() {	
	global $wp_roles;

	$editable_roles = apply_filters('editable_roles', $wp_roles->roles);
	$sorted = array();
	
	foreach($editable_roles as $role => $details) {
		$sorted[$details['name']] = get_role($role);
	}

	$sorted = array_reverse($sorted);

	return $sorted;
}

/*-------------------------------------------------------------
 Name:      adrotate_set_capability

 Purpose:   Grant or revoke capabilities to a role and all higher roles
 Receive:   $lowest_role, $capability
 Return:    -None-
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_set_capability($lowest_role, $capability){
	$check_order = adrotate_get_sorted_roles();
	$add_capability = false;
	
	foreach($check_order as $role) {
		if($lowest_role == $role->name) $add_capability = true;
		if(empty($role)) continue;
		$add_capability ? $role->add_cap($capability) : $role->remove_cap($capability) ;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_remove_capability

 Purpose:   Remove the $capability from the all roles
 Receive:   $capability
 Return:    -None-
 Since:		3.2
-------------------------------------------------------------*/
function adrotate_remove_capability($capability){
	$check_order = adrotate_get_sorted_roles();

	foreach($check_order as $role) {
		$role = get_role($role->name);
		$role->remove_cap($capability);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_mail_beta

 Purpose:   Send beta feedback
 Receive:   -None-
 Return:    -None-
 Since:		3.6.11
-------------------------------------------------------------*/
function adrotate_mail_beta() {
	global $wpdb, $adrotate_config;

	if(wp_verify_nonce($_POST['adrotate_nonce'],'adrotate_email_beta')) {
		$author 		= $_POST['adrotate_username'];
		$useremail 		= $_POST['adrotate_email'];
		$version 		= $_POST['adrotate_version'];
		$text	 		= strip_tags(stripslashes(trim($_POST['adrotate_message'], "\t\n ")));
	
		if(strlen($text) < 1) {
			adrotate_return('beta_mail_empty');
		} else {
			$wpurl			= get_bloginfo('wpurl');
			$wpversion		= get_bloginfo('version');
			$wpcharset		= get_bloginfo('charset');
			$wplang			= get_bloginfo('language');
			$pluginurl		= "http://www.adrotateplugin.com";
		
			$to[] = $useremail;
			$to[] = "feedback@adrotateplugin.com";

			$headers[] = "Content-Type: text/html; charset=iso-8859-1";
			$headers[] = "From: $author <$useremail>";
				
			$subject = "[AdRotate Beta] Feedback from $author!";
		
			$message = "<p>Hello,</p>";
			$message .= "<p>From: $author<br />Website: $wpurl<br />WordPress Version: $wpversion<br />WordPress Language: $wplang<br />WordPress Charset: $wpcharset<br />AdRotate Version: $version</p>";	
			$message .= "<p>Attached message: $text</p>";
			$message .= "<p>You can reply to this message to contact $author.<br />";
			$message .= "</p>";
	
			wp_mail($to, $subject, $message, $headers);
			adrotate_return('beta_mail_sent');
		}
	} else {
		adrotate_nonce_error();
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_scripts

 Purpose:   Load file uploaded popup
 Receive:   -None-
 Return:	-None-
 Since:		3.6
-------------------------------------------------------------*/
function adrotate_dashboard_scripts() {
	wp_enqueue_script('media-upload');
	wp_enqueue_script('thickbox');
	wp_enqueue_script('jquery');
	wp_enqueue_script('raphael', plugins_url('/library/raphael-min.js', __FILE__), array('jquery'));
	wp_enqueue_script('elycharts', plugins_url('/library/elycharts.min.js', __FILE__), array('jquery', 'raphael'));

	wp_enqueue_style('thickbox');
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_head

 Purpose:   Add even more stuff to <head> in the dashboard right behind adrotate_dashboard_scripts()
 Receive:   -None-
 Return:	-None-
 Since:		3.8.4
-------------------------------------------------------------*/
function adrotate_dashboard_head(){
	Broadstreet_Mini_Utility::editableJS();
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_styles

 Purpose:   Load file uploaded popup
 Receive:   -None-
 Return:	-None-
 Since:		3.6
-------------------------------------------------------------*/
function adrotate_dashboard_styles() {
?>
<style type="text/css" media="screen">
	/* styles for graphs */
	.adrotate-label { font-size: 12px; line-height: 5px; margin: 2px; font-weight: bold }
	.adrotate-clicks { color: #5Af; font-weight: normal }
	.adrotate-impressions { color: #F80; font-weight: normal }
	
	/* styles for advert statuses and stats */
	.row_urgent { background-color:#ffebe8; border-color:#c00; }
	.row_error { background-color:#ffffe0; border-color:#e6db55; }
	.row_inactive { background-color:#ebf3fa; border-color:#466f82; }
	.stats_large { display: block; margin-bottom: 10px; margin-top: 10px; text-align: center; font-weight: bold; }
	.number_large {	margin: 20px; font-size: 28px; }
	
	/* Fancy select box for group and page injection*/
	.adrotate-select { padding:3px; border:1px solid #ccc; max-width:500px; max-height:100px; overflow-y:scroll; background-color:#fff; }
</style>
<?php
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
	$siteurl = get_option('siteurl');

	// Read Banner folder
	$files = array();
	$i = 0;
	if($handle = opendir(ABSPATH.$adrotate_config['banner_folder'])) {
	    while (false !== ($file = readdir($handle))) {
	        if ($file != "." AND $file != ".." AND $file != "index.php") {
	            $files[] = $file;
	        	$i++;
	        }
	    }
	    closedir($handle);

	    if($i > 0) {
			sort($files);
			foreach($files as $file) {
				$fileinfo = pathinfo($file);
		
				if((strtolower($fileinfo['extension']) == "jpg" OR strtolower($fileinfo['extension']) == "gif" OR strtolower($fileinfo['extension']) == "png" 
				OR strtolower($fileinfo['extension']) == "jpeg" OR strtolower($fileinfo['extension']) == "swf" OR strtolower($fileinfo['extension']) == "flv")) {
				    $output .= "<option value='".$file."'";
				    if(($current == $siteurl.'/wp-content/banners/'.$file) OR ($current == $siteurl."%folder%".$file)) { $output .= "selected"; }
				    $output .= ">".$file."</option>";
				}
			}
		} else {
	    	$output .= "<option disabled>&nbsp;&nbsp;&nbsp;".__('No files found', 'adrotate')."</option>";
		}
	} else {
    	$output .= "<option disabled>&nbsp;&nbsp;&nbsp;".__('Folder not found or not accessible', 'adrotate')."</option>";
	}
	
	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_return

 Purpose:   Internal redirects
 Receive:   $action, $arg (array)
 Return:    -none-
 Since:		0.2
 Usage:		array('wp_page', 'message', 'arguments', 'id')
-------------------------------------------------------------*/
function adrotate_return($action, $arg = null) {

	switch($action) {
		// Manage Ads
		case "new" :
			wp_redirect('admin.php?page=adrotate-ads&message=new');
		break;

		case "update" :
			wp_redirect('admin.php?page=adrotate-ads&view=edit&message=updated&ad='.$arg[0]);
		break;

		case "update_manage" :
			wp_redirect('admin.php?page=adrotate-ads&message=updated');
		break;

		case "delete" :
			wp_redirect('admin.php?page=adrotate-ads&message=deleted');
		break;

		case "reset" :
			wp_redirect('admin.php?page=adrotate-ads&message=reset');
		break;

		case "renew" :
			wp_redirect('admin.php?page=adrotate-ads&message=renew');
		break;

		case "deactivate" :
			wp_redirect('admin.php?page=adrotate-ads&message=deactivate');
		break;

		case "activate" :
			wp_redirect('admin.php?page=adrotate-ads&message=activate');
		break;

		case "field_error" :
			wp_redirect('admin.php?page=adrotate-ads&message=field_error');
		break;

		// Groups
		case "group_new" :
			wp_redirect('admin.php?page=adrotate-groups&message=created');
		break;

		case "group_edit" :
			wp_redirect('admin.php?page=adrotate-groups&view=edit&message=updated&group='.$arg[0]);
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
			wp_redirect('admin.php?page=adrotate-blocks&view=edit&message=updated&block='.$arg[0]);
		break;

		case "block_delete" :
			wp_redirect('admin.php?page=adrotate-blocks&message=deleted');
		break;

		case "block_template_new" :
			wp_redirect('admin.php?page=adrotate-blocks&view=templates&message=created_template');
		break;

		case "block_template_edit" :
			wp_redirect('admin.php?page=adrotate-blocks&view=templates&message=edit_template');
		break;

		case "block_template_delete" :
			wp_redirect('admin.php?page=adrotate-blocks&view=templates&message=deleted_template');
		break;

		// Settings
		case "settings_saved" :
			wp_redirect('admin.php?page=adrotate-settings&message=updated');
		break;

		// Maintenance
		case "db_optimized" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_optimized');
		break;

		case "db_evaluated" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_evaluated');
		break;

		case "db_repaired" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_optimized');
		break;

		case "db_cleaned" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_cleaned');
		break;

		case "db_timer" :
			wp_redirect('admin.php?page=adrotate-settings&message=db_timer');
		break;

		// Misc plugin events
		case "mail_sent" :
			wp_redirect('admin.php?page=adrotate-advertiser&message=mail_sent');
		break;

		case "beta_mail_sent" :
			wp_redirect('admin.php?page=adrotate-beta&message=sent');
		break;

		case "beta_mail_empty" :
			wp_redirect('admin.php?page=adrotate-beta&message=empty');
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
?>