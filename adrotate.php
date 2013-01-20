<?php
/*
Plugin Name: AdRotate
Plugin URI: http://www.adrotateplugin.com
Description: The very best and most convenient way to publish your ads.
Author: Arnan de Gans of AJdG Solutions
Version: 3.8.2
Author URI: http://www.ajdg.net
License: GPLv3
*/

/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*--- AdRotate values ---------------------------------------*/
define("ADROTATE_BETA", '');
define("ADROTATE_DISPLAY", '3.8.2'.ADROTATE_BETA);
define("ADROTATE_VERSION", 362);
define("ADROTATE_DB_VERSION", 26);
/*-----------------------------------------------------------*/

/*--- Load Files --------------------------------------------*/
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-setup.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-manage-publisher.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-functions.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-statistics.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-output.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-widget.php');
include_once(WP_CONTENT_DIR.'/plugins/adrotate/adrotate-network.php');
// wp-content/plugins/adrotate/adrotate-out.php
/*-----------------------------------------------------------*/

/*--- Check and Load config ---------------------------------*/
load_plugin_textdomain('adrotate', false, basename( dirname( __FILE__ ) ) . '/language' );
adrotate_check_config();
$adrotate_config 				= get_option('adrotate_config');
$adrotate_crawlers 				= get_option('adrotate_crawlers');
$adrotate_roles 				= get_option('adrotate_roles');
$adrotate_version				= get_option("adrotate_version");
$adrotate_db_version			= get_option("adrotate_db_version");
$adrotate_debug					= get_option("adrotate_debug");
$adrotate_advert_status			= get_option("adrotate_advert_status");
/*-----------------------------------------------------------*/

/*--- Core --------------------------------------------------*/
register_activation_hook(__FILE__, 'adrotate_activate');
register_deactivation_hook(__FILE__, 'adrotate_deactivate');
register_uninstall_hook(__FILE__, 'adrotate_uninstall');
add_filter('cron_schedules', 'adrotate_reccurences');
add_action('adrotate_clean_trackerdata', 'adrotate_clean_trackerdata');
/*-----------------------------------------------------------*/

/*--- Front end ---------------------------------------------*/
add_shortcode('adrotate', 'adrotate_shortcode');
add_filter('the_content', 'adrotate_inject_posts');
//add_action('wp_enqueue_scripts', 'adrotate_head');
add_action('widgets_init', create_function('', 'return register_widget("adrotate_widgets");'));
add_action('wp_meta', 'adrotate_meta');
/*-----------------------------------------------------------*/

/*--- Dashboard ---------------------------------------------*/
add_action('admin_init', 'adrotate_colorpicker');
add_action('admin_menu', 'adrotate_dashboard');
add_action('admin_notices','adrotate_notifications_dashboard');
/*-----------------------------------------------------------*/

/*--- BETA NOTICE -------------------------------------------*/
if(strlen(ADROTATE_BETA) > 0) add_action('admin_notices','adrotate_beta_notifications_dashboard');
/*-----------------------------------------------------------*/

/*--- Internal redirects ------------------------------------*/
if(isset($_POST['adrotate_ad_submit'])) 				add_action('init', 'adrotate_insert_input');
if(isset($_POST['adrotate_group_submit'])) 				add_action('init', 'adrotate_insert_group');
if(isset($_POST['adrotate_block_submit'])) 				add_action('init', 'adrotate_insert_block');
if(isset($_POST['adrotate_action_submit'])) 			add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_disabled_action_submit']))	add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_error_action_submit']))		add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_beta_submit'])) 				add_action('init', 'adrotate_mail_beta');
if(isset($_POST['adrotate_options_submit'])) 			add_action('init', 'adrotate_options_submit');
if(isset($_POST['adrotate_request_submit'])) 			add_action('init', 'adrotate_mail_message');
if(isset($_POST['adrotate_db_optimize_submit'])) 		add_action('init', 'adrotate_optimize_database');
if(isset($_POST['adrotate_db_cleanup_submit'])) 		add_action('init', 'adrotate_cleanup_database');
if(isset($_POST['adrotate_evaluate_submit'])) 			add_action('init', 'adrotate_prepare_evaluate_ads');
/*-----------------------------------------------------------*/

