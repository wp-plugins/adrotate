<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark (pending registration) of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */
?>
<h3><?php _e('Erroneous ads from server', 'adrotate'); ?></h3>
<p><?php _e('Adverts served from AdRotate server but having some issues.', 'adrotate'); ?></p>

<table class="widefat" style="margin-top: .5em">
	<thead>
		<tr>
		<th width="2%"><center><?php _e('ID', 'adrotate'); ?></center></th>
		<th width="12%"><?php _e('Updated', 'adrotate'); ?></th>
		<th width="12%"><?php _e('Show from', 'adrotate'); ?></th>
		<th width="12%"><?php _e('Show until', 'adrotate'); ?></th>
		<th><?php _e('Title', 'adrotate'); ?></th>
		<th width="5%"><center><?php _e('Weight', 'adrotate'); ?></center></th>
		<th width="5%"><center><?php _e('Shown', 'adrotate'); ?></center></th>
		<th width="5%"><center><?php _e('Today', 'adrotate'); ?></center></th>
		<th width="5%"><center><?php _e('Clicks', 'adrotate'); ?></center></th>
		<th width="5%"><center><?php _e('Today', 'adrotate'); ?></center></th>
		<th width="7%"><center><?php _e('CTR', 'adrotate'); ?></center></th>
	</tr>
	</thead>
	<tbody>
<?php
if ($activebanners) {
	$class = '';
	foreach($activebanners as $banner) {
		$stats = adrotate_stats($banner['id']);
		$stats_today = adrotate_stats($banner['id'], $today);
		$grouplist = adrotate_ad_is_in_groups($banner['id']);

		$ctr = adrotate_ctr($stats['clicks'], $stats['impressions']);						
		
		if($adrotate_debug['dashboard'] == true) {
			echo "<tr><td>&nbsp;</td><td><strong>[DEBUG]</strong></td><td colspan='10'><pre>";
			echo "Ad Specs: <pre>";
			print_r($banner); 
			echo "</pre></td></tr>"; 
		}
					
		if($class != 'alternate') {
			$class = 'alternate';
		} else {
			$class = '';
		}

		?>
	    <tr id='adrotateindex' class='<?php echo $class; ?>'>
			<td><center><?php echo $banner['id'];?></center></td>
			<td><?php echo date_i18n("F d, Y", $banner['updated']);?></td>
			<td><?php echo date_i18n("F d, Y", $banner['firstactive']);?></td>
			<td><span style="color: <?php echo adrotate_prepare_color($banner['lastactive']);?>;"><?php echo date_i18n("F d, Y", $banner['lastactive']);?></span></td>
			<td>
				<strong><?php echo stripslashes(html_entity_decode($banner['title']));?></strong>
				<span style="color:#999;"><?php if(strlen($grouplist) > 0) echo '<br /><span style="font-weight:bold;">Groups:</span> '.$grouplist; ?></span>
			</td>
			<td><center><?php echo $banner['weight']; ?></center></td>
			<td><center><?php echo $stats['impressions']; ?></center></td>
			<td><center><?php echo $stats_today['impressions']; ?></center></td>
			<td><center><?php echo $stats['clicks']; ?></center></td>
			<td><center><?php echo $stats_today['clicks']; ?></center></td>
			<td><center><?php echo $ctr; ?> %</center></td>
		</tr>
		<?php } ?>
<?php } else { ?>
	<tr id='no-groups'>
		<th class="check-column">&nbsp;</th>
		<td colspan="10"><em><?php _e('No ads received yet!', 'adrotate'); ?></em></td>
	</tr>
<?php } ?>
	</tbody>
</table>