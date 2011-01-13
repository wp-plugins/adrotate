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
 Name:      adrotate_insert_input

 Purpose:   Prepare input form on saving new or updated banners
 Receive:   -None-
 Return:	-None-
 Since:		0.1 
-------------------------------------------------------------*/
function adrotate_insert_input() {
	global $wpdb, $adrotate_config;

	/* Changelog:
	// Nov 14 2010 - Updated queries for 3.0
	*/

	$id 				= $_POST['adrotate_id'];
	$author 			= $_POST['adrotate_username'];
	$title	 			= strip_tags(htmlspecialchars(trim($_POST['adrotate_title'], "\t\n "), ENT_QUOTES));
	$bannercode			= htmlspecialchars(trim($_POST['adrotate_bannercode'], "\t\n "), ENT_QUOTES);
	$thetime 			= date('U');
	$active 			= $_POST['adrotate_active'];
	$imageraw			= $_POST['adrotate_image'];
	$link				= strip_tags(htmlspecialchars(trim($_POST['adrotate_link'], "\t\n "), ENT_QUOTES));
	$tracker			= $_POST['adrotate_tracker'];
	$sday 				= strip_tags(trim($_POST['adrotate_sday'], "\t\n "));
	$smonth 			= strip_tags(trim($_POST['adrotate_smonth'], "\t\n "));
	$syear 				= strip_tags(trim($_POST['adrotate_syear'], "\t\n "));
	$eday 				= strip_tags(trim($_POST['adrotate_eday'], "\t\n "));
	$emonth 			= strip_tags(trim($_POST['adrotate_emonth'], "\t\n "));
	$eyear 				= strip_tags(trim($_POST['adrotate_eyear'], "\t\n "));
	$maxclicks			= strip_tags(trim($_POST['adrotate_maxclicks'], "\t\n "));
	$maxshown			= strip_tags(trim($_POST['adrotate_maxshown'], "\t\n "));
	$groups				= $_POST['groupselect'];
	$adtype				= strip_tags(trim($_POST['adrotate_type'], "\t\n "));
	$advertiser			= $_POST['adrotate_advertiser'];
	$weight				= $_POST['adrotate_weight'];

	if(current_user_can($adrotate_config['ad_manage'])) {
		if(strlen($title) < 1) $title = 'Ad '.$id;

		if(strlen($bannercode)!=0) {
			// Sort out dates
			if(strlen($smonth) == 0 OR !is_numeric($smonth)) 	$smonth 	= date('m');
			if(strlen($sday) == 0 OR !is_numeric($sday)) 		$sday 		= date('d');
			if(strlen($syear) == 0 OR !is_numeric($syear)) 		$syear 		= date('Y');
			if(strlen($emonth) == 0 OR !is_numeric($emonth)) 	$emonth 	= $smonth;
			if(strlen($eday) == 0 OR !is_numeric($eday)) 		$eday 		= $sday;
			if(strlen($eyear) == 0 OR !is_numeric($eyear)) 		$eyear 		= $syear+1;
			$startdate 	= gmmktime($shour, $sminute, 0, $smonth, $sday, $syear);
			$enddate 	= gmmktime($ehour, $eminute, 0, $emonth, $eday, $eyear);
			
			// Enddate is too early, reset
			if($enddate <= $startdate) $enddate = $startdate + 345600; // 4 days

			// Sort out click and impressions restrictions
			if(strlen($maxclicks) < 1 OR !is_numeric($maxclicks))	$maxclicks	= 0;
			if(strlen($maxshown) < 1 OR !is_numeric($maxshown))		$maxshown	= 0;

			// Set tracker value
			if(isset($tracker) AND strlen($tracker) != 0) $tracker = 'Y';
				else $tracker = 'N';
	
			// Determine image settings
			list($type, $file) = explode("|", $imageraw, 2);
			if($type == "banner") {
				$image = get_option('siteurl').'/wp-content/banners/'.$file;
			}
			
			if($type == "media") {
				$image = $wpdb->get_var("SELECT `guid` FROM ".$wpdb->prefix."posts 
				WHERE `post_type` = 'attachment' 
				AND (`post_mime_type` = 'image/jpeg' 
					OR `post_mime_type` = 'image/gif' 
					OR `post_mime_type` = 'image/png'
					OR `post_mime_type` = 'application/x-shockwave-flash')
				AND `guid` LIKE '%".$file."' LIMIT 1;");
			}

			// Determine status of ad and what to do next
			if($adtype == 'empty') {
				$action = 'new';
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `type` = 'manual' WHERE `id` = '$id';");
			} else {
				$action = 'update';
			}

			// Save the ad to the DB
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `title` = '$title', `bannercode` = '$bannercode', `updated` = '$thetime', `author` = '$author', `active` = '$active', `startshow` = '$startdate', `endshow` = '$enddate', `image` = '$image', `link` = '$link', `tracker` = '$tracker', `maxclicks` = '$maxclicks', `maxshown` = '$maxshown', `weight` = '$weight' WHERE `id` = '$id';");

			// Fetch records for the ad
			$groupmeta = $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$id' AND `block` = 0 AND `user` = 0;");
			foreach($groupmeta as $meta) {
				$group_array[] = $meta->group;
			}
			
			if(!is_array($group_array)) $group_array = array();
			if(!is_array($groups)) 		$groups = array();
			
			// Add new groups to this ad
			$insert = array_diff($groups, $group_array);
			foreach($insert as &$value) {
				$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_linkmeta` (`ad`, `group`, `block`, `user`) VALUES ($id, $value, 0, 0);"); 
			}
			unset($value);
			
			// Remove groups from this ad
			$delete = array_diff($group_array, $groups);
			foreach($delete as &$value) {
				$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$id' AND `group` = '$value' AND `block` = 0 AND `user` = 0;"); 
			}
			unset($value);

			// Fetch records for the ad, see if a publisher is set
			$linkmeta = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$id' AND `group` = 0 AND `block` = 0 AND `user` > 0;");

			// Add/update/remove publisher on this ad
			if($linkmeta == 0 AND $advertiser > 0) 	$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_linkmeta` (`ad`, `group`, `block`, `user`) VALUES ($id, 0, 0, $advertiser);"); 
			if($linkmeta == 1 AND $advertiser > 0) 	$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_linkmeta` SET `user` = '$advertiser' WHERE `ad` = '$id' AND `group` = '0' AND `block` = '0';");
			if($linkmeta == 1 AND $advertiser == 0) 	$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$id' AND `group` = 0 AND `block` = 0;"); 
			adrotate_return($action, array($id));
			exit;
		} else {
			adrotate_return('field_error');
			exit;
		}
	} else {
		adrotate_return('no_access');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_insert_group

 Purpose:   Save provided data for groups, update linkmeta where required
 Receive:   -None-
 Return:	-None-
 Since:		0.4
-------------------------------------------------------------*/
function adrotate_insert_group() {
	global $wpdb, $adrotate_config;

	/* Changelog:
	// Nov 14 2010 - Rewritten for 3.0
	*/

	$action		= $_POST['adrotate_action'];
	$id 		= $_POST['adrotate_id'];
	$name 		= strip_tags(trim($_POST['adrotate_groupname'], "\t\n "));
	$fallback 	= $_POST['adrotate_fallback'];
	$ads		= $_POST['adselect'];

	if(current_user_can($adrotate_config['group_manage'])) {
		if(strlen($name) < 1) $name = 'Group '.$id;

		// Fetch records for the group
		$linkmeta = $wpdb->get_results("SELECT `ad` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = '$id' AND `block` = 0 AND `user` = 0;");
		foreach($linkmeta as $meta) {
			$meta_array[] = $meta->ad;
		}
		
		if(!is_array($meta_array)) 	$meta_array = array();
		if(!is_array($ads)) 		$ads = array();
		
		// Add new ads to this group
		$insert = array_diff($ads,$meta_array);
		foreach($insert as &$value) {
				$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_linkmeta` (`ad`, `group`, `block`, `user`) VALUES ($value, $id, 0, 0);"); 
		}
		unset($value);
		
		// Remove ads from this group
		$delete = array_diff($meta_array,$ads);
		foreach($delete as &$value) {
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$value' AND `group` = '$id' AND `block` = 0 AND `user` = 0;"); 
		}
		unset($value);

		// Update the group itself
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_groups` SET `name` = '$name', `fallback` = '$fallback' WHERE `id` = '$id';");
		adrotate_return($action, array($id));
		exit;
	} else {
		adrotate_return('no_access');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_insert_block

 Purpose:   Save provided data for blocks, update linkmeta where required
 Receive:   -None-
 Return:	-None-
 Since:		3.0
-------------------------------------------------------------*/
function adrotate_insert_block() {
	global $wpdb, $adrotate_config;

	$action			= $_POST['adrotate_action'];
	$id 			= $_POST['adrotate_id'];
	$name 			= strip_tags(trim($_POST['adrotate_blockname'], "\t\n "));
	$adcount		= strip_tags(trim($_POST['adrotate_adcount'], "\t\n "));
	$columns 		= strip_tags(trim($_POST['adrotate_columns'], "\t\n "));
	$wrapper_before = htmlspecialchars(trim($_POST['adrotate_wrapper_before'], "\t\n "), ENT_QUOTES);
	$wrapper_after 	= htmlspecialchars(trim($_POST['adrotate_wrapper_after'], "\t\n "), ENT_QUOTES);
	$groups 		= $_POST['groupselect'];

	if(current_user_can($adrotate_config['block_manage'])) {
		if($adcount < 1 OR $adcount == '' OR !is_numeric($adcount)) $adcount = 1;
		if($columns < 1 OR $columns == '' OR !is_numeric($columns)) $columns = 1;
		if(strlen($name) < 1) $name = 'Block '.$id;

		// Fetch records for the block
		$linkmeta = $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `block` = '$id' AND `ad` = 0 AND `user` = 0;");
		foreach($linkmeta as $meta) {
			$meta_array[] = $meta->group;
		}
		
		if(!is_array($meta_array)) 	$meta_array = array();
		if(!is_array($groups)) 		$groups = array();
		
		// Add new groups to this block
		$insert = array_diff($groups,$meta_array);
		foreach($insert as &$value) {
			$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_linkmeta` (`ad`, `group`, `block`, `user`) VALUES (0, $value, $id, 0);"); 
		}
		unset($value);
		
		// Remove groups from this block
		$delete = array_diff($meta_array,$groups);
		foreach($delete as &$value) {
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `group` = '$value' AND `block` = '$id' AND `user` = 0;"); 
		}
		unset($value);

		// Update the block itself
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_blocks` SET `name` = '$name', `adcount` = '$adcount', `columns` = '$columns', `wrapper_before` = '$wrapper_before', `wrapper_after` = '$wrapper_after' WHERE `id` = '$id';");
		adrotate_return($action, array($id));
		exit;
	} else {
		adrotate_return('no_access');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_request_action

 Purpose:   Prepare action for banner or group from database
 Receive:   -none-
 Return:    -none-
 Since:		2.2
-------------------------------------------------------------*/
function adrotate_request_action() {
	global $wpdb, $adrotate_config;

	/* Changelog:
	// Nov 14 2010 - Removed "move" option, re-inserted access rights
	// Nov 16 2010 - Rebranded 'resetmultiple' and 'renewmultiple' to work like 'reset' and 'renew' original 'reset' and 'renew' are removed, added block support
	// Dec 4 2010 - Fixed bug where adrotate_renew() wasn't called properly
	// Dec 17 2010 - Added support for single ad actions (renew, reset, delete)
	*/

	if(isset($_POST['bannercheck'])) 	$banner_ids = $_POST['bannercheck'];
	if(isset($_POST['groupcheck'])) 	$group_ids = $_POST['groupcheck'];
	if(isset($_POST['blockcheck'])) 	$block_ids = $_POST['blockcheck'];
	
	if(isset($_POST['adrotate_id'])) 	$banner_ids = array($_POST['adrotate_id']);
	
	$actions = $_POST['adrotate_action'];	
	list($action, $specific) = explode("-", $actions);	

	if($banner_ids != '') {
		foreach($banner_ids as $banner_id) {
			if($action == 'deactivate') {
				if(current_user_can($adrotate_config['ad_manage'])) {
					adrotate_active($banner_id, 'deactivate');
					$result_id = $banner_id;
				} else {
					adrotate_return('no_access');
				}
			}
			if($action == 'activate') {
				if(current_user_can($adrotate_config['ad_manage'])) {
					adrotate_active($banner_id, 'activate');
					$result_id = $banner_id;
				} else {
					adrotate_return('no_access');
				}
			}
			if($action == 'delete') {
				if(current_user_can($adrotate_config['ad_delete'])) {
					adrotate_delete($banner_id, 'banner');
					$result_id = $banner_id;
				} else {
					adrotate_return('no_access');
				}
			}
			if($action == 'reset') {
				if(current_user_can($adrotate_config['ad_delete'])) {
					adrotate_reset($banner_id);
					$result_id = $banner_id;
				} else {
					adrotate_return('no_access');
				}
			}
			if($action == 'renew') {
				if(current_user_can($adrotate_config['ad_manage'])) {
					adrotate_renew($banner_id, $specific);
					$result_id = $banner_id;
				} else {
					adrotate_return('no_access');
				}
			}
		}
	}
	
	if($group_ids != '') {
		foreach($group_ids as $group_id) {
			if($action == 'group_delete') {
				if(current_user_can($adrotate_config['group_delete'])) {
					adrotate_delete($group_id, 'group');
					$result_id = $group_id;
				} else {
					adrotate_return('no_access');
				}
			}
			if($action == 'group_delete_banners') {
				if(current_user_can($adrotate_config['group_delete'])) {
					adrotate_delete($group_id, 'bannergroup');
					$result_id = $group_id;
				} else {
					adrotate_return('no_access');
				}
			}
		}
	 }

	if($block_ids != '') {
		foreach($block_ids as $block_id) {
			if($action == 'block_delete') {
				if(current_user_can($adrotate_config['block_delete'])) {
					adrotate_delete($block_id, 'block');
					$result_id = $block_id;
				} else {
					adrotate_return('no_access');
				}
			}
		}
	 }
	
	adrotate_return($action, array($result_id));
}

/*-------------------------------------------------------------
 Name:      adrotate_delete

 Purpose:   Remove banner or group from database
 Receive:   $id, $what
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_delete($id, $what) {
	global $wpdb;

	/* Changelog:
	// 14 Nov 2010 - Added and updated queries to work with linkmeta
	*/

	if($id > 0) {
		if($what == 'banner') {
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `id` = $id;");
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = $id;");
		} else if ($what == 'group') {
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = $id;");
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = $id;");
		} else if ($what == 'block') {
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = $id;");
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `block` = $id;");
		} else if ($what == 'bannergroup') {
			$linkmeta = $wpdb->get_results("SELECT `ad` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = '$id' AND `block` = '0';");
			foreach($linkmeta as $meta) {
				$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `id` = ".$meta->ad.";");
			}
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = $id;");
			$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = $id;");
		} else {
			adrotate_return('error');
			exit;
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_active

 Purpose:   Activate or Deactivate a banner
 Receive:   $id, $what
 Return:    -none-
 Since:		0.1
-------------------------------------------------------------*/
function adrotate_active($id, $what) {
	global $wpdb;

	if($id > 0) {
		if($what == 'deactivate') {
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `active` = 'no' WHERE `id` = '$id'");
		}
		if ($what == 'activate') {
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `active` = 'yes' WHERE `id` = '$id'");
		}
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_reset

 Purpose:   Reset statistics for a banner
 Receive:   $id
 Return:    -none-
 Since:		2.2
-------------------------------------------------------------*/
function adrotate_reset($id) {
	global $wpdb;

	if($id > 0) {
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `clicks` = '0', `shown` = '0' WHERE `id` = '$id'");
		$wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_tracker` WHERE `bannerid` = $id");
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_renew

 Purpose:   Renew the end date of a banner
 Receive:   $id, $howlong
 Return:    -none-
 Since:		2.2
-------------------------------------------------------------*/
function adrotate_renew($id, $howlong = 2592000) {
	global $wpdb;

	if($id > 0) {
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `endshow` = `endshow` + '$howlong' WHERE `id` = '$id'");
	}
}
?>