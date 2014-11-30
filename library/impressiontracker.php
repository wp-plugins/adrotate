<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

/*-------------------------------------------------------------
 Purpose:   Facilitate outgoing affiliate links
 Receive:   $_POST
 Return:	-None-
 Since:		3.9.2
-------------------------------------------------------------*/
if(isset($_POST['track'])) {
	require('../../../../wp-load.php');

	global $wpdb, $adrotate_crawlers, $adrotate_config, $adrotate_debug;

	$meta = $_POST['track'];
	if($adrotate_debug['track'] != true) {
		$meta = base64_decode($meta);
	}

	$meta = esc_attr($meta);
	list($ad, $group, $remote, $blog_id) = explode(",", $meta, 4);

	if(is_numeric($ad) AND is_numeric($group) AND $adrotate_config['enable_stats'] == 'Y') {
		adrotate_count_impression($ad, $group);
	}
}
?>