<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Purpose:   Facilitate outgoing affiliate links
 Receive:   $_GET
 Return:	-None-
 Since:		3.9.2
-------------------------------------------------------------*/
define('WP_USE_THEMES', false);
require('../../../../wp-blog-header.php');

global $wpdb, $adrotate_crawlers, $adrotate_config, $adrotate_debug;

if(isset($_GET['track']) OR $_GET['track'] != '') {
	if($adrotate_debug['track'] == true) {
		$meta = $_GET['track'];
	} else {
		$meta = base64_decode($_GET['track']);
	}

	list($ad, $group, $block) = explode(",", $meta, 3);

	$useragent 	= trim($_SERVER['HTTP_USER_AGENT'], ' \t\r\n\0\x0B');
	$prefix		= $wpdb->prefix;
	$remote_ip 	= adrotate_get_remote_ip();
	$now 		= adrotate_now();

	if($adrotate_debug['timers'] == true) {
		$ip = 0;
	} else {
		$ip = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `".$prefix."adrotate_tracker` WHERE `ipaddress` = '%s' AND `stat` = 'c' AND `timer` < $now + 86400 AND `bannerid` = %d LIMIT 1;", $remote_ip, $ad));
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

	if($ip < 1 AND !in_array(1, $nocrawler) AND !empty($useragent)) {
		$today = adrotate_date_start('day');
		$wpdb->query($wpdb->prepare("UPDATE `".$prefix."adrotate_stats` SET `clicks` = `clicks` + 1 WHERE `ad` = %d AND `group` = %d AND `block` = %d AND `thetime` = $today;", $ad, $group, $block));
		if($remote_ip != "unknown" AND !empty($remote_ip)) {
			$wpdb->insert($prefix.'adrotate_tracker', array('ipaddress' => $remote_ip, 'timer' => $now, 'bannerid' => $ad, 'stat' => 'c', 'useragent' => $useragent, 'country' => '', 'city' => ''));
		}
	}

	unset($nocrawler, $crawlers, $ip, $remote_ip, $useragent, $track, $meta, $ad, $group, $block);
	exit();
}
?>