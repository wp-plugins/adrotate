<?php require dirname(__FILE__) . '/global/_header.php' ?>

<script src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/jcrop/js/jquery.Jcrop.js') ?>"></script>
<script src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/jcrop/js/jquery.color.js') ?>"></script>
<script src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/jcrop/js/jquery.browser.js') ?>"></script>
<script src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/spectrum/js/spectrum.js') ?>"></script>
<link rel="stylesheet" href="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/jcrop/css/jquery.Jcrop.css') ?>" type="text/css" />
<link rel="stylesheet" href="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/vendor/spectrum/css/spectrum.css') ?>" type="text/css" />


<?php if(@$error): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $error ?>
    </div>
<?php endif; ?>

<h2>Step 2: Design Your Ad</h2>

<div class="divider"></div>

<div class="designer">
    <div class="encourage">Woot!</div>
    <span>
        <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_proof')): ?>
        <img id="cropbox" src="<?php echo $data['ad_proof'] ?>" alt="Ad Proof" />
        <?php else: ?>
        <img id="cropbox" src="<?php echo $data['ad_url'] ?>" alt="New Ad" />
        <?php endif; ?>
    </span>
</div>

<div class="divider"></div>

<p class="explanation">
    First: Drag and drop the area on the ad where the editable text should go.
    Then, generate a proof!
</p>

<form id="bs-design-ad" method="post" encoding="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id={$data['ad_id']}") ?>">

    <input type="hidden" id="x" name="x" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_x') ?>" />
    <input type="hidden" id="y" name="y" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_y') ?>" />
    <input type="hidden" id="w" name="w" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_w') ?>" />
    <input type="hidden" id="h" name="h" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_h') ?>" />
    <input type="hidden" id="proof" name="proof" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_proof') ?>" />

    <div class="left-col">
        <table class="customize">
            <tr>
                <td class="label">Select a font</td>
                <td class="control">
                    <select name="font" style="width: 150px;">
                        <?php foreach($fonts as $font): ?>
                        <option value="<?php echo $font->id ?>" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_font') == $font->id) echo 'selected' ?>><?php echo $font->name ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Select a font color</td>
                <td class="control">
                    <input type="text" name="color" id="color" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_color', '#000000') ?>" />
                </td>
            </tr>
            <tr>
                <td class="label">Generate a Proof</td>
                <td class="control">
                    <a title="See what it looks like so far!" id="get-proof" href="#" class="btn smaller">Go!</a>
                    <span id="loading-proof"><img src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/ajax-loader.gif') ?>" alt="Loading"/> </span>
                    <br />
                    <span id="proof-done">View Above or <a target="_blank" rel="track" id="proof-link" href="#">View Here</a></span>
                </td>
            </tr>
        </table>
    </div>
    <div class="right-col">
        <table class="customize">
            <tr>
                <td class="label">Text Justification</td>
                <td class="control">
                    <select name="justify" style="width: 150px;">
                        <option value="center" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_justify') == 'center') echo 'selected' ?>>Center</option>
                        <option value="left" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_justify') == 'left') echo 'selected' ?>>Left</option>
                        <option value="right" <?php if(Broadstreet_Mini_Utility::arrayGet($data, 'ad_justify') == 'right') echo 'selected' ?>>Right</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Default Text to Place</td>
                <td class="control">
                    <input title="This is text that will be placed in the ad before any updates are sent to it (we'll get to that)" type="text" name="default" value="<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_default', 'Check this ad for updates!') ?>" />
                </td>
            </tr>
        </table>
    </div>
    
    <div class="clearfix"></div>
    
</form>

<div class="divider"></div>

<div class="left-col left">
    <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=upload") ?>">Back to the Uploader</a>
</div>
<div class="right-col right smaller">
    <a id="submit" style="margin: 0px 25px 0 0;" class="btn call-to-action" href="#">Almost There! &raquo;</a>
    
</div>

<div class="clearfix"></div>

<script>
    $('#submit').click(function(e) {
        e.preventDefault();
        _gaq.push(['_trackEvent', 'Clicks', 'Set Design']);
        $('#bs-design-ad').submit();
    });
