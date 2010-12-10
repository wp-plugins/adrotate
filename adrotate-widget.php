<?php
/*  
Copyright 2010 Arnan de Gans  (email : adegans@meandmymac.net)

This program is free software; you can redistribute it and/or modify it under the terms of 
the GNU General Public License, version 2, as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, visit: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*-------------------------------------------------------------
 Name:      adrotate_widget

 Purpose:   Unlimited widgets for the sidebar
 Receive:   -none-
 Return:    -none-
 Since:		0.8
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
		
		if($instance['type'] == "single")
			echo adrotate_ad($instance['id']);
		
		if($instance['type'] == "group")
			echo adrotate_group($instance['id']);
		
		if($instance['type'] == "block")
			echo adrotate_block($instance['id']);
		
		if($adrotate_config['widgetalign'] == 'Y')
			echo '</li></ul>';
		
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$new_instance['title'] = strip_tags($new_instance['title']);
		$new_instance['type'] = strip_tags($new_instance['type']);	
		$new_instance['id'] = strip_tags($new_instance['id']);

		$instance = wp_parse_args($new_instance,$old_instance);
		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$defaults = array();
		$instance = wp_parse_args( (array) $instance, $defaults );
		extract($instance);
		$title = esc_attr( $title );
		$type = esc_attr( $type );
		$id = esc_attr( $id );
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title (optional):' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			<br />
			<small><?php _e( 'HTML will be stripped out.' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e( 'Type:' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id('type'); ?>" name="<?php echo $this->get_field_name('type'); ?>" class="postform">
			    <option value="single" <?php if($type == "single") { echo 'selected'; } ?>>Single Ad - Use Ad ID</option>
		        <option value="group" <?php if($type == "group") { echo 'selected'; } ?>>Group of Ads - Use group ID</option>
			    <option value="block" <?php if($type == "block") { echo 'selected'; } ?>>Block of Ads - Use Block ID</option>
			</select>
			<br />
			<small><?php _e( 'Choose what you want to use this widget for' ); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('id'); ?>"><?php _e( 'ID:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('id'); ?>" name="<?php echo $this->get_field_name('id'); ?>" type="text" value="<?php echo $id; ?>" />
			<br />
			<small><?php _e( 'Fill in the ID of the type you want to display!' ); ?></small>
		</p>
<?php
	}

}

/*-------------------------------------------------------------
 Name:      adrotate_widget_init

 Purpose:   Initialize unlimited widgets for AdRotate
 Receive:   -none-
 Return:    -none-
 Since:		0.8
-------------------------------------------------------------*/
function adrotate_widget_init() {
	register_widget('adrotate_widget');
}

/*-------------------------------------------------------------
 Name:      adrotate_dashboard_widget

 Purpose:   Add a WordPress dashboard widget
 Receive:   -none-
 Return:    -none-
 Since:		2.1
-------------------------------------------------------------*/
function adrotate_dashboard_widget() {
	wp_add_dashboard_widget('meandmymac_rss_widget', 'Meandmymac.net RSS Feed', 'meandmymac_rss_widget');
}

/*-------------------------------------------------------------
 Name:      meandmymac_rss_widget

 Purpose:   Shows the Meandmymac RSS feed on the dashboard
 Receive:   -none-
 Return:    -none-
 Since:		2.4.3
-------------------------------------------------------------*/
if(!function_exists('meandmymac_rss_widget')) {
	function meandmymac_rss_widget() {

	/* Changelog:
	// Dec 8 2010 - Now uses SimplePIE RSS parser
	*/

		include_once(ABSPATH . WPINC . '/feed.php');
		$feed		= array('http://meandmymac.net/feed/', 'http://www.adrotateplugin.com/page/updates_files/adrotate.xml');
		$count		= 10;
		$showdates	= 'yes';
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
		<?php
		$rss = fetch_feed($feed);
		if (!is_wp_error($rss)) { 
		    $maxitems = $rss->get_item_quantity($count); 
		    $rss_items = $rss->get_items(0, $maxitems); 
		}
		echo '<ul>';
		if(is_array($rss_items) AND $rss_items) {
			if ($maxitems == 0) {
				echo '<li class="text-wrap">No items</li>';
			} else {
				foreach ($rss_items as $item) {
			        echo '<li class="text-wrap"><a href='.$item->get_permalink().' title="'.$item->get_title().'">'.$item->get_title().'</a>';
					if($showdates == "yes") echo ' on '.$item->get_date('j F Y \a\t g:i a');
					echo '</li>';
				}
			}
		} else {
			echo '<li><span class="rsserror">The feed appears to be invalid or corrupt!</span></li>';
		}
		echo '</ul>';
	}
}
?>