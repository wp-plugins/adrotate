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

	$now = adrotate_now();
	$today = adrotate_date_start('day');
	if(isset($_SERVER['HTTP_USER_AGENT'])) {
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$useragent = trim($useragent, ' \t\r\n\0\x0B');
	} else {
		$useragent = '';
	}

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
				if($ip < $impression_timer AND $nocrawler == true AND strlen($useragent) > 0) {
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
					$selected[$ad->id] = array('id' => $ad->id, 'group' => $ad->group);
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

					$output .= '<div class="g g-'.$group->id.'">';

					if($group->modus == 1) { // Slider ads
						$selected = adrotate_shuffle($selected);
						foreach($selected as $key => $value) {
							$output .= '<div class="g-dyn a-'.$key.'">';
							if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
							$output .= adrotate_ad($key, 0, $group->id);
							if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
							$output .= '</div>';
						}
					} else if($group->modus == 2) { // Block of ads
						$block_count = $group->gridcolumns * $group->gridrows;
						if($array_count < $block_count) $block_count = $array_count;
						$columns = 1;

						for($i=1;$i<=$block_count;$i++) {
							$banner_id = array_rand($selected);
							$output .= '<div class="g-col b-'.$group->id.' a-'.$banner_id.'">';
							if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
							$output .= adrotate_ad($banner_id, 0, $group->id);
							if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));
							$output .= '</div>';

							if($columns == $group->gridcolumns AND $i != $block_count) {
								$output .= '</div><div class="g g-'.$group->id.'">';
								$columns = 1;
							} else {
								$columns++;
							}
							$selected = array_diff_key($selected, array($banner_id => 0));
						}

					} else { // Default (single ad)
						$banner_id = array_rand($selected);
						$output .= '<div class="g-single a-'.$banner_id.'">';
						if($wrapper_before != '') $output .= stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
						$output .= adrotate_ad($banner_id, 0, $group->id);
						if($wrapper_after != '') $output .= stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));							
						$output .= '</div>';
					}

					$output .= '</div><div class="clear"></div>';

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
 Name:      adrotate_inject_posts

 Purpose:   Add adverts to a single post or page
 Receive:   $post_content
 Return:    $post_content
 Added:		3.7
