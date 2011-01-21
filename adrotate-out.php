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

if(isset($_GET['trackerid']) OR $_GET['trackerid'] > 0 OR $_GET['trackerid'] != '') {
	$id 									= $_GET['trackerid'];	
	$useragent 								= $_SERVER['HTTP_USER_AGENT'];
	$useragent_trim 						= trim($useragent, ' \t\r\n\0\x0B');
	if(isset($_GET['preview'])) $preview 	= $_GET['preview'];	
	
	if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$remote_ip = $_SERVER["REMOTE_ADDR"];
	} else {
		$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	
	$buffer 	= explode(',', $remote_ip, 2);
	$now 		= date('U');
	$tomorrow 	= $now + 86400;
	
	$banner = $wpdb->get_row("SELECT `link` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '".$id."' LIMIT 1;");
	if($banner) {
		if(is_array($adrotate_crawlers)) $crawlers = $adrotate_crawlers;
			else $crawlers = array();
	
		$nocrawler = true;
		foreach ($crawlers as $crawler) {
			if (preg_match("/$crawler/i", $useragent)) $nocrawler = false;
		}
	
		$ip = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` = '$buffer[0]' AND `timer` < '$tomorrow' AND `bannerid` = '$id' LIMIT 1;");
		if($ip < 1 AND $nocrawler == true AND (!isset($preview) OR empty($preview)) AND (strlen($useragent_trim) > 0 OR !empty($useragent))) {
			$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `clicks` = `clicks` + 1 WHERE `id` = '$id';");
			$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_tracker` (`ipaddress`, `timer`, `bannerid`) VALUES ('$buffer[0]', '$now', '$id');");
		}
		$banner->link = str_replace('%random%', $now, $banner->link);

		wp_redirect(htmlspecialchars_decode($banner->link));
	} else {
		echo '<span style="color: #F00; font-style: italic; font-weight: bold;">There was an error retrieving the ad! Contact an administrator!</span>';
	}
} else {
	echo '<span style="color: #F00; font-style: italic; font-weight: bold;">No or invalid Ad ID specified! Contact an administrator!</span>';
}
?>