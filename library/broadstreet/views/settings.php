<?php require dirname(__FILE__) . '/global/_header.php' ?>

<?php if(@$data['error']): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $data['error'] ?>
    </div>
<?php endif; ?>

<h2>Configure Broadstreet Settings</h2>

<?php if(@$done): ?>
<script>
    var win = window.dialogArguments || opener || parent || top;
    win.send_to_editor('');
</script>
<?php endif; ?>

<p class="explanation">You can configure your Broadstreet account settings here. 
    If things are working correctly, it might be best not to change these settings.</p>

<div class="divider"></div>

<form id="bs-settings" method="post" encoding="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=settings&id=$id") ?>">

    <div class="left-col bigger">
        <table class="customize">
            <tr>
                <td class="label">Access Token</td>
                <td class="control">
                    <input  style="width: 250px;" type="text" name="access_token" id="access_token" value="<?php echo $access_token ?>" />
                    <br/>
                    You can retrieve your access token <a target="blank" href="http://my.broadstreetads.com/access-token">here</a>.
                </td>
            </tr>
            <tr>
                <td class="label">Your Network</td>
                <td class="control">
                    <select name="network_id" style="width: 260px;">
                        <?php foreach($networks as $network): ?>
                        <option value="<?php echo $network->id ?>" <?php if($network_id == $network->id) echo 'selected' ?>><?php echo $network->name ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
    </div>
    <div class="right-col">

    </div>
    
    <div class="clearfix"></div>
    
</form>
<div class="divider"></div>

<div class="left-col">
    <h2>Important Links</h2>
    <p class="explanation">These items may be helpful when selling, designing, and getting reports.</p>
    <ul>
        <li><a title="This will take you to the Broadstreet dashboard" rel="track" target="_blank" href="<?php echo Broadstreet_Mini_Utility::broadstreetLink("/networks/{$network->id}"); ?>">Broadstreet Dashboard and Reporting</a></li>
        <li><a title="This is a PDF for helping you sell editable ads" rel="track" target="_blank" href="http://broadstreetads.com/assets/docs/editable-ads-text-message.pdf">Sales Materials</a></li>
        <li><a title="This is a PDF for use by you or an ad designer" rel="track" target="_blank" href="http://broadstreetads.com/assets/docs/designer-specs.pdf">Designer Specifications</a></li>
    </ul>
</div>

<div class="right-col right">
    <a style="margin: 30px 25px 0 0;" id="submit" class="btn call-to-action" href="#">Done &raquo;</a>
</div>

<div class="clearfix"></div>

<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Load', 'Settings']);
    });
</script>
<script>
    $('#submit').click(function(e) {
        e.preventDefault();
        _gaq.push(['_trackEvent', 'Clicks', 'Save Settings']);
        $('#bs-settings').submit();
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>