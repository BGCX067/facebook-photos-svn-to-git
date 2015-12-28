<?php
class TanTanFacebookPlugin {
    var $facebook;
    var $session;
    var $albums;
    var $lastError;
    
    function TanTanFacebookPlugin() {
        add_action('admin_menu', array(&$this, 'addhooks'));
        add_action('load-upload.php', array(&$this, 'addPhotosTab'));
    }

    function addhooks() {
        add_options_page('Facebook', 'Facebook', 10, __FILE__, array(&$this, 'admin'));
    }  

    function admin() {
        require_once(dirname(__FILE__).'/facebookapi_php5_restlib.php');
        $this->facebook = new FacebookRestClient(FACEBOOK_REST_SERVER, FACEBOOK_API_KEY, FACEBOOK_API_SECRET, null, true);
        if ($_POST['action'] == 'get-new-session') {
            $this->saveNewSession();
        }
        
        if ($_POST['action'] == 'savebase') {
            $url = parse_url(get_bloginfo('siteurl'));
            $baseurl = $url['path'] . '/' . $_POST['baseurl'];
            if (!ereg('.*/$', $baseurl)) $baseurl .= '/';

            if (strlen($_POST['baseurl']) <= 0) {
                $baseurl = false;
            }
            update_option('silas_facebook_baseurl_pre', $url['path'] . '/');
            update_option('silas_facebook_baseurl', $baseurl);
        } elseif ($_POST['action'] == 'save') {
            $this->saveNewSession();
        } elseif ($_POST['action'] == 'logout') {
            update_option('tantan_facebook_session', '');
        }
        $baseurl     = get_option('tantan_facebook_baseurl');
        $baseurl_pre = get_option('tantan_facebook_baseurl_pre');
        
        $apikey  = get_option('tantan_facebook_apikey');
        $secret  = get_option('tantan_facebook_secret');
        $session = get_option('tantan_facebook_session');
        if (!$session) {
            $authToken = $this->facebook->auth_createToken();
            //$authToken = $authToken['token'];
            include(dirname(__FILE__).'/admin-options.html');
        } else {
            $this->facebook->session_key = $session['session_key'];
            $this->facebook->session_secret = $session['secret'];
            try {
                $user = array_pop($this->facebook->users_getInfo($session['uid'], array('name', 'first_name', 'last_name')));
                include(dirname(__FILE__).'/admin-options.html');
            } catch (FacebookRestClientException $e) {
                $this->handleException($e);
                echo 
                '<div style="margin:10px 20px;border:2px solid #ccc; padding:10px;">'.
                '<p>Or, you can reset your Facebook.com login.</p>'.
                '<form method="post"><input type="hidden" name="action" value="logout" />'.
                '<input type="submit" value="Reset Facebook.com login &gt;" />'.
                '</form></div>';
            }
        }
    }
    function handleException($e=false) {
        if ($e) $this->lastError = $e;
        switch ($this->lastError->getCode()) {
            case 102: // invalid session / timeout
                $this->showSessionError();
            break;
            case -1000:
                $this->showNotConfigured();
            break;
            default:
                echo '<div id="message" class="error fade"><p><strong>Error ('.$this->lastError->getCode().'): '.$this->lastError->getMessage().'</strong></p>'.
                '<xmp style="overflow:auto">'.$this->lastError->getTraceAsString().'</xmp>'.
                '</div>';
            break;
        }
    }
    function showNotConfigured() {
        echo '<p>&nbsp;</p><p><strong>Error:</strong> The Facebook Photos plugin has not been configured yet.</p>'.
            '<p>You can configure the plugin in the <strong>Options -&gt; Facebook</strong> menu.</p>';
    }
    function showSessionError() {
        $authToken = $this->facebook->auth_createToken();
        include(dirname(__FILE__).'/admin-get-new-session.html');
    }
    function saveNewSession() {
        try {
            $session = $this->facebook->auth_getSession($_POST['auth_token']);
            if ($session['session_key']) {
                //$session['uid_numeric'] = $facebook->users_getLoggedInUser();
                update_option('tantan_facebook_session', $session);
            } else {
                update_option('tantan_facebook_session', '');
                throw new Exception("Error applying Facebook session information.", -1001);
            }
        } catch (FacebookRestClientException $e) {
            $this->handleException($e);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }
    function addPhotosTab() {
        add_filter('wp_upload_tabs', array(&$this, 'wp_upload_tabs'));
        add_action('upload_files_tantan_facebook', array(&$this, 'upload_files_tantan_facebook'));
        add_action('admin_print_scripts', array(&$this, 'upload_tabs_scripts'));
    }
    function wp_upload_tabs ($array) {
    /*
        0 => tab display name, 
        1 => required cap, 
        2 => function that produces tab content, 
        3 => total number objects OR array(total, objects per page), 
        4 => add_query_args
	*/
        if ($this->lastError) {
    	    $args = array();
            $tab = array(
                'tantan_facebook' => array('Photos (Facebook)', 'upload_files', array(&$this, 'handleException'), 0, $args),
                );
            return array_merge($array, $tab);
        } else {
    	    $numAlbums = count($this->albums);
    	    $args = array();
            $tab = array(
                'tantan_facebook' => array('Photos (Facebook)', 'upload_files', array(&$this, 'photosTab'), array($numAlbums, 1), $args),
                );
            return array_merge($array, $tab);
        }
    }

    function upload_tabs_scripts() {
        include(dirname(__FILE__).'/admin-tab-head.html');
    }
    function upload_files_tantan_facebook() {
        require_once(dirname(__FILE__).'/facebookapi_php5_restlib.php');
        $this->facebook = new FacebookRestClient(FACEBOOK_REST_SERVER, FACEBOOK_API_KEY, FACEBOOK_API_SECRET, null, true);
        if ($_POST['action'] == 'get-new-session') {
            $this->saveNewSession();
        }
        try {
            $this->session = get_option('tantan_facebook_session');
            if ($this->session) {
                $this->facebook->session_key = $this->session['session_key'];
                $this->facebook->session_secret = $this->session['secret']; 
        	    $this->albums = $this->facebook->photos_getAlbums($this->session['uid'], '');
            } else {
                throw new Exception('Plugin is not configured', -1000);
            }
        } catch (FacebookRestClientException $e) {
            $this->lastError = $e;
            // see wp_upload_tabs for the catch... kinda ugly, but need to do this because of the way WP plugin hooks / filters are defined
        } catch (Exception $e) {
            $this->lastError = $e;
        }
        
    }
    function photosTab() {
        $offsetpage = (int) $_GET['paged'];
        if (!$offsetpage) $offsetpage = 1;
        $album = $this->albums[$offsetpage-1];
        try {
            $photos = $this->facebook->photos_get('', $album['aid'], '');
            $numPhotos = count($photos);
                //$photos = $facebook->photos_get($session['uid'], '', '');
                //$photos = $facebook->call_method('facebook.fql.query', array('query' => 'SELECT pid, aid, owner, src, src_big, src_small, link, caption, created FROM photo WHERE owner = '.$session['uid']));
            include(dirname(__FILE__).'/admin-photos-tab.html');
        } catch (FacebookRestClientException $e) {
            $this->handleException($e);
        }
    }    
}
?>