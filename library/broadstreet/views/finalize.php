<?php require dirname(__FILE__) . '/global/_header.php' ?>

<?php if(@$done): ?>
<script>
    var win = window.dialogArguments || opener || parent || top;
    win.send_to_editor('<?php echo $data['ad_html_output'] ?>');
</script>
<?php endif; ?>

<?php if(@$error): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $error ?>
    </div>
<?php endif; ?>

<h2>This A Preview of Your Ad. Like It?</h2>

<div class="divider"></div>

<div class="designer">
    <div class="encourage">Woot!</div>
    <span><img id="cropbox" src="<?php echo $data['ad_proof'] ?>" alt="New Ad" /></span>
</div>

<div class="divider"></div>

<?php if($show_pay_notice && !isset($data['bs_id'])): ?>

<div style="border-radius: 4px; padding: 10px; background-color: lightyellow">
<h2>You're at the end of your free ads!</h2>

<p style="padding-top: 5px;">
    We're <strong>really</strong> happy that you've enjoyed using our service.
    Before creating another free ad, you'll need to
    <a rel="track" target="_blank" href="<?php echo Broadstreet_Mini_Utility::broadstreetLink("/networks/{$network->id}/accounts"); ?>">Set Up A Paid Account</a>. 
    Editable ads are $<?php echo number_format($network->advertisement_costs->dynamic/100, 2); ?> USD
    per ad, per month. We think you can charge your advertisers much more. <a rel="track" target="_blank" href="http://broadstreetads.com/about/history/">We Did</a>!
</p>

<p style="padding-top: 5px;">You only pay for ads that serve more than <?php echo $network->min_view_count ?> times per day.
    You can also <a rel="track" target="_blank" href="http://broadstreetads.com/about/team/">Learn More About Us</a>.
</p>

<p class="explanation" style="margin-top: 10px;">
    If you aren't ready to go just yet, talk to us: <a rel="track" href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>.
</p>

</div>

<div class="divider"></div>

<?php endif; ?>

<div class="left-col left smaller">
    <h2 class="exp-heading">A Quick Recap</h2>
    <?php if($data['ad_source'] == 'text_message'): ?>
        <p>
            After this ad is placed on your site,
            send a text message from the number you entered,
            <strong><?php echo $data['ad_phone'] ?></strong>, to 
            <strong>+16092077067</strong>. After a few minutes,
            your message will appear in the ad.
        </p>
    <?php elseif($data['ad_source'] == 'facebook'): ?>
        <p>
            After this ad is placed on your site,
            have an admin post an update to the
            <a target="_blank" href="<?php echo $data['ad_facebook'] ?>">Facebook page</a> 
            with the hashtag <strong><?php echo $data['ad_hashtag'] ?></strong> at the end (like below).
            After a few minutes, your message will appear in the ad.
        </p>
        <img width="280" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/facebook-example.png') ?>" alt="Twitter example" />
        <p>Also, be sure to check that:</p>
        <ul>
            <li>The advertiser has a Facebook "page" as opposed to a user account.</li>
            <li>Page permission settings are set to "Any Country" and "Any Age"</li>
        </ul>
    <?php elseif($data['ad_source'] == 'twitter'): ?>
        <p>
            After this ad is placed on your site,
            post a tweet from <strong><?php echo basename($data['ad_twitter']) ?></strong>
            with the hashtag <strong><?php echo $data['ad_hashtag'] ?></strong> at the end (like below).
            After a few minutes, your message will appear in the ad.
        </p>
        <img width="280" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/twitter-example.png') ?>" alt="Twitter example" />
    <?php endif; ?>
        
    <br />
    
    <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=source&id={$data['ad_id']}") ?>">Back to the Source Setter</a>
</div>
<div class="right-col right bigger">
    <form id="bs-finalize" method="post" encoding="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=finalize&id={$data['ad_id']}") ?>">


            <table <?php if(isset($data['bs_id'])) echo 'style="display:none;" ' ?> class="pick-adv">
                <tr>
                    <td class="label">Advertiser Name?</td>
                    <td class="control">
                        <select title="Tell us which advertiser this ad belongs to. If it's not in this list, enter a name below." name="advertiser_id" style="width: 150px;">
                            <option value="">Pick One ...</option>
                            <?php foreach($advertisers as $advertiser): ?>
                            <option value="<?php echo $advertiser->id ?>" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'bs_advertiser_id') == $advertiser->id) echo 'selected' ?>><?php echo $advertiser->name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label">Or enter a name:</td>
                    <td class="control">
                        <input title="This should be at least 3 letters long" style="width: 135px;" type="text" name="advertiser_name" value="<?php if(Broadstreet_Mini_Utility::arrayGet($data, 'bs_advertiser_id')) echo ''; else echo 'New Advertiser'; ?>" />
                    </td>
                </tr>
            </table>
    </form>
    <a id="submit" style="text-align: center; margin: <?php echo isset($data['bs_id']) ? '35' : '5' ?>px 25px 0 0; width: 250px;" class="btn call-to-action" href="#">I Love It. <?php echo !isset($data['bs_id']) ? 'Make It Happen' : 'Update It' ?>.</a>
    <div style="text-align: center; width: 250px; padding: 10px 0 0 20px;"><span id="loading-proof"><img src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/ajax-loader.gif') ?>" alt="Loading"/></span></div>
</div>

<div class="clearfix"></div>

<script>
    $('#submit').click(function(e) {
        e.preventDefault();
        _gaq.push(['_trackEvent', 'Clicks', 'Purchase', 'Text Ad']);
        $('#loading-proof').show();
        $('#submit').off('click'); 
        $('#bs-finalize').submit();
    });
</script>
<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Purchase', '<?php echo $data['ad_source'] ?>']);
        _gaq.push(['_trackEvent', 'Milestones', 'Purchase', 'Network: <?php echo Broadstreet_Mini_Utility::getNetworkID() ?>']);
    });
<?php require dirname(__FILE__) . '/global/_footer.php' ?>