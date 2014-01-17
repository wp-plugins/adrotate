/****************************************************************************************
 * Track clicks from special elements								  					*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://www.ajdg.net)		*
 * Version: 0.2														   					*
 ****************************************************************************************/
jQuery(document).ready(function() {
    jQuery(document).on("click", "a.gofollow", function(e){
        e.preventDefault();
		jQuery(this).each(function() {
			var href = jQuery(this).attr("href");
			var target = jQuery(this).attr("target");
			var track = jQuery(this).attr("data-track");

            //alert('href: ' + href + '\nTarget: ' + target + '\nTrack: ' + track); // For debugging
			jQuery.ajax({ url: location.protocol + '//' + location.hostname + '/wp-content/plugins/adrotate/library/clicktracker.php', data: 'track='+track });
			setTimeout(function() { window.open(href,(!target?"_self":target)); },300);
		});
		return this;
	});
});