<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)

This program is free software; you can redistribute it and/or modify it under the terms of 
the GNU General Public License, version 2, as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, visit: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true) {
	global $wpdb;

	/* Changelog:
	// Nov 15 2010 - Moved ad formatting to new function adrotate_prepare_ad_output()
	// Dec 10 2010 - Added check for single ad or not. Query updates for 3.0.1.
	// Dec 11 2010 - Check for single ad now works.
	*/
	
	$now = current_time('timestamp');

	if($banner_id) {
		if($individual == false) { 
			// Coming from a group or block, no checks just load the ad
			$banner = $wpdb->get_row("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");
		} else { 
			// Single ad, check if it's ok
			$banner = $wpdb->get_row("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `startshow` <= '$now' AND `endshow` >= '$now' AND `id` = '$banner_id';");
		}
		
		if($banner) {
			$output = adrotate_prepare_ad_output($banner->id, $banner->bannercode, $banner->tracker, $banner->link, $banner->image);
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `shown` = `shown` + 1 WHERE `id` = '$banner->id'");
		} else {
//			$output = '<span style="color: #F00; font-weight: bold;">Error, Ad (ID: '.$banner_id.') is expired or does not exist!</span>';
			$output = '<!-- Error, Ad (ID: '.$banner_id.') is expired or does not exist! -->';
		}
	} else {
		$output = '<span style="color: #F00; font-weight: bold;">Error, no Ad ID set! Check your syntax!</span>';
	}

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_group

 Purpose:   Fetch ads in specified group(s) and show a random ad
 Receive:   $group_ids, $fallback
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_group($group_ids, $fallback = 0) {
	global $wpdb;

	/* Changelog:
	// Dec 10 2010 - $fallback now works. Query updated.
	*/

	if($group_ids) {
		$now = current_time('timestamp');
		$group_array = explode(",", $group_ids);
		$group_choice = array_rand($group_array, 1);
		$prefix = $wpdb->prefix;
		if($fallback == 0 OR $fallback == '')
			$fallback = $wpdb->get_var("SELECT `fallback` FROM `".$prefix."adrotate_groups` WHERE `id` = '$group_array[$group_choice]';");
	
		$linkmeta = $wpdb->get_results("SELECT `".$prefix."adrotate`.`id`, `".$prefix."adrotate`.`clicks`, `".$prefix."adrotate`.`maxclicks`, `".$prefix."adrotate`.`shown`, `".$prefix."adrotate`.`maxshown`, `".$prefix."adrotate`.`tracker`
										FROM `".$prefix."adrotate`, `".$prefix."adrotate_linkmeta` 
										WHERE `".$prefix."adrotate_linkmeta`.`group` = '$group_array[$group_choice]' 
											AND `".$prefix."adrotate_linkmeta`.`block` = 0 
											AND `".$prefix."adrotate_linkmeta`.`user` = 0
											AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad`
											AND `".$prefix."adrotate`.`active` = 'yes'
											AND `".$prefix."adrotate`.`type` = 'manual'
											AND `".$prefix."adrotate`.`startshow` <= '$now' 
											AND `".$prefix."adrotate`.`endshow` >= '$now'
										;");
		if($linkmeta) {
			foreach($linkmeta as $meta) {
				$selected[] = $meta->id;
	
				if($meta->clicks >= $meta->maxclicks AND $meta->maxclicks > 0 AND $meta->tracker == "Y") {
					$selected = array_diff($selected, array($meta->id));
				}
				if($meta->shown >= $meta->maxshown AND $meta->maxshown > 0) {
					$selected = array_diff($selected, array($meta->id));
				}
			}
			if(count($selected) > 0) {
				$banner_id = array_rand($selected, 1);
				$output = adrotate_ad($selected[$banner_id], false);
			} else {
				$output = adrotate_fallback($fallback, 'expired');
			}
		} else {
			$output = adrotate_fallback($fallback, 'unqualified');
		}
	} else {
		$output = '<span style="color: #F00; font-weight: bold;">Error, no group set! Check your syntax!</span>';
	}

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_block

 Purpose:   Fetch all ads in specified groups within block. Show set amount of ads randomly
 Receive:   $block_id
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_block($block_id) {
	global $wpdb;

	/* Changelog:
	// Dec 10 2010 - Query updates for 3.0.1.
	*/
	if($block_id) {
		$now = current_time('timestamp');
		$prefix = $wpdb->prefix;
		
		$block = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '$block_id';");
		if($block) {
			$groups = $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = '$block->id' AND `user` = 0;");
			if($groups) {
				$results = array();
				foreach($groups as $group) {
					$ads = $wpdb->get_results( // Carefull this is not a list of objects!!
						"SELECT `".$prefix."adrotate`.`id`, `".$prefix."adrotate`.`clicks`, `".$prefix."adrotate`.`maxclicks`, `".$prefix."adrotate`.`shown`, `".$prefix."adrotate`.`maxshown`, `".$prefix."adrotate`.`tracker` FROM `".$prefix."adrotate`, `".$prefix."adrotate_linkmeta`
						WHERE `".$prefix."adrotate_linkmeta`.`group` = '$group->group' 
							AND `".$prefix."adrotate_linkmeta`.`block` = 0 
							AND `".$prefix."adrotate_linkmeta`.`user` = 0 
							AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
							AND `".$prefix."adrotate`.`active` = 'yes' 
							AND '$now' >= `".$prefix."adrotate`.`startshow` 
							AND '$now' <= `".$prefix."adrotate`.`endshow`
						;", ARRAY_A);
					$results = array_merge($ads, $results);
				}

				$results = adrotate_prepare_array_unique($results);
				foreach($results as $result) {
					$selected[] = $result['id'];
					
					if($result['clicks'] >= $result['maxclicks'] AND $result['maxclicks'] > 0 AND $result['tracker'] == "Y") {
						$selected = array_diff($selected, array($result['id']));
					}
					if($result['shown'] >= $result['maxshown'] AND $result['maxshown'] > 0) {
						$selected = array_diff($selected, array($result['id']));
					}
				}
			}

			$array_count = count($selected);

			if($array_count > 0) {
				if($array_count < $block->adcount) { 
					$block_count = $array_count;
				} else { 
					$block_count = $block->adcount;
				}
					
				$chosen_few = array_rand($selected, $block_count);
				if(count($chosen_few) == 1) $chosen_few = array($chosen_few);
				shuffle($chosen_few);

				$output = '';
				$break = 1;
				for($i=0;$i<$block_count;$i++) {
					if($block->wrapper_before != '')
						$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES));
					$output .= adrotate_ad($selected[$chosen_few[$i]], false);
					if($block->wrapper_after != '')
						$output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES));
					
					if($block->columns > 0 AND $break == $block->columns) {
						$output .= '<br style="height:none; width:none;" />';
						$break = 1;
					} else {
						$break++;
					}
				}			
			} else {
				$output = adrotate_error('ad_unqualified');
			}
		} else {
			$output = '<span style="color: #F00; font-weight: bold;">Error, Block (ID: '.$block_id.') does not exist! Check your syntax!</span>';
		}
	} else {
		$output = '<span style="color: #F00; font-weight: bold;">Error, no Block ID set! Check your syntax!</span>';
	}

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_preview

 Purpose:   Show preview of selected ad (Dashboard)
 Receive:   $banner_id
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_preview($banner_id) {
	global $wpdb;

	/* Changelog:
	// Nov 15 2010 - Moved ad formatting to new function adrotate_prepare_ad_output()
	*/
	
	if($banner_id) {
		$now = current_time('timestamp');
		
		$banner = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");

		if($banner) {
			$output = adrotate_prepare_ad_output($banner->id, $banner->bannercode, $banner->tracker, $banner->link, $banner->image, true);
		} else {
			$output = '<span style="color: #F00; font-weight: bold;">Error, ad could not be found! If the issue persits contact <a href="htpp://meandmymac.net/support/" title="Support">AdRotate support</a>!</span>';
		}
	} else {
		$output = '<span style="color: #F00; font-weight: bold;">Error, no banner id specified! If the issue persits contact <a href="htpp://meandmymac.net/support/" title="Support">AdRotate support</a>!</span>';
	}

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_fallback

 Purpose:   Fall back to the set group or show an error if no fallback is set
 Receive:   $group, $case
 Return:    $fallback_output
 Added:		2.6
-------------------------------------------------------------*/
function adrotate_fallback($group, $case) {

	/* Changelog:
	// Dec 10 2010 - Update for 3.0.1, no longer double checks for $fallback values
	*/

	if($group > 0) {
		$fallback_output = adrotate_group($group);
	} else {
		if($case == 'expired') {
			$fallback_output = adrotate_error('ad_expired');
		}
		
		if($case == 'unqualified') {
			$fallback_output = adrotate_error('ad_unqualified');
		}
	}
	
	return $fallback_output;
}

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Prepare function requests for calls on shortcodes
 Receive:   $atts, $content
 Return:    Function()
 Since:		0.7
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	/* Changelog:
	// Nov 9 2010 - Rewritten for 3.0, param 'columns' removed, added 'fallback' override for groups
	// Nov 17 2010 - Added filters for empty values
	*/

	if(!empty($atts['banner'])) 	$banner_id 	= trim($atts['banner'], "\r\t ");
	if(!empty($atts['group'])) 		$group_ids 	= trim($atts['group'], "\r\t ");
	if(!empty($atts['fallback']))	$fallback	= trim($atts['fallback'], "\r\t "); // OPTIONAL
	if(!empty($atts['block']))		$block_id	= trim($atts['block'], "\r\t "); // Block ID only!

	if($banner_id > 0 AND $group_ids == 0 AND $block_id == 0) // Show one Ad
		return adrotate_ad($banner_id);

	if($banner_id == 0 AND $group_ids > 0 AND $block_id == 0) // Show group 
		return adrotate_group($group_ids, $fallback);

	if($banner_id == 0 AND $group_ids == 0 AND $block_id > 0) // Show block 
		return adrotate_block($block_id);
}

/*-------------------------------------------------------------
 Name:      adrotate_banner DEPRECIATED

 Purpose:   Compatibility layer for old setups 
 Receive:   $group_ids, $banner_id, $block_id, $column
 Return:    Function()
 Added: 	0.1
-------------------------------------------------------------*/
function adrotate_banner($group_ids = 0, $banner_id = 0, $block_id = 0, $column = 0) {

	/* Changelog:
	// Nov 6 2010 - Changed function to form a compatibility layer for old setups, for ad output see adrotate_ad()
	// Nov 9 2010 - $block, Now accepts Block ID's only. $column, DEPRECIATED, no longer in use
	// Dec 6 2010 - Function DEPRECIATED, maintained for backward compatibility
	*/
	
	if(($banner_id > 0 AND ($group_ids == 0 OR $group_ids == '')) OR ($banner_id > 0 AND $group_ids > 0 AND ($block_id == 0 OR $block_id == ''))) // Show one Ad
		return adrotate_ad($banner_id);

	if($group_ids != 0 AND ($banner_id == 0 OR $banner_id == '')) // Show group 
		return adrotate_group($group_ids);

	if($block_id > 0 AND ($banner_id == 0 OR $banner_id == '') AND ($group_ids == 0 OR $group_ids == '')) // Show block
		return adrotate_block($block_id);
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_array_unique

 Purpose:   Filter out duplicate records in multidimensional arrays
 Receive:   $array
 Return:    $result|$return
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_array_unique($array) {
	if(count($array) > 0) {
		if(is_array($array[0])) {
			$return = array();
			// multidimensional
			foreach($array as $row){
				if(!in_array($row, $return)){
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
	
	$stats['lastfive']				= adrotate_prepare_array_unique($wpdb->get_results("SELECT `timer`, `bannerid` FROM `".$wpdb->prefix."adrotate_tracker` ORDER BY `timer` DESC LIMIT 5;", ARRAY_A));
	$stats['thebest']				= $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' AND `type` = 'manual' ORDER BY `clicks` DESC LIMIT 1;", ARRAY_A);
	$stats['theworst']				= $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' AND `type` = 'manual' ORDER BY `clicks` ASC LIMIT 1;", ARRAY_A);
	$stats['banners'] 				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'manual';");
	$stats['tracker']				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['clicks']				= $wpdb->get_var("SELECT SUM(clicks) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['banners_tracker']		= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	$stats['impressions']			= $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'N' AND `type` = 'manual';");
	$stats['impressions_tracker']	= $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'manual';");
	
	if(!$stats['lastfive']) 			array();
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
			
			$stats['thebest']				= $wpdb->get_row("SELECT `".$prefix."adrotate`.`title`, `".$prefix."adrotate`.`clicks` 
															FROM `".$prefix."adrotate`,`".$prefix."adrotate_linkmeta` 
															WHERE `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
																AND `".$prefix."adrotate`.`tracker` = 'Y' 
																AND `".$prefix."adrotate`.`active` = 'yes' 
																AND `".$prefix."adrotate`.`type` = 'manual' 
																AND `".$prefix."adrotate_linkmeta`.`user` = '$user' 
															ORDER BY `".$prefix."adrotate`.`clicks` DESC LIMIT 1;"
															, ARRAY_A);
			$stats['theworst']				= $wpdb->get_row("SELECT `".$prefix."adrotate`.`title`, `".$prefix."adrotate`.`clicks` 
															FROM `".$prefix."adrotate`,`".$prefix."adrotate_linkmeta` 
															WHERE `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
																AND `".$prefix."adrotate`.`tracker` = 'Y' 
																AND `".$prefix."adrotate`.`active` = 'yes' 
																AND `".$prefix."adrotate`.`type` = 'manual' 
																AND `".$prefix."adrotate_linkmeta`.`user` = '$user' 
															ORDER BY `".$prefix."adrotate`.`clicks` ASC LIMIT 1;"
															, ARRAY_A);
			$stats['ad_amount']				= count($ads);
	
			foreach($ads as $ad) {
				$meta = $wpdb->get_row("SELECT * FROM `".$prefix."adrotate` WHERE `id` = '$ad->ad' AND `type` = 'manual';");
				
				$adstats[$x]['id']						= $meta->id;			
				$adstats[$x]['title']					= $meta->title;			
				$adstats[$x]['startshow']				= $meta->startshow;
				$adstats[$x]['endshow']					= $meta->endshow;
				$adstats[$x]['clicks']					= $meta->clicks;
				$adstats[$x]['maxclicks']				= $meta->maxclicks;
				$adstats[$x]['impressions']				= $meta->shown;
				$adstats[$x]['maximpressions']			= $meta->maxshown;
		
				$ad_amount_tracker		= $wpdb->get_var("SELECT COUNT(*) FROM `".$prefix."adrotate` WHERE `id` = '$ad->ad' AND `tracker` = 'Y' AND `type` = 'manual';");
	
				$stats['ad_amount_tracker']				= $stats['ad_amount_tracker'] + $ad_amount_tracker;
				$stats['total_clicks']					= $stats['total_clicks'] + $meta->clicks;
				$stats['total_impressions']				= $stats['total_impressions'] + $meta->shown;
	
				$x++;
			}	
			$lastclicks			= adrotate_prepare_array_unique($wpdb->get_results("SELECT `".$prefix."adrotate_tracker`.`timer`, `".$prefix."adrotate_tracker`.`bannerid` 
																					FROM `".$prefix."adrotate`, `".$prefix."adrotate_tracker`, `".$prefix."adrotate_linkmeta` 
																					WHERE `".$prefix."adrotate_linkmeta`.`user` = '$user' 
																						AND `".$prefix."adrotate_linkmeta`.`group` = 0 
																						AND `".$prefix."adrotate_linkmeta`.`block` = 0 
																						AND `".$prefix."adrotate_tracker`.`ipaddress` != 0 
																						AND `".$prefix."adrotate_tracker`.`bannerid` = `".$prefix."adrotate_linkmeta`.`ad` 
																						AND `".$prefix."adrotate`.`tracker` = 'Y' 
																					ORDER BY `".$prefix."adrotate_tracker`.`timer` DESC LIMIT 15;"
																					, ARRAY_A));
			
			$stats['ads'] 					= $adstats;
			$stats['last_clicks']			= $lastclicks;
		
			if(!$stats['thebest']) 						$stats['thebest']				= array('title' => 0, 'clicks' => 0);
			if(!$stats['theworst']) 					$stats['theworst']				= array('title' => 0, 'clicks' => 0);
			if(!$stats['ads']) 							$stats['ads'] 					= array();
			if(!$stats['last_clicks']) 					$stats['last_clicks'] 			= array();
		
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
	} else {
		echo "<small>No ads for user. If you feel this to be in error please contact the site administrator.</small>";
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
 Name:      adrotate_prepare_ad_output

 Purpose:   Prepare the output for viewing
 Receive:   $id, $bannercode, $tracker, $link, $image
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_ad_output($id, $bannercode, $tracker, $link, $image, $preview = false) {
	$banner_output = $bannercode;

	if($tracker == "Y") {
		if($preview == true) {
			$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?trackerid='.$id.'&preview=true', $banner_output);		
		} else {
			$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?trackerid='.$id, $banner_output);
		}
	} else {
		$banner_output = str_replace('%link%', $link, $banner_output);
	}
	$banner_output = str_replace('%image%', $image, $banner_output);
	$banner_output = str_replace('%id%', $id, $banner_output);
	$banner_output = stripslashes(html_entity_decode($banner_output, ENT_QUOTES));

	return $banner_output;
}

/*-------------------------------------------------------------
 Name:      adrotate_prepare_roles

 Purpose:   Prepare user roles for WordPress
 Receive:   -None-
 Return:    $action
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_prepare_roles() {
	global $wp_roles;
	
	if(isset($_POST['adrotate_role_add_submit'])) {
		$action = "role_add";
		adrotate_add_roles();		
		update_option('adrotate_roles', '1');
	} 
	if(isset($_POST['adrotate_role_remove_submit'])) {
		$action = "role_remove";
		adrotate_remove_roles();
		update_option('adrotate_roles', '0');
	} 

	adrotate_return($action);
}

/*-------------------------------------------------------------
 Name:      adrotate_add_roles

 Purpose:   Prepare the output for viewing
 Receive:   $id, $bannercode, $tracker, $link, $image
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_add_roles() {
	global $wp_roles;
	
	add_role('adrotate_clientstats', 'AdRotate Clients', array('adrotate_clients' => true, 'read' => true));
	$wp_roles->add_cap('administrator','adrotate_clients');
	$wp_roles->add_cap('editor','adrotate_clients');
	$wp_roles->add_cap('author','adrotate_clients');
//	$wp_roles->add_cap('contributor','adrotate_clients');
//	$wp_roles->add_cap('subscriber','adrotate_clients');
}

/*-------------------------------------------------------------
 Name:      adrotate_remove_roles

 Purpose:   Prepare the output for viewing
 Receive:   $id, $bannercode, $tracker, $link, $image
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_remove_roles() {
	global $wp_roles;
	
	remove_role('adrotate_clientstats');
	$wp_roles->remove_cap('administrator','adrotate_clients');
	$wp_roles->remove_cap('editor','adrotate_clients');
	$wp_roles->remove_cap('author','adrotate_clients');
//	$wp_roles->remove_cap('contributor','adrotate_clients');
//	$wp_roles->remove_cap('subscriber','adrotate_clients');
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
	$output = rtrim($output, " ,\t");
	
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
	
	$alreadyexpired = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $now");
	$expiressoon = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `endshow` <= $in2days AND `endshow` >= $now");
	$configerror = $wpdb->get_var("SELECT COUNT(*) 
									FROM `".$wpdb->prefix."adrotate`, `".$wpdb->prefix."adrotate_linkmeta` 
									WHERE `".$wpdb->prefix."adrotate`.`tracker` = 'N' 
										AND `".$wpdb->prefix."adrotate_linkmeta`.`ad` = `".$wpdb->prefix."adrotate`.`id` 
										AND `".$wpdb->prefix."adrotate_linkmeta`.`group` = 0 
										AND `".$wpdb->prefix."adrotate_linkmeta`.`block` = 0 
										AND `".$wpdb->prefix."adrotate_linkmeta`.`user` > 0;"
									);

	$count = $alreadyexpired + $expiressoon + $configerror;
	
	$result = array($alreadyexpired, $expiressoon, $configerror, $count);

	return $result;	
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_notifications

 Purpose:   Notify user of expired banners in the dashboard
 Receive:   -none-
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_dashboard_notifications() {

	$data = adrotate_check_banners();

	if($data[3] > 0) {
		if($data[0] > 0 AND $data[1] == 0 AND $data[2] == 0) {
			echo '<div class="error"><p>'.$data[0].' ad(s) expired. <a href="admin.php?page=adrotate">Take action now</a>!</p></div>';
		} else if($data[0] == 0 AND $data[1] > 0 AND $data[2] == 0) {
			echo '<div class="error"><p>'.$data[1].' ad(s) are about to expire. <a href="admin.php?page=adrotate">Check it out</a>!</p></div>';
		} else if($data[0] == 0 AND $data[1] == 0 AND $data[2] > 0) {
			echo '<div class="error"><p>'.$data[2].' ad(s) should have tracking enabled or his publisher removed. <a href="admin.php?page=adrotate">Solve this conflict</a>!</p></div>';
		} else {
			echo '<div class="error"><p>'.$data[3].' ads require your attention. <a href="admin.php?page=adrotate">Fix this as soon as possible</a>!</p></div>';
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
	global $adrotate_config, $adrotate_notification_timer;
	
	if(isset($_POST['adrotate_testmail_submit'])) $action = 'test';
		else $action = 'alert';
	
	if(strlen($adrotate_config['notification_email']) > 0) $email = $adrotate_config['notification_email'];
		else $email = get_option('admin_email');
	
	$blogname 		= get_option('blogname');
	$siteurl 		= get_option('siteurl');
	$dashboardurl	= $siteurl."/wp-admin/admin.php?page=adrotate";
	$pluginurl		= "http://meandmymac.net/plugins/adrotate/";
    $headers 		= "MIME-Version: 1.0\n" .
      				  "From: AdRotate Plugin <".$email.">\r\n\n" . 
      				  "Content-Type: text/html; charset=\"" . get_settings('blog_charset') . "\"\n";
	$now 			= current_time('timestamp');
	$timer			= $now - 180;

	switch($action) {
	case 'alert' :
		$data = adrotate_check_banners();
		if($data[3] > 0) {
			$subject = '[AdRotate Alert] Your ads need your help!';
			
			$message = "<p>Hello,</p>";
			$message .= "<p>This notification is send to you from your website '$blogname'.</p>";
			$message .= "<p>You will receive a notification approximately every 24 hours until the issues are resolved.</p>";
			$message .= "<p>Current issues:<br />";
			if($data[0] > 0) $message .= $data[0]." ad(s) expired. This needs your immediate attention!<br />";
			if($data[1] > 0) $message .= $data[1]." ad(s) will expire in less than 2 days.<br />";
			if($data[2] > 0) $message .= $data[2]." ad(s) should have tracking enabled or his publisher removed.<br />";
			$message .= "</p>";
			$message .= "<p>A total of ".$data[3]." ad(s) are in need of your care!</p>";
			$message .= "<p>Access your dashboard here: %s</p>";
			$message .= "<p>Have a nice day!</p>";
			$message .= "<p>Your AdRotate Notifier<br />";
			$message .= "%s</p>";
			
			$message = sprintf($message, $dashboardurl, $pluginurl);
	
			wp_mail($email, $subject, $message, $headers);
		}
	break;	
	
	case 'test' :
		if($adrotate_notification_timer < $timer) {
			$subject = '[AdRotate test] This is a test notification!';
			
			$message = "<p>Hello,</p>";
			$message .= "<p>This test notification is send to you from your website '$blogname'.<br />";
			$message .= "Your email address has been set to receive notifications from the AdRotate plugin.</p>";
			$message .= "<p>Have a nice day!</p>";
			$message .= "<p>Your AdRotate Notifier<br />";
			$message .= "%s</p>";
			
			$message = sprintf($message, $pluginurl);
		
			wp_mail($email, $subject, $message, $headers);

			update_option('adrotate_notification_timer', $now);
						
			adrotate_return('mail_sent');
		} else {
			adrotate_return('mail_timer');
		}	
	break;	
	}
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
 Name:      adrotate_check_config

 Purpose:   Create or reset the options
 Receive:   -none-
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_check_config() {
	if(!$config = get_option('adrotate_config')) {
		$config['credits']		 				= 'Y';
		$config['browser']		 				= 'Y';
		$config['widgetalign']		 			= 'N';
		$config['notification_email'] 			= get_option('admin_email');
		$config['pagestatistics'] 				= 'switch_themes'; 	// Admin
		$config['dashboardstatistics'] 			= 'switch_themes'; 	// Admin
		$config['ad_manage'] 					= 'switch_themes'; 	// Admin
		$config['ad_delete'] 					= 'switch_themes'; 	// Admin
		$config['group_manage'] 				= 'switch_themes'; 	// Admin
		$config['group_delete'] 				= 'switch_themes'; 	// Admin
		$config['block_manage'] 				= 'switch_themes'; 	// Admin
		$config['block_delete'] 				= 'switch_themes'; 	// Admin
		update_option('adrotate_config', $config);
	}
	
	if(!$crawlers = get_option('adrotate_crawlers')) {
		$crawlers	= array("bot", "crawler", "spider", "google", "yahoo", "msn", "ask", "ia_archiver");
		update_option('adrotate_crawlers', $crawlers);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_options_submit

 Purpose:   Save options from dashboard
 Receive:   $_POST
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_options_submit() {
	if(isset($_POST['adrotate_credits'])) 					$config['credits'] = 'Y';
		else 												$config['credits'] = 'N';
	if(isset($_POST['adrotate_browser'])) 					$config['browser'] = 'Y';
		else 												$config['browser'] = 'N';
	if(isset($_POST['adrotate_widgetalign'])) 				$config['widgetalign'] = 'Y';
		else 												$config['widgetalign'] = 'N';

	$config['notification_email'] 		= $_POST['adrotate_notification_email'];
	$config['pagestatistics'] 			= $_POST['adrotate_pagestatistics'];
	$config['dashboardstatistics'] 		= $_POST['adrotate_dashboardstatistics'];
	$config['ad_manage'] 				= $_POST['adrotate_ad_manage'];
	$config['ad_delete'] 				= $_POST['adrotate_ad_delete'];
	$config['group_manage'] 			= $_POST['adrotate_group_manage'];
	$config['group_delete'] 			= $_POST['adrotate_group_delete'];
	$config['block_manage'] 			= $_POST['adrotate_block_manage'];
	$config['block_delete'] 			= $_POST['adrotate_block_delete'];

	$crawlers							= explode(', ', $_POST['adrotate_crawlers']);

	update_option('adrotate_config', $config);
	update_option('adrotate_crawlers', $crawlers);
	adrotate_return('settings_saved');
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
	    	$output .= "<option disabled>&nbsp;&nbsp;&nbsp;No banners found</option>";
		} else {
			sort($files);
			$output .= "<option disabled>-- Banner folder --</option>";
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

		// Groups
		case "group_new" :
			wp_redirect('admin.php?page=adrotate-groups&message=created');
		break;

		case "group_edit" :
			wp_redirect('admin.php?page=adrotate-groups&view=edit&message=updated&edit_group='.$arg[0]);
		break;

		case "group_field_error" :
			wp_redirect('admin.php?page=adrotate-groups&view=edit&message=field_error&edit_group='.$arg[0]);
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

		case "block_field_error" :
			wp_redirect('admin.php?page=adrotate-blocks&view=edit&message=field_error&edit_block='.$arg[0]);
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
			wp_redirect('admin.php?page=adrotate-settings&message=mail_sent');
		break;

		case "mail_timer" :
			wp_redirect('admin.php?page=adrotate-settings&message=mail_timer');
		break;

		case "settings_saved" :
			wp_redirect('admin.php?page=adrotate-settings&message=updated');
		break;

		case "no_access" :
			wp_redirect('admin.php?page=adrotate&message=no_access');
		break;

		case "field_error" :
			wp_redirect('admin.php?page=adrotate&message=field_error');
		break;

		case "error" :
			wp_redirect('admin.php?page=adrotate&message=error');
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
function adrotate_error($action) {
	switch($action) {
		case "ad_expired" :
			$result = "<!-- The banners in this group are expired! Contact an administrator to resolve this issue! -->";
			return $result;
		break;
		
		case "ad_unqualified" :
			$result = "<!-- Either there are no banners, they are disabled or none qualified for this location! Contact an administrator to resolve this issue! -->";
			return $result;
		break;
		
		case "db_error" :
			$result = "<span style=\"font-weight: bold; color: #f00;\">There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!<br />If this does not solve the issue please seek support at <a href=\"http://www.adrotateplugin.com/page/support.php\">www.adrotateplugin.com/page/support.php</a></span>";
			return $result;
		break;
		
		default:
			$default = "An unknown error occured. Please deactivate and re-activate AdRotate from the plugin page!!<br />If this does not solve the issue please seek support at <a href=\"http://www.adrotateplugin.com/page/support.php\">http://www.adrotateplugin.com/page/support.php</a>";
			return $default;
		break;

	}
}

/*-------------------------------------------------------------
 Name:      adrotate_meta

 Purpose:   Sidebar meta
 Receive:   -none-
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_meta() {
	global $adrotate_config;

	if($adrotate_config['credits'] == "Y") {
		echo "<li>I'm using <a href=\"http://meandmymac.net/plugins/adrotate/\" target=\"_blank\" title=\"AdRotate\">AdRotate</a></li>\n";
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_credits

 Purpose:   Credits shown throughout the plugin
 Receive:   -none-
 Return:    -none-
 Since:		2.?
-------------------------------------------------------------*/
function adrotate_credits() {
	echo '<table class="widefat" style="margin-top: .5em">';

	echo '<thead>';
	echo '<tr valign="top">';
	echo '	<th>AdRotate Ninja!</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td>';
	echo '	Find my website at <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>.<br />';
	echo '	Give me your money to <a href="http://meandmymac.net/donate/" target="_blank">show your appreciation</a>. Thanks!<br />';
	echo '	The plugin homepage is at <a href="http://www.adrotateplugin.com/" target="_blank">www.adrotateplugin.com</a>!<br />';
	echo '	Read about updates! <a href="http://www.adrotateplugin.com/page/updates.php" target="_blank">www.adrotateplugin.com/page/updates.php</a>.<br />';
	echo '	Need help? <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">ticket support</a> is available. Also check out the Knowledgebase!';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}

/*-------------------------------------------------------------
 Name:      adrotate_user_credits

 Purpose:   Credits shown on user statistics
 Receive:   -none-
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_user_credits() {
	echo '<table class="widefat" style="margin-top: .5em">';

	echo '<thead>';
	echo '<tr valign="top">';
	echo '	<th>AdRotate</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td>';
	echo '	Cached results are refreshed every 24 hours.<br />';
	echo '	All statistics are indicative. They do not nessesarily reflect results counted by other parties.<br />';
	echo '	Your ads are published with <a href="http://www.adrotateplugin.com/" target="_blank">AdRotate</a> for WordPress.';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}
?>