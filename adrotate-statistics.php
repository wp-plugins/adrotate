<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/

/*-------------------------------------------------------------
 Name:      adrotate_draw_graph

 Purpose:   Draw graph using ElyCharts
 Receive:   $id, $labels, $clicks, $impressions
 Return:    -None-
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_draw_graph($id = 0, $labels = 0, $clicks = 0, $impressions = 0) {

	if($id == 0 OR !is_numeric($id) OR strlen($labels) < 1 OR strlen($clicks) < 1 OR strlen($impressions) < 1) {
		echo 'Syntax error, graph can not de drawn!';
	} else {
		echo '
		<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery("#chart-'.$id.'").chart({ 
			    type: "line",
			    margins: [5, 45, 25, 45],
		        values: {
		            serie1: ['.$clicks.'],
		            serie2: ['.$impressions.']
		        },
        		labels: ['.$labels.'],
			    tooltips: function(env, serie, index, value, label) {
			        return "<div class=\"adrotate-label\"><p><span class=\"adrotate-clicks\">Clicks:</span> " + env.opt.values[\'serie1\'][index] + "</p><p><span class=\"adrotate-impressions\">Impressions:</span> " + env.opt.values[\'serie2\'][index] + "</p></div>";
			    },
			    defaultSeries: {
			        plotProps: {
			            "stroke-width": 3
			        },
			        dot: true,
			        rounded: true,
			        dotProps: {
			            stroke: "white",
			            size: 5,
			            "stroke-width": 1,
			            opacity: 0 // dots invisible until we hover it
			        },
			        highlight: {
			            scaleSpeed: 0, // do not animate the dot scaling. instant grow.
			            scaleEasing: "",
			            scale: 1.2, // enlarge the dot on hover
			            newProps: {
			                opacity: 1 // show dots on hover
			            }
			        },
			        tooltip: {
			            height: 45,
			            width: 120,
			            padding: [0],
			            offset: [-15, -10],
			            frameProps: {
			                opacity: 0.95,
			                stroke: "#000"
			
			            }
			        }
			    },
			    series: {
			        serie1: {
			            fill: true,
			            fillProps: {
			                opacity: .1
			            },
			            color: "#26B",
			        },
			        serie2: {
			            axis: "r",
			            color: "#F80",
			            plotProps: {
			                "stroke-width": 2
			            },
			            dotProps: {
			                stroke: "white",
			                size: 3,
			                "stroke-width": 1
			            }
			        }
			
			    },
			    defaultAxis: {
			        labels: true,
			        labelsProps: {
			            fill: "#777",
			            "font-size": "10px"
			        },
			        labelsAnchor: "start",
			        labelsMargin: 5,
			        labelsDistance: 8
			    },
 			    axis: {
			        l: { // left axis
			            labels: true,
			            labelsDistance: 0,
			            labelsSkip: 1,
			            labelsAnchor: "end",
			            labelsMargin: 15,
				        labelsDistance: 4,
			            labelsProps: {
			                fill: "#26B",
			                "font-size": "11px",
			                "font-weight": "bold"
			            }
			        },
			        r: { // right axis
			            labels: true,
			            labelsDistance: 0,
			            labelsSkip: 1,
			            labelsAnchor: "start",
			            labelsMargin: 15,
				        labelsDistance: 4,
			            labelsProps: {
			                fill: "#F80",
			                "font-size": "11px",
			                "font-weight": "bold"
			            }
			        }
			    },
			    features: {
			        mousearea: {
			            type: "axis"
			        },
			        tooltip: {
			            positionHandler: function(env, tooltipConf, mouseAreaData, suggestedX, suggestedY) {
			                return [mouseAreaData.event.pageX, mouseAreaData.event.pageY, true]
			            }
			        },
			        grid: {
			            draw: true, // draw both x and y grids
			            forceBorder: [true, true, true, true], // force grid for external border
			            props: {
			                stroke: "#eee" // color for the grid
			            }
			        }
			    }
			});
		});
		</script>
		';
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_stats

 Purpose:   Generate latest number of clicks and impressions
 Receive:   $ad, $when
 Return:    $stats
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_stats($ad, $when = 0) {
	global $wpdb;
	
	if($when > 0 AND is_numeric($when)) $whenquery =  " AND `thetime` = '$when'";
		else $whenquery = "";

	$stats = $wpdb->get_row("SELECT SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` WHERE `ad` = '$ad'$whenquery;");

	return $stats;
}

/*-------------------------------------------------------------
 Name:      adrotate_stats_nav

 Purpose:   Create browsable links for graph
 Receive:   $type, $id, $month, $year
 Return:    $nav
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_stats_nav($type, $id, $month, $year) {
	global $wpdb;

	$lastmonth = $month-1;
	$nextmonth = $month+1;
	$lastyear = $nextyear = $year;
	if($month == 1) {
		$lastmonth = 12;
		$lastyear = $year - 1;
	}
	if($month == 12) {
		$nextmonth = 1;
		$nextyear = $year + 1;
	}
	$months = array(__('January', 'adrotate'), __('February', 'adrotate'), __('March', 'adrotate'), __('April', 'adrotate'), __('May', 'adrotate'), __('June', 'adrotate'), __('July', 'adrotate'), __('August', 'adrotate'), __('September', 'adrotate'), __('October', 'adrotate'), __('November', 'adrotate'), __('December', 'adrotate'));
	
	if($type == 'ads') $page = '&view=report&ad='.$id;
	if($type == 'groups') $page = '&view=report&group='.$id;
	if($type == 'blocks') $page = '&view=report&block='.$id;
	if($type == 'global-report' OR $type == 'advertiser-report') $page = '';
	
	$nav = '<a href="admin.php?page=adrotate-'.$type.$page.'&month='.$lastmonth.'&year='.$lastyear.'">&lt;&lt; '.__('Previous', 'adrotate').'</a> - ';
	$nav .= '<strong>'.$months[$month-1].' '.$year.'</strong> - ';
	$nav .= '(<a href="admin.php?page=adrotate-'.$type.$page.'">'.__('This month', 'adrotate').'</a>) - ';
	$nav .= '<a href="admin.php?page=adrotate-'.$type.$page.'&month='.$nextmonth.'&year='.$nextyear.'">'. __('Next', 'adrotate').' &gt;&gt;</a>';
	
	return $nav;
}

/*-------------------------------------------------------------
 Name:      adrotate_stats_graph

 Purpose:   Generate graph
 Receive:   $type, $id, $chartid, $start, $end
 Return:    $output
 Since:		3.8
-------------------------------------------------------------*/
function adrotate_stats_graph($type, $id, $chartid, $start, $end) {
	global $wpdb, $adrotate_debug;

	if($type == 'ads') {
		$stats = $wpdb->get_results($wpdb->prepare("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` WHERE `ad` = %d AND `thetime` >= %d AND `thetime` <= %d GROUP BY `thetime` ASC;", $id, $start, $end));
	}

	if($type == 'groups') {
		$stats = $wpdb->get_results($wpdb->prepare("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` WHERE `group` = %d AND `thetime` >= %d AND `thetime` <= %d GROUP BY `thetime` ASC;", $id, $start, $end));
	}

	if($type == 'blocks') {
		$stats = $wpdb->get_results($wpdb->prepare("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` WHERE `block` = %d AND `thetime` >= %d AND `thetime` <= %d GROUP BY `thetime` ASC;", $id, $start, $end));
	}
	
	if($type == 'global-report') {
		$stats = $wpdb->get_results($wpdb->prepare("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` WHERE `thetime` >= %d AND `thetime` <= %d GROUP BY `thetime` ASC;", $start, $end));
	}
	
	if($type == 'advertiser') {
		$stats = $wpdb->get_results($wpdb->prepare("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats`, `".$wpdb->prefix."adrotate_linkmeta` WHERE `".$wpdb->prefix."adrotate_stats`.`ad` = `".$wpdb->prefix."adrotate_linkmeta`.`ad` AND `".$wpdb->prefix."adrotate_linkmeta`.`user` = %d AND (`".$wpdb->prefix."adrotate_stats`.`thetime` >= %d AND `".$wpdb->prefix."adrotate_stats`.`thetime` <= %d) GROUP BY `thetime` ASC;", $id, $start, $end));
	}

	if($stats) {
		$dates = $clicks = $impressions = '';

		foreach($stats as $result) {
			if($result->clicks == null) $result->clicks = '0';
			if($result->impressions == null) $result->impressions = '0';
			
			$dates .= ',"'.date_i18n("d M", $result->thetime).'"';
			$clicks .= ','.$result->clicks;
			$impressions .= ','.$result->impressions;
		}

		$dates = trim($dates, ",");
		$clicks = trim($clicks, ",");
		$impressions = trim($impressions, ",");
		
		$output = '';
		if($adrotate_debug['stats'] == true) { 
			$output .= "<p><strong>[DEBUG] Dates</strong><pre>".$dates."</pre></p>"; 
			$output .= "<p><strong>[DEBUG] Clicks</strong><pre>".$clicks."</pre></p>"; 
			$output .= "<p><strong>[DEBUG] Impressions</strong><pre>".$impressions."</pre></p>"; 
		}

		$output .= '<div id="chart-1" style="height:300px; width:100%;"></div>';
		$output .= adrotate_draw_graph($chartid, $dates, $clicks, $impressions);
		unset($stats, $dates, $clicks, $impressions);
	} else {
		$output = __('No data to show!', 'adrotate');
	} 

	return $output;
}

