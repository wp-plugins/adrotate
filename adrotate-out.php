<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)

This program is free software; you can redistribute it and/or modify it under the terms of 
the GNU General Public License, version 2, as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, visit: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
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
		wp_redirect(htmlspecialchars_decode($banner->link));
	} else {
		echo '<span style="color: #F00; font-style: italic; font-weight: bold;">There was an error retrieving the ad! Contact an administrator!</span>';
	}
} else {
	echo '<span style="color: #F00; font-style: italic; font-weight: bold;">No or invalid Ad ID specified! Contact an administrator!</span>';
}
?>