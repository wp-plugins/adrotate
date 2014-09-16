<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark (pending registration) of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

/*-------------------------------------------------------------
 Purpose:   Facilitate outgoing affiliate links
 Receive:   $_GET
 Return:	-None-
 Since:		3.9.2
-------------------------------------------------------------*/
if(isset($_POST['track']) OR isset($_GET['track'])) {
	require('../../../../wp-load.php');

	global $wpdb, $adrotate_crawlers, $adrotate_config, $adrotate_debug, $adrotate_geo;

	if(isset($_POST['track'])) {
		$meta = $_POST['track'];
	} else {
		$meta = $_GET['track'];
	}

	if($adrotate_debug['track'] != true) {
		$meta = base64_decode($meta);
	}
	
	$meta = esc_attr($meta);
	list($ad, $group, $remote, $blog_id) = explode(",", $meta, 4);

	if(is_numeric($ad) AND is_numeric($group) AND is_numeric($remote) AND is_numeric($blog_id)) {
		$useragent = trim($_SERVER['HTTP_USER_AGENT'], ' \t\r\n\0\x0B');
		$prefix = $wpdb->get_blog_prefix($blog_id);
		$remote_ip = adrotate_get_remote_ip();
		$now = adrotate_now();
	
		if(($adrotate_config['enable_loggedin_clicks'] == 'Y' AND is_user_logged_in()) OR !is_user_logged_in()) {

			if(is_array($adrotate_crawlers)) {
				$crawlers = $adrotate_crawlers;
			} else {
				$crawlers = array();
			}
		
			$nocrawler = array(0);
			foreach ($crawlers as $crawler) {
				if(preg_match("/$crawler/i", $useragent)) $nocrawler[] = 1;
			}
	
			if(!in_array(1, $nocrawler) AND !empty($useragent) AND $remote_ip != "unknown" AND !empty($remote_ip)) {
				$today = adrotate_date_start('day');

				if($adrotate_debug['timers'] == true) {
					$impression_timer = $now;
				} else {
					$impression_timer = $now - $adrotate_config['click_timer'];
				}
	
				$ip = $wpdb->get_var($wpdb->prepare("SELECT `timer` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` = '%s' AND `stat` = 'c' AND `bannerid` = %d ORDER BY `timer` DESC LIMIT 1;", $remote_ip, $ad));
				if($ip < $impression_timer) {
					$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$wpdb->prefix."adrotate_stats` WHERE `ad` = %d AND `group` = %d AND `thetime` = $today;", $ad, $group));
					if($stats > 0) {
						$wpdb->query("UPDATE `".$wpdb->prefix."adrotate_stats` SET `clicks` = `clicks` + 1 WHERE `id` = $stats;");
					} else {
						$wpdb->insert($wpdb->prefix.'adrotate_stats', array('ad' => $ad, 'group' => $group, 'block' => 0, 'thetime' => $today, 'clicks' => 1, 'impressions' => 1));
					}
					$wpdb->insert($prefix.'adrotate_tracker', array('ipaddress' => $remote_ip, 'timer' => $now, 'bannerid' => $ad, 'stat' => 'c', 'useragent' => $useragent, 'country' => '', 'city' => ''));
				}
			}
		}
	
		// Redirect for external ads
		if($remote == 1) {
			$bannerurl = $wpdb->get_var("SELECT `link` FROM `".$prefix."adrotate` WHERE `id` = $ad;");
			wp_redirect(htmlspecialchars_decode($bannerurl), 302);		
		}
		unset($nocrawler, $crawlers, $remote_ip, $useragent, $track, $meta, $ad, $group, $remote, $banner);
	}
}
?>