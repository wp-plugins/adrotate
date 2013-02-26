<?php
/**
 * We need to do a little handywork to make sure that Wordpress
 * thinks we're a standard built-in modal. 
 */
if (!isset( $_GET['inline']))
	define('IFRAME_REQUEST', true);

# Find the wp-admin directory
if(!preg_match('#(.*)/wp-content/plugins/#', $_SERVER['SCRIPT_FILENAME'], $matches))
    exit("We're awfully sorry. You have a strange server configuration we can't figure out. Email us, and we'll help figure it out. errors@broadstreetads.com");

# Include this so Wordpress doesn't throw NOTICEs when it sets $pagenow
$_SERVER['PHP_SELF'] = '/wp-admin';

$root = $matches[1];

chdir("$root/wp-admin");

/** Load WordPress Administration Bootstrap **/
require_once('./admin.php');

# Check permissions
if (!current_user_can('upload_files'))
	wp_die(__('You do not have permission to do this.'));

# Include libraries
require_once 'partner.php';
require_once 'lib/Utility.php';
require_once 'lib/Broadstreet.php';

if(BROADSTREET_DEBUG)
{
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

class Broadstreet_Mini
{
    CONST KEY_API_KEY       = 'Broadstreet_API_Key';
    CONST KEY_NETWORK_ID    = 'Broadstreet_Network_Key';
    
    public static function execute()
    {
        # Check permissions again
        if(!is_admin()) wp_die('Denied');
        
        $action = $_GET['action'];
        
        # Don't allow access to protected/utillity methods
        if($action[0] == '_')
            wp_die('Denied');
        
        # Make sure the requested function is available
        if(is_callable(array('self', $action)))
        {
            call_user_func(array('self', $action));
        }
    }
    
    /**
     * Load the front intro page 
     */
    public static function index()
    {
        $id = @$_GET['id'];
        
        if($id)
        {
            $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id=$id");
            header("Location: $next");
        }
                
        self::_load('index', array('has_free' => Broadstreet_Mini_Utility::hasFreeAds()));
    }
    
    /**
     * Load the final purchase page 
     */
    public static function finalize()
    {
        $id         = $_GET['id'];
        $data       = Broadstreet_Mini_Utility::getOption($id);
        $api        = Broadstreet_Mini_Utility::getClient();
        $network_id = Broadstreet_Mini_Utility::getOption(self::KEY_NETWORK_ID);
        $done       = false;
        $error      = false;
        

        $network         = $api->getNetwork($network_id);
        $show_pay_notice = false;
        if(!Broadstreet_Mini_Utility::hasFreeAds()
                && !$network->cc_on_file)
        {
            $show_pay_notice = true;
        }
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $advertiser_id   = $_POST['advertiser_id'];
            $advertiser_name = $_POST['advertiser_name'];
            
            if(!$advertiser_id && strlen($advertiser_name) < 3)
            {
                $error = "You must choose an advertiser or enter the name of a new one. It should be at least 3 letters long.";
            }
            
            if($show_pay_notice)
            {
                $url = Broadstreet_Mini_Utility::broadstreetLink("/networks/{$network->id}/accounts");
                $error = 'You need <a rel="track" target="_blank" href="'.$url.'">A Paid Account</a> with us to continue. Come aboard! We exist for indy publishers!';
            }
            
            if(!$error)
            {
                $params = array();
                
                try
                {
                    if(!$advertiser_id)
                    {
                        $advertiser    = $api->createAdvertiser($network_id, stripslashes($advertiser_name));
                        $advertiser_id = $advertiser->id;
                    }

                    $params['text_x']       = $data['ad_x'];
                    $params['text_y']       = $data['ad_y'];
                    $params['text_width']   = $data['ad_w'];
                    $params['text_height']  = $data['ad_h'];
                    $params['text_color']   = $data['ad_color'];
                    $params['font_id']      = $data['ad_font'];
                    $params['default_text'] = $data['ad_default'];
                    $params['alignment']    = $data['ad_justify'];
                    $params['destination']  = $data['ad_destination'];
                    $params['partner']      = BROADSTREET_PARTNER_NAME;

                    # Template file
                    $params['template_base64'] = base64_encode(file_get_contents($data['ad_file']));

                    # Create or update the ad
                    if(isset($data['bs_id']))
                    # Use the original params since you can't change networks or advertisers
                        $ad = $api->updateAdvertisement($data['bs_network_id'], $data['bs_advertiser_id'], $data['bs_id'], $params);
                    else
                        $ad = $api->createAdvertisement($network_id, $advertiser_id, 'Editable Ad ('.BROADSTREET_PARTNER_NAME.') '.date('m/Y'), 'dynamic', $params);

                    $options    = array();
                    $hash_tag   = false;

                    # Get the source information
                    if($data['ad_source'] == 'facebook')
                    {
                        $options['facebook_id'] = $data['ad_facebook'];
                        $hash_tag = $data['ad_hashtag'];
                    } 
                    elseif($data['ad_source'] == 'twitter')
                    {
                        $options['twitter_id'] = $data['ad_twitter'];
                        $hash_tag = $data['ad_hashtag'];
                    }
                    elseif($data['ad_source'] == 'text_message')
                    {
                        $options['phone_number'] = $data['ad_phone'];
                    }

                    # Update the ad with the hashtag if we need it
                    if($hash_tag)
                    {
                        $api->updateAdvertisement($network_id, $advertiser_id, $ad->id, array (
                            'hash_tag' => $hash_tag
                        ));
                    }

                    # Set the ad source
                    $api->setAdvertisementSource($network_id, $advertiser_id, $ad->id, $data['ad_source'], $options);

                    # Build the final ad tag
                    $header                     = "<!-- READY TO GO! DO NOT EDIT! - broadstreet:$id -->";
                    $data['ad_html']            = $ad->html;
                    $data['ad_html_output']     = $header . Broadstreet_Mini_Utility::escapeJSTag($ad->html);
                    
                    # Only set this if it's new
                    if(!isset($data['bs_id']))
                    {
                        $data['bs_id']              = $ad->id;
                        $data['bs_advertiser_id']   = $advertiser_id;
                        $data['bs_network_id']      = $network_id;
                    }
                    
                    # Save
                    Broadstreet_Mini_Utility::setOption($id, $data);

                    $done = true;
                }
                catch(Exception $ex)
                {
                    $error = "There was an error creating your ad: " . Broadstreet_Mini_Utility::getPrettyError($ex);
                    if(BROADSTREET_DEBUG) $error .= ' ' . $ex->__toString();
                    Broadstreet_Mini_Utility::sendReport("Network: $network_id\n\n" . $ex->__toString() . "\n\n" . print_r($params, true));
                }
            }
        }

        self::_load('finalize', array('network' => $network, 'show_pay_notice' => $show_pay_notice, 'error' => $error, 'data' => $data, 'done' => $done, 'advertisers' => $api->getAdvertisers($network_id)));
    }
    
    /**
     * Show the login/register page
     */
    public static function register()
    {
        $id   = $_GET['id'];
        $data = Broadstreet_Mini_Utility::getOption($id);
        $api  = new Broadstreet();
        $error= null;
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            if($_POST['type'] == 'login')
            {
                try
                {
                    # At this point, the user should already be logged in from an AJAX request
                    $network_id   = $_POST['network_id'];
                    $network_name = $_POST['network_name'];
                    $network_name = $network_name ? $network_name : get_bloginfo('name');
                    
                    # Hook up an existing network or create a new one
                    if($network_id)
                    {
                        Broadstreet_Mini_Utility::setOption(self::KEY_NETWORK_ID, $network_id);
                    }
                    else
                    {
                        $api = Broadstreet_Mini_Utility::getClient();
                        $resp = $api->createNetwork($network_name);
                        Broadstreet_Mini_Utility::setOption(self::KEY_NETWORK_ID, $resp->id);
                    }
                    
                    # Go to the finalize page
                    $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=finalize&id=$id");
                    header("Location: $next");
                    return;
                }
                catch(Exception $ex)
                {
                    echo $ex->__toString();
                    $error = "That email and password combination isn't valid.";
                }
            }
            else
            {
                try
                {
                    # Register the user by email address
                    $resp = $api->register($_POST['email']);
                    Broadstreet_Mini_Utility::setOption(self::KEY_API_KEY, $resp->access_token);
                    
                    # Create a network for the new user
                    $resp = $api->createNetwork(get_bloginfo('name'));
                    Broadstreet_Mini_Utility::setOption(self::KEY_NETWORK_ID, $resp->id);
                    
                    # Go to the 'finalize' page
                    $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=finalize&id=$id");
                    header("Location: $next"); 
                    return;
                }
                catch(Exception $ex)
                {
                    $error = "There was an error. Do you already have an account with us? Try logging in.";
                }
            }
        }
        
        self::_load('register', array('data' => $data, 'error' => $error));
    }
    
    /**
     * The call for designing what the ad looks like 
     */
    public static function design()
    {
        $id   = $_GET['id'];
        $api  = new Broadstreet();
        $data = Broadstreet_Mini_Utility::getOption($id);
        $fonts= $api->getFonts();
        $error= null;
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $data['ad_x']     = $_POST['x'];
            $data['ad_y']     = $_POST['y'];
            $data['ad_w']     = $_POST['w'];
            $data['ad_h']     = $_POST['h'];
            $data['ad_color'] = $_POST['color'];
            $data['ad_font']  = $_POST['font'];
            $data['ad_default'] = $_POST['default'];
            $data['ad_justify'] = $_POST['justify'];
            $data['ad_proof']   = $_POST['proof'];

            Broadstreet_Mini_Utility::setOption($id, $data);

            if(!$_POST['proof'])
            {
                $error = 'See how your proof looks before moving on.';
            }
            
            if(!$error)
            {
                $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=source&id=$id");
                header("Location: $next");
            }
        }

        self::_load('design', array('data' => $data, 'fonts' => $fonts, 'error' => $error));

    }
    
    /**
     * The call for uploading the ad
     * @return type 
     */
    public static function upload()
    {
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            if(!isset($_FILES['file']) || $_FILES['file']['size'] == 0) 
            {
                self::_load('upload', array('error' => "You didn't upload any files. Try again!"));
                return;
            }
            
            $uploadedfile     = $_FILES['file'];
            $upload_overrides = array('test_form' => false);
            
            $result = wp_handle_upload($uploadedfile, $upload_overrides);
            
            # Make sure we got an image
            if(!strstr($result['type'], 'gif') 
                && !strstr($result['type'], 'jpeg')
                && !strstr($result['type'], 'png'))
            {
                self::_load('upload', array('error' => "That didn't look like an image that was uploaded. We support jpeg, png, gif."));
                return;
            }
            
            if(isset($result['error']))
            {
                self::_load('upload', $result);
                return;
            }
            else
            {
                # Save the ad data in wp_options
                $id      = uniqid('bs_editable_');
                $ad_data = array (
                    'ad_id'   => $id,
                    'ad_url'  => $result['url'],
                    'ad_file' => $result['file']
                );
                
                Broadstreet_Mini_Utility::setOption($id, $ad_data);
                
                $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id=$id");
                header("Location: $next");
            }
        }
        else
        {
            self::_load('upload');
        }
    }
    
    /**
     * Show the settings page 
     */
    public static function settings()
    {
        $id  = @$_GET['id'];
        $api = Broadstreet_Mini_Utility::getClient();
        
        $network_id   = Broadstreet_Mini_Utility::getOption(self::KEY_NETWORK_ID);
        $access_token = Broadstreet_Mini_Utility::getOption(self::KEY_API_KEY);
        $networks     = $api->getNetworks();
        
        $done = false;
        $error = false;
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $new_token = $_POST['access_token'];
            
            try
            {
                $new_api = new Broadstreet($new_token);
                $new_networks = $new_api->getNetworks();
                
                if($new_token !== $access_token)
                {
                    $networks = $new_networks;
                    $network_id = null;
                    Broadstreet_Mini_Utility::setOption(self::KEY_API_KEY, $new_token);
                        
                    if(count($new_networks) > 1)
                    {
                        # User commands mroe than one publisher
                        $error = "One more step. Pick the network below that this site corresponds to.";
                        $networks = $new_networks;
                        $access_token = $new_token;
                    }
                    else
                    {
                        # User commands a single publisher - grab the first
                        Broadstreet_Mini_Utility::setOption(self::KEY_NETWORK_ID, $networks[0]->id);
                        
                        $done = true;
                    }
                }
                else
                {
                    $network_id = $_POST['network_id'];
                    Broadstreet_Mini_Utility::setOption(self::KEY_NETWORK_ID, $network_id);
                    
                    $done = true;
                }
            }
            catch(Exception $ex)
            {
                $error = "That access token doesn't look valid";
                $access_token = $new_token;
            }
        }
        
        # If an ID was passed in, go back to the design page
        if($done && $id)
        {
            $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=design&id=$id");
            header("Location: $next");
            return;
        }
        elseif($done)
        {
            $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=upload&id=$id");
            header("Location: $next");
            return;
        }
        
        self::_load('settings', array('id' => $id, 'access_token' => $access_token, 'network_id' => $network_id, 'networks' => $networks, 'done' => $done, 'error' => $error));
    }
    
    /**
     * Show the page for picking the text source 
     */
    public static function source()
    {
        $id   = $_GET['id'];
        $data = Broadstreet_Mini_Utility::getOption($id);
        
        if($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $data['ad_destination'] = $_POST['destination'];
            
            # Get rid of old data
            unset($data['ad_source']);
            unset($data['ad_facebook']);
            unset($data['ad_twitter']);
            unset($data['ad_phone']);
            unset($data['ad_hashtag']);
            
            if($_POST['source'] == 'text_message')
            {
                $data['ad_source'] = 'text_message';
                $data['ad_phone'] = $_POST['phone'];
                
                if(!Broadstreet_Mini_Utility::isPhoneValid($_POST['phone']))
                    $data['error'] = 'You need a valid-looking phone number to use text messaging. Try numbers-only.';
                
                //if(!$_POST['destination'])
                //    $data['error'] = 'You need to set where the ad will take you after a click (destination)';
            }
            
            if($_POST['source'] == 'facebook')
            {
                $data['ad_source']   = 'facebook';
                $data['ad_facebook'] = $_POST['facebook'];
                $data['ad_hashtag']  = $_POST['hashtag'];
                
                if(!$_POST['facebook'])
                    $data['error'] = "You need to enter the URL of the advertiser's Facebook page";
            }
            
            if($_POST['source'] == 'twitter')
            {
                $data['ad_source']  = 'twitter';
                $data['ad_twitter'] = ltrim($_POST['twitter'], '@');
                $data['ad_hashtag'] = $_POST['hashtag'];
                
                if(!$_POST['twitter'])
                    $data['error'] = 'You need to enter a Twitter username';
            }
            
            if(!isset($data['error']))
            {
                # Woo!
                Broadstreet_Mini_Utility::setOption($id, $data);
                
                # Check for account
                $api_key = Broadstreet_Mini_Utility::getOption(self::KEY_API_KEY);
                
                if(!$api_key)
                {
                    $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=register&id=$id");
                    header("Location: $next");
                }
                else
                {
                    $next = Broadstreet_Mini_Utility::getBaseURL("index.php?action=finalize&id=$id");
                    header("Location: $next");                    
                }
                # Redirect
                
            }
        }
        
        self::_load('source', array('data' => $data));
    }
    
    /**
     * AJAX handler for getting a proof 
     */
    public static function get_proof()
    {
        $id = $_GET['id'];
        $data = Broadstreet_Mini_Utility::getOption($id);
        
        $params = array();
        $params['text_x']       = $_POST['x'];
        $params['text_y']       = $_POST['y'];
        $params['text_width']   = $_POST['w'];
        $params['text_height']  = $_POST['h'];
        $params['text_color']   = $_POST['color'];
        $params['font_id']      = $_POST['font'];
        $params['default_text'] = $_POST['default'];
        $params['alignment']    = $_POST['justify'];
        $params['partner']      = BROADSTREET_PARTNER_NAME;
        
        # Template file
        $params['template_base64'] = base64_encode(file_get_contents($data['ad_file']));
            
        $api = new Broadstreet();
        
        try 
        {
            $response = $api->createProof($params);
            echo json_encode(array('success' => true, 'proof' => $response));
        }
        catch(Exception $ex)
        {
            echo json_encode(array('success' => false, 'message' => $ex->__toString()));
        }
    }
    
    /**
     * AJAX handler for logging in and getting a list of publishers 
     */
    public function login()
    {
        $api  = new Broadstreet();

        if($_POST['type'] == 'login')
        {
            try
            {
                # Log the user in
                $resp = $api->login($_POST['email'], $_POST['password']);
                Broadstreet_Mini_Utility::setOption(self::KEY_API_KEY, $resp->access_token);

                echo json_encode(array('success' => true, 'networks' => $api->getNetworks()));
            }
            catch(Exception $ex)
            {
                echo json_encode(array('success' => false, 'message' => $ex->__toString()));
            }
        }
    }
    
    /**
     * Load a view file. The file should be located in Broadstreet/Views.
     * @param string $file The filename of the view without the extenstion (assumed
     *  to be PHP)
     * @param array $data An associative array of data that be be extracted and
     *  available to the view
     * @param bool $return Return the output instead of outputting it
     */
    public static function _load($file, $data = array(), $return = false, $eval = true)
    {
        $file = dirname(__FILE__) . '/views/' . $file . '.php';

        if(!file_exists($file))
        {
            throw new Exception("View '$file' was not found");
        }

        # Extract the variables into the global scope so the views can use them
        extract($data);

        if(!$return)
        {
            if($eval)
                include($file);
            else
                readfile($file);
        }
        else
        {
            ob_start();
            if($eval)
                include($file);
            else
                readfile($file);
            return ob_get_clean();
        }
    }
}

Broadstreet_Mini::execute();