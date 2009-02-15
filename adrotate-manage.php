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
	$image				= $_POST['adrotate_image'];
	$link				= htmlspecialchars(trim($_POST['adrotate_link'], "\t\n "), ENT_QUOTES);
	$tracker			= $_POST['adrotate_tracker'];
	$sday 				= htmlspecialchars(trim($_POST['adrotate_sday'], "\t\n "), ENT_QUOTES);
	$smonth 			= htmlspecialchars(trim($_POST['adrotate_smonth'], "\t\n "), ENT_QUOTES);
	$syear 				= htmlspecialchars(trim($_POST['adrotate_syear'], "\t\n "), ENT_QUOTES);
	$eday 				= htmlspecialchars(trim($_POST['adrotate_eday'], "\t\n "), ENT_QUOTES);
	$emonth 			= htmlspecialchars(trim($_POST['adrotate_emonth'], "\t\n "), ENT_QUOTES);
	$eyear 				= htmlspecialchars(trim($_POST['adrotate_eyear'], "\t\n "), ENT_QUOTES);

	
	if (strlen($title)!=0 AND strlen($bannercode)!=0) {
		if(strlen($smonth) == 0) 	$smonth 	= date('m');
		if(strlen($sday) == 0) 		$sday 		= date('d');
		if(strlen($syear) == 0) 	$syear 		= date('Y');
		if(strlen($emonth) == 0) 	$emonth 	= $smonth;
		if(strlen($eday) == 0) 		$eday 		= $sday;
		if(strlen($eyear) == 0) 	$eyear 		= $syear+1;
		
		$startdate 	= gmmktime($shour, $sminute, 0, $smonth, $sday, $syear);
		$enddate 	= gmmktime($ehour, $eminute, 0, $emonth, $eday, $eyear);

		if(isset($tracker) AND strlen($tracker) != 0) $tracker = 'Y';			
			else $tracker = 'N';

		/* Check if you need to update or insert a new record */
		if(strlen($banner_id) != 0) {
			/* Update */
			$postquery = "UPDATE 
			".$wpdb->prefix."adrotate 
			SET
			`title` = '$title', `bannercode` = '$bannercode', `updated` = '$thetime', `author` = '$author', 
			`active` = '$active', `startshow` = '$startdate', `endshow` = '$enddate', `group` = '$group', 
			`image` = '$image', `link` = '$link', `tracker` = '$tracker'
			WHERE 
			`id` = '$banner_id'";
			$action = "update";
		} else {
			/* New */
			$postquery = "INSERT INTO ".$wpdb->prefix."adrotate
			(`title`, `bannercode`, `thetime`, `updated`, `author`, `active`, `startshow`, `endshow`, `group`, `image`, `link`, `tracker`, `clicks`, `shown`) 
			VALUES 
			('$title', '$bannercode', '$thetime', '$thetime', '$author', '$active', '$startdate', '$enddate', '$group', '$image', '$link', '$tracker', 0, 0)";
			$action = "new";
		}
		if($wpdb->query($postquery) !== FALSE) {
			if($adrotate_tracker['register'] == 'Y') { 
				if($action == 'new'){ 
					adrotate_send_data('New Banner'); 
				} else {
					adrotate_send_data('Update Banner');
				}
			}
			adrotate_return($action, $banner_id);
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
function adrotate_insert_group() {
	global $wpdb, $adrotate_tracker;
	
	$name = $_POST['adrotate_group'];
	
	if (strlen($name) != 0) {
		$postquery = "INSERT INTO ".$wpdb->prefix."adrotate_groups
		(name)
		VALUES ('$name')";		
		$action = "group_new";
		if($wpdb->query($postquery) !== FALSE) {
			if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('New Group'); }
			adrotate_return($action);
			exit;
		} else {
			die(mysql_error());
		}
	} else {
		adrotate_return('group_field_error');
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_request_action

 Purpose:   Prepare action of banner or group from database
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_request_action() {
	global $wpdb, $adrotate_tracker;

	if(isset($_POST['bannercheck'])) $banner_ids = $_POST['bannercheck'];
	if(isset($_POST['adrotate_id'])) $banner_ids = array($_POST['adrotate_id']);
	$group_ids = $_POST['groupcheck'];
	$action = strtolower($_POST['adrotate_action']);

	if($banner_ids != '') {
		foreach($banner_ids as $banner_id) {
			if($action == 'deactivate') {
				adrotate_active($banner_id, 'deactivate');
				if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('Deactivate Banner'); }
			}
			if($action == 'activate') {
				adrotate_active($banner_id, 'activate');
				if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('Activate Banner'); }
			}
			if($action == 'delete') {
				adrotate_delete($banner_id, 'banner');
				if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('Delete Banner'); }
			}
			if($action == 'reset' OR $action == 'resetmultiple') {
				adrotate_reset($banner_id);
				if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('Reset Banner'); }
			}
		}
	}
	if($group_ids != '') {
		foreach($group_ids as $group_id) {
			adrotate_delete($group_id, 'group');
			if($adrotate_tracker['register'] == 'Y') { adrotate_send_data('Delete Group'); }
		}
	}
	adrotate_return($action, $banner_id);
	exit;
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
			$SQL = "SELECT
			".$wpdb->prefix."adrotate.author,
			".$wpdb->prefix."users.display_name as display_name
			FROM
			".$wpdb->prefix."adrotate,
			".$wpdb->prefix."users
			WHERE
			".$wpdb->prefix."adrotate.id = '$id'
			AND
			".$wpdb->prefix."users.display_name = ".$wpdb->prefix."adrotate.author";
	
			$banner = $wpdb->get_row($SQL);
	
			if ($banner->display_name == $banner->author ) {
				$SQL = "DELETE FROM ".$wpdb->prefix."adrotate WHERE id = $id";
				if($wpdb->query($SQL) == FALSE) {
					die(mysql_error());
				}
			} else {
				adrotate_return('no_access');
				exit;
			}
		} else if ($what == 'group') {
			$SQL = "DELETE FROM ".$wpdb->prefix."adrotate_groups WHERE id = $id";
			if($wpdb->query($SQL) == FALSE) {
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
?>