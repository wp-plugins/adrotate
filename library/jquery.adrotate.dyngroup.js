/****************************************************************************************
 * Dynamic advert rotation for AdRotate													*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://ajdg.solutions/)	*
 * Version: 0.4														   					*
 * With help from: N/a																	*
 * Original code: N/a																	*
 ****************************************************************************************/

/* == Settings ==
groupid : PHP Group ID [integer, defaults to 0]
speed : Time each slide is shown [integer: milliseconds, defaults to 3000]
transition : Fade time [integer: milliseconds, defaults to 200]
*/

(function($) {
	$.fn.gslider = function(settings) {
		var config = {groupid:0,speed:3000,transition:200};
		if(settings) $.extend(true, config, settings)

		this.each(function(i) {
			var $cont = $(this);
			var gallery = $(this).children();
			var length = gallery.length;
			var timer = 0;
			var counter = 1;
			
			if(length > 1) {
				$cont.find(".c-1").show();
				for(n = 2; n < length; n++) {
					$cont.find(".c-" + n).hide();
				}
				timer = setInterval(function(){ play(); }, config.speed);
			}

			function transitionTo(gallery, index) {
				if((counter >= length) || (index >= length)) { 
					counter = 1;
				} else { 
					counter++;
				}

				$cont.find(".c-" + counter).delay(config.transition).fadeIn(config.transition);
				if(length > 1) {
					$cont.find(".c-" + index).fadeOut(config.transition);
				}
			}
			
			function play() {
				if(length > 1) transitionTo(gallery, counter);
			}
		}); // end .each
		return this;
	}; // end .gslider
}(jQuery)); // end closure