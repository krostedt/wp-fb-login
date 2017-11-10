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
        

        add_action('init', array( $this, 'start_session' ), 1);

        // We register our shortcode
        add_shortcode( 'brbr_facebook_login', array($this, 'generate_shortcode') );
 
    }

    /**
     * Render the shortcode [brbr_facebook_login/]
     *
     * It displays our Login / Register button
     */
    public function generate_shortcode() {

        if ( is_user_logged_in() ) {
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
        $html .= '<a href="' . $this->get_login_url() . '" id="brbr-facebook-button">'.$button_label.'</a>';
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

   /*
    * Login URL to Facebook API
    *
    * @return string
    */
    private function get_login_url() {
        
        $fb = $this->init_fb_api();
        $helper = $fb->getRedirectLoginHelper();
        
        // Optional permissions
        $permissions = ['email'];
        
        $url = $helper->getLoginUrl($this->callback_url, $permissions);
        
        return esc_url($url);
        
    }
    
    public function start_session() {

        if ( !session_id() ) {
            session_start();
        }

    }

    
}
 
new brbrFacebookLogin();