<?php require dirname(__FILE__) . '/global/_header.php' ?>

<?php if(@$error): ?>
    <div class="alert alert-error">
        Whoops! <?php echo $error ?>
    </div>
<?php endif; ?>

<h2>Here Are Some Ads You've Already Created</h2>

<?php if(count($ads)): ?>
<table id="ad-list">
    <thead>
        <tr>
            <th>Advertiser</th>
            <th>Update Source</th>
            <th>Created</th>
            <th>Last Edited</th>
            <th>Edit</th> 
        </tr>
    </thead>
    <tbody>
        <?php foreach($ads as $id => $ad): ?>
        <tr>
            <td><?php echo htmlentities($ad['bs_advertiser_name']) ?></td> 
            <td><?php echo htmlentities(ucwords(str_replace('_', ' ', $ad['ad_source']))) ?></td> 
            <td><?php echo date('Y/m/d', $ad['ad_created']) ?></td> 
            <td><?php echo date('Y/m/d', $ad['ad_modified']) ?></td> 
            <td><a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id={$ad['ad_id']}") ?>">Click to Edit</a></td> 
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>

    <?php if($logged_in): ?>
    <p class="explanation">
        ... wait a minute, you haven't created any ads yet!
        <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=index") ?>">Get Started</a>.
    </p>
    <?php else: ?>
        <p class="explanation">Hey, you're not logged in yet.
            <a href="<?php echo Broadstreet_Mini_Utility::getBaseURL("index.php?action=register&next=list_ads") ?>">
                Log in or sign up here</a>. It's easy.
        </p>
    <?php endif; ?>
<?php endif; ?>

<div class="left-col">

</div>

<div class="right-col right">
    
</div>

<div class="clearfix"></div>

<script>
    $(function(){
        _gaq.push(['_trackEvent', 'Pages', 'Edit List']);
    });
</script>
<?php require dirname(__FILE__) . '/global/_footer.php' ?>