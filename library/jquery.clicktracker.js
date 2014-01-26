/****************************************************************************************
 * Track clicks from special elements								  					*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://www.ajdg.net)		*
 * Version: 0.3														   					*
 ****************************************************************************************/
jQuery(document).ready(function() {
    jQuery(document).on("click", "a.gofollow", function(e){
        e.preventDefault();
		jQuery(this).each(function() {
			var href = jQuery(this).attr("href");
			var target = jQuery(this).attr("target");
			var track = jQuery(this).attr("data-track");
			var debug = jQuery(this).attr("data-debug");

			if(debug == 1) {
	            alert('URL: ' + href + '\nTrack: ' + track + '\nTarget: ' + target + '\n\nAt least URL and Track must be defined for clicktracking to work.');		
			}
			jQuery.ajax({ url: location.protocol + '//' + location.host + '/wp-content/plugins/adrotate/library/clicktracker.php', data: 'track=' + track });
			window.open(href,(!target ? "_self" : target));
		});
		return this;
	});
});