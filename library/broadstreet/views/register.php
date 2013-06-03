<?php require dirname(__FILE__) . '/global/_header.php' ?>

<?php if(@$error): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $error ?>
    </div>
<?php endif; ?>

<h2>Do you already have an account with Broadstreet&trade;?</h2>

<div class="divider"></div>

<div class="signup-form">
    
    <div id="existing-account" style="display: none; text-align: center; margin: 10px auto -5px auto; width: 55%;" class="alert">
        Nice! You're already a user. Pick which network this website corresponds to, or just create a new one.
    </div>
    
    
    <form class="center-form" id="bs-login" method="post" enctype="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=register&id={$data['ad_id']}") ?>">
        <table class="customize">
            <tr id="email-row">
                <td class="label">Account Email</td>
                <td class="control">
                    <input type="text" name="email" value="<?php echo bs_get_email() ?>" />
                </td>
            </tr>
            <tr id="password-row">
                <td class="label">Account Password</td>
                <td class="control">
                    <input type="password" name="password" value="" />
                </td>
            </tr>
            <tr id="existing-publisher-row" style="display:none;">
                <td class="label">This Website Is ...</td>
                <td class="control">
                    <select title="Tell us which website this is. If it's not in the list, enter it below." id="network" name="network_id" style="width: 150px;">
                        <option value="">-- Choose --</option>
                    </select>
                </td>
            </tr>
            <tr id="new-publisher-row" style="display:none;">
                <td class="label">Not Listed?</td>
                <td class="control">
                    <input title="This should be at least 5 letters long" type="text" id="network_name" name="network_name" value="<?php echo htmlentities(bs_get_website_name()) ?>" />
                </td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="control">
                    <a href="#" id="btn-login" class="btn call-to-action">Login</a>
                    <img id="checking" style="display:none;" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/ajax-loader.gif') ?>" alt="Loading"/>
                </td>
            </tr>
        </table>
        <input type="hidden" name="type" value="login" />
        <input type="hidden" name="next" value="<?php echo @$_GET['next'] ?>" />
    </form>
        
    <h2>&minus; <em class="or-fancy">or, if you're a new user (welcome aboard!)</em> &minus;</h2>
    
    <form class="center-form" id="bs-register" method="post" enctype="multipart/form-data" action="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=register&id={$data['ad_id']}") ?>">
        <table class="customize">
            <tr id="email-row">
                <td class="label">What's your email?</td>
                <td class="control">
                    <input title="We'll send you a welcome email to get you rolling" id="email" type="text" name="email" placeholder="your@email.com" value="<?php echo (bs_get_email() ? bs_get_email() : '') ?>" />
                </td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="control">
                    <a href="#" id="btn-register" class="btn call-to-action">Sign Up</a>
                </td>
            </tr>
        </table>
        <input type="hidden" name="type" value="register" />
        <input type="hidden" name="next" value="<?php echo @$_GET['next'] ?>" />
    </form>
    
</div>

<div class="divider"></div>

<div class="left-col left">
    <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=source&id={$data['ad_id']}") ?>">Go back</a>
</div>
<div class="right-col right">
    &nbsp;
</div>

<div class="clearfix"></div>

<script>
    $('#btn-register').click(function(e) {
        e.preventDefault();
        _gaq.push(['_trackEvent', 'Clicks', 'Register']);
        $('#bs-register').submit();
    });
    
    $('#btn-login').click(function(e) {
        
        if($('#network').val() ||
            ($('#network_name').val() && $('#network_name').is(':visible')))
        {
            $('#bs-login').submit();
            return;
        }
        
        e.preventDefault();
        
        _gaq.push(['_trackEvent', 'Clicks', 'Login']);
        
        var url = '<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=login") ?>';
        
        $('#checking').show();
        
        $.post(url, $('#bs-login').serialize(), function(response) {
            
            if(response.success)
            {
                $('#email-row').hide();
                $('#password-row').hide();
                $('#new-publisher-row').show();
                $('#existing-publisher-row').show();
                $('#checking').hide();
                
                for(var i in response.networks)
                {
                    $('#network').append (
                        $('<option>').text(response.networks[i].name).attr('value', response.networks[i].id)
                    );
                }
                
                $('#existing-account').show();
            }
            else
            {
                alert('Whoops! We could not log you in. Try again.')
                $('#checking').hide();
            }
        },
        'json');
        
    });
    
    $('#email').click(function() {
        var e = $('#email');
        
        if(e.val() == 'Your Email Here')
        {
            e.val('');
        }
    });
</script>
<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Load', 'Login']);
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>
