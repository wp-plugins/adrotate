<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2008-2014 AJdG Solutions (Arnan de Gans). All Rights Reserved.
*  ADROTATE is a trademark (pending registration) of Arnan de Gans.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from it's use.
------------------------------------------------------------------------------------ */

$adrotate_server = get_option('adrotate_server');
$adrotate_server_hide = get_option('adrotate_server_hide');
?>
<h3><?php _e('AdRotate Server Settings', 'adrotate'); ?></h3>

<form name="settings" id="post" method="post" action="admin.php?page=adrotate-settings">

	<?php wp_nonce_field('adrotate_server','adrotate_nonce_server'); ?>
	
	<span class="description"><?php _e('Link this website to an AdRotate server so your adverts and stats are synchronised regularly.', 'adrotate'); ?></span>
	<table class="form-table">
		<tr>
			<th valign="top"><?php _e('Status', 'adrotate'); ?></th>
			<td>
				<?php
				echo ($adrotate_server['status'] == 1) ? __('Linked - Adverts can be synced.', 'adrotate') : __('Not linked - No adverts will be synced.', 'adrotate');
				if($adrotate_server_hide == 'N') {
					echo ($adrotate_server['activated'] != '') ? '<br />'.$adrotate_server['activated'] : '';
					echo ($adrotate_server['url'] != '') ? '<br />'.$adrotate_server['url'] : '';
					echo ($adrotate_server['account'] != '') ? '<br />'.$adrotate_server['account'] : '';
					echo ($adrotate_server['puppet'] != '') ? '<br />'.$adrotate_server['puppet'] : '';
				}
				?>
				
			</td>
		</tr>
		<?php if($adrotate_server['status'] == 0) { ?>
		<tr>
			<th valign="top"><?php _e('Server Key', 'adrotate'); ?></th>
			<td><textarea name="adrotate_server_key" cols="80" rows="4" autocomplete="off" disabled></textarea><br /><span class="description"><?php _e('You can get your server key from your AdRotate Server installation or the AdRollr website.', 'adrotate'); ?></span><br /><span class="description"><?php _e('You should not share your key with anyone you do not trust. Treat this key as a password!', 'adrotate'); ?></span></td>
		</tr>
		<tr>
			<th valign="top"><?php _e('Make this site a puppet', 'adrotate'); ?></th>
			<td>
				<input type="checkbox" name="adrotate_server_puppet" disabled /> <span class="description"><?php _e('Have AdRotate use AdRotate Server adverts exclusively.', 'adrotate'); ?><br /><?php _e('Enabling this function will DISABLE ALL LOCAL MANAGEMENT and will make this installation of AdRotate a puppet for your AdRotate Server.', 'adrotate'); ?></span>
			</td>
		</tr>
		<tr>
			<th valign="top"><?php _e('Hide Server Details', 'adrotate'); ?></th>
			<td>
				<input type="checkbox" name="adrotate_server_hide" disabled /> <span class="description"><?php _e('If you have installed AdRotate Pro for a client or in a Multisite network and want to hide the server details from your users or client.', 'adrotate'); ?></span>
			</td>
		</tr>
		<?php } ?>
		<tr>
			<th valign="top">&nbsp;</th>
			<td>
				<?php if($adrotate_server['status'] == 0) { ?>
				<input type="submit" id="post-role-submit" name="adrotate_server_save" value="<?php _e('Link to server', 'adrotate'); ?>" class="button-primary" disabled />
				<?php } else { ?>
				<input type="submit" id="post-role-submit" name="adrotate_server_save" value="<?php _e('Unlink from server', 'adrotate'); ?>" class="button-secondary" disabled />
				<?php } ?>
			</td>
		</tr>
	</table>
</form>