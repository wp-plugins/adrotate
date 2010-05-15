<?php
/*-------------------------------------------------------------
 Name:      adrotate_widget

 Purpose:   Unlimited widgets for the sidebar
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
class adrotate_widget extends WP_Widget {

	function adrotate_widget() {
		$widget_ops = array('classname' => 'adrotate_widget', 'description' => "Add banners in the sidebar." );
		$this->WP_Widget('adrotate', __('AdRotate'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? '' : $instance['title']);

		echo $before_widget;
		if ($title)
			echo $before_title . $title . $after_title;
		
		if($adrotate_config['widgetalign'] == 'Y')
			echo '<ul><li>';
			
		echo adrotate_banner($instance['group'], $instance['banner'], $instance['block'], $instance['column'], false, $adrotate_config['fallbackads']);
		
		if($adrotate_config['widgetalign'] == 'Y')
			echo '</li></ul>';
		
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$new_instance['title'] = strip_tags($new_instance['title']);
		$new_instance['group'] = strip_tags($new_instance['group']);
		$new_instance['block'] = strip_tags($new_instance['block']);
		$new_instance['column'] = strip_tags($new_instance['column']);
		$new_instance['banner'] = strip_tags($new_instance['banner']);	

		$instance=wp_parse_args($new_instance,$old_instance);
		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$defaults = array();
		$instance = wp_parse_args( (array) $instance, $defaults );
		extract($instance);
		$title = esc_attr( $title );
		$group = esc_attr( $group );
		$block = esc_attr( $block );
		$column = esc_attr( $column );
		$banner = esc_attr( $banner );

?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title (optional):' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			<br />
			<small><?php _e( 'HTML will be stripped out.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('group'); ?>"><?php _e( 'Group:' ); ?></label>
			<input  class="widefat" id="<?php echo $this->get_field_id('group'); ?>" name="<?php echo $this->get_field_name('group'); ?>" type="text" value="<?php echo $group; ?>" />
			<br />
			<small><?php _e( 'Group IDs. If multiple, separate them with commas (ie. 2,3,12,5).' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('banner'); ?>"><?php _e( 'Banner (Optional):' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('banner'); ?>" name="<?php echo $this->get_field_name('banner'); ?>" type="text" value="<?php echo $banner; ?>" />
			<br />
			<small><?php _e( 'Leave empty for multiple groups or when using a block! Do NOT enter multiple numbers here!' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('block'); ?>"><?php _e( 'Block (Optional):' ); ?></label>
			<input  class="widefat" id="<?php echo $this->get_field_id('block'); ?>" name="<?php echo $this->get_field_name('block'); ?>" type="text" value="<?php echo $block; ?>" />
			<br />
			<small><?php _e( 'Sets the amount of banners in a block.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('column'); ?>"><?php _e( 'Columns (Optional):' ); ?></label>
			<input  class="widefat" id="<?php echo $this->get_field_id('column'); ?>" name="<?php echo $this->get_field_name('column'); ?>" type="text" value="<?php echo $column; ?>" />
			<br />
			<small><?php _e( 'Define how many columns your ad-block has.' ); ?></small>
		</p>
<?php
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_widget_init

 Purpose:   Initialize unlimited widgets for AdRotate
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_widget_init() {
	register_widget('adrotate_widget');
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_widget

 Purpose:   Add a WordPress dashboard widget
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function adrotate_dashboard_widget() {
	wp_add_dashboard_widget( 'adrotate_stats_widget', 'Adrotate', 'adrotate_stats_widget' );
	wp_add_dashboard_widget( 'meandmymac_rss_widget', 'Meandmymac.net RSS Feed', 'meandmymac_rss_widget' );
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
			<?php 
			$clicks = $wpdb->get_var("SELECT SUM(clicks) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y'");
			$thebest = $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' ORDER BY `clicks` DESC LIMIT 1");
			$theworst = $wpdb->get_row("SELECT `title`, `clicks` FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y' AND `active` = 'yes' ORDER BY `clicks` ASC LIMIT 1");
			$banners = $wpdb->get_var("SELECT COUNT(*) FROM `".$wpdb->prefix."adrotate` WHERE `tracker` = 'Y'");
			$impressions = $wpdb->get_var("SELECT SUM(shown) FROM `".$wpdb->prefix."adrotate`");
			$lastfive = $wpdb->get_results("SELECT `timer`, `bannerid` FROM `".$wpdb->prefix."adrotate_tracker` ORDER BY `timer` DESC LIMIT 5"); 
			?>
			
			<h4><label for="Best">The best</label></h4>
			<div class="text-wrap">
				<?php echo $thebest->title; ?> with <?php echo $thebest->clicks; ?> clicks.
			</div>

			<h4><label for="Worst">The worst</label></h4>
			<div class="text-wrap">
				<?php echo $theworst->title; ?> with <?php echo $theworst->clicks; ?> clicks.
			</div>

			<h4><label for="Average">Average</label></h4>
			<div class="text-wrap">
				<?php if($banners < 1 OR $clicks < 1) {
					echo '0';
				} else {
					$average = $clicks / $banners;
					echo round($average, 2);
				} ?> clicks on all banners.
			</div>

			<h4><label for="More">More...</label></h4>
			<div class="text-wrap">
				<?php if($impressions > 0 AND $clicks > 0) {
					$ctr = round((100/$impressions)*$clicks, 2);
				} else {
					$ctr = 0;
				}
				echo $impressions.' impressions and '.$clicks.' clicks. CTR of '.$ctr.'%.'; ?>
			</div>

			<h4><label for="Last5">The last 5</label></h4>
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

/*-------------------------------------------------------------
 Name:      meandmymac_rss_widget

 Purpose:   Shows the Meandmymac RSS feed on the dashboard
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
if(!function_exists('meandmymac_rss_widget')) {
	function meandmymac_rss_widget() {
		?>
			<style type="text/css" media="screen">
			#meandmymac_rss_widget .text-wrap {
				padding-top: 5px;
				margin: 0.5em;
				display: block;
			}
			#meandmymac_rss_widget .text-wrap .rsserror {
				color: #f00;
			}
			</style>
		<?php meandmymac_rss('http://meandmymac.net/feed/');
	}
}
?>