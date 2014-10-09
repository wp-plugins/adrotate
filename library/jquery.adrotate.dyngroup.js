/****************************************************************************************
 * Dynamic advert rotation for AdRotate													*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, https://ajdg.solutions/)	*
 * Version: 0.5.3													   					*
 * With help from: Mathias Joergensen (http://www.moofy.me)								*
 * Original code: Fraser Munro															*
 ****************************************************************************************/

/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark (pending registration) of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

/* == Settings ==
groupid : PHP Group ID [integer, defaults to 0]
speed : Time each slide is shown [integer: milliseconds, defaults to 3000]
*/

(function($) {
	$.fn.gslider = function(settings) {
		var config = {groupid:0,speed:3000};
		if(settings) $.extend(true, config, settings)

		this.each(function(i) {
			var $cont = $(this);
			var gallery = $(this).children();
			var length = gallery.length;
			var timer = 0;
			var counter = 1;

			if(length > 1) {
				for(n = 1; n < length; n++) {
					$cont.find(".c-" + n).hide();
				}
				$cont.find(".c-" + Math.floor(Math.random()*length+1)).show();
				
				timer = setInterval(function(){ play(); }, config.speed);
			}

			function transitionTo(gallery, index) {
				if((counter >= length) || (index >= length)) { 
					counter = 1;
				} else { 
					counter++;
				}

				$cont.find(".c-" + counter).fadeIn(300)
				if(length > 1) {
					$cont.find(".c-" + index).fadeOut(300);
				}
			}
			
			function play() {
				if(length > 1) transitionTo(gallery, counter);
			}
		});
		return this;
	};
}(jQuery));