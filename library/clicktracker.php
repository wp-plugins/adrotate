<?php
/*  
Copyright 2010-2014 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

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

			if($adrotate_debug['timers'] == true) {
				$adrotate_config['click_timer'] = 0;
			}

			if(is_array($adrotate_crawlers)) {
				$crawlers = $adrotate_crawlers;
			} else {
				$crawlers = array();
			}
		
			$nocrawler = array(0);
			foreach ($crawlers as $crawler) {
				if(preg_match("/$crawler/i", $useragent)) $nocrawler[] = 1;
			}
	
			if(!in_array(1, $nocrawler) AND !empty($useragent)) {
				$today = adrotate_date_start('day');
	
				$stats = $wpdb->get_var($wpdb->prepare("SELECT `id` FROM `".$prefix."adrotate_stats` WHERE `ad` = %d AND `group` = %d AND `thetime` = $today;", $ad, $group));
				if($stats > 0) {
					$wpdb->query($wpdb->prepare("UPDATE `".$prefix."adrotate_stats`, `".$prefix."adrotate_tracker` SET `clicks` = `clicks` + 1 WHERE `".$prefix."adrotate_stats`.`id` = %d AND `".$prefix."adrotate_stats`.`ad` = %d AND (`".$prefix."adrotate_stats`.`ad` = `".$prefix."adrotate_tracker`.`bannerid` AND `stat` = 'c' AND `timer` < $now - ".$adrotate_config['click_timer'].");", $stats, $ad));
				} else {
					$wpdb->insert($prefix.'adrotate_stats', array('ad' => $ad, 'group' => $group, 'block' => 0, 'thetime' => $today, 'clicks' => 1, 'impressions' => 1));
				}
				
				if($remote_ip != "unknown" AND !empty($remote_ip)) {
					$wpdb->insert($prefix.'adrotate_tracker', array('ipaddress' => $remote_ip, 'timer' => $now, 'bannerid' => $ad, 'stat' => 'c', 'useragent' => $useragent, 'country' => '', 'city' => ''));
				}
			}
		}
	
		if($remote == 1) {
			$bannerurl = $wpdb->get_var("SELECT `link` FROM `".$prefix."adrotate` WHERE `id` = $ad;");
			wp_redirect(htmlspecialchars_decode($bannerurl), 302);		
		}
		unset($nocrawler, $crawlers, $remote_ip, $useragent, $track, $meta, $ad, $group, $remote, $bannerurl);
	}
}
exit();
?>