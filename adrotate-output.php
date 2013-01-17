<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual, $group, $block
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true, $group = 0, $block = 0) {
	global $wpdb, $adrotate_config, $adrotate_crawlers, $adrotate_debug;

	$now 				= date('U');
	$today 				= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
	$useragent 			= $_SERVER['HTTP_USER_AGENT'];
	$useragent_trim 	= trim($useragent, ' \t\r\n\0\x0B');

	if($group > 0) $grouporblock = " AND `group` = '$group'";
	if($block > 0) $grouporblock = " AND `block` = '$block'";

	if($banner_id) {
		if($individual == true) {
			$banner = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d AND `type` = 'active';", $banner_id));

			if($adrotate_debug['general'] == true) {
				if($banner->timeframe == '') $banner->timeframe = "not used";
				echo "<p><strong>[DEBUG][adrotate_ad()] Selected Ad, specs</strong><pre>";
				print_r($banner); 
				echo "</pre></p>"; 
			}
			
			$selected = array($banner->id => 0);			
			$selected = adrotate_filter_schedule($selected, $banner);
		} else {
			// Coming from a group or block, no checks (they're already ran elsewhere) just load the ad
			$banner = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $banner_id));
			$selected = array($banner->id => 0);			
			$schedules = array('Already checked when choosing group/block.');
		}
		
		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_ad()] Ad to display (ID => (fake) weight)</strong><pre>";
			print_r($selected); 
			echo "</pre></p>"; 
		}
			
		if($adrotate_debug['timers'] == true) {
			$impression_timer = $now;
		} else {
			$impression_timer = $now - $adrotate_config['impression_timer'];
		}
		
		if($selected) {
			$image = str_replace('%folder%', $adrotate_config['banner_folder'], $banner->image);		
			$output = adrotate_ad_output($banner->id, $group, $block, $banner->bannercode, $banner->tracker, $banner->link, $image);

			$remote_ip 	= adrotate_get_remote_ip();
			if(is_array($adrotate_crawlers)) $crawlers = $adrotate_crawlers;
				else $crawlers = array();

			$nocrawler = true;
			foreach($crawlers as $crawler) {
				if(preg_match("/$crawler/i", $useragent)) $nocrawler = false;
			}
			$ip = $wpdb->get_var($wpdb->prepare("SELECT `timer` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` = '%s' AND `stat` = 'i' AND `bannerid` = %d ORDER BY `timer` DESC LIMIT 1;", $remote_ip, $banner_id));
			if($ip < $impression_timer AND $nocrawler == true AND (strlen($useragent_trim) > 0 OR !empty($useragent))) {
				$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$wpdb->prefix."adrotate_stats` WHERE `ad` = %d $grouporblock AND `thetime` = '$today';", $banner_id));
				if($stats > 0) {
					$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_stats` SET `impressions` = `impressions` + 1 WHERE `id` = '$stats';");
				} else {
					$wpdb->insert($wpdb->prefix.'adrotate_stats', array('ad' => $banner_id, 'group' => $group, 'block' => $block, 'thetime' => $today, 'clicks' => 0, 'impressions' => 1));
				}
				$wpdb->insert($wpdb->prefix."adrotate_tracker", array('ipaddress' => $remote_ip, 'timer' => $now, 'bannerid' => $banner_id, 'stat' => 'i', 'useragent' => ''));
			}
		} else {
			$output = adrotate_error('ad_expired', array($banner_id));
		}
		unset($banner, $schedules);
		
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

	if($group_ids) {
		$now = current_time('timestamp');
		if(!is_array($group_ids)) {
			$group_array = explode(",", $group_ids);
		} else {
			$group_array = $group_ids;
		}
		
		$group_choice = array_rand($group_array, 1);
		$prefix = $wpdb->prefix;

		if($fallback == 0 OR $fallback == '') {
			$fallback = $wpdb->get_var($wpdb->prepare("SELECT `fallback` FROM `".$prefix."adrotate_groups` WHERE `id` = %d;", $group_array[$group_choice]));
		}
		
		if($weight > 0) {
			$weightoverride = "	AND `".$prefix."adrotate`.`weight` >= '$weight'";
		} else {
			$weightoverride = "";
		}

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_group()] Group array</strong><pre>"; 
			print_r($group_array); 
			print_r("Group choice: ".$group_array[$group_choice]); 
			echo "</pre></p>"; 
		}			

		$results = $wpdb->get_results($wpdb->prepare("
			SELECT 
				`".$prefix."adrotate`.`id`, 
				`".$prefix."adrotate`.`tracker` 
			FROM 
				`".$prefix."adrotate`, 
				`".$prefix."adrotate_linkmeta`
			WHERE 
				`".$prefix."adrotate_linkmeta`.`group` = %d 
				AND `".$prefix."adrotate_linkmeta`.`block` = 0 
				AND `".$prefix."adrotate_linkmeta`.`user` = 0
				AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad`
				AND `".$prefix."adrotate`.`type` = 'active'
				".$weightoverride."
			;", $group_array[$group_choice]));

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_group()] All ads in group</strong><pre>"; 
			print_r($results); 
			echo "</pre></p>"; 
		}			

		if($results) {
			$i = 0;
			foreach($results as $result) {
				$selected[$result->id] = 6;
				$selected = adrotate_filter_schedule($selected, $result);

				$i++;
			}
			
			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_group()] Initial selection (filtered down by clicks, impressions, timeframes and schedules)</strong><pre>"; 
				print_r($selected); 
				echo "</pre></p>"; 
			}			

			if(count($selected) > 0) {
				$banner_id = array_rand($selected, 1);
				
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_group()] Selected ad based on weight</strong><pre>"; 
					print_r($banner_id); 
					echo "</pre></p>"; 
				}			

				$output = adrotate_ad($banner_id, false, $group_array[$group_choice], 0);
			}
		}
		
		unset($results, $selected);
		
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

	if($block_id) {
		$now = current_time('timestamp');
		$prefix = $wpdb->prefix;
		
		// Get block specs
		$block = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = %d;", $block_id));
		if($block) {
			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_block()] Selected block</strong><pre>"; 
				print_r($block); 
				echo "</pre></p>"; 
			}			

			// Get groups in block
			$groups = $wpdb->get_results($wpdb->prepare("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = %d AND `user` = 0;", $block->id));
			if($groups) {
				// Get all ads in all groups and process them in an array
				$results = array();
				foreach($groups as $group) {
					$ads = $wpdb->get_results(
						"SELECT 
							`".$prefix."adrotate`.`id`, 
							`".$prefix."adrotate`.`tracker` 
						FROM 
							`".$prefix."adrotate`, 
							`".$prefix."adrotate_linkmeta` 
						WHERE 
							`".$prefix."adrotate_linkmeta`.`group` = '$group->group' 
							AND `".$prefix."adrotate_linkmeta`.`block` = 0 
							AND `".$prefix."adrotate_linkmeta`.`user` = 0 
							AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
							AND `".$prefix."adrotate`.`type` = 'active' 
						;");
					$results = array_merge($ads, $results);
					unset($ads);
				}

				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_block()] Groups in block</strong><pre>"; 
					print_r($groups); 
					echo "</pre></p>"; 
					echo "<p><strong>[DEBUG][adrotate_block()] All ads in block</strong><pre>"; 
					print_r($results); 
					echo "</pre></p>"; 
				}			

				if($results) {
					$i = 0;
					foreach($results as $result) {
						$selected[$result->id] = 6;
						$selected = adrotate_filter_schedule($selected, $result);

						$i++;
					}
				}
				
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_block()] Reduced array based on schedule and timeframe restrictions</strong><pre>"; 
					print_r($selected); 
					echo "</pre></p>"; 
				}			

				$array_count = count($selected);

				if($array_count > 0) {
					$block_count = $block->columns * $block->rows;
					if($array_count < $block_count) $block_count = $array_count;

					$selected = array_rand($selected, $block_count);
					shuffle($selected);
				
					if($adrotate_debug['general'] == true) {
						echo "<p><strong>[DEBUG][adrotate_block()] Reduced array randomly picked</strong><pre>"; 
						print_r($selected); 
						echo "</pre></p>"; 
					}			
	
					// grab border width in px
					list($adborder, $rest) = explode (" ", $block->adborder, 2);
					$adborder = rtrim($adborder, "px");

					// set definitive block size
					$adwidth = $block->adwidth.'px';
					if($block->adheight == 'auto') $adheight = 'auto';
						else $adheight = $block->adheight.'px';
					
					//Set float
					if($block->gridfloat == 'none') $gridfloat = 'none';
					if($block->gridfloat == 'left') $gridfloat = 'left;';
					if($block->gridfloat == 'right') $gridfloat = 'right;';
					if($block->gridfloat == 'inherit') $gridfloat = 'inherit;';
					
					$output = '';
					$output .= '<style type="text/css" media="screen">';
					$output .= '.block_outer { margin:0; padding:'.$block->gridpadding.'px; display:block; float:'.$gridfloat.'; }';
					$output .= '.block_inner { margin:'.$block->admargin.'px; padding:0; display:block; float:left; width:'.$block->adwidth.'; height:'.$block->adheight.'; border:'.$block->adborder.'; }';
					$output .= '.block_first { clear:left; }';
					$output .= '.block_last { clear:right; }';
					$output .= '</style>';

					$output .='<div id="'.$block->id.'" class="block_outer">';
					
					$j = 1;
					foreach($selected as $key => $banner_id) {
						$output .= '<div id="'.$j.' '.$banner_id.'"class="block_inner';
						if($j == $block->rows) {
							$output .= ' block_last ';
							$j = 1;
						} else if($j == 1) {
							$output .= ' block_first ';
							$j++;
						} else {
							$j++;
						}
						$output .= '">';
						if($block->wrapper_before != '') {$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES)); }
						$output .= adrotate_ad($banner_id, false, 0, $block-id);
						if($block->wrapper_after != '') { $output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES)); }
						$output .= '</div>';
	
						$selected = array_diff_key($selected, array($banner_id => 0));

						if($adrotate_debug['general'] == true) {
							echo "<p><strong>[DEBUG][adrotate_block()] Selected ad</strong><pre>"; 
							echo "Selected ad: ".$banner_id."<br />";
							echo "</pre></p>"; 
						}
					}
				} else {
					$output = adrotate_error('ad_unqualified');
				}
			}
			
			// Destroy data
			unset($groups, $results, $selected, $block);
			
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
	global $wpdb, $adrotate_debug, $adrotate_config;

	if($banner_id) {
		$now = current_time('timestamp');
		
		$banner = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $banner_id));

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_preview()] Ad information</strong><pre>"; 
			$memory = (memory_get_usage() / 1024 / 1024);
			echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
			$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
			echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
			print_r($banner); 
			echo "</pre></p>"; 
		}			

		if($banner) {
			$image = str_replace('%folder%', $adrotate_config['banner_folder'], $banner->image);		
			$output = adrotate_ad_output($banner->id, 0, 0, $banner->bannercode, $banner->tracker, $banner->link, $image, 1);
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
function adrotate_ad_output($id, $group = 0, $block = 0, $bannercode, $tracker, $link, $image, $preview = 0) {
	global $adrotate_debug;

	$now = time();
	$banner_output = $bannercode;
	if($tracker == "Y") {
		if($adrotate_debug['track'] == true) {
			$meta = "$id,$group,$block,$link";
		} else {
			$meta = base64_encode("$id,$group,$block,$link");
		}

		$url = add_query_arg('track', $meta, WP_CONTENT_URL."/plugins/adrotate/adrotate-out.php");
		if($preview == true) $url = add_query_arg('preview', '1', $url);
		$banner_output = str_replace('%link%', $url, $banner_output);
	} else {
		$banner_output = str_replace('%link%', $link, $banner_output);
	}
	$banner_output = str_replace('%random%', rand(100000,999999), $banner_output);
	$banner_output = str_replace('%image%', $image, $banner_output);
	$banner_output = str_replace('%id%', $id, $banner_output);
	$banner_output = stripslashes(htmlspecialchars_decode($banner_output, ENT_QUOTES));

	return $banner_output;
}

/*-------------------------------------------------------------
 Name:      adrotate_inject_posts

 Purpose:   Add an advert to a single post
 Receive:   $post_content
 Return:    $post_content
 Added:		3.7
-------------------------------------------------------------*/
function adrotate_inject_posts($post_content) { 
	global $wpdb, $post, $adrotate_debug;

	// Inject ads into page
	if(is_page()) {
		$ids = $wpdb->get_results("SELECT `id`, `page`, `page_loc` FROM `".$wpdb->prefix."adrotate_groups` WHERE `page_loc` > 0;");
		
		$page_array = array();
		foreach($ids as $id) {
			$pages = explode(",", $id->page);
			// Build array of groups for pages
			if(in_array($post->ID, $pages)) {
				$page_array[] = array('id' => $id->id, 'loc' => $id->page_loc, 'pages' => $pages);
			}
		}

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_inject_posts()] Arrays</strong><pre>"; 
			echo "Group ids (unsorted)<br />";
			print_r($ids); 
			echo "page_array<br />";
			print_r($page_array); 
			echo "</pre></p>"; 
		}			
		unset($ids, $pages);

		if(count($page_array) > 0) {
			if(count($page_array) > 1) {
				$page_choice = array_rand($page_array, 2);
			} else {
				$page_choice = array(0,0);
			}

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_inject_posts()] Arrays</strong><pre>"; 
				echo "Group choices<br />";
				print_r($page_choice); 
				echo "</pre></p>"; 
			}			

			if($page_array[$page_choice[0]]['loc'] == 1 OR $page_array[$page_choice[0]]['loc'] == 3) {
				if(is_page($page_array[$page_choice[0]]['pages'])) {
					$advert_before = adrotate_group($page_array[$page_choice[0]]['id']);
			   		$post_content = $advert_before.$post_content;
				}
			}
		
			if($page_array[$page_choice[1]]['loc'] == 2 OR $page_array[$page_choice[1]]['loc'] == 3) {
				if(is_page($page_array[$page_choice[1]]['pages'])) {
					$advert_after = adrotate_group($page_array[$page_choice[1]]['id']);
			   		$post_content = $post_content.$advert_after;
				}
			}
		}
		unset($page_choice, $page_array);
	}
	
	// Inject ads into posts in specified category
	if(is_single()) {
		$ids = $wpdb->get_results("SELECT `id`, `cat`, `cat_loc` FROM `".$wpdb->prefix."adrotate_groups` WHERE `cat_loc` > 0;");
		$category = get_the_category();
		
		$cat_array = array();
		foreach($ids as $id) {
			$cats = explode(",", $id->cat);
			// Build array of groups for categories
			if(in_array($category[0]->cat_ID, $cats)) {
				$cat_array[] = array('id' => $id->id, 'loc' => $id->cat_loc, 'categories' => $cats);
			}
		}
	
		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_inject_posts()] Arrays</strong><pre>"; 
			echo "Group ids (unsorted)<br />";
			print_r($ids); 
			echo "cat_array<br />";
			print_r($cat_array); 
			echo "</pre></p>"; 
		}			
		unset($ids, $cats);

		if(count($cat_array) > 0) {
			if(count($cat_array) > 1) {
				$cat_choice = array_rand($cat_array, 2);
			} else {
				$cat_choice = array(0,0);
			}


			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_inject_posts()] Arrays</strong><pre>"; 
				echo "Group choices<br />";
				print_r($cat_choice); 
				echo "</pre></p>"; 
			}			

			if($cat_array[$cat_choice[0]]['loc'] == 1 OR $cat_array[$cat_choice[0]]['loc'] == 3) {
				if(in_category($cat_array[$cat_choice[0]]['categories'])) {
					$advert_before = adrotate_group($cat_array[$cat_choice[0]]['id']);
					$post_content = $advert_before.$post_content;
				}
			}
			
			if($cat_array[$cat_choice[1]]['loc'] == 2 OR $cat_array[$cat_choice[1]]['loc'] == 3) {
				if(in_category($cat_array[$cat_choice[1]]['categories'])) {
					$advert_after = adrotate_group($cat_array[$cat_choice[1]]['id']);
			   		$post_content = $post_content.$advert_after;
				}
			}
		}
		unset($cat_choice, $cat_array);
	}

	return $post_content;
}

/*-------------------------------------------------------------
 Name:      adrotate_meta

 Purpose:   Sidebar meta
 Receive:   -none-
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_meta() {
	echo "<li>". __('I\'m using', 'adrotate') ." <a href=\"http://www.adrotateplugin.com/\" target=\"_blank\" title=\"AdRotate\">AdRotate</a></li>\n";
}

/*-------------------------------------------------------------
 Name:      adrotate_nonce_error

 Purpose:   Display a formatted error if Nonce fails
 Receive:   -none-
 Return:    -none-
 Since:		3.7.4.2
-------------------------------------------------------------*/
function adrotate_nonce_error() {
	echo '	<h2 style="text-align: center;">'.__('Oh no! Something went wrong!', 'adrotate').'</h2>';
	echo '	<p style="text-align: center;">'.__('WordPress was unable to verify the authenticity of the url you have clicked. Verify if the url used is valid or log in via your browser.', 'adrotate').'</p>';
	echo '	<p style="text-align: center;">'.__('If you have received the url you want to visit via email, you are being tricked!', 'adrotate').'</p>';
	echo '	<p style="text-align: center;">'.__('Contact support if the issue persists:', 'adrotate').' <a href="http://www.adrotateplugin.com/support/" title="AdRotate Support" target="_blank">AdRotate Support</a>.</p>';
}

/*-------------------------------------------------------------
 Name:      adrotate_error

 Purpose:   Show errors for problems in using AdRotate, should they occur
 Receive:   $action, $arg
 Return:    -none-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_error($action, $arg = null) {
	global $adrotate_debug;

	switch($action) {
		// Ads
		case "ad_expired" :
			if($adrotate_debug['general'] == true) {
				$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, Ad', 'adrotate').' (ID: '.$arg[0].') '.__('is not available at this time due to schedule restrictions or does not exist!', 'adrotate').'</span>';
			} else {
				$result = '<!-- '.__('Error, Ad', 'adrotate').' (ID: '.$arg[0].') '.__('is not available at this time due to schedule restrictions!', 'adrotate').' -->';
			}
		break;
		
		case "ad_unqualified" :
			if($adrotate_debug['general'] == true) {
				$result = '<span style="font-weight: bold; color: #f00;">'.__('Either there are no banners, they are disabled or none qualified for this location!', 'adrotate').'</span>';
			} else {
				$result = '<!-- '.__('Either there are no banners, they are disabled or none qualified for this location!', 'adrotate').' -->';
			}
		break;
		
		case "ad_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no Ad ID set! Check your syntax!', 'adrotate').'</span>';
		break;

		case "ad_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, ad could not be found! Make sure it exists.', 'adrotate').'</span>';
		break;

		// Groups
		case "group_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no group set! Check your syntax!', 'adrotate').'</span>';
		break;

		// Blocks
		case "block_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, Block', 'adrotate').' (ID: '.$arg[0].') '.__('does not exist! Check your syntax!', 'adrotate').'</span>';
		break;

		case "block_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no Block ID set! Check your syntax!', 'adrotate').'</span>';
		break;

		// Database
		case "db_error" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!', 'adrotate').'<br />'.__('If this does not solve the issue please seek support at', 'adrotate').' <a href="http://www.adrotateplugin.com/support/">www.adrotateplugin.com/support/</a></span>';
		break;

		// Misc
		default:
			$default = '<span style="font-weight: bold; color: #f00;">'.__('An unknown error occured.', 'adrotate').'</span>';
		break;

		return $default;

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
	global $adrotate_advert_status;
	if(current_user_can('adrotate_ad_manage')) {

		if(!is_array($adrotate_advert_status)) $data = unserialize($adrotate_advert_status);
			else $data = $adrotate_advert_status;

		if($data['total'] > 0) {
			if($data['expired'] > 0 AND $data['expiressoon'] == 0 AND $data['error'] == 0) {
				echo '<div class="error"><p>'.$data['expired'].' '.__('active ad(s) expired.', 'adrotate').' <a href="admin.php?page=adrotate-ads">'.__('Take action now', 'adrotate').'</a>!</p></div>';
			} else if($data['expired'] == 0 AND $data['expiressoon'] > 0 AND $data['error'] == 0) {
				echo '<div class="error"><p>'.$data['expiressoon'].' '.__('active ad(s) are about to expire.', 'adrotate').' <a href="admin.php?page=adrotate-ads">'.__('Check it out', 'adrotate').'</a>!</p></div>';
			} else if($data['expired'] == 0 AND $data['expiressoon'] == 0 AND $data['error'] > 0) {
				echo '<div class="error"><p>There are '.$data['error'].' '.__('active ad(s) with configuration errors.', 'adrotate').' <a href="admin.php?page=adrotate-ads">'.__('Solve this', 'adrotate').'</a>!</p></div>';
			} else {
				echo '<div class="error"><p>'.$data['expired'].' '.__('ad(s) expired.', 'adrotate').' '.$data['expiressoon'].' '.__('ad(s) are about to expire.', 'adrotate').' There are '.$data['error'].' '.__('ad(s) with configuration errors.', 'adrotate').' <a href="admin.php?page=adrotate-ads">'.__('Fix this as soon as possible', 'adrotate').'</a>!</p></div>';
			}
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_beta_notifications_dashboard

 Purpose:   Remind users that they're using a beta and should provide feedback
 Receive:   -none-
 Return:    -none-
 Since:		3.7
-------------------------------------------------------------*/
function adrotate_beta_notifications_dashboard() {
	if(current_user_can('adrotate_ad_manage'))
		echo '<div id="message" class="updated fade"><p>You are using AdRotate beta version <strong>'.ADROTATE_DISPLAY.'</strong>. Please provide <a href="'.admin_url('/admin.php?page=adrotate-beta').'">feedback</a> on your experience.</p></div>';
}

/*-------------------------------------------------------------
 Name:      adrotate_credits

 Purpose:   Promotional stuff shown throughout the plugin
 Receive:   -none-
 Return:    -none-
 Since:		3.7
-------------------------------------------------------------*/
function adrotate_credits() {

	echo '<table class="widefat" style="margin-top: .5em">';

	echo '<thead>';
	echo '<tr valign="top">';
	if(!stristr(ADROTATE_DISPLAY, 'Professional')) { echo '	<th width="27%">'.__('Your support makes a difference', 'adrotate').'</th>'; }
	echo '	<th>'.__('Useful links', 'adrotate').'</th>';
	echo '	<th width="35%">'.__('Brought to you by', 'adrotate').'</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr>';
	if(!stristr(ADROTATE_DISPLAY, 'Professional')) { 
		echo '<td><ul>';
		echo '	<li>'.__('The plugin homepage is at', 'adrotate').' <a href="http://www.adrotateplugin.com" target="_blank">www.adrotateplugin.com</a>!</li>';
		echo '	<li>'.__('Your gift will ensure the continued development of AdRotate!', 'adrotate').'</li>';
		echo '	<li><center><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=paypal%40ajdg%2enet&item_name=AdRotate%20Donation&item_number=Dashboard%20single%20donation&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=GB&bn=PP%2dDonationsBF&charset=UTF%2d8" target="_blank"><img src="http://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" /></a></center></li>';
		echo '</ul></td>';
	}

	echo '<td style="border-left:1px #ddd solid;"><ul>';
	echo '	<li>'.__('Find my website at', 'adrotate').' <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>.</li>';
	echo '	<li>'.__('Need fast and efficient assistance? Take a look at the', 'adrotate').' <a href="http://www.adrotateplugin.com/shop/" target="_blank">'.__('AdRotate Store', 'adrotate').'</a>! '.__('I also have a', 'adrotate').' <a href="http://www.adrotateplugin.com/support/" target="_blank">'.__('forum', 'adrotate').'</a>!</li>';
	echo '	<li>'.__('Grab the latest news and updates on the', 'adrotate').' <a href="http://www.adrotateplugin.com/news/" target="_blank">'.__('AdRotate Blog','adrotate').'</a>. '.__('Be sure to subscribe!', 'adrotate').'</li>';
	echo '	<li>'.__('Check out the', 'adrotate').' <a href="http://www.adrotateplugin.com/support/" target="_blank">'.__('manuals', 'adrotate').'</a> '.__('and have the most popular features explained.', 'adrotate').'</li>';
	echo '</ul></td>';

	echo '<td style="border-left:1px #ddd solid;"><ul>';
	echo '	<li><a href="http://www.ajdg.net" title="AJdG Solutions"><img src="'.WP_CONTENT_URL.'/plugins/adrotate/images/ajdg-logo-100x60.png" alt="ajdg-logo-100x60" width="100" height="60" align="left" style="padding: 0 10px 10px 0;" /></a>';
	echo '	'.__('Your one stop for Webdevelopment, consultancy and anything WordPress! When you need a custom plugin, theme customizations or have your site moved/migrated entirely. Find out more about what I can do for you on my website!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="http://www.ajdg.net" target="_blank">'.__('AJdG Solutions', 'adrotate').'</a> '.__('website', 'adrotate').'.</li>';
	echo '</ul></td>';
	echo '</tr>';
	echo '</tbody>';

	echo '</table>';
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
	echo '	<th>'.__('AdRotate Notice', 'adrotate').'</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr><td>';
	echo '	'.__('The overall stats do not take ads from other advertisers into account.', 'adrotate').'<br />';
	echo '	'.__('All statistics are indicative. They do not nessesarily reflect results counted by other parties.', 'adrotate').'<br />';
	echo '	'.__('Your ads are published with', 'adrotate').' <a href="http://www.adrotateplugin.com" target="_blank">AdRotate</a> '.__('for WordPress. Created by', 'adrotate').' <a href="http://www.ajdg.net" target="_blank">AJdG Solutions</a>.';
	echo '</td></tr>';
	echo '</tbody>';

	echo '</table>';
}

/*-------------------------------------------------------------
 Name:      adrotate_pro_notice
 
 Purpose:   Credits shown on user statistics
 Receive:   -none-
 Return:    -none-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_pro_notice() {

	echo __('This feature is available in AdRotate Pro', 'adrotate').'. <a href="http://www.adrotateplugin.com/features/" target="_blank">'.__('Get AdRotate Pro', 'adrotate').'</a>!';
}
?>