/*-------------------------------------------------------------
 Name:      adrotate_ctr

 Purpose:   Calculate Click-Through-Rate
 Receive:   $clicks, $impressions, $round
 Return:    $ctr
 Since:		3.7
-------------------------------------------------------------*/
function adrotate_ctr($clicks = 0, $impressions = 0, $round = 2) { 

	if($impressions > 0 AND $clicks > 0) {
		$ctr = round($clicks/$impressions*100, $round);
	} else {
		$ctr = 0;
	}
	
	return $ctr;
} 

/*-------------------------------------------------------------
 Name:      adrotate_prepare_global_report

 Purpose:   Generate live stats for admins
 Receive:   -None-
 Return:    -None-
 Since:		3.5
-------------------------------------------------------------*/
function adrotate_prepare_global_report() {
	global $wpdb;
	
	$today = adrotate_today();

	$stats['lastclicks']			= adrotate_array_unique($wpdb->get_results("SELECT `timer`, `bannerid`, `useragent` FROM `".$wpdb->prefix."adrotate_tracker` WHERE `stat` = 'c' AND `ipaddress` != 0 ORDER BY `timer` DESC LIMIT 50;", ARRAY_A));
	$stats['banners'] 				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'active';");
	$stats['tracker']				= $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `type` = 'active';");
	$stats['clicks']				= $wpdb->get_var("SELECT SUM(`clicks`) as `clicks` FROM `".$wpdb->prefix."adrotate_stats`;");
	$stats['impressions']			= $wpdb->get_var("SELECT SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats`;");
	
	if(!$stats['lastclicks']) 			array();
	if(!$stats['banners']) 				$stats['banners'] = 0;
	if(!$stats['tracker']) 				$stats['tracker'] = 0;
	if(!$stats['clicks']) 				$stats['clicks'] = 0;
	if(!$stats['impressions']) 			$stats['impressions'] = 0;

	return $stats;
}

/*-------------------------------------------------------------
 Name:      adrotate_today

 Purpose:   Get and return the localized UNIX time for "today"
 Receive:   -None-
 Return:    int
 Since:		3.8.4.4
-------------------------------------------------------------*/
function adrotate_today() {
	return mktime(0, 0, 0, date("m"), date("d"), date("Y"));
}

/*-------------------------------------------------------------
 Name:      adrotate_now

 Purpose:   Get and return the localized UNIX time for "now"
 Receive:   -None-
 Return:    int
 Since:		3.8.4.8
-------------------------------------------------------------*/
function adrotate_now() {
	return current_time('timestamp');
}
?>