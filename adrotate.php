<?php
/*
Plugin Name: AdRotate
Plugin URI: http://meandmymac.net/plugins/adrotate/
Description: A simple way of showing random banners on your site with a user friendly panel to manage them.
Author: Arnan de Gans
Version: 0.7
Author URI: http://meandmymac.net
*/ 

#---------------------------------------------------
# Only proceed with the plugin if MySQL Tables are setup properly
#---------------------------------------------------
if(adrotate_mysql_install()) {

	add_shortcode('adrotate', 'adrotate_shortcode');
	add_action('admin_menu', 'adrotate_add_pages'); //Add page menu links
	
	if(isset($_POST['adrotate_submit'])) {
		add_action('init', 'adrotate_insert_input'); //Save banner
	}
	
	if(isset($_POST['add_group_submit'])) {
		add_action('init', 'adrotate_insert_group'); //Add a group
	}

	if(isset($_POST['delete_banners']) OR isset($_POST['delete_groups'])) {
		add_action('init', 'adrotate_request_delete'); //Delete banners/groups
	}
}	

/*-------------------------------------------------------------
 Name:      adrotate_banner

 Purpose:   Show a banner as requested in the WP Theme or post
 Receive:   $group_ids, $banner_id, $preview, $shortcode
 Return:    -none-
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
			$output = str_replace('%image%', '<img src="'.get_option('siteurl').'/wp-content/banners/'.$banner->image.'" />', $output);
		} else { 
			$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">The group is empty or the current (randomly picked) banner ID is not in this group!</span>';
		}
		
	} else {
	
		$output = '<span style="color: #F00; font-style: italic; font-weight: bold;">Error, no group_id specified! Check your syntax!</span>';
	
	}
	
	$output = stripslashes(html_entity_decode($output));
	
	if($shortcode != false) {
		return $output;
	} else {
		echo $output;
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_shortcode

 Purpose:   Show a banner as requested in a post using shortcodes
 Receive:   $atts, $content
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_shortcode($atts, $content = null) {

	if(!empty($atts['group'])) $group_ids = $atts['group'];
		else $group_ids = ''; 
	if(!empty($atts['banner'])) $banner_id = $atts['banner']; 
		else $banner_id = 0;
		
	return adrotate_banner($group_ids, $banner_id, false, true);
	
}

/*-------------------------------------------------------------
 Name:      adrotate_add_pages

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_add_pages() {
	add_submenu_page('edit.php', 'AdRotate', 'Banners', 10, 'adrotate', 'adrotate_manage_page');
	add_submenu_page('post-new.php', 'AdRotate', 'Banner', 10, 'adrotate', 'adrotate_add_page');
}

/*-------------------------------------------------------------
 Name:      adrotate_manage_page

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage_page() {
	global $wpdb, $userdata;

	$action = $_GET['action'];
	if(isset($_POST['order'])) { $order = $_POST['order']; } else { $order = 'thetime ASC'; }
	
	if ($action == 'deleted') { ?>
		<div id="message" class="updated fade"><p>Banner/group <strong>deleted</strong></p></div>
	<?php } else if ($action == 'updated') { ?>
		<div id="message" class="updated fade"><p>Banner <strong>updated</strong> | <a href="post-new.php?page=adrotate.php">add banner</a></p></div>
	<?php } else if ($action == 'group_new') { ?>
		<div id="message" class="updated fade"><p>Group <strong>created</strong> | <a href="post-new.php?page=adrotate.php">add banners now</a></p></div>
	<?php } else if ($action == 'group_field_error') { ?>
		<div id="message" class="updated fade"><p>Check the group name</p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } ?>

	<div class="wrap">
		<h2>Manage Banners</h2>

		<form name="banners" id="post" method="post" action="edit.php?page=adrotate.php">
			<div class="tablenav">

				<div class="alignleft">
					<input onclick="return confirm('You are about to delete multiple banners!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete banner" name="delete_banners" class="button-secondary delete" />
					<select name='order' id='cat' class='postform' >
				        <option value="thetime ASC" <?php if($order == "thetime ASC") { echo 'selected'; } ?>>by date (ascending)</option>
				        <option value="thetime DESC" <?php if($order == "thetime DESC") { echo 'selected'; } ?>>by date (descending)</option>
				        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>by ID</option>
				        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>by ID reversed</option>
				        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
					</select>
					<input type="submit" id="post-query-submit" value="Sort" class="button-secondary" />
				</div>
	
				<br class="clear" />
			</div>

			<br class="clear" />
		<table class="widefat">
  			<thead>
  				<tr>
					<th scope="col" class="check-column">&nbsp;</th>
					<th scope="col" width="15%">Date added</th>
					<th scope="col" width="5%">ID</th>
					<th scope="col" width="5%">Active</th>
					<th scope="col" width="20%">Group</th>
					<th scope="col">Title</th>
				</tr>
  			</thead>
  			<tbody>
		<?php $banners = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."adrotate ORDER BY ".$order);
		if ($banners) {
			foreach($banners as $banner) {
				$group = $wpdb->get_row("SELECT name FROM " . $wpdb->prefix . "adrotate_groups WHERE id = '".$banner->group."'");
				$class = ('alternate' != $class) ? 'alternate' : ''; ?>
			    <tr id='banner-<?php echo $banner->id; ?>' class=' <?php echo $class; ?>'>
					<th scope="row" class="check-column"><input type="checkbox" name="bannercheck[]" value="<?php echo $banner->id; ?>" /></th>
					<td><?php echo date("F d Y H:i", $banner->thetime);?></td>
					<td><?php echo $banner->id;?></td>
					<td><?php echo $banner->active;?></td>
					<td><?php echo $group->name;?></td>
					<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/post-new.php?page=adrotate.php&amp;edit_banner='.$banner->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($banner->title));?></a></strong></td>
				</tr>
 			<?php } ?>
 		<?php } else { ?>
			<tr id='no-id'><td scope="row" colspan="6"><em>No banners yet! </em></td></tr>
		<?php }	?>
			</tbody>
		</table>
		</form>
		
		<h2>Banner groups</h2>

		<form name="groups" id="post" method="post" action="edit.php?page=adrotate.php">
		<div class="tablenav">

			<div class="alignleft">
				<input onclick="return confirm('You are about to delete groups! Make sure there are no banners in those groups or they will not show on the website\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete group" name="delete_groups" class="button-secondary delete" />
			</div>

			<br class="clear" />
		</div>

		<br class="clear" />
		<table class="widefat">
  			<thead>
  				<tr>
					<th scope="col" class="check-column">&nbsp;</th>
					<th scope="col" width="5%">ID</th>
					<th scope="col">Name</th>
				</tr>
  			</thead>
  			<tbody>
		<?php $groups = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "adrotate_groups ORDER BY id");
		if ($groups) {
			foreach($groups as $group) {
				$class = ('alternate' != $class) ? 'alternate' : ''; ?>
			    <tr id='group-<?php echo $group->id; ?>' class=' <?php echo $class; ?>'>
					<th scope="row" class="check-column"><input type="checkbox" name="groupcheck[]" value="<?php echo $group->id; ?>" /></th>
					<td><?php echo $group->id;?></td>
					<td><?php echo $group->name;?></td>
				</tr>
 			<?php } ?>
		<?php }	?>
			    <tr id='group-new'>
					<th scope="row" class="check-column">&nbsp;</th>
					<td colspan="2"><input name="adrotate_group" type="text" size="40" value="" /> <input type="submit" id="post-query-submit" name="add_group_submit" value="Add" class="button-secondary" /></td>
				</tr>
 			</tbody>
		</table>
		</form>
	</div>
	<?php	 
}
	
/*-------------------------------------------------------------
 Name:      adrotate_add_page

 Purpose:   Create new/edit banners
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_add_page() {
	global $wpdb, $userdata;
	
	if($_GET['edit_banner']) {
		$banner_edit_id = $_GET['edit_banner'];
	}
	
	$action = $_GET['action']; 
	if ($action == 'created') { ?>
		<div id="message" class="updated fade"><p>Banner <strong>created</strong> | <a href="edit.php?page=adrotate.php">manage banners</a></p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } else if ($action == 'field_error') { ?>
		<div id="message" class="updated fade"><p>Not all fields met the requirements</p></div>
	<?php } ?>
	
	<div class="wrap">
		<?php if(!$banner_edit_id) { ?>
		<h2>Add banner</h2>
		<?php } else { ?>
		<h2>Edit banner</h2>
		<?php
			$SQL = "SELECT * FROM ".$wpdb->prefix."adrotate WHERE id = ".$banner_edit_id;
			$edit_banner = $wpdb->get_row($SQL);
			list($day, $month, $year, $hour, $minute) = split(" ", date("d m Y H i", $edit_banner->thetime));
		}
		
		$SQL2 = "SELECT * FROM ".$wpdb->prefix."adrotate_groups ORDER BY id";
		$groups = $wpdb->get_results($SQL2);
		if($groups) { ?>
		  	<form method="post" action="post-new.php?page=adrotate.php">
		  	   	<input type="hidden" name="adrotate_submit" value="true" />
		    	<input type="hidden" name="adrotate_username" value="<?php echo $userdata->display_name;?>" />
		    	<input type="hidden" name="adrotate_event_id" value="<?php echo $banner_edit_id;?>" />
		    	<table class="form-table">
					<tr valign="top">
						<td colspan="4" bgcolor="#DDD">Fill in the title so you can recognize the banner from management.
						<br />Paste the banner code in the code field this can be any html/javascript. Use the %image% tag to include a banner image from the dropdown menu.
						<br />All fields are required and should be used!</td>
					</tr>
			      	<tr>
				        <th scope="row" width="25%">Title:</th>
				        <td colspan="3"><input name="adrotate_title" type="text" size="52" value="<?php echo $edit_banner->title;echo $title;?>" /></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Code:</th>
				        <td colspan="2"><textarea name="adrotate_bannercode" cols="50" rows="10"><?php echo stripslashes($edit_banner->bannercode); ?></textarea></td>
				        <td valign="top" width="25%"><em>Options: %image%<br />HTML allowed, use with care!</em></td>
			      	</tr>
	      			<?php if($banner_edit_id) { ?>
					<tr valign="top">
						<td colspan="4" bgcolor="#DDD">Note: While this preview is an accurate one, it might look different than it does on the website.</td>
					</tr>
			      	<tr>
				        <th scope="row">Preview:</th>
				        <td colspan="3"><?php adrotate_banner($edit_banner->group, $banner_edit_id, true); ?></td>
			      	</tr>
			      	<?php } ?>
			      	<tr>
				        <th scope="row">Banner image:</th>
				        <td colspan="3"><select name="adrotate_image" style="min-width: 200px;">
       						<option value="none">No image or remote</option>
							<?php echo adrotate_folder_contents($edit_banner->image); ?>
						</select> <em>Accepted files are: jpg, jpeg, gif, png, swf and flv.</em></td>
			      	</tr>
			      	<tr>
					    <th scope="row">Group:</th>
				        <td colspan="3">
				        <select name='adrotate_group' id='cat' class='postform'>
						<?php foreach($groups as $group) {
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <option value="<?php echo $group->id; ?>" <?php if($group->id == $edit_banner->group) { echo 'selected'; } ?>><?php echo $group->name; ?></option>
				    	<?php } ?>
				    	</select>
						</td>
					</tr>
			      	<tr>
				        <th scope="row">Activate the banner:</th>
				        <td colspan="3"><select name="adrotate_active">
						<?php if($edit_banner->active == "no") { ?>
						<option value="no">No</option>
						<option value="yes">Yes</option>
						<?php } else { ?>
						<option value="yes">Yes</option>
						<option value="no">No</option>
						<?php } ?>
						</select> <em>IMPORTANT: Make sure that you do not leave a group empty or with all banners/ads disabled when it's in the theme!!</em></td>
			      	</tr>
				<?php if($banner_edit_id) { ?>
			      	<tr>
				        <th scope="row">Added:</th>
				        <td><?php echo date("F d Y H:i", $edit_banner->thetime); ?></td>
				        <th scope="row">Updated:</th>
				        <td><?php echo date("F d Y H:i", $edit_banner->updated); ?></td>
			      	</tr>
				<?php } ?>
		    	</table>
		    	
		    	<p class="submit">
					<input type="submit" name="Submit" value="Save banner &raquo;" />
		    	</p>
	
		  	</form>
		<?php } else { ?>
		    <table class="form-table">
				<tr valign="top">
					<td bgcolor="#DDD"><strong>You should create atleast one group before adding banners! <a href="edit.php?page=adrotate.php">Add a group now</a>.</strong></td>
				</tr>
			</table>
		<?php } ?>
	</div>
<?php } 

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
			wp_redirect('post-new.php?page=adrotate.php&action=created');
		break;
		
		case "group_new" :
			wp_redirect('edit.php?page=adrotate.php&action=group_new');
		break;
		
		case "update" :
			wp_redirect('edit.php?page=adrotate.php&action=updated');
		break;
		
		case "group_field_error" :
			wp_redirect('edit.php?page=adrotate.php&action=group_field_error');
		break;
		
		case "field_error" :
			wp_redirect('post-new.php?page=adrotate.php&action=field_error');
		break;
		
		case "no_access" :
			wp_redirect('post-new.php?page=adrotate.php&action=no_access');
		break;
		
		case "delete" :
			wp_redirect('edit.php?page=adrotate.php&action=deleted');
		break;
		
		case "error" :
			wp_redirect('edit.php?page=adrotate.php&action=error');
		break;
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_table_exists($table_name) {
	global $wpdb;
	
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	return false;
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_warning

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_warning() {
	echo '<div class="updated"><h3>WARNING! The MySQL table was not created! You cannot store banners. Seek support at meandmymac.net.</h3></div>';
}

/*-------------------------------------------------------------
 Name:      adrotate_mysql_install

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function adrotate_mysql_install() {
	global $wpdb;

	$table_name1 = $wpdb->prefix . "adrotate";
	$table_name2 = $wpdb->prefix . "adrotate_groups";
	if(!adrotate_mysql_table_exists($table_name1)) {
		$add1 = "CREATE TABLE ".$table_name1." (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `title` longtext NOT NULL,
			  `bannercode` longtext NOT NULL,
			  `thetime` int(15) NOT NULL default '0',
			  `updated` int(15) NOT NULL,
			  `author` varchar(60) NOT NULL default '',
			  `active` varchar(4) NOT NULL default 'yes',
			  `group` int(15) NOT NULL default '1',
			  `image` varchar(255) NOT NULL,
	  		PRIMARY KEY  (`id`)
			);";
	
		if($wpdb->query($add1) === true) {
			$table1 = 1;
		}
	} else {
		$table1 = 1;
	}
	
	if(!adrotate_mysql_table_exists($table_name2)) {
		$add2 = "CREATE TABLE ".$table_name2." (
			  `id` mediumint(8) unsigned NOT NULL auto_increment,
			  `name` varchar(255) NOT NULL,
	  		PRIMARY KEY  (`id`)
			);";
			
		if($wpdb->query($add2) === true ) {
			$table2 = 1;
		}
	} else {
		$table2 = 1;
	}
	
	if($table1 == '1' AND $table2 == '1') {
		return true; //tables exist
	} else {
		adrotate_mysql_warning();
		exit;
	}
}
?>