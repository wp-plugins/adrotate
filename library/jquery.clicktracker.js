/*!*********************************************************************
 * Track clicks from special elements								   *
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://www.ajdg.net)
 **********************************************************************/
jQuery(document).ready(function() {
	jQuery("#follow").each(function() {
		var href = jQuery(this).attr("href");
		var target = jQuery(this).attr("target");
		var track = jQuery(this).attr("track");
		var path = jQuery(this).attr("path");

		if(track != '') {
	        jQuery(this).click(function(event) {
	            event.preventDefault();
	            //alert(path + '/adrotate-pro/library/clicktracker.php?track=' + track); // For debugging
				jQuery.ajax({ url: path + '/adrotate/library/clicktracker.php?track=' + track });
				setTimeout(function() { window.open(href,(!target?"_self":target)); },300);
			});
		}
	});
});