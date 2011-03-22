<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual , $group , $block
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true, $group = 0, $block = 0) {
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Nov 15 2010 - Moved ad formatting to new function adrotate_ad_output()
	// Dec 10 2010 - Added check for single ad or not. Query updates for 3.0.1.
	// Dec 11 2010 - Check for single ad now works.
	// Dec 13 2010 - Exired/Non-existant error now as a comment
	// Jan 21 2011 - Added debug routine
	// Feb 22 2011 - Updated debug routine with Memory usage
	// Feb 28 2011 - Updated for new statistics system
	// Mar 12 2011 - Added new receiving values $group and $block for stats
	*/
	
	$now 	= current_time('timestamp');
	$today 	= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));

	if($group > 0) $grouporblock = " AND `group` = '$group'";
	if($block > 0) $grouporblock = " AND `block` = '$block'";

	if($banner_id) {
		if($individual == false) { 
			// Coming from a group or block, no checks just load the ad
			$banner = $wpdb->get_row("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");
		} else { 
			// Single ad, check if it's ok
			$banner = $wpdb->get_row("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `active` = 'yes' AND `startshow` <= '$now' AND `endshow` >= '$now' AND `id` = '$banner_id';");
		}
		
		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG] Ad specs</strong><pre>";
			$memory = (memory_get_usage() / 1024 / 1024);
			echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
			$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
			echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
			print_r($banner); 
			echo "</pre></p>"; 
		}			
		
		if($banner) {
			$output = adrotate_ad_output($banner->id, $group, $block, $banner->bannercode, $banner->tracker, $banner->link, $banner->image);

			$stats = $wpdb->get_var("SELECT `id` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$banner_id'$grouporblock AND `thetime` = '$today';");
			if($stats > 0) {
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_stats_tracker` SET `impressions` = `impressions` + 1 WHERE `id` = '$stats';");
			} else {
				$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_stats_tracker` (`ad`, `group`, `block`, `thetime`, `clicks`, `impressions`) VALUES ('$banner_id', '$group', '$block', '$today', '0', '1');");
			}
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
	// Feb 22 2011 - Updated debug routine with Memory usage
	// Feb 28 2011 - Updated ad selection for new statistics system
	// Mar 12 2011 - Added use of $group for adrotate_ad()
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
		
		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG] Group array</strong><pre>"; 
			$memory = (memory_get_usage() / 1024 / 1024);
			echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
			$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
			echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
			print_r($group_array); 
			echo "</pre></p>"; 
		}			

		$results = $wpdb->get_results("
			SELECT 
				`".$prefix."adrotate`.`id`, 
				`".$prefix."adrotate`.`maxclicks`, 
				`".$prefix."adrotate`.`maxshown`, 
				`".$prefix."adrotate`.`tracker`, 
				`".$prefix."adrotate`.`weight`, 
				`".$prefix."adrotate`.`updated`
			FROM 
				`".$prefix."adrotate`, 
				`".$prefix."adrotate_linkmeta` 
			WHERE 
				`".$prefix."adrotate_linkmeta`.`group` = '$group_array[$group_choice]' 
				AND `".$prefix."adrotate_linkmeta`.`block` = 0 
				AND `".$prefix."adrotate_linkmeta`.`user` = 0
				AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad`
				AND `".$prefix."adrotate`.`active` = 'yes'
				AND `".$prefix."adrotate`.`type` = 'manual'
				AND `".$prefix."adrotate`.`startshow` <= '$now' 
				AND `".$prefix."adrotate`.`endshow` >= '$now'
				".$weightoverride."
			;");

		if($results) {

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG] Initial selection</strong><pre>"; 
				$memory = (memory_get_usage() / 1024 / 1024);
				echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
				$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
				echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
				print_r($results); 
				echo "</pre></p>"; 
			}			

			foreach($results as $result) {
				$stats = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$result->id' AND `thetime` >= '$result->updated';");

				if($stats->clicks == null) $stats->clicks = '0';
				if($stats->impressions == null) $stats->impressions = '0';

				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG] Stats for ad $result->id since ".date("d-M-Y", $result->updated)."</strong><pre>"; 
					$memory = (memory_get_usage() / 1024 / 1024);
					echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
					$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
					echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
					print_r($stats); 
					echo "</pre></p>"; 
				}			

				$selected[$result->id] = $result->weight;

				if($stats->clicks >= $result->maxclicks AND $result->maxclicks > 0 AND $result->tracker == "Y") {
					$selected = array_diff_key($selected, array($result->id => $result->weight));
				}
				if($stats->impressions >= $result->maxshown AND $result->maxshown > 0) {
					$selected = array_diff_key($selected, array($result->id => $result->weight));
				}
				unset($stats);
			}
			unset($results);

			if(count($selected) > 0) {
				$banner_id = adrotate_weight($selected);
				
				if($adrotate_debug['general']['userroles'] == true) {
					echo "<p><strong>[DEBUG] Selected ad based on weight</strong><pre>"; 
					$memory = (memory_get_usage() / 1024 / 1024);
					echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
					$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
					echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
					print_r($banner_id); 
					echo "</pre></p>"; 
				}			

				$output = adrotate_ad($banner_id, false, $group_array[$group_choice]);

				unset($selected);

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
 Receive:   $block_id, $weight
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_block($block_id, $weight = 0) {
	global $wpdb, $adrotate_debug;

	/* Changelog:
	// Dec 10 2010 - Query updates for 3.0.1.
	// Jan 05 2011 - Added support for weight system
	// Jan 15 2011 - Fixed array being made for one group only
	// Jan 21 2011 - Added debug routine
	// Feb 22 2011 - Updated debug routine with Memory usage
	// Feb 28 2011 - Updated ad selection for new statistics system
	// Mar 12 2011 - Added use of $block for adrotate_ad()
	*/
	
	if($block_id) {
		$now = current_time('timestamp');
		$prefix = $wpdb->prefix;
		
		$block = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '$block_id';");
		if($block) {

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG] Selected block</strong><pre>"; 
				$memory = (memory_get_usage() / 1024 / 1024);
				echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
				$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
				echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
				print_r($block); 
				echo "</pre></p>"; 
			}			

			$groups = $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = '$block->id' AND `user` = 0;");
			if($groups) {

				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG] Groups in block</strong><pre>"; 
					$memory = (memory_get_usage() / 1024 / 1024);
					echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
					$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
					echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
					print_r($groups); 
					echo "</pre></p>"; 
				}			

				if($weight > 0) {
					$weightoverride = "	AND `".$prefix."adrotate`.`weight` >= '$weight'";
				}
		
				$results = array();
				foreach($groups as $group) {
					$ads = $wpdb->get_results(
						"SELECT 
							`".$prefix."adrotate`.`id`, 
							`".$prefix."adrotate`.`maxclicks`, 
							`".$prefix."adrotate`.`maxshown`, 
							`".$prefix."adrotate`.`tracker`, 
							`".$prefix."adrotate`.`weight`,
							`".$prefix."adrotate`.`updated`
						FROM 
							`".$prefix."adrotate`, 
							`".$prefix."adrotate_linkmeta` 
						WHERE 
							`".$prefix."adrotate_linkmeta`.`group` = '$group->group' 
							AND `".$prefix."adrotate_linkmeta`.`block` = 0 
							AND `".$prefix."adrotate_linkmeta`.`user` = 0 
							AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
							AND `".$prefix."adrotate`.`active` = 'yes' 
							AND '$now' >= `".$prefix."adrotate`.`startshow` 
							AND '$now' <= `".$prefix."adrotate`.`endshow` 
							".$weightoverride."
						;");
					$results = array_merge($ads, $results);
				}
				unset($groups);
					
				if($results) {
					if($adrotate_debug['general'] == true) {
						echo "<p><strong>[DEBUG] All ads from all groups</strong><pre>"; 
						$memory = (memory_get_usage() / 1024 / 1024);
						echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
						$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
						echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
						print_r($results); 
						echo "</pre></p>"; 
					}			

					foreach($results as $result) {
						$stats = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$result->id' AND `thetime` >= '$result->updated';");

						if($stats->clicks == null) $stats->clicks = '0';
						if($stats->impressions == null) $stats->impressions = '0';

						if($adrotate_debug['general'] == true) {
							echo "<p><strong>[DEBUG] Stats for ad $result->id since ".date("d-M-Y", $result->updated)."</strong><pre>"; 
							$memory = (memory_get_usage() / 1024 / 1024);
							echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
							$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
							echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
							print_r($stats); 
							echo "</pre></p>"; 
						}			
	
						$selected[$result->id] = $result->weight;

						if($stats->clicks >= $result->maxclicks AND $result->maxclicks > 0 AND $result->tracker == "Y") {
							$selected = array_diff_key($selected, array($result->id => $result->weight));
						}
						if($stats->impressions >= $result->maxshown AND $result->maxshown > 0) {
							$selected = array_diff_key($selected, array($result->id => $result->weight));
						}
						unset($stats);
					}
					unset($results);
				}
				
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG] Pre-selected ads based on impressions and clicks. (ad_id => weight)</strong><pre>"; 
					$memory = (memory_get_usage() / 1024 / 1024);
					echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
					$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
					echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
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

						if($adrotate_debug['general'] == true) {
							echo "<p><strong>[DEBUG] Reduced array (Cycle ".$i.")</strong><pre>"; 
							$memory = (memory_get_usage() / 1024 / 1024);
							echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
							print_r($selected); 
							echo "</pre></p>"; 
						}			
	
						if($block->wrapper_before != '')
							$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES));
						$output .= adrotate_ad($banner_id, false, 0, $block_id);
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

					if($adrotate_debug['general'] == true) {
						echo "<p><strong>[DEBUG] Looped array for blocks (Cycle ".$i.")</strong><pre>"; 
						$memory = (memory_get_usage() / 1024 / 1024);
						echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
						$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
						echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
						echo "</pre></p>"; 
					}			

					unset($selected);
			
				} else {
					$output = adrotate_error('ad_unqualified');
				}
			}
			
			unset($block);
			
		} else {
			$output = adrotate_error('block_not_found', array($block_id));
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
	// Feb 22 2011 - Updated debug routine with Memory usage
	*/
	
	if($banner_id) {
		$now = current_time('timestamp');
		
		$banner = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id';");

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG] Ad information</strong><pre>"; 
			$memory = (memory_get_usage() / 1024 / 1024);
			echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
			$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
			echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
			print_r($banner); 
			echo "</pre></p>"; 
		}			

		if($banner) {
			$output = adrotate_ad_output($banner->id, 0, s0, $banner->bannercode, $banner->tracker, $banner->link, $banner->image, true);
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
function adrotate_ad_output($id, $group = 0, $block = 0, $bannercode, $tracker, $link, $image, $preview = false) {

	$meta = urlencode("$id,$group,$block");
	
	$banner_output = $bannercode;
	if($tracker == "Y") {
		if($preview == true) {
			$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?track='.$meta.'&preview=true', $banner_output);		
		} else {
			$banner_output = str_replace('%link%', get_option('siteurl').'/wp-content/plugins/adrotate/adrotate-out.php?track='.$meta, $banner_output);
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
	echo '	<li>Check out the <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">knowledgebase</a> for manuals, general information!</li>';
	echo '	<li>Need more help? <a href="http://www.adrotateplugin.com/page/support.php" target="_blank">Ticket support</a> is available!</li>';
	echo '</ul></td>';
	echo '<td style="border-left:1px #ddd solid;">';
	meandmymac_rss_widget(5);
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
	echo '	<th>AdRotate Notice</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td>';
	echo '	The overall stats do not take ads from other advertisers into account.<br />';
	echo '	All statistics are indicative. They do not nessesarily reflect results counted by other parties.<br />';
	echo '	Your ads are published with <a href="http://www.adrotateplugin.com/" target="_blank">AdRotate</a> for WordPress.';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table';
}
?>