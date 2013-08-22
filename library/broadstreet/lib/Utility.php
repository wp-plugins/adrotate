<?php

if(!class_exists('Broadstreet_Mini_Utility')):
    
require_once dirname(__FILE__) . '/../partner.php';

class Broadstreet_Mini_Utility
{
    /**
     * Get the base URL of the Broadstreet Mini vendor plugin
     * @param string $append A path to append to the base url
     * @return string The final path
     */
    public static function getBaseURL($append = false)
    {
        $folder = self::getContainingFolder();
        
        return plugins_url("$folder/".BROADSTREET_VENDOR_PATH, $folder) . '/' . ($append ? ltrim($append, '/') : '');
    }
    
    /**
     * Get the folder containing the broadstreet plugin
     * @return string
     */
    public static function getContainingFolder()
    {   
        if(!preg_match('#wp-content/plugins/([^/]+)#', __FILE__, $matches))
            return BROADSTRET_PARTNER_PLUGIN;
        else
            return $matches[1];
    }
    
    /**
     * Sets a Wordpress option
     * @param string $name The name of the option to set
     * @param string $value The value of the option to set
     */
    public static function setOption($name, $value)
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

    /**
     * Gets a Wordpress option
     * @param string    $name The name of the option
     * @param mixed     $default The default value to return if one doesn't exist
     * @return string   The value if the option does exist
     */
    public static function getOption($name, $default = FALSE)
    {
        $value = get_option($name);
        if( $value !== FALSE ) return $value;
        return $default;
    }
    
    /**
     * Send an email about an error, issue, etc
     */
    public static function sendReport($message = 'General')
    {
        
        $report = "";
        $report .= get_bloginfo('name'). "\n";
        $report .= get_bloginfo('url'). "\n";
        $report .= get_bloginfo('admin_email'). "\n";
        $report .= 'WP Version: ' . get_bloginfo('version'). "\n";
        $report .= "$message\n";

        @wp_mail('errors@broadstreetads.com', "Partner error: ".BROADSTREET_PARTNER_NAME, $report);
    }
    
    /**
     * Get a link to the Broadstreet interface
     * @param string $path
     * @return string
     */
    public static function broadstreetLink($path)
    {
        $path = ltrim($path, '/');
        $key = Broadstreet_Mini_Utility::getOption(Broadstreet_Mini::KEY_API_KEY);
        $url = "https://my.broadstreetads.com/$path?access_token=$key";
        return $url;
    }
    
    /**
     * Get a key from an array without causing hell
     * @param array  $array
     * @param string $key
     * @param bool   $default 
     */
    public static function arrayGet($array, $key, $default = null)
    {
        if(isset($array[$key]))
            return $array[$key];
        
        return $default;
    }
    
    /**
     * Get a nice string for an error message
     * @param Broadstreet_ServerException $ex
     */
    public static function getPrettyError(Broadstreet_ServerException $ex)
    {
        $error = '';
        
        if($ex instanceof Broadstreet_ServerException)
        {   
            $error = 'Please check these item(s) are correct: <br />';
            $api_response = (array)$ex->error;
        
            foreach($api_response['errors'] as $field => $errors)
            {
                $error .= ucwords(str_replace('_', ' ', $field)) . '<br />';
            }
        }
        
        return $error;
    }
    
    /*
     * Determine whether the current site has been hooked up
     * with Broadstreet
     */
    public static function hasNetwork()
    {
        return (bool)self::getOption(Broadstreet_Mini::KEY_NETWORK_ID, false);
    }
    
    /*
     * Determine whether the current site has been hooked up
     * with Broadstreet
     */
    public static function getNetworkID()
    {
        return self::getOption(Broadstreet_Mini::KEY_NETWORK_ID, false);
    }
    
    /**
     * Escape a javascript tag
     * @param string $tag 
     */
    public static function escapeJSTag($tag)
    {
        $tag = str_ireplace('<script>', '\x3Cscript>', $tag);
        $tag = str_ireplace('</script>', '\x3C/script>', $tag);
        
        return $tag;
    }
    
    /**
     * Get a Broadstreet API client
     * @return Broadstreet 
     */
    public static function getClient()
    {
        $key = Broadstreet_Mini_Utility::getOption(Broadstreet_Mini::KEY_API_KEY);
        
        if($key)
            return new Broadstreet($key);
        else
            return new Broadstreet();
    }
    
    /**
     * Does this account have any free ads left? 
     */
    public static function hasFreeAds()
    {
        $net_id = Broadstreet_Mini_Utility::getOption(Broadstreet_Mini::KEY_NETWORK_ID);
        
        # If we haven't seen them before they probably do
        if(!$net_id) return true;
        
        $net = self::getClient()->getNetwork($net_id);
        
        # Check if you've used up all the hacks
        # Note to l33t haxors. We check on the server side too.
        return ($net->comp_count < $net->comp_count_max);
    }
    
    /**
     * Print a link to open an editable link
     * @param type $label_or_markup 
     */
    public static function editableLink($label_or_markup = false, $key = 'solo')
    {
        if(!$label_or_markup)
            $label_or_markup = '<img alt="Create Editable" src="'.Broadstreet_Mini_Utility::getBaseURL('/assets/img/editable-button.png').'" />';
        echo '<a href="#" onclick="editable_'.$key.'(); return false;">'.$label_or_markup.'</a>';
    }
    
    /**
     * Output JS for placing the ad HTML into the 
     *  new ad form
     */
    public static function editableJS($selector = false, $key = 'solo')
    {
        if(!$selector) $selector = BROADSTREET_AD_TAG_SELECTOR;
        $url =  self::getBaseURL('index.php?action=index');
        
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
    
    /**
     * Is this phone number valid?
     * @param string $num
     * @return boolean 
     */
    public static function isPhoneValid($num)
    {
        if(preg_match('/^[+]?([0-9]?[0-9]?[0-9]?)[(|s|-|.]?([0-9]{3})[)|s|-|.]*([0-9]{3})[s|-|.]*([0-9]{4})$/', $num))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}

endif;
