<?php
/*
Plugin Name: AdRotate
Plugin URI: http://meandmymac.net/plugins/adrotate/
Description: A simple way of showing random banners on your site with a user friendly panel to manage them.
Author: Arnan de Gans
Version: 2.0
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
register_deactivation_hook(__FILE__, 'adrotate_deactivate');

adrotate_check_config();
adrotate_clean_trackerdata();

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

if(isset($_POST['adrotate_submit_options'])) {
	add_action('init', 'adrotate_options_submit'); //Update Options
}

$adrotate_tracker = get_option('adrotate_tracker');

/*-------------------------------------------------------------
 Name:      adrotate_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard() {
	add_submenu_page('edit.php', 'AdRotate > Add/Edit', 'Write Banner', 'manage_options', 'adrotate', 'adrotate_edit');
	add_submenu_page('plugins.php', 'AdRotate > Manage', 'Manage Banners', 'manage_options', 'adrotate2', 'adrotate_manage');
	add_submenu_page('options-general.php', 'AdRotate > Settings', 'AdRotate', 'manage_options', 'adrotate3', 'adrotate_options');
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
			<div id="message" class="updated fade"><p>Banner <strong>updated</strong></p></div>
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
					<input onclick="return confirm('You are about to delete one or more banners!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete banner(s)" name="delete_banners" class="button-secondary delete" />
					Sort by <select name='order' id='cat' class='postform' >
				        <option value="startshow ASC" <?php if($order == "startshow ASC") { echo 'selected'; } ?>>start date (ascending)</option>
				        <option value="startshow DESC" <?php if($order == "startshow DESC") { echo 'selected'; } ?>>start date (descending)</option>
				        <option value="endshow ASC" <?php if($order == "endshow ASC") { echo 'selected'; } ?>>end date (ascending)</option>
				        <option value="endshow DESC" <?php if($order == "endshow DESC") { echo 'selected'; } ?>>end date (descending)</option>
				        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>ID</option>
				        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>ID reversed</option>
				        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>title (A-Z)</option>
				        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>title (Z-A)</option>
				        <option value="clicks ASC" <?php if($order == "clicks ASC") { echo 'selected'; } ?>>clicks (A-Z)</option>
				        <option value="clicks DESC" <?php if($order == "clicks DESC") { echo 'selected'; } ?>>clicks (Z-A)</option>
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
					<th scope="col" width="2%"><center>ID</center></th>
					<th scope="col" width="15%">Show from</th>
					<th scope="col" width="15%">Show until</th>
					<th scope="col" width="5%"><center>Active</center></th>
					<th scope="col" width="15%">Group</th>
					<th scope="col">Title</th>
					<th scope="col" width="5%"><center>Shown</center></th>
					<th scope="col" width="5%"><center>Clicks</center></th>
					<th scope="col" width="8%"><center>CTR</center></th>
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
						<td><center><?php echo $banner->id;?></center></td>
						<td><?php echo date("F d Y", $banner->startshow);?></td>
						<td><?php echo date("F d Y", $banner->endshow);?></td>
						<td><center><?php if($banner->active == "yes") { echo '<span style="color:#0F0; font-weight:bold;">'.$banner->active.'</span>'; } else { echo '<span style="color:#F00; font-weight:bold;">'.$banner->active.'</span>'; }?></center></td>
						<td><?php echo $group->name;?></td>
						<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/edit.php?page=adrotate&amp;edit_banner='.$banner->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($banner->title));?></a></strong></td>
						<td><center><?php echo $banner->shown;?></center></td>
						<?php if($banner->tracker == "Y") { ?>
						<td><center><?php echo $banner->clicks;?></center></td>
						<td><center><?php echo round((100/$banner->shown)*$banner->clicks,2);?> %</center></td>
						<?php } else { ?>
						<td colspan="2"><center>N/A</center></td>
						<?php } ?>
					</tr>
	 			<?php } ?>
	 		<?php } else { ?>
				<tr id='no-id'><td scope="row" colspan="7"><em>No banners yet!</em></td></tr>
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
				<input onclick="return confirm('You are about to delete one or more groups!\n\nMake sure there are no banners in those groups or they will not show on the website.\n\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete group(s)" name="delete_groups" class="button-secondary delete" />
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
			$SQL = "SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = ".$banner_edit_id;
			$edit_banner = $wpdb->get_row($SQL);
			list($sday, $smonth, $syear) = split(" ", gmdate("d m Y", $edit_banner->startshow));
			list($eday, $emonth, $eyear) = split(" ", gmdate("d m Y", $edit_banner->endshow));
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
		    	<input type="hidden" name="adrotate_edit_id" value="<?php echo $banner_edit_id;?>" />

		    	<table class="widefat" style="margin-top: .5em">

					<thead>
					<tr valign="top">
						<th colspan="4" bgcolor="#DDD">Fill in the title so you can recognize the banner from management.
						<br />Paste the banner code in the code field this can be any HTML/JavaScript.</th>
					</tr>
					</thead>

					<tbody>
			      	<tr>
				        <th scope="row" width="25%">Title:</th>
				        <td colspan="3"><input tabindex="1" name="adrotate_title" type="text" size="67" class="search-input" value="<?php echo $edit_banner->title;?>" /></td>
			      	</tr>
			      	<tr>
				        <th scope="row" width="25%">Code:</th>
				        <td colspan="3"><textarea tabindex="2" name="adrotate_bannercode" cols="70" rows="10"><?php echo stripslashes($edit_banner->bannercode); ?></textarea>
				        <br /><em>Options: %image%, %link%. HTML allowed, use with care!</em></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Display From:</th>
				        <td>
				        	<input tabindex="3" id="title" name="adrotate_sday" class="search-input" type="text" size="4" maxlength="2" value="<?php echo $sday;?>" tabindex="5" /> /
							<select tabindex="4" name="adrotate_smonth" tabindex="6">
								<option value="01" <?php if($smonth == "01") { echo 'selected'; } ?>>January</option>
								<option value="02" <?php if($smonth == "02") { echo 'selected'; } ?>>February</option>
								<option value="03" <?php if($smonth == "03") { echo 'selected'; } ?>>March</option>
								<option value="04" <?php if($smonth == "04") { echo 'selected'; } ?>>April</option>
								<option value="05" <?php if($smonth == "05") { echo 'selected'; } ?>>May</option>
								<option value="06" <?php if($smonth == "06") { echo 'selected'; } ?>>June</option>
								<option value="07" <?php if($smonth == "07") { echo 'selected'; } ?>>July</option>
								<option value="08" <?php if($smonth == "08") { echo 'selected'; } ?>>August</option>
								<option value="09" <?php if($smonth == "09") { echo 'selected'; } ?>>September</option>
								<option value="10" <?php if($smonth == "10") { echo 'selected'; } ?>>October</option>
								<option value="11" <?php if($smonth == "11") { echo 'selected'; } ?>>November</option>
								<option value="12" <?php if($smonth == "12") { echo 'selected'; } ?>>December</option>
							</select> /
							<input tabindex="5" name="adrotate_syear" class="search-input" type="text" size="4" maxlength="4" value="<?php echo $syear;?>" tabindex="6" />
				        </td>
				        <th scope="row">Until:</th>
				        <td>
				        	<input tabindex="6" id="title" name="adrotate_eday" class="search-input" type="text" size="4" maxlength="2" value="<?php echo $eday;?>" tabindex="5" /> /
							<select tabindex="7" name="adrotate_emonth" tabindex="6">
								<option value="01" <?php if($emonth == "01") { echo 'selected'; } ?>>January</option>
								<option value="02" <?php if($emonth == "02") { echo 'selected'; } ?>>February</option>
								<option value="03" <?php if($emonth == "03") { echo 'selected'; } ?>>March</option>
								<option value="04" <?php if($emonth == "04") { echo 'selected'; } ?>>April</option>
								<option value="05" <?php if($emonth == "05") { echo 'selected'; } ?>>May</option>
								<option value="06" <?php if($emonth == "06") { echo 'selected'; } ?>>June</option>
								<option value="07" <?php if($emonth == "07") { echo 'selected'; } ?>>July</option>
								<option value="08" <?php if($emonth == "08") { echo 'selected'; } ?>>August</option>
								<option value="09" <?php if($emonth == "09") { echo 'selected'; } ?>>September</option>
								<option value="10" <?php if($emonth == "10") { echo 'selected'; } ?>>October</option>
								<option value="11" <?php if($emonth == "11") { echo 'selected'; } ?>>November</option>
								<option value="12" <?php if($emonth == "12") { echo 'selected'; } ?>>December</option>
							</select> /
							<input tabindex="8" name="adrotate_eyear" class="search-input" type="text" size="4" maxlength="4" value="<?php echo $eyear;?>" tabindex="6" />
						</td>
			      	</tr>
			      	<tr>
					    <th scope="row">Group:</th>
				        <td colspan="3">
				        <select tabindex="9" name='adrotate_group' id='cat' class='postform'>
						<?php foreach($groups as $group) {
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <option value="<?php echo $group->id; ?>" <?php if($group->id == $edit_banner->group) { echo 'selected'; } ?>><?php echo $group->name; ?></option>
				    	<?php } ?>
				    	</select>
						</td>
					</tr>
					</tbody>
					
					<thead>
					<tr valign="top">
						<th colspan="4" bgcolor="#DDD">Advanced (optional)</th>
					</tr>
					</thead>

					<tbody>
			      	<tr>
				        <th scope="row">Banner image:</th>
				        <td colspan="3"><select tabindex="10" name="adrotate_image" style="min-width: 200px;">
       						<option value="none">No image or remote</option>
							<?php echo adrotate_folder_contents($edit_banner->image); ?>
						</select>
						<br /><em>Use %image% in the code. Accepted files are: jpg, jpeg, gif, png, swf and flv.</em></td>
			      	</tr>
			      	<tr>
				        <th scope="row" width="25%">Clicktracking:</th>
				        <td colspan="3">Enable? <input tabindex="11" type="checkbox" name="adrotate_tracker" <?php if($edit_banner->tracker == 'Y') { ?>checked="checked" <?php } ?> /> url: <input tabindex="12" name="adrotate_link" type="text" size="52" class="search-input" value="<?php echo $edit_banner->link;?>" />
				        <br /><em>Use %link% in the code. Do not check the box if you cannot specify an url (eg, you do not use &lt;a href="http://somelink"&gt;).</em></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Activate the banner:</th>
				        <td colspan="3"><select tabindex="13" name="adrotate_active">
						<?php if($edit_banner->active == "no") { ?>
						<option value="no">No! Do not show the banner anywhere.</option>
						<option value="yes">Yes! The banner will be shown at random intervals.</option>
						<?php } else { ?>
						<option value="yes">Yes! The banner will be shown at random intervals.</option>
						<option value="no">No! Do not show the banner anywhere.</option>
						<?php } ?>
						</select></td>
			      	</tr>
				<?php if($banner_edit_id) { ?>
			      	<tr>
				        <th scope="row">Added:</th>
				        <td width="25%"><?php echo date("F d Y H:i", $edit_banner->thetime); ?></td>
				        <th scope="row">Updated:</th>
				        <td width="25%"><?php echo date("F d Y H:i", $edit_banner->updated); ?></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Clicked:</th>
				        <td width="25%"><?php if($banner->tracker == "Y") { echo $banner->clicks; } else { echo 'N/A'; } ?></td>
				        <th scope="row">Shown:</th>
				        <td width="25%"><?php echo $edit_banner->shown; ?></td>
			      	</tr>
				<?php } ?>
					</tbody>
		    	</table>


	      		<?php if($banner_edit_id) { ?>
				<br class="clear" />
				
				<h3>Preview</h3>
				
		    	<table class="widefat" style="margin-top: .5em">

					<thead>
					<tr valign="top">
						<th>Note: While this preview is an accurate one, it might look different then it does on the website.
						<br />This is because of CSS differences. Your themes CSS file is not active here!</th>
					</tr>
					</thead>

					<tbody>
			      	<tr>
				        <td><?php adrotate_banner($edit_banner->group, $banner_edit_id, true); ?></td>
			      	</tr>
			      	</tbody>
			      	
				</table>
				<?php } ?>


		    	<p class="submit">
					<input tabindex="14" type="submit" name="Submit" class="button-primary" value="Save banner &raquo;" />
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

/*-------------------------------------------------------------
 Name:      adrotate_options

 Purpose:   Admin options page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_options() {
	$adrotate_tracker = get_option('adrotate_tracker');
?>
	<div class="wrap">
	  	<h2>AdRotate options</h2>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>&amp;updated=true">
	    	<input type="hidden" name="adrotate_submit_options" value="true" />

	    	<table class="form-table">
			<tr>
				<th scope="row" valign="top">Why</th>
				<td>For fun and as an experiment i would like to gather some information and develop a simple stats system for it. I would like to ask you to participate in this experiment. All it takes for you is to not opt-out. More information is found <a href="http://meandmymac.net/plugins/data-project/" title="http://meandmymac.net/plugins/data-project/ - New window" target="_blank">here</a>. Any questions can be directed to the <a href="http://forum.at.meandmymac.net/" title="http://forum.at.meandmymac.net/ - New window" target="_blank">forum</a>.</td>

			</tr>
			<tr>
				<th scope="row" valign="top">Registration</th>
				<td><input type="checkbox" name="adrotate_register" <?php if($adrotate_tracker['register'] == 'Y') { ?>checked="checked" <?php } ?> /> Allow Meandmymac.net to collect some data about the plugin usage and your blog.<br /><em>This includes your blog name, blog address, email address and a selection of triggered events as well as the name and version of this plugin.</em></td>
			</tr>
			<tr>
				<th scope="row" valign="top">Anonymous</th>
				<td><input type="checkbox" name="adrotate_anonymous" <?php if($adrotate_tracker['anonymous'] == 'Y') { ?>checked="checked" <?php } ?> /> Your blog name, blog address and email will not be send.</td>
			</tr>
			<tr>
				<th scope="row" valign="top">Agree</th>
				<td><strong>Upon activating the plugin you agree to the following:</strong>

				<br />- All gathered information, but not your email address, may be published or used in a statistical overview for reference purposes.
				<br />- You're free to opt-out or to make any to be gathered data anonymous at any time.
				<br />- All acquired information remains in my database and will not be sold, made public or otherwise spread to third parties.
				<br />- If you opt-out or go anonymous, all previously saved data will remain intact.
				<br />- Requests to remove your data or make everything you sent anonymous will not be granted unless there are pressing issues.
				<br />- Anonymously gathered data cannot be removed since it's anonymous.
				</td>
			</tr>
	    	</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" class="button-primary" value="Update Options &raquo;" />
		    </p>
		</form>
	</div>
<?php } ?>