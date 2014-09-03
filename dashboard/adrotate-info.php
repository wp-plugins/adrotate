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
<style type="text/css" media="screen">
.postbox-adrotate {
	margin-bottom: 20px;
	padding: 0;
	position: relative;
	min-width: 255px;
	border: #dfdfdf 1px solid;
	background-color: #fff;
	-moz-box-shadow: inset 0 1px 0 #fff;
	-webkit-box-shadow: inset 0 1px 0 #fff;
	box-shadow: inset 0 1px 0 #fff;
	line-height: 1.5;
}

.postbox-adrotate h3 {
	margin: 0;
	padding: 7px 10px 7px 10px;
	box-shadow: #ddd 0px 1px 0px 0px;
	-moz-box-shadow: inset 0 1px 0 #ddd;
	-webkit-box-shadow: #ddd 0px 1px 0px 0px;
	display: block;
	line-height: 15px;
}

.postbox-adrotate .inside {
	margin: 10px 0px 0px 10px;
	padding: 0px 10px 10px 0px;
	min-height: 40px;
	position: relative;
	display: block;
	line-height: 16px;
}

.inside {
	padding: 6px 10px 12px;
	clear: both;
}
</style>

<?php
$banners = $groups = $queued = 0;
$banners = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` != 'empty' AND `type` != 'a_empty';");
$groups = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate_groups` WHERE `name` != '';");
$queued = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `type` = 'queue';");
$data = get_option("adrotate_advert_status");
?>

