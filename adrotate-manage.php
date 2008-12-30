<?php
/*-------------------------------------------------------------
 Name:      adrotate_folder_contents

 Purpose:   List folder contents
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_folder_contents($current) {
	global $wpdb;
	
	if ($handle = opendir(ABSPATH.'/wp-content/banners/')) {
		$output = '';
	    while (false !== ($file = readdir($handle))) {
			$fileinfo = pathinfo($file);
	
	        if ($file != "." && $file != ".." 
	        AND (strtolower($fileinfo['extension']) == "jpg" OR strtolower($fileinfo['extension']) == "gif" 
	        OR strtolower($fileinfo['extension']) == "png" OR strtolower($fileinfo['extension']) == "jpeg"
	        OR strtolower($fileinfo['extension']) == "swf" OR strtolower($fileinfo['extension']) == "flv")) {
	            $output .= "<option ";
	            if($current == $file) { $output .= "selected"; }
	            $output .= ">".$file."</option>";
	        }
	    }
	    closedir($handle);
	}
	
	return $output;
}


/*-------------------------------------------------------------
 Name:      adrotate_insert_input

 Purpose:   Prepare input form on saving new or updated banners
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function adrotate_insert_input() {
	global $wpdb, $userdata;
	
	$banner_id 			= $_POST['adrotate_event_id'];
	$author 			= $_POST['adrotate_username'];
	$title	 			= htmlspecialchars(trim($_POST['adrotate_title'], "\t\n "), ENT_QUOTES);
	$bannercode			= htmlspecialchars(trim($_POST['adrotate_bannercode'], "\t\n "), ENT_QUOTES);
	$thetime 			= date('U');
	$active 			= $_POST['adrotate_active'];
	$group				= $_POST['adrotate_group'];
	$image				= $_POST['adrotate_image'];
	
	if (strlen($title)!=0 AND strlen($bannercode)!=0) {
		/* Check if you need to update or insert a new record */
		if(strlen($banner_id) != 0) {
			/* Update */
			$postquery = "UPDATE 
			".$wpdb->prefix."adrotate 
			SET
			`title` = '$title', `bannercode` = '$bannercode', `updated` = '$thetime', `author` = '$author', 
			`active` = '$active', `group` = '$group', `image` = '$image'
			WHERE 
			`id` = '$banner_id'";
			$action = "update";
		} else {
			/* New */
			$postquery = "INSERT INTO ".$wpdb->prefix."adrotate
			(`title`, `bannercode`, `thetime`, `updated`, `author`, `active`, `group`, `image`) 
			VALUES 
			('$title', '$bannercode', '$thetime', '$thetime', '$author', '$active', '$group', '$image')";		
			$action = "new";
		}
		if($wpdb->query($postquery) !== FALSE) {
			adrotate_return($action);
			exit;
		} else {
			die(mysql_error());
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
	global $wpdb, $userdata;
	
	$name = $_POST['adrotate_group'];
	
	if (strlen($name) != 0) {
		/* New event */
		$postquery = "INSERT INTO ".$wpdb->prefix."adrotate_groups
		(name)
		VALUES ('$name')";		
		$action = "group_new";
		if($wpdb->query($postquery) !== FALSE) {
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
 Name:      adrotate_request_delete

 Purpose:   Prepare removal of banner or group from database
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_request_delete() {
	global $userdata, $wpdb;

	$banner_ids = $_POST['bannercheck'];
	$group_ids = $_POST['groupcheck'];

	if($banner_ids != '') {
		foreach($banner_ids as $banner_id) {
			adrotate_delete($banner_id, 'banner');
		}
	}
	if($group_ids != '') {
		foreach($group_ids as $group_id) {
			adrotate_delete($group_id, 'group');
		}
	}
	adrotate_return('delete');
	exit;
}

/*-------------------------------------------------------------
 Name:      adrotate_delete

 Purpose:   Remove banner or group from database
 Receive:   $id, $what
 Return:    boolean
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
 Name:      adrotate_return

 Purpose:   Redirect to various pages
 Receive:   $action
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_return($action) {
	switch($action) {
		case "new" :
			wp_redirect('plugins.php?page=adrotate2&action=created');
		break;
		
		case "group_new" :
			wp_redirect('plugins.php?page=adrotate2&action=group_new');
		break;
		
		case "update" :
			wp_redirect('plugins.php?page=adrotate2&action=updated');
		break;
		
		case "group_field_error" :
			wp_redirect('edit.php?page=adrotate&action=group_field_error');
		break;
		
		case "field_error" :
			wp_redirect('edit.php?page=adrotate&action=field_error');
		break;
		
		case "no_access" :
			wp_redirect('plugins.php?page=adrotate2&action=no_access');
		break;
		
		case "delete" :
			wp_redirect('plugins.php?page=adrotate2&action=deleted');
		break;
		
		case "error" :
			wp_redirect('plugins.php?page=adrotate2&action=error');
		break;
	}
}
?>