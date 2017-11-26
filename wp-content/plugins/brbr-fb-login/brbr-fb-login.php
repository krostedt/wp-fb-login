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
    * Access token from Facebook
    *
    * @var string
    */
   private $access_token;

   /**
     * Where we redirect our user after the process
     *
     * @var string
     */
    private $redirect_url;

    /**
     * User details from the API
     */
    private $facebook_details;

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
        add_shortcode( 'brbr_facebook_login', array($this, 'generate_shortcode') );
        add_action( 'wp_ajax_brbr_facebook_login', array($this, 'api_callback'));
        add_action( 'wp_ajax_nopriv_brbr_facebook_login', array($this, 'api_callback'));
 
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

        // We save the URL for the redirection:
        if ( !isset($_SESSION['brbr_facebook_url']) ) {
            $_SESSION['brbr_facebook_url'] = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];            
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

        // Messages
        if(isset($_SESSION['brbr_facebook_message'])) {
            $message = $_SESSION['brbr_facebook_message'];
            $html .= '<div id="brbr-facebook-message" class="alert alert-danger">'; 
            if (is_array($message)) {
                foreach ($message as $item) {
                    $html .= $item;
                } 
            } else {
                $html .= $message;
            }
            $html .= '</div>';

            // We remove them from the session
            unset($_SESSION['brbr_facebook_message']);
        }

        $html .= '<a href="' . $this->get_login_url() . '" id="brbr-facebook-button">' . $button_label . '</a>';
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

    private function get_token() {

        // Assign the Session variable for Facebook
        $_SESSION['FBRLH_state'] = $_GET['state'];

        $fb = $this->init_fb_api();

        // Load the Facebook SDK helper
        $helper = $fb->getRedirectLoginHelper();

        // Try to get an access token
        try {
            $access_token = $helper->getAccessToken();
        }
        // When Graph returns an error
        catch(FacebookResponseException $e) {
            $error = __('Graph returned an error: ', 'brbr') . $e->getMessage();
            $message = array(
                'type' => 'error',
                'content' => $error
            );
        }

        // When validation fails or other local issues
        catch(FacebookSDKException $e) {
            $error = __('Facebook SDK returned an error: ', 'brbr') . $e->getMessage();
            $message = array(
                'type' => 'error',
                'content' => $error
            );
        }

        // if token wasn't obtained, set an error message
        if ( !isset($access_token) ) {
            // Report our errors
            $_SESSION['brbr_facebook_message'] = $message;
            // Redirect
            header("Location: ".$this->redirect_url, true);
            die();
        }


 
        return $access_token->getValue();

    }

    private function get_user_details() {

        $fb = $this->init_fb_api();
        try {
            $response = $fb->get('/me?fields=id,name,first_name,last_name,email,link', $this->access_token);
        } catch(FacebookResponseException $e) {
            $message = __('Graph returned an error: ', 'brbr') . $e->getMessage();
            $message = array(
                'type' => 'error',
                'content' => $error
            );
        } catch(FacebookSDKException $e) {
            $message = __('Facebook SDK returned an error: ', 'brbr') . $e->getMessage();
            $message = array(
                'type' => 'error',
                'content' => $error
            );
        }

        if ( isset($message) ) {
            // Report our errors
            $_SESSION['brbr_facebook_message'] = $message;
            // Redirect
            header("Location: ".$this->redirect_url, true);
            die();
        }
 
        return $response->getGraphUser();

    }

    private function login_user() {

        // We look for the `facebook_id` to see if there is any match
        $wp_users = get_users(array(
            'meta_key'     => 'brbr_facebook_id',
            'meta_value'   => $this->facebook_details['id'],
            'number'       => 1,
            'count_total'  => false,
            'fields'       => 'id',
        ));
    
        if( empty($wp_users[0]) ) {
            return false;
        }
    
        // Log the user ?
        wp_set_auth_cookie( $wp_users[0] );

        return true;

    }

    private function create_user() {

        $fb_user = $this->facebook_details;
        
        // Create an username
        $user_name = sanitize_user(str_replace(' ', '_', strtolower($this->facebook_details['name'])));
        
        // Creating our user
        $new_user = wp_insert_user([
            'user_login'  => $user_name,
            'user_email'  => $fb_user['email'],
            'first_name'  => $fb_user['first_name'],
            'last_name'   => $fb_user['last_name'],
            'user_url'    => $fb_user['link'],
            'user_pass'   => wp_generate_password()
        ]);
        
        if (is_wp_error($new_user)) {
            // Report our errors
            $_SESSION['brbr_facebook_message'] = $new_user->get_error_message();
            // Redirect
            header("Location: ".$this->redirect_url, true);
            die();
        }
        
        // Setting the meta
        update_user_meta( $new_user, 'brbr_facebook_id', $fb_user['id'] );
    
        // Log the user ?
        wp_set_auth_cookie( $new_user );

    }
    
    /**
     * initiate a session if not already active
     */
    public function start_session() {

        if ( !session_id() ) {
            session_start();
        }

    }

    /**
     * ajax api callback
     */
    public function api_callback() {

        // Set the Redirect URL:
        $this->redirect_url = isset($_SESSION['brbr_facebook_url']) ? $_SESSION['brbr_facebook_url'] : home_url();
        $fb = $this->init_fb_api();

        // We save the token in our instance
        $this->access_token = $this->get_token($fb);

        // We get the user details
        $this->facebook_details = $this->get_user_details($fb);

        // We first try to login the user
        if ( !$this->login_user() ) {

            // Otherwise, we create a new account
            $this->create_user();

        }

        // Redirect the user
        wp_redirect( $$this->redirect_urlurl );
        exit;

    }

    
}
 
new brbrFacebookLogin();