/*-------------------------------------------------------------
 Name:      adrotate_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard() {
	global $wpdb, $current_user, $userdata;
	
	get_currentuserinfo();

	$admin_pages = array();

	add_object_page('AdRotate', 'AdRotate', 'adrotate_ad_manage', 'adrotate', 'adrotate_info');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('General Info', 'adrotate'), __('General Info', 'adrotate'), 'adrotate_ad_manage', 'adrotate', 'adrotate_info');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Manage Ads', 'adrotate'), __('Manage Ads', 'adrotate'), 'adrotate_ad_manage', 'adrotate-ads', 'adrotate_manage');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Manage Groups', 'adrotate'), __('Manage Groups', 'adrotate'), 'adrotate_group_manage', 'adrotate-groups', 'adrotate_manage_group');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Manage Blocks', 'adrotate'), __('Manage Blocks', 'adrotate'), 'adrotate_block_manage', 'adrotate-blocks', 'adrotate_manage_block');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Moderate', 'adrotate'), __('Moderate Adverts', 'adrotate'), 'manage_options', 'adrotate-moderate', 'adrotate_moderate');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Global Reports', 'adrotate'), __('Global Reports', 'adrotate'), 'manage_options', 'adrotate-global-report', 'adrotate_global_report');
	$admin_pages[] = add_submenu_page('adrotate', 'AdRotate > '.__('Settings', 'adrotate'), __('Settings', 'adrotate'), 'manage_options', 'adrotate-settings', 'adrotate_options');
	if(strlen(ADROTATE_BETA) > 0) $admin_pages[] = add_submenu_page('adrotate', 'AdRotate > Beta Feedback', 'Beta Feedback', 'adrotate_ad_manage', 'adrotate-beta', 'adrotate_beta');
	
	foreach($admin_pages as $admin_page) {
		add_action("admin_print_styles-{$admin_page}", 'adrotate_dashboard_scripts');
		add_action("admin_print_scripts-{$admin_page}", 'adrotate_dashboard_styles');
	}
}

/*-------------------------------------------------------------
 Name:      adrotate_info

 Purpose:   Admin general info page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_info() {
	global $wpdb, $adrotate_config, $adrotate_advert_status;

	?>

	<div class="wrap">
		<h2><?php _e('AdRotate Info', 'adrotate'); ?></h2>

		<br class="clear" />

		<?php include("dashboard/adrotate-info.php"); ?>

		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_manage() {
	global $wpdb, $current_user, $userdata, $adrotate_config, $adrotate_debug;

	$message = $view = $ad_edit_id = '';
	if(isset($_GET['message'])) $message = $_GET['message'];
	if(isset($_GET['view'])) $view = $_GET['view'];
	if(isset($_GET['ad'])) $ad_edit_id = $_GET['ad'];
	$now 			= current_time('timestamp');
	$in2days 		= $now + 172800;
	$in7days 		= $now + 604800;
	$in84days 		= $now + 7257600;

	if(isset($_GET['month']) AND isset($_GET['year'])) {
		$month = $_GET['month'];
		$year = $_GET['year'];
	} else {
		$month = date("m");
		$year = date("Y");
	}
	$monthstart = mktime(0, 0, 0, $month, 1, $year);
	$monthend = mktime(0, 0, 0, $month+1, 0, $year);	
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
		<h2><?php _e('Ad Management', 'adrotate'); ?></h2>

		<?php if ($message == 'new') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad created', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad updated', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad(s) deleted', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'reset') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad(s) statistics reset', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'renew') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad(s) renewed', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'deactivate') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad(s) deactivated', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'activate') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ad(s) activated', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'field_error') { ?>
			<div id="message" class="updated fade"><p><?php _e('The ad was saved but has an issue which might prevent it from working properly. Review the yellow marked ad.', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'no_access') { ?>
			<div id="message" class="updated fade"><p><?php _e('Action prohibited', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'nodata') { ?>
			<div id="message" class="updated fade"><p><?php _e('No data found in selected time period', 'adrotate'); ?></p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_groups';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_schedule';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>

			<?php
			$allbanners = $wpdb->get_results("SELECT `id`, `title`, `type`, `tracker` FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'active' OR `type` = 'error' OR `type` = 'disabled' ORDER BY `sortorder` ASC, `id` ASC;");
			$activebanners = $errorbanner = $disabledbanners = false;
			foreach($allbanners as $singlebanner) {
				
				$schedule = $wpdb->get_row("SELECT `starttime`, `stoptime` FROM `".$wpdb->prefix."adrotate_schedule` WHERE `ad` = '".$singlebanner->id."';");
				
				$type = $singlebanner->type;
				if($schedule->stoptime <= $now) {
					$type = 'expired';
				} 
				if($schedule->stoptime <= $in7days) {
					$type = 'expiressoon';
				}
	
				if($type == 'active') {
					$activebanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'firstactive' => $schedule->starttime,
						'lastactive' => $schedule->stoptime
					);
				}
				
				if($type == 'error' OR $type == 'expired' OR $type == 'expiressoon') {
					$errorbanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'firstactive' => $schedule->starttime,
						'lastactive' => $schedule->stoptime
					);
				}
				
				if($type == 'disabled') {
					$disabledbanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'firstactive' => $schedule->starttime,
						'lastactive' => $schedule->stoptime
					);
				}
			}
			?>
			
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-ads&view=manage');?>"><?php _e('Manage', 'adrotate'); ?></a> | 
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-ads&view=addnew');?>"><?php _e('Add New', 'adrotate'); ?></a> 
					<?php if($ad_edit_id) { ?>
					| <a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-ads&view=report&ad='.$ad_edit_id);?>"><?php _e('Report', 'adrotate'); ?></a>
					<?php } ?>
				</div>
			</div>

			<br class="clear" />

			<?php adrotate_credits(); ?>

			<br class="clear" />

	    	<?php if ($view == "" OR $view == "manage") { ?>
	
				<?php
				// Show list of errorous ads if any			
				if ($errorbanners) {
					include("dashboard/publisher/adrotate-ads-main-error.php");
				}
		
				include("dashboard/publisher/adrotate-ads-main.php");
	
				// Show disabled ads, if any
				if ($disabledbanners) {
					include("dashboard/publisher/adrotate-ads-main-disabled.php");
				}
				?>

			<?php
		   	} else if($view == "addnew" OR $view == "edit") { 
		   	?>

				<?php
				include("dashboard/publisher/adrotate-ads-edit.php");
				?>

		   	<?php } else if($view == "report") { ?>

				<?php
				include("dashboard/publisher/adrotate-ads-report.php");
				?>

		   	<?php } ?>
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

	$message = $view = $group_edit_id = '';
	if(isset($_GET['message'])) $message = $_GET['message'];
	if(isset($_GET['view'])) $view = $_GET['view'];
	if(isset($_GET['group'])) $group_edit_id = $_GET['group'];

	if(isset($_GET['month']) AND isset($_GET['year'])) {
		$month = $_GET['month'];
		$year = $_GET['year'];
	} else {
		$month = date("m");
		$year = date("Y");
	}
	$monthstart = mktime(0, 0, 0, $month, 1, $year);
	$monthend = mktime(0, 0, 0, $month+1, 0, $year);	
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
		<h2><?php _e('Group Management', 'adrotate'); ?></h2>

		<?php if ($message == 'created') { ?>
			<div id="message" class="updated fade"><p><?php _e('Group created', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p><?php _e('Group updated', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p><?php _e('Group deleted', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'deleted_banners') { ?>
			<div id="message" class="updated fade"><p><?php _e('Group including it\'s Ads deleted', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'nodata') { ?>
			<div id="message" class="updated fade"><p><?php _e('No data found in selected time period', 'adrotate'); ?></p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_groups';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-groups&view=manage');?>"><?php _e('Manage', 'adrotate'); ?></a> | 
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-groups&view=addnew');?>"><?php _e('Add New', 'adrotate'); ?></a>
					<?php if($group_edit_id) { ?>
					| <a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-groups&view=report&group='.$group_edit_id);?>"><?php _e('Report', 'adrotate'); ?></a>
					<?php } ?>
				</div>
			</div>

			<?php adrotate_credits(); ?>

			<br class="clear" />

	    	<?php if ($view == "" OR $view == "manage") { ?>

				<?php
				include("dashboard/publisher/adrotate-groups-main.php");
				?>

		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>

				<?php
				include("dashboard/publisher/adrotate-groups-edit.php");
				?>

		   	<?php } else if($view == "report") { ?>

				<?php
				include("dashboard/publisher/adrotate-groups-report.php");
				?>

		   	<?php } ?>
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

	$message = $view = $block_edit_id = '';
	if(isset($_GET['message'])) $message = $_GET['message'];
	if(isset($_GET['view'])) $view = $_GET['view'];
	if(isset($_GET['block'])) $block_edit_id = $_GET['block'];

	if(isset($_GET['month']) AND isset($_GET['year'])) {
		$month = $_GET['month'];
		$year = $_GET['year'];
	} else {
		$month = date("m");
		$year = date("Y");
	}
	$monthstart = mktime(0, 0, 0, $month, 1, $year);
	$monthend = mktime(0, 0, 0, $month+1, 0, $year);	
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
		<h2><?php _e('Block Management', 'adrotate'); ?></h2>

		<?php if ($message == 'created') { ?>
			<div id="message" class="updated fade"><p><?php _e('Block created', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p><?php _e('Block updated', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'deleted') { ?>
			<div id="message" class="updated fade"><p><?php _e('Block deleted', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'nodata') { ?>
			<div id="message" class="updated fade"><p><?php _e('No data found in selected time period', 'adrotate'); ?></p></div>
		<?php } ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_blocks';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-blocks&view=manage');?>"><?php _e('Manage', 'adrotate'); ?></a> 
					| <a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-blocks&view=addnew');?>"><?php _e('Add New', 'adrotate'); ?></a> 
					<?php if($block_edit_id) { ?>
					| <a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-blocks&view=report&block='.$block_edit_id);?>"><?php _e('Report', 'adrotate'); ?></a> 
					<?php } ?>
				</div>
			</div>

			<?php adrotate_credits(); ?>

			<br class="clear" />

	    	<?php if ($view == "" OR $view == "manage") { ?>

				<?php
				include("dashboard/publisher/adrotate-blocks-main.php");
				?>

		   	<?php } else if($view == "addnew" OR $view == "edit") { ?>
		   	
				<?php
				include("dashboard/publisher/adrotate-blocks-edit.php");
				?>
	
		   	<?php } else if($view == "report") { ?>

				<?php
				include("dashboard/publisher/adrotate-blocks-report.php");
				?>

		   	<?php } ?>
		<?php } else { ?>
			<?php echo adrotate_error('db_error'); ?>
		<?php }	?>
		<br class="clear" />
	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_moderate

 Purpose:   Moderation queue
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_moderate() {
?>
	<div class="wrap">
		<h2><?php _e('Moderation queue', 'adrotate'); ?></h2>

		<div class="tablenav">
			<div class="alignleft actions">
				<select name="adrotate_action" id="cat" class="postform" disabled>
			        <option value=""><?php _e('Bulk Actions', 'adrotate'); ?></option>
			        <option value="approve"><?php _e('Approve', 'adrotate'); ?></option>
			        <option value="update"><?php _e('Update', 'adrotate'); ?></option>
			        <option value="delete"><?php _e('Delete', 'adrotate'); ?></option>
			        <option value="reject"><?php _e('Reject', 'adrotate'); ?></option>
				</select>
				<input type="submit" id="post-action-submit" name="adrotate_action_submit" value="Go" class="button-secondary" disabled />
			</div>
		
			<br class="clear" />
		</div>
	
		<table class="widefat" style="margin-top: .5em">
			<thead>
			<tr>
				<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox" disabled /></th>
				<th width="2%"><center><?php _e('ID', 'adrotate'); ?></center></th>
				<th width="12%"><?php _e('Show from', 'adrotate'); ?></th>
				<th width="12%"><?php _e('Show until', 'adrotate'); ?></th>
				<th><?php _e('Title', 'adrotate'); ?></th>
				<th width="20%"><center><?php _e('Advertiser', 'adrotate'); ?></center></th>
				<th width="5%"><center><?php _e('Weight', 'adrotate'); ?></center></th>
				<th width="15%"><center><?php _e('Options', 'adrotate'); ?></center></th>
			</tr>
			</thead>
	
			<tbody>
		    <tr>
				<td colspan="8">
					<p><?php adrotate_pro_notice(); ?></p>
					<p><?php _e('Couple adverts to advertisers and allow them to create and upload their own advertisements for you to moderate and approve or reject!', 'adrotate'); ?></p>
				</td>
			</tr>
			</tbody>
		</table>

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
?>
	<div class="wrap">

	  	<h2><?php _e('Statistics', 'adrotate'); ?></h2>

		<p><?php adrotate_pro_notice(); ?></p>
		<p><?php _e('A summarized overview of all adverts currently active!', 'adrotate'); ?></p>
		<p><a href="http://www.adrotateplugin.com" title="AdRotate Plugin for WordPress"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/global-stats.png" align="center" /></a></p>
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

	$message = $corrected = $converted = '';
	if(isset($_GET['message'])) $message = $_GET['message'];

	$converted = base64_decode($converted);
?>
	<div class="wrap">
	  	<h2><?php _e('AdRotate Settings', 'adrotate'); ?></h2>

		<?php if ($message == 'updated') { ?>
			<div id="message" class="updated fade"><p><?php _e('Settings saved', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'db_optimized') { ?>
			<div id="message" class="updated fade"><p><?php _e('Database optimized', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'db_repaired') { ?>
			<div id="message" class="updated fade"><p><?php _e('Database repaired', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'db_evaluated') { ?>
			<div id="message" class="updated fade"><p><?php _e('Ads evaluated and statuses have been corrected where required', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'db_cleaned') { ?>
			<div id="message" class="updated fade"><p><?php _e('Empty database records removed', 'adrotate'); ?></p></div>
		<?php } else if ($message == 'db_timer') { ?>
			<div id="message" class="updated fade"><p><?php _e('Database can only be optimized or cleaned once every hour', 'adrotate'); ?></p></div>
		<?php } ?>

	  	<form name="settings" id="post" method="post" action="admin.php?page=adrotate-settings">

			<?php wp_nonce_field('adrotate_email_test','adrotate_nonce'); ?>
			<?php wp_nonce_field('adrotate_settings','adrotate_nonce_settings'); ?>

	    	<table class="form-table">
			<tr>
				<td colspan="2"><h2><?php _e('Access Rights', 'adrotate'); ?></h2></td>
			</tr>

			<tr>
				<th valign="top"><?php _e('Advertiser page', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_advertiser" disabled>
						<?php wp_dropdown_roles($adrotate_config['advertiser']); ?>
					</select> <span class="description"><?php _e('Role to allow users/advertisers to see their advertisement page.', 'adrotate'); ?> <?php adrotate_pro_notice(); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Global report page', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_global_report" disabled>
						<?php wp_dropdown_roles($adrotate_config['global_report']); ?>
					</select> <span class="description"><?php _e('Role to review the global report.', 'adrotate'); ?> <?php adrotate_pro_notice(); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Manage/Add/Edit adverts', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_ad_manage">
						<?php wp_dropdown_roles($adrotate_config['ad_manage']); ?>
					</select> <span class="description"><?php _e('Role to see and add/edit ads.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Delete/Reset adverts', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_ad_delete">
						<?php wp_dropdown_roles($adrotate_config['ad_delete']); ?>
					</select> <span class="description"><?php _e('Role to delete ads and reset stats.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Manage/Add/Edit groups', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_group_manage">
						<?php wp_dropdown_roles($adrotate_config['group_manage']); ?>
					</select> <span class="description"><?php _e('Role to see and add/edit groups.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Delete groups', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_group_delete">
						<?php wp_dropdown_roles($adrotate_config['group_delete']); ?>
					</select> <span class="description"><?php _e('Role to delete groups.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Manage/Add/Edit blocks', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_block_manage">
						<?php wp_dropdown_roles($adrotate_config['block_manage']); ?>
					</select> <span class="description"><?php _e('Role to see and add/edit blocks.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Delete blocks', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_block_delete">
						<?php wp_dropdown_roles($adrotate_config['block_delete']); ?>
					</select> <span class="description"><?php _e('Role to delete blocks.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Moderate new adverts', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_moderate" disabled>
						<?php wp_dropdown_roles($adrotate_config['moderate']); ?>
					</select> <span class="description"><?php _e('Role to approve ads submitted by advertisers.', 'adrotate'); ?> <?php adrotate_pro_notice(); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Approve/Reject adverts in Moderation Queue', 'adrotate'); ?></th>
				<td>
					<select name="adrotate_moderate_approve" disabled>
						<?php wp_dropdown_roles($adrotate_config['moderate_approve']); ?>
					</select> <span class="description"><?php _e('Role to approve or reject ads submitted by advertisers.', 'adrotate'); ?> <?php adrotate_pro_notice(); ?></span>
				</td>
			</tr>

			<?php if($adrotate_debug['dashboard'] == true) { ?>
			<tr>
				<td colspan="2">
					<?php 
					echo "<p><strong>[DEBUG] Globalized Config</strong>"; 
					echo "<pre>"; 
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
				<td colspan="2"><h2><?php _e('Banner Folder', 'adrotate'); ?></h2></td>
			</tr>
			<tr><td colspan="2"><?php adrotate_pro_notice(); ?></td></tr>
			<tr><td colspan="2"><em><?php _e('Move the banner folder to a different location with this option.', 'adrotate'); ?></em></td></tr>


			<tr>
				<td colspan="2"><h2><?php _e('Email Notifications', 'adrotate'); ?></h2></td>
			</tr>
			<tr><td colspan="2"><?php adrotate_pro_notice(); ?></td></tr>
			<tr><td colspan="2"><em><?php _e('Receive email notifications from AdRotate when Adverts are about to expire or otherwise need your attention!', 'adrotate'); ?></em></td></tr>
			
			<tr>
				<td colspan="2"><h2><?php _e('Clicktracker / Impressiontracker', 'adrotate'); ?></h2></td>
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
				<th valign="top"><?php _e('User-Agent Filter', 'adrotate'); ?></th>
				<td>
					<textarea name="adrotate_crawlers" cols="90" rows="5"><?php echo $crawlers; ?></textarea><br />
					<span class="description"><?php _e('A comma separated list of keywords. Filter out bots/crawlers/user-agents. To prevent impressions and clicks counted on them.', 'adrotate'); ?><br />
					<?php _e('Keep in mind that this might give false positives. The word \'google\' also matches \'googlebot\', so be careful!', 'adrotate'); ?><br />
					<?php _e('Additionally to the list specified here, empty User-Agents are blocked as well.', 'adrotate'); ?> (<?php _e('Learn more about', 'adrotate'); ?> <a href="http://en.wikipedia.org/wiki/User_agent" title="User Agents" target="_blank"><?php _e('user-agents', 'adrotate'); ?></a>.)</span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Impressions timer', 'adrotate'); ?></th>
				<td>
					<input name="adrotate_impression_timer" type="text" class="search-input" size="5" value="<?php echo $adrotate_config['impression_timer']; ?>" autocomplete="off" /> <?php _e('Seconds.', 'adrotate'); ?><br />
					<span class="description"><?php _e('Default: 10. Set to 0 to disable this timer.', 'adrotate'); ?><br /><?php _e('This number may not be empty, negative or exceed 3600 (1 hour).', 'adrotate'); ?></span>
				</td>
			</tr>

			
			<tr>
				<td colspan="2"><h2><?php _e('Miscellaneous', 'adrotate'); ?></h2></td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Widget alignment', 'adrotate'); ?></th>
				<td><input type="checkbox" name="adrotate_widgetalign" <?php if($adrotate_config['widgetalign'] == 'Y') { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Check this box if your widgets do not align in your themes sidebar. (Does not always help!)', 'adrotate'); ?></span></td>
			</tr>

			<tr>
				<td colspan="2"><h2><?php _e('Maintenance', 'adrotate'); ?></h2></td>
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
				<td colspan="2"><span class="description"><?php _e('NOTE: The below functions are intented to be used to OPTIMIZE your database. They only apply to your ads/groups/blocks and stats. Not to other settings or other parts of Wordpress! Always always make a backup! These functions are to be used when you feel or notice your database is slow, unresponsive and sluggish.', 'adrotate'); ?></span></td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Optimize Database', 'adrotate'); ?></th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_db_optimize_submit" value="<?php _e('Optimize Database', 'adrotate'); ?>" class="button-secondary" onclick="return confirm('<?php _e('You are about to optimize the AdRotate database.', 'adrotate'); ?>\n\n<?php _e('Did you make a backup of your database?', 'adrotate'); ?>\n\n<?php _e('This may take a moment and may cause your website to respond slow temporarily!', 'adrotate'); ?>\n\n<?php _e('OK to continue, CANCEL to stop.', 'adrotate'); ?>')" /><br />
					<span class="description"><?php _e('Cleans up overhead data in the AdRotate tables.', 'adrotate'); ?><br />
					<?php _e('Overhead data is accumulated garbage resulting from many changes you\'ve made. This can vary from nothing to hundreds of KiB of data.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Clean-up Database', 'adrotate'); ?></th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_db_cleanup_submit" value="<?php _e('Clean-up Database', 'adrotate'); ?>" class="button-secondary" onclick="return confirm('<?php _e('You are about to remove empty records from the AdRotate database.', 'adrotate'); ?>\n\n<?php _e('Did you make a backup of your database?', 'adrotate'); ?>\n\n<?php _e('OK to continue, CANCEL to stop.', 'adrotate'); ?>')" /><br />
					<span class="description"><?php _e('AdRotate creates empty records when you start making ads, groups or blocks. In rare occasions these records are faulty. If you made an ad, group or block that does not save when you make it use this button to delete those empty records.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<th valign="top"><?php _e('Re-evaluate Ads', 'adrotate'); ?></th>
				<td>
					<input type="submit" id="post-role-submit" name="adrotate_evaluate_submit" value="<?php _e('Re-evaluate all ads', 'adrotate'); ?>" class="button-secondary" onclick="return confirm('<?php _e('You are about to check all ads for errors.', 'adrotate'); ?>\n\n<?php _e('This might take a while and make slow down your site during this action!', 'adrotate'); ?>\n\n<?php _e('OK to continue, CANCEL to stop.', 'adrotate'); ?>')" /><br />
					<span class="description"><?php _e('This will apply all evaluation rules to all ads to see if any error slipped in. Normally you shouldn\t need this feature.', 'adrotate'); ?></span>
				</td>
			</tr>
			<tr>
				<td colspan="2"><span class="description"><?php _e('DISCLAIMER: If for any reason your data is lost, damaged or otherwise becomes unusable in any way or by any means in whichever way i will not take responsibility. You should always have a backup of your database. These functions do NOT destroy data. If data is lost, damaged or unusable, your database likely was beyond repair already. Claiming it worked before clicking these buttons is not a valid point in any case.', 'adrotate'); ?></span></td>
			</tr>

			<tr>
				<td colspan="2"><h2><?php _e('Troubleshooting', 'adrotate'); ?></h2></td>
			</tr>
			<tr>
				<td colspan="2"><span class="description"><?php _e('NOTE: The below options are not meant for normal use and are only there for developers to review saved settings or how ads are selected. These can be used as a measure of troubleshooting upon request but for normal use they SHOULD BE LEFT UNCHECKED!!', 'adrotate'); ?></span></td>
			</tr>

			<tr>
				<th valign="top"><?php _e('Developer Debug', 'adrotate'); ?></th>
				<td>
					<input type="checkbox" name="adrotate_debug" <?php if($adrotate_debug['general'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Troubleshoot ads and how (if) they are selected, will mess up your theme!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_dashboard" <?php if($adrotate_debug['dashboard'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Show all settings, dashboard routines and related values!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_userroles" <?php if($adrotate_debug['userroles'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Show array of all userroles and capabilities!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_userstats" <?php if($adrotate_debug['userstats'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Review saved user stats (users)! Visible to advertisers!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_stats" <?php if($adrotate_debug['stats'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Review global stats, per ad/group/block stats (admins)!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_timers" <?php if($adrotate_debug['timers'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Disable timers for clicks and impressions allowing you to test the impression and click counters or stats without having to wait for the timer!', 'adrotate'); ?></span><br />
					<input type="checkbox" name="adrotate_debug_track" <?php if($adrotate_debug['track'] == true) { ?>checked="checked" <?php } ?> /> <span class="description"><?php _e('Disable encryption on the redirect url. This will NOT compromise any security!', 'adrotate'); ?></span><br />
				</td>
			</tr>
	    	</table>
	    	
		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>
		</form>
	</div>
<?php 
}

/*-------------------------------------------------------------
 Name:      adrotate_beta

 Purpose:   Admin dashboard for beta releases
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_beta() {
	global $wpdb, $current_user;
	
	$message = '';
	if(isset($_GET['message'])) $message = $_GET['message'];
?>
	<div class="wrap">

	  	<h2>Feedback submission</h2>

		<?php if ($message == 'sent') { ?>
			<div id="message" class="updated fade"><p>Feedback sent! Thanks for improving AdRotate</p></div>
		<?php } else if ($message == 'empty') { ?>
			<div id="message" class="error fade"><p>Feedback form can not be empty!</p></div>
		<?php } ?>

		<?php include("dashboard/publisher/adrotate-beta.php"); ?>
		
		<br class="clear" />
		<?php adrotate_credits(); ?>

		<br class="clear" />
	</div>
<?php 
}

?>
