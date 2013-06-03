<?php

session_start();

function bs_setup()
{
    
}

function bs_get_base_url($append = false)
{
    $pageURL = (@$_SERVER["HTTPS"] == "on" || @$_SERVER["HTTP_X_FORWARDED_PROTO"] == "https") ? "https://" : "http://";
    
    if ($_SERVER["SERVER_PORT"] != "80")
    {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } 
    else 
    {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    
    preg_match('#(.*)'.BROADSTREET_VENDOR_PATH.'#', $pageURL, $matches);
    return $matches[1] . BROADSTREET_VENDOR_PATH . '/' . $append;
}

function bs_check_perms()
{
    
}

function bs_get_email()
{
    return '';
}

function bs_get_website()
{
    return 'http://broadstreetads.com';
}

function bs_get_website_name()
{
    return 'New Publisher ' . date('Y-m-d');
}

function bs_get_platform_version()
{
    return 'NO PLATFORM';
}

function bs_handle_upload($uploadedfile, $upload_overrides = array())
{
    $uploaddir = realpath(dirname(__FILE__) . '/../../tmp');
    
    $filename = uniqid('upload_', true);
    $filename = preg_replace('#.*\.#i', "$filename.", basename($uploadedfile['name']));
    
    $uploadfile = $uploaddir .'/'. $filename;
    
    if (move_uploaded_file($uploadedfile['tmp_name'], $uploadfile)) 
    {
        return array (
            'type' => mime_content_type($uploadfile),
            'url' => bs_get_base_url('tmp/'.  basename($uploadfile)),
            'file' => $uploadfile
        );
    }
    else 
    {
        return array('error' => 'There was an error uploading the file!');
    }
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
    if(BROADSTREET_USE_DATABASE && !bs_is_session_data($name))
    {
        return bs_db_get_option ($name, $default);
    }
    else
    {
        $value = @$_SESSION[$name];
        if($value !== NULL) return $value;
        return $default;
    }
}

function bs_set_option($name, $value)
{
    if (bs_get_option($name) !== NULL)
    {
        bs_update_option($name, $value);
    }
    else
    {
        bs_add_option($name, $value);
    }
}

function bs_update_option($name, $value)
{
    if(BROADSTREET_USE_DATABASE && !bs_is_session_data($name))
    {
        bs_db_update_option($name, $value);
    }
    else
    {
        $_SESSION[$name] = $value;
    }
}

function bs_is_session_data($name)
{
    return in_array($name, array(Broadstreet_Mini::KEY_API_KEY, Broadstreet_Mini::KEY_NETWORK_ID));
}

function bs_add_option($name, $value)
{
    bs_update_option($name, $value);
}

function bs_editable_js($selector = false, $key = 'solo')
{
        if(!$selector) $selector = BROADSTREET_AD_TAG_SELECTOR;
        
        echo <<<JS
<script language="javascript">
    function editable_$key()
    {
        window.send_to_editor = function(html) {
            if(html) jQuery('$selector').val(html);
            tb_remove();
        };
    }
</script>
JS;
}

function bs_get_db()
{
    $db = @$GLOBALS['bs_db'];
    
    if(!$db)
    {
        $db = mysql_connect(BROADSTREET_DB_HOST, BROADSTREET_DB_USER, BROADSTREET_DB_PASS);
        mysql_select_db(BROADSTREET_DB_NAME, $db);
        $GLOBALS['bs_db'] = $db;
    }
    
    return $db;
}

function bs_db_update_option($name, $value)
{
    $db = bs_get_db();
    $table = BROADSTREET_DB_TABLE;
    $name = mysql_real_escape_string($name);
    $value = mysql_real_escape_string(serialize($value));
    
    $sql = "INSERT INTO $table (name, value) VALUES ('$name', '$value')
            ON DUPLICATE KEY UPDATE value = '$value'";
    
    mysql_query($sql, $db);
}

function bs_db_get_option($name, $default)
{
    $db     = bs_get_db();
    $table  = BROADSTREET_DB_TABLE;
    $name   = mysql_real_escape_string($name);
    
    $sql    = "SELECT value FROM $table WHERE name = '$name' LIMIT 1";
    
    $qh     = mysql_query($sql, $db);
    $row    = mysql_fetch_array($qh);
    
    if($row) return unserialize($row['value']);
    
    return $default;
}