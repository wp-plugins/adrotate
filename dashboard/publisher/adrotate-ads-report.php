<?php
/*  
Copyright 2010-2013 Arnan de Gans - AJdG Solutions (email : info@ajdg.net)
*/
?>
<h3><?php _e('Advertisement performance', 'adrotate'); ?></h3>

<?php
	$today 			= gmmktime(0, 0, 0, gmdate("n"), gmdate("j"), gmdate("Y"));
	$banner 		= $wpdb->get_row("SELECT `title`, `tracker` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$ad_edit_id';");
	$stats 			= adrotate_stats($ad_edit_id);
	$stats_today 	= adrotate_stats($ad_edit_id, $today);

	// Get Click Through Rate
	$ctr = adrotate_ctr($stats->clicks, $stats->impressions);						

	// Prevent gaps in display
	if($stats->impressions == 0) 		$stats->impressions 		= 0;
	if($stats->clicks == 0) 			$stats->clicks 				= 0;
	if($stats_today->impressions == 0) 	$stats_today->impressions 	= 0;
	if($stats_today->clicks == 0) 		$stats_today->clicks 		= 0;

	if($adrotate_debug['stats'] == true) {
		echo "<p><strong>[DEBUG] Ad Stats (all time)</strong><pre>";
		$memory = (memory_get_usage() / 1024 / 1024);
		echo "Memory usage: " . round($memory, 2) ." MB <br />"; 
		$peakmemory = (memory_get_peak_usage() / 1024 / 1024);
		echo "Peak memory usage: " . round($peakmemory, 2) ." MB <br />"; 
		print_r($stats); 
		echo "</pre></p>"; 
		echo "<p><strong>[DEBUG] Ad Stats (today)</strong><pre>";
		print_r($stats_today); 
		echo "</pre></p>"; 
	}	

?>

<table class="widefat" style="margin-top: .5em">
	<thead>
	<tr>
		<th colspan="5" bgcolor="#DDD"><?php _e('Statistics for', 'adrotate'); ?> '<?php echo $banner->title; ?>'</th>
	</tr>
	</thead>

	<tbody>
  	<tr>
        <td width="20%"><div class="stats_large"><?php _e('Impressions', 'adrotate'); ?><br /><div class="number_large"><?php echo $stats->impressions; ?></div></div></td>
        <td width="20%"><div class="stats_large"><?php _e('Clicks', 'adrotate'); ?><br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $stats->clicks; } else { echo '--'; } ?></div></div></td>
        <td width="20%"><div class="stats_large"><?php _e('Impressions today', 'adrotate'); ?><br /><div class="number_large"><?php echo $stats_today->impressions; ?></div></div></td>
        <td width="20%"><div class="stats_large"><?php _e('Clicks today', 'adrotate'); ?><br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $stats_today->clicks; } else { echo '--'; } ?></div></div></td>
        <td width="20%"><div class="stats_large"><?php _e('CTR', 'adrotate'); ?><br /><div class="number_large"><?php if($banner->tracker == "Y") { echo $ctr.' %'; } else { echo '--'; } ?></div></div></td>
  	</tr>
	<tbody>

	<thead>
	<tr>
		<th colspan="5" bgcolor="#DDD"><?php _e('Monthly overview of clicks and impressions', 'adrotate'); ?> '<?php echo $banner->title; ?>'</th>
	</tr>
	</thead>

	<tbody>
  	<tr>
        <th colspan="5">
        	<div style="text-align:center;"><?php echo adrotate_stats_nav('ads', $ad_edit_id, $month, $year); ?></div>
        	<?php echo adrotate_stats_graph('ads', $ad_edit_id, 1, $monthstart, $monthend); ?>
        </th>
  	</tr>
	</tbody>
	
	<thead>
	<tr>
		<th colspan="5" bgcolor="#DDD"><?php _e('Export options for', 'adrotate'); ?> '<?php echo $banner->title; ?>'</th>
	</tr>
	</thead>
    <tbody>
    <tr>
		<td colspan="5">
			<p><?php adrotate_pro_notice(); ?></p>
			<p><em>Export these statistics as a CSV file. Download or email them.</em></p>
		</td>
	</tr>
	</tbody>
    <thead>
  	<tr>
		<th colspan="5">
			<b><?php _e('Note:', 'adrotate'); ?></b> <em><?php _e('All statistics are indicative. They do not nessesarily reflect results counted by other parties.', 'adrotate'); ?></em>
		</th>
  	</tr>
	</thead>
</table>