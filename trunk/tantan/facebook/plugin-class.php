<?php
/*
$Revision: 33 $
$Date: 2008-03-29 03:06:02 +0600 (Sat, 29 Mar 2008) $
$Author: joetan54 $
*/
class TanTanFacebookPlugin {
    var $facebook;
    var $session;
    var $albums;
    var $photos;
    var $lastError;
    var $perPage; // only applies to photo search (not by albums)
    
    function TanTanFacebookPlugin() {
        add_action('admin_menu', array(&$this, 'addhooks'));
        add_action('load-upload.php', array(&$this, 'addPhotosTab'));// WP < 2.5
        
        // WP >= 2.5
		add_action('media_buttons_context', array(&$this, 'media_buttons')); 
		add_action('media_upload_tantan-facebook-photos', array(&$this, 'media_upload_content'));
		add_action('media_upload_tantan-facebook-photos-of-me', array(&$this, 'media_upload_content_of_me'));
		
        add_action('activate_tantan/facebook-photos.php', array(&$this, 'activate'));
        if ($_GET['tantanActivate'] == 'facebook-photos') {
            $this->showConfigNotice();
        }
        $this->photos = array();
        $this->albums = array();
        $this->perPage = 1000;
    }
    function activate() {
        wp_redirect('plugins.php?tantanActivate=facebook-photos');
        exit;
    }
    function deactivate() {}
    
    function showConfigNotice() {
        add_action('admin_notices', create_function('', 'echo \'<div id="message" class="updated fade"><p>Facebook Photos <strong>activated</strong>. <a href="options-general.php?page=tantan/facebook/plugin-class.php">Configure the plugin &gt;</a></p></div>\';'));
    }

    function addhooks() {
        add_options_page('Facebook', 'Facebook', 10, __FILE__, array(&$this, 'admin'));
        $this->version_check();
    }  
    function version_check() {
        global $TanTanVersionCheck;
        if (is_object($TanTanVersionCheck)) {
            $data = get_plugin_data(dirname(__FILE__).'/../facebook-photos.php');
            $TanTanVersionCheck->versionCheck(603, $data['Version']);
        }
    }
    function admin() {
        if (!class_exists('FacebookRestClient')) require_once(dirname(__FILE__).'/facebookapi_php5_restlib.php');
        $this->facebook = new FacebookRestClient(FACEBOOK_API_KEY, FACEBOOK_API_SECRET, null, true);
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
            $this->facebook->secret = $session['secret'];
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
            case 104: // incorrect signature (app is logged in somewhere else?)
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
        update_option('tantan_facebook_session', '');
        $this->facebook->session_key = '';
        $this->facebook->secret = FACEBOOK_API_SECRET;
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
    function media_buttons($context) {
		global $post_ID, $temp_ID;
		$dir = dirname(__FILE__);
		$pluginRootURL = get_option('siteurl').substr($dir, strpos($dir, '/wp-content'));
		$image_btn = $pluginRootURL.'/icon.gif';
		$image_title = 'Facebook Photos';
		
		$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);

		$media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
		$out = ' <a href="'.$media_upload_iframe_src.'&tab=tantan-facebook-photos&TB_iframe=true&height=500&width=640" class="thickbox" title="'.$image_title.'"><img src="'.$image_btn.'" alt="'.$image_title.'" /></a>';
		return $context.$out;
	}
	function media_upload_content_of_me() {
	    $_REQUEST['tt-type'] = 'tagged';
	    add_filter('tantan_media_upload_page_links', array(&$this, 'media_upload_page_links'));
		
	    $this->media_upload_content('of-me');
	}
	function media_upload_content($type='') {
        $this->upload_files_tantan_facebook();
        
        add_filter('media_upload_tabs', array(&$this, 'media_upload_tabs'));
        add_action('admin_print_scripts', array(&$this, 'upload_tabs_scripts'));
        add_action('admin_print_scripts', 'media_admin_css');
        add_action('tantan_media_upload_header', 'media_upload_header');
        try {
          wp_iframe(array(&$this, 'photosTab'));
        } catch (FacebookRestClientException $e) {
            $this->handleException($e);
        }
		  
	}
	function media_upload_page_links($args) {
	    return paginate_links( $args );
	}
	function media_upload_tabs($tabs) {
		return array(
			'tantan-facebook-photos' => __('My Photos'), // handler action suffix => tab text
			'tantan-facebook-photos-of-me' => __('Photos of Me'),
		);
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
	        $numPhotos = count($this->photos);
	        $paged = array();
	        $args = array('tt-type' => ''); // this doesn't do anything in WP 2.1.2
            $tab = array(
                'tantan_facebook' => array('Photos (Facebook)', 'upload_files', array(&$this, 'photosTab'), $paged, $args),
                );
            return array_merge($array, $tab);
        }
    }

    function upload_tabs_scripts() {
        include(dirname(__FILE__).'/admin-tab-head.html');
    }
    function upload_files_tantan_facebook() {
        if (!class_exists('FacebookRestClient')) require_once(dirname(__FILE__).'/facebookapi_php5_restlib.php');
        $this->facebook = new FacebookRestClient(FACEBOOK_API_KEY, FACEBOOK_API_SECRET, null, true);
        if ($_POST['action'] == 'get-new-session') {
            $this->saveNewSession();
        }
        try {
            $this->session = get_option('tantan_facebook_session');
            if ($this->session) {
                $this->facebook->session_key = $this->session['session_key'];
                $this->facebook->secret = $this->session['secret']; 
                if (isset($POST['aid'])) unset($_REQUEST['tt-type']);
                if ($_REQUEST['tt-type'] == 'tagged') {
                    $this->perPage = 50;
                    $this->photos = $this->facebook->photos_get($this->session['uid'], '', '');
        	    } else {
            	    $this->albums = $this->facebook->photos_getAlbums($this->session['uid'], '');
        	    }
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
        
        try {
            if ($_REQUEST['tt-type'] == 'tagged') {
                $photos = array_slice($this->photos, ($offsetpage-1)*$this->perPage, $this->perPage);
                $numPhotos = count($this->photos);
            
            } else {
                if (isset($_POST['aid'])) {
                    foreach ($this->albums as $album) {
                        if ($album['aid'] == $_POST['aid']) break;
                    }
                    $photos = $this->facebook->photos_get('', $_POST['aid'], '');
                } else {
                    $album = $this->albums[$offsetpage-1];
                    $_POST['aid'] = $album['aid'];
                    $photos = $this->facebook->photos_get('', $album['aid'], '');
                }
                $numPhotos = count($photos);
            }
            $albums = $this->albums;
                //$photos = $facebook->photos_get($session['uid'], '', '');
                //$photos = $facebook->call_method('facebook.fql.query', array('query' => 'SELECT pid, aid, owner, src, src_big, src_small, link, caption, created FROM photo WHERE owner = '.$session['uid']));
            do_action('tantan_media_upload_header');
            $page_links = apply_filters('tantan_media_upload_page_links',array(
        		'base' => add_query_arg( 'paged', '%#%' ),
        		'format' => '',
        		'total' => ceil($numPhotos / $this->perPage),
        		'mid_size' => 1,
        		'current' => ($offsetpage ? $offsetpage : 1),
            ));
            include(dirname(__FILE__).'/admin-photos-tab.html');
        } catch (FacebookRestClientException $e) {
            $this->handleException($e);
        }
    }    
}
?>