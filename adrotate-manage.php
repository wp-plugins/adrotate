<?php
/*-------------------------------------------------------------
 Name:      adrotate_insert_input

 Purpose:   Prepare input form on saving new or updated banners
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_insert_input() {
	global $wpdb, $adrotate_tracker;

	$banner_id 			= $_POST['adrotate_id'];
	$author 			= $_POST['adrotate_username'];
	$title	 			= htmlspecialchars(trim($_POST['adrotate_title'], "\t\n "), ENT_QUOTES);
	$bannercode			= htmlspecialchars(trim($_POST['adrotate_bannercode'], "\t\n "), ENT_QUOTES);
	$thetime 			= date('U');
	$active 			= $_POST['adrotate_active'];
	$group				= $_POST['adrotate_group'];
	$imageraw			= $_POST['adrotate_image'];
	$link				= htmlspecialchars(trim($_POST['adrotate_link'], "\t\n "), ENT_QUOTES);
	$tracker			= $_POST['adrotate_tracker'];
	$sday 				= htmlspecialchars(trim($_POST['adrotate_sday'], "\t\n "), ENT_QUOTES);
	$smonth 			= htmlspecialchars(trim($_POST['adrotate_smonth'], "\t\n "), ENT_QUOTES);
	$syear 				= htmlspecialchars(trim($_POST['adrotate_syear'], "\t\n "), ENT_QUOTES);
	$eday 				= htmlspecialchars(trim($_POST['adrotate_eday'], "\t\n "), ENT_QUOTES);
	$emonth 			= htmlspecialchars(trim($_POST['adrotate_emonth'], "\t\n "), ENT_QUOTES);
	$eyear 				= htmlspecialchars(trim($_POST['adrotate_eyear'], "\t\n "), ENT_QUOTES);
	$maxclicks			= htmlspecialchars(trim($_POST['adrotate_maxclicks'], "\t\n "), ENT_QUOTES);
	$maxshown			= htmlspecialchars(trim($_POST['adrotate_maxshown'], "\t\n "), ENT_QUOTES);


	if (strlen($title)!=0 AND strlen($bannercode)!=0) {
		if(strlen($smonth) == 0) 	$smonth 	= date('m');
		if(strlen($sday) == 0) 		$sday 		= date('d');
		if(strlen($syear) == 0) 	$syear 		= date('Y');
		if(strlen($emonth) == 0) 	$emonth 	= $smonth;
		if(strlen($eday) == 0) 		$eday 		= $sday;
		if(strlen($eyear) == 0) 	$eyear 		= $syear+1;

		if(strlen($maxclicks) < 1)	$maxclicks	= 0;

		$startdate 	= gmmktime($shour, $sminute, 0, $smonth, $sday, $syear);
		$enddate 	= gmmktime($ehour, $eminute, 0, $emonth, $eday, $eyear);

		list($type, $file)	= explode("|", $imageraw, 2);
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
			AND `guid` LIKE '%".$file."' LIMIT 1");
		}			

		if(isset($tracker) AND strlen($tracker) != 0) $tracker = 'Y';
			else $tracker = 'N';

		/* Check if you need to update or insert a new record */
		if(strlen($banner_id) != 0) {
			/* Update */
			$postquery = "UPDATE `".$wpdb->prefix."adrotate`	SET `title` = '$title', `bannercode` = '$bannercode', `updated` = '$thetime', `author` = '$author', `active` = '$active', `startshow` = '$startdate', `endshow` = '$enddate', `group` = '$group', `image` = '$image', `link` = '$link', `tracker` = '$tracker', `maxclicks` = '$maxclicks', `maxshown` = '$maxshown' WHERE `id` = '$banner_id'";
			$action = "update";
		} else {
			/* New */
			$postquery = "INSERT INTO `".$wpdb->prefix."adrotate` (`title`, `bannercode`, `thetime`, `updated`, `author`, `active`, `startshow`, `endshow`, `group`, `image`, `link`, `tracker`, `clicks`, `maxclicks`, `shown`, `maxshown` ,`magic`) VALUES ('$title', '$bannercode', '$thetime', '$thetime', '$author', '$active', '$startdate', '$enddate', '$group', '$image', '$link', '$tracker', 0, 0, 0, 0, 0)";
			$action = "new";
		}
		if($wpdb->query($postquery) !== FALSE) {
			adrotate_return($action, array($banner_id));
			exit;
		} else {
			die('[MySQL error] '.mysql_error());
		}
	} else {
		adrotate_return('field_error');
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_insert_group

 Purpose:   Add a new group
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_insert_magic() {
	global $wpdb, $adrotate_tracker;

	$step	 			= $_POST['adrotate_step'];
	$banner_id 			= $_POST['adrotate_magic_id'];

	$checkup = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$banner_id'");

	if($step == 1) {
		$title	 			= htmlspecialchars(trim($_POST['adrotate_title'], "\t\n "), ENT_QUOTES);
		$bannercode			= htmlspecialchars(trim($_POST['adrotate_bannercode'], "\t\n "), ENT_QUOTES);

		if(strlen($title) > 0 AND strlen($bannercode) > 0) {
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `title` = '$title', `bannercode` = '$bannercode' WHERE `id` = '$banner_id'");
			wp_redirect('admin.php?page=adrotate2&magic_id='.$banner_id.'&step=2');
		} else {
			adrotate_return('magic_field_error', array(1,$banner_id));
		}
	} else if($step == 2) {
		if(strlen($checkup->title) > 0 AND strlen($checkup->bannercode) > 0) {
			$group				= $_POST['adrotate_group'];
			$newgroup			= htmlspecialchars(trim($_POST['adrotate_newgroup'], "\t\n "), ENT_QUOTES);
			$sday 				= htmlspecialchars(trim($_POST['adrotate_sday'], "\t\n "), ENT_QUOTES);
			$smonth 			= $_POST['adrotate_smonth'];
			$syear 				= htmlspecialchars(trim($_POST['adrotate_syear'], "\t\n "), ENT_QUOTES);
			$eday 				= htmlspecialchars(trim($_POST['adrotate_eday'], "\t\n "), ENT_QUOTES);
			$emonth 			= $_POST['adrotate_emonth'];
			$eyear 				= htmlspecialchars(trim($_POST['adrotate_eyear'], "\t\n "), ENT_QUOTES);

			if(strlen($smonth) == 0) 	$smonth 	= date('m');
			if(strlen($sday) == 0) 		$sday 		= date('d');
			if(strlen($syear) == 0) 	$syear 		= date('Y');
			if(strlen($emonth) == 0) 	$emonth 	= $smonth;
			if(strlen($eday) == 0) 		$eday 		= $sday;
			if(strlen($eyear) == 0) 	$eyear 		= $syear+1;

			if(strlen($group) > 0 OR strlen($newgroup) > 0) {
				$startdate 	= gmmktime($shour, $sminute, 0, $smonth, $sday, $syear);
				$enddate 	= gmmktime($ehour, $eminute, 0, $emonth, $eday, $eyear);

				if(strlen($newgroup) > 0) {
					$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_groups` (`name`) VALUES ('$newgroup')");
					$group = $wpdb->get_var("SELECT `id` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` = '$newgroup' LIMIT 1");
				}
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `startshow` = '$startdate', `endshow` = '$enddate', `group` = '$group' WHERE `id` = '$banner_id'");
				wp_redirect('admin.php?page=adrotate2&magic_id='.$banner_id.'&step=3');
			} else {
				adrotate_return('magic_field_error', array(2,$banner_id));
			}
		} else {
			adrotate_return('magic_field_error', array(2,$banner_id));
		}
	} else if($step == 3) {
		if(strlen($checkup->title) > 0 AND strlen($checkup->bannercode) > 0) {
			if(strlen($checkup->group) > 0 AND strlen($checkup->startshow) > 0 AND strlen($checkup->endshow) > 0) {
				$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `active` = 'yes', `magic` = '1' WHERE `id` = '$banner_id'");
				adrotate_return('magic_new');
			} else {
				adrotate_return('magic_field_error', array(2,$banner_id));
			}
		} else {
			adrotate_return('magic_field_error', array(2,$banner_id));
		}
	} else {
		adrotate_return('magic_error', array(1));
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_insert_group

 Purpose:   Add/edit a group
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_insert_group() {
	global $wpdb;

	$id 		= $_POST['adrotate_id'];
	$name 		= $_POST['adrotate_group'];
	$fallback 	= $_POST['adrotate_fallback'];

	if (strlen($name) != 0) {
		if($id > 0) {
			$postquery = "UPDATE `".$wpdb->prefix."adrotate_groups` SET `name` = '$name', `fallback` = '$fallback' WHERE `id` = '$id'";
			$action = "group_edit";
		} else {
			$postquery = "INSERT INTO `".$wpdb->prefix."adrotate_groups` (`name`, `fallback`) VALUES ('$name', '$fallback')";
			$action = "group_new";
		}
		if($wpdb->query($postquery) !== FALSE) {
			adrotate_return($action);
			exit;
		} else {
			die(mysql_error());
		}
	} else {
		adrotate_return('group_field_error', array($id));
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_request_action

 Purpose:   Prepare action for banner or group from database
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_request_action() {
	global $wpdb, $userdata, $adrotate_tracker;

	if(isset($_POST['bannercheck'])) $banner_ids = $_POST['bannercheck'];
	if(isset($_POST['adrotate_id'])) $banner_ids = array($_POST['adrotate_id']);

	if(isset($_POST['groupcheck'])) $group_ids = $_POST['groupcheck'];
	$actions = $_POST['adrotate_action'];
	list($action, $specific) = explode("-", $actions);
	
	if(current_user_can('manage_options')) {
		if($banner_ids != '') {
			foreach($banner_ids as $banner_id) {
				if($action == 'deactivate') {
					adrotate_active($banner_id, 'deactivate');
				}
				if($action == 'activate') {
					adrotate_active($banner_id, 'activate');
				}
				if($action == 'delete') {
					adrotate_delete($banner_id, 'banner');
				}
				if($action == 'reset' OR $action == 'resetmultiple') {
					adrotate_reset($banner_id);
				}
				if($action == 'renew') {
					adrotate_renew($banner_id);
				}
				if($action == 'renewmultiple') {
					adrotate_renew($banner_id, $specific);
				}
				if($action == 'move') {
					adrotate_move($banner_id, $specific);
				}
			}
		}
		if($group_ids != '') {
			foreach($group_ids as $group_id) {
				if($action == 'group_delete') {
					adrotate_delete($group_id, 'group');
				}
				if($action == 'group_delete_banners') {
					adrotate_delete($group_id, 'bannergroup');
				}
			}
		}
		adrotate_return($action, array($banner_id));
	} else {
		adrotate_return('no_access');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_delete

 Purpose:   Remove banner or group from database
 Receive:   $id, $what
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_delete($id, $what) {
	global $wpdb;

	if($id > 0) {
		if($what == 'banner') {
			if($wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `id` = $id") == FALSE) {
				die(mysql_error());
			}
		} else if ($what == 'group') {
			if($wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = $id") == FALSE) {
				die(mysql_error());
			}
		} else if ($what == 'bannergroup') {
			if($wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = $id") == FALSE) {
				die(mysql_error());
			}
			if($wpdb->query("DELETE FROM `".$wpdb->prefix."adrotate` WHERE `group` = $id") == FALSE) {
				die(mysql_error());
			}
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
-------------------------------------------------------------*/
function adrotate_renew($id, $howlong = 31536000) {
	global $wpdb;

	if($id > 0) {
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `endshow` = `endshow` + '$howlong' WHERE `id` = '$id'");
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_move

 Purpose:   Renew the end date of a banner
 Receive:   $id, $group
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_move($id, $group) {
	global $wpdb;

	if($id > 0) {
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `group` = '$group' WHERE `id` = '$id'");
	}
}
?>