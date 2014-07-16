/****************************************************************************************
 * Dynamic advert rotation for AdRotate													*
 * Arnan de Gans from AJdG Solutions (http://meandmymac.net, http://www.ajdg.net)		*
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
		var config = {groupid:0, speed:3000, transition:200};
		if(settings) $.extend(true, config, settings)

	    var allBoxes = $("div.g-"+config.groupid).children("div");
		transitionBox(null, allBoxes.first());

		function transitionBox(from, to) {
		    function next() {
		        var nextTo;
		        if(to.is(":last-child")) {
		            nextTo = to.closest(".g-"+config.groupid).children("div").first();
		        } else {
		            nextTo = to.next();
		        }
		        to.fadeIn(config.transition, function() {
		            setTimeout(function() {
		                transitionBox(to, nextTo);
		            }, config.speed);
		        });
		    }
		    
		    if(from) {
		        from.fadeOut(config.transition, next);
		    } else {
		        next();
		    }
		}
	}
}(jQuery));