<div id="dashboard-widgets-wrap">
	<div id="dashboard-widgets" class="metabox-holder">

		<div id="postbox-container-1" class="postbox-container" style="width:50%;">
			<div id="normal-sortables" class="meta-box-sortables ui-sortable">
				
				<h3><?php _e('Currently', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<table width="100%">
							<thead>
							<tr class="first">
								<td width="50%"><strong><?php _e('Your setup', 'adrotate'); ?></strong></td>
								<td width="50%"><strong><?php _e('Adverts that need you', 'adrotate'); ?></strong></td>
							</tr>
							</thead>
							
							<tbody>
							<tr class="first">
								<td class="first b"><a href="admin.php?page=adrotate-ads"><?php echo $banners; ?> <?php _e('Adverts', 'adrotate'); ?></a></td>
								<td class="b"><a href="admin.php?page=adrotate-ads"><?php echo $data['expiressoon']; ?> <?php _e('(Almost) Expired', 'adrotate'); ?></a></td>
							</tr>
							<tr>
								<td class="first b"><a href="admin.php?page=adrotate-groups"><?php echo $groups; ?> <?php _e('Groups', 'adrotate'); ?></a></td>
								<td class="b"><a href="admin.php?page=adrotate-ads"><?php echo $data['error']; ?> <?php _e('Have errors', 'adrotate'); ?></a></td>
							</tr>
							<tr>
								<td class="first b">&nbsp;</td>
								<td class="b"><a href="admin.php?page=adrotate-moderate"><?php echo $queued; ?> <?php _e('Queued', 'adrotate'); ?></a></td>
							</tr>
							</tbody>

							<thead>
							<tr class="first">
								<td colspan="2"><strong><?php _e('The last few days', 'adrotate'); ?></strong></td>
							</tr>
							</thead>

							<tbody>
							<tr class="first">
								<td colspan="2">
						        	<?php
						        	$adstats = $wpdb->get_results("SELECT `thetime`, SUM(`clicks`) as `clicks`, SUM(`impressions`) as `impressions` FROM `".$wpdb->prefix."adrotate_stats` GROUP BY `thetime` DESC LIMIT 5;");
						
									if($adstats) {
										$adstats = array_reverse($adstats);
										$dates = $clicks = $impressions = 0;
						
										foreach($adstats as $result) {
											if($result->clicks == null) $result->clicks = '0';
											if($result->impressions == null) $result->impressions = '0';
											
											$dates .= ',"'.date_i18n("d M", $result->thetime).'"';
											$clicks .= ','.$result->clicks;
											$impressions .= ','.$result->impressions;
										}
						
										$dates = trim($dates, ",");
										$clicks = trim($clicks, ",");
										$impressions = trim($impressions, ",");
										
										echo '<div id="chart-1" style="height:150px; width:100%;"></div>';
										adrotate_draw_graph(1, $dates, $clicks, $impressions);
									} else {
										_e('No data to show!', 'adrotate');
									} 
									?>
								</td>
							</tr>
							</tbody>
						</table>

						<div class="versions">
							<span id="adrotate-version-message"><?php _e('You are using', 'adrotate'); ?> <strong>AdRotate <?php echo ADROTATE_DISPLAY; ?></strong>.</span>
							<br class="clear">
						</div>
					</div>
				</div>
				
				<h3><?php _e('AdRotate News and Developer Blog', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<?php wp_widget_rss_output(array(
							'url' => array('http://feeds.feedburner.com/AdrotatePluginForWordPress', 'http://feeds.feedburner.com/meandmymacnet'), 
							'title' => 'AdRotate Development News', 
							'items' => 4, 
							'show_summary' => 1, 
							'show_author' => 0, 
							'show_date' => 1)
							); ?>
					</div>
				</div>

			</div>
		</div>

		<div id="postbox-container-3" class="postbox-container" style="width:50%;">
			<div id="side-sortables" class="meta-box-sortables ui-sortable">
						
				<h3><?php _e('AdRotate Store', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><h4><?php _e('AdRotate Pro', 'adrotate'); ?></h4> <?php _e('Get more features! Get AdRotate Pro.', 'adrotate'); ?> <a href="https://www.adrotateplugin.com/adrotate-pro/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=upgrade_adrotatefree"><?php _e('More info', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Get help with installations', 'adrotate'); ?></h4> <?php _e('Not sure how to set up AdRotate? Get me to do it!', 'adrotate'); ?> <a href="https://www.adrotateplugin.com/installations/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=installations"><?php _e('More info', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Premium Support', 'adrotate'); ?></h4> <?php _e("Stuck with AdRotate? I'll help!", 'adrotate'); ?> <a href="https://www.adrotateplugin.com/shop/category/premium-support/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=premium_support"><?php _e('More info', 'adrotate'); ?> &raquo;</a></p>
						<p><a href="https://www.adrotateplugin.com/shop/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=shop"><?php _e('Visit store to see all services and products', 'adrotate'); ?> &raquo;</a></p>
					</div>
				</div>

				<h3><?php _e('Get more features with AdRotate Pro', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><a href="https://www.adrotateplugin.com/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=upgrade_adrotatefree" title="AdRotate plugin for WordPress"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/adrotate-logo-60x60.png" alt="adrotate-logo-60x60" width="60" height="60" align="left" style="padding: 0 10px 10px 0;" /></a><?php _e('Benefit from extra features to reinforce your income with advertising campaigns. Make the most of your website with the powerful tools AdRotate Pro offers on top of the trusted features included in the free version.', 'adrotate'); ?></p>
						<p><?php _e('Learn more about', 'adrotate'); ?> <a href="admin.php?page=adrotate-pro">AdRotate Pro</a> <?php _e('or go to the', 'adrotate'); ?> <a href="https://www.adrotateplugin.com/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=upgrade_adrotatefree" target="_blank">AdRotate <?php _e('website', 'adrotate'); ?> &raquo;</a></p>
					</div>
				</div>

				<h3><?php _e('Support AdRotate', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><center><?php _e('Your gift will ensure the continued development of AdRotate!', 'adrotate'); ?></center></p>
						<p><center><a href="https://www.adrotateplugin.com/donate/?utm_source=adrotate_info&utm_medium=adrotate_free&utm_campaign=donate" target="_blank"><img src="http://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" /></a></center></p>
					</div>
				</div>

				<h3><?php _e('AdRotate is brought to you by', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><a href="https://ajdg.solutions/" title="AJdG Solutions"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/ajdg-logo-100x60.png" alt="ajdg-logo-100x60" width="100" height="60" align="left" style="padding: 0 10px 10px 0;" /></a>
						<a href="https://ajdg.solutions/" title="AJdG Solutions">AJdG Solutions</a> - <?php _e('Your one stop for Webdevelopment, consultancy and anything WordPress! If you need a custom plugin. Theme customizations or have your site moved/migrated entirely. Visit my website for details!', 'adrotate'); ?> <a href="https://ajdg.solutions/" title="AJdG Solutions"><?php _e('Find out more', 'adrotate'); ?></a>!</p>

						<p><center><a href="https://twitter.com/AJdGSolutions" class="twitter-follow-button" data-show-count="false" data-size="large" data-dnt="true"><?php _e('Follow', 'adrotate'); ?> @AJdGSolutions</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script></center></p>
					</div>
				</div>

			</div>	
		</div>

	</div>

	<div class="clear"></div>
	<p><?php echo adrotate_trademark(); ?></p>
</div>