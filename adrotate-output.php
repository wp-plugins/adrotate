<?php
/*  
Copyright 2010-2014 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_ad

 Purpose:   Show requested ad
 Receive:   $banner_id, $individual, $group, $block (obsolete), $site
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad($banner_id, $individual = true, $group = 0, $block = 0, $site = 0) {
	global $wpdb, $adrotate_config, $adrotate_crawlers, $adrotate_debug;

	$now 				= adrotate_now();
	$today 				= adrotate_date_start('day');
	$useragent 			= $_SERVER['HTTP_USER_AGENT'];
	$useragent_trim 	= trim($useragent, ' \t\r\n\0\x0B');

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
			$image = str_replace('%folder%', '/wp-content/banners/', $banner->image);		
			$output .= adrotate_ad_output($banner->id, $group, $banner->bannercode, $banner->tracker, $banner->link, $image);

			if($adrotate_config['enable_stats'] == 'Y') {
				$remote_ip 	= adrotate_get_remote_ip();
				if(is_array($adrotate_crawlers)) $crawlers = $adrotate_crawlers;
					else $crawlers = array();

				$nocrawler = true;
				foreach($crawlers as $crawler) {
					if(preg_match("/$crawler/i", $useragent)) $nocrawler = false;
				}

				if($adrotate_debug['timers'] == true) {
					$impression_timer = $now;
				} else {
					$impression_timer = $now - $adrotate_config['impression_timer'];
				}
		
				$ip = $wpdb->get_var($wpdb->prepare("SELECT `timer` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` = '%s' AND `stat` = 'i' AND `bannerid` = %d ORDER BY `timer` DESC LIMIT 1;", $remote_ip, $banner_id));
				if($ip < $impression_timer AND $nocrawler == true AND (strlen($useragent_trim) > 0 OR !empty($useragent))) {
					$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$wpdb->prefix."adrotate_stats` WHERE `ad` = %d AND `group` = %d AND `thetime` = $today;", $banner_id, $group));
					if($stats > 0) {
						$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_stats` SET `impressions` = `impressions` + 1 WHERE `id` = '$stats';");
					} else {
						$wpdb->insert($wpdb->prefix.'adrotate_stats', array('ad' => $banner_id, 'group' => $group, 'block' => 0, 'thetime' => $today, 'clicks' => 0, 'impressions' => 1));
					}
					$wpdb->insert($wpdb->prefix."adrotate_tracker", array('ipaddress' => $remote_ip, 'timer' => $now, 'bannerid' => $banner_id, 'stat' => 'i', 'useragent' => '', 'country' => '', 'city' => ''));
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
function adrotate_group($group_ids, $fallback = 0, $weight = 0, $site = 0) {
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

		$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$prefix."adrotate_groups` WHERE `name` != '' AND `id` = %d;", $group_array[0]));

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
						$output .= '<div class="g g-'.$group->id.'">';
						$i = 1;
						foreach($selected as $key => $value) {
							$output .= '<div class="a-'.$group->id.' c-'.$i.'">';
							if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
							$output .= adrotate_ad($key, 0, $group->id);
							if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
							$output .= '</div>';
							$i++;
						}
						$output .= '</div><div class="clear"></div>';
					} else if($group->modus == 2) { // Block of ads
						$block_count = $group->gridcolumns * $group->gridrows;
						if($array_count < $block_count) $block_count = $array_count;
						$output .= '<div class="block_outer b b-'.$group->id.'">';
						$j = 1;
						for($i=0;$i<$block_count;$i++) {
							$banner_id = array_rand($selected, 1);
							$output .= '<div class="block_inner a a-'.$group->id;
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
						$output .= adrotate_ad($banner_id, 0, $group->id);
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
	if($block_id AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_blocks';")) {
		$now = adrotate_now();
		$prefix = $wpdb->prefix;
		
		// Get block specs
		$block = $wpdb->get_row($wpdb->prepare("SELECT * FROM `".$prefix."adrotate_blocks` WHERE `id` = %d;", $block_id));
		if($block) {
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

				if($results) {
					$i = 0;
					foreach($results as $result) {
						$selected[$result->id] = 6;
						$selected = adrotate_filter_schedule($selected, $result);

						$i++;
					}
				}
				
				$array_count = count($selected);

				if($array_count > 0) {
					$block_count = $block->columns * $block->rows;
					($block->adwidth == 'auto') ? $adwidth = 'auto' : $adwidth = $block->adwidth.'px';	
					($block->adheight == 'auto') ? $adheight = 'auto' : $adheight = $block->adheight.'px';	
					if($array_count < $block_count) $block_count = $array_count;
				
					$output .= '<div id="bl-'.$block->id.'" class="block_outer" style="overflow:auto;margin:0;padding:'.$block->gridpadding.'px;clear:none;width:auto;height:auto;">';
					
					$j = 1;
					for($i=0;$i<$block_count;$i++) {
						$banner_id = array_rand($selected);

						$output .= '<div id="al-'.$banner_id.'" class="block_inner';
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
						$output .= '" class="margin:'.$block->admargin.'px;clear:none;float:left;width:'.$adwidth.';height:'.$adheight.';border:'.$block->adborder.';">';

						if($block->wrapper_before != '') {$output .= stripslashes(html_entity_decode($block->wrapper_before, ENT_QUOTES)); }
						$output .= adrotate_ad($banner_id, false, 0, 0);
						if($block->wrapper_after != '') { $output .= stripslashes(html_entity_decode($block->wrapper_after, ENT_QUOTES)); }
						$output .= '</div>';
	
						$selected = array_diff_key($selected, array($banner_id => 0));
					}
					$output .= '</div>';
					unset($block, $adwidth, $adheight);
				} else {
					$output .= adrotate_error('ad_unqualified');
				}
			}
			
			// Destroy data
			unset($groups, $results, $selected, $block);
			
		} else {
			$output .= '<!-- BLOCKS ARE MERGED WITH GROUPS, SET UP A GROUP INSTEAD! -->';
		}
	} else {
		$output .= '<!-- BLOCKS ARE MERGED WITH GROUPS, SET UP A GROUP INSTEAD! -->';
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
			$output = adrotate_ad_output($banner->id, 0, $banner->bannercode, $banner->tracker, $banner->link, $image);
		} else {
			$output = adrotate_error('ad_expired');
		}
	} else {
		$output = adrotate_error('ad_no_id');
	}

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_ad_output

 Purpose:   Prepare the output for viewing
 Receive:   $id, $group, $bannercode, $tracker, $link, $image
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad_output($id, $group = 0, $bannercode, $tracker, $link, $image) {
	global $blog_id, $adrotate_debug, $adrotate_config;

	$banner_output = $bannercode;
	$banner_output = stripslashes(htmlspecialchars_decode($banner_output, ENT_QUOTES));
	if($tracker == "Y" AND $adrotate_config['enable_stats'] == 'Y') {
		if(empty($blog_id) or $blog_id == '') {
			$blog_id = 0;
		}
		
		if($adrotate_config['clicktracking'] == 'Y') {
			// Fancy Clicktracking
			$banner_output = str_replace('%link%', $link, $banner_output);
			preg_match_all('/<a[^>](.*?)>/i', $banner_output, $matches, PREG_SET_ORDER);
			if(count($matches) > 0) {
				foreach($matches as &$value) {
					if(preg_match('/<a[^>]+class=\"(.+?)\"[^>]*>/i', $value[0], $regs)) {
					    $result = $regs[1]." gofollow";
						$str = str_replace('class="'.$regs[1].'"', 'class="'.$result.'"', $value[0]);	    
					} else {
						$str = str_replace('<a ', '<a class="gofollow" ', $value[0]);
					}
					$banner_output = str_replace($value[0], $str, $banner_output);
					unset($value, $regs, $result, $str);
				}
			}
			$banner_output = str_replace('<a ', '<a data-track="'.adrotate_clicktrack_hash($id, $group, 0, $blog_id).'" ', $banner_output);
			if($adrotate_debug['timers'] == true) {
				$banner_output = str_replace('<a ', '<a data-debug="1" ', $banner_output);
			}
			unset($matches);
		} else {
			// Default clicktracking		
			$url = add_query_arg('track', adrotate_clicktrack_hash($id, $group, 1, $blog_id), plugins_url().'/'.ADROTATE_FOLDER.'/library/clicktracker.php');
			$banner_output = str_replace('%link%', $url, $banner_output);
		}
	} else {
		$banner_output = str_replace('%link%', $link, $banner_output);
	}
	$banner_output = str_replace('%random%', rand(100000,999999), $banner_output);
	$banner_output = str_replace('%image%', $image, $banner_output);
	$banner_output = str_replace('%id%', $id, $banner_output);
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
	
	$in_footer = false;
	if($adrotate_config['jsfooter'] == "Y") {
		$in_footer = true;
	}
	
	if($adrotate_config['jquery'] == 'Y') wp_enqueue_script('jquery', false, false, false, $in_footer);
	if($adrotate_config['jshowoff'] == 'Y') wp_enqueue_script('jshowoff-adrotate', plugins_url('/library/jquery.jshowoff.adrotate.js', __FILE__), false, '0.3', $in_footer);
	if($adrotate_config['clicktracking'] == 'Y') wp_enqueue_script('clicktrack-adrotate', plugins_url('/library/jquery.clicktracker.js', __FILE__), false, '0.6', $in_footer);
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

	$groups = $wpdb->get_results("SELECT `id`, `modus`, `gridrows`, `gridcolumns`, `adwidth`, `adheight`, `admargin`, `adspeed` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' AND `modus` > 0 ORDER BY `id` ASC;");
	if($groups) {
		$array_js = $array_css = array();
		foreach($groups as $group) {
			if($group->adwidth != 'auto') {
				$ad_width = 'width:'.$group->adwidth.'px;';
				if($group->modus == 1) {
					$dynamic_width = "width:".$group->adwidth."px;";
				}
				if($group->modus == 2) {
					$width_sum = ($group->admargin * 2) + $group->adwidth;
					$width_min = $group->admargin * 2;
					$grid_width = "min-width:".$width_min."px; max-width:".$group->gridcolumns * $width_sum."px;";
				}
			} else {
				$dynamic_width = $grid_width = $ad_width = "width:auto;";
			}
			
			if($group->adheight != 'auto') {
				$ad_height = 'height:'.$group->adheight.'px;';
				if($group->modus == 1) {
					$dynamic_height = "height:".$group->adheight."px;";
				}
				if($group->modus == 2) {
					$height_sum = ($group->admargin * 2) + $group->adheight;
					$height_min = $group->admargin * 2;
					$grid_height = "min-height:".$height_min."px; max-height:".$group->gridrows * $height_sum."px;";
				}
			} else {
				$dynamic_height = $grid_height = $ad_height = "height:auto;";
			}


			if($group->modus == 1) {
				$array_js[] = "\tjQuery('.g-".$group->id."').gslider({ groupid: ".$group->id.", speed: ".$group->adspeed." });\n";
				$array_css[] = "\t.g-".$group->id." { ".$dynamic_width." ".$dynamic_height." margin:".$group->admargin."px; }\n";
				$array_css[] = "\t.a-".$group->id." { ".$dynamic_width." ".$dynamic_height." }\n";
			}

			if($group->modus == 2) {
				$array_css[] = "\t.b-".$group->id." { ".$grid_width." ".$grid_height." }\n";
				$array_css[] = "\t.a-".$group->id." { ".$ad_height." ".$ad_height." margin:".$group->admargin."px; }\n";
			}

			unset($group, $width_sum, $height_sum, $grid_width, $dynamic_width, $ad_width, $grid_height, $dynamic_height, $ad_height);
		}
		unset($groups);

		if($array_js) {
			$output_js = "jQuery(document).ready(function(){\n";
			$output_js .= "if(jQuery.fn.gslider) {\n";
			foreach($array_js as $js) {
				$output_js .= $js;
			}
			$output_js .= "}\n";
			$output_js .= "});\n";
			unset($array_js);
		}

		if($array_css) {
			$output_css = "\t.g { padding:0; overflow:hidden; }\n";
			$output_css .= "\t.b { padding:0; margin:0; overflow:hidden; clear:none; }\n";
			$output_css .= "\t.a { clear:none; float:left; }\n";
			foreach($array_css as $css) {
				$output_css .= $css;
			}
			$output_css .= "\t.clear, .block_both { clear:both; }\n";
			$output_css .= "\t.clear_l, .block_left { clear:left; }\n";
			$output_css .= "\t.clear_r, .block_right { clear:right; }\n";
			unset($array_css);
		}
	}

	if($adrotate_config['clicktracking'] == 'Y' OR isset($output_js)) {
		$output .= "<!-- AdRotate JS -->\n";
		$output .= "<script type=\"text/javascript\">\n";
		if($adrotate_config['clicktracking'] == 'Y') $output .= "var tracker_url = '".plugins_url()."/".ADROTATE_FOLDER."/library/clicktracker.php';\n";
		if(isset($output_js)) {
			$output .= $output_js;
		}
		$output .= "</script>\n";
		$output .= "<!-- /AdRotate JS -->\n\n";
	}

	if(isset($output_css) OR $adrotate_config['widgetpadding'] == "Y") {
		$output .= "<!-- AdRotate CSS -->\n";
		$output .= "<style type=\"text/css\" media=\"screen\">\n";
		if(isset($output_css)) {
			$output .= $output_css;
		}
		if($adrotate_config['widgetpadding'] == "Y") { 
			$output .= ".widget_adrotate_widgets { overflow:hidden; padding:0; }\n"; 
		}
		$output .= "</style>\n";
		$output .= "<!-- /AdRotate CSS -->\n\n";
	}
	unset($output_css, $output_js);

	echo $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_inject_pages

 Purpose:   Add an advert to a single page
 Receive:   $post_content
 Return:    $post_content
 Added:		3.7
-------------------------------------------------------------*/
function adrotate_inject_pages($post_content) { 
	global $wpdb, $post, $adrotate_debug;

	// Inject ads into page
	if(is_page()) {
		$ids = $wpdb->get_results("SELECT `id`, `page`, `page_loc`, `page_par` FROM `".$wpdb->prefix."adrotate_groups` WHERE `page_loc` > 0;");
		
		$page_array = array();
		foreach($ids as $id) {
			$pages = explode(",", $id->page);
			// Build array of groups for pages
			if(in_array($post->ID, $pages)) {
				$page_array[] = array('id' => $id->id, 'loc' => $id->page_loc, 'par' => $id->page_par, 'pages' => $pages);
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
				// Try to prevent the same ad from showing when there are multiple ads to show
				$choice_amount = substr_count($post_content, '<p>');
				if($choice_amount == 0 OR count($page_array) < $choice_amount) {
					$choice_amount = 2;
				}
				$page_choice = array_rand($page_array, $choice_amount);
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

			// Adverts inside the content
			if($page_array[$page_choice[0]]['loc'] == 4) {
				if(is_page($page_array[$page_choice[0]]['pages'])) {
					if(substr_count($post_content, '<p>') > $page_array[$page_choice[0]]['par']) {
						$paragraphs = explode("</p>", $post_content);
						$par = 0;
						$advert = 1;
						$post_content = '';
						foreach($paragraphs as $paragraph) {
							if($par == $page_array[$page_choice[$advert]]['par']-1) {
								$paragraph = $paragraph.adrotate_group($page_array[$page_choice[$advert]]['id']);
								if($page_array[$page_choice[$advert]]['par'] > 0) {
									$par = 0;
								} else {
									$par++;
								}
							} else {
								$par++;
							}
							$post_content .= $paragraph.'</p>';
							if(count($page_choice)-1 > $advert) $advert++;
						}
					}
				}
			}
		}
		unset($page_choice, $page_array, $paragraph, $advert);
	}

	return $post_content;
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
				// Try to prevent the same ad from showing when there are multiple ads to show
				$choice_amount = substr_count($post_content, '<p>');
				if($choice_amount == 0 OR count($cat_array) < $choice_amount) {
					$choice_amount = 2;
				}
				$cat_choice = array_rand($cat_array, $choice_amount);
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

			// Adverts inside the content
			if($cat_array[$cat_choice[0]]['loc'] == 4) {
				if(in_category($cat_array[$cat_choice[0]]['pages'])) {
					if(substr_count($post_content, '<p>') > $cat_array[$cat_choice[0]]['par']) {
						$paragraphs = explode("</p>", $post_content);
						$par = 0;
						$advert = 1;
						$post_content = '';
						foreach($paragraphs as $paragraph) {
							if($par == $cat_array[$cat_choice[$par]]['par']-1) {
								$paragraph = $paragraph.adrotate_group($cat_array[$cat_choice[$par]]['id']);
								if($cat_array[$cat_choice[$par]]['par'] > 0) {
									$par = 0;
								} else {
									$par++;
								}
							} else {
								$par++;
							}
							$post_content .= $paragraph.'</p>';
							if(count($cat_choice)-1 > $advert) $advert++;
						}
					}
				}
			}
		}
		unset($cat_choice, $cat_array, $paragraph, $advert);
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
				$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, Ad is not available at this time due to schedule/geolocation restrictions or does not exist!', 'adrotate').'</span>';
			} else {
				$result = '<!-- '.__('Error, Ad is not available at this time due to schedule/geolocation restrictions!', 'adrotate').' -->';
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

		// Groups
		case "group_no_id" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, no group ID set! Check your syntax!', 'adrotate').'</span>';
			return $result;
		break;

		case "group_not_found" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('Error, group does not exist! Check your syntax!', 'adrotate').' (ID: '.$arg[0].')</span>';
			return $result;
		break;

		// Database
		case "db_error" :
			$result = '<span style="font-weight: bold; color: #f00;">'.__('There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!', 'adrotate').'<br />'.__('If this does not solve the issue please seek support at', 'adrotate').' <a href="http://www.adrotateplugin.com/support/">www.adrotateplugin.com/support/</a></span>';
			return $result;
		break;

		// Misc
		default:
			$result = '<span style="font-weight: bold; color: #f00;">'.__('An unknown error occured.', 'adrotate').' (ID: '.$arg[0].')</span>';
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
 Name:      adrotate_welcome_pointer

 Purpose:   Show dashboard pointers
 Receive:   -none-
 Return:    -none-
 Since:		3.9.14
-------------------------------------------------------------*/
function adrotate_welcome_pointer() {
    $pointer_content = '<h3>AdRotate '.ADROTATE_DISPLAY.'</h3>';
    $pointer_content .= '<p><strong>Welcome</strong><br />Thanks for using AdRotate. Everything related to AdRotate is in this menu. Check out the <a href="http:\/\/www.adrotateplugin.com\/support\/knowledgebase\/" target="_blank">knowledgebase</a> and <a href="http:\/\/www.adrotateplugin.com\/support\/forums\/" target="_blank">forums</a> if you get stuck with something.</p>';
    $pointer_content .= '<p><strong>AdRotate Professional</strong><br />Did you know there is also a premium version of AdRotate? Learn how you can benefit from the <a href="admin.php?page=adrotate-pro" target="_blank">extra features</a>.</p>';
?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#toplevel_page_adrotate').pointer({
				'content':'<?php echo $pointer_content; ?>',
				'position':{
					'edge':'left', // left, right, top, bottom
					'align':'middle' // top, bottom, left, right, middle
				},
				close: function() {
	                $.post(ajaxurl, {
	                    pointer:'adrotate_free_'+<?php echo ADROTATE_VERSION.ADROTATE_DB_VERSION; ?>,
	                    action:'dismiss-wp-pointer'
	                });
				}
			}).pointer("open");
		});
	</script>
