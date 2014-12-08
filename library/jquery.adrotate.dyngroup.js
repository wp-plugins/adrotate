/****************************************************************************************
 * Dynamic advert rotation for AdRotate													*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, https://ajdg.solutions/)	*
 * Version: 0.7														   					*
 * With help from: Mathias Joergensen (http://www.moofy.me), Fraser Munro				*
 * Original code: Arnan de Gans															*
 ****************************************************************************************/

/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

/* == Settings ==
groupid : PHP Group ID [integer, defaults to 0]
speed : Time each slide is shown [integer: milliseconds, defaults to 3000]
*/

jQuery(document).ready(function($){
	$.fn.gslider = function(settings){
		var config = {groupid:0,speed:3000};
		if(settings) $.extend(true, config, settings)

	    var adverts = $("div.g-" + config.groupid).children("div");
		var adverts = shuffle(adverts);
		transitionBox(null, adverts.first());

		function transitionBox(from, to){
		    function next() {
		        var nextTo;

		        if(to.is(":last-child")){
		            nextTo = to.closest(".g-" + config.groupid).children("div").first();
		        } else {
		            nextTo = to.next();
		        }

		        to.fadeIn(300, function(){
		            tracker = to.find('a').attr("data-track");
					if(typeof tracker !== 'undefined') {
						$.post(
							impression_object.ajax_url, 
							{'action': 'adrotate_impression','track': tracker}
						);
						delete tracker;
					}

		            setTimeout(function(){
		                transitionBox(to, nextTo);
		            }, config.speed);
		        });
		    }
		    
		    if(from) {
		        from.fadeOut(250, next);
		    } else {
		        next();
		    }
		}

		function shuffle(o){
			for(var j, x, i = o.length; i; j = Math.floor(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x);
			return o;
		};
	}
}(jQuery));