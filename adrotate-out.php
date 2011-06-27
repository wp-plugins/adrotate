<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)
*/

/*-------------------------------------------------------------
 Purpose:   Facilitate outgoing affiliate links
 Receive:   $_GET
 Return:	-None-
 Since:		2.0
-------------------------------------------------------------*/

include('../../../wp-blog-header.php');
global $wpdb, $adrotate_crawlers;

if(isset($_GET['track']) OR $_GET['track'] != '') {
	$meta 									= urldecode($_GET['track']);	
	$useragent 								= $_SERVER['HTTP_USER_AGENT'];
	$useragent_trim 						= trim($useragent, ' \t\r\n\0\x0B');
	$prefix									= $wpdb->prefix;

	if(isset($_GET['preview'])) $preview 	= $_GET['preview'];	
	list($ad, $group, $block) = explode("-", $meta);
	if($group > 0) $grouporblock = " AND `group` = '$group'";
	if($block > 0) $grouporblock = " AND `block` = '$block'";

	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$remote_ip = $_SERVER["REMOTE_ADDR"];
	} else {
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	
	$buffer 	= explode(',', $remote_ip, 2);
	$now 		= date('U');
	$today 		= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
	$tomorrow 	= $now + 86400;
	
	$bannerurl = $wpdb->get_var("SELECT `link` FROM `".$prefix."adrotate` WHERE `id` = '".$ad."' LIMIT 1;");
	if($bannerurl) {
		if(is_array($adrotate_crawlers)) $crawlers = $adrotate_crawlers;
			else $crawlers = array();
	
		$nocrawler = true;
		foreach ($crawlers as $crawler) {
			if (preg_match("/$crawler/i", $useragent)) $nocrawler = false;
		}
	
		$ip = $wpdb->get_var("SELECT COUNT(*) FROM `".$prefix."adrotate_tracker` WHERE `ipaddress` = '$buffer[0]' AND `timer` < '$tomorrow' AND `bannerid` = '$ad' LIMIT 1;");
		if($ip < 1 AND $nocrawler == true AND (!isset($preview) OR empty($preview)) AND (strlen($useragent_trim) > 0 OR !empty($useragent))) {
			$wpdb->query("UPDATE `".$prefix."adrotate_stats_tracker` SET `clicks` = `clicks` + 1 WHERE `ad` = '$ad'$grouporblock AND `thetime` = '$today';");
			$wpdb->query("INSERT INTO `".$prefix."adrotate_tracker` (`ipaddress`, `timer`, `bannerid`) VALUES ('$buffer[0]', '$now', '$ad');");
		}
		$bannerurl = str_replace('%random%', $now, $bannerurl);

		header('Location: '.htmlspecialchars_decode($bannerurl));
	} else {
		echo '<span style="color: #F00; font-style: italic; font-weight: bold;">There was an error retrieving the ad! Contact an administrator!</span>';
	}
} else {
	echo '<span style="color: #F00; font-style: italic; font-weight: bold;">No or invalid Ad ID specified! Contact an administrator!</span>';
}
?>