</script>
<script>

    $(function(){
        var x = <?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_x', '0') ?>,
            y = <?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_y', '0') ?>,
            w = <?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_w', 'false') ?>, 
            h = <?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_h', 'false') ?>;
            
            if(!w) {
                w = 10;
                h = 10;
            } else {
                w = x + w;
                h = y + h;
            }            
        
        $('#cropbox').Jcrop({
            onSelect: updateCoords,
            setSelect: [x,y,w,h]
        });

        $("#color").spectrum({
            color: "<?php echo Broadstreet_Mini_Utility::arrayGet($data, 'ad_color', '#000000') ?>",
            showInput: true,
            className: "full-spectrum",
            showInitial: true,
            showPalette: true,
            showSelectionPalette: true,
            maxPaletteSize: 10,
            preferredFormat: "hex6",
            localStorageKey: "spectrum.demo",
            move: function (color) {
                updateBorders(color);
            },
            show: function () {

            },
            beforeShow: function () {

            },
            hide: function (color) {
                updateBorders(color);
            },

            palette: [
                ["rgb(0, 0, 0)", "rgb(67, 67, 67)", "rgb(102, 102, 102)", /*"rgb(153, 153, 153)","rgb(183, 183, 183)",*/
                "rgb(204, 204, 204)", "rgb(217, 217, 217)", /*"rgb(239, 239, 239)", "rgb(243, 243, 243)",*/ "rgb(255, 255, 255)"],
                ["rgb(152, 0, 0)", "rgb(255, 0, 0)", "rgb(255, 153, 0)", "rgb(255, 255, 0)", "rgb(0, 255, 0)",
                "rgb(0, 255, 255)", "rgb(74, 134, 232)", "rgb(0, 0, 255)", "rgb(153, 0, 255)", "rgb(255, 0, 255)"],
                ["rgb(230, 184, 175)", "rgb(244, 204, 204)", "rgb(252, 229, 205)", "rgb(255, 242, 204)", "rgb(217, 234, 211)",
                "rgb(208, 224, 227)", "rgb(201, 218, 248)", "rgb(207, 226, 243)", "rgb(217, 210, 233)", "rgb(234, 209, 220)",
                "rgb(221, 126, 107)", "rgb(234, 153, 153)", "rgb(249, 203, 156)", "rgb(255, 229, 153)", "rgb(182, 215, 168)",
                "rgb(162, 196, 201)", "rgb(164, 194, 244)", "rgb(159, 197, 232)", "rgb(180, 167, 214)", "rgb(213, 166, 189)",
                "rgb(204, 65, 37)", "rgb(224, 102, 102)", "rgb(246, 178, 107)", "rgb(255, 217, 102)", "rgb(147, 196, 125)",
                "rgb(118, 165, 175)", "rgb(109, 158, 235)", "rgb(111, 168, 220)", "rgb(142, 124, 195)", "rgb(194, 123, 160)",
                "rgb(166, 28, 0)", "rgb(204, 0, 0)", "rgb(230, 145, 56)", "rgb(241, 194, 50)", "rgb(106, 168, 79)",
                "rgb(69, 129, 142)", "rgb(60, 120, 216)", "rgb(61, 133, 198)", "rgb(103, 78, 167)", "rgb(166, 77, 121)",
                /*"rgb(133, 32, 12)", "rgb(153, 0, 0)", "rgb(180, 95, 6)", "rgb(191, 144, 0)", "rgb(56, 118, 29)",
                "rgb(19, 79, 92)", "rgb(17, 85, 204)", "rgb(11, 83, 148)", "rgb(53, 28, 117)", "rgb(116, 27, 71)",*/
                "rgb(91, 15, 0)", "rgb(102, 0, 0)", "rgb(120, 63, 4)", "rgb(127, 96, 0)", "rgb(39, 78, 19)",
                "rgb(12, 52, 61)", "rgb(28, 69, 135)", "rgb(7, 55, 99)", "rgb(32, 18, 77)", "rgb(76, 17, 48)"]
            ]

        });
    });

    function updateCoords(c)
    {
        $('#x').val(c.x);
        $('#y').val(c.y);
        $('#w').val(c.w);
        $('#h').val(c.h);
        
        $('.encourage').show().fadeOut();
    };

    function checkCoords()
    {
        if (parseInt($('#w').val())) return true;
        alert('Please select a crop region then press submit.');
        return false;
    };
    
    $('#get-proof').click(function(e){
        e.preventDefault();
        
        _gaq.push(['_trackEvent', 'Clicks', 'Get Proof']);
        
        var url = '<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=get_proof&id={$data['ad_id']}") ?>';
        
        $('#proof-done').hide();
        $('#loading-proof').show();
        
        $.post(url, $('#bs-design-ad').serialize(), function(response) {
            if(response.success)
            {
                $('#loading-proof').hide();
                $('#proof-link').attr('href', response.proof.url);
                $('#proof').val(response.proof.url);
                
                $('.jcrop-holder').find('img').attr('src', response.proof.url);
                
                $('#proof-done').show();
            }
            else
            {
                alert('There was a problem getting a proof. We\'re sorry!');
                $('#loading-proof').hide();
                $('#proof-done').hide();
            }
        }, 'json');
    });

</script>
<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Design']);
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>