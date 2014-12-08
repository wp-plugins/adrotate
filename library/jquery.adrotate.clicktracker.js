/****************************************************************************************
 * Track clicks from special elements								  					*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://ajdg.solutions)		*
 * Version: 0.7														   					*
 * With help from: Fraser Munro															*
 * Original code: N/a																	*
 ****************************************************************************************/
 
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

jQuery(document).ready(function() {
	jQuery("a.gofollow").click(function(){
		var tracker = jQuery(this).attr("data-track");
		var debug = jQuery(this).attr("data-debug");
		var unixtime = Math.round(Date.now()/1000 - click_timer);
		var thetime = unixtime - click_timer;

        admeta = atob(tracker).split(',');
        timer = jQuery.cookie('adrotate-track-' + admeta[0]);
		if(!timer) timer = '0,0';
		timer = timer.split(','); 

		if(timer[1] < thetime) {
			if(jQuery.fn.cookie){
				jQuery.cookie('adrotate-track-'+admeta[0], timer[0]+','+unixtime, {expires:1,path:cookie_path});
			}
			jQuery.post(click_object.ajax_url, {'action':'adrotate_click','track':tracker});
		}

		if(debug == 1) {
			alert('Tracker: ' + tracker + '\nclick_object.ajax_url: '+click_object.ajax_url);		
		}
	});
});