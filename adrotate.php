<?php
/*
Plugin Name: AdRotate
Plugin URI: http://www.adrotateplugin.com
Description: The very best and most convenient way to publish your ads.
Author: Arnan de Gans
Version: 3.4
Author URI: http://meandmymac.net/
License: GPL2
*/

/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*--- AdRotate values and config ----------------------------*/
define("ADROTATE_VERSION", 340);
define("ADROTATE_DB_VERSION", 3);
$adrotate_config 				= get_option('adrotate_config');
$adrotate_crawlers 				= get_option('adrotate_crawlers');
$adrotate_stats 				= get_option('adrotate_stats');
$adrotate_roles 				= get_option('adrotate_roles');
$adrotate_version				= get_option("adrotate_version");
$adrotate_db_version			= get_option("adrotate_db_version");
$adrotate_debug					= get_option("adrotate_debug");
/*-----------------------------------------------------------*/

/*--- Load Files --------------------------------------------*/
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-setup.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-manage.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-functions.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-output.php');
include_once(ABSPATH.'wp-content/plugins/adrotate/adrotate-widget.php');
// wp-content/plugins/adrotate/adrotate-out.php
// wp-content/plugins/adrotate/uninstall.php
/*-----------------------------------------------------------*/

/*--- Load functions ----------------------------------------*/
register_activation_hook(__FILE__, 'adrotate_activate');
register_deactivation_hook(__FILE__, 'adrotate_deactivate');
register_uninstall_hook(__FILE__, 'adrotate_uninstall');
if($adrotate_version < ADROTATE_VERSION) adrotate_upgrade();

adrotate_check_config();
adrotate_clean_trackerdata();
/*-----------------------------------------------------------*/

/*--- Front end ---------------------------------------------*/
add_shortcode('adrotate', 'adrotate_shortcode');
add_action('widgets_init', 'adrotate_widget_init');
add_action('wp_meta', 'adrotate_meta');
/*-----------------------------------------------------------*/

/*--- Dashboard ---------------------------------------------*/
add_action('admin_menu', 'adrotate_dashboard');
add_action('admin_notices','adrotate_notifications_dashboard');
add_action('wp_dashboard_setup', 'adrotate_dashboard_widget');
/*-----------------------------------------------------------*/

/*--- Core --------------------------------------------------*/
add_action('adrotate_ad_notification', 'adrotate_mail_notifications');
add_action('adrotate_cache_statistics', 'adrotate_prepare_cache_statistics');
add_filter('cron_schedules', 'adrotate_reccurences');
/*-----------------------------------------------------------*/

/*--- Internal redirects ------------------------------------*/
if(isset($_POST['adrotate_ad_submit'])) 				add_action('init', 'adrotate_insert_input');
if(isset($_POST['adrotate_group_submit'])) 				add_action('init', 'adrotate_insert_group');
if(isset($_POST['adrotate_block_submit'])) 				add_action('init', 'adrotate_insert_block');
if(isset($_POST['adrotate_action_submit'])) 			add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_options_submit'])) 			add_action('init', 'adrotate_options_submit');
if(isset($_POST['adrotate_request_submit'])) 			add_action('init', 'adrotate_mail_message');
if(isset($_POST['adrotate_testmail_submit'])) 			add_action('init', 'adrotate_mail_notifications');
if(isset($_POST['adrotate_role_add_submit']) OR isset($_POST['adrotate_role_remove_submit'])) add_action('init', 'adrotate_prepare_roles');
if(isset($_POST['adrotate_db_optimize_submit'])) 		add_action('init', 'adrotate_optimize_database');
if(isset($_POST['adrotate_db_repair_submit'])) 			add_action('init', 'adrotate_repair_database');
//if(isset($_POST['headers']) and isset($_POST['body'])) 	add_action('init', 'adrotate_receiver');
/*-----------------------------------------------------------*/

