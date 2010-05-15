<?php
/*-------------------------------------------------------------
 Name:      adrotate_banner

 Purpose:   Show a banner as requested in the WP Theme or post
 Receive:   $group_ids, $banner_id, $block, $column, $preview
 Return:    $output
-------------------------------------------------------------*/
function adrotate_banner($group_ids, $banner_id = 0, $block = 0, $column = 0, $preview = false) {
	global $wpdb;

	if($group_ids) {
		$group_ids = explode(",", $group_ids);
		$x = array_rand($group_ids, 1);
		$now = current_time('timestamp');

		if($banner_id > 0 AND $block < 1) {
			$select_banner = " AND `id` = '$banner_id'";
		} else {
			$select_banner = "";
		}

		if($preview == false) {
			$active_banner = " AND `active` = 'yes' AND '$now' >= `startshow` AND '$now' <= `endshow`";
		}

		$rawbanners = $wpdb->get_results("SELECT `id`, `tracker`, `clicks`, `maxclicks`, `shown`, `maxshown`, `group` FROM `".$wpdb->prefix."adrotate` WHERE `group` = '$group_ids[$x]' ".$select_banner.$active_banner);

		if($rawbanners) {

			foreach($rawbanners as $raw) {
				$selected[] = $raw->id;

				if($raw->clicks >= $raw->maxclicks AND $raw->maxclicks > 0 AND $raw->tracker == "Y") {
					$selected = array_diff($selected, array($raw->id));
				}
				if($raw->shown >= $raw->maxshown AND $raw->maxshown > 0 AND in_array($raw->id, $selected)) {
					$selected = array_diff($selected, array($raw->id));
				}
			}

			if($block > 0 AND $banner_id < 1) {
				shuffle($selected);
				$chosen = $selected;
				$limit = $block;
			} else if($banner_id > 0) {
				$chosen = array($banner_id);
				$limit = 1;
			} else {
				$y = array_rand($selected, 1);
				$chosen = array($selected[$y]);
				$limit = count($chosen);
			}

			if(count($selected) > 0) {
				$output = '';
				$cutoff = 1;
				for($i=0;$i<$limit;$i++) {
					$banner = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '".$chosen[$i]."'");

					$banner_output = $banner->bannercode;

					if($banner->tracker == "Y") {
						$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?trackerid='.$banner->id, $banner_output);
					} else {
						$banner_output = str_replace('%link%', $banner->link, $banner_output);
					}
					$banner_output = str_replace('%image%', $banner->image, $banner_output);
					$banner_output = str_replace('%id%', $banner->id, $banner_output);

					$output .= $banner_output;
					if($column > 0 AND $cutoff == $column) {
						$output .= '<br style="height:none; width:none;" />';
						$cutoff = 1;
					} else {
						$cutoff++;
					}

					if($preview == false) {
						$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `shown` = `shown` + 1 WHERE `id` = '$banner->id'");
					}
				}
			} else {
				$output = adrotate_fallback($group_ids[$x], 'expired');
			}
		} else {
			$output = adrotate_fallback($group_ids[$x], 'unqualified');
		}
	} else {
		$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">Error, no group_id specified! Check your syntax or contact an administrator!</span>';
	}

	$output = stripslashes(html_entity_decode($output, ENT_QUOTES));

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_fallback

 Purpose:   Select another banner from appointed fallback group
 Receive:   $group, $case
 Return:    $fallback_output
-------------------------------------------------------------*/
function adrotate_fallback($group, $case) {
	global $wpdb;

	$fallback = $wpdb->get_var("SELECT `fallback` FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = '$group' LIMIT 1");

	if($fallback > 0) {
		$fallback_output = adrotate_banner($fallback);
	} else {
		if($case == 'expired') {
			$fallback_output = '<!-- The banners in this group are expired! Contact an administrator to resolve this issue! -->';
		}
		
		if($case == 'unqualified') {
			$fallback_output = '<!-- Either there are no banners, they are disabled or none qualified for this location! Contact an administrator to resolve this issue! -->';
		}
	}
	
	return $fallback_output;
}

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Show a banner as requested in a post or page using shortcodes
 Receive:   $atts, $content
 Return:    adrotate_banner()
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	if(!empty($atts['group'])) 	$group_ids = $atts['group'];
	if(!empty($atts['block']))	$block = $atts['block'];
	if(!empty($atts['column'])) $column = $atts['column'];
	if(!empty($atts['banner'])) $banner_id = $atts['banner'];

	return adrotate_banner($group_ids, $banner_id, $block, $column, false);
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
	$in2days = $now + 172800;
	
	$alreadyexpired = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $now");
	$expiressoon = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $in2days AND `endshow` >= $now");

	$count = $alreadyexpired + $expiressoon;
	
	if($count > 0) {
		if($alreadyexpired > 0 and $expiressoon == 0) {
			echo '<div class="error"><p>'.$alreadyexpired.' banner(s) expired. <a href="admin.php?page=adrotate">Take action</a>!</p></div>';
		} else if($alreadyexpired == 0 and $expiressoon > 0) {
			echo '<div class="updated"><p>'.$expiressoon.' banner(s) are about to expire. <a href="admin.php?page=adrotate">Check it out</a>!</p></div>';
		} else {
			echo '<div class="error"><p>'.$count.' banners require your attention. <a href="admin.php?page=adrotate">Fix it</a>!</p></div>';
		}
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
	echo '	The plugin homepage is at <a href="http://meandmymac.net/plugins/adrotate/" target="_blank">http://meandmymac.net/plugins/adrotate/</a>. For general information about AdRotate!<br />';
	echo '	Read about updates! <a href="http://meandmymac.net/tag/adrotate/" target="_blank">http://meandmymac.net/tag/adrotate/</a>.<br />';
	echo '	Need help? <a href="http://meandmymac.net/support/" target="_blank">ticket support</a> is available. Did you see the Knowledgebase?!<br />';
	echo '	Like my software? Did i help you? <a href="http://meandmymac.net/donate/" target="_blank">Show your appreciation</a>. Thanks!</td>';
	echo '</tr>';
	echo '</tbody>';

	echo '</table';
}

/*-------------------------------------------------------------
 Name:      adrotate_meta

 Purpose:   Sidebar meta
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_meta() {
	global $adrotate_config;

	if($adrotate_config['credits'] == "Y") {
		echo "<li>I'm using <a href=\"http://meandmymac.net/plugins/adrotate/\" target=\"_blank\" title=\"AdRotate\">AdRotate</a></li>\n";
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_check_config

 Purpose:   Create or update the options
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_check_config() {
	if ( !$config = get_option('adrotate_config') ) {
		$config['credits']		 		= 'Y';
		$config['browser']		 		= 'N';
		$config['widgetalign']		 	= 'N';
		update_option('adrotate_config', $config);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_options_submit

 Purpose:   Save options from dashboard
 Receive:   $_POST
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_options_submit() {
	if(isset($_POST['adrotate_credits'])) 		$config['credits'] = 'Y';
		else 									$config['credits'] = 'N';
	if(isset($_POST['adrotate_browser'])) 		$config['browser'] = 'Y';
		else 									$config['browser'] = 'N';
	if(isset($_POST['adrotate_widgetalign'])) 	$config['widgetalign'] = 'Y';
		else 									$config['widgetalign'] = 'N';

	update_option('adrotate_config', $config);
}

/*-------------------------------------------------------------
 Name:      adrotate_folder_contents

 Purpose:   List folder contents of /wp-content/banners and /wp-content/uploads/
 Receive:   $current
 Return:	$output
-------------------------------------------------------------*/
function adrotate_folder_contents($current) {
	global $wpdb, $adrotate_config;

	$output = '';

	// Read /wp-content/banners/
	$output .= "<option disabled>-- Banners Folder --</option>";
	if ($handle = opendir(ABSPATH.'/wp-content/banners/')) {
		$i=0;
	    while (false !== ($file = readdir($handle))) {
			$fileinfo = pathinfo($file);

	        if ($file != "." && $file != ".."
	        AND (strtolower($fileinfo['extension']) == "jpg" OR strtolower($fileinfo['extension']) == "gif"
	        OR strtolower($fileinfo['extension']) == "png" OR strtolower($fileinfo['extension']) == "jpeg"
	        OR strtolower($fileinfo['extension']) == "swf" OR strtolower($fileinfo['extension']) == "flv")) {
	            $output .= "<option value='banner|".$file."'";
	            if($current == get_option('siteurl').'/wp-content/banners/'.$file) { $output .= "selected"; }
	            $output .= ">".$file."</option>";
	        $i++;
	        }
	    }
	    closedir($handle);
	    if($i==0) {
	    	$output .= "<option disabled>&nbsp;&nbsp;&nbsp;No banners found</option>";
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

 Purpose:   Redirect to various pages
 Receive:   $action, $arg
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_return($action, $arg = null) {
	switch($action) {
		// Regular actions
		case "new" :
			wp_redirect('admin.php?page=adrotate&message=created');
		break;

		case "update" :
			wp_redirect('admin.php?page=adrotate3&message=updated&edit_banner='.$arg[0]);
		break;

		case "delete" :
			wp_redirect('admin.php?page=adrotate&message=deleted');
		break;

		case "move" :
			wp_redirect('admin.php?page=adrotate&message=moved');
		break;

		// Manage Events
		case "reset" :
			wp_redirect('admin.php?page=adrotate3&message=reset&edit_banner='.$arg[0]);
		break;

		case "renew" :
			wp_redirect('admin.php?page=adrotate3&message=renew&edit_banner='.$arg[0]);
		break;

		case "resetmultiple" :
			wp_redirect('admin.php?page=adrotate&message=reset');
		break;

		case "renewmultiple" :
			wp_redirect('admin.php?page=adrotate&message=renew');
		break;

		case "deactivate" :
			wp_redirect('admin.php?page=adrotate&message=deactivate');
		break;

		case "activate" :
			wp_redirect('admin.php?page=adrotate&message=activate');
		break;

		// Groups
		case "group_new" :
			wp_redirect('admin.php?page=adrotate4&message=created');
		break;

		case "group_edit" :
			wp_redirect('admin.php?page=adrotate4&message=updated');
		break;

		case "group_field_error" :
			wp_redirect('admin.php?page=adrotate4&message=field_error&edit_group='.$arg[0]);
		break;

		case "group_delete" :
			wp_redirect('admin.php?page=adrotate4&message=deleted');
		break;

		case "group_delete_banners" :
			wp_redirect('admin.php?page=adrotate4&message=deleted_banners');
		break;

		// Wizard
		case "magic_new" :
			wp_redirect('admin.php?page=adrotate&message=created');
		break;

		case "magic_field_error" :
			wp_redirect('admin.php?page=adrotate2&message=field_error&step='.$arg[0].'&magic_id='.$arg[1]);
		break;

		case "magic_error" :
			wp_redirect('admin.php?page=adrotate2&step='.$arg[0].'&message=error');
		break;

		// Misc plugin events
		case "no_access" :
			wp_redirect('admin.php?page=adrotate&message=no_access');
		break;

		case "field_error" :
			wp_redirect('admin.php?page=adrotate3&message=field_error');
		break;

		case "error" :
			wp_redirect('admin.php?page=adrotate&message=error');
		break;

	}
}

/*-------------------------------------------------------------
 Name:      meandmymac_rss

 Purpose:   A very simple RSS parser for Meandmymac.net
 Receive:   $rss, $count
 Return:    -none-
-------------------------------------------------------------*/
if(!function_exists('meandmymac_rss')) {
	function meandmymac_rss($rss, $count = 10, $showdates = 'yes') {
		if ( is_string( $rss ) ) {
			require_once(ABSPATH . WPINC . '/rss.php');
			if ( !$rss = fetch_rss($rss) ) {
				echo '<div class="text-wrap"><span class="rsserror">The feed could not be fetched, try again later!</span></div>';
				return;
			}
		}

		if ( is_array( $rss->items ) && !empty( $rss->items ) ) {
			$rss->items = array_slice($rss->items, 0, $count);
			foreach ( (array) $rss->items as $item ) {
				while ( strstr($item['link'], 'http') != $item['link'] ) {
					$item['link'] = substr($item['link'], 1);
				}

				$link = clean_url(strip_tags($item['link']));
				$desc = attribute_escape(strip_tags( $item['description']));
				$title = attribute_escape(strip_tags($item['title']));
				if ( empty($title) ) {
					$title = __('Untitled');
				}
				
				if ( empty($link) ) {
					$link = "#";
				}

				if (isset($item['pubdate'])) {
					$date = $item['pubdate'];
				} elseif (isset($item['published'])) {
					$date = $item['published'];
				}

				if ($date) {
					if ($date_stamp = strtotime($date)) {
						$date = date_i18n( get_option('date_format'), $date_stamp);
					} else {
						$date = '';
					}
				}
				echo '<div class="text-wrap"><a href="'.$link.'" target="_blank">'.$title.'</a> ';
				if($showdates == "yes") echo 'on '.$date;
				echo '</div>';
			}
		} else {
			echo '<div class="text-wrap"><span class="rsserror">The feed appears to be invalid or corrupt!</span></div>';
		}
		return;
	}
}
?>