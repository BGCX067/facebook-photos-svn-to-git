<?php
/*
Plugin Name: Facebook Photos
Plugin URI: http://tantannoodles.com/toolkit/facebook-photos/
Description: This plugin retrieves your Facebook photos and allows you to easily post them to your WordPress blog.
Author: Joe Tan
Version: 0.3
Author URI: http://tantannoodles.com/

Copyright (C) 2008 Joe Tan

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA


Release Page:
http://tantannoodles.com/toolkit/facebook-photos/

Project Page:
http://code.google.com/p/facebook-photos/

Changlog:
http://code.google.com/p/facebook-photos/wiki/ChangeLog

$Revision: 30 $
$Date: 2008-03-25 03:41:29 +0600 (Tue, 25 Mar 2008) $
$Author: joetan54 $

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