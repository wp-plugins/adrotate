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
 Name:      adrotate_has_cookie

 Purpose:   Check if a certain AdRotate Cookie exists
 Receive:   $get
 Return:    Boolean
 Since:		3.11.3
-------------------------------------------------------------*/
function adrotate_has_cookie($get, $ad_id = 0) {
	if($get = 'geo') {
		if(isset($_COOKIE['adrotate-geo'])) return true;
	}
	if($get = 'track' AND $ad_id > 0) {
		if(isset($_COOKIE['adrotate-track-'.$ad_id])) return true;
	}
	return false;
}

/*-------------------------------------------------------------
 Name:      adrotate_get_cookie

 Purpose:   Get a certain AdRotate Cookie
 Receive:   $get, $ad_id
 Return:    $data, boolean
 Since:		3.11.3
-------------------------------------------------------------*/
function adrotate_get_cookie($get, $ad_id = 0) {
	$data = false;
	if($get = 'geo') {
		$data = (isset($_COOKIE['adrotate-geo'])) ? $_COOKIE['adrotate-geo'] : '';
	}
	if($get = 'track' AND $ad_id > 0) {
		$data = (isset($_COOKIE['adrotate-track-'.$ad_id])) ? $_COOKIE['adrotate-track-'.$ad_id] : '0,0';
	}
	return maybe_unserialize(stripslashes($data));
}

/*-------------------------------------------------------------
 Name:      adrotate_cookie_impressions

 Purpose:   Save advert tracking data in a cookie
 Receive:   $ad_id, $now
 Return:    -None-
 Since:		3.11.3
-------------------------------------------------------------*/
function adrotate_cookie_impressions($ad_id = 0, $now = 0, $type = false) {
	if($ad_id > 0) {
		$data = explode(',', adrotate_get_cookie('track', $ad_id));

		$impression = $now;
		$click = ($data[1] > 0) ? $data[1] : 0;

		if(!$type){
			setcookie('adrotate-track-'.$ad_id, $impression.','.$click, $now + 86400, COOKIEPATH, COOKIE_DOMAIN);
		} else {
		?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					if(jQuery.fn.cookie){
						jQuery.cookie(
							'adrotate-track-<?php echo $ad_id;?>', 
							'<?php echo $impression; ?>,<?php echo $click; ?>', 
							{ expires: 1, path: '<?php echo COOKIEPATH;?>', domain: '<?php echo COOKIE_DOMAIN;?>'}
						);
					}
				});
			</script>
		<?php
		}
		
		unset($ad_id, $data, $impression, $click);
	}
}
?>