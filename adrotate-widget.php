<?php
/*-------------------------------------------------------------
 Name:      adrotate_widget

 Purpose:   Widget for the sidebar
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_widget_init() {

	if ( !function_exists('register_sidebar_widget') )
		return;
	if ( !function_exists('adrotate_banner') )
		return;

	function adrotate_widget($args) {
		$options = get_option('widget_adrotate');
		extract($args);

		echo $before_widget . $before_title . $options['title'] . $after_title;
		echo '<ul><li>';
			echo adrotate_banner($options['group'], $options['banner'], $options['block'], false);
		echo '</li></ul>';
		echo $after_widget;

	}
	
	function adrotate_widget_control() {
		$options = $newoptions = get_option('widget_adrotate');
		if ( $_POST['adrotate-submit'] ) {
			$newoptions['title'] = strip_tags(stripslashes($_POST['adrotate-title']));
			$newoptions['group'] = strip_tags(stripslashes($_POST['adrotate-group']));
			$newoptions['block'] = strip_tags(stripslashes($_POST['adrotate-block']));
			$newoptions['banner'] = strip_tags(stripslashes($_POST['adrotate-banner']));
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_adrotate', $options);
		}
		$title = attribute_escape($options['title']);
		$group = attribute_escape($options['group']);
		$block = attribute_escape($options['block']);
		$banner = attribute_escape($options['banner']);
	?>
			<p>
				<label for="adrotate-title">Title: <input class="widefat" id="adrotate-title" name="adrotate-title" type="text" value="<?php echo $title; ?>" /></label>
				<br />
				<small>HTML will be stripped out.</small>
			</p>
			<p>
				<label for="adrotate-group">Group: <input  class="widefat" id="adrotate-group" name="adrotate-group" type="text" value="<?php echo $group; ?>" /></label>
				<br />
				<small>Group IDs. If multiple, separate them with commas (ie. 2,3,12,5).</small>
			</p>
			<p>
				<label for="adrotate-block">Block: <input  class="widefat" id="adrotate-block" name="adrotate-block" type="text" value="<?php echo $block; ?>" /></label>
				<br />
				<small>Sets the amount of banners in a block.</small>
			</p>
			<p>
				<label for="adrotate-banner">Banner (Optional): <input class="widefat" id="adrotate-banner" name="adrotate-banner" type="text" value="<?php echo $banner; ?>" /></label>
				<br />
				<small>Leave empty for multiple groups or when using a block! Do NOT enter multiple numbers here!</small>
			</p>
			<input type="hidden" id="adrotate-submit" name="adrotate-submit" value="1" />
	<?php
	}


	$widget_ops = array('classname' => 'adrotate_widget', 'description' => "Add banners in the sidebar." );
	wp_register_sidebar_widget('AdRotate', 'AdRotate', 'adrotate_widget', $widget_ops);
	wp_register_widget_control('AdRotate', 'AdRotate', 'adrotate_widget_control' );
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_widget

 Purpose:   Add a WordPress dashboard widget
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard_widget() {
	wp_add_dashboard_widget( 'adrotate_stats_widget', 'Adrotate', 'adrotate_stats_widget' );
}


/*-------------------------------------------------------------
 Name:      adrotate_stats_widget

 Purpose:   AdRotate stats
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_stats_widget() {
	global $wpdb;
	
	$timezone = get_option('gmt_offset')*3600;
	$url = get_option('siteurl');
	?>
		<style type="text/css" media="screen">
		#adrotate_stats_widget h4 {
			font-family: "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif;
			float: left;
			width: 7em;
			clear: both;
			font-weight: normal;
			text-align: right;
			padding-top: 5px;
			font-size: 12px;
		}
		
		#adrotate_stats_widget h4 label {
			margin-right: 10px;
			font-weight: bold;
		}
		
		#adrotate_stats_widget .text-wrap {
			padding-top: 5px;
			margin: 0 0 1em 5em;
			display: block;
		}
		</style>
	<?php
	
	$banners = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` ORDER BY `id`");
	if($banners > 0) { ?>
			<?php $thebest = $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` ORDER BY `clicks` DESC LIMIT 1"); ?>
			<h4><label for="Best">The best</label></h4>
			<div class="text-wrap">
				<?php echo $thebest->title; ?> with <?php echo $thebest->clicks; ?> clicks.
			</div>

			<h4><label for="Worst">The worst</label></h4>
			<?php $theworst = $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` ORDER BY `clicks` ASC LIMIT 1"); ?>
			<div class="text-wrap">
				<?php echo $theworst->title; ?> with <?php echo $theworst->clicks; ?> clicks.
			</div>
								
			<h4><label for="Average">Average</label></h4>
			<?php
			$clicks = $wpdb->get_var("SELECT SUM(clicks) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y'");
			$banners = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y'");
			?>
			<div class="text-wrap">
				<?php if($banners < 1 OR $clicks < 1) { 
					echo '0'; 
				} else { 
					$average = $clicks / $banners;
					echo round($average, 2); 
				} ?> clicks on all banners.
			</div>

			<h4><label for="More">More...</label></h4>
			<?php 
			$impressions = $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate`");
			$clicks2 = $wpdb->get_var("SELECT SUM(clicks) FROM `".$wpdb->prefix."adrotate`");
			?>
			<div class="text-wrap">
				<?php if($impressions > 0 OR $clicks2 > 0) { 
					$ctr = round((100/$impressions)*$clicks2, 2);
				} else {
					$ctr = 0;
				}
				echo $impressions.' impressions and '.$clicks2.' clicks. CTR of '.$ctr.'%.'; ?>
			</div>

			<h4><label for="Last5">The last 5</label></h4>
			<?php
			$lastfive = $wpdb->get_results("SELECT `timer`, `bannerid` FROM `".$wpdb->prefix."adrotate_tracker` ORDER BY `timer` DESC LIMIT 5");
			?>
			<div class="text-wrap">
				<?php 
				if(count($lastfive) > 0) {
					foreach($lastfive as $last) {
						$bannertitle = $wpdb->get_var("SELECT `title` FROM `".$wpdb->prefix."adrotate` WHERE `id` = '$last->bannerid'");
						echo date('d-m-Y', $last->timer) .', '. $bannertitle .'<br />';
					}
				} else {
				?>
				<em>No clicks in the past 24 hours</em>
				<?php } ?>
			</div>
			
			<div style="padding-top: .5em">
				<p><a href="admin.php?page=adrotate" class="button">Manage Banners</a>&nbsp;&nbsp;<a href="admin.php?page=adrotate2" class="button">Add Banner</a></p>
			</div>
								
	<?php } else { ?>	
		<span style="font-style: italic;">There are no banners yet. <a href="admin.php?page=adrotate2">Add some banners now</a>!</span>
	<?php } ?>
<?php 
}
?>