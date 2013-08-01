jQuery(document).ready(function($){
	var custom_uploader;
	$('#adrotate_image_button').click(function(e) {
		e.preventDefault();
		if (custom_uploader) {
			custom_uploader.open();
			return;
		}
	
		custom_uploader = wp.media.frames.file_frame = wp.media({
			title: 'Choose Banner',
			button: {
				text: 'Choose Banner'
			},
			multiple: false
		});
		
		custom_uploader.on('select', function() {
			attachment = custom_uploader.state().get('selection').first().toJSON();
			$('#adrotate_image').val(attachment.url);
		});
		
		custom_uploader.open();
	});
});