-------------------------------------------------------------*/
function adrotate_inject_posts($post_content) { 
	global $wpdb, $post, $adrotate_config, $adrotate_debug;
	
	if(is_page() OR is_single()) {
		$prefix = $wpdb->prefix;
		
		$groups = $wpdb->get_results("SELECT `id`, `cat`, `cat_loc`, `cat_par`, `page`, `page_loc`, `page_par`, `wrapper_before`, `wrapper_after` FROM `".$prefix."adrotate_groups` WHERE `page_loc` > 0 ORDER BY `id` ASC;");

		$group_array = array();
		if(is_page()) {
			foreach($groups as $group) {
				$pages = explode(",", $group->page);
				if(in_array($post->ID, $pages)) {
					$group_array[] = array('group' => $group->id, 'location' => $group->page_loc, 'paragraph' => $group->page_par, 'before' => $group->wrapper_before, 'after' => $group->wrapper_after);
				}
			}
		}

		if(is_single()) {
			$category = get_the_category();
			foreach($groups as $group) {
				$categories = explode(",", $group->cat);
				if(in_array($category[0]->cat_ID, $categories)) {
					$group_array[] = array('group' => $group->id, 'location' => $group->cat_loc, 'paragraph' => $group->cat_par, 'before' => $group->wrapper_before, 'after' => $group->wrapper_after);
				}
			}
		}
		$group_array = array_unique($group_array);
		unset($groups, $category);

		if($adrotate_debug['general'] == true) {
			echo "<p><strong>[DEBUG][post injection] Array of pages/categories</strong><pre>"; 
			print_r($group_array); 
			echo "</pre></p>"; 
		}

		if(count($group_array) > 0) {
			$group_select = array_rand($group_array);

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][post injection] All Groups</strong><pre>"; 
				print_r($group_array); 
				echo "</pre></p><p><strong>[DEBUG][post injection] Selected Group</strong><pre>"; 
				print_r($group_select); 
				echo "</pre></p>"; 

			}

			// Get all ads in all selected groups
			$ads = $wpdb->get_results(
				"SELECT 
					`".$prefix."adrotate`.`id`, 
					`".$prefix."adrotate`.`tracker`, 
					`".$prefix."adrotate_linkmeta`.`group`
				FROM 
					`".$prefix."adrotate`, 
					`".$prefix."adrotate_linkmeta` 
				WHERE 
					`".$prefix."adrotate_linkmeta`.`group` = '".$group_array[$group_select]['group']."'
					AND `".$prefix."adrotate_linkmeta`.`block` = 0 
					AND `".$prefix."adrotate_linkmeta`.`user` = 0 
					AND `".$prefix."adrotate`.`id` = `".$prefix."adrotate_linkmeta`.`ad` 
					AND (`".$prefix."adrotate`.`type` = 'active' 
						OR `".$prefix."adrotate`.`type` = '2days'
						OR `".$prefix."adrotate`.`type` = '7days')
				GROUP BY `".$prefix."adrotate`.`id` 
				ORDER BY `".$prefix."adrotate`.`id`;");
	
			if($ads) {
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][post injection] Selected Ads</strong><pre>"; 
					print_r($ads); 
					echo "</pre></p>"; 
	
				}

				foreach($ads as $ad) {
					$selected[$ad->id] = array('id' => $ad->id, 'group' => $ad->group);
					$selected = adrotate_filter_schedule($selected, $ad);
				}
				unset($ads);

				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][post injection] Reduced array based on schedule and timeframe restrictions</strong><pre>"; 
					print_r($selected); 
					echo "</pre></p>"; 
				}			

				$before = $after = '';
				$wrapper_before = str_replace('%id%', $group_array[$group_select]['group'], $group_array[$group_select]['before']);
				if($wrapper_before != '') $before = stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
				$wrapper_after = str_replace('%id%', $group_array[$group_select]['group'], $group_array[$group_select]['after']);
				if($wrapper_after != '') $after = stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));							

				// Advert in front of content
				if($group_array[$group_select]['location'] == 1 OR $group_array[$group_select]['location'] == 3) {
					$banner_id = array_rand($selected);
					$post_content = $before.adrotate_ad($banner_id, 0, $group_array[$group_select]['group']).$after.$post_content;
					$selected = array_diff_key($selected, array($banner_id => 0));
				}
			
				// Advert behind the content
				if($group_array[$group_select]['location'] == 2 OR $group_array[$group_select]['location'] == 3) {
					$banner_id = array_rand($selected);
					$post_content = $post_content.$before.adrotate_ad($banner_id, 0, $group_array[$group_select]['group']).$after;
					$selected = array_diff_key($selected, array($banner_id => 0));
				}

				// Advert(s) inside the content
				if($group_array[$group_select]['location'] == 4) {
					$paragraphs = explode("</p>", $post_content);

					$par = 1;
					$post_content = '';
					foreach($paragraphs as $paragraph) {
						if($par == $group_array[$group_select]['paragraph'] 
						  OR ($par == 2 AND $group_array[$group_select]['paragraph'] == 20) 
						  OR ($par == 3 AND $group_array[$group_select]['paragraph'] == 30) 
						  OR ($par == 4 AND $group_array[$group_select]['paragraph'] == 40)) {
							$banner_id = array_rand($selected);
							$paragraph = $paragraph.$before.adrotate_ad($banner_id, 0, $group_array[$group_select]['group']).$after;
							if($group_array[$group_select]['paragraph'] > 1 AND $group_array[$group_select]['paragraph'] < 10) {
								$par = 1;
							} else {
								$par++;
							}
							$selected = array_diff_key($selected, array($banner_id => 0));
						} else {
							$par++;
						}
						$post_content .= $paragraph.'</p>';
					}
				}
			}
		}
		unset($group_array, $group_select, $paragraphs, $paragraph, $selected, $banner_id, $wrapper_before, $wrapper_after);
	}
	return $post_content;
}

