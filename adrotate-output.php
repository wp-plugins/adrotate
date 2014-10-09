<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark (pending registration) of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

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
			$banner = $wpdb->get_row($wpdb->prepare(
				"SELECT 
					`id`, `bannercode`, `responsive`, `tracker`, `link`, `image` 
				FROM 
					`".$wpdb->prefix."adrotate` 
				WHERE 
					`id` = %d 
					AND (`type` = 'active' OR `type` = '7days' OR `type` = '2days')
				;", $banner_id));

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
			// Coming from a group, no checks (they're already ran elsewhere) just load the ad
			$banner = $wpdb->get_row($wpdb->prepare("SELECT `id`, `bannercode`, `tracker`, `link`, `image`, `responsive` FROM `".$wpdb->prefix."adrotate` WHERE `id` = %d;", $banner_id));
			$selected = array($banner->id => 0);			
			$schedules = array('Already checked when choosing group/block.');
		}
		
		if($selected) {
			$image = str_replace('%folder%', '/wp-content/banners/', $banner->image);		

			if($individual == true) $output .= '<div class="a-single a-'.$banner->id.'">';
			$output .= adrotate_ad_output($banner->id, $group, $banner->bannercode, $banner->tracker, $banner->link, $image, $banner->responsive);
			if($individual == true) $output .= '</div>';

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
					$selected[$ad->id] = 6;
					$selected = adrotate_filter_schedule($selected, $ad);
				}
				unset($ads);
				
				if($adrotate_debug['general'] == true) {
					echo "<p><strong>[DEBUG][adrotate_group] Reduced array based on schedule restrictions</strong><pre>"; 
					print_r($selected); 
					echo "</pre></p>"; 
				}			

				$array_count = count($selected);
				if($array_count > 0) {
					$before = $after = '';
					$wrapper_before = str_replace('%id%', $group_array[0], $group->wrapper_before);
					if($wrapper_before != '') $before = stripslashes(html_entity_decode($wrapper_before, ENT_QUOTES));
					$wrapper_after = str_replace('%id%', $group_array[0], $group->wrapper_after);
					if($wrapper_after != '') $after = stripslashes(html_entity_decode($wrapper_after, ENT_QUOTES));	

					$output .= '<div class="g g-'.$group->id.'">';

					if($group->modus == 1) { // Slider ads
						$i = 1;
						foreach($selected as $key => $value) {
							$output .= '<div class="g-dyn a-'.$key.' c-'.$i.'">';
							$output .= $before.adrotate_ad($key, 0, $group->id).$after;
							$output .= '</div>';
							$i++;
						}
					} else if($group->modus == 2) { // Block of ads
						$block_count = $group->gridcolumns * $group->gridrows;
						if($array_count < $block_count) $block_count = $array_count;
						$columns = 1;

						for($i=1;$i<=$block_count;$i++) {
							$banner_id = array_rand($selected);
							$output .= '<div class="g-col b-'.$group->id.' a-'.$banner_id.'">';
							$output .= $before.adrotate_ad($banner_id, 0, $group->id).$after;
							$output .= '</div>';

							if($columns == $group->gridcolumns AND $i != $block_count) {
								$output .= '</div><div class="g g-'.$group->id.'">';
								$columns = 1;
							} else {
								$columns++;
							}
							unset($selected[$banner_id]);
						}

					} else { // Default (single ad)
						$banner_id = array_rand($selected);
						$output .= '<div class="g-single a-'.$banner_id.'">';
						$output .= $before.adrotate_ad($banner_id, 0, $group->id).$after;
						$output .= '</div>';
					}

					$output .= '</div>';

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
 Name:      adrotate_inject_pages

 Purpose:   Add an advert to a single page
 Receive:   $post_content
 Return:    $post_content
 Added:		3.7
-------------------------------------------------------------*/
function adrotate_inject_pages($post_content) { 
	global $wpdb, $post, $adrotate_debug;
	
	if(is_page()) {
		// Inject ads into page
		$ids = $wpdb->get_results("SELECT `id`, `page`, `page_loc`, `page_par` FROM `".$wpdb->prefix."adrotate_groups` WHERE `page_loc` > 0;");
		
		$page_array = $group_array = array();
		foreach($ids as $id) {
			$pages = explode(",", $id->page);
			// Build array of groups for pages
			if(in_array($post->ID, $pages)) {
				$page_array[] = array('group' => $id->id, 'location' => $id->page_loc, 'paragraph' => $id->page_par, 'pages' => $pages);
				if($id->page_loc == 4) {
					$group_array[] = $id->id;
				}
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

			foreach($page_array as $page_output) {
				// Advert in front of content
				if($page_output['location'] == 1 OR $page_output['location'] == 3) {
					if(is_page($page_output['pages'])) {
						$advert_before = adrotate_group($page_output['group']);
				   		$post_content = $advert_before.$post_content;
					}
				}
	
				// Advert behind the content
				if($page_output['location'] == 2 OR $page_output['location'] == 3) {
					if(is_page($page_output['pages'])) {
						$advert_after = adrotate_group($page_output['group']);
				   		$post_content = $post_content.$advert_after;
					}
				}

				// Adverts inside the content
				if($page_output['location'] == 4 AND is_page($page_output['pages'])) {
					$paragraphs = explode("</p>", $post_content);
					$par = 1;
					$advert = 0;
					$post_content = '';
					foreach($paragraphs as $paragraph) {
						if($par == $page_output['paragraph']
						  OR ($par == 2 AND $page_output['paragraph'] == 20) 
						  OR ($par == 3 AND $page_output['paragraph'] == 30) 
						  OR ($par == 4 AND $page_output['paragraph'] == 40)) {
							$paragraph = $paragraph.'</p>'.adrotate_group($page_output['group']);

							if(count($paragraphs) > $advert) {
								$advert++;
							} else {
								$advert = 0;
							}

							if($page_output['paragraph'] > 1 AND $page_output['paragraph'] < 10) {
								$par = 1;
							} else {
								$par++;
							}
						} else {
							$par++;
						}
						$post_content .= $paragraph;
					}
				}
			}
		}
		unset($page_choice, $page_array, $group_array, $paragraph, $advert);
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

	if(is_single()) {
		// Inject ads into posts in specified category
		$ids = $wpdb->get_results("SELECT `id`, `cat`, `cat_loc`, `cat_par` FROM `".$wpdb->prefix."adrotate_groups` WHERE `cat_loc` > 0;");
		$category = get_the_category();
		
		$cat_array = $group_array = array();
		foreach($ids as $id) {
			$cats = explode(",", $id->cat);
			// Build array of groups for categories
			if(in_array($category[0]->cat_ID, $cats)) {
				$cat_array[] = array('group' => $id->id, 'location' => $id->cat_loc, 'paragraph' => $id->cat_par, 'categories' => $cats);
				if($id->cat_loc == 4) {
					$group_array[] = $id->id;
				}
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
	
			if(count($group_array) > 0) {
				$group_select = '';
				foreach($group_array as $key => $value) {
					$group_select .= ' `'.$wpdb->prefix.'adrotate_linkmeta`.`group` = '.$value.' OR';
				}
				$group_select = rtrim($group_select, " OR");
		
				// Get all ads in all selected groups
				$ads = $wpdb->get_results(
					"SELECT 
						`".$wpdb->prefix."adrotate`.`id`, 
						`".$wpdb->prefix."adrotate_linkmeta`.`group`
					FROM
						`".$wpdb->prefix."adrotate`, 
						`".$wpdb->prefix."adrotate_linkmeta` 
					WHERE 
						(".$group_select.") 
						AND `".$wpdb->prefix."adrotate_linkmeta`.`block` = 0 
						AND `".$wpdb->prefix."adrotate_linkmeta`.`user` = 0 
						AND `".$wpdb->prefix."adrotate`.`id` = `".$wpdb->prefix."adrotate_linkmeta`.`ad` 
						AND (`".$wpdb->prefix."adrotate`.`type` = 'active' 
							OR `".$wpdb->prefix."adrotate`.`type` = '2days'
							OR `".$wpdb->prefix."adrotate`.`type` = '7days')
					GROUP BY `".$wpdb->prefix."adrotate`.`id` 
					ORDER BY `".$wpdb->prefix."adrotate`.`id`;", ARRAY_A);
				shuffle($ads);
			}

			if($adrotate_debug['general'] == true) {
				echo "<p><strong>[DEBUG][adrotate_inject_posts()] Arrays</strong><pre>"; 
				echo "Group choices<br />";
				print_r($cat_choice); 
				echo "Ads in group<br />";
				print_r($ads); 
				echo "</pre></p>"; 
			}
	
			foreach($cat_array as $cat_output) {
				// Advert in front of content
				if($cat_output['location'] == 1 OR $cat_output['location'] == 3) {
					if(in_category($cat_output['categories'])) {
						$advert_before = adrotate_group($cat_output['group']);
						$post_content = $advert_before.$post_content;
					}
				}
				
				// Advert behind content
				if($cat_output['location'] == 2 OR $cat_output['location'] == 3) {
					if(in_category($cat_output['categories'])) {
						$advert_after = adrotate_group($cat_output['group']);
				   		$post_content = $post_content.$advert_after;
					}
				}

				// Adverts inside the content
				if($cat_output['location'] == 4 AND in_category($cat_output['categories'])) {
					$paragraphs = explode("</p>", $post_content);
					$adcount = count($ads);
					$par = 1;
					$advert = 0;
					$post_content = '';
					foreach($paragraphs as $paragraph) {
						if(($par == $cat_output['paragraph']
						  OR ($par == 2 AND $cat_output['paragraph'] == 20) 
						  OR ($par == 3 AND $cat_output['paragraph'] == 30) 
						  OR ($par == 4 AND $cat_output['paragraph'] == 40)) AND $adcount > 0) {
							$paragraph = $paragraph.'</p>'.adrotate_ad($ads[$advert]['id'], 0, $ads[$advert]['group']);

							if(count($paragraphs) > $advert AND $advert < $adcount-1) {
								$advert++;
							} else {
								$advert = 0;
							}

							if($cat_output['paragraph'] > 1 AND $cat_output['paragraph'] < 10) {
								$par = 1;
							} else {
								$par++;
							}
						} else {
							$par++;
						}
						$post_content .= $paragraph;
					}
				}
			}
		}
		unset($cat_choice, $cat_array, $group_array, $paragraph, $advert);
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
	global $wpdb;

	$output = '';
	if($block_id) {
		$groups = $wpdb->get_results($wpdb->prepare("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = %d AND `user` = 0;", $block_id));
		if($groups) {
			foreach($groups as $group) {
				$group_ids[] = $group->group;
			}
			$output .= adrotate_group($group_ids);
		}			
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
			$output = adrotate_ad_output($banner->id, 0, $banner->bannercode, $banner->tracker, $banner->link, $image, 'N');
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
 Receive:   $id, $group, $bannercode, $tracker, $link, $image, $responsive
 Return:    $banner_output
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_ad_output($id, $group = 0, $bannercode, $tracker, $link, $image, $responsive) {
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

	// Add Responsive classes
	preg_match_all('/<img[^>](.*?)>/i', $banner_output, $matches, PREG_SET_ORDER);
	if(count($matches) > 0) {
		foreach($matches as &$value) {
			if(preg_match('/<img[^>]+class=\"(.+?)\"[^>]*>/i', $value[0], $regs)) {
				$result = $regs[1];
				if($responsive == 'Y') $result .= " responsive";
				$result = trim($result);
				$str = str_replace('class="'.$regs[1].'"', 'class="'.$result.'"', $value[0]);	    
			} else {
				$result = '';
				if($responsive == 'Y') $result .= " responsive";
				$result = trim($result);
				if(strlen($result) > 0) {
					$str = str_replace('<img ', '<img class="'.$result.'" ', $value[0]);
				} else {
					$str = $value[0];
				}
			}
			$banner_output = str_replace($value[0], $str, $banner_output);
			unset($value, $regs, $result, $str);
		}
	}
	unset($matches);

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
	if(get_option('adrotate_dynamic_required') > 0) wp_enqueue_script('jshowoff-adrotate', plugins_url('/library/jquery.adrotate.dyngroup.js', __FILE__), false, '0.5.3', $in_footer);
	if($adrotate_config['clicktracking'] == 'Y') wp_enqueue_script('clicktrack-adrotate', plugins_url('/library/jquery.adrotate.clicktracker.js', __FILE__), false, '0.6', $in_footer);
	if(get_option('adrotate_responsive_required') > 0) wp_enqueue_script('responsive-adrotate', plugins_url('/library/jquery.adrotate.responsive.js', __FILE__), false, '0.4', $in_footer);

	if(!$in_footer) {
		add_action('wp_head', 'adrotate_custom_javascript');
	} else {
		add_action('wp_footer', 'adrotate_custom_javascript', 100);
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_custom_javascript

 Purpose:   Add required JavaScript to site
 Receive:   -None-
 Return:	-None-
 Since:		3.10.5
-------------------------------------------------------------*/
function adrotate_custom_javascript() {
	global $wpdb, $adrotate_config;

	$groups = $wpdb->get_results("SELECT `id`, `adspeed` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' AND `modus` = 1 ORDER BY `id` ASC;");
	if($groups) {
		$output_js = "jQuery(document).ready(function(){\n";
		$output_js .= "if(jQuery.fn.gslider) {\n";
		foreach($groups as $group) {
			$output_js .= "\tjQuery('.g-".$group->id."').gslider({ groupid: ".$group->id.", speed: ".$group->adspeed." });\n";
		}
		$output_js .= "}\n";
		$output_js .= "});\n";
		unset($groups);
	}

	if($adrotate_config['clicktracking'] == 'Y' OR $adrotate_config['adblock'] == 'Y' OR isset($output_js)) {
		$output = "<!-- AdRotate JS -->\n";
		$output .= "<script type=\"text/javascript\">\n";
		if($adrotate_config['clicktracking'] == 'Y') {
			$output .= "var tracker_url = '".plugins_url()."/".ADROTATE_FOLDER."/library/clicktracker.php';\n";
		}

		if(($adrotate_config['adblock'] == 'Y' AND !is_user_logged_in()) OR ($adrotate_config['adblock'] == 'Y' AND $adrotate_config['adblock_loggedin'] == "Y" AND is_user_logged_in())) {
			$output .= "jQuery(document).ready(function() {\n";
			$output .= "\tjQuery('body').adblockdetect({time: ".$adrotate_config['adblock_timer'].", message: \"".$adrotate_config['adblock_message']."\"});\n";
			$output .= "});\n";
		}

		if(isset($output_js)) {
			$output .= $output_js;
			unset($output_js);
		}
		$output .= "</script>\n";
		$output .= "<!-- /AdRotate JS -->\n\n";

		echo $output;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_custom_css

 Purpose:   Add required CSS to site head
 Receive:   -None-
 Return:	-None-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_custom_css() {
	global $wpdb, $adrotate_config;
	
	$output = "\n<!-- This site is using AdRotate v".ADROTATE_DISPLAY." to display their advertisements - https://www.adrotateplugin.com/ -->\n";

	$groups = $wpdb->get_results("SELECT `id`, `modus`, `gridrows`, `gridcolumns`, `adwidth`, `adheight`, `admargin` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' ORDER BY `id` ASC;");
	if($groups) {
		$output_css = "\t.g { margin:0px; padding:0px; overflow:hidden; line-height:1; zoom:1; }\n";
		$output_css .= "\t.g-col { position:relative; float:left; }\n";
		$output_css .= "\t.g-col:first-child { margin-left: 0; }\n";
		$output_css .= "\t.g-col:last-child { margin-right: 0; }\n";
		foreach($groups as $group) {
			if($group->modus == 0 AND $group->admargin > 0) { // Single ad group
				$output_css .= "\t.g-".$group->id." { margin:".$group->admargin."px; }\n";
			}
	
			if($group->modus == 1) { // Dynamic group
				if($group->adwidth != 'auto') {
					$width = "min-width:".$group->admargin."px; max-width:".$group->adwidth."px;";
				} else {
					$width = "width:auto;";
				}
				
				if($group->adheight != 'auto') {
					$height = "min-height:".$group->admargin."px; max-height:".$group->adheight."px;";
				} else {
					$height = "height:auto;";
				}
				$output_css .= "\t.g-".$group->id." { margin:".$group->admargin."px; ".$width." ".$height." }\n";

				unset($width_sum, $width, $height_sum, $height);
			}
	
			if($group->modus == 2) { // Block group
				if($group->adwidth != 'auto') {
					$width_sum = $group->gridcolumns * ($group->admargin + $group->adwidth + $group->admargin);
					$grid_width = "min-width:".$group->admargin."px; max-width:".$width_sum."px;";
				} else {
					$grid_width = "width:auto;";
				}
				
				$output_css .= "\t.g-".$group->id." { ".$grid_width." }\n";
				$output_css .= "\t.b-".$group->id." { margin:".$group->admargin."px; }\n";
				unset($width_sum, $grid_width, $height_sum, $grid_height);
			}
		}
		$output_css .= "\t@media only screen and (max-width: 480px) {\n";
		$output_css .= "\t\t.g-col, .g-dyn, .g-single { width:100%; margin-left:0; margin-right:0; }\n";
		$output_css .= "\t}\n";
		unset($groups);
	}

	if(isset($output_css) OR $adrotate_config['widgetpadding'] == "Y") {
		$output .= "<!-- AdRotate CSS -->\n";
		$output .= "<style type=\"text/css\" media=\"screen\">\n";
		if(isset($output_css)) {
			$output .= $output_css;
			unset($output_css);
		}
		if($adrotate_config['widgetpadding'] == "Y") { 
			$output .= ".widget_adrotate_widgets { overflow:hidden; padding:0; }\n"; 
		}
		$output .= "</style>\n";
		$output .= "<!-- /AdRotate CSS -->\n\n";
	}

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
	echo '	<p style="text-align: center;">'.__('Contact support if the issue persists:', 'adrotate').' <a href="https://www.adrotateplugin.com/support/" title="AdRotate Support" target="_blank">AdRotate Support</a>.</p>';
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
			$result = '<span style="font-weight: bold; color: #f00;">'.__('There was an error locating the database tables for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!', 'adrotate').'<br />'.__('If this does not solve the issue please seek support at', 'adrotate').' <a href="https://www.adrotateplugin.com/support/">www.adrotateplugin.com/support/</a></span>';
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
    $pointer_content .= '<p><strong>Welcome</strong><br />Thank you for using AdRotate. Everything related to AdRotate is in this menu. Check out the <a href="https:\/\/www.adrotateplugin.com\/support\/knowledgebase\/" target="_blank">knowledgebase</a> and <a href="https:\/\/www.adrotateplugin.com\/support\/forums\/" target="_blank">forums</a> if you get stuck with something.</p>';
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
	echo '<td><center><a href="https://www.adrotateplugin.com/?utm_source=adrotate_dashboard&utm_medium=adrotate_free&utm_campaign=adrotatefree" title="AdRotate plugin for WordPress"><img src="'.plugins_url('/images/adrotate-logo-60x60.png', __FILE__).'" alt="adrotate-logo-60x60" width="60" height="60" /></a></center></td>';
	echo '<td>'.__('Check out the', 'adrotate').' <a href="https://www.adrotateplugin.com/support/knowledgebase/?utm_source=adrotate_dashboard&utm_medium=adrotate_free&utm_campaign=adrotatefree" target="_blank">'.__('manuals', 'adrotate').'</a> '.__('and have the most popular features explained.', 'adrotate').'<br />';
	echo __('Grab the latest news and updates on the', 'adrotate').' <a href="https://www.adrotateplugin.com/news/?utm_source=adrotate_dashboard&utm_medium=adrotate_free&utm_campaign=adrotatefree" target="_blank">'.__('AdRotate Blog','adrotate').'</a>.<br />';
	echo __('Find my website at', 'adrotate').' <a href="http://meandmymac.net" target="_blank">meandmymac.net</a></td>';

	echo '<td><center><a href="https://ajdg.solutions/" title="AJdG Solutions"><img src="'.plugins_url('/images/ajdg-logo-100x60.png', __FILE__).'" alt="ajdg-logo-100x60" width="100" height="60" /></a></center></td>';
	echo '<td><a href="https://ajdg.solutions/" title="AJdG Solutions">AJdG Solutions</a> - '.__('Your one stop for Webdevelopment, consultancy and anything WordPress! Find out more about what I can do for you!', 'adrotate').' '.__('Visit the', 'adrotate').' <a href="https://ajdg.solutions/" target="_blank">AJdG Solutions</a> '.__('website', 'adrotate').'</td>';
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