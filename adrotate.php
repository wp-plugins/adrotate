<?php
/*
Plugin Name: AdRotate
Plugin URI: http://meandmymac.net/plugins/adrotate/
Description: A simple way of showing random banners on your site with a user friendly panel to manage them.
Author: Arnan de Gans
Version: 1.0
Author URI: http://meandmymac.net/
*/

#---------------------------------------------------
# Load other plugin files and configuration
#---------------------------------------------------
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-setup.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-manage.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-functions.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-widget.php');
register_activation_hook(__FILE__, 'adrotate_activate');

add_shortcode('adrotate', 'adrotate_shortcode');
add_action('admin_menu', 'adrotate_dashboard',1);
add_action('widgets_init', 'adrotate_widget_init');

if(isset($_POST['adrotate_submit'])) {
	add_action('init', 'adrotate_insert_input'); //Save banner
}

if(isset($_POST['add_group_submit'])) {
	add_action('init', 'adrotate_insert_group'); //Add a group
}

if(isset($_POST['delete_banners']) OR isset($_POST['delete_groups'])) {
	add_action('init', 'adrotate_request_delete'); //Delete banners/groups
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard() {
	add_submenu_page('edit.php', 'AdRotate > Add/Edit', 'Write Banner', 'manage_options', 'adrotate', 'adrotate_edit');
	add_submenu_page('plugins.php', 'AdRotate > Manage', 'Manage Banners', 'manage_options', 'adrotate2', 'adrotate_manage');
}

/*-------------------------------------------------------------
 Name:      adrotate_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage() {
	global $wpdb, $userdata;

	$action = $_GET['action'];
	if(isset($_POST['order'])) { $order = $_POST['order']; } else { $order = 'thetime ASC'; }
	?>

	<div class="wrap">
		<h2>Manage Banners (<a href="edit.php?page=adrotate">add new</a>)</h2>

		<?php if ($action == 'deleted') { ?>
			<div id="message" class="updated fade"><p>Banner/group <strong>deleted</strong></p></div>
		<?php } else if ($action == 'updated') { ?>
			<div id="message" class="updated fade"><p>Banner <strong>updated</strong> | <a href="edit.php?page=adrotate">add banner</a></p></div>
		<?php } else if ($action == 'group_new') { ?>
			<div id="message" class="updated fade"><p>Group <strong>created</strong> | <a href="edit.php?page=adrotate">add banners now</a></p></div>
		<?php } else if ($action == 'group_field_error') { ?>
			<div id="message" class="updated fade"><p>Check the group name</p></div>
		<?php } else if ($action == 'no_access') { ?>
			<div id="message" class="updated fade"><p>Action prohibited</p></div>
		<?php } ?>

		<form name="banners" id="post" method="post" action="plugins.php?page=adrotate2">
			<div class="tablenav">

				<div class="alignleft actions">
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
					<th scope="col" width="5%">Hits</th>
 				</tr>
  			</thead>
  			<tbody>
		<?php
		if(adrotate_mysql_table_exists($wpdb->prefix.'adrotate')) {
			$banners = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."adrotate ORDER BY ".$order);
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
						<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/edit.php?page=adrotate&amp;edit_banner='.$banner->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($banner->title));?></a></strong></td>
						<td><?php echo $banner->hits;?></td>
					</tr>
	 			<?php } ?>
	 		<?php } else { ?>
				<tr id='no-id'><td scope="row" colspan="7"><em>No banners yet! </em></td></tr>
			<?php }
		} else { ?>
			<tr id='no-id'><td scope="row" colspan="7"><span style="font-weight: bold; color: #f00;">There was an error locating the main database table for AdRotate. Please deactivate and re-activate AdRotate from the plugin page!!<br />If this does not solve the issue please seek support at <a href="http://forum.at.meandmymac.net">http://forum.at.meandmymac.net</a></span></td></tr>
		<?php }	?>
			</tbody>
		</table>
		</form>

		<h2>Banner groups</h2>

		<form name="groups" id="post" method="post" action="plugins.php?page=adrotate2">
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
		<?php
		if(adrotate_mysql_table_exists($wpdb->prefix.'adrotate_groups')) {
			$groups = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "adrotate_groups ORDER BY id");
			if ($groups) {
				foreach($groups as $group) {
					$class = ('alternate' != $class) ? 'alternate' : ''; ?>
				    <tr id='group-<?php echo $group->id; ?>' class=' <?php echo $class; ?>'>
						<th scope="row" class="check-column"><input type="checkbox" name="groupcheck[]" value="<?php echo $group->id; ?>" /></th>
						<td><?php echo $group->id;?></td>
						<td><?php echo $group->name;?></td>
					</tr>
	 			<?php } ?>
			<?php }
		} else { ?>
			<tr id='no-id'><td scope="row" colspan="3"><span style="font-weight: bold; color: #f00;">There was an error locating the database table for the AdRotate groups. Please deactivate and re-activate AdRotate from the plugin page!!<br />If this does not solve the issue please seek support at <a href="http://forum.at.meandmymac.net">http://forum.at.meandmymac.net</a></span></td></tr>
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
 Name:      adrotate_edit

 Purpose:   Create new/edit banners
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_edit() {
	global $wpdb, $userdata;

	$action = $_GET['action'];
	if($_GET['edit_banner']) {
		$banner_edit_id = $_GET['edit_banner'];
	}
	?>

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

		if ($action == 'created') { ?>
			<div id="message" class="updated fade"><p>Banner <strong>created</strong> | <a href="plugins.php?page=adrotate2">manage banners</a></p></div>
		<?php } else if ($action == 'no_access') { ?>
			<div id="message" class="updated fade"><p>Action prohibited</p></div>
		<?php } else if ($action == 'field_error') { ?>
			<div id="message" class="updated fade"><p>Not all fields met the requirements</p></div>
		<?php }

		$SQL2 = "SELECT * FROM ".$wpdb->prefix."adrotate_groups ORDER BY id";
		$groups = $wpdb->get_results($SQL2);
		if($groups) { ?>
		  	<form method="post" action="edit.php?page=adrotate">
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
					<input type="submit" name="Submit" class="button-primary" value="Save banner &raquo;" />
		    	</p>

		  	</form>
		<?php } else { ?>
		    <table class="form-table">
				<tr valign="top">
					<td bgcolor="#DDD"><strong>You should create atleast one group before adding banners! <a href="plugins.php?page=adrotate2">Add a group now</a>.</strong></td>
				</tr>
			</table>
		<?php } ?>
	</div>
<?php
}
?>