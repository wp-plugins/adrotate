        <!--
        <div>
            <a href="" onclick="document.getElementById('iframeid').src = document.getElementById('iframeid').src">Reload</a>
        </div>
        -->
    </div>
    
<script>
    $(function(){
        $('[rel="track"]').click(function(e) {
            var lbl = $(e.target).text();
            _gaq.push(['_trackEvent', 'Clicks', lbl]);
        });
    });
</script>
<script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-38026755-1']);
  _gaq.push(['_setDomainName', '<?php echo $_SERVER['SERVER_NAME']; ?>']);
  _gaq.push(['_setAllowLinker', true]);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
<script type='text/javascript'>
 $(function() {
   $('[title]').tipsy({fade: true, gravity: 'n'});
 });
</script>
</body>
</html>