<?php require dirname(__FILE__) . '/global/_header.php' ?>

<?php if(@$data['error']): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $data['error'] ?>
    </div>
<?php endif; ?>

<h2>Step 3: Where Should the Text Come From?</h2>

<p class="explanation">
    The next step is to pick where the editable text should come from.
    It can be by Text Message, Facebook, or Twitter.
</p>

<div class="divider"></div>

<div class="left-col left">
    <h2 class="exp-heading">Settings</h2>
    <div class="source-form">
        <form id="bs-upload-ad" method="post" enctype="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=source&id={$data['ad_id']}") ?>">
            <div class="control">
                <select id="update_source" name="source">
                    <option value="text_message" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_source') == 'text_message') echo 'selected="selected"' ?>>Update by Text Message</option>
                    <option value="facebook" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_source') == 'facebook') echo 'selected="selected"' ?>>Update by Facebook</option>
                    <option value="twitter" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_source') == 'twitter') echo 'selected="selected"' ?>>Update by Twitter</option>
                </select>
            </div>
            <div id="source_details">
                <div class="control" id="source_text_message_detail">
                    <strong>Phone Number</strong><br/>
                    <input title="Enter your full phone number. Don't forget the country code if you are outside the US!" type="text" name="phone" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_phone') ?>" />
                </div>
                <div class="control" id="source_facebook_detail">
                    <strong>Facebook Page URL</strong><br/>
                    <input type="text" name="facebook" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_facebook') ?>" placeholder="http://facebook.com/broadstreetads" /><br/>
                    <strong>Hashtag</strong><br/>
                    <input type="text" name="hashtag" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_hashtag', '#broadstreet') ?>" />
                </div>
                <div class="control" id="source_twitter_detail">
                    <strong>Twitter Username</strong><br/>
                    <input type="text" name="twitter" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_twitter') ?>" placeholder="broadstreetads" /><br />
                    <strong>Hashtag</strong><br/>
                    <input type="text" name="hashtag" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_hashtag', '#broadstreet') ?>" />
                </div>
            </div>
            <div class="control"">
                <strong>Click Destination (URL)</strong><br/>
                <input title="Where does the user go after they click the ad?" type="text" name="destination" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_destination', get_bloginfo('url')) ?>" /><br />
            </div>
        </form>
    </div>
</div>

<div class="right-col right left-just">
    <div id="source_exp">
        <div id="source_text_message_exp">
            <h2 class="exp-heading">How Text Message Works</h2>
            <p>
                You can let your advertisers text their updates
                directly to your ad. Just enter the advertiser's 
                phone number on the left. When they want to send
                updates, they should be sent to <br /><span class="callout">+16092077067</span>.
            </p>
        </div>
        <div id="source_facebook_exp">
            <h2 class="exp-heading">How Facebook Works</h2>
            <p>
                You can set up your advertiser so any Facebook posts with a 
                special hashtag appended are pulled directly into the ad.
                Just enter the advertiser's Facebook page URL and the hashtag
                they'd like to use. See below:
            </p>
            <img width="280" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/facebook-example.png') ?>" alt="Twitter example" />
            <p>Also, be sure to check that:</p>
            <ul>
                <li>The advertiser has a Facebook "page" as opposed to a user account.</li>
                <li>Page permission settings are set to "Any Country" and "Any Age"</li>
            </ul>
        </div>
        <div id="source_twitter_exp">
            <h2 class="exp-heading">How Twitter Works</h2>
            <p>
                You can set up your advertiser so any Twitter posts with a 
                special hashtag appended are pulled directly into the ad.
                Just enter the user's Twitter username and the hashtag
                they'd like to use. See below:
            </p>
            <img width="280" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/twitter-example.png') ?>" alt="Twitter example" />
        </div>
    </div>

</div>
<div class="clearfix"></div>
<div class="divider"></div>

<div class="left-col left">
    <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id={$data['ad_id']}") ?>">Back to the Designer</a>
</div>
<div class="right-col right smaller">
    <a id="submit" style="margin: 0px 25px 0 0;" class="btn call-to-action" href="#">Finish Up &raquo;</a>
</div>

<div class="clearfix"></div>

<script>
    $('#submit').click(function(e) {
        e.preventDefault();
        _gaq.push(['_trackEvent', 'Clicks', 'Set Source']);
        $('#bs-upload-ad').submit();
    });
    
    $('#update_source').change(showUpdateDetails);
    
    function showUpdateDetails() {
        var type = $('#update_source').val();
        
        $('#source_details').children().hide();
        $('#source_' + type + '_detail').show();
        
        $('#source_exp').children().hide();
        $('#source_' + type + '_exp').show();
    }
    
    $('#source_details').children().hide();
    $('#source_exp').children().hide();
    showUpdateDetails();
</script>
<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Source']);
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>