<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2015 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark of Arnan de Gans.

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
$adrotate_config = get_option('adrotate_config');
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

							<?php if($adrotate_config['stats'] == 1) { ?>
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
							<?php } ?>

							<thead>
							<tr class="first">
								<td colspan="2"><strong><?php _e('Support AdRotate', 'adrotate'); ?></strong></td>
							</tr>
							</thead>

							<tbody>
							<tr class="first">
								<td colspan="2">
									<p><center><?php _e('Your gift helps ensure the continued development of AdRotate!', 'adrotate'); ?><br /><?php _e("Can't donate money? Consider writing a review instead. Thank you!", 'adrotate'); ?></center></p>
									<p><center><a class="button-primary" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=paypal%40ajdg%2enet&item_name=AdRotate%20Donation&no_shipping=0&no_note=0&tax=0&currency_code=EUR&bn=PP%2dDonationsBF&charset=UTF%2d8&amount=5" target="_blank">Donate &euro; 5 via Paypal</a> <a class="button" target="_blank" href="https://wordpress.org/support/view/plugin-reviews/adrotate?rate=5#postform">Write review on WordPress.org</a></center></p>
								</td>
							</tr>
							</tbody>
						</table>
					</div>
				</div>

				<h3><?php _e('AdRotate News and Developer Blog', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<?php 
							wp_widget_rss_output(array(
							'url' => array('http://meandmymac.net/feed/', 'http://www.floatingcoconut.net/feed/', 'http://ajdg.solutions/feed/',), 
							'title' => 'AdRotate Development News', 
							'items' => 4, 
							'show_summary' => 1, 
							'show_author' => 0, 
							'show_date' => 1)
							);
						?>

						<table width="100%">
							<thead>
							<tr class="first">
								<td colspan="2"><strong><?php _e('Get notified of AdRotate updates via Pushover', 'adrotate'); ?></strong></td>
							</tr>
							</thead>
							
							<tbody>
							<tr class="first">
								<td width="50%" class="first b"><p><center><a class="pushover-button" target="_blank" href="https://pushover.net/subscribe/AdRotateUpdates-R5GYqJtGHNcwsQY">Subscribe with Pushover</a></center></p></td>
								<td class="b"><p><?php _e('Pushover is a push notification service for iOS and Android!', 'adrotate'); ?> <a href="https://www.pushover.net/" target="_blank">Pushover website</a>.</p></td>
							</tr>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</div>

		<div id="postbox-container-3" class="postbox-container" style="width:50%;">
			<div id="side-sortables" class="meta-box-sortables ui-sortable">
						
				<h3><?php _e('Get more features with AdRotate Pro', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><a href="https://ajdg.solutions/products/adrotate-for-wordpress/?pk_campaign=adrotatefree-infopage&pk_kwd=adrotate_logo" title="AdRotate plugin for WordPress"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/adrotate-logo-60x60.png" alt="adrotate-logo-60x60" width="60" height="60" align="left" style="padding: 0 10px 10px 0;" /></a><?php _e('Benefit from extra features to reinforce your income with advertising campaigns. Make the most of your website with the powerful tools AdRotate Pro offers on top of the trusted features included in the free version.', 'adrotate'); ?> <?php _e('Want to know more about', 'adrotate'); ?> <a href="admin.php?page=adrotate-pro">AdRotate Pro</a>? <?php _e('Visit the', 'adrotate'); ?> <a href="https://ajdg.solutions/products/adrotate-for-wordpress/?pk_campaign=adrotatefree-infopage&pk_kwd=adrotate_link" target="_blank">AdRotate <?php _e('website', 'adrotate'); ?></a>.</p>
					</div>
				</div>

				<h3><?php _e('Buy AdRotate Professional', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<a href="https://ajdg.solutions/products/adrotate-for-wordpress/?pk_campaign=adrotatefree-infopage&pk_kwd=compare_license"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/adrotate-product.png" alt="adrotate-product" width="150" height="150" align="right" style="padding: 0 0 10px 10px;" /></a>
						<p><h4><?php _e('Single License', 'adrotate'); ?> (&euro; 29.00)</h4><?php _e('For one WordPress installation.', 'adrotate'); ?> <a href="https://ajdg.solutions/cart/?add-to-cart=1124&pk_campaign=adrotatefree-infopage&pk_kwd=buy_single" target="_blank"><?php _e('Buy now', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Duo License', 'adrotate'); ?> (&euro; 39.00)</h4><?php _e('For two WordPress installations.', 'adrotate'); ?> <a href="https://ajdg.solutions/cart/?add-to-cart=1126&pk_campaign=adrotatefree-infopage&pk_kwd=buy_duo" target="_blank"><?php _e('Buy now', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Multi License', 'adrotate'); ?> (&euro; 99.00)</h4><?php _e(' For up to five WordPress installations.', 'adrotate'); ?> <a href="https://ajdg.solutions/cart/?add-to-cart=1128&pk_campaign=adrotatefree-infopage&pk_kwd=buy_multi" target="_blank"><?php _e('Buy now', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Developer License', 'adrotate'); ?> (&euro; 299.00)</h4><?php _e('Unlimited WordPress installations and/or networks.', 'adrotate'); ?> <a href="https://ajdg.solutions/cart/?add-to-cart=1130&pk_campaign=adrotatefree-infopage&pk_kwd=buy_developer" target="_blank"><?php _e('Buy now', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Network License', 'adrotate'); ?> (&euro; 199.00)</h4><?php _e('Set up your own advertising network on a WordPress Multisite.', 'adrotate'); ?> <a href="https://ajdg.solutions/cart/?add-to-cart=1132&pk_campaign=adrotatefree-infopage&pk_kwd=buy_network" target="_blank"><?php _e('Buy now', 'adrotate'); ?> &raquo;</a></p>
						<p><h4><?php _e('Compare licenses', 'adrotate'); ?></h4> <?php _e("Not sure which license is for you? Compare them...", 'adrotate'); ?> <a href="https://ajdg.solutions/products/adrotate-for-wordpress/?pk_campaign=adrotatefree-infopage&pk_kwd=compare_license" target="_blank"><?php _e('All Licenses', 'adrotate'); ?> &raquo;</a></p>
					</div>
				</div>

				<h3><?php _e('AdRotate is brought to you by', 'adrotate'); ?></h3>
				<div class="postbox-adrotate">
					<div class="inside">
						<p><a href="https://ajdg.solutions/?pk_campaign=adrotatefree-infopage&pk_kwd=ajdg_logo" title="AJdG Solutions"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/adrotate/images/ajdg-logo-100x60.png" alt="ajdg-logo-100x60" width="100" height="60" align="left" style="padding: 0 10px 10px 0;" /></a>
						<a href="https://ajdg.solutions/?pk_campaign=adrotatefree-infopage&pk_kwd=ajdg_link" title="AJdG Solutions">AJdG Solutions</a> - <?php _e('Your one stop for Webdevelopment, consultancy and anything WordPress! If you need a custom plugin. Theme customizations or have your site moved/migrated entirely. Visit my website for details!', 'adrotate'); ?> <a href="https://ajdg.solutions/?pk_campaign=adrotatefree-infopage&pk_kwd=ajdg_link" title="AJdG Solutions"><?php _e('Find out more', 'adrotate'); ?></a>!</p>

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