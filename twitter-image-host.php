<?php
/*
Plugin Name: Twitter Image Host
Plugin URI: http://atastypixel.com/wordpress/plugins/twitter-image-host
Description: Host Twitter images from your blog and keep your traffic, rather than using a service like Twitpic and losing your viewers
Version: 0.1
Author: Michael Tyson
Author URI: http://atastypixel.com/blog
*/

/*  Copyright 2010 Michael Tyson <michael@atastypixel.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('IMAGE_HOST_FOLDER', WP_CONTENT_DIR.'/twitter-image-host-content');
define('IMAGE_HOST_URL', WP_CONTENT_URL.'/twitter-image-host-content');
define('IMAGE_HOST_MAX_FULL_IMAGE_WIDTH', 900);
define('IMAGE_HOST_MAX_FULL_IMAGE_HEIGHT', 800);


// =============================
// =       Template Tags       =
// =============================


function the_twitter_image_url() {
    if ( !$GLOBALS['__twitter_image_host_data'] ) return false;
    return IMAGE_HOST_URL."/".$GLOBALS['__twitter_image_host_data']['name'];
}

function the_twitter_full_image_url() {
    if ( !$GLOBALS['__twitter_image_host_data'] ) return false;
    if ( !$GLOBALS['__twitter_image_host_data']['full'] ) return false;
    return IMAGE_HOST_URL."/".$GLOBALS['__twitter_image_host_data']['full'];
}

function the_twitter_image_title() {
    if ( !$GLOBALS['__twitter_image_host_data'] ) return false;
    return $GLOBALS['__twitter_image_host_data']['title'];
}

function the_twitter_image_author() {
    if ( !$GLOBALS['__twitter_image_host_data'] ) return false;
    return $GLOBALS['__twitter_image_host_data']['author'];
}

function the_twitter_image() {
    return (the_twitter_full_image_url() ? '<a href="'.the_twitter_full_image_url().'" rel="lightbox">' : '') .
            '<img src="'.the_twitter_image_url().'" class="center twitter_image" />'.
           (the_twitter_full_image_url() ? '</a>' : '');
}


// ========================
// =       The Guts       =
// ========================


/**
 * Main plugin entry point
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_run() {
    $siteURL = get_option('siteurl');
    $siteSubdirectory = substr($siteURL, strpos($siteURL, '://'.$_SERVER['HTTP_HOST'])+strlen('://'.$_SERVER['HTTP_HOST']));
    if ( $siteSubdirectory == '/' ) $siteSubdirectory = '';
    $request = ($siteSubdirectory ? str_replace($_SERVER['REQUEST_URI'], $siteSubdirectory, '/') : $_SERVER['REQUEST_URI']);
    
    if ( preg_match('/^\/?twitter-image-host(?:\/(.*))?/', $request, &$matches) ) {
        // API call
        twitter_image_host_server($matches[1]);
        exit;
    }
    
    twitter_image_host_setup(preg_replace('/\.\.+/', '', basename($_SERVER['REQUEST_URI'])));
}

/**
 * API entry point
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_server($command) {

    if ( !$command ) {
        // No command: Assume this is a person navigating here and show them a form
        include('form.inc.php');
        return;
    }
    
    include('class.rsp.php');
    
    // Sanity check
    if ( !in_array($command, array("upload", "uploadAndPost")) ) {
        twitter_image_host_error(INVALID_REQUEST, 'Invalid request');
        return;
    }
    if ( !isset($_FILES['media']) ) {
        twitter_image_host_error(IMAGE_NOT_FOUND, 'No image provided');
        return;
    }
    if ( !isset($_REQUEST["username"]) || !isset($_REQUEST["password"]) ) {
        twitter_image_host_error(INVALID_USER_OR_PASS, 'Invalid username or password');
        return;
    }
    
    $accounts = split(',', get_option('twitter_image_host_twitter_accounts'));
    if ( !in_array($_REQUEST["username"], $accounts) ) {
        twitter_image_host_error(UNAUTHORIZED_ACCOUNT, "Unauthorised Twitter account");
        return;
    }
    
    // Check credentials with Twitter
    include('class.twitter.php');
    $t = new twitter;
    $t->username = $_REQUEST["username"];
    $t->password = $_REQUEST["password"];
    if ( !$t->directMessages() ) {
        if ( $t->responseInfo['http_code'] == 401 ) {
            twitter_image_host_error(INVALID_USER_OR_PASS, 'Invalid username or password');
        } else {
            twitter_image_host_error(TWITTER_OFFLINE, 'Twitter may be offline');
        }
        return;
    }
    
    // Generate tag
    $extension = strtolower(substr($_FILES['media']['name'], strrpos($_FILES['media']['name'], '.')+1));
    do {
        $tag = strtolower(substr(str_replace("=","",base64_encode(rand())), -5));
    } while ( file_exists(IMAGE_HOST_FOLDER."/$tag.$extension") );
    
    // Accept file
    if ( !file_exists(IMAGE_HOST_FOLDER) ) @mkdir(IMAGE_HOST_FOLDER, 0755);
    if ( !move_uploaded_file($_FILES['media']['tmp_name'], IMAGE_HOST_FOLDER."/$tag.$extension") ) {
        twitter_image_host_error(INTERNAL_ERROR, "Couldn't place uploaded file");
        return;
    }
    
    list($width,$height) = @getimagesize(IMAGE_HOST_FOLDER."/$tag.$extension");
    
    if ( !$width ) {
        @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
        twitter_image_host_error(INVALID_IMAGE, "Invalid image");
        return;
    }
    
    $maxwidth = get_option('twitter_image_host_max_width', 500);
    $maxheight = get_option('twitter_image_host_max_height', 500);
    
    if ( $width > $maxwidth || $height > $maxheight ) {
        require_once(ABSPATH . 'wp-admin/includes/image.php' );
        $full = IMAGE_HOST_FOLDER."/$tag-full.$extension";
        rename(IMAGE_HOST_FOLDER."/$tag.$extension", $full);
        $new_file = image_resize($full, $maxwidth, $maxheight);
        rename($new_file, IMAGE_HOST_FOLDER."/$tag.$extension");

        if ( $width > IMAGE_HOST_MAX_FULL_IMAGE_WIDTH || $height > IMAGE_HOST_MAX_FULL_IMAGE_HEIGHT ) {
            $new_file = image_resize($full, IMAGE_HOST_MAX_FULL_IMAGE_WIDTH, IMAGE_HOST_MAX_FULL_IMAGE_HEIGHT);
            rename($new_file, $full);
        }
    }
    
    
    // Write metadata
    if ( ($fd = fopen(IMAGE_HOST_FOLDER."/$tag.meta", "w")) ) {
        fwrite($fd, ($_REQUEST['title']?$_REQUEST['title']:$_REQUEST['message'])."\n".$_REQUEST['username']);
        fclose($fd);
    }
    
    // Generate URL
    $url = (get_option('twitter_image_host_override_url_prefix') ? get_option('twitter_image_host_override_url_prefix') : get_option('siteurl').'/').$tag;
    
    // Post to twitter if asked to
    if ( $command == 'uploadAndPost' || ($_REQUEST['from_form'] && $_REQUEST['tweet']) ) {
        $status = ($_REQUEST['message'] ? $_REQUEST['message'].' '.$url : $url);
        if ( strlen($status) > 140 ) {
            twitter_image_host_error(TWEET_TOO_LONG, 'Tweet is too long');
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            return;
        }
        $response = $t->update($status);
        if ( !$response ) {
            twitter_image_host_error(TWITTER_POST_ERROR, 'Error posting to Twitter');
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            return;
        }
        $userid = $response->user->id;
        $statusid = $response->id;
    }
    
    // Report success
    twitter_image_host_response($tag, $url, $userid, $statusid);
    return;
}


/**
 * Locate image by name
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_setup($name) {
    if ( !$name || !($result=array_filter(glob(IMAGE_HOST_FOLDER."/$name.*"), create_function('$elt', 'return in_array(strtolower(substr($elt,-4)), array(".jpg", "jpeg", ".gif", ".png"));'))) ) {
        return false;
    }

    // Image exists with this name: Prepare to show it
    $tag = $name;
    $name = basename(array_shift($result));
    $base = substr($name, 0, strrpos($name,'.'));
    $extension = substr($name, strrpos($name,'.')+1);
    
    if ( file_exists(IMAGE_HOST_FOLDER."/$base.meta") ) {
        list($title, $author) = file(IMAGE_HOST_FOLDER."/$base.meta");
    }
    
    if ( file_exists(IMAGE_HOST_FOLDER."/$base-full.$extension") ) {
        $full = "$base-full.$extension";
    }
    
    $GLOBALS['__twitter_image_host_data'] = array("tag" => $tag, "name" => $name, "title" => $title, "author" => $author, "full" => $full);
    return true;
}


function twitter_image_host_error($code, $message) {
    if ( $_REQUEST['from_form'] ) {
        $error = $message;
        include('form.inc.php');
    } else {
        RSP::error($code, $message);
    }
}

function twitter_image_host_response($tag, $url, $userid=null, $statusid=null) {
    if ( $_REQUEST['from_form'] ) {
        include('form.inc.php');
    } else {
        RSP::response($tag, $url, $userid, $statusid);
    }
}

// =======================
// =     Presentation    =
// =======================


/**
 * Template redirection
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_template_redirect() {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return;

    $template = locate_template(array('twitter_image_host.php'));
    if ( $template ) {
        // Proper template is available - use that
        include($template);
        exit;
    }
    
    $template = get_single_template();
    
    // Have to inject content into the page template instead
    $post = new StdClass;
    $post->ID = base_convert($GLOBALS['__twitter_image_host_data']['tag'], 36, 10);
    $post->post_author = 0;
    $post->post_date = date( 'Y-m-d H:i:s', filemtime(IMAGE_HOST_FOLDER."/".$GLOBALS['__twitter_image_host_data']['name']) );
    $post->post_content = the_twitter_image();
    $post->post_title = the_twitter_image_title();
    $post->comment_status = (get_option('twitter_image_host_comments_open', true) ? 'open' : 'closed');
    $post->ping_status = (get_option('twitter_image_host_comments_open', true) ? 'open' : 'closed');
    wp_cache_add($post->ID, $post, 'posts');
    
    global $wp_query;
    $wp_query->queried_object = $post;
    $wp_query->is_single = true;
    $wp_query->post_count = 1;
    $wp_query->posts[0] = $post;
    $wp_query->is_404 = false;
    
    include($template);
    
    exit;
}

// ==================================
// =   Filters to aid presentation  =
// ==================================

function twitter_image_host_page_link($permalink, $page) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $permalink;
    return get_option('siteurl')."/".basename(substr(the_twitter_image_url(), 0, strrpos(the_twitter_image_url(), '.')));
}

function twitter_image_host_comments_open_filter($open, $post) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $open;
    return get_option('twitter_image_host_comments_open', true);
}

function twitter_image_host_edit_post_link_filter($link, $post) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $link;
    return '';
}

function twitter_image_host_author_link_filter($link, $authorid, $author_nicename) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $link;
    return 'http://twitter.com/'.the_twitter_image_author();
}

function twitter_image_host_the_author_filter($author) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $author;
    return the_twitter_image_author();
}

function twitter_image_host_query_filter($query) {
    $post_status_query = "SELECT post_status, comment_status FROM wp_posts WHERE ID = ";

    if ( $_SERVER['REQUEST_URI'] == '/wp-comments-post.php' ) {

        if ( strlen($query) > strlen($post_status_query) && !strncmp($query, $post_status_query, strlen($post_status_query)) ) {
            $name = base_convert(substr($query, strlen($post_status_query)), 10, 36);
            if ( !twitter_image_host_setup($name) ) {
                return $query;
            }
            
            return 'SELECT "published" as post_status, "open" as comment_status';
        }
    }
    
    return $query;
}

function twitter_image_host_comment_redirect_filter($location, $comment) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $location;
    return twitter_image_host_page_link().'#comments';
}

function twitter_image_host_get_comments_number_filter($count) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $count;
    return count(get_comments( array('post_id' => base_convert($GLOBALS['__twitter_image_host_data']['tag'], 36, 10), 'status' => 'approve') ));
    
}

// =======================
// =       Options       =
// =======================

/**
 * Settings page
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_options_page() {
    ?>
	<div class="wrap">
	<h2>Twitter Image Host</h2>
	
	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
	
	<table class="form-table">

		<tr valign="top">
    		<th scope="row"><?php _e('Authorized Twitter accounts:') ?></th>
    		<td>
    			<input type="text" id="twitter_image_host_twitter_accounts" name="twitter_image_host_twitter_accounts" value="<?php echo get_option('twitter_image_host_twitter_accounts') ?>" /><br />
    			<?php echo _e('Separate multiple accounts with commas', 'twitter-image-host'); ?>
    		</td>
    	</tr>
    	
    	<tr valign="top">
    		<th scope="row"><?php _e('Commenting:') ?></th>
    		<td>
    			<input type="checkbox" name="twitter_image_host_comments_open" <?php echo (get_option('twitter_image_host_comments_open', true) ? 'checked="checked"' : '') ?> /> Allow comments and trackbacks
    		</td>
    	</tr>
    	
    	<tr valign="top">
    		<th scope="row"><?php _e('Image dimensions:') ?></th>
    		<td>
    		    Maximum width<br/>
    			<input type="text" name="twitter_image_host_max_width" value="<?php echo get_option('twitter_image_host_max_width', 500) ?>" /><br/>
    			Maximum height<br/>
    			<input type="text" name="twitter_image_host_max_height" value="<?php echo get_option('twitter_image_host_max_height', 500) ?>" /><br/>
                <small>Images larger than this will be thumbnailed to this size</small>
    		</td>
    	</tr>
    	
    	<tr valign="top">
    		<th scope="row"><?php _e('Override URL prefix:') ?></th>
    		<td>
    			<input type="text" name="twitter_image_host_override_url_prefix" value="<?php echo get_option('twitter_image_host_override_url_prefix') ?>" /><br/>
                <small>If you have your own .htaccess rewrite rules in place, override the short URL prefix here (Advanced)</small>
    		</td>
    	</tr>
	
	</table>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="twitter_image_host_twitter_accounts, twitter_image_host_comments_open" />
	
	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Save Changes', 'twitter-image-host') ?>" />
	</p>
	
	</form>
	</div>
	<?php
}

/**
 * Set up administration
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 */
function twitter_image_host_setup_admin() {
	add_options_page( 'Twitter Image Host', 'Twitter Image Host', 5, __FILE__, 'twitter_image_host_options_page' );
}


add_action( 'plugins_loaded', 'twitter_image_host_run' );
add_action( 'template_redirect', 'twitter_image_host_template_redirect' );
add_action( 'admin_menu', 'twitter_image_host_setup_admin' );

add_filter( 'page_link', 'twitter_image_host_page_link');
add_filter( 'post_link', 'twitter_image_host_page_link');
add_filter( 'comments_open', 'twitter_image_host_comments_open_filter' );
add_filter( 'pings_open', 'twitter_image_host_comments_open_filter' );
add_filter( 'edit_post_link', 'twitter_image_host_edit_post_link_filter' );
add_filter( 'author_link', 'twitter_image_host_author_link_filter' );
add_filter( 'the_author', 'twitter_image_host_the_author_filter' );
add_filter( 'query', 'twitter_image_host_query_filter' );
add_filter( 'comment_post_redirect', 'twitter_image_host_comment_redirect_filter' );
add_filter( 'get_comments_number', 'twitter_image_host_get_comments_number_filter' );
