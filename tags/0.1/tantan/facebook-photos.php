<?php
/*
Plugin Name: Facebook Photos
Plugin URI: http://tantannoodles.com/toolkit/facebook-photos/
Description: This plugin retrieves your Facebook photos and allows you to easily post them to your WordPress blog.
Author: Joe Tan
Version: 0.1
Author URI: http://tantannoodles.com/

Copyright (C) 2007 Joe Tan
*/

DEFINE('FACEBOOK_API_SERVER', 'http://api.facebook.com');
DEFINE('FACEBOOK_LOGIN_SERVER', 'http://www.facebook.com');
DEFINE('FACEBOOK_REST_SERVER', FACEBOOK_API_SERVER.'/restserver.php');
DEFINE('FACEBOOK_API_KEY', '4b58483e3f449ac22e7f05e7467e8206');
DEFINE('FACEBOOK_API_SECRET', '45e6af3b1a474745dd30b8824413e0ac');

if (version_compare(phpversion(), '5.0', '>=') && version_compare(get_bloginfo('version'), '2.1', '>=')) {
    if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/') >= 0) { // just load in admin
        require(dirname(__FILE__).'/facebook/plugin-class.php');
        $TanTanFacebookPlugin = new TanTanFacebookPlugin();
    }
} else {
    class TanTanFacebookPhotosError {
        function TanTanFacebookPhotosError() {
            add_action('admin_menu', array(&$this, 'addhooks'));
        }
        function addhooks() {
            add_options_page('Facebook', 'Facebook', 10, __FILE__, array(&$this, 'admin'));
        }
        function admin() {
            include(dirname(__FILE__).'/facebook/admin-version-error.html');
        }
    }
    $error = new TanTanFacebookPhotosError();
}
?>