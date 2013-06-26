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

	$now 				= adrotate_now();
	$today 				= adrotate_today();
	$useragent 			= $_SERVER['HTTP_USER_AGENT'];
	$useragent_trim 	= trim($useragent, ' \t\r\n\0\x0B');

	$grouporblock = '';
	if($group > 0) $grouporblock = " AND `group` = '$group'";
	if($block > 0) $grouporblock = " AND `block` = '$block'";

	$output = '';
	if($banner_id) {
		if($individual == true) {
			$banner = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d AND (`type` = 'active' OR `type` = '7days' OR `type` = '2days');", $banner_id));

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_ad()] Selected Ad, specs</strong><pre>";
				print_r($banner); 
				echo "</pre></p>"; 
			}
			
			if($banner) {
				$selected = array($banner->id => 0);			
				$selected = adrotate_filter_schedule($selected, $banner);
			} else {
				$selected = false;
			}
		} else {
			// Coming from a group or block, no checks (they're already ran elsewhere) just load the ad
			$banner = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `image` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $banner_id));
			$selected = array($banner->id => 0);			
			$schedules = array('Already checked when choosing group/block.');
		}
		
		if($selected) {
			if($adrotate_debug['timers'] == true) {
				$impression_timer = $now;
			} else {
				$impression_timer = $now - $adrotate_config['impression_timer'];
			}
		
			$image = str_replace('%folder%', $adrotate_config['banner_folder'], $banner->image);		
			$output .= adrotate_ad_output($banner->id, $group, $block, $banner->bannercode, $banner->tracker, $banner->link, $image);

			if($adrotate_config['enable_stats'] == 'Y') {
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
			}
		} else {
			$output .= adrotate_error('ad_expired', array($banner_id));
		}
		unset($banner, $schedules);
		
	} else {
		$output .= adrotate_error('ad_no_id');
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

	$output = '';
	if($group_ids) {
		$now = adrotate_now();
		if(!is_array($group_ids)) {
			$group_array = explode(",", $group_ids);
		} else {
			$group_array = $group_ids;
		}
		
		$group_choice = array_rand($group_array, 1);
		$prefix = $wpdb->prefix;

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_group()] Group array</strong><pre>"; 
			print_r($group_array); 
			print_r("Group choice: ".$group_array[$group_choice]); 
			echo "</pre></p>"; 
		}			

		$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = %d;", $group_array[$group_choice]));

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
				AND (`".$prefix."adrotate`.`type` = 'active' 
					OR `".$prefix."adrotate`.`type` = '2days'
					OR `".$prefix."adrotate`.`type` = '7days')
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

				$group->wrapper_before = str_replace('%id%', $group_array[$group_choice], $group->wrapper_before);
				$group->wrapper_after = str_replace('%id%', $group_array[$group_choice], $group->wrapper_after);

				if($group->wrapper_before != '') { $output .= stripslashes(html_entity_decode($group->wrapper_before, ENT_QUOTES)); }
				$output .= adrotate_ad($banner_id, false, $group_array[$group_choice], 0);
				if($group->wrapper_after != '') { $output .= stripslashes(html_entity_decode($group->wrapper_after, ENT_QUOTES)); }
			}
		}
		
		unset($results, $selected);
		
	} else {
		$output .= adrotate_error('group_no_id');
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

	$output = '';
	if($block_id) {
		$now = adrotate_now();
		$prefix = $wpdb->prefix;
		
		// Get block specs
		$block = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$prefix."adrotate_blocks` WHERE `id` = %d;", $block_id));
		if($block) {
			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_block()] Selected block</strong><pre>"; 
				print_r($block); 
				echo "</pre></p>"; 
			}			

			// Get groups in block
			$groups = $wpdb->get_results($wpdb->prepare("SELECT `group` FROM `".$prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = %d AND `user` = 0;", $block->id));
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
							AND (`".$prefix."adrotate`.`type` = 'active' 
								OR `".$prefix."adrotate`.`type` = '2days'
								OR `".$prefix."adrotate`.`type` = '7days')
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
				
					$output .= '<div id="b-'.$block->id.'" class="block_outer b-'.$block->id.'">';
					$j = 1;
					for($i=0;$i<$block_count;$i++) {
						$banner_id = array_rand($selected);

						$output .= '<div id="a-'.$banner_id.'" class="block_inner a-'.$block->id;
						if($block->columns == 1) {
							$output .= ' block_both ';						
						} else if($j == $block->columns) {
							$output .= ' block_right ';
							$j = 1;
						} else if($j == 1) {
							$output .= ' block_left ';
							$j++;
						} else {
							$j++;
						}
						$output .= '">';

						if($block->wrapper_before != '') {$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES)); }
						$output .= adrotate_ad($banner_id, false, 0, $block_id);
						if($block->wrapper_after != '') { $output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES)); }
						$output .= '</div>';
	
						$selected = array_diff_key($selected, array($banner_id => 0));

						if($adrotate_debug['general'] == true) {
							echo "<p><strong>[DEBUG][adrotate_block()] Selected ad (Cycle ".$i.")</strong><pre>"; 
							echo "Selected ad: ".$banner_id."<br />";
							echo "</pre></p>"; 
						}			
					}
					$output .= '</div>';
				} else {
					$output .= adrotate_error('ad_unqualified');
				}
			}
			
			// Destroy data
			unset($groups, $results, $selected, $block);
			
		} else {
			$output .= adrotate_error('block_not_found', array($block_id));
		}
	} else {
		$output .= adrotate_error('block_no_id');
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
		$now = adrotate_now();
		
		$banner = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $banner_id));

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_preview()] Ad information</strong><pre>"; 
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
	$banner_output = do_shortcode($banner_output);

	return $banner_output;
}

