<?php
/*-------------------------------------------------------------
 Name:      adrotate_banner

 Purpose:   Show a banner as requested in the WP Theme or post
 Receive:   $group_ids, $banner_id, $preview, $shortcode
 Return:    $output
-------------------------------------------------------------*/
function adrotate_banner($group_ids, $banner_id = 0, $preview = false, $shortcode = false) {
	global $wpdb;

	if($group_ids) {
	
		$group_ids = explode(",", $group_ids);
		$x = array_rand($group_ids, 1);
		
		if($banner_id != 0) {
			$select_banner = " AND `id` = '".$banner_id."'";
		} else {
			$select_banner = " ORDER BY rand()";
		}
		
		if($preview == false) {
			$active_banner = " `active` = 'yes' AND";
		}
		
		$SQL = "SELECT `bannercode`, `image` FROM `".$wpdb->prefix."adrotate` 
			WHERE ".$active_banner." `group` = '".$group_ids[$x]."' ".$select_banner." LIMIT 1";
		
		if($banner = $wpdb->get_row($SQL)) {
			$output = $banner->bannercode;
			$output = str_replace('%image%', get_option('siteurl').'/wp-content/banners/'.$banner->image, $output);
		} else { 
			$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">The group is empty or the current (randomly picked) banner ID is not in this group!</span>';
		}
		
	} else {
	
		$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">Error, no group_id specified! Check your syntax!</span>';
	
	}
	
	$output = stripslashes(html_entity_decode($output, ENT_QUOTES));
	
	if($shortcode != false) {
		return $output;
	} else {
		echo $output;
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Show a banner as requested in a post or page using shortcodes
 Receive:   $atts, $content
 Return:    adrotate_banner()
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	if(!empty($atts['group'])) $group_ids = $atts['group'];
		else $group_ids = ''; 
	if(!empty($atts['banner'])) $banner_id = $atts['banner']; 
		else $banner_id = 0;
		
	return adrotate_banner($group_ids, $banner_id, false, true);
	
}
?>