<?php
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
	echo '	<th colspan="2">'.__('Useful links', 'adrotate').'</th>';
	echo '	<th colspan="2" width="40%">'.__('Brought to you by', 'adrotate').'</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';
	echo '<tr>';
	echo '<td><center><a href="http://www.adrotateplugin.com" title="AdRotate plugin for WordPress"><img src="'.plugins_url('/images/adrotate-logo-60x60.png', __FILE__).'" alt="adrotate-logo-60x60" width="60" height="60" /></a></center></td>';
	echo '<td>'.__('Like the plugin?', 'adrotate').' <a href="http://wordpress.org/support/view/plugin-reviews/adrotate?rate=5#postform" target="_blank">'.__('Rate and review', 'adrotate').'</a> AdRotate.<br />';
	echo __('Buy AdRotate Professional here', 'adrotate').' <a href="//www.adrotateplugin.com/adrotate-pro/" target="_blank">adrotateplugin.com shop</a>.<br />';
	echo __('Check out the', 'adrotate').' <a href="//www.adrotateplugin.com/support/knowledgebase/" target="_blank">'.__('manuals', 'adrotate').'</a> '.__('and have the most popular features explained.', 'adrotate').'<br />';
	echo __('Grab the latest news and updates on the', 'adrotate').' <a href="//www.adrotateplugin.com/news/" target="_blank">'.__('AdRotate Blog','adrotate').'</a>.<br />';
	echo '</td>';

	echo '<td><center><a href="//ajdg.solutions/" title="AJdG Solutions"><img src="'.plugins_url('/images/ajdg-logo-100x60.png', __FILE__).'" alt="ajdg-logo-100x60" width="100" height="60" /></a></center></td>';
	echo '<td><a href="//ajdg.solutions/" title="AJdG Solutions">AJdG Solutions</a> - '.__('Your one stop for Webdevelopment, consultancy and anything WordPress! Find out more about what I can do for you!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="//ajdg.solutions/" target="_blank">'.__('AJdG Solutions', 'adrotate').'</a> '.__('website', 'adrotate').'.</li>';
	echo '</ul></td>';
	echo '</tr>';
	echo '</tbody>';

	echo '</table>';
	echo adrotate_trademark();
}

/*-------------------------------------------------------------
 Name:      adrotate_trademark
 
 Purpose:   Trademark notice
 Receive:   -none-
 Return:    -none-
 Since:		3.9.14
-------------------------------------------------------------*/
function adrotate_trademark() {
 return '<center><small>AdRotate&trade; and the AdRotate Logo are owned by Arnan de Gans for AJdG Solutions.</small></center>';
}

/*-------------------------------------------------------------
 Name:      adrotate_pro_notice
 
 Purpose:   Credits shown on user statistics
 Receive:   $d
 Return:    -none-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_pro_notice($d = '') {

	if($d == "t") echo __('Available in AdRotate Pro', 'adrotate').'. <a href="admin.php?page=adrotate-pro">'.__('More information...', 'adrotate').'</a>';
	else echo __('This feature is available in AdRotate Pro', 'adrotate').'. <a href="admin.php?page=adrotate-pro">'.__('Learn more', 'adrotate').'</a>!';
}
?>