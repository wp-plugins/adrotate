<?php
/*
Plugin Name: AdRotate
Plugin URI: http://www.adrotateplugin.com
Description: The very best and most convenient way to publish your ads.
Author: Arnan de Gans
Version: 3.5.1
Author URI: http://meandmymac.net/
License: GPL2
*/

/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*--- AdRotate values and config ----------------------------*/
define("ADROTATE_VERSION", 352);
define("ADROTATE_DB_VERSION", 7);
$adrotate_config 				= get_option('adrotate_config');
$adrotate_crawlers 				= get_option('adrotate_crawlers');
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
/*-----------------------------------------------------------*/

/*--- Load functions ----------------------------------------*/
register_activation_hook(__FILE__, 'adrotate_activate');
register_deactivation_hook(__FILE__, 'adrotate_deactivate');
register_uninstall_hook(__FILE__, 'adrotate_uninstall');
if($adrotate_version < ADROTATE_VERSION) adrotate_core_upgrade();
if($adrotate_db_version < ADROTATE_DB_VERSION) adrotate_database_upgrade();

adrotate_check_config();
adrotate_clean_trackerdata();
/*-----------------------------------------------------------*/

/*--- Front end ---------------------------------------------*/
add_shortcode('adrotate', 'adrotate_shortcode');
add_action('widgets_init', create_function('', 'return register_widget("adrotate_widgets");'));
add_action('wp_meta', 'adrotate_meta');
/*-----------------------------------------------------------*/

/*--- Dashboard ---------------------------------------------*/
add_action('admin_menu', 'adrotate_dashboard');
add_action('admin_notices','adrotate_notifications_dashboard');
add_action('wp_dashboard_setup', 'adrotate_dashboard_widget');
/*-----------------------------------------------------------*/

/*--- Core - Cron -------------------------------------------*/
add_action('adrotate_ad_notification', 'adrotate_mail_notifications');
add_filter('cron_schedules', 'adrotate_reccurences');
/*-----------------------------------------------------------*/

/*--- Internal redirects ------------------------------------*/
if(isset($_POST['adrotate_ad_submit'])) 				add_action('init', 'adrotate_insert_input');
if(isset($_POST['adrotate_group_submit'])) 				add_action('init', 'adrotate_insert_group');
if(isset($_POST['adrotate_block_submit'])) 				add_action('init', 'adrotate_insert_block');
if(isset($_POST['adrotate_action_submit'])) 			add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_options_submit'])) 			add_action('init', 'adrotate_options_submit');
if(isset($_POST['adrotate_request_submit'])) 			add_action('init', 'adrotate_mail_message');
if(isset($_POST['adrotate_notification_test_submit'])) 	add_action('init', 'adrotate_mail_test');
if(isset($_POST['adrotate_advertiser_test_submit'])) 	add_action('init', 'adrotate_mail_test');
if(isset($_POST['adrotate_role_add_submit']))			add_action('init', 'adrotate_prepare_roles');
if(isset($_POST['adrotate_role_remove_submit'])) 		add_action('init', 'adrotate_prepare_roles');
if(isset($_POST['adrotate_db_optimize_submit'])) 		add_action('init', 'adrotate_optimize_database');
if(isset($_POST['adrotate_db_cleanup_submit'])) 		add_action('init', 'adrotate_cleanup_database');
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
	add_submenu_page('adrotate', 'AdRotate > Advertiser Reports', 'Advertiser Reports', 'adrotate_advertiser_report', 'adrotate-advertiser-report', 'adrotate_advertiser_report');
	add_submenu_page('adrotate', 'AdRotate > Global Reports', 'Global Reports', 'adrotate_global_report', 'adrotate-global-report', 'adrotate_global_report');
	add_submenu_page('adrotate', 'AdRotate > Settings', 'Settings', 'manage_options', 'adrotate-settings', 'adrotate_options');
}

