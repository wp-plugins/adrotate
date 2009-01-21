<?php
include('../../../wp-blog-header.php');
global $wpdb;

$id = $_GET['trackerid'];

if(empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
	$remote_ip = $_SERVER["REMOTE_ADDR"];
} else {
	$remote_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
}

$buffer = explode(',', $remote_ip, 2);
$now = date('U');
$tomorrow = $now + 86400;

$SQL = "SELECT `link` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '".$id."' LIMIT 1";
if($banner = $wpdb->get_row($SQL)) {
	$SQLcheckip = "SELECT `id` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `ipaddress` = '".$buffer[0]."' AND `timer` < '$tomorrow' LIMIT 1";
	if(!$ip = $wpdb->get_row($SQLcheckip)) {
		$wpdb->query("UPDATE `".$wpdb->prefix."adrotate` SET `clicks` = `clicks` + 1 WHERE `id` = '$id'");
		$wpdb->query("INSERT INTO `".$wpdb->prefix."adrotate_tracker` (`ipaddress`, `timer`) VALUES ('$buffer[0]', '$now')");
	}
	wp_redirect($banner->link);
} else { 
	echo '<span style="color: #F00; font-style: italic; font-weight: bold;">There was an error retrieving the banner! Contact an administrator!</span>';
}
?>