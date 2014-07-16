<?php
/*
Plugin Name: AdRotate
Plugin URI: https://www.adrotateplugin.com
Description: The very best and most convenient way to publish your ads.
Author: Arnan de Gans of AJdG Solutions
Version: 3.10.2
Author URI: http://ajdg.solutions/
License: GPLv3
*/

/*  
Copyright 2010-2014 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*--- AdRotate values ---------------------------------------*/
define("ADROTATE_DISPLAY", '3.10.2');
define("ADROTATE_VERSION", 373);
define("ADROTATE_DB_VERSION", 42);
define("ADROTATE_FOLDER", 'adrotate');
/*-----------------------------------------------------------*/

/*--- Load Files --------------------------------------------*/
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-setup.php');
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-manage-publisher.php');
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-functions.php');
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-statistics.php');
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-output.php');
include_once(WP_CONTENT_DIR.'/plugins/'.ADROTATE_FOLDER.'/adrotate-widget.php');
/*-----------------------------------------------------------*/

/*--- Check and Load config ---------------------------------*/
load_plugin_textdomain('adrotate', false, basename(dirname(__FILE__)) . '/language');
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
add_action('adrotate_clean_trackerdata', 'adrotate_clean_trackerdata');
add_action('adrotate_evaluate_ads', 'adrotate_evaluate_ads');
add_action('widgets_init', create_function('', 'return register_widget("adrotate_widgets");'));
/*-----------------------------------------------------------*/

/*--- Front end ---------------------------------------------*/
if(!is_admin()) {
	add_shortcode('adrotate', 'adrotate_shortcode');
	add_action("wp_enqueue_scripts", 'adrotate_custom_scripts');
	add_action('wp_head', 'adrotate_custom_head');
	add_filter('the_content', 'adrotate_inject_posts');
}
/*-----------------------------------------------------------*/

/*--- Back End ----------------------------------------------*/
if(is_admin()) {
	adrotate_check_config();
	add_action('admin_init', 'adrotate_check_upgrade');
	add_action('admin_menu', 'adrotate_dashboard');
	add_action("admin_enqueue_scripts", 'adrotate_dashboard_scripts');
	add_action("admin_print_styles", 'adrotate_dashboard_styles');
	add_action('admin_notices','adrotate_notifications_dashboard');
}
/*-----------------------------------------------------------*/

/*--- Internal redirects ------------------------------------*/
if(isset($_POST['adrotate_ad_submit'])) 				add_action('init', 'adrotate_insert_input');
if(isset($_POST['adrotate_group_submit'])) 				add_action('init', 'adrotate_insert_group');
if(isset($_POST['adrotate_action_submit'])) 			add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_disabled_action_submit']))	add_action('init', 'adrotate_request_action');
if(isset($_POST['adrotate_error_action_submit']))		add_action('init', 'adrotate_request_action');
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
	global $adrotate_config, $adrotate_server;

	add_menu_page('AdRotate', 'AdRotate', 'adrotate_ad_manage', 'adrotate', 'adrotate_info', plugins_url('/images/icon.png', __FILE__), '25.8');
	add_submenu_page('adrotate', 'AdRotate > '.__('General Info', 'adrotate'), __('General Info', 'adrotate'), 'adrotate_ad_manage', 'adrotate', 'adrotate_info');
	add_submenu_page('adrotate', 'AdRotate > '.__('AdRotate Pro', 'adrotate'), __('AdRotate Pro', 'adrotate'), 'adrotate_ad_manage', 'adrotate-pro', 'adrotate_pro');
	if($adrotate_server['adrotate_server_puppet'] == 0) {
		add_submenu_page('adrotate', 'AdRotate > '.__('Manage Ads', 'adrotate'), __('Manage Ads', 'adrotate'), 'adrotate_ad_manage', 'adrotate-ads', 'adrotate_manage');
	}
	add_submenu_page('adrotate', 'AdRotate > '.__('Manage Groups', 'adrotate'), __('Manage Groups', 'adrotate'), 'adrotate_group_manage', 'adrotate-groups', 'adrotate_manage_group');
	add_submenu_page('adrotate', 'AdRotate > '.__('AdRotate Server', 'adrotate'), __('AdRotate Server', 'adrotate'), 'manage_options', 'adrotate-server', 'adrotate_server');
	add_submenu_page('adrotate', 'AdRotate > '.__('Settings', 'adrotate'), __('Settings', 'adrotate'), 'manage_options', 'adrotate-settings', 'adrotate_options');
}