/*-------------------------------------------------------------
 Name:      adrotate_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage() {
	global $wpdb, $adrotate_debug;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$ad_edit_id 	= $_GET['ad'];
	$now 			= current_time('timestamp');
	$in2days 		= $now + 172800;
	$in7days 		= $now + 604800;
	
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
	.row_inactive {
		background-color:#ebf3fa;
		border-color:#466f82;
	}
	.stats_large {
		display: block;
		margin-bottom: 10px;
		margin-top: 10px;
		text-align: center;
		font-weight: bold;
	}
	.number_large {
		margin: 20px;
		font-size: 28px;
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
					<?php if($ad_edit_id) { ?>
					| <a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=report&ad='.$ad_edit_id;?>">Report</a>
					<?php } ?>
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
						<th width="12%">Show from</th>
						<th width="12%">Show until</th>
						<th>Title</th>
						<th width="5%"><center>Weight</center></th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="5%"><center>CTR</center></th>
					</tr>
	  			</thead>
	  			<tbody>
				<?php
				$banners = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'manual' OR `type` = 'error' ORDER BY ".$order);
				if ($banners) {
					foreach($banners as $banner) {
						$today = gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
						$stats = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$banner->id';");
						$stats_today = $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$banner->id' AND `thetime` = '$today';");

						// Sort out CTR
						if($stats->impressions == 0) $ctrimpressions = 0.001;
							else $ctrimpressions = $stats->impressions;
						if($stats->clicks == 0) $ctrclicks = 0.001;
							else $ctrclicks = $stats->clicks;
						$ctr = round((100/$ctrimpressions)*$ctrclicks,2);						

						// Prevent gaps in display
						if($stats->impressions == 0) 		$stats->impressions 		= 0;
						if($stats->clicks == 0) 			$stats->clicks 				= 0;
						if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
						if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;
						
						$groups	= $wpdb->get_results("
							SELECT 
								`".$wpdb->prefix."adrotate_groups`.`name` 
							FROM 
								`".$wpdb->prefix."adrotate_groups`, 
								`".$wpdb->prefix."adrotate_linkmeta` 
							WHERE 
								`".$wpdb->prefix."adrotate_linkmeta`.`ad` = '".$banner->id."'
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
							$errorclass = ' row_error';
						} else {
							$errorclass = '';
						}
	
						if($banner->endshow <= $now OR $banner->endshow <= $in2days) {
							$expiredclass = ' row_urgent';
						} else {
							$expiredclass = '';
						}
	
						if($banner->active == 'no') {
							$inactiveclass = ' row_inactive';
						} else {
							$inactiveclass = '';
						}

						if($class != 'alternate') {
							$class = 'alternate';
						} else {
							$class = '';
						}
						?>
					    <tr id='adrotateindex' class='<?php echo $class.$expiredclass.$errorclass.$inactiveclass; ?>'>
							<th class="check-column"><input type="checkbox" name="bannercheck[]" value="<?php echo $banner->id; ?>" /></th>
							<td><center><?php echo $banner->id;?></center></td>
							<td><?php echo date("F d, Y", $banner->startshow);?></td>
							<td><span style="color: <?php echo adrotate_prepare_color($banner->endshow);?>;"><?php echo date("F d, Y", $banner->endshow);?></span></td>
							<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=edit&ad='.$banner->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($banner->title));?></a></strong> - <a href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate&view=report&ad='.$banner->id;?>" title="Report">Report</a><?php if($groups) echo '<br /><em style="color:#999">'.$grouplist.'</em>'; ?></td>
							<td><center><?php echo $banner->weight; ?></center></td>
							<td><center><?php echo $stats->impressions; ?></center></td>
							<td><center><?php echo $stats_today->impressions; ?></center></td>
							<?php if($banner->tracker == "Y") { ?>
							<td><center><?php echo $stats->clicks; ?></center></td>
							<td><center><?php echo $stats_today->clicks; ?></center></td>
							<td><center><?php echo $ctr; ?> %</center></td>
							<?php } else { ?>
							<td><center>--</center></td>
							<td><center>--</center></td>
							<td><center>--</center></td>
							<?php } ?>
						</tr>
		 			<?php } ?>
		 		<?php } else { ?>
					<tr id='no-groups'>
						<th class="check-column">&nbsp;</th>
						<td colspan="10"><em>No ads created yet!</em></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			
			</form>

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
					$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate` (`title`, `bannercode`, `thetime`, `updated`, `author`, `active`, `startshow`, `endshow`, `image`, `link`, `tracker`, `maxclicks`, `maxshown`, `targetclicks`, `targetimpressions`, `type`, `weight`) VALUES ('', '', '$startshow', '$startshow', '$userdata->user_login', 'no', '$startshow', '$endshow', '', '', 'N', 0, 0, 0, 0, 'empty', 6);");
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
			
			if($ad_edit_id) {
				// Errors
				if(strlen($edit_banner->bannercode) < 1 AND $edit_banner->type != 'empty') echo '<div class="error"><p>The AdCode cannot be empty!</p></div>';
				if($edit_banner->tracker == 'N' AND strlen($edit_banner->link) < 1 AND $saved_user > 0) echo '<div class="error"><p>You\'ve set an advertiser but didn\'t enable clicktracking!</p></div>';
				if($edit_banner->tracker == 'Y' AND strlen($edit_banner->link) < 1) echo '<div class="error"><p>You\'ve enabled clicktracking but didn\'t provide an url in the url field!</p></div>';
				if($edit_banner->tracker == 'N' AND strlen($edit_banner->link) > 0) echo '<div class="error"><p>You didn\'t enable clicktracking but you did use the url field!</p></div>';
				if(!preg_match("/%link%/i", $edit_banner->bannercode) AND $edit_banner->tracker == 'Y') echo '<div class="error"><p>You didn\'t use %link% in your AdCode but did enable clicktracking!</p></div>';
				if(preg_match("/%link%/i", $edit_banner->bannercode) AND $edit_banner->tracker == 'N') echo '<div class="error"><p>You\'ve %link% in your AdCode but didn\'t enable clicktracking!</p></div>';
				if(!preg_match("/%image%/i", $edit_banner->bannercode) AND $edit_banner->image != '') echo '<div class="error"><p>You didn\'t use %image% in your AdCode but did select an image!</p></div>';
				if(preg_match("/%image%/i", $edit_banner->bannercode) AND $edit_banner->image == '') echo '<div class="error"><p>You did use %image% in your AdCode but did not select one in the dropdown menu!</p></div>';
				
				// Notices
				if($edit_banner->active == 'no' AND $edit_banner->type != "empty") echo '<div class="updated"><p>This ad has been disabled and does not rotate on your site!</p></div>';
				if($edit_banner->endshow < $now) echo '<div class="updated"><p>This ad is expired and currently not shown on your website!</p></div>';
				else if($edit_banner->endshow < $in2days) echo '<div class="updated"><p>This ad will expire in less than 2 days!</p></div>';
				else if($edit_banner->endshow < $in7days) echo '<div class="updated"><p>This ad will expire in less than 7 days!</p></div>';
			}
			?>
			
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
								$userdata = get_userdata($id->ID); 
								if(strlen($userdata->first_name) < 1) $firstname = $userdata->user_login;
									else $firstname = $userdata->first_name;
								if(strlen($userdata->last_name) < 1) $lastname = ''; 
									else $lastname = $userdata->last_name;
							?>
								<option value="<?php echo $id->ID; ?>"<?php if($saved_user == $id->ID) { echo ' selected'; } ?>><?php echo $firstname; ?> <?php echo $lastname; ?></option>
							<?php } ?>
							</select><br />
					        <em>Must be a registered user on your site with appropriate access roles.</em>
						</td>
			      	</tr>
			      	<tr>
				        <th valign="top">Clicktracking:</th>
				        <td colspan="3">
				        	Enable? <input tabindex="11" type="checkbox" name="adrotate_tracker" <?php if($edit_banner->tracker == 'Y') { ?>checked="checked" <?php } ?> /> url: <input tabindex="12" name="adrotate_link" type="text" size="80" class="search-input" value="<?php echo $edit_banner->link;?>" /><br />
					        <em>Use %link% in the adcode instead of the actual url.<br />
					        For a random seed you can use %random%. A generated timestamp you can use.</em>
				        </td>
			      	</tr>
			      	<tr>
				        <th valign="top">Banner image:</th>
				        <td colspan="3"><select tabindex="13" name="adrotate_image" style="min-width: 200px;">
	   						<option value="none">No image or remote</option>
							<?php echo adrotate_folder_contents($edit_banner->image); ?>
						</select>
						<br /><em>Use %image% in the code. Accepted files are: jpg, jpeg, gif, png, swf and flv.</em></td>
			      	</tr>
			      	<tr>
					    <th valign="top">Weight:</th>
				        <td colspan="3">
				        	<input type="radio" tabindex="14" name="adrotate_weight" value="2" <?php if($edit_banner->weight == "2") { echo 'checked'; } ?> /> 2, Barely visible<br />
				        	<input type="radio" tabindex="15" name="adrotate_weight" value="4" <?php if($edit_banner->weight == "4") { echo 'checked'; } ?> /> 4, Less than average<br />
				        	<input type="radio" tabindex="16" name="adrotate_weight" value="6" <?php if($edit_banner->weight == "6") { echo 'checked'; } ?> /> 6, Normal coverage<br />
				        	<input type="radio" tabindex="17" name="adrotate_weight" value="8" <?php if($edit_banner->weight == "8") { echo 'checked'; } ?> /> 8, More than average<br />
				        	<input type="radio" tabindex="18" name="adrotate_weight" value="10" <?php if($edit_banner->weight == "10") { echo 'checked'; } ?> /> 10, Best visibility
						</td>
					</tr>
			      	<tr>
					    <th>Maximum Clicks:</th>
				        <td colspan="3">Disable after <input tabindex="19" name="adrotate_maxclicks" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->maxclicks;?>" /> clicks! <em>Leave empty or 0 to skip this.</em></td>
					</tr>
			      	<tr>
					    <th>Maximum Impressions:</th>
				        <td colspan="3">Disable after <input tabindex="20" name="adrotate_maxshown" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->maxshown;?>" /> impressions! <em>Leave empty or 0 to skip this.</em></td>
					</tr>
			      	<tr>
				        <th valign="top">Expected Clicks:</th>
				        <td colspan="3">
				        	<input tabindex="21" name="adrotate_targetclicks" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->targetclicks;?>" /> <em>Set a target or milestone for clicks. Shows in the graph. Leave empty or 0 to skip this.</em>
				        </td>
			      	</tr>
			      	<tr>
				        <th valign="top">Expected impressions:</th>
				        <td colspan="3">
				        	<input tabindex="22" name="adrotate_targetimpressions" type="text" size="5" class="search-input" autocomplete="off" value="<?php echo $edit_banner->targetimpressions;?>" /> <em>Set a target or milestone for impressions. Shows in the graph. Leave empty or 0 to skip this.</em>
				        </td>
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
	
				<?php if($groups) { ?>
				<h3>Select Groups</h3>

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
	
		    	<p class="submit">
					<input tabindex="23" type="submit" name="adrotate_ad_submit" class="button-primary" value="Save ad" />
					<a href="admin.php?page=adrotate&view=manage" class="button">Cancel</a>
		    	</p>
	
			</form>

		   	<?php } else if($view == "report") { ?>

				<h3>This ads performance</h3>
				
				<?php
					$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
					$banner 		= $wpdb->get_row("SELECT `title`, `tracker` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$ad_edit_id';");
					$stats 			= $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$ad_edit_id';");
					$stats_today 	= $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$ad_edit_id' AND `thetime` = '$today';");

					// Sort out CTR
					if($stats->impressions == 0) $ctrimpressions = 0.001;
						else $ctrimpressions = $stats->impressions;
					if($stats->clicks == 0) $ctrclicks = 0.001;
						else $ctrclicks = $stats->clicks;
					$ctr = round((100/$ctrimpressions)*$ctrclicks,2);						
	
					// Prevent gaps in display
					if($stats->impressions == 0) 		$stats->impressions 		= 0;
					if($stats->clicks == 0) 			$stats->clicks 				= 0;
					if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
					if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;
			
					if($adrotate_debug['stats'] == true) {
						echo "<p><strong>[DEBUG] Ad Stats (all time)</strong><pre>";
						$memory = (memory_get_usage() / 1024 / 1024);
						echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
						$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
						echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
						print_r($stats); 
						echo "</pre></p>"; 
						echo "<p><strong>[DEBUG] Ad Stats (today)</strong><pre>";
						print_r($stats_today); 
						echo "</pre></p>"; 
					}	
		
				?>
				
		    	<table class="widefat" style="margin-top: .5em">
					<thead>
					<tr>
						<th colspan="5" bgcolor="#DDD">Statistics for '<?php echo $banner->title; ?>'</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <td width="20%"><div class="stats_large">Impressions<br /><div class="number_large"><?php echo $stats->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks<br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $stats->clicks; } else { echo '--'; } ?></div></div></td>
				        <td width="20%"><div class="stats_large">Impressions today<br /><div class="number_large"><?php echo $stats_today->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks today<br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $stats_today->clicks; } else { echo '--'; } ?></div></div></td>
				        <td width="20%"><div class="stats_large">CTR<br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $ctr.' %'; } else { echo '--'; } ?></div></div></td>
			      	</tr>
			      	<tr>
				        <th colspan="5">
				        	<?php
				        	$adstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$ad_edit_id' GROUP BY `thetime` ASC LIMIT 21;");
				        	$target = $wpdb->get_row("SELECT `targetclicks`, `targetimpressions` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$ad_edit_id';");
							if($adstats) {

								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] 21 days (Or as much as is available) Ad stats</strong><pre>"; 
									print_r($adstats); 
									echo "</pre></p>"; 
								}

								foreach($adstats as $result) {
									if($result->clicks == null) $result->clicks = '0';
									if($result->impressions == null) $result->impressions = '0';
									
									$clicks_array[date("d-m-Y", $result->thetime)] = $result->clicks;
									$impressions_array[date("d-m-Y", $result->thetime)] = $result->impressions;
								}
								
								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] Found clicks as presented to PHPGraphLib</strong><pre>"; 
									print_r($clicks_array); 
									echo "</pre></p>"; 
									echo "<p><strong>[DEBUG] Found impressions as presented to PHPGraphLib</strong><pre>"; 
									print_r($impressions_array); 
									echo "</pre></p>"; 
								}

								$impressions_title = urlencode(serialize('Impressions over the past 21 days'));
								$impressions_target = urlencode(serialize($target->targetimpressions));
								$impressions_array = urlencode(serialize($impressions_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_single_ad.php?title=$impressions_title&target=$impressions_target&data=$impressions_array\" />";

								if($banner->tracker == "Y") {
									$clicks_title = urlencode(serialize('Clicks over the past 21 days'));
									$clicks_target = urlencode(serialize($target->targetclicks));
									$clicks_array = urlencode(serialize($clicks_array));
									echo "<img src=\"../wp-content/plugins/adrotate/library/graph_single_ad.php?title=$clicks_title&target=$clicks_target&data=$clicks_array\" />";
								}
							} else {
								echo "No data to show!";
							} 
							?>
				        </th>
			      	</tr>
			      	<tr>
						<td colspan="5">
							<b>Note:</b> <em>All statistics are indicative. They do not nessesarily reflect results counted by other parties.</em><br />
							Visual graphing kindly provided using <a href="http://www.ebrueggeman.com/" target="_blank">PHPGraphLib by Elliot Brueggeman</a>
						</td>
			      	</tr>
					</tbody>
				</table>

		   	<?php } ?>

			<br class="clear" />

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
	global $wpdb, $adrotate_debug;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$group_edit_id 	= $_GET['group'];
	?>

	<style type="text/css" media="screen">
	.stats_large {
		display: block;
		margin-bottom: 10px;
		margin-top: 10px;
		text-align: center;
		font-weight: bold;
	}
	.number_large {
		margin: 20px;
		font-size: 28px;
	}
	</style>

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
					<?php if($group_edit_id) { ?>
					| <a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=report&group='.$group_edit_id;?>">Report</a>
					<?php } ?>
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
						<th width="5%"><center>Ads</center></th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="15%"><center>Code</center></th>
						<th width="8%"><center>Fallback</center></th>
					</tr>
		  			</thead>
					<tbody>
		  			
					<?php $groups = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "adrotate_groups` WHERE `name` != '' ORDER BY `id`;");
					if ($groups) {
						foreach($groups as $group) {
							$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
							$stats 			= $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `group` = '$group->id';");
							$stats_today	= $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `group` = '$group->id' AND `thetime` = '$today';");
	
							// Prevent gaps in display
							if($stats->impressions == 0) 		$stats->impressions 		= 0;
							if($stats->clicks == 0) 			$stats->clicks 				= 0;
							if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
							if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;

							$ads_in_group = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = ".$group->id." AND `block` = 0;");
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <trclass='<?php echo $class; ?>'>
								<th class="check-column"><input type="checkbox" name="groupcheck[]" value="<?php echo $group->id; ?>" /></th>
								<td><center><?php echo $group->id;?></center></td>
								<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=edit&group='.$group->id;?>" title="Edit"><?php echo $group->name;?></a></strong><br /><a href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-groups&view=report&group='.$group->id;?>" title="Report">Report</a></td>
								<td><center><?php echo $ads_in_group;?></center></td>
								<td><center><?php echo $stats->impressions;?></center></td>
								<td><center><?php echo $stats_today->impressions;?></center></td>
								<td><center><?php echo $stats->clicks;?></center></td>
								<td><center><?php echo $stats_today->clicks;?></center></td>
								<td><center>[adrotate group="<?php echo $group->id; ?>"]</center></td>
								<td><center><?php if($group->fallback == 0) { echo "Not set"; } else { echo $group->fallback; } ?></center></td>
							</tr>
			 			<?php } ?>
					<?php } else { ?>
					<tr>
						<th class="check-column">&nbsp;</th>
						<td colspan="9"><em>No groups created!</em></td>
					</tr>
					<?php } ?>
		 			</tbody>
				</table>
			</form>

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
							<th colspan="4">Usage</th>
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
				
					<h3>Select Ads</h3>

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
								$stats = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `ad` = '$ad->id';");

								// Prevent gaps in display
								if($stats->impressions == 0) 		$stats->impressions 		= 0;
								if($stats->clicks == 0) 			$stats->clicks 				= 0;

								$class = ('alternate' != $class) ? 'alternate' : ''; ?>
							    <tr class='<?php echo $class; ?>'>
									<th class="check-column"><input type="checkbox" name="adselect[]" value="<?php echo $ad->id; ?>" <?php if(in_array($ad->id, $meta_array)) echo "checked"; ?> /></th>
									<td><?php echo $ad->id; ?> - <strong><?php echo $ad->title; ?></strong></td>
									<td><center><?php echo $stats->impressions; ?></center></td>
									<td><center><?php if($ad->tracker == 'Y') { echo $stats->clicks; } else { ?>--<?php } ?></center></td>
									<td><center><?php echo $ad->weight; ?></center></td>
									<td><span style="color: <?php echo adrotate_prepare_color($ad->endshow);?>;"><?php echo date("F d, Y", $ad->endshow); ?></span></td>
								</tr>
							<?php unset($stats);?>
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

		   	<?php } else if($view == "report") { ?>

				<h3>This groups performance</h3>

				<?php
					$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
					$title		 	= $wpdb->get_var("SELECT `name` FROM `".$wpdb->prefix."adrotate_groups` WHERE `id` = '$group_edit_id';");
					$stats 			= $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `group` = '$group_edit_id';");
					$stats_today 	= $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `group` = '$group_edit_id' AND `thetime` = '$today';");

					// Sort out CTR
					if($stats->impressions == 0) $ctrimpressions = 0.001;
						else $ctrimpressions = $stats->impressions;
					if($stats->clicks == 0) $ctrclicks = 0.001;
						else $ctrclicks = $stats->clicks;
					$ctr = round((100/$ctrimpressions)*$ctrclicks, 2);						
	
					// Prevent gaps in display
					if($stats->impressions == 0) 		$stats->impressions 		= 0;
					if($stats->clicks == 0) 			$stats->clicks 				= 0;
					if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
					if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;
			
					if($adrotate_debug['stats'] == true) {
						echo "<p><strong>[DEBUG] Group (all time)</strong><pre>";
						$memory = (memory_get_usage() / 1024 / 1024);
						echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
						$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
						echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
						print_r($stats); 
						echo "</pre></p>"; 
						echo "<p><strong>[DEBUG] Group (today)</strong><pre>";
						print_r($stats_today); 
						echo "</pre></p>"; 
					}	
		
				?>
				
		    	<table class="widefat" style="margin-top: .5em">
					<thead>
					<tr>
						<th colspan="5" bgcolor="#DDD">Statistics for '<?php echo $title; ?>'</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <td width="20%"><div class="stats_large">Impressions<br /><div class="number_large"><?php echo $stats->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks<br /><div class="number_large"><?php echo $stats->clicks; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Impressions today<br /><div class="number_large"><?php echo $stats_today->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks today<br /><div class="number_large"><?php echo $stats_today->clicks; ?></div></div></td>
				        <td width="20%"><div class="stats_large">CTR<br /><div class="number_large"><?php echo $ctr.' %'; ?></div></div></td>
			      	</tr>
			      	<tr>
				        <th colspan="5">
				        	<?php
				        	$groupstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `group` = '$group_edit_id' GROUP BY `thetime` ASC LIMIT 21;");
							if($groupstats) {

								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] 21 days (Or as much as is available) Group stats</strong><pre>"; 
									print_r($groupstats); 
									echo "</pre></p>"; 
								}

								foreach($groupstats as $result) {
									if($result->clicks == null) $result->clicks = '0';
									if($result->impressions == null) $result->impressions = '0';
									
									$clicks_array[date("d-m-Y", $result->thetime)] = $result->clicks;
									$impressions_array[date("d-m-Y", $result->thetime)] = $result->impressions;
								}
								
								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] Found clicks as presented to PHPGraphLib</strong><pre>"; 
									print_r($clicks_array); 
									echo "</pre></p>"; 
									echo "<p><strong>[DEBUG] Found impressions as presented to PHPGraphLib</strong><pre>"; 
									print_r($impressions_array); 
									echo "</pre></p>"; 
								}

								$impressions_title = urlencode(serialize('Impressions over the past 21 days'));
								$impressions_array = urlencode(serialize($impressions_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_group.php?title=$impressions_title&data=$impressions_array\" />";

								$clicks_title = urlencode(serialize('Clicks over the past 21 days'));
								$clicks_array = urlencode(serialize($clicks_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_group.php?title=$clicks_title&data=$clicks_array\" />";
							} else {
								echo "No data to show!";
							} 
							?>
				        </th>
			      	</tr>
			      	<tr>
				        <td colspan="5"><b>Note:</b> <em>All statistics are indicative. They do not nessesarily reflect results counted by other parties.</em></td>
			      	</tr>
					</tbody>
				</table>

		   	<?php } ?>
	
			<br class="clear" />
		
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
	global $wpdb, $adrotate_debug;

	$message 		= $_GET['message'];
	$view 			= $_GET['view'];
	$block_edit_id 	= $_GET['block'];
	?>

	<style type="text/css" media="screen">
	.stats_large {
		display: block;
		margin-bottom: 10px;
		margin-top: 10px;
		text-align: center;
		font-weight: bold;
	}
	.number_large {
		margin: 20px;
		font-size: 28px;
	}
	</style>

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
					<?php if($block_edit_id) { ?>
					| <a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=report&block='.$block_edit_id;?>">Report</a>
					<?php } ?>
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
						<th width="5%"><center>Groups</center></th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="15%"><center>Code</center></th>
					</tr>
		  			</thead>

					<tbody>
		  			
					<?php $blocks = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix . "adrotate_blocks` WHERE `name` != '' ORDER BY `id`;");
					if ($blocks) {
						foreach($blocks as $block) {
							$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
							$stats 			= $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `block` = '$block->id';");
							$stats_today	= $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `block` = '$block->id' AND `thetime` = '$today';");
	
							// Prevent gaps in display
							if($stats->impressions == 0) 		$stats->impressions 		= 0;
							if($stats->clicks == 0) 			$stats->clicks 				= 0;
							if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
							if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;

							$groups_in_block = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `block` = ".$block->id.";");
							$class = ('alternate' != $class) ? 'alternate' : ''; ?>
						    <tr class='<?php echo $class; ?>'>
								<th class="check-column"><input type="checkbox" name="blockcheck[]" value="<?php echo $block->id; ?>" /></th>
								<td><center><?php echo $block->id;?></center></td>
								<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=edit&block='.$block->id;?>" title="Edit"><?php echo $block->name;?></a></strong><br /><a href="<?php echo get_option('siteurl').'/wp-admin/admin.php?page=adrotate-blocks&view=report&block='.$block->id;?>" title="Report">Report</a></td>
								<td><center><?php echo $groups_in_block;?></center></td>
								<td><center><?php echo $stats->impressions;?></center></td>
								<td><center><?php echo $stats_today->impressions;?></center></td>
								<td><center><?php echo $stats->clicks;?></center></td>
								<td><center><?php echo $stats_today->clicks;?></center></td>
								<td><center>[adrotate block="<?php echo $block->id; ?>"]</center></td>
							</tr>
						<?php unset($stats);?>
			 			<?php } ?>
					<?php } else { ?>
					<tr>
						<th class="check-column">&nbsp;</th>
						<td colspan="8"><em>No blocks created yet!</em></td>
					</tr>
					<?php } ?>
		 			</tbody>

				</table>
			</form>

		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>
		   	
				<?php if(!$block_edit_id) { ?>
				<h3>New Block</h3>
					<?php
					$action = "block_new";
					$query = "SELECT `id` FROM `".$wpdb->prefix."adrotate_blocks` WHERE `name` = '' ORDER BY `id` DESC LIMIT 1;";
					$edit_id = $wpdb->get_var($query);
					if($edit_id == 0) {
						$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_blocks` (`name`, `adcount`, `columns`, `wrapper_before`, `wrapper_after`) VALUES ('', 0, 0, '', '');");
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
							<th colspan="4">Usage</th>
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
					
					<h3>Select Groups</h3>

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
	
		   	<?php } else if($view == "report") { ?>

				<h3>This blocks performance</h3>

				<?php
					$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
					$title		 	= $wpdb->get_var("SELECT `name` FROM `".$wpdb->prefix."adrotate_blocks` WHERE `id` = '$block_edit_id';");
					$stats 			= $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `block` = '$block_edit_id';");
					$stats_today 	= $wpdb->get_row("SELECT `clicks`, `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `block` = '$block_edit_id' AND `thetime` = '$today';");

					// Sort out CTR
					if($stats->impressions == 0) $ctrimpressions = 0.001;
						else $ctrimpressions = $stats->impressions;
					if($stats->clicks == 0) $ctrclicks = 0.001;
						else $ctrclicks = $stats->clicks;
					$ctr = round((100/$ctrimpressions)*$ctrclicks,2);						
	
					// Prevent gaps in display
					if($stats->impressions == 0) 		$stats->impressions 		= 0;
					if($stats->clicks == 0) 			$stats->clicks 				= 0;
					if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
					if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;
			
					if($adrotate_debug['stats'] == true) {
						echo "<p><strong>[DEBUG] Block (all time)</strong><pre>";
						$memory = (memory_get_usage() / 1024 / 1024);
						echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
						$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
						echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
						print_r($stats); 
						echo "</pre></p>"; 
						echo "<p><strong>[DEBUG] Block (today)</strong><pre>";
						print_r($stats_today); 
						echo "</pre></p>"; 
					}	
		
				?>

		    	<table class="widefat" style="margin-top: .5em">
					<thead>
					<tr>
						<th colspan="5" bgcolor="#DDD">Statistics for '<?php echo $title; ?>'</th>
					</tr>
					</thead>
	
					<tbody>
			      	<tr>
				        <td width="20%"><div class="stats_large">Impressions<br /><div class="number_large"><?php echo $stats->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks<br /><div class="number_large"><?php echo $stats->clicks; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Impressions today<br /><div class="number_large"><?php echo $stats_today->impressions; ?></div></div></td>
				        <td width="20%"><div class="stats_large">Clicks today<br /><div class="number_large"><?php echo $stats_today->clicks; ?></div></div></td>
				        <td width="20%"><div class="stats_large">CTR<br /><div class="number_large"><?php echo $ctr.' %'; ?></div></div></td>
			      	</tr>
			      	<tr>
				        <th colspan="5">
				        	<?php
				        	$blockstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` WHERE `block` = '$block_edit_id' GROUP BY `thetime` ASC LIMIT 21;");
							if($blockstats) {
								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] 21 days (Or as much as is available) Block stats</strong><pre>"; 
									print_r($blockstats); 
									echo "</pre></p>"; 
								}

								foreach($blockstats as $result) {
									if($result->clicks == null) $result->clicks = '0';
									if($result->impressions == null) $result->impressions = '0';
									
									$clicks_array[date("d-m-Y", $result->thetime)] = $result->clicks;
									$impressions_array[date("d-m-Y", $result->thetime)] = $result->impressions;
								}
								
								if($adrotate_debug['stats'] == true) { 
									echo "<p><strong>[DEBUG] Found clicks as presented to PHPGraphLib</strong><pre>"; 
									print_r($clicks_array); 
									echo "</pre></p>"; 
									echo "<p><strong>[DEBUG] Found impressions as presented to PHPGraphLib</strong><pre>"; 
									print_r($impressions_array); 
									echo "</pre></p>"; 
								}

								$impressions_title = urlencode(serialize('Impressions over the past 21 days'));
								$impressions_array = urlencode(serialize($impressions_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_block.php?title=$impressions_title&data=$impressions_array\" />";

								$clicks_title = urlencode(serialize('Clicks over the past 21 days'));
								$clicks_array = urlencode(serialize($clicks_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_block.php?title=$clicks_title&data=$clicks_array\" />";
							} else {
								echo "No data to show!";
							} 
							?>
				        </th>
			      	</tr>
			      	<tr>
				        <td colspan="5"><b>Note:</b> <em>All statistics are indicative. They do not nessesarily reflect results counted by other parties.</em></td>
			      	</tr>
					</tbody>
				</table>

		   	<?php } ?>
	
			<br class="clear" />
		
			<?php adrotate_credits(); ?>

		<?php } else { ?>
			<?php echo adrotate_error('db_error'); ?>
		<?php }	?>
		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_advertiser_report

 Purpose:   User statistics page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_advertiser_report() {
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
	  	<h2>Advertiser Report</h2>

		<?php if ($message == 'mail_sent') { ?>
			<div id="message" class="updated fade"><p>Your message has been sent</p></div>
		<?php } ?>

		<?php if($view == "" OR $view == "stats") {
			$user_has_ads = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_linkmeta` WHERE `group` = 0 AND `block` = 0 AND `user` = ".$user->ID.";");

			if($user_has_ads > 0) {
				$result = adrotate_prepare_advertiser_report($user->ID); 
				
				if($result['total_impressions'] > 0 AND $result['total_clicks'] > 0) {
					$ctr = round((100/$result['total_impressions'])*$result['total_clicks'], 2);
				} else {
					$ctr = 0;
				}
		?>
	
				<h4>Your ads</h4>
				
				<table class="widefat" style="margin-top: .5em">
					<thead>
						<tr>
						<th width="2%"><center>ID</center></th>
						<th width="13%">Show from</th>
						<th width="13%">Show until</th>
						<th>Title</th>
						<th width="5%"><center>Impressions</center></th>
						<th width="5%"><center>Today</center></th>
						<th width="5%"><center>Clicks</center></th>
						<th width="5%"><center>Today</center></th>
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
							<td><center><?php echo $ad['impressions_today'];?></center></td>
							<td><center><?php echo $ad['clicks'];?></center></td>
							<td><center><?php echo $ad['clicks_today'];?></center></td>
							<?php if($ad['impressions'] == 0) $ad['impressions'] = 1; ?>
							<td><center><?php echo round((100/$ad['impressions']) * $ad['clicks'],2); ?> %</center></td>
							<td><a href="admin.php?page=adrotate-advertiser-report&view=message&request=renew&id=<?php echo $ad['id']; ?>">Renew</a> - <a href="admin.php?page=adrotate-advertiser-report&view=message&request=remove&id=<?php echo $ad['id']; ?>">Remove</a> - <a href="admin.php?page=adrotate-advertiser-report&view=message&request=other&id=<?php echo $ad['id']; ?>">Other</a></td>
						</tr>
						<?php } ?>
				<?php } else { ?>
					<tr id='no-ads'>
						<th class="check-column">&nbsp;</th>
						<td colspan="10"><em>No ads to show! <a href="admin.php?page=adrotate-advertiser-report&view=message&request=issue">Contact your publisher</a>.</em></td>
					</tr>
				<?php } ?>
					</tbody>
				</table>

				<h4>Summary</h4>
				
				<table class="widefat" style="margin-top: .5em">					

					<thead>
					<tr>
						<th colspan="2">Overall statistics</th>
						<th>The last 8 clicks in the past 24 hours</th>
					</tr>
					</thead>
					
					<tbody>

					<?php if($adrotate_debug['userstats'] == true) { ?>
					<tr>
						<td colspan="3">
							<?php 
							echo "<p><strong>User Report</strong><pre>"; 
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
			      	<tr>
				        <th colspan="3">
				        	<?php
				        	$adstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` GROUP BY `thetime` ASC LIMIT 21;");
							if($adstats) {
		
								if($adrotate_debug['userstats'] == true) { 
									echo "<p><strong>[DEBUG] 21 days (Or as much as is available) Ad stats</strong><pre>"; 
									print_r($adstats); 
									echo "</pre></p>"; 
								}
		
								foreach($adstats as $stat) {
									if($stat->clicks == null) $stat->clicks = '0';
									if($stat->impressions == null) $stat->impressions = '0';
								
									$clicks_array[date("d-m-Y", $stat->thetime)] = $stat->clicks;
									$impressions_array[date("d-m-Y", $stat->thetime)] = $stat->impressions;
								}
			
								if($adrotate_debug['userstats'] == true) { 
									echo "<p><strong>[DEBUG] Found clicks as presented to PHPGraphLib</strong><pre>"; 
									print_r($clicks_array); 
									echo "</pre></p>"; 
									echo "<p><strong>[DEBUG] Found impressions as presented to PHPGraphLib</strong><pre>"; 
									print_r($impressions_array); 
									echo "</pre></p>"; 
								}
			
								$impressions_title = urlencode(serialize('Impressions of all your ads over the past 21 days'));
								$impressions_array = urlencode(serialize($impressions_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_all_ads.php?title=$impressions_title&data=$impressions_array\" />";

								$clicks_title = urlencode(serialize('Clicks of all your ads over the past 21 days'));
								$clicks_array = urlencode(serialize($clicks_array));
								echo "<img src=\"../wp-content/plugins/adrotate/library/graph_all_ads.php?title=$clicks_title&data=$clicks_array\" />";
							} else {
								echo "No data to show!";
							} 
							?>
				        </th>
			      	</tr>
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
							<td>No ads for user. If you feel this to be in error please <a href="admin.php?page=adrotate-advertiser-report&view=message&request=issue">contact the site administrator</a>.</td>
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
			} else if($request == "other") {
				$request_name = "About";
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
			<form name="request" id="post" method="post" action="admin.php?page=adrotate-advertiser-report">
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

		<br class="clear" />
	</div>
<?php 
}

/*-------------------------------------------------------------
 Name:      adrotate_global_report

 Purpose:   Admin statistics page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_global_report() {
	global $wpdb, $adrotate_debug;
	
	$adrotate_stats = adrotate_prepare_global_report();
	
	if($adrotate_stats['tracker'] > 0 OR $adrotate_stats['clicks'] > 0) {
		$clicks = round($adrotate_stats['clicks'] / $adrotate_stats['tracker'], 2); 
	} else { 
		$clicks = 0; 
	}
	
	if($adrotate_stats['impressions'] > 0 AND $adrotate_stats['clicks'] > 0) {
		$ctr = round((100/$adrotate_stats['impressions'])*$adrotate_stats['clicks'], 2);
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
				<th colspan="2">The last 4 clicks in the past 24 hours</th>
			</tr>
			</thead>
			
			<tbody>

			<?php if($adrotate_debug['stats'] == true) { ?>
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
				<td width="40%"><?php echo $adrotate_stats['banners']; ?> ads, sharing a total of <?php echo $adrotate_stats['impressions']; ?> impressions. <?php echo $adrotate_stats['tracker']; ?> ads have tracking enabled.</td>
				<td rowspan="3" style="border-left:1px #EEE solid;">
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
				<th>Average on all ads</th>
				<td><?php echo $clicks; ?> clicks.</td>
			</tr>
		    <tr>
				<th>Click-Through-Rate</th>
				<td><?php echo $ctr; ?>%, based on <?php echo $adrotate_stats['impressions']; ?> impressions and <?php echo $adrotate_stats['clicks']; ?> clicks.</td>
			</tr>
	      	<tr>
		        <th colspan="4">
		        	<?php
		        	$adstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats_tracker` GROUP BY `thetime` ASC LIMIT 21;");
					if($adstats) {

						if($adrotate_debug['stats'] == true) { 
							echo "<p><strong>[DEBUG] 21 days (Or as much as is available) Ad stats</strong><pre>"; 
							print_r($adstats); 
							echo "</pre></p>"; 
						}

						foreach($adstats as $result) {
							if($result->clicks == null) $result->clicks = '0';
							if($result->impressions == null) $result->impressions = '0';
						
							$clicks_array[date("d-m-Y", $result->thetime)] = $result->clicks;
							$impressions_array[date("d-m-Y", $result->thetime)] = $result->impressions;
						}

						if($adrotate_debug['stats'] == true) { 
							echo "<p><strong>[DEBUG] Found clicks as presented to PHPGraphLib</strong><pre>"; 
							print_r($clicks_array); 
							echo "</pre></p>"; 
							echo "<p><strong>[DEBUG] Found impressions as presented to PHPGraphLib</strong><pre>"; 
							print_r($impressions_array); 
							echo "</pre></p>"; 
						}

						$impressions_title = urlencode(serialize('Impressions over the past 21 days'));
						$impressions_array = urlencode(serialize($impressions_array));
						echo "<img src=\"../wp-content/plugins/adrotate/library/graph_all_ads.php?title=$impressions_title&data=$impressions_array\" />";

						$clicks_title = urlencode(serialize('Clicks over the past 21 days'));
						$clicks_array = urlencode(serialize($clicks_array));
						echo "<img src=\"../wp-content/plugins/adrotate/library/graph_all_ads.php?title=$clicks_title&data=$clicks_array\" />";
					} else {
						echo "No data to show!";
					} 
					?>
		        </th>
	      	</tr>
	      	<tr>
				<td colspan="4">
					<b>Note:</b> <em>All statistics are indicative. They do not nessesarily reflect results counted by other parties.</em><br />
					Visual graphing kindly provided using <a href="http://www.ebrueggeman.com/" target="_blank">PHPGraphLib by Elliot Brueggeman</a>
				</td>
	      	</tr>
			</tbody>
		</table>

		<br class="clear" />
		<?php adrotate_credits(); ?>

		<br class="clear" />
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

	$adrotate_config 			= get_option('adrotate_config');
	$adrotate_crawlers 			= get_option('adrotate_crawlers');
	$adrotate_roles				= get_option('adrotate_roles');
	$adrotate_debug				= get_option('adrotate_debug');
	
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
		<?php } else if ($message == 'db_cleaned') { ?>
			<div id="message" class="updated fade"><p>Empty database records removed</p></div>
		<?php } else if ($message == 'db_timer') { ?>
			<div id="message" class="updated fade"><p>Database can only be optimized, cleaned or repaired once every 24 hours</p></div>
		<?php } else if ($message == 'mail_notification_sent') { ?>
			<div id="message" class="updated fade"><p>Test notification(s) sent</p></div>
		<?php } else if ($message == 'mail_advertiser_sent') { ?>
			<div id="message" class="updated fade"><p>Test mail(s) sent</p></div>
		<?php } ?>

	  	<form name="settings" id="post" method="post" action="admin.php?page=adrotate-settings">

	    	<table class="form-table">
			<tr>
				<td colspan="2"><h3>Access Rights</h3></td>
			</tr>

			<tr>
				<th valign="top">Advertiser Reports Page</th>
				<td>
					<select name="adrotate_advertiser_report">
						<?php wp_dropdown_roles($adrotate_config['advertiser_report']); ?>
					</select> <span class="description">Role to allow users/advertisers to see their reports page.</span>
				</td>
			</tr>

			<tr>
				<th valign="top">Global Report Page</th>
				<td>
					<select name="adrotate_global_report">
						<?php wp_dropdown_roles($adrotate_config['global_report']); ?>
					</select> <span class="description">Role to review the global report.</span>
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

			<?php if($adrotate_debug['dashboard'] == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] Globalized Config</strong><pre>"; 
					$memory = (memory_get_usage() / 1024 / 1024);
					echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
					$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
					echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
					print_r($adrotate_config); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>
			<?php if($adrotate_debug['userroles'] == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] AdRotate Advertiser role enabled? (0 = no, 1 = yes)</strong><pre>"; 
					print_r($adrotate_roles); 
					echo "</pre></p>"; 
					echo "<p><strong>[DEBUG] Current User Capabilities</strong><pre>"; 
					print_r(get_option('wp_user_roles')); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>

			<tr>
				<td colspan="2"><h3>Email Notifications</h3></td>
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
				<th scope="row" valign="top">Test</th>
				<td>
					<input type="submit" name="adrotate_notification_test_submit" class="button-secondary" value="Test" /> 
					<span class="description">This sends a test notification. Before you test, for example, with a new email address. Save the options first!</span>
				</td>
			</tr>

			<tr>
				<td colspan="2"><h3>Advertiser Messages</h3></td>
			</tr>

			<tr>
				<th valign="top">Advertiser Messages</th>
				<td>
					<textarea name="adrotate_advertiser_email" cols="90" rows="2"><?php echo $advertiser_mails; ?></textarea><br />
					<span class="description">Maximum of 2 addresses. Comma seperated. This field cannot be empty!</span>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">Test</th>
				<td>
					<input type="submit" name="adrotate_advertiser_test_submit" class="button-secondary" value="Test" /> 
					<span class="description">This sends a test message. Before you test, for example, with a new email address. Save the options first!</span>
				</td>
			</tr>
			
			<tr>
				<td colspan="2"><h3>User-Agent Filter</h3></td>
			</tr>
			
			<?php if($adrotate_debug['dashboard'] == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] List of crawler keywords</strong><pre>";
					print_r($adrotate_crawlers); 
					echo "</pre></p>"; 
					?>
				</td>
			</tr>
			<?php } ?>

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

			<?php if($adrotate_debug['dashboard'] == true) { ?>
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
				<td colspan="2"><span class="description">NOTE: The below functions are intented to be used to OPTIMIZE and/or REPAIR your database. They only apply to your ads/groups/blocks and stats. Not to other settings or other parts of Wordpress! Always always make a backup! These functions are to be used when you feel or notice your database is slow, unresponsive and sluggish.</span></td>
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
				<th valign="top">Clean-up database</th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_db_cleanup_submit" value="Clean-up Database" class="button-secondary" onclick="return confirm('You are about to remove empty records from the AdRotate database.\n\nDid you make a backup of your database?\n\n\'OK\' to continue, \'Cancel\' to stop.')" /><br />
					<span class="description">AdRotate creates empty records when you start making ads, groups or blocks. In rare occasions these records are faulty. If you made an ad, group or block that does not save when you make it use this button to delete those empty records.</span>
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
				<td colspan="2"><h3>Troubleshooting</h3></td>
			</tr>
			
			<tr>
				<td colspan="2"><span class="description">NOTE: The below options are not meant for normal use and are only there for developers to review saved settings or how ads are selected. These can be used as a measure of troubleshooting upon request but for normal use they SHOULD BE LEFT UNCHECKED!!</span></td>
			</tr>

			<tr>
				<th valign="top">Developer Debug</th>
				<td>
					<input type="checkbox" name="adrotate_debug" <?php if($adrotate_debug['general'] == true) { ?>checked="checked" <?php } ?> /> <span class="description">Troubleshoot ads and how (if) they are selected, will mess up your theme!</span><br />
					<input type="checkbox" name="adrotate_debug_dashboard" <?php if($adrotate_debug['dashboard'] == true) { ?>checked="checked" <?php } ?> /> <span class="description">Show all settings and related values!</span><br />
					<input type="checkbox" name="adrotate_debug_userroles" <?php if($adrotate_debug['userroles'] == true) { ?>checked="checked" <?php } ?> /> <span class="description">Show array of all userroles and capabilities!</span><br />
					<input type="checkbox" name="adrotate_debug_userstats" <?php if($adrotate_debug['userstats'] == true) { ?>checked="checked" <?php } ?> /> <span class="description">Review saved user stats (users)! Visible to advertisers!</span><br />
					<input type="checkbox" name="adrotate_debug_stats" <?php if($adrotate_debug['stats'] == true) { ?>checked="checked" <?php } ?> /> <span class="description">Review global stats, per ad/group/block stats (admins)!</span><br />
				</td>
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