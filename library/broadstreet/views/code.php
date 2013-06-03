<?php require dirname(__FILE__) . '/global/_header.php' ?>

<h2>All Set — Here's Your Your Editable Ad Code</h2>

<div class="divider"></div>

<div class="hero">
    <textarea id="adcode"><?php echo htmlentities($data['ad_html']) ?></textarea>
</div>

<div class="divider"></div>

<div class="left-col left bigger">
    <h2 class="exp-heading">How to Use This Ad Code</h2>
    <p>
        If you already place ads on your website, placing this ad code will be simple.
        In most ad management tools, this is known as a "Third-party", "Affiliate" or "HTML Ad".
    </p>
    <ul>
        <li><strong>Broadstreet Adserver</strong>: This ad is available in your dashboard, and ready to be placed.</li>
        <li><strong>OpenX</strong>: Set up a new "HTML" banner and paste this code</li>
        <li><strong>Google DFP</strong>: Create a "Third-party" creative and paste this code</li>
        <li><strong>Wordpress</strong>: Drop this code into a new "Text Widget"</li>
        <li><strong>Other</strong>: Check if your CMS lets you place custom HTML, or ask us!</li>
    </ul>
    <p>
        If you need any help, email us! <a href="mailto:frontdesk@broadstreetads.com">frontdesk@broadstreetads.com</a>.
    </p>
    <p>
        When you're done, the code will always show the latest updates within a few minutes.
    </p>
</div>

<div class="right-col right smaller">
    <a id="done" style="margin: 100px 25px 0 0;" class="btn call-to-action" href="#">Got it. All Set!</a>
</div>

<div class="clearfix"></div>

<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Code']);
    });
    $('#adcode').select();
    $('#done').click(function() {
        var win = window.dialogArguments || opener || parent || top;
        
        if(typeof win.send_to_editor === 'function') 
        {
            win.send_to_editor('<?php echo $data['ad_html_output'] ?>');
        }
        else
        {
            win.tb_remove();
        }
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>