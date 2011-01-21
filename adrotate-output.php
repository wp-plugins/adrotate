<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true) {
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Nov 15 2010 - Moved ad formatting to new function adrotate_ad_output()
	// Dec 10 2010 - Added check for single ad or not. Query updates for 3.0.1.
	// Dec 11 2010 - Check for single ad now works.
	// Dec 13 2010 - Exired/Non-existant error now as a comment
	// Jan 21 2011 - Added debug routine
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
		if($adrotate_debug == true) {
			echo "<p><strong>Ad specs</strong><pre>"; 
			print_r($banner); 
			echo "</pre></p>"; 
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
 Receive:   $group_ids, $fallback, $weight
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_group($group_ids, $fallback = 0, $weight = 0) {
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Dec 10 2010 - $fallback now works. Query updated.
	// Jan 05 2011 - Added support for weight system
	// Jan 16 2011 - Added support for weight override
	// Jan 21 2011 - Added debug routine
	*/

	if($group_ids) {
		$now = current_time('timestamp');
		$group_array = explode(",", $group_ids);
		$group_choice = array_rand($group_array, 1);
		$prefix = $wpdb->prefix;
		if($fallback == 0 OR $fallback == '') {
			$fallbackoverride = $wpdb->get_var("SELECT `fallback` FROM `".$prefix."adrotate_groups` WHERE `id` = '$group_array[$group_choice]';");
		}
		
		if($weight > 0) {
			$weightoverride = "	AND `".$prefix."adrotate`.`weight` >= '$weight'";
		}
		
		if($adrotate_debug == true) {
			echo "<p><strong>Group array</strong><pre>"; 
			print_r($group_array); 
			echo "</pre></p>"; 
		}			

		$linkmeta = $wpdb->get_results("
			SELECT `".$prefix."adrotate`.`id`, `".$prefix."adrotate`.`clicks`, `".$prefix."adrotate`.`maxclicks`, `".$prefix."adrotate`.`shown`, `".$prefix."adrotate`.`maxshown`, `".$prefix."adrotate`.`tracker`, `".$prefix."adrotate`.`weight`
			FROM `".$prefix."adrotate`, `".$prefix."adrotate_linkmeta` 
			WHERE `".$prefix."adrotate_linkmeta`.`group` = '$group_array[$group_choice]' 
				AND `".$prefix."adrotate_linkmeta`.`block` = 0 
				AND `".$prefix."adrotate_linkmeta`.`user` = 0
				AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad`
				AND `".$prefix."adrotate`.`active` = 'yes'
				AND `".$prefix."adrotate`.`type` = 'manual'
				AND `".$prefix."adrotate`.`startshow` <= '$now' 
				AND `".$prefix."adrotate`.`endshow` >= '$now'
				".$weightoverride."
			;");

		if($adrotate_debug == true) {
			echo "<p><strong>Initial selection</strong><pre>"; 
			print_r($linkmeta); 
			echo "</pre></p>"; 
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

			if(count($selected) > 0) {
				$banner_id = adrotate_weight($selected);
				
				if($adrotate_debug == true) {
					echo "<p><strong>Selected ad based on weight</strong><pre>"; 
					print_r($banner_id); 
					echo "</pre></p>"; 
				}			

				$output = adrotate_ad($banner_id, false);
			} else {
				$output = adrotate_fallback($fallbackoverride, 'expired');
			}
		} else {
			$output = adrotate_fallback($fallbackoverride, 'unqualified');
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
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Dec 10 2010 - Query updates for 3.0.1.
	// Jan 05 2011 - Added support for weight system
	// Jan 15 2011 - Fixed array being made for one group only
	// Jan 21 2011 - Added debug routine
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
					$ads = $wpdb->get_results(
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
					$results = array_merge($ads, $results);
				}

				if($adrotate_debug == true) {
					echo "<p><strong>Initial selection</strong><pre>"; 
					print_r($results); 
					echo "</pre></p>"; 
				}			

				if($results) {
					foreach($results as $result) {
						$selected[$result->id] = $result->weight;

						if($result->clicks >= $result->maxclicks AND $result->maxclicks > 0 AND $result->tracker == "Y") {
							$selected = array_diff_key($selected, array($result->id => $result->weight));
						}
						if($result->shown >= $result->maxshown AND $result->maxshown > 0) {
							$selected = array_diff_key($selected, array($result->id => $result->weight));
						}
					}
				}
				
				if($adrotate_debug == true) {
					echo "<p><strong>Pre-selected ads based on impressions and clicks (reduced on cycles)</strong><pre>"; 
					print_r($selected); 
					echo "</pre></p>"; 
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

						if($adrotate_debug == true) {
							echo "<p><strong>Selected ad based on weight (Cycle ".$i.")</strong><pre>"; 
							print_r($banner_id); 
							echo "</pre></p>"; 
						}			
	
						if($block->wrapper_before != '')
							$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES));
						$output .= adrotate_ad($banner_id, false);
						if($block->wrapper_after != '')
							$output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES));
	
						$selected = array_diff_key($selected, array($banner_id => 0));

						if($adrotate_debug == true) {
							echo "<p><strong>Looped array for blocks (Cycle ".$i.")</strong><pre>"; 
							print_r($selected); 
							echo "</pre></p>"; 
						}			
	
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
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Nov 15 2010 - Moved ad formatting to new function adrotate_ad_output()
	// Jan 21 2011 - Added debug routine
	*/
	
	if($banner_id) {
		$now = current_time('timestamp');
		
		$banner = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");

		if($adrotate_debug == true) {
			echo "<p><strong>Ad information</strong><pre>"; 
			print_r($banner); 
			echo "</pre></p>"; 
		}			

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
 Name:      adrotate_ad_output

 Purpose:   Prepare the output for viewing
 Receive:   $id, $bannercode, $tracker, $link, $image
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad_output($id, $bannercode, $tracker, $link, $image, $preview = false) {

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
	$banner_output = stripslashes(htmlspecialchars_decode($banner_output, ENT_QUOTES));

	return $banner_output;
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
	// Dec 10 2010 - No longer double checks for $fallback values
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
	echo '	<li>Check out the <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">knowledgebase</a> for manuals, how-to\'s and general information!</li>';
	echo '	<li>Need more help? <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">Ticket support</a> is available!</li>';
	echo '</ul></td>';
	echo '<td style="border-left:1px #ddd solid;">';
	meandmymac_rss_widget(5);
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}

/*-------------------------------------------------------------
 Name:      adrotate_notice

 Purpose:   Credits shown on global statistics
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
	echo '	Cached results are refreshed every 24 hours or the first time you log in after 24 hours.<br />';
	echo '	The overall stats do not take ads from other publishers into account.<br />';
	echo '	All statistics are indicative. They do not nessesarily reflect results counted by other parties.<br />';
	echo '	Your ads are published with <a href="http://www.adrotateplugin.com/" target="_blank">AdRotate</a> for WordPress.';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}
?>