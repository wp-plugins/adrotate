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
	// Nov 15 2010 - Moved ad formatting to new function adrotate_ad_output()
	// Dec 10 2010 - Added check for single ad or not. Query updates for 3.0.1.
	// Dec 11 2010 - Check for single ad now works.
	// Dec 13 2010 - Exired/Non-existant error now as a comment
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
			$output = adrotate_ad_output($banner->id, $banner->bannercode, $banner->tracker, $banner->link, $banner->image);
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `shown` = `shown` + 1 WHERE `id` = '$banner->id'");
		} else {
			$output = adrotate_error('ad_expired', array($banner_id));
		}
	} else {
		$output = adrotate_error('ad_no_id');
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
	// Jan 05 2011 - Added support for weight system
	*/

	if($group_ids) {
		$now = current_time('timestamp');
		$group_array = explode(",", $group_ids);
		$group_choice = array_rand($group_array, 1);
		$prefix = $wpdb->prefix;
		if($fallback == 0 OR $fallback == '')
			$fallback = $wpdb->get_var("SELECT `fallback` FROM `".$prefix."adrotate_groups` WHERE `id` = '$group_array[$group_choice]';");
	
		$linkmeta = $wpdb->get_results("SELECT `".$prefix."adrotate`.`id`, `".$prefix."adrotate`.`clicks`, `".$prefix."adrotate`.`maxclicks`, `".$prefix."adrotate`.`shown`, `".$prefix."adrotate`.`maxshown`, `".$prefix."adrotate`.`tracker`, `".$prefix."adrotate`.`weight`
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
				$selected[$meta->id] = $meta->weight;
	
				if($meta->clicks >= $meta->maxclicks AND $meta->maxclicks > 0 AND $meta->tracker == "Y") {
					$selected = array_diff_key($selected, array($meta->id => $meta->weight));
				}
				if($meta->shown >= $meta->maxshown AND $meta->maxshown > 0) {
					$selected = array_diff_key($selected, array($meta->id => $meta->weight));
				}
			}
			if(count($selected) > 0) {
				$banner_id = adrotate_weight($selected);
				$output = adrotate_ad($banner_id, false);
			} else {
				$output = adrotate_fallback($fallback, 'expired');
			}
		} else {
			$output = adrotate_fallback($fallback, 'unqualified');
		}
	} else {
		$output = adrotate_error('group_no_id');
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
	// Jan 05 2011 - Added support for weight system
	*/
	
	if($block_id) {
		$now = current_time('timestamp');
		$prefix = $wpdb->prefix;
		
		$block = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '$block_id';");
		if($block) {
			$groups = $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = '$block->id' AND `user` = 0;");
			if($groups) {
				foreach($groups as $group) {
					$linkmeta = $wpdb->get_results(
						"SELECT `".$prefix."adrotate`.`id`, `".$prefix."adrotate`.`clicks`, `".$prefix."adrotate`.`maxclicks`, `".$prefix."adrotate`.`shown`, `".$prefix."adrotate`.`maxshown`, `".$prefix."adrotate`.`tracker`, `".$prefix."adrotate`.`weight`
						FROM `".$prefix."adrotate`, `".$prefix."adrotate_linkmeta`
						WHERE `".$prefix."adrotate_linkmeta`.`group` = '$group->group' 
							AND `".$prefix."adrotate_linkmeta`.`block` = 0 
							AND `".$prefix."adrotate_linkmeta`.`user` = 0 
							AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
							AND `".$prefix."adrotate`.`active` = 'yes' 
							AND '$now' >= `".$prefix."adrotate`.`startshow` 
							AND '$now' <= `".$prefix."adrotate`.`endshow`
						;");
				}

				if($linkmeta) {
					foreach($linkmeta as $meta) {
						$selected[$meta->id] = $meta->weight;

						if($meta->clicks >= $meta->maxclicks AND $meta->maxclicks > 0 AND $meta->tracker == "Y") {
							$selected = array_diff_key($selected, array($meta->id => $meta->weight));
						}
						if($meta->shown >= $meta->maxshown AND $meta->maxshown > 0) {
							$selected = array_diff_key($selected, array($meta->id => $meta->weight));
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
						
					$output = '';
					$break = 1;
					for($i=0;$i<$block_count;$i++) {
						$banner_id = adrotate_weight($selected);
	
						if($block->wrapper_before != '')
							$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES));
						$output .= adrotate_ad($banner_id, false);
						if($block->wrapper_after != '')
							$output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES));
	
						$selected = array_diff_key($selected, array($banner_id => 0));

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
			}
		} else {
			$output = adrotate_error('block_not_found', array($bock_id));
		}
	} else {
		$output = adrotate_error('block_no_id');
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
	// Nov 15 2010 - Moved ad formatting to new function adrotate_ad_output()
	*/
	
	if($banner_id) {
		$now = current_time('timestamp');
		
		$banner = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");

		if($banner) {
			$output = adrotate_ad_output($banner->id, $banner->bannercode, $banner->tracker, $banner->link, $banner->image, true);
		} else {
			$output = adrotate_error('ad_not_found');
		}
	} else {
		$output = adrotate_error('ad_no_id');
	}

	return $output;
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
		echo "<li>I'm using <a href=\"http://www.adrotateplugin.com/\" target=\"_blank\" title=\"AdRotate\">AdRotate</a></li>\n";
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
	echo '	<th width="40%">AdRotate Credits</th>';
	echo '	<th>AdRotate Updates</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td><ul>';
	echo '	<li>Find my website at <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>.</li>';
	echo '	<li>Give me your money to <a href="http://meandmymac.net/donate/" target="_blank">show your appreciation</a>. Thanks!</li>';
	echo '	<li>The plugin homepage is at <a href="http://www.adrotateplugin.com/" target="_blank">www.adrotateplugin.com</a>!</li>';
	echo '	<li>Read about <a href="http://www.adrotateplugin.com/page/updates.php" target="_blank">updates</a>!</li>';
	echo '	<li>Need help? <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">support</a> is available!</li>';
	echo '</ul></td>';
	echo '<td style="border-left:1px #ddd solid;">';
	meandmymac_rss_widget(5);
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}

/*-------------------------------------------------------------
 Name:      adrotate_notice

 Purpose:   Credits shown on statistics
 Receive:   -none-
 Return:    -none-
 Since:		3.1
-------------------------------------------------------------*/
function adrotate_notice() {
	echo '<table class="widefat" style="margin-top: .5em">';

	echo '<thead>';
	echo '<tr valign="top">';
	echo '	<th>AdRotate</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td>';
	echo '	Cached results are refreshed every 6 hours.<br />';
	echo '	All statistics are indicative. They do not nessesarily reflect results counted by other parties.<br />';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}

/*-------------------------------------------------------------
 Name:      adrotate_user_notice
 
 Purpose:   Credits shown on user statistics
 Receive:   -none-
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_user_notice() {
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