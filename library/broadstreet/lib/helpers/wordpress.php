<?php

if(!isset( $_GET['inline']))
    define('IFRAME_REQUEST', true);

if(!defined('WP_ADMIN'))
{
    # Find the wp-admin directory
    if(!preg_match('#(.*)/wp-content/plugins/#', $_SERVER['SCRIPT_FILENAME'], $matches))
        exit("We're awfully sorry. You have a strange server configuration we can't figure out. Email us, and we'll help figure it out. errors@broadstreetads.com");

    $root = $matches[1];

    chdir("$root/wp-admin");

    /** Load WordPress Administration Bootstrap **/
    require_once('./admin.php');
}

function bs_setup()
{
    # Nada. The code in the head of this file took care of it
    # (It had to be in the global scope :P)
}

function bs_get_base_url($append = false)
{
    if(!preg_match('#wp-content/plugins/([^/]+)#', __FILE__, $matches))
        $slug = BROADSTREET_PARTNER_PLUGIN;
    else
        $slug = $matches[1];
    
    return plugins_url("$slug/".BROADSTREET_VENDOR_PATH, $slug) . '/' . ($append ? ltrim($append, '/') : '');
}

function bs_check_perms()
{
    if(!is_admin()) wp_die('Denied.');
}

function bs_get_email()
{
    return get_bloginfo('admin_email');
}

function bs_get_website()
{
    return get_bloginfo('url');
}

function bs_get_website_name()
{
    return get_bloginfo('url');
}

function bs_get_platform_version()
{
    return get_bloginfo('version');
}

function bs_handle_upload($uploadedfile, $upload_overrides)
{
    return wp_handle_upload($uploadedfile, $upload_overrides);
}

function bs_get_sample()
{
    $file = 'sample-ad.png';
    $path = realpath(dirname(__FILE__) . '/../../assets/img/'.$file);
    $url  = bs_get_base_url('assets/img/'.$file);
    
    return array (
        'type' => mime_content_type($path),
        'url'  => $url,
        'file' => $path
    );
}

function bs_mail($to, $subject, $body)
{
    @wp_mail($to, $subject, $body);
}

function bs_get_option($name, $default = FALSE)
{
    $value = get_option($name);
    if( $value !== FALSE ) return $value;
    return $default;
}

function bs_set_option($name, $value)
{
    if (get_option($name) !== FALSE)
    {
        update_option($name, $value);
    }
    else
    {
        $deprecated = ' ';
        $autoload   = 'no';
        add_option($name, $value, $deprecated, $autoload);
    }
}

function bs_editable_js($selector = false, $key = 'solo')
{
        if(!$selector) $selector = BROADSTREET_AD_TAG_SELECTOR;
        $url = bs_get_base_url('index.php?action=index');
        
        echo <<<JS
<script language="javascript">
    function editable_$key()
    {
        window.send_to_editor = function(html) {
            if(html) jQuery('$selector').val(html);
            tb_remove();
        };
        
        var tag     = jQuery('$selector').val();
        var matches = tag.match(/broadstreet:\s*([^\s]*)/i);
        
        var id = null;
        if(matches && matches[1]) id = matches[1];
        
        tb_show('Broadstreet', '$url' + (id ? ('&id=' + id) : '') + '&width=650&height=580&TB_iframe=true');
    }
</script>
JS;
}