/*-------------------------------------------------------------
 Name:      adrotate_info

 Purpose:   Admin general info page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_info() {
	global $wpdb, $adrotate_advert_status;
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
 Name:      adrotate_pro
 
 Purpose:   AdRotate Pro Sales
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_pro() {
?>
	<div class="wrap">
		<h2><?php _e('AdRotate Professional', 'adrotate'); ?></h2>

		<br class="clear" />

		<?php include("dashboard/adrotate-pro.php"); ?>

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
	if(isset($_GET['message'])) $message = esc_attr($_GET['message']);
	if(isset($_GET['view'])) $view = esc_attr($_GET['view']);
	if(isset($_GET['ad'])) $ad_edit_id = esc_attr($_GET['ad']);
	$now 			= adrotate_now();
	$today 			= adrotate_date_start('day');
	$in2days 		= $now + 172800;
	$in7days 		= $now + 604800;
	$in84days 		= $now + 7257600;

	if(isset($_GET['month']) AND isset($_GET['year'])) {
		$month = esc_attr($_GET['month']);
		$year = esc_attr($_GET['year']);
	} else {
		$month = date("m");
		$year = date("Y");
	}
	$monthstart = mktime(0, 0, 0, $month, 1, $year);
	$monthend = mktime(0, 0, 0, $month+1, 0, $year);	
	?>
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
			$allbanners = $wpdb->get_results("SELECT `id`, `title`, `type`, `tracker`, `weight`, `cbudget`, `ibudget`, `crate`, `irate` FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'active' OR `type` = 'error' OR `type` = 'expired' OR `type` = '2days' OR `type` = '7days' OR `type` = 'disabled' ORDER BY `sortorder` ASC, `id` ASC;");
			$activebanners = $errorbanners = $disabledbanners = false;
			foreach($allbanners as $singlebanner) {
				$starttime = $stoptime = 0;
				$starttime = $wpdb->get_var("SELECT `starttime` FROM `".$wpdb->prefix."adrotate_schedule`, `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '".$singlebanner->id."' AND `schedule` = `".$wpdb->prefix."adrotate_schedule`.`id` ORDER BY `starttime` ASC LIMIT 1;");
				$stoptime = $wpdb->get_var("SELECT `stoptime` FROM `".$wpdb->prefix."adrotate_schedule`, `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '".$singlebanner->id."' AND `schedule` = `".$wpdb->prefix."adrotate_schedule`.`id` ORDER BY `stoptime` DESC LIMIT 1;");
				
				$type = $singlebanner->type;
				if($type == 'active' AND $stoptime <= $in7days) $type = '7days';
				if($type == 'active' AND $stoptime <= $in2days) $type = '2days';
				if($type == 'active' AND $stoptime <= $now) $type = 'expired'; 
	
				if($type == 'active' OR $type == '7days') {
					$activebanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'weight' => $singlebanner->weight,
						'firstactive' => $starttime,
						'lastactive' => $stoptime
					);
				}
				
				if($type == 'error' OR $type == 'expired' OR $type == '2days') {
					$errorbanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'weight' => $singlebanner->weight,
						'firstactive' => $starttime,
						'lastactive' => $stoptime
					);
				}
				
				if($type == 'disabled') {
					$disabledbanners[$singlebanner->id] = array(
						'id' => $singlebanner->id,
						'title' => $singlebanner->title,
						'type' => $type,
						'tracker' => $singlebanner->tracker,
						'weight' => $singlebanner->weight,
						'firstactive' => $starttime,
						'lastactive' => $stoptime
					);
				}
			}
			?>
			
			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-ads&view=manage');?>"><?php _e('Manage', 'adrotate'); ?></a> | 
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-ads&view=addnew');?>"><?php _e('Add New', 'adrotate'); ?></a>
				</div>
			</div>

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

		<?php adrotate_credits(); ?>

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
	if(isset($_GET['message'])) $message = esc_attr($_GET['message']);
	if(isset($_GET['view'])) $view = esc_attr($_GET['view']);
	if(isset($_GET['group'])) $group_edit_id = esc_attr($_GET['group']);

	if(isset($_GET['month']) AND isset($_GET['year'])) {
		$month = esc_attr($_GET['month']);
		$year = esc_attr($_GET['year']);
	} else {
		$month = date("m");
		$year = date("Y");
	}
	$monthstart = mktime(0, 0, 0, $month, 1, $year);
	$monthend = mktime(0, 0, 0, $month+1, 0, $year);	

	$today = adrotate_date_start('day');
	$now 			= adrotate_now();
	$today 			= adrotate_date_start('day');
	$in2days 		= $now + 172800;
	$in7days 		= $now + 604800;
	?>
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
				</div>
			</div>

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

		<?php adrotate_credits(); ?>

	</div>
<?php
}

/*-------------------------------------------------------------
 Name:      adrotate_server

 Purpose:   Connect and manage AdRotate server
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_server() {
	global $wpdb, $current_user, $userdata, $blog_id, $adrotate_config, $adrotate_debug;

	$status = $file = $view = '';
	if(isset($_GET['status'])) $status = esc_attr($_GET['status']);
	if(isset($_GET['file'])) $file = esc_attr($_GET['file']);
	if(isset($_GET['view'])) $view = esc_attr($_GET['view']);
	$now 			= adrotate_now();
	$today 			= adrotate_date_start('day');
	$in2days 		= $now + 172800;
	$in7days 		= $now + 604800;
	?>
	<div class="wrap">
	  	<h2><?php _e('AdRotate Server', 'adrotate'); ?> (BETA)</h2>

		<?php if($status > 0) adrotate_status($status, array('file' => $file)); ?>

		<?php if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_groups';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_schedule';") AND $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->prefix."adrotate_linkmeta';")) { ?>
			
			<?php if($status > 0) adrotate_status($status); ?>
			
			<p style="color:#f00;">NOTICE: <?php _e('AdRotate server is currently in development and these menus are not functional yet.', 'adrotate'); ?></p>

			<div class="tablenav">
				<div class="alignleft actions">
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-server&view=overview');?>"><?php _e('Overview', 'adrotate'); ?></a> | 
					<a class="row-title" href="<?php echo admin_url('/admin.php?page=adrotate-server&view=settings');?>"><?php _e('Settings', 'adrotate'); ?></a> 
				</div>
			</div>

			<?php
	    	if ($view == "" OR $view == "overview") {
				$allbanners = $wpdb->get_results("SELECT `id`, `title`, `thetime`, `updated`, `type`, `weight`, `cbudget`, `ibudget`, `crate`, `irate` FROM `".$wpdb->prefix."adrotate` WHERE `type` = 's_active' OR `type` = 's_error' OR `type` = 's_expired' OR `type` = 's_2days' OR `type` = 's_7days' ORDER BY `sortorder` ASC, `id` ASC;");
				
				$activebanners = $errorbanners = false;
				foreach($allbanners as $singlebanner) {
					$starttime = $stoptime = 0;
					$starttime = $wpdb->get_var("SELECT `starttime` FROM `".$wpdb->prefix."adrotate_schedule`, `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '".$singlebanner->id."' AND `schedule` = `".$wpdb->prefix."adrotate_schedule`.`id` ORDER BY `starttime` ASC LIMIT 1;");
					$stoptime = $wpdb->get_var("SELECT `stoptime` FROM `".$wpdb->prefix."adrotate_schedule`, `".$wpdb->prefix."adrotate_linkmeta` WHERE `ad` = '".$singlebanner->id."' AND  `schedule` = `".$wpdb->prefix."adrotate_schedule`.`id` ORDER BY `stoptime` DESC LIMIT 1;");
		
					$type = $singlebanner->type;
					if($type == 's_active' AND $stoptime <= $now) $type = 's_expired'; 
					if($type == 's_active' AND $stoptime <= $in2days) $type = 's_2days';
					if($type == 's_active' AND $stoptime <= $in7days) $type = 's_7days';
					if(($singlebanner->crate > 0 AND $singlebanner->cbudget < 1) OR ($singlebanner->irate > 0 AND $singlebanner->ibudget < 1)) $type = 's_expired';
		
					if($type == 's_active' OR $type == 's_7days') {
						$activebanners[$singlebanner->id] = array(
							'id' => $singlebanner->id,
							'title' => $singlebanner->title,
							'type' => $type,
							'weight' => $singlebanner->weight,
							'added' => $singlebanner->thetime,
							'updated' => $singlebanner->updated,
							'firstactive' => $starttime,
							'lastactive' => $stoptime
						);
					}
					
					if($type == 's_error' OR $type == 's_expired' OR $type == 's_2days') {
						$errorbanners[$singlebanner->id] = array(
							'id' => $singlebanner->id,
							'title' => $singlebanner->title,
							'type' => $type,
							'weight' => $singlebanner->weight,
							'updated' => $singlebanner->updated,
							'firstactive' => $starttime,
							'lastactive' => $stoptime
						);
					}
				}

				include("dashboard/server/adrotate-active.php");
				if ($errorbanners) {
					include("dashboard/server/adrotate-error.php");
				}
		   	} else if($view == "settings") { 
				include("dashboard/server/adrotate-settings.php");
			}
		} else {
			echo adrotate_error('db_error');
		}
		?>
		<br class="clear" />

		<?php adrotate_credits(); ?>
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
	global $wpdb, $wp_roles;

	$adrotate_config 			= get_option('adrotate_config');
	$adrotate_crawlers 			= get_option('adrotate_crawlers');
	$adrotate_roles				= get_option('adrotate_roles');
	$adrotate_debug				= get_option('adrotate_debug');
	$adrotate_version			= get_option('adrotate_version');
	$adrotate_db_version		= get_option('adrotate_db_version');
	$adrotate_advert_status		= get_option("adrotate_advert_status");

	$crawlers = '';
	if(is_array($adrotate_crawlers)) {
		$crawlers = implode(', ', $adrotate_crawlers);
	}

	$message = $corrected = $converted = '';
	if(isset($_GET['message'])) $message = esc_attr($_GET['message']);

	$converted = base64_decode($converted);
	$adtracker = wp_next_scheduled('adrotate_clean_trackerdata');
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

			<h3><?php _e('Access Rights', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Who has access to what?', 'adrotate'); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Manage/Add/Edit adverts', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_ad_manage"><select name="adrotate_ad_manage">
							<?php wp_dropdown_roles($adrotate_config['ad_manage']); ?>
						</select> <?php _e('Role to see and add/edit ads.', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Delete/Reset adverts', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_ad_delete"><select name="adrotate_ad_delete">
							<?php wp_dropdown_roles($adrotate_config['ad_delete']); ?>
						</select> <?php _e('Role to delete ads and reset stats.', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Manage/Add/Edit groups', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_group_manage"><select name="adrotate_group_manage">
							<?php wp_dropdown_roles($adrotate_config['group_manage']); ?>
						</select> <?php _e('Role to see and add/edit groups.', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Delete groups', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_group_delete"><select name="adrotate_group_delete">
							<?php wp_dropdown_roles($adrotate_config['group_delete']); ?>
						</select> <?php _e('Role to delete groups.', 'adrotate'); ?></label>
					</td>
				</tr>

				<?php if($adrotate_debug['userroles'] == true) { ?>
				<tr>
					<td colspan="2">
						<?php 
						echo "<p><strong>[DEBUG] AdRotate Advertiser role enabled? (0 = no, 1 = yes)</strong><pre>"; 
						print_r($adrotate_roles); 
						echo "</pre></p>"; 
						echo "<p><strong>[DEBUG] Current User Capabilities</strong><pre>"; 
						print_r($wp_roles); 
						echo "</pre></p>"; 
						?>
					</td>
				</tr>
				<?php } ?>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>

			<h3><?php _e('Advertisers', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Enable advertisers so they can review and manage their own ads.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Enable Advertisers', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_enable_advertisers"><input type="checkbox" name="adrotate_enable_advertisers" disabled /> <?php _e('Allow adverts to be coupled to users (Advertisers).', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Edit/update adverts', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_enable_editing"><input type="checkbox" name="adrotate_enable_editing" disabled /> <?php _e('Allow advertisers to add new or edit their adverts.', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Advertiser role', 'adrotate'); ?></th>
					<td>
						<input type="submit" id="post-role-submit" name="adrotate_role_add_submit" value="<?php _e('Create Role', 'adrotate'); ?>" class="button-secondary" disabled />
						<br /><span class="description"><?php _e('This role has no capabilities unless you assign them using the above options. Obviously you should use this with care.', 'adrotate'); ?><br />
						<?php _e('This type of user is NOT required to use AdRotate or any of it\'s features. It merely helps you to seperate advertisers from regular subscribers without giving them too much access to your dashboard.', 'adrotate'); ?></span>
					</td>
				</tr>
			</table>

			<?php
			if($adrotate_debug['dashboard'] == true) {
				echo "<p><strong>[DEBUG] Globalized Config</strong><pre>"; 
				print_r($adrotate_config); 
				echo "</pre></p>"; 
			}
			?>

			<h3><?php _e('Banner Folder', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Set a location where your banner images will be stored.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Location', 'adrotate'); ?></th>
					<td>
						<label for="adrotate_banner_folder"><?php echo home_url(); ?>/<input name="adrotate_banner_folder" type="text" class="search-input" size="30" value="wp-content/banners/" disabled /> <?php _e('(Default: wp-content/banners/).', 'adrotate'); ?><br />
						<span class="description"><?php _e('To try and trick ad blockers you could set the folder to something crazy like:', 'adrotate'); ?> "/wp-content/<?php echo adrotate_rand(12); ?>/".<br />
						<?php _e("This folder will not be automatically created if it doesn't exist. AdRotate will show errors when the folder is missing.", 'adrotate'); ?></span>
					</td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>

			<h3><?php _e('Notifications', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Set up who gets notifications if ads need your attention.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Delivery method', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_notification_push" disabled /> <?php _e('Push notifications to your smartphone.', 'adrotate'); ?><br />
						<input type="checkbox" name="adrotate_notification_email" disabled /> <?php _e('Email message.', 'adrotate'); ?><br />
						<span class="description"><?php _e('Push notifications are delivered through Pushover, a notification service for Android and iOS', 'adrotate'); ?><br /><?php _e('The Pushover App is a one time purchase for either Android and/or iOS. More information can be found on the pushover website;', 'adrotate'); ?> <a href="http://www.pushover.net" target="_blank">pushover.net</a>.</span>
					</td>
				</tr>
			</table>
			
			<h3><?php _e('Push Notifications', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Receive information about what is happening with your AdRotate setup on your smartphone via Pushover.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Publishers', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_notification_push_status" disabled /> <?php _e('Daily digest of any advert status other than normal.', 'adrotate'); ?><br /><br />
						<input type="checkbox" name="adrotate_notification_push_queue" disabled /> <?php _e('Any advertiser saving an advert in your moderation queue.', 'adrotate'); ?><br />
						<input type="checkbox" name="adrotate_notification_push_approved" disabled /> <?php _e('A moderator approved an advert from the moderation queue.', 'adrotate'); ?><br />
						<input type="checkbox" name="adrotate_notification_push_rejected" disabled /> <?php _e('A moderator rejected an advert from the moderation queue.', 'adrotate'); ?><br /><span class="description"><?php _e('If you have a lot of activity with many advertisers adding/changing adverts you may get a lot of messages!', 'adrotate'); ?></span>

					</td>
				</tr>
			</table>

			<h3><?php _e('Email Notifications', 'adrotate'); ?></h3>
			<span class="description"><?php _e('Set up who gets notification emails.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Publishers', 'adrotate'); ?></th>
					<td>
						<textarea name="adrotate_notification_email_publisher" cols="90" rows="2" disabled>youremail@example.com</textarea><br />
						<span class="description"><?php _e('A comma separated list of email addresses. Maximum of 5 addresses. Keep this list to a minimum!', 'adrotate'); ?><br />
						<?php _e('Messages are sent once every 24 hours when needed. If this field is empty no email notifications will be send.', 'adrotate'); ?></span>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Advertisers', 'adrotate'); ?></th>
					<td>
						<textarea name="adrotate_notification_email_advertiser" cols="90" rows="2" disabled>youremail@example.com</textarea><br />
						<span class="description"><?php _e('Who gets email from advertisers. Maximum of 2 addresses. Comma seperated. This field may not be empty!', 'adrotate'); ?></span>
					</td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>

			<h3><?php _e('Statistics', 'adrotate'); ?></h3></td>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Enable stats', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_enable_stats" <?php if($adrotate_config['enable_stats'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('Track clicks and impressions.', 'adrotate'); ?><br /><span class="description"><?php _e('Disabling this also disables click and impression limits on schedules and disables timeframes.', 'adrotate'); ?></span><br />
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Logged in impressions', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_enable_loggedin_impressions" checked disabled /> <?php _e('Track impressions from logged in users (Recommended).', 'adrotate'); ?><br /><span class="description"><?php _e('Has no effect when click and impression tracking is disabled.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span><br />
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Logged in clicks', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_enable_loggedin_clicks" checked disabled /> <?php _e('Track clicks from logged in users.', 'adrotate'); ?></br><span class="description"><?php _e('Has no effect when click and impression tracking is disabled.', 'adrotate'); ?><br /><?php adrotate_pro_notice(); ?></span>
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
					<th valign="top"><?php _e('Clicks timer', 'adrotate'); ?></th>
					<td>
						<input name="adrotate_click_timer" type="text" class="search-input" size="5" value="<?php echo $adrotate_config['click_timer']; ?>" autocomplete="off" /> <?php _e('Seconds.', 'adrotate'); ?><br />
						<span class="description"><?php _e('Default: 86400. Set to 0 to disable this timer.', 'adrotate'); ?><br /><?php _e('This number may not be empty, negative or exceed 86400 (24 hours).', 'adrotate'); ?></span>
					</td>
				</tr>
			</table>

			<h3><?php _e('Bot filter', 'adrotate'); ?></h3></td>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('User-Agent Filter', 'adrotate'); ?></th>
					<td>
						<textarea name="adrotate_crawlers" cols="90" rows="15"><?php echo $crawlers; ?></textarea><br />
						<span class="description"><?php _e('A comma separated list of keywords. Filter out bots/crawlers/user-agents. To prevent impressions and clicks counted on them.', 'adrotate'); ?><br />
						<?php _e('Keep in mind that this might give false positives. The word \'google\' also matches \'googlebot\', but not vice-versa. So be careful!', 'adrotate'); ?>. <?php _e('Keep your list up-to-date', 'adrotate'); ?> <a href="http://www.robotstxt.org/db.html" target="_blank">robotstxt.org/db.html</a>.<br />
						<?php _e('Use only words with alphanumeric characters, [ - _ ] are allowed too. All other characters are stripped out.', 'adrotate'); ?><br />
						<?php _e('Additionally to the list specified here, empty User-Agents are blocked as well.', 'adrotate'); ?> (<?php _e('Learn more about', 'adrotate'); ?> <a href="http://en.wikipedia.org/wiki/User_agent" title="User Agents" target="_blank"><?php _e('user-agents', 'adrotate'); ?></a>.)</span>
					</td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>
			
			<h3><?php _e('Geo Targeting', 'adrotate'); ?></h3>
			<span class="description"><?php adrotate_pro_notice(); ?></span>
			<table class="form-table">
				<tr>
					<th valign="top"><?php _e('Geographic Tracking', 'adrotate'); ?></th>
					<td><input disabled type="checkbox" name="adrotate_enable_geo" /> <?php _e('Enable Freegeoip for adverts.', 'adrotate'); ?><br />
						<span class="description"><?php _e('Consider making a donation to', 'adrotate'); ?> <a href="http://www.freegeoip.net" target="_blank">Freegeoip</a> <?php _e('to keep their services free and awesome!', 'adrotate'); ?><br />Positional data includes GeoLite data created by MaxMind, available from <a href="http://www.maxmind.com" target="_blank">maxmind.com</a>.</span>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Advertisers', 'adrotate'); ?></th>
					<td>
						<input disabled type="checkbox" name="adrotate_enable_geo_advertisers" /> <?php _e('Allow advertisers to specify where their ads will show.', 'adrotate'); ?>
					</td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>
			
			<h3><?php _e('Miscellaneous', 'adrotate'); ?></h3>
			<table class="form-table">			
				<tr>
					<th valign="top"><?php _e('Widget alignment', 'adrotate'); ?></th>
					<td><label for="adrotate_widgetalign"><input type="checkbox" name="adrotate_widgetalign" <?php if($adrotate_config['widgetalign'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('Check this box if your widgets do not align in your themes sidebar. (Does not always help!)', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Widget padding', 'adrotate'); ?></th>
					<td><label for="adrotate_widgetpadding"><input type="checkbox" name="adrotate_widgetpadding" <?php if($adrotate_config['widgetpadding'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('Enable this to remove the padding (blank space) around ads in widgets. (Does not always work!)', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Admin Bar', 'adrotate'); ?></th>
					<td><label for="adrotate_adminbar"><input type="checkbox" name="adrotate_adminbar" disabled /> <?php _e('Enable the AdRotate Quickmenu in the Admin Bar', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Dashboard Notifications', 'adrotate'); ?></th>
					<td><label for="adrotate_dashboard_notifications"><input type="checkbox" name="adrotate_dashboard_notifications" disabled /> <?php _e('Disable Dashboard Notifications about advert statuses.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Hide Schedules', 'adrotate'); ?></th>
					<td><label for="adrotate_hide_schedules"><input type="checkbox" name="adrotate_hide_schedules" disabled /> <?php _e('When editing adverts; Hide schedules that are not in use by that advert.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('W3 Total Caching', 'adrotate'); ?></th>
					<td><label for="adrotate_w3caching"><input type="checkbox" name="adrotate_w3caching" <?php if($adrotate_config['w3caching'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('Check this box if you use W3 Total Caching on your site.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('WP Super Cache', 'adrotate'); ?></th>
					<td><label for="adrotate_supercache"><input type="checkbox" name="adrotate_supercache" <?php if($adrotate_config['supercache'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('Check this box if you use WP Super Cache on your site.', 'adrotate'); ?></label><br />
					<em><?php _e('It is reported by users that if you have version 1.4 or newer this option may not work.', 'adrotate'); ?></em></td>
				</tr>
				<tr>
					<th valign="top">&nbsp;</th>
					<td><span class="description"><?php _e('It may take a while for the ad to start rotating. The caching plugin needs to refresh the cache. This can take up to a week if not done manually.', 'adrotate'); ?><br /><?php _e('Caching support only works for [shortcodes] and the AdRotate Widget. If you use a PHP Snippet you need to wrap your PHP in the exclusion code yourself.', 'adrotate'); ?><br /><?php _e('Check the manual of your caching plugin to see how this works.', 'adrotate'); ?></span></td>
				</tr>
			</table>

			<h3><?php _e('Javascript Libraries', 'adrotate'); ?></h3>
			<table class="form-table">			
				<tr>
					<th valign="top"><?php _e('Load jQuery', 'adrotate'); ?></th>
					<td><label for="adrotate_jquery"><input type="checkbox" name="adrotate_jquery" <?php if($adrotate_config['jquery'] == 'Y') { ?>checked="checked" <?php } ?> /> <?php _e('jQuery is required for all Javascript features below. Enable this if your theme does not load jQuery already.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Load Dynamic Groups', 'adrotate'); ?></th>
					<td><label for="adrotate_jshowoff"><input type="checkbox" name="adrotate_jshowoff" <?php if($adrotate_config['jshowoff'] == 'Y') { ?>checked="checked" <?php } ?> /><?php _e('Required for Dynamic Groups to rotate ads.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Load jQuery Clicktracking', 'adrotate'); ?></th>
					<td><label for="adrotate_clicktracking"><input type="checkbox" name="adrotate_clicktracking" <?php if($adrotate_config['clicktracking'] == 'Y') { ?>checked="checked" <?php } ?> /><?php _e('Required for jQuery Clicktracking. When disabled AdRotate falls back on Redirect Tracking.', 'adrotate'); ?></label></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Load Ad Blocker Detection', 'adrotate'); ?></th>
					<td><label for="adrotate_adblock"><input type="checkbox" name="adrotate_adblock" disabled /> <?php _e('Try to detect Ad Blockers and show your visitors a nag message.', 'adrotate'); ?></label>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Load in footer?', 'adrotate'); ?></th>
					<td><label for="adrotate_jsfooter"><input type="checkbox" name="adrotate_jsfooter" <?php if($adrotate_config['jsfooter'] == 'Y') { ?>checked="checked" <?php } ?> /><?php _e('Enable if you want to load the above libraries in the footer. Your theme needs to call wp_footer() for this to work.', 'adrotate'); ?></label></td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>

			<h3><?php _e('Maintenance', 'adrotate'); ?></h3>
			<span class="description"><?php _e('NOTE: The below functions are intented to be used to OPTIMIZE your database. They only apply to your ads/groups and stats. Not to other settings or other parts of WordPress! Always always make a backup! These functions are to be used when you feel or notice your database is slow, unresponsive and sluggish.', 'adrotate'); ?></span>
			<?php 
			if($adrotate_debug['dashboard'] == true) {
				echo "<p><strong>[DEBUG] List of tables</strong><pre>";
				$tables = adrotate_list_tables();
				print_r($tables); 
				echo "</pre></p>"; 

				echo "<p><strong>[DEBUG] Current ad states</strong><pre>";
				print_r(get_option("adrotate_advert_status")); 
				echo "</pre></p>"; 
			} 
			?>
			<table class="form-table">			
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
						<input type="submit" id="post-role-submit" name="adrotate_db_cleanup_submit" value="<?php _e('Clean-up Database', 'adrotate'); ?>" class="button-secondary" onclick="return confirm('<?php _e('You are about to clean up your database. This may delete expired schedules and older statistics.', 'adrotate'); ?>\n\n<?php _e('Are you sure you want to continue?', 'adrotate'); ?>\n\n<?php _e('This might take a while and may slow down your site during this action!', 'adrotate'); ?>\n\n<?php _e('OK to continue, CANCEL to stop.', 'adrotate'); ?>')" /><br />
						<label for="adrotate_db_cleanup_statistics"><input type="checkbox" name="adrotate_db_cleanup_statistics" /> <?php _e('Delete stats older than 356 days (Optional).', 'adrotate'); ?></label><br />
						<span class="description"><?php _e('AdRotate creates empty records when you start making ads or groups. In rare occasions these records are faulty.', 'adrotate'); ?><br /><?php _e('If you made an ad or group that does not save when you make it use this button to delete those empty records.', 'adrotate'); ?><br /><?php _e('Additionally you can clean up old statistics. This will improve the speed of your site.', 'adrotate'); ?></span>
					</td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Re-evaluate Ads', 'adrotate'); ?></th>
					<td>
						<input type="submit" id="post-role-submit" name="adrotate_evaluate_submit" value="<?php _e('Re-evaluate all ads', 'adrotate'); ?>" class="button-secondary" onclick="return confirm('<?php _e('You are about to check all ads for errors.', 'adrotate'); ?>\n\n<?php _e('This might take a while and may slow down your site during this action!', 'adrotate'); ?>\n\n<?php _e('OK to continue, CANCEL to stop.', 'adrotate'); ?>')" /><br />
						<span class="description"><?php _e('This will apply all evaluation rules to all ads to see if any error slipped in. Normally you should not need this feature.', 'adrotate'); ?></span>
					</td>
				</tr>
				<tr>
					<td colspan="2"><span class="description"><?php _e('DISCLAIMER: If for any reason your data is lost, damaged or otherwise becomes unusable in any way or by any means in whichever way I will not take responsibility. You should always have a backup of your database. These functions do NOT destroy data. If data is lost, damaged or unusable, your database likely was beyond repair already. Claiming it worked before clicking these buttons is not a valid point in any case.', 'adrotate'); ?></span></td>
				</tr>
			</table>

		    <p class="submit">
		      	<input type="submit" name="adrotate_options_submit" class="button-primary" value="<?php _e('Update Options', 'adrotate'); ?>" />
		    </p>

			<h3><?php _e('Troubleshooting', 'adrotate'); ?></h3>
			<table class="form-table">			
				<tr>
					<td><?php _e('Current version:', 'adrotate'); ?> <?php echo $adrotate_version['current']; ?></td>
					<td><?php _e('Previous version:', 'adrotate'); ?> <?php echo $adrotate_version['previous']; ?></td>
				</tr>
				<tr>
					<td><?php _e('Current database version:', 'adrotate'); ?> <?php echo $adrotate_db_version['current']; ?></td>
					<td><?php _e('Previous database version:', 'adrotate'); ?> <?php echo $adrotate_db_version['previous']; ?></td>
				</tr>
				<tr>
					<td><?php _e('Clean Trackerdata next run:', 'adrotate'); ?></td>
					<td><?php if(!$adtracker) _e('Not scheduled!', 'adrotate'); else echo date_i18n(get_option('date_format')." H:i", $adtracker); ?></td>
				</tr>
				<tr>
					<th valign="top"><?php _e('Current status of adverts', 'adrotate'); ?></th>
					<td><?php _e('Normal', 'adrotate'); ?>: <?php echo $adrotate_advert_status['normal']; ?>, <?php _e('Error', 'adrotate'); ?>: <?php echo $adrotate_advert_status['error']; ?>, <?php _e('Expired', 'adrotate'); ?>: <?php echo $adrotate_advert_status['expired']; ?>, <?php _e('Expires Soon', 'adrotate'); ?>: <?php echo $adrotate_advert_status['expiressoon']; ?>, <?php _e('Unknown Status', 'adrotate'); ?>: <?php echo $adrotate_advert_status['unknown']; ?>.</td>
				</tr>
				<tr>
					<td colspan="2"><span class="description"><?php _e('NOTE: The below options are not meant for normal use and are only there for developers to review saved settings or how ads are selected. These can be used as a measure of troubleshooting upon request but for normal use they SHOULD BE LEFT UNCHECKED!!', 'adrotate'); ?></span></td>
				</tr>
	
				<tr>
					<th valign="top"><?php _e('Developer Debug', 'adrotate'); ?></th>
					<td>
						<input type="checkbox" name="adrotate_debug" <?php if($adrotate_debug['general'] == true) { ?>checked="checked" <?php } ?> /> General - <span class="description"><?php _e('Troubleshoot ads and how (if) they are selected, has front-end output.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_dashboard" <?php if($adrotate_debug['dashboard'] == true) { ?>checked="checked" <?php } ?> /> Dashboard - <span class="description"><?php _e('Show all settings, dashboard routines and related values.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_userroles" <?php if($adrotate_debug['userroles'] == true) { ?>checked="checked" <?php } ?> /> User Roles - <span class="description"><?php _e('Show array of all userroles and capabilities.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_userstats" <?php if($adrotate_debug['userstats'] == true) { ?>checked="checked" <?php } ?> /> Userstats - <span class="description"><?php _e('Review saved advertisers! Visible to advertisers.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_stats" <?php if($adrotate_debug['stats'] == true) { ?>checked="checked" <?php } ?> /> Stats - <span class="description"><?php _e('Review global stats, per ad/group stats. Visible only to publishers.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_timers" <?php if($adrotate_debug['timers'] == true) { ?>checked="checked" <?php } ?> /> Clicktracking - <span class="description"><?php _e('Disable timers for clicks and impressions and enable a alert window for clicktracking.', 'adrotate'); ?></span><br />
						<input type="checkbox" name="adrotate_debug_track" <?php if($adrotate_debug['track'] == true) { ?>checked="checked" <?php } ?> /> Tracking Encryption - <span class="description"><?php _e('Temporarily disable encryption on the redirect url.', 'adrotate'); ?></span><br />
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
?>
