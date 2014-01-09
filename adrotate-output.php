<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual, $group, $block (deprecated)
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true, $group = 0, $block = 0) {
	global $wpdb, $adrotate_config, $adrotate_crawlers, $adrotate_debug;

	$now 				= adrotate_now();
	$today 				= adrotate_date_start('day');
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
		
			$image = str_replace('%folder%', '/wp-content/banners/', $banner->image);		
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
	global $wpdb, $adrotate_config, $adrotate_debug;

	$output = $group_select = $weightoverride = '';
	if($group_ids) {
		$now = adrotate_now();
		$prefix = $wpdb->prefix;

		(!is_array($group_ids)) ? $group_array = explode(",", $group_ids) : $group_array = $group_ids;

		foreach($group_array as $key => $value) {
			$group_select .= ' `'.$prefix.'adrotate_linkmeta`.`group` = '.$value.' OR';
		}
		$group_select = rtrim($group_select, " OR");

		$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$prefix."adrotate_groups` WHERE `id` = %d;", $group_array[0]));

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][adrotate_group] Selected group</strong><pre>"; 
			print_r($group);
			echo "</pre></p>";
		}

		if($group) {
			if($fallback == 0) $fallback = $group->fallback;
			if($weight > 0) $weightoverride = "	AND `".$prefix."adrotate`.`weight` >= '$weight'";
	
			$ads = $wpdb->get_results(
				"SELECT 
					`".$prefix."adrotate`.`id`, 
					`".$prefix."adrotate`.`tracker`, 
					`".$prefix."adrotate`.`weight`,
					`".$prefix."adrotate`.`timeframe`, 
					`".$prefix."adrotate`.`timeframelength`, 
					`".$prefix."adrotate`.`timeframeclicks`, 
					`".$prefix."adrotate`.`timeframeimpressions`, 
					`".$prefix."adrotate`.`timeframeimpressions`, 
					`".$prefix."adrotate`.`crate`, 
					`".$prefix."adrotate`.`irate`, 
					`".$prefix."adrotate`.`cbudget`, 
					`".$prefix."adrotate`.`ibudget`, 
					`".$prefix."adrotate`.`cities`, 
					`".$prefix."adrotate`.`countries`,
					`".$prefix."adrotate_linkmeta`.`group`
				FROM 
					`".$prefix."adrotate`, 
					`".$prefix."adrotate_linkmeta` 
				WHERE 
					(".$group_select.") 
					AND `".$prefix."adrotate_linkmeta`.`block` = 0 
					AND `".$prefix."adrotate_linkmeta`.`user` = 0 
					AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
					AND (`".$prefix."adrotate`.`type` = 'active' 
						OR `".$prefix."adrotate`.`type` = '2days'
						OR `".$prefix."adrotate`.`type` = '7days')
					".$weightoverride."
				GROUP BY `".$prefix."adrotate`.`id` 
				ORDER BY `".$prefix."adrotate`.`id`;");
		
			if($ads) {
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_group()] All ads in group</strong><pre>"; 
					print_r($ads); 
					echo "</pre></p>"; 
				}			

				foreach($ads as $ad) {
					$selected[$ad->id] = array('id' => $ad->id, 'weight' => $ad->weight, 'group' => $ad->group);
					$selected = adrotate_filter_schedule($selected, $ad);
				}
				unset($ads);
				
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_group] Reduced array based on schedule and timeframe restrictions</strong><pre>"; 
					print_r($selected); 
					echo "</pre></p>"; 
				}			

				$array_count = count($selected);
				if($array_count > 0) {
					$wrapper_before = str_replace('%id%', $group_array[0], $group->wrapper_before);
					$wrapper_after = str_replace('%id%', $group_array[0], $group->wrapper_after);

					if($group->modus == 1) { // Slider ads
						$selected = adrotate_shuffle($selected);
						$output .= '<div id="g-'.$group->id.'" class="g g-'.$group->id.'">';
						foreach($selected as $key => $value) {
							$output .= '<div id="a-'.$value['id'].'" class="a-'.$value['group'].'">';
							if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
							$output .= adrotate_ad($key, 0, $value['group']);
							if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
							$output .= '</div>';
						}
						$output .= '</div><div class="clear"></div>';
					} else if($group->modus == 2) { // Block of ads
						$block_count = $group->gridcolumns * $group->gridrows;
						if($array_count < $block_count) $block_count = $array_count;
						$output .= '<div id="b-'.$group->id.'" class="block_outer b b-'.$group->id.'">';
						$j = 1;
						for($i=0;$i<$block_count;$i++) {
							$banner_id = array_rand($selected, 1);
							$output .= '<div id="a-'.$banner_id.'" class="block_inner a a-'.$group->id;
							if($group->gridcolumns == 1) {
								$output .= ' clear';						
							} else if($j == $group->gridcolumns) {
								$output .= ' clear_r';
								$j = 1;
							} else if($j == 1) {
								$output .= ' clear_l';
								$j++;
							} else {
								$j++;
							}
							$output .= '">';
	
							if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
							$output .= adrotate_ad($banner_id, 0, $selected[$banner_id]['group']);
							if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
							$output .= '</div>';
		
							$selected = array_diff_key($selected, array($banner_id => 0));
						}
						$output .= '</div><div class="clear"></div>';
						$selected = array_diff_key($selected, array($banner_id => 0));
					} else { // Default (single ad)
						$banner_id = array_rand($selected, 1);
						if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
						$output .= adrotate_ad($banner_id, 0, $selected[$banner_id]['group']);
						if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
					}
					unset($selected);
				} else {
					$output .= adrotate_error('ad_expired');
				}
			} else { 
				$output .= adrotate_error('ad_unqualified');
			}
		} else {
			$output .= adrotate_error('group_not_found', array($group_array[0]));
		}
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
				
					$output .= '<div id="bl-'.$block->id.'" class="block_outer bl-'.$block->id.'">';
					
					$j = 1;
					for($i=0;$i<$block_count;$i++) {
						$banner_id = array_rand($selected);

						$output .= '<div id="al-'.$banner_id.'" class="block_inner al-'.$block->id;
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
			$image = str_replace('%folder%', '/wp-content/banners/', $banner->image);		
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

		$url = add_query_arg('track', $meta, plugins_url("/adrotate-out.php", __FILE__));
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
 Name:      adrotate_custom_scripts

 Purpose:   Add required scripts to site head
 Receive:   -None-
 Return:	-None-
 Since:		3.6
-------------------------------------------------------------*/
function adrotate_custom_scripts() {
	global $adrotate_config;
	
	if($adrotate_config['jquery'] == 'Y') wp_enqueue_script('jquery');
	if($adrotate_config['jshowoff'] == 'Y') wp_enqueue_script('jshowoff-min', plugins_url('/library/jquery.jshowoff.min.js', __FILE__), array('jquery'), '0.1.2');
}

/*-------------------------------------------------------------
 Name:      adrotate_custom_head

 Purpose:   Add required CSS and JavaScript to site head
 Receive:   -None-
 Return:	-None-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_custom_head() {
	global $wpdb, $adrotate_config;
	
	$output = "\n<!-- This site is using AdRotate v".ADROTATE_DISPLAY." to display their advertisements - http://www.adrotateplugin.com/ -->\n";

	$blocks = $wpdb->get_results("SELECT `id`, `adwidth`, `adheight`, `gridpadding`, `admargin`, `adborder` FROM `".$wpdb->prefix . "adrotate_blocks` WHERE `name` != '' ORDER BY `id` ASC;");
	if($blocks) {
		$output .= "<!-- AdRotate CSS (Deprecated) -->\n";
		$output .= "<style type=\"text/css\" media=\"screen\">\n";
		foreach($blocks as $block) {
			($block->adwidth == 'auto') ? $adwidth = 'auto' : $adwidth = $block->adwidth.'px';	
			($block->adheight == 'auto') ? $adheight = 'auto' : $adheight = $block->adheight.'px';	
			$output .= ".bl-".$block->id." { overflow:auto;margin:0;padding:".$block->gridpadding."px;clear:none;width:auto;height:auto; }\n";
			$output .= ".al-".$block->id." { margin:".$block->admargin."px;clear:none;float:left;width:".$adwidth.";height:".$adheight.";border:".$block->adborder."; }\n";
			unset($block, $adwidth, $adheight);
		}
		$output .= ".block_left { clear:left; }\n";
		$output .= ".block_right { clear:right; }\n";
		$output .= ".block_both { clear:both; }\n";
		if($adrotate_config['widgetpadding'] == "Y") { 
			$output .= ".widget_adrotate_widgets { overflow:hidden; padding:0; }\n"; 
		}
		$output .= "</style>\n";
		$output .= "<!-- /AdRotate CSS (Deprecated) -->\n\n";
		unset($blocks);
	}

	$groups = $wpdb->get_results("SELECT `id`, `modus`, `gridrows`, `gridcolumns`, `adwidth`, `adheight`, `admargin`, `adspeed` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' AND `modus` > 0 ORDER BY `id` ASC;");
	if($groups) {
		$array_js = $array_css = array();
		foreach($groups as $group) {
			$adwidth = $adheight = 'auto';
			if($group->adwidth != 'auto') {
				$width_sum = ($group->admargin * 2) + $group->adwidth;
				$adwidth = $group->adwidth."px";
				if($group->modus == 1) {
					$dynamic_width = "min-width:".$adwidth."; max-width:".$width_sum."px;";
				}
				if($group->modus == 2) {
					$grid_width = "min-width:".$adwidth."; max-width:".$group->gridcolumns * $width_sum."px;";
				}
			} else {
				$dynamic_width = $grid_width = "width:auto;";
			}
			
			if($group->adheight != 'auto') {
				$height_sum = ($group->admargin * 2) + $group->adheight;
				$adheight = $group->adheight."px";
				if($group->modus == 1) {
					$dynamic_height = "min-height:".$adheight."; max-height:".$height_sum."px;";
				}
				if($group->modus == 2) {
					$grid_height = "min-height:".$adheight."; max-height:".$group->gridrows * $height_sum."px;";
				}
			} else {
				$dynamic_height = $grid_height = "height:auto;";
			}


			if($group->modus == 1) {
				$array_js[] = "\tjQuery('#g-".$group->id."').jshowoff({ links: false, controls: false, effect: 'fade', changeSpeed: 300, speed: ".$group->adspeed.", hoverPause: false });\n";
				$array_css[] = "\t.g-".$group->id." { ".$dynamic_width." ".$dynamic_height." }\n";
			}

			if($group->modus == 2) {
				$array_css[] = "\t.b-".$group->id." { ".$grid_width." ".$grid_height." }\n";
			}

			$array_css[] = "\t.a-".$group->id." { margin:".$group->admargin."px; width:".$adwidth."; height:".$adheight."; }\n";
			unset($group, $width_sum, $adwidth, $height_sum, $adheight, $grid_width, $dynamic_width, $grid_height, $dynamic_height, $safety);
		}
		unset($groups);

		if($array_js) {
			$output .= "<!-- AdRotate JS -->\n";
			$output .= "<script type=\"text/javascript\">\n";
			$output .= "jQuery(document).ready(function(){\n";
			foreach($array_js as $js) {
				$output .= $js;
			}
			$output .= "});\n";
			$output .= "</script>\n";
			$output .= "<!-- /AdRotate JS -->\n\n";
			unset($array_js);
		}

		if($array_css) {
			$output .= "<!-- AdRotate CSS -->\n";
			$output .= "<style type=\"text/css\" media=\"screen\">\n";
			$output .= "\t.g { padding:0; position:relative; overflow:hidden; width:auto; height:auto; }\n";
			$output .= "\t.b { padding:0; margin:0; overflow:hidden; clear:none; }\n";
			$output .= "\t.a { clear:none; float:left; }\n";
			foreach($array_css as $css) {
				$output .= $css;
			}
			$output .= "\t.clear { clear:both; }\n";
			$output .= "\t.clear_l { clear:left; }\n";
			$output .= "\t.clear_r { clear:right; }\n";
			$output .= "</style>\n";
			$output .= "<!-- /AdRotate CSS -->\n\n";
			unset($array_css);
		}
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

		if(!is_array($adrotate_advert_status)) {
			$data = unserialize($adrotate_advert_status);
		} else {
			$data = $adrotate_advert_status;
		}

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
	if(current_user_can('adrotate_ad_manage')) {
		echo '<div id="message" class="updated fade"><p>You are using AdRotate beta version <strong>'.ADROTATE_DISPLAY.'</strong>. Please provide <a href="'.admin_url('/admin.php?page=adrotate-beta').'">feedback</a> on your experience.</p></div>';
	}
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
	echo '	<li>'.__('Find my website at', 'adrotate').' <a href="http://meandmymac.net" target="_blank">meandmymac.net</a>.</li>';
	echo '	<li>'.__('Grab the latest news and updates on the', 'adrotate').' <a href="http://www.adrotateplugin.com/news/" target="_blank">'.__('AdRotate Blog','adrotate').'</a>.</li>';
	echo '	<li>'.__('Check out the', 'adrotate').' <a href="http://www.adrotateplugin.com/support/knowledgebase/" target="_blank">'.__('manuals', 'adrotate').'</a> '.__('and have the most popular features explained.', 'adrotate').'</li>';
	echo '</ul></td>';

	echo '<td style="border-left:1px #ddd solid;"><ul>';
	echo '	<li><a href="http://www.ajdg.net" title="AJdG Solutions"><img src="'.plugins_url('/images/ajdg-logo-100x60.png', __FILE__).'" alt="ajdg-logo-100x60" width="100" height="60" align="left" style="padding: 0 10px 10px 0;" /></a>';
	echo '	<a href="http://www.ajdg.net" title="AJdG Solutions">AJdG Solutions</a> - '.__('Your one stop for Webdevelopment, consultancy and anything WordPress! Find out more about what I can do for you!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="http://www.ajdg.net" target="_blank">'.__('AJdG Solutions', 'adrotate').'</a> '.__('website', 'adrotate').'.</li>';
	echo '</ul></td>';
	echo '</tr>';
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