/*-------------------------------------------------------------
 Name:      adrotate_block (OBSOLETE)

 Purpose:   This function is no longer supported. Replace blocks with groups!
 Receive:   $block_id, $weight
 Return:    $output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_block($block_id, $weight = 0) {
	global $wpdb, $adrotate_config, $adrotate_debug;

	$output = '';
	if($block_id) {
		$prefix = $wpdb->prefix;
		
		// Get groups in block
		$groups = $wpdb->get_results($wpdb->prepare("SELECT `group` FROM `".$prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = %d AND `user` = 0;", $block_id));
		if($groups) {
			foreach($groups as $group) {
				$group_ids[] = $group->group;
			}
			$output .= adrotate_group($group_ids);
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

	$banner_output = str_replace('%title%', get_the_title(), $banner_output);		
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
	if($adrotate_config['jshowoff'] == 'Y') wp_enqueue_script('jshowoff-adrotate', plugins_url('/library/jquery.adrotate.dyngroup.js', __FILE__), false, '0.3', $in_footer);
	if($adrotate_config['clicktracking'] == 'Y') wp_enqueue_script('clicktrack-adrotate', plugins_url('/library/jquery.adrotate.clicktracker.js', __FILE__), false, '0.6', $in_footer);
	if(get_option('adrotate_responsive_required') > 0) wp_enqueue_script('responsive-adrotate', plugins_url('/library/jquery.adrotate.responsive.js', __FILE__), false, '0.1', $in_footer);
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

	$groups = $wpdb->get_results("SELECT `id`, `modus`, `gridrows`, `gridcolumns`, `adwidth`, `adheight`, `admargin`, `adspeed` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' ORDER BY `id` ASC;");
	if($groups) {
		$array_js = $array_css = array();
		foreach($groups as $group) {
			if($group->modus == 0 OR $group->modus == 1) {
				$array_css[] = "\t.g-".$group->id." { margin:".$group->admargin."px; }\n";
			}
	
			if($group->modus == 1) {
				$array_js[] = "\tjQuery('.g-".$group->id."').gslider({ groupid: ".$group->id.", speed: ".$group->adspeed." });\n";
			}
	
			if($group->modus == 2) {
				if($group->adwidth != 'auto') {
					$width_sum = $group->gridcolumns * ($group->admargin + $group->adwidth + $group->admargin);
					$width_min = $group->admargin * 2;
					$grid_width = "min-width:".$width_min."px; max-width:".$width_sum."px;";
				} else {
					$grid_width = "width:auto;";
				}
				
				if($group->adheight != 'auto') {
/*
					$height_sum = $group->admargin + $group->adheight + $group->admargin; 
					$height_min = $group->admargin * 2;
					$grid_height = "min-height:".$height_min."px; max-height:".$height_sum."px;";
*/
					$grid_height = '';
				} else {
					$grid_height = "height:auto;";
				}

				$array_css[] = "\t.g-".$group->id." { ".$grid_width." ".$grid_height." }\n";
				$array_css[] = "\t.b-".$group->id." { margin:".$group->admargin."px; }\n";
			}

			unset($group, $width_sum, $width_min, $grid_width, $height_sum, $height_min, $grid_height);
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
			$output_css = "\t.g { positive:relative; clear:both; margin:0px; padding:0px; overflow:hidden; line-height:1; zoom:1; }\n";
			$output_css .= "\t.g:before, .g:after { display:table; }\n";
			$output_css .= "\t.g:after { clear:both; }\n";
			$output_css .= "\t.g-dyn { display:none; }\n";
			$output_css .= "\t.g-col { position:relative; float:left; }\n";
			$output_css .= "\t.g-col:first-child { margin-left: 0; }\n";
			$output_css .= "\t.g-col:last-child { margin-right: 0; }\n";
			foreach($array_css as $css) {
				$output_css .= $css;
			}
			unset($array_css);
		}
	}

	if($adrotate_config['clicktracking'] == 'Y' OR isset($output_js)) {
		$output .= "<!-- AdRotate JS -->\n";
		$output .= "<script type=\"text/javascript\">\n";
		if($adrotate_config['clicktracking'] == 'Y') {
			$output .= "var tracker_url = '".plugins_url()."/".ADROTATE_FOLDER."/library/clicktracker.php';\n";
		}

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
	echo '<td><a href="//ajdg.solutions/" title="AJdG Solutions">AJdG Solutions</a> - '.__('Your one stop for Webdevelopment, consultancy and anything WordPress! Find out more about what I can do for you!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="//ajdg.solutions/" target="_blank">AJdG Solutions</a> '.__('website', 'adrotate').'.</li>';
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