<!DOCTYPE html>
<!--[if IE 8]>
<html xmlns="http://www.w3.org/1999/xhtml" class="ie8 "  dir="ltr" lang="en-US">
<![endif]-->
<!--[if !(IE 8) ]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" class=""  dir="ltr" lang="en-US">
<!--<![endif]-->
<head>
    <link rel='stylesheet' href='<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/css/style.css?' . rand()) ?>' type='text/css' media='all' />
    <link rel='stylesheet' href='<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/css/tipsy.css') ?>' type='text/css' media='all' />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
    <script type='text/javascript' src='<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/js/jquery.tipsy.js') ?>'></script>
</head>
<body>
    <div id="header">
        <h1><img height="35" src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/marty.png') ?>" /> 
            Broadstreet&trade; &minus; Editable Ads</h1>
    </div>
    <?php if(Broadstreet_Mini_Utility::hasNetwork()): ?>
    <?php $id = @$id ? $id : @$data['ad_id']; $id = $id ? $id : '';?>
    <a rel="tipsy" title="Change core settings, get sales material, and more" class="settings-link" href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=settings&id=$id") ?>">
        <img src="<?php echo Broadstreet_Mini_Utility::getBaseURL('assets/img/settings-icon.png') ?>" alt="Settings" />
    </a>
    <?php endif; ?>
    <div id="main">