/*-------------------------------------------------------------
 Name:      adrotate_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard() {
	global $adrotate_config;

	add_object_page('AdRotate', 'AdRotate', 'adrotate_ad_manage', 'adrotate', 'adrotate_manage');
	add_submenu_page('adrotate', 'AdRotate > Manage Ads', 'Manage Ads', 'adrotate_ad_manage', 'adrotate', 'adrotate_manage');
	add_submenu_page('adrotate', 'AdRotate > Groups', 'Manage Groups', 'adrotate_group_manage', 'adrotate-groups', 'adrotate_manage_group');
	add_submenu_page('adrotate', 'AdRotate > Blocks', 'Manage Blocks', 'adrotate_block_manage', 'adrotate-blocks', 'adrotate_manage_block');
	add_submenu_page('adrotate', 'AdRotate > User Statistics', 'User Statistics', 'adrotate_userstatistics', 'adrotate-userstatistics', 'adrotate_userstatistics');
	add_submenu_page('adrotate', 'AdRotate > Global Statistics', 'Global Statistics', 'adrotate_globalstatistics', 'adrotate-statistics', 'adrotate_statistics');
	add_submenu_page('adrotate', 'AdRotate > Settings','Settings', 'manage_options', 'adrotate-settings', 'adrotate_options');
}

/*-------------------------------------------------------------
 Name:      adrotate_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage() {
	global $wpdb, $userdata;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$ad_edit_id 	= $_GET['edit_ad'];
	$now 			= current_time('timestamp');
	$in2days 		= $now + 172800;
	
	if(isset($_POST['adrotate_order_submit'])) { 
		$order = $_POST['adrotate_order']; 
	} else { 
		$order = 'thetime ASC'; 
	}
	?>

	<style type="text/css" media="screen">
	.row_urgent {
		background-color:#ffebe8;
		border-color:#c00;
	}
	.row_error {
		background-color:#ffffe0;
		border-color:#e6db55;
	}
	</style>

	<div class="wrap">
		<h2>Ad Management</h2>

		<?php if ($message == 'created') { ?>
			<div id="message" class="updated fade"><p>Ad created</p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p>Ad updated</p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p>Ad(s) deleted</p></div>
		<?php } else if ($message == 'reset') { ?>
			<div id="message" class="updated fade"><p>Ad(s) statistics reset</p></div>
		<?php } else if ($message == 'renew') { ?>
			<div id="message" class="updated fade"><p>Ad(s) renewed</p></div>
		<?php } else if ($message == 'deactivate') { ?>
			<div id="message" class="updated fade"><p>Ad(s) deactivated</p></div>
		<?php } else if ($message == 'activate') { ?>
			<div id="message" class="updated fade"><p>Ad(s) activated</p></div>
		<?php } else if ($message == 'field_error') { ?>
			<div id="message" class="updated fade"><p>The ad was saved but has an issue which might prevent it from working properly. Review the yellow marked ad.</p></div>
		<?php } else if ($message == 'no_access') { ?>
			<div id="message" class="updated fade"><p>Action prohibited</p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_groups';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=manage';?>">Manage</a> | 
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=addnew';?>">Add New</a>
				</div>
			</div>

	    	<?php if ($view == "" OR $view == "manage") { ?>

			<h3>Manage Ads</h3>

			<form name="banners" id="post" method="post" action="admin.php?page=adrotate">
				
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="adrotate_action" id="cat" class="postform">
					        <option value="">Bulk Actions</option>
					        <option value="deactivate">Deactivate</option>
					        <option value="activate">Activate</option>
					        <option value="delete">Delete</option>
					        <option value="reset">Reset stats</option>
					        <option value="renew-31536000">Renew for 1 year</option>
					        <option value="renew-5184000">Renew for 180 days</option>
					        <option value="renew-2592000">Renew for 30 days</option>
					        <option value="renew-604800">Renew for 7 days</option>
						</select>
						<input type="submit" id="post-action-submit" name="adrotate_action_submit" value="Go" class="button-secondary" />
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						Sort by <select name="adrotate_order" id="cat" class="postform">
					        <option value="startshow ASC" <?php if($order == "startshow ASC") { echo 'selected'; } ?>>start date (ascending)</option>
					        <option value="startshow DESC" <?php if($order == "startshow DESC") { echo 'selected'; } ?>>start date (descending)</option>
					        <option value="endshow ASC" <?php if($order == "endshow ASC") { echo 'selected'; } ?>>end date (ascending)</option>
					        <option value="endshow DESC" <?php if($order == "endshow DESC") { echo 'selected'; } ?>>end date (descending)</option>
					        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>ID</option>
					        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>ID reversed</option>
					        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>title (A-Z)</option>
					        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>title (Z-A)</option>
					        <option value="clicks ASC" <?php if($order == "clicks ASC") { echo 'selected'; } ?>>clicks (Low to high)</option>
					        <option value="clicks DESC" <?php if($order == "clicks DESC") { echo 'selected'; } ?>>clicks (High to low)</option>
						</select>
						<input type="submit" id="post-query-submit" name="adrotate_order_submit" value="Sort" class="button-secondary" />
					</div>
	
					<br class="clear" />
				</div>
	
			   	<table class="widefat" style="margin-top: .5em">
	 			<thead>
	  				<tr>
						<th class="check-column">&nbsp;</th>
						<th width="2%"><center>ID</center></th>
						<th width="13%">Show from</th>
						<th width="13%">Show until</th>
						<th width="5%"><center>Active</center></th>
						<th>Title</th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>CTR</center></th>
					</tr>
	  			</thead>
	  			<tbody>
				<?php
				$banners = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'manual' OR `type` = 'error' ORDER BY ".$order);
				if ($banners) {
					foreach($banners as $banner) {
						$groups	= $wpdb->get_results("SELECT `".$wpdb->prefix."adrotate_groups`.`name` 
													FROM `".$wpdb->prefix."adrotate_groups`, `".$wpdb->prefix."adrotate_linkmeta` 
													WHERE `".$wpdb->prefix."adrotate_linkmeta`.`ad` = '".$banner->id."'
														AND `".$wpdb->prefix."adrotate_linkmeta`.`group` = `".$wpdb->prefix."adrotate_groups`.`id`
														AND `".$wpdb->prefix."adrotate_linkmeta`.`block` = 0
														AND `".$wpdb->prefix."adrotate_linkmeta`.`user` = 0
													;");
						$grouplist = '';
						foreach($groups as $group) {
							$grouplist .= $group->name.", ";
						}
						$grouplist = rtrim($grouplist, ", ");
						
						if($banner->type == 'error') {
							$publisherror = ' row_error';
						} else {
							$publisherror = '';
						}
	
						if($banner->endshow <= $now OR $banner->endshow <= $in2days) {
							$expiredclass = ' row_urgent';
						} else {
							$expiredclass = '';
						}
	
						if($class != 'alternate') {
							$class = 'alternate';
						} else {
							$class = '';
						}
						?>
					    <tr id='banner-<?php echo $banner->id; ?>' class='<?php echo $class.$expiredclass.$publisherror; ?>'>
							<th class="check-column"><input type="checkbox" name="bannercheck[]" value="<?php echo $banner->id; ?>" /></th>
							<td><center><?php echo $banner->id;?></center></td>
							<td><?php echo date("F d, Y", $banner->startshow);?></td>
							<td><span style="color: <?php echo adrotate_prepare_color($banner->endshow);?>;"><?php echo date("F d, Y", $banner->endshow);?></span></td>
							<td><center><?php if($banner->active == "yes") { 
								echo '<img src="'.get_option('siteurl').'/wp-content/plugins/adrotate/icons/tick.png" title="Active"/>'; 
							} else { 
								echo '<img src="'.get_option('siteurl').'/wp-content/plugins/adrotate/icons/cross.png" title="Inactive"/>'; 
							}?></center></td>
							<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=edit&edit_ad='.$banner->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($banner->title));?></a></strong><br /><?php if($groups) echo '<em style="color:#999">'.$grouplist.'</em>'; ?></td>
							<td><center><?php echo $banner->shown;?></center></td>
							<?php if($banner->tracker == "Y") { ?>
							<td><center><?php echo $banner->clicks;?></center></td>
								<?php if($banner->shown == 0) $banner->shown = 1; ?>
							<td><center><?php echo round((100/$banner->shown)*$banner->clicks,2);?> %</center></td>
							<?php } else { ?>
							<td colspan="2"><center>N/A</center></td>
							<?php } ?>
						</tr>
		 			<?php } ?>
		 		<?php } else { ?>
					<tr id='no-groups'>
						<th class="check-column">&nbsp;</th>
						<td colspan="8"><em>No ads created yet!</em></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			
			</form>

			<br class="clear" />
			
		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>

			<?php if(!$ad_edit_id) { ?>
			<h3>New Ad</h3>
			<?php
				$action = "new";
				$startshow = $now;
				$endshow = $now + 31536000;
				$query = "SELECT `id` FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'empty' ORDER BY `id` DESC LIMIT 1;";
				$edit_id = $wpdb->get_var($query);
				if($edit_id == 0) {
					$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate` (`title`, `bannercode`, `thetime`, `updated`, `author`, `active`, `startshow`, `endshow`, `image`, `link`, `tracker`, `clicks`, `maxclicks`, `shown`, `maxshown`, `type`, `weight`) VALUES ('', '', '$startshow', '$startshow', '$userdata->user_login', 'no', '$startshow', '$endshow', '', '', 'N', 0, 0, 0, 0, 'empty', 6);");
					$edit_id = $wpdb->get_var($query);
				}
				$ad_edit_id = $edit_id;
			} else { ?>
			<h3>Edit Ad</h3>
			<?php
				$action = "update";
				
			}
			
			$edit_banner 	= $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$ad_edit_id';");
			$groups			= $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' ORDER BY `id` ASC;"); 
			$user_list		= $wpdb->get_results("SELECT `ID` FROM `$wpdb->users` ORDER BY `user_nicename` ASC;");
			$saved_user 	= $wpdb->get_var("SELECT `user` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$edit_banner->id' AND `group` = 0 AND `block` = 0;");
			$linkmeta		= $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '$edit_banner->id' AND `block` = 0 AND `user` = 0;");
			foreach($linkmeta as $meta) {
				$meta_array[] = $meta->group;
			}
			if(!is_array($meta_array)) $meta_array = array();
			
			list($sday, $smonth, $syear) = split(" ", gmdate("d m Y", $edit_banner->startshow));
			list($eday, $emonth, $eyear) = split(" ", gmdate("d m Y", $edit_banner->endshow));
			?>
				
			<?php if($ad_edit_id) {
				if (strlen($edit_banner->bannercode) < 1 AND $edit_banner->type != 'empty') echo '<div id="message" class="error"><p>The AdCode cannot be empty!</p></div>';
				if ($edit_banner->tracker == 'N' AND strlen($edit_banner->link) < 1 AND $saved_user > 0) echo '<div id="message" class="error"><p>You\'ve set an advertiser but didn\'t enable clicktracking!</p></div>';
				if ($edit_banner->tracker == 'Y' AND strlen($edit_banner->link) < 1) echo '<div id="message" class="error"><p>You\'ve enabled clicktracking but didn\'t provide an url in the url field!</p></div>';
				if ($edit_banner->tracker == 'N' AND strlen($edit_banner->link) > 0) echo '<div id="message" class="error"><p>You didn\'t enable clicktracking but you did use the url field!</p></div>';
			} ?>
			
		  	<form method="post" action="admin.php?page=adrotate">
		    	<input type="hidden" name="adrotate_username" value="<?php echo $userdata->user_login;?>" />
		    	<input type="hidden" name="adrotate_id" value="<?php echo $edit_banner->id;?>" />
		    	<input type="hidden" name="adrotate_type" value="<?php echo $edit_banner->type;?>" />
	
		    	<table class="widefat" style="margin-top: .5em">
	
					<thead>
					<tr>
						<th colspan="4">The basics (Required)</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <th>Title:</th>
				        <td colspan="3"><input tabindex="1" name="adrotate_title" type="text" size="80" class="search-input" value="<?php echo $edit_banner->title;?>" autocomplete="off" /></td>
			      	</tr>
			      	<tr>
				        <th valign="top">AdCode:</th>
				        <td colspan="2"><textarea tabindex="2" name="adrotate_bannercode" cols="65" rows="15"><?php echo stripslashes($edit_banner->bannercode); ?></textarea></td>
				        <td>
					        <p><strong>Options:</strong></p>
					        <p><em>%id%, %image%, %link%</em><br />
					        HTML/JavaScript allowed, use with care!</p>
					        
					        <p><strong>Basic Examples:</strong></p>
					        <p>Clicktracking: <em>&lt;a href="%link%"&gt;This ad is great!&lt;/a&gt;</em></p>
					        <p>Image: <em>&lt;a href="http://example.com"&gt;&lt;img src="%image%" /&gt;&lt;/a&gt;</em></p>
					        <p>Combination: <em>&lt;a href="%link%"&gt;&lt;img src="%image%" /&gt;&lt;/a&gt;</em></p>
					        
					        <p><strong>Advanced Example:</strong></p>
					        <p>Clicktracking: <em>&lt;span class="ad-%id%"&gt;&lt;a href="%link%"&gt;Text Link Ad!&lt;/a&gt;&lt;/span&gt;</em></p>
				        </td>
			      	</tr>
			      	<tr>
				        <th>Display From:</th>
				        <td>
				        	<input tabindex="3" name="adrotate_sday" class="search-input" type="text" size="4" maxlength="2" value="<?php echo $sday;?>" /> /
							<select tabindex="4" name="adrotate_smonth">
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
							<input tabindex="5" name="adrotate_syear" class="search-input" type="text" size="4" maxlength="4" value="<?php echo $syear;?>" />
				        </td>
				        <th>Until:</th>
				        <td>
				        	<input tabindex="6" name="adrotate_eday" class="search-input" type="text" size="4" maxlength="2" value="<?php echo $eday;?>"  /> /
							<select tabindex="7" name="adrotate_emonth">
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
							<input tabindex="8" name="adrotate_eyear" class="search-input" type="text" size="4" maxlength="4" value="<?php echo $eyear;?>" />
						</td>
			      	</tr>
			      	<tr>
				        <th>Activate:</th>
				        <td colspan="3">
					        <select tabindex="9" name="adrotate_active">
								<option value="yes" <?php if($edit_banner->active == "yes") { echo 'selected'; } ?>>Yes, this ad will be used</option>
								<option value="no" <?php if($edit_banner->active == "no") { echo 'selected'; } ?>>No, no do not show this ad anywhere</option>
							</select>
						</td>
			      	</tr>
					</tbody>
	
				<?php if($edit_banner->type != 'empty') { ?>
					<thead>
					<tr>
						<th colspan="4">Preview</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <td colspan="4"><?php echo adrotate_preview($edit_banner->id); ?>
				        <br /><em>Note: While this preview is an accurate one, it might look different then it does on the website.
						<br />This is because of CSS differences. Your themes CSS file is not active here!</em></td>
			      	</tr>
			      	</tbody>
				<?php } ?>
	
					<thead>
					<tr>
						<th colspan="4">Usage</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <th>In a post or page:</th>
				        <td>[adrotate banner="<?php echo $edit_banner->id; ?>"]</td>
				        <th>Directly in a theme:</th>
				        <td>&lt;?php echo adrotate_ad(<?php echo $edit_banner->id; ?>); ?&gt;</td>
			      	</tr>
			      	</tbody>
	
					<thead>
					<tr>
						<th colspan="4" bgcolor="#DDD">Advanced (Everything below is optional)</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <th valign="top">Advertiser:</th>
				        <td colspan="3">
				        	<select tabindex="10" name="adrotate_advertiser" style="min-width: 200px;">
								<option value="0" <?php if($saved_user == '0') { echo 'selected'; } ?>>Not specified</option>
							<?php 
							foreach($user_list as $id) {
								$user = get_userdata($id->ID); 
								if(strlen($user->first_name) < 1) $firstname = $user->user_login;
									else $firstname = $user->first_name;
								if(strlen($user->last_name) < 1) $lastname = ''; 
									else $lastname = $user->last_name;
								if($userdata->ID == $id->ID) $you = '(You)';
									$you = ''; 
							?>
								<option value="<?php echo $id->ID; ?>"<?php if($saved_user == $id->ID) { echo ' selected'; } ?>><?php echo $firstname; ?> <?php echo $lastname; ?> <?php echo $you; ?></option>
							<?php } ?>
							</select><br />
					        <em>Must be a registered user on your site with appropriate access roles.</em>
						</td>
			      	</tr>
			      	<tr>
				        <th valign="top">Clicktracking:</th>
				        <td colspan="3">
				        	Enable? <input tabindex="10" type="checkbox" name="adrotate_tracker" <?php if($edit_banner->tracker == 'Y') { ?>checked="checked" <?php } ?> /> url: <input tabindex="11" name="adrotate_link" type="text" size="80" class="search-input" value="<?php echo $edit_banner->link;?>" /><br />
					        <em>Use %link% in the adcode instead of the actual url.<br />
					        For a random seed you can use %random%. A generated timestamp you can use.</em>
				        </td>
			      	</tr>
			      	<tr>
				        <th valign="top">Banner image:</th>
				        <td colspan="3"><select tabindex="12" name="adrotate_image" style="min-width: 200px;">
	   						<option value="none">No image or remote</option>
							<?php echo adrotate_folder_contents($edit_banner->image); ?>
						</select>
						<br /><em>Use %image% in the code. Accepted files are: jpg, jpeg, gif, png, swf and flv.</em></td>
			      	</tr>
			      	<tr>
					    <th valign="top">Weight:</th>
				        <td colspan="3"><select tabindex="13" name="adrotate_weight">
								<option value="2" <?php if($edit_banner->weight == "2") { echo 'selected'; } ?>>2 - Barely visible</option>
								<option value="4" <?php if($edit_banner->weight == "4") { echo 'selected'; } ?>>4 - Less than average</option>
								<option value="6" <?php if($edit_banner->weight == "6") { echo 'selected'; } ?>>6 - Normal coverage</option>
								<option value="8" <?php if($edit_banner->weight == "8") { echo 'selected'; } ?>>8 - More than average</option>
								<option value="10" <?php if($edit_banner->weight == "10") { echo 'selected'; } ?>>10 - Best visibility</option>
							</select>
						</td>
					</tr>
			      	<tr>
					    <th>Maximum Clicks:</th>
				        <td colspan="3">Disable after <input tabindex="14" name="adrotate_maxclicks" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->maxclicks;?>" /> clicks! <em>Leave empty or 0 to skip this.</em></td>
					</tr>
			      	<tr>
					    <th>Maximum Impressions:</th>
				        <td colspan="3">Disable after <input tabindex="15" name="adrotate_maxshown" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->maxshown;?>" /> views! <em>Leave empty or 0 to skip this.</em></td>
					</tr>
					</tbody>

				<?php if($edit_banner->type != 'empty') { ?>
					<thead>
					<tr>
						<th colspan="4" bgcolor="#DDD">Maintenance</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <th>Actions:</th>
				        <td colspan="3">
					        <select name="adrotate_action" id="cat" class="postform">
						        <option value="0">--</option>
						        <option value="renew-31536000">Renew for 1 year</option>
						        <option value="renew-5184000">Renew for 180 days</option>
						        <option value="renew-2592000">Renew for 30 days</option>
						        <option value="renew-604800">Renew for 7 days</option>
						        <option value="delete">Delete</option>
						        <option value="reset">Reset stats</option>
							</select> <input type="submit" id="post-action-submit" name="adrotate_action_submit" value="Go" class="button-secondary" />
						</td>
					</tr>
					</tbody>
				<?php } ?>
				
				</table>
	
				<br class="clear" />

				<?php if($groups) { ?>
		    	<table class="widefat" style="margin-top: .5em">
		  			<thead>
	  				<tr>
						<th colspan="3">Select the group(s) this ad belongs to (Optional)</th>
					</tr>
		  			</thead>

					<tbody>
					<?php foreach($groups as $group) {
						$ads_in_group = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = ".$group->id." AND `block` = 0;");
						$class = ('alternate' != $class) ? 'alternate' : ''; ?>
					    <tr id='group-<?php echo $group->id; ?>' class=' <?php echo $class; ?>'>
							<th class="check-column"><input type="checkbox" name="groupselect[]" value="<?php echo $group->id; ?>" <?php if(in_array($group->id, $meta_array)) echo "checked"; ?> /></th>
							<td><?php echo $group->id; ?> - <strong><?php echo $group->name; ?></strong></td>
							<td width="15%"><?php echo $ads_in_group; ?> Ads</td>
						</tr>
		 			<?php } ?>
					</tbody>					
				</table>
				<?php } ?>

				<br class="clear" />

		    	<table class="widefat" style="margin-top: .5em">
					<thead>
					<tr>
						<th colspan="4" bgcolor="#DDD">Statistics</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <th>Added:</th>
				        <td width="25%"><?php echo date("F d, Y H:i", $edit_banner->thetime); ?></td>
				        <th>Updated:</th>
				        <td width="25%"><?php echo date("F d, Y H:i", $edit_banner->updated); ?></td>
			      	</tr>
			      	<tr>
				        <th>Clicked:</th>
				        <td width="25%"><?php if($edit_banner->tracker == "Y") { echo $edit_banner->clicks; } else { echo 'N/A'; } ?></td>
				        <th>Impressions:</th>
				        <td width="25%"><?php echo $edit_banner->shown; ?></td>
			      	</tr>
			      	<tr>
				        <th>CTR:</th>
							<?php if($edit_banner->shown == 0) $edit_banner->shown = 1; ?>
				        <td width="25%"><?php if($edit_banner->tracker == "Y") { echo round((100/$edit_banner->shown)*$edit_banner->clicks,2).' %'; } else { echo 'N/A'; } ?></td>
				        <th>&nbsp;</th>
				        <td width="25%">&nbsp;</td>
			      	</tr>
			      	<tr>
				        <th>Note:</th>
							<?php if($edit_banner->shown == 0) $edit_banner->shown = 1; ?>
				        <td colspan="3"><em>All statistics are indicative. They do not nessesarily reflect results counted by other parties.</em></td>
			      	</tr>
					</tbody>
				</table>
	
		    	<p class="submit">
					<input tabindex="16" type="submit" name="adrotate_ad_submit" class="button-primary" value="Save ad" />
					<a href="admin.php?page=adrotate&view=manage" class="button">Cancel</a>
		    	</p>
	
			</form>

		   	<?php } ?>

			<?php adrotate_credits(); ?>

		<?php } else { ?>
			<?php echo adrotate_error('db_error'); ?>
		<?php }	?>
		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_manage_group

 Purpose:   Manage groups
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage_group() {
	global $wpdb;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$group_edit_id 	= $_GET['edit_group'];
	?>

	<div class="wrap">
		<h2>Group Management</h2>

		<?php if ($message == 'created') { ?>
			<div id="message" class="updated fade"><p>Group created</p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p>Group updated</p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p>Group deleted</p></div>
		<?php } else if ($message == 'deleted_banners') { ?>
			<div id="message" class="updated fade"><p>Group including it's Ads deleted</p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_groups';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=manage';?>">Manage</a> | 
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=addnew';?>">Add New</a>
				</div>
			</div>

	    	<?php if ($view == "" OR $view == "manage") { ?>

			<h3>Manage Groups</h3>

			<form name="groups" id="post" method="post" action="admin.php?page=adrotate-groups">
	
				<div class="tablenav">
					<div class="alignleft">
						<select name="adrotate_action" id="cat" class="postform">
					        <option value="">Bulk Actions</option>
					        <option value="group_delete">Delete Group</option>
							<option value="group_delete_banners">Delete Group including ads</option>
						</select>
						<input onclick="return confirm('You are about to delete a group\nThis action can not be undone!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" id="post-action-submit" name="adrotate_action_submit" value="Go" class="button-secondary" />
					</div>
				</div>
				
			   	<table class="widefat" style="margin-top: .5em">
		  			<thead>
	  				<tr>
						<th class="check-column">&nbsp;</th>
						<th width="5%"><center>ID</center></th>
						<th>Name</th>
						<th width="10%"><center>Ads</center></th>
						<th width="15%"><center>Code</center></th>
						<th width="10%"><center>Fallback</center></th>
					</tr>
		  			</thead>
					<tbody>
		  			
					<?php $groups = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "adrotate_groups` WHERE `name` != '' ORDER BY `id`;");
					if ($groups) {
						foreach($groups as $group) {
							$ads_in_group = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = ".$group->id." AND `block` = 0;");
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <trclass='<?php echo $class; ?>'>
								<th class="check-column"><input type="checkbox" name="groupcheck[]" value="<?php echo $group->id; ?>" /></th>
								<td><center><?php echo $group->id;?></center></td>
								<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=edit&edit_group='.$group->id;?>" title="Edit"><?php echo $group->name;?></a></strong></td>
								<td><center><?php echo $ads_in_group;?></center></td>
								<td><center>[adrotate group="<?php echo $group->id; ?>"]</center></td>
								<td><center><?php if($group->fallback == 0) { echo "Not set"; } else { echo $group->fallback; } ?></center></td>
							</tr>
			 			<?php } ?>
					<?php } else { ?>
					<tr>
						<th class="check-column">&nbsp;</th>
						<td colspan="5"><em>No groups created!</em></td>
					</tr>
					<?php } ?>
		 			</tbody>
				</table>
			</form>

			<br class="clear" />

		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>
		   	
				<?php if(!$group_edit_id) { ?>
				<h3>New group</h3>
					<?php
					$action = "group_new";
					$query = "SELECT `id` FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` = '' ORDER BY `id` DESC LIMIT 1;";
					$edit_id = $wpdb->get_var($query);
					if($edit_id == 0) {
						$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_groups` (`name`, `fallback`) VALUES ('', 0);");
						$edit_id = $wpdb->get_var($query);
					}
					$group_edit_id = $edit_id;
					?>
				<?php } else { ?>
				<h3>Edit Group</h3>
				<?php 
					$action = "group_edit";
				}

				$edit_group = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = '$group_edit_id';");
				$groups		= $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' ORDER BY `id` ASC;"); 
				$ads		= $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `type` != 'empty' AND `active` = 'yes' ORDER BY `id` ASC;"); 
				$linkmeta	= $wpdb->get_results("SELECT `ad` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = '$group_edit_id' AND `block` = 0 AND `user` = 0;");
				foreach($linkmeta as $meta) {
					$meta_array[] = $meta->ad;
				}
				if(!is_array($meta_array)) $meta_array = array();
				?>
	
				<form name="editgroup" id="post" method="post" action="admin.php?page=adrotate-groups">
			    	<input type="hidden" name="adrotate_id" value="<?php echo $edit_group->id;?>" />
			    	<input type="hidden" name="adrotate_action" value="<?php echo $action;?>" />
	
				   	<table class="widefat" style="margin-top: .5em">
	
			  			<thead>
		  				<tr>
							<th colspan="4">Fill in a name as reference and specify a fallback group</th>
						</tr>
			  			</thead>
	
						<tbody>
					    <tr>
							<th width="15%">ID:</th>
							<td colspan="3"><?php echo $edit_group->id; ?></td>
						</tr>
					    <tr>
							<th width="15%">Name:</th>
							<td colspan="3"><input tabindex="1" name="adrotate_groupname" type="text" class="search-input" size="80" value="<?php echo $edit_group->name; ?>" autocomplete="off" /></td>
						</tr>
					    <tr>
							<th>Fallback group?</th>
							<td colspan="3">
								<select tabindex="2" name="adrotate_fallback">
						        <option value="0">No</option>
							<?php if ($groups) { ?>
								<?php foreach($groups as $group) { ?>
							        <option value="<?php echo $group->id;?>" <?php if($edit_group->fallback == $group->id) { echo 'selected'; } ?>><?php echo $group->id;?> - <?php echo $group->name;?></option>
					 			<?php } ?>
							<?php } ?>
								</select> <em>You need atleast two groups to use this feature!</em>
							</td>
						</tr>
						<?php if($edit_group->name != '') { ?>
				      	<tr>
					        <th>This group is in the block(s):</th>
					        <td colspan="3"><?php echo adrotate_group_is_in_blocks($edit_group->id); ?></td>
				      	</tr>
						<?php } ?>
						</tbody>
	
						<thead>
						<tr>
							<th colspan="4">Group Code</th>
						</tr>
						</thead>
		
						<tbody>
				      	<tr>
					        <th width="15%">In a post or page:</th>
					        <td width="35%">[adrotate group="<?php echo $edit_group->id; ?>"]</td>
					        <th width="15%">Directly in a theme:</th>
					        <td width="35%">&lt;?php echo adrotate_group(<?php echo $edit_group->id; ?>); ?&gt;</td>
				      	</tr>
				      	</tbody>
					</table>
				
					<br class="clear" />
	
				   	<table class="widefat" style="margin-top: .5em">
			  			<thead>
		  				<tr>
							<th colspan="2">Choose the ads to use in this group</th>
							<th width="5%"><center>Impressions</center></th>
							<th width="5%"><center>Clicks</center></th>
							<th width="5%"><center>Visibility</center></th>
							<th width="15%">Visible until</th>
						</tr>
			  			</thead>
	
						<tbody>
						<?php if($ads) {
							foreach($ads as $ad) {
								$class = ('alternate' != $class) ? 'alternate' : ''; ?>
							    <tr class='<?php echo $class; ?>'>
									<th class="check-column"><input type="checkbox" name="adselect[]" value="<?php echo $ad->id; ?>" <?php if(in_array($ad->id, $meta_array)) echo "checked"; ?> /></th>
									<td><?php echo $ad->id; ?> - <strong><?php echo $ad->title; ?></strong></td>
									<td><center><?php echo $ad->shown; ?></center></td>
									<td><center><?php if($ad->tracker == 'Y') { echo $ad->clicks; } else { ?>N/A<?php } ?></center></td>
									<td><center><?php echo $ad->weight; ?></center></td>
									<td><span style="color: <?php echo adrotate_prepare_color($ad->endshow);?>;"><?php echo date("F d, Y", $ad->endshow); ?></span></td>
								</tr>
				 			<?php } ?>
						<?php } else { ?>
						<tr>
							<th class="check-column">&nbsp;</th>
							<td colspan="5"><em>No ads created!</em></td>
						</tr>
						<?php } ?>
						</tbody>					
			 		</table>
					
			    	<p class="submit">
						<input tabindex="3" type="submit" name="adrotate_group_submit" class="button-primary" value="Save" />
						<a href="admin.php?page=adrotate-groups&view=manage" class="button">Cancel</a>
			    	</p>
	
				</form>

		   	<?php } ?>
	
			<?php adrotate_credits(); ?>

		<?php } else { ?>
			<?php echo adrotate_error('db_error'); ?>
		<?php }	?>
		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_manage_block

 Purpose:   Manage blocks of ads
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage_block() {
	global $wpdb, $userdata;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$block_edit_id 	= $_GET['edit_block'];
	?>

	<div class="wrap">
		<h2>Block Management</h2>

		<?php if ($message == 'created') { ?>
			<div id="message" class="updated fade"><p>Block created</p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p>Block updated</p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p>Block deleted</p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_blocks';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=manage';?>">Manage</a> | 
					<a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=addnew';?>">Add New</a>
				</div>
			</div>

	    	<?php if ($view == "" OR $view == "manage") { ?>

			<h3>Manage Blocks</h3>

			<form name="blocks" id="post" method="post" action="admin.php?page=adrotate-blocks">
	
				<div class="tablenav">
					<div class="alignleft">
						<select name="adrotate_action" id="cat" class="postform">
					        <option value="">Bulk Actions</option>
					        <option value="block_delete">Delete Block(s)</option>
						</select>
						<input onclick="return confirm('You are about to delete a block\nThis action can not be undone!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" id="post-action-submit" name="adrotate_action_submit" value="Go" class="button-secondary" />
					</div>
				</div>
	
			   	<table class="widefat" style="margin-top: .5em">

		  			<thead>
	  				<tr>
						<th class="check-column">&nbsp;</th>
						<th width="5%"><center>ID</center></th>
						<th>Name</th>
						<th width="10%"><center>Groups</center></th>
						<th width="20%"><center>Code</center></th>
					</tr>
		  			</thead>

					<tbody>
		  			
					<?php $blocks = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "adrotate_blocks` WHERE `name` != '' ORDER BY `id`;");
					if ($blocks) {
						foreach($blocks as $block) {
							$groups_in_block = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `block` = ".$block->id.";");
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <tr class='<?php echo $class; ?>'>
								<th class="check-column"><input type="checkbox" name="blockcheck[]" value="<?php echo $block->id; ?>" /></th>
								<td><center><?php echo $block->id;?></center></td>
								<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=edit&edit_block='.$block->id;?>" title="Edit"><?php echo $block->name;?></a></strong></td>
								<td><center><?php echo $groups_in_block;?></center></td>
								<td><center>[adrotate block="<?php echo $block->id; ?>"]</center></td>
							</tr>
			 			<?php } ?>
					<?php } else { ?>
					<tr>
						<th class="check-column">&nbsp;</th>
						<td colspan="4"><em>No blocks created yet!</em></td>
					</tr>
					<?php } ?>
		 			</tbody>

				</table>
			</form>

			<br class="clear" />

		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>
		   	
				<?php if(!$block_edit_id) { ?>
				<h3>New Block</h3>
					<?php
					$action = "block_new";
					$query = "SELECT `id` FROM `".$wpdb->prefix."adrotate_blocks` WHERE `name` = '' ORDER BY `id` DESC LIMIT 1;";
					$edit_id = $wpdb->get_var($query);
					if($edit_id == 0) {
						$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_blocks` (`name`, `adcount`, `columns`) VALUES ('', '', '');");
						$edit_id = $wpdb->get_var($query);
					}
					$block_edit_id = $edit_id;
					?>
				<?php } else { ?>
				<h3>Edit Block</h3>
				<?php 
					$action = "block_edit";
				} 
				
				$edit_block = $wpdb->get_row("SELECT * FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '$block_edit_id';");
				$groups		= $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '' ORDER BY `id` ASC;"); 
				$linkmeta	= $wpdb->get_results("SELECT `group` FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = 0 AND `block` = '$block_edit_id' AND `user` = 0;");
				foreach($linkmeta as $meta) {
					$meta_array[] = $meta->group;
				}
				if(!is_array($meta_array)) $meta_array = array();
				?>

				<form name="editblock" id="post" method="post" action="admin.php?page=adrotate-blocks">
			    	<input type="hidden" name="adrotate_id" value="<?php echo $edit_block->id;?>" />
			    	<input type="hidden" name="adrotate_action" value="<?php echo $action;?>" />

				   	<table class="widefat" style="margin-top: .5em">
				   	
			  			<thead>
		  				<tr>
							<th colspan="4">The basics (Required)</th>
						</tr>
			  			</thead>
			  			
						<tbody>
					    <tr>
							<th width="15%">ID:</th>
							<td colspan="3"><?php echo $edit_block->id; ?></td>
						</tr>
					    <tr>
							<th width="15%">Name / Reference:</th>
							<td colspan="3"><input tabindex="1" name="adrotate_blockname" type="text" class="search-input" size="80" value="<?php echo $edit_block->name; ?>" autocomplete="off" /></td>
						</tr>
					    <tr>
							<th width="15%">How many ads and columns</th>
							<td colspan="3">
								<input tabindex="2" name="adrotate_adcount" type="text" class="search-input" size="5" value="<?php echo $edit_block->adcount; ?>" autocomplete="off" /> ads in <input tabindex="3" name="adrotate_columns" type="text" class="search-input" size="5" value="<?php echo $edit_block->columns; ?>" autocomplete="off" /> columns. (Example: 4 ads in 2 columns, makes a square block of 2x2 ads.)
							</td>
						</tr>
						</tbody>
				   	
			  			<thead>
		  				<tr>
							<th colspan="4">Wrapper code (Optional) - Wraps around each ad to facilitate easy margins, paddings or borders around ads</th>
						</tr>
			  			</thead>
			  			
						<tbody>
					    <tr>
							<th valign="top">Before ad</strong></th>
							<td colspan="2"><textarea tabindex="4" name="adrotate_wrapper_before" cols="65" rows="3"><?php echo $edit_block->wrapper_before; ?></textarea></td>
							<td>
						        <p><strong>Example:</strong></p>
						        <p><em>&lt;span style="margin: 2px;"&gt;</em></p>
							</td>
						</tr>
					    <tr>
							<th valign="top">After ad</strong></th>
							<td colspan="2"><textarea tabindex="5" name="adrotate_wrapper_after" cols="65" rows="3"><?php echo $edit_block->wrapper_after; ?></textarea></td>
							<td>
								<p><strong>Example:</strong></p>
								<p><em>&lt;/span&gt;</em></p>
							</td>
						</tr>
						</tbody>
	
						<thead>
						<tr valign="top">
							<th colspan="4">Block Code</th>
						</tr>
						</thead>
		
						<tbody>
				      	<tr>
					        <th width="15%">In a post or page:</th>
					        <td>[adrotate block="<?php echo $edit_block->id; ?>"]</td>
					        <th width="15%">Directly in a theme:</th>
					        <td width="35%">&lt;?php echo adrotate_block(<?php echo $edit_block->id; ?>); ?&gt;</td>
				      	</tr>
				      	</tbody>
					</table>
					
					<br class="clear" />
	
				   	<table class="widefat" style="margin-top: .5em">
			  			<thead>
		  				<tr>
							<th colspan="3">Choose the groups to use in this block</th>
						</tr>
			  			</thead>
	
						<tbody>
						<?php if($groups) {
							foreach($groups as $group) {
								$ads_in_group = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = ".$group->id." AND `block` = 0;");
								$class = ('alternate' != $class) ? 'alternate' : ''; ?>
							    <tr class='<?php echo $class; ?>'>
									<th class="check-column"><input type="checkbox" name="groupselect[]" value="<?php echo $group->id; ?>" <?php if(in_array($group->id, $meta_array)) echo "checked"; ?> /></th>
									<td><?php echo $group->id; ?> - <strong><?php echo $group->name; ?></strong></td>
									<td width="10%"><?php echo $ads_in_group; ?> Ads</td>
								</tr>
				 			<?php } ?>
						<?php } else { ?>
						<tr>
							<th class="check-column">&nbsp;</th>
							<td colspan="2"><em>No groups created!</em></td>
						</tr>
						<?php } ?>
						</tbody>					
					</table>
				
			    	<p class="submit">
						<input tabindex="6" type="submit" name="adrotate_block_submit" class="button-primary" value="Save" />
						<a href="admin.php?page=adrotate-blocks&view=manage" class="button">Cancel</a>
			    	</p>
	
				</form>
	
		   	<?php } ?>
	
			<?php adrotate_credits(); ?>

		<?php } else { ?>
			<?php echo adrotate_error('db_error'); ?>
		<?php }	?>
		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_userstatistics

 Purpose:   User statistics page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_userstatistics() {
	global $wpdb, $adrotate_config, $adrotate_debug;
	
	$user 			= wp_get_current_user();
	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$request		= $_GET['request'];
	$request_id		= $_GET['id'];
	$now 			= current_time('timestamp');
	$in2days 		= $now + 172800;
?>
	<div class="wrap">
	  	<h2>User Statistics</h2>

		<?php if ($message == 'mail_sent') { ?>
			<div id="message" class="updated fade"><p>Your message has been sent</p></div>
		<?php } ?>

		<?php if($view == "" OR $view == "stats") {
			$user_has_ads 	= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = 0 AND `block` = 0 AND `user` = ".$user->ID.";");
			
			if($user_has_ads > 0) {
				adrotate_prepare_user_cache_statistics($user->ID); 
				$result = $wpdb->get_var("SELECT `cache` FROM `".$wpdb->prefix."adrotate_stats_cache` WHERE `user` = ".$user->ID.";");
				$result = unserialize($result);
				
				if($result['total_impressions'] > 0 AND $result['total_clicks'] > 0) {
					$ctr = round((100/$result['total_impressions'])*$result['total_clicks'], 2);
				} else {
					$ctr = 0;
				}
		?>
	
				<h4>Overall</h4>
				
				<table class="widefat" style="margin-top: .5em">					

					<thead>
					<tr>
						<th colspan="2">Overall statistics</th>
						<th colspan="2">The last 8 clicks in the past 24 hours</th>
					</tr>
					</thead>
					
					<tbody>

					<?php if($adrotate_debug == true) { ?>
					<tr>
						<td colspan="4">
							<?php 
							echo "<p><strong>User Statistics from cache</strong><pre>"; 
							print_r($result); 
							echo "</pre></p>"; 
							?>
						</td>
					</tr>
					<?php } ?>
		
				    <tr>
						<th width="10%">General</th>
						<td width="40%"><?php echo $result['ad_amount']; ?> ads, sharing a total of <?php echo $result['total_impressions']; ?> impressions.</td>
						<td rowspan="5" style="border-left:1px #EEE solid;">
						<?php 
						if($result['last_clicks']) {
							foreach($result['last_clicks'] as $last) {
								$bannertitle = $wpdb->get_var("SELECT `title` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$last[bannerid]'");
								echo '<strong>'.date('d-m-Y', $last['timer']) .'</strong> - '. $bannertitle .'<br />';
							}
						} else {
							echo '<em>No recent clicks</em>';
						} ?>
						</td>
					</tr>
				    <tr>
						<th>The best</th>
						<td><?php if($result['thebest']) {?>'<?php echo $result['thebest']['title']; ?>' with <?php echo $result['thebest']['clicks']; ?> clicks.<?php } else { ?>No ad stands out at this time.<?php } ?></td>
					</tr>
				    <tr>
						<th>The worst</th>
						<td><?php if($result['theworst']) {?>'<?php echo $result['theworst']['title']; ?>' with <?php echo $result['theworst']['clicks']; ?> clicks.<?php } else { ?>All ads seem equally bad.<?php } ?></td>
					</tr>
				    <tr>
						<th>Average on all ads</th>
						<td><?php echo $result['total_clicks']; ?> clicks.</td>
					</tr>
				    <tr>
						<th>Click-Through-Rate</th>
						<td><?php echo $ctr; ?>%, based on <?php echo $result['total_impressions']; ?> impressions and <?php echo $result['total_clicks']; ?> clicks.</td>
					</tr>
					</tbody>
				</table>
				
				<h4>Your ads</h4>
				
				<table class="widefat" style="margin-top: .5em">
					<thead>
						<tr>
						<th width="2%"><center>ID</center></th>
						<th width="10%">Show from</th>
						<th width="10%">Show until</th>
						<th>Title</th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>CTR</center></th>
						<th width="15%">Contact</th>
					</tr>
					</thead>
					
					<tbody>
				<?php
				if($result['ads']) {
					foreach($result['ads'] as $ad) {
						$class 			= ('alternate' != $class) ? 'alternate' : '';
						$expiredclass 	= ($ad['endshow'] <= $now OR $ad['endshow'] <= $in2days) ? ' error' : '';
				?>
					    <tr id='banner-<?php echo $ad['id']; ?>' class='<?php echo $class.$expiredclass; ?>'>
							<td><center><?php echo $ad['id'];?></center></td>
							<td><?php echo date("F d, Y", $ad['startshow']);?></td>
							<td><span style="color: <?php echo adrotate_prepare_color($ad['endshow']);?>;"><?php echo date("F d, Y", $ad['endshow']);?></span></td>
							<th><strong><?php echo stripslashes(html_entity_decode($ad['title']));?></strong></th>
							<td><center><?php echo $ad['impressions'];?></center></td>
							<td><center><?php echo $ad['clicks'];?></center></td>
							<?php if($ad['impressions'] == 0) $ad['impressions'] = 1; ?>
							<td><center><?php echo round((100/$ad['impressions']) * $ad['clicks'],2); ?> %</center></td>
							<td><<a href="admin.php?page=adrotate-userstatistics&view=message&request=renew&id=<?php echo $ad['id']; ?>">Renew</a> - <a href="admin.php?page=adrotate-userstatistics&view=message&request=remove&id=<?php echo $ad['id']; ?>">Remove</a> - <a href="admin.php?page=adrotate-userstatistics&view=message&request=report&id=<?php echo $ad['id']; ?>">Report</a></td>
						</tr>
						<?php } ?>
				<?php } else { ?>
					<tr id='no-ads'>
						<th class="check-column">&nbsp;</th>
						<td colspan="7"><em>No ad stats cached! If no stats show up after 24 hours, contact an administrator.</em></td>
					</tr>
				<?php } ?>
					</tbody>
				</table>
			<?php } else { ?>
				<table class="widefat" style="margin-top: .5em">
					<thead>
						<tr>
							<th>Notice</th>
						</tr>
					</thead>
					<tbody>
					    <tr>
							<td>No ads for user. If you feel this to be in error please <a href="admin.php?page=adrotate-userstatistics&view=message&request=issue">contact the site administrator</a>.</td>
						</tr>
					</tbody>
				</table>
			<?php } ?>
			
		<?php } else if($view == "message") { ?>
			
			<?php
			if($request == "renew") {
				$request_name = "Renewal of";
				$example = "- I'd want my ad renewed for 1 year. Quote me!<br />- Renew my ad, but i want the weight set higher.";
			} else if($request == "remove") {
				$request_name = "Removal of";
				$example = "- This ad doesn't perform, please remove it.<br />- The budget is spent, please remove the ad when it expires.";
			} else if($request == "report") {
				$request_name = "Reporting";
				$example = "- The ad is not in the right place. I'd like...<br />- This ad works great for me!!";
			} else if($request == "issue") {
				$request_name = "Complaint or problem";
				$example = "- My ads do not show, what's going on?<br />- Why can't i see any clicks?";
			}
	
			$user = get_userdata($user->ID); 
			if(strlen($user->first_name) < 1) $firstname = $user->user_login;
				else $firstname = $user->first_name;
			if(strlen($user->last_name) < 1) $lastname = ''; 
				else $lastname = $user->last_name;
			if(strlen($user->user_email) < 1) $email = 'No address specified'; 
				else $email = $user->user_email;
			?>
			<form name="request" id="post" method="post" action="admin.php?page=adrotate-userstatistics">
		    	<input type="hidden" name="adrotate_id" value="<?php echo $request_id;?>" />
		    	<input type="hidden" name="adrotate_request" value="<?php echo $request;?>" />
		    	<input type="hidden" name="adrotate_username" value="<?php echo $firstname." ".$lastname;?>" />
		    	<input type="hidden" name="adrotate_email" value="<?php echo $email;?>" />

				<h4>Contact your Publisher</h4>

				<table class="widefat" style="margin-top: .5em">
					<thead>
						<tr>
							<th colspan="3">Put in a request for renewal, removal or report an issue with this ad.</th>
						</tr>
					</thead>
					<tbody>
					    <tr>
							<th width="15%">Subject</th>
							<td colspan="2">
								<?php
								if($request == "issue") {
									echo $request_name;
								} else {
									echo $request_name." ad ".$request_id;
								}
								?>
							</td>
						</tr>
					    <tr>
							<td valign="top"><p><strong>Short message/Reason</strong></p></td>
							<td><textarea tabindex="1" name="adrotate_message" cols="50" rows="5"></textarea></td>
							<td>
								<p><strong>Examples:</strong></p>
								<p><em><?php echo $example; ?></em></p>
							</td>
						</tr>
					</tbody>
				</table>

		    	<p class="submit">
					<input tabindex="2" type="submit" name="adrotate_request_submit" class="button-primary" value="Send" />
					<a href="admin.php?page=adrotate-userstatistics&view=stats" class="button">Cancel</a>
		    	</p>

			</form>
		<?php } ?>

		<br class="clear" />
		<?php adrotate_user_notice(); ?>

	</div>
<?php 
}

/*-------------------------------------------------------------
 Name:      adrotate_statistics

 Purpose:   Admin statistics page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_statistics() {
	global $wpdb, $adrotate_stats, $adrotate_debug;
	
	if($adrotate_stats['tracker'] > 0 OR $adrotate_stats['clicks'] > 0) {
		$clicks = round($adrotate_stats['clicks'] / $adrotate_stats['tracker'], 2); 
	} else { 
		$clicks = 0; 
	}
	
	if($adrotate_stats['total_impressions'] > 0 AND $adrotate_stats['clicks'] > 0) {
		$ctr = round((100/$adrotate_stats['total_impressions'])*$adrotate_stats['clicks'], 2);
	} else {
		$ctr = 0;
	}
?>
	<div class="wrap">
	  	<h2>Statistics</h2>

		<table class="widefat" style="margin-top: .5em">

			<thead>
			<tr>
				<th colspan="2">Overall statistics</th>
				<th colspan="2">The last 8 clicks in the past 24 hours</th>
			</tr>
			</thead>
			
			<tbody>

			<?php if($adrotate_debug == true) { ?>
			<tr>
				<td colspan="4">
					<?php 
					echo "<p><strong>Globalized Statistics from cache</strong><pre>"; 
					print_r($adrotate_stats); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>

		    <tr>
				<th width="10%">General</th>
				<td width="40%"><?php echo $adrotate_stats['banners']; ?> ads, sharing a total of <?php echo $adrotate_stats['total_impressions']; ?> impressions. <?php echo $adrotate_stats['tracker']; ?> ads have tracking enabled.</td>
				<td rowspan="5" style="border-left:1px #EEE solid;">
				<?php 
				if($adrotate_stats['lastclicks']) {
					foreach($adrotate_stats['lastclicks'] as $last) {
						$bannertitle = $wpdb->get_var("SELECT `title` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$last[bannerid]'");
						echo '<strong>'.date('d-m-Y', $last['timer']) .'</strong> - '. $bannertitle .'<br />';
					}
				} else {
					echo '<em>No recent clicks</em>';
				} ?>
				</td>
			</tr>
		    <tr>
				<th>The best</th>
				<td><?php if($adrotate_stats['thebest']) {?>'<?php echo $adrotate_stats['thebest']['title']; ?>' with <?php echo $adrotate_stats['thebest']['clicks']; ?> clicks.<?php } else { ?>No ad stands out at this time.<?php } ?></td>
			</tr>
		    <tr>
				<th>The worst</th>
				<td><?php if($adrotate_stats['theworst']) {?>'<?php echo $adrotate_stats['theworst']['title']; ?>' with <?php echo $adrotate_stats['theworst']['clicks']; ?> clicks.<?php } else { ?>All ads seem equally bad.<?php } ?></td>
			</tr>
		    <tr>
				<th>Average on all ads</th>
				<td><?php echo $clicks; ?> clicks.</td>
			</tr>
		    <tr>
				<th>Click-Through-Rate</th>
				<td><?php echo $ctr; ?>%, based on <?php echo $adrotate_stats['total_impressions']; ?> impressions and <?php echo $adrotate_stats['clicks']; ?> clicks.</td>
			</tr>
			</tbody>
		</table>

		<br class="clear" />
		<?php adrotate_notice(); ?>

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
	global $wpdb;

	$adrotate_config 	= get_option('adrotate_config');
	$adrotate_crawlers 	= get_option('adrotate_crawlers');
	$adrotate_roles		= get_option('adrotate_roles');
	$adrotate_debug		= get_option('adrotate_debug');
	
	$crawlers 			= implode(', ', $adrotate_crawlers);
	$notification_mails	= implode(', ', $adrotate_config['notification_email']);
	$advertiser_mails	= implode(', ', $adrotate_config['advertiser_email']);
	$message 			= $_GET['message'];
?>
	<div class="wrap">
	  	<h2>Settings</h2>

		<?php if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p>Settings saved</p></div>
		<?php } else if ($message == 'role_add') { ?>
			<div id="message" class="updated fade"><p>AdRotate client role added</p></div>
		<?php } else if ($message == 'role_remove') { ?>
			<div id="message" class="updated fade"><p>AdRotate client role removed</p></div>
		<?php } else if ($message == 'db_optimized') { ?>
			<div id="message" class="updated fade"><p>Database optimized</p></div>
		<?php } else if ($message == 'db_repaired') { ?>
			<div id="message" class="updated fade"><p>Database repaired</p></div>
		<?php } else if ($message == 'db_timer') { ?>
			<div id="message" class="updated fade"><p>Database can only be optimized or repaired once every 24 hours</p></div>
		<?php } ?>

	  	<form name="settings" id="post" method="post" action="admin.php?page=adrotate-settings">

	    	<table class="form-table">
			<tr>
				<td colspan="2"><h3>Access Rights</h3></td>
			</tr>

			<tr>
				<th valign="top">Advertiser Statistics Page</th>
				<td>
					<select name="adrotate_userstatistics">
						<?php wp_dropdown_roles($adrotate_config['userstatistics']); ?>
					</select> <span class="description">Role to allow users/advertisers to see their statistics page.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Global Statistics Page</th>
				<td>
					<select name="adrotate_globalstatistics">
						<?php wp_dropdown_roles($adrotate_config['globalstatistics']); ?>
					</select> <span class="description">Role to review the global statistics.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Manage/Add/Edit Ads</th>
				<td>
					<select name="adrotate_ad_manage">
						<?php wp_dropdown_roles($adrotate_config['ad_manage']); ?>
					</select> <span class="description">Role to see and add/edit ads.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Delete/Reset Ads</th>
				<td>
					<select name="adrotate_ad_delete">
						<?php wp_dropdown_roles($adrotate_config['ad_delete']); ?>
					</select> <span class="description">Role to delete ads and reset stats.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Manage/Add/Edit Groups</th>
				<td>
					<select name="adrotate_group_manage">
						<?php wp_dropdown_roles($adrotate_config['group_manage']); ?>
					</select> <span class="description">Role to see and add/edit groups.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Delete Groups</th>
				<td>
					<select name="adrotate_group_delete">
						<?php wp_dropdown_roles($adrotate_config['group_delete']); ?>
					</select> <span class="description">Role to delete groups.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Manage/Add/Edit Blocks</th>
				<td>
					<select name="adrotate_block_manage">
						<?php wp_dropdown_roles($adrotate_config['block_manage']); ?>
					</select> <span class="description">Role to see and add/edit blocks.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Delete Blocks</th>
				<td>
					<select name="adrotate_block_delete">
						<?php wp_dropdown_roles($adrotate_config['block_delete']); ?>
					</select> <span class="description">Role to delete blocks.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">AdRotate Advertisers</th>
				<td>
					<?php if($adrotate_roles == 0) { ?>
					<input type="submit" id="post-role-submit" name="adrotate_role_add_submit" value="Create Role" class="button-secondary" />
					<?php } else { ?>
					<input type="submit" id="post-role-submit" name="adrotate_role_remove_submit" value="Remove Role" class="button-secondary" onclick="return confirm('You are about to remove access rights from the Author, Editor and Administrator role aswell as remove the AdRotate Clients role.\n\nThis may lead to users not being able to access their ads statistics!\n\n\'OK\' to continue, \'Cancel\' to stop.')" />
					<?php } ?><br />
					<span class="description">This role has no capabilities unless you assign them using the above options. Obviously you should use this with care.<br />
					This type of user is NOT required to use AdRotate or it's stats. It merely helps you to seperate advertisers from regular subscribers without giving them too much access to your dashboard.</span>
				</td>
			</tr>

			<?php if($adrotate_debug == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] Globalized Config</strong><pre>"; 
					print_r($adrotate_config); 
					echo "</pre></p>"; 
					echo "<p><strong>[DEBUG] Current User Capabilities</strong><pre>"; 
					print_r(get_option('wp_user_roles')); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>

			<tr>
				<td colspan="2"><h3>Email Notifications and Advertiser Messages</h3></td>
			</tr>

			<tr>
				<th valign="top">Notifications</th>
				<td>
					<textarea name="adrotate_notification_email" cols="90" rows="3"><?php echo $notification_mails; ?></textarea><br />
					<span class="description">A comma separated list of email addresses. Maximum of 5 addresses. Keep this list to a minimum!<br />
					Messages are sent once every 24 hours when needed. If this field is empty the function will be disabled.</span>
				</td>
			</tr>
			<tr>
				<th valign="top">Advertiser Messages</th>
				<td>
					<textarea name="adrotate_advertiser_email" cols="90" rows="2"><?php echo $advertiser_mails; ?></textarea><br />
					<span class="description">Maximum of 2 addresses. Comma seperated. This field cannot be empty!</span>
				</td>
			</tr>
			
			<tr>
				<td colspan="2"><h3>User-Agent Filter</h3></td>
			</tr>
			
			<tr>
				<th valign="top">List of keywords to filter</th>
				<td>
					<textarea name="adrotate_crawlers" cols="90" rows="5"><?php echo $crawlers; ?></textarea><br />
					<span class="description">A comma separated list of keywords. Filter out bots/crawlers/user-agents.<br />
					Keep in mind that this might give false positives. The word 'google' also matches 'googlebot', so be careful!<br />
					Additionally to the list specified here, empty User-Agents are blocked as well. (Learn more about <a href="http://en.wikipedia.org/wiki/User_agent" title="User Agents" target="_blank">user-agents</a>.)</span>
				</td>
			</tr>
			
			<tr>
				<td colspan="2"><h3>Maintenance</h3></td>
			</tr>

			<?php if($adrotate_debug == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] List of tables</strong><pre>";
					$tables = adrotate_list_tables();
					print_r($tables); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>
			
			<tr>
				<td colspan="2"><span class="description">NOTE: The below functions are intented to be used to OPTIMIZE and/or REPAIR your database. Always always make a backup! These functions are to be used when you feel or notice your database is slow, unresponsive and sluggish. Or if you notice garbeled characters when editing ads, groups or blocks.</span></td>
			</tr>
			
			<tr>
				<th valign="top">Optimize database</th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_db_optimize_submit" value="Optimize Database" class="button-secondary" onclick="return confirm('You are about to optimize the AdRotate database.\n\nDid you make a backup of your database?\n\nThis may take a moment and may cause your website to respond slow temporarily!\n\n\'OK\' to continue, \'Cancel\' to stop.')" /><br />
					<span class="description">Cleans up overhead data in the AdRotate tables.<br />
					Overhead data is accumulated garbage resulting from many changes you've made. This can vary from nothing to hundreds of KiB of data.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Repair database</th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_db_repair_submit" value="Repair Database" class="button-secondary" onclick="return confirm('You are about to repair the AdRotate database.\n\nDid you make a backup of your database?\n\nThis may take a moment and may cause your website to respond slow temporarily!\n\n\'OK\' to continue, \'Cancel\' to stop.')" /><br />
					<span class="description">In some cases the database tables become corrupted or otherwise unusable. Use this button when you notice garbeled or otherwise unreadable icons/characters when editing ads, groups or blocks.</span>
				</td>
			</tr>

			<tr>
				<td colspan="2"><span class="description">DISCLAIMER: If for any reason your data is lost, damaged or otherwise becomes unusable in any way or by any means in whichever way i will not take resonsibility. You should always have a backup of your database. These functions do NOT destroy data. If data is lost, damaged or unusable, your database likely was beyond repair already. Claiming it worked before clicking these buttons is not a valid point in any case.</span></td>
			</tr>

			<tr>
				<td colspan="2"><h3>Miscellaneous</h3></td>
			</tr>
			
			<tr>
				<th valign="top">Media Browser</th>
				<td><input type="checkbox" name="adrotate_browser" <?php if($adrotate_config['browser'] == 'Y') { ?>checked="checked" <?php } ?> /> <span class="description">Include images and flash files from the media browser in the image selector.</span></td>
			</tr>
			<tr>
				<th valign="top">Widget alignment</th>
				<td><input type="checkbox" name="adrotate_widgetalign" <?php if($adrotate_config['widgetalign'] == 'Y') { ?>checked="checked" <?php } ?> /> <span class="description">Check this box if your widgets do not align in your themes sidebar. (Does not always help!)</span></td>
			</tr>
			<tr>
				<th valign="top">Credits</th>
				<td><input type="checkbox" name="adrotate_credits" <?php if($adrotate_config['credits'] == 'Y') { ?>checked="checked" <?php } ?> /> <span class="description">Show a simple token that you're using AdRotate in the themes Meta part.</span></td>
			</tr>
			<tr>
				<th valign="top">Developer Debug</th>
				<td><input type="checkbox" name="adrotate_debug" <?php if($adrotate_debug == true) { ?>checked="checked" <?php } ?> /> <span class="description">Leave this option off for normal use!</span></td>
			</tr>
	    	</table>
	    	
		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="Update Options" />
		    </p>
		</form>
	</div>
<?php 
}
?>