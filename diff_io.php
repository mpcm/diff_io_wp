<?php

/* Plugin Name: DIFF.IO
 * Plugin URI: https://github.com/mpcm/diff_io_wp
 * Description: On any post update, trigger a diff.io call
 * Author: Matthew Morley
 * Author URI: http://www.mpcm.com
 * Stable tag: 0.3
 * Version: 0.3
 */

# If this file does note existing, bring it in via composer
if (class_exists('JWT') == false) {
    # or from https://github.com/firebase/php-jwt/blob/master/Authentication/JWT.php
    require_once('JWT.php');
}

function diff_io_update( $post_id ){
	if ( wp_is_post_revision( $post_id ) ){ return; }
	if ( get_post_status( $post_id) != 'publish' ){ return; }
	$post_url = get_permalink( $post_id );
	$apiurl = diff_io_url($post_url);
	$response = wp_remote_get( $apiurl );
}
add_action( 'save_post', 'diff_io_update' );


////////////////////////////////////////////////////
// admin items below this line
////////////////////////////////////////////////////

if( is_admin() ) {
  new DIFFIOSETTINGS();
}

function diff_io_url($url1, $url2 = '') {

  $options = get_option( 'diffio_option_name', Array() );
  if( isset($options['apikey']) ){
    $apikey = $options['apikey'];
  }
  if( isset($options['secret']) ){
    $secret = $options['secret'];
  }
  $host = 'https://api.diff.io/v1/diff/';

  # do not generate requests that the server will just drop
  if( empty( $apikey ) || empty( $secret ) || empty( $host ) ) {
    // add WP admin warning that this module is on, but not configured correctly
    return "";
  }

  # declare the url to capture and diff
  $claims = array(
      "iss" => $apikey,
      "jti" => $apikey . "/" . microtime(),
      "url1" => $url1
  );

  # used for page vs page calls
  if( !empty( $url2 ) ) {
    $claims["url2"] = $url2;
  }

  # generate token
  $jwt = JWT::encode($claims, $secret, "HS256");

  return "{$host}{$apikey}/{$jwt}";
}


class DIFFIOSETTINGS
{
    private $options;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin',
            'DIFF.IO',
            'manage_options',
            'diffio-settings',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        // Set class property
        $this->options = get_option( 'diffio_option_name' );
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>diffio Shortcode settings</h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'diffio_option_group' );
                do_settings_sections( 'diffio-settings' );
                submit_button();
            ?>
            </form>
            <h3><A href="https://www.diff.io">Find your apikey and secret</A></h3>
            <h3><a href="https://www.diff.io/#plans">Not yet a customer</A></h3>
        </div>
        <?php
    }

    public function page_init()
    {
        register_setting('diffio_option_group', 'diffio_option_name', array( $this, 'sanitize' ) );

        add_settings_section(
            'setting_section_id',
            'diffio API Key',
            array( $this, 'print_section_info' ),
            'diffio-settings'
        );

        add_settings_field(
            'apikey',
            'API Key',
            array( $this, 'apikey_callback' ),
            'diffio-settings',
            'setting_section_id'
        );

        add_settings_field(
            'secret',
            'Secret',
            array( $this, 'secret_callback' ),
            'diffio-settings',
            'setting_section_id'
        );
    }

    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['apikey'] ) ) {
            $new_input['apikey'] = sanitize_text_field( $input['apikey'] );
        }
        if( isset( $input['secret'] ) ) {
            $new_input['secret'] = sanitize_text_field( $input['secret'] );
        }
        return $new_input;
    }

    public function print_section_info() {
        print 'Enter your settings below:';
    }

    public function apikey_callback() {
        printf(
            '<input type="text" id="apikey" name="diffio_option_name[apikey]" value="%s" />',
            isset( $this->options['apikey'] ) ? esc_attr( $this->options['apikey']) : ''
        );
    }

    public function secret_callback() {
        printf(
            '<input type="text" id="secret" name="diffio_option_name[secret]" value="%s" />',
            isset( $this->options['secret'] ) ? esc_attr( $this->options['secret']) : ''
        );
    }
}