/*-------------------------------------------------------------
 Name:      adrotate_custom_css

 Purpose:   Load file uploaded popup style
 Receive:   -None-
 Return:	-None-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_custom_css() {
	global $wpdb;
	
	$blocks = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "adrotate_blocks` WHERE `name` != '' ORDER BY `id` ASC;");
	
	$output = "\n<!-- This site is using AdRotate v".ADROTATE_DISPLAY." to display their advertisements - http://www.adrotateplugin.com/ -->\n";
	if($blocks) {
		$output .= "<!-- AdRotate CSS for Blocks -->\n";
		$output .= "<style type=\"text/css\" media=\"screen\">\n";
		foreach($blocks as $block) {
			$adwidth = $block->adwidth.'px';
			if($block->adheight == 'auto') $adheight = 'auto';
				else $adheight = $block->adheight.'px';	
			$output .= ".b-".$block->id." { overflow:auto;margin:0;padding:".$block->gridpadding."px;clear:none;width:auto;height:auto; }\n";
			$output .= ".a-".$block->id." { margin:".$block->admargin."px;clear:none;float:left;width:".$adwidth.";height:".$adheight.";border:".$block->adborder."; }\n";
		}
		$output .= ".block_left { clear:left; }\n";
		$output .= ".block_right { clear:right; }\n";
		$output .= ".block_both { clear:both; }\n";
		$output .= "</style>\n";
		$output .= "<!-- / AdRotate CSS for Blocks -->\n\n";
		unset($blocks, $block);
	}
	echo $output;
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
			return $result;
		break;
		
		case "ad_unqualified" :
			if($adrotate_debug['general'] == true) {
				$result = '<span style="font-weight: bold; color: #f00;">'.__('Either there are no banners, they are disabled or none qualified for this location!', 'adrotate').'</span>';
			} else {
				$result = '<!-- '.__('Either there are no banners, they are disabled or none qualified for this location!', 'adrotate').' -->';
			}
			return $result;
		break;
		
		case "ad_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no Ad ID set! Check your syntax!', 'adrotate').'</span>';
			return $result;
		break;

		case "ad_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, ad could not be found! Make sure it exists.', 'adrotate').'</span>';
			return $result;
		break;

		// Groups
		case "group_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no group set! Check your syntax!', 'adrotate').'</span>';
			return $result;
		break;

		// Blocks
		case "block_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, Block', 'adrotate').' (ID: '.$arg[0].') '.__('does not exist! Check your syntax!', 'adrotate').'</span>';
			return $result;
		break;

		case "block_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no Block ID set! Check your syntax!', 'adrotate').'</span>';
			return $result;
		break;

		// Database
		case "db_error" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!', 'adrotate').'<br />'.__('If this does not solve the issue please seek support at', 'adrotate').' <a href="http://www.adrotateplugin.com/support/">www.adrotateplugin.com/support/</a></span>';
			return $result;
		break;

		// Misc
		default:
			$result = '<span style="font-weight: bold; color: #f00;">'.__('An unknown error occured.', 'adrotate').'</span>';
			return $result;
		break;
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
	echo '	<th width="27%">'.__('Your support makes a difference', 'adrotate').'</th>';
	echo '	<th>'.__('Useful links', 'adrotate').'</th>';
	echo '	<th width="35%">'.__('Brought to you by', 'adrotate').'</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr>';
	echo '<td><ul>';
	echo '	<li><center>'.__('Your awesome gift will ensure the continued development of AdRotate! Thanks for your consideration!', 'adrotate').'</center></li>';
	echo '	<li><center><a href="http://www.adrotateplugin.com/donate/" target="_blank"><img src="http://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" /></a></center></li>';
	echo '</ul></td>';

	echo '<td style="border-left:1px #ddd solid;"><ul>';
	echo '	<li>'.__('Like the plugin?', 'adrotate').' <a href="http://wordpress.org/support/view/plugin-reviews/adrotate?rate=5#postform" target="_blank">'.__('Rate and review', 'adrotate').'</a> AdRotate.</li>';
	echo '	<li>'.__('Find my website at', 'adrotate').' <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>. '.__('More on AdRotate on', 'adrotate').' <a href="http://www.adrotateplugin.com" target="_blank">adrotateplugin.com</a>!</li>';
	echo '	<li>'.__('Grab the latest news and updates on the', 'adrotate').' <a href="http://www.adrotateplugin.com/news/" target="_blank">'.__('AdRotate Blog','adrotate').'</a>. '.__('Be sure to subscribe!', 'adrotate').'</li>';
	echo '	<li>'.__('Check out the', 'adrotate').' <a href="http://www.adrotateplugin.com/support/knowledgebase/" target="_blank">'.__('manuals', 'adrotate').'</a> '.__('and have the most popular features explained.', 'adrotate').'</li>';
	echo '</ul></td>';

	echo '<td style="border-left:1px #ddd solid;"><ul>';
	echo '	<li><a href="http://www.ajdg.net" title="AJdG Solutions"><img src="'.WP_CONTENT_URL.'/plugins/adrotate/images/ajdg-logo-100x60.png" alt="ajdg-logo-100x60" width="100" height="60" align="left" style="padding: 0 10px 10px 0;" /></a>';
	echo '	<a href="http://www.ajdg.net" title="AJdG Solutions">AJdG Solutions</a> - '.__('Your one stop for Webdevelopment, consultancy and anything WordPress! When you need a custom plugin, theme customizations or have your site moved/migrated entirely. Find out more about what I can do for you on my website!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="http://www.ajdg.net" target="_blank">'.__('AJdG Solutions', 'adrotate').'</a> '.__('website', 'adrotate').'.</li>';
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
 Receive:   $d
 Return:    -none-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_pro_notice($d = '') {

	if($d == "t") echo __('Available in AdRotate Pro', 'adrotate').'. <a href="http://www.adrotateplugin.com/adrotate-pro/" target="_blank">'.__('Buy now', 'adrotate').'</a>!';
	else echo __('This feature is available in AdRotate Pro', 'adrotate').'. <a href="http://www.adrotateplugin.com/adrotate-pro/" target="_blank">'.__('Go Pro today', 'adrotate').'</a>!';
}
?>