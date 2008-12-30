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

		echo $before_widget . '<h2 class="widgettitle">' . $options['title'] . '</h2>';
		$url_parts = parse_url(get_bloginfo('home'));
		adrotate_banner($options['group'], $options['banner'], false, false);
		echo $after_widget;
	}
	
	function adrotate_widget_control() {
		$options = $newoptions = get_option('widget_adrotate');
		if ( $_POST['adrotate-submit'] ) {
			$newoptions['title'] = strip_tags(stripslashes($_POST['adrotate-title']));
			$newoptions['group'] = strip_tags(stripslashes($_POST['adrotate-group']));
			$newoptions['banner'] = strip_tags(stripslashes($_POST['adrotate-banner']));
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_adrotate', $options);
		}
		$title = attribute_escape($options['title']);
		$group = attribute_escape($options['group']);
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
				<label for="adrotate-banner">Banner (Optional): <input class="widefat" id="adrotate-banner" name="adrotate-banner" type="text" value="<?php echo $banner; ?>" /></label>
				<br />
				<small>Leave empty for multiple groups. Do NOT enter multiple numbers here!</small>
			</p>
			<input type="hidden" id="adrotate-submit" name="adrotate-submit" value="1" />
	<?php
	}


	$widget_ops = array('classname' => 'adrotate_widget', 'description' => "[AdRotate] Add 1 banner in the sidebar." );
	wp_register_sidebar_widget('AdRotate', 'AdRotate', 'adrotate_widget', $widget_ops);
	wp_register_widget_control('AdRotate', 'AdRotate', 'adrotate_widget_control' );
}
?>