<?php require dirname(__FILE__) . '/global/_header.php' ?>

<h2>Let Advertisers Edit Ad Text With Editable Ads&trade;</h2>

<div class="divider"></div>

<div class="hero">
    <img alt="Phone to ad" width="450" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/phone-to-ad.png') ?>" />
</div>

<div class="divider"></div>

<div class="left-col">
    <p>Our Editable Ads let your
    advertisers edit a message in their ads on the fly via text message,
    Facebook, or Twitter posts.</p>
    <ul>
        <li>Offer something new and unique</li>
        <li>Close more ad sales</li>
        <li>Create house ads for instant updates</li>
    </ul>
    <p>
        Be sure to check out our <a title="This is a PDF for helping you sell ads" rel="track" target="_blank" href="http://broadstreetads.com/assets/docs/editable-ads-text-message.pdf">Sales Materials</a>
        and our <a title="This is a PDF for use by you or an ad designer" rel="track" target="_blank" href="http://broadstreetads.com/assets/docs/designer-specs.pdf">Designer Specifications</a>!
        Your first ads are free.
    </p>
</div>

<div class="right-col right">
    <a style="margin: 50px 25px 0 0;" class="btn call-to-action" href="<?php echo Broadstreet_Mini_Utility::getBaseURL('index.php?action=upload') ?>">Create <?php echo $has_free ? 'A Free' : 'An' ?> Editable Ad! &raquo;</a>
</div>

<div class="clearfix"></div>

<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Intro']);
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>