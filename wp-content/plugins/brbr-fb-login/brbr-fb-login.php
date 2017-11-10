<?php
/**
 * Plugin Name:       brbr fb login
 * Description:       facebook social login for wordpress
 * Version:           0.0.1
 * Author:            brbr
 * Text Domain:       brbr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Original codebase: https://github.com/2Fwebd/alka-facebook
 */

/* 
* Import the Facebook SDK and load all the classes
*/
//include (plugin_dir_path( __FILE__ ) . 'vendor/autoload.php');
require_once __DIR__ . '/vendor/autoload.php'; // change path as needed

/*
 * Classes required to call the Facebook API
 * They will be used by our class
 */
use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;
use Dotenv\Dotenv;

class brbrFacebookLogin {

     /**
     * Facebook APP ID
     *
     * @var string
     */
    private $app_id;
    
    /**
    * Facebook APP Secret
    *
    * @var string
    */
    private $app_secret;
    
    /**
    * Callback URL used by the API
    *
    * @var string
    */
    private $callback_url;

    /**
     * constructor
     */
    public function __construct() {

        $dotenv = new Dotenv(__DIR__);
        $dotenv->load();

        $this->app_id = getenv('APP_ID');
        $this->app_secret = getenv('APP_SECRET');

        $this->callback_url = admin_url('admin-ajax.php') . '?action=brbr_facebook_login';
        
        // We register our shortcode
        add_shortcode( 'brbr_facebook_login', array($this, 'generateShortcode') );
 
    }

    /**
     * Render the shortcode [alka_facebook/]
     *
     * It displays our Login / Register button
     */
    public function generateShortcode() {
        
        // No need for the button is the user is already logged
        if ( is_user_logged_in()) {
            return;
        }
            
        /* Different labels according to whether the user is allowed to 
        register or not*/
        if (get_option( 'users_can_register' )) {
            $button_label = __('Login or Register with Facebook', 'brbr');
        } else {
            $button_label = __('Login with Facebook', 'brbr');
        }
        
        // Button markup
        $html = '<div id="brbr-facebook-wrapper">';
        $html .= '<a href="#" id="brbr-facebook-button">'.$button_label.'</a>';
        $html .= '</div>';
        
        return $html;
        
    }

    /*
    * Init the API Connection
    *
    * @return Facebook
    */
   private function init_fb_api() {
    
       $facebook = new Facebook([
           'app_id' => $this->app_id,
           'app_secret' => $this->app_secret,
           'default_graph_version' => 'v2.10',
           'persistent_data_handler' => 'session'
       ]);
    
       return $facebook;
    
   }
    
}
 
/*
 * Starts our plugins, easy!
 */
new brbrFacebookLogin();