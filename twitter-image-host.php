<?php
/*
Plugin Name: Twitter Image Host
Plugin URI: http://atastypixel.com/blog/wordpress/plugins/twitter-image-host
Description: Host Twitter images from your blog and keep your traffic, rather than using a service like Twitpic and losing your viewers
Version: 0.5
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
define('IMAGE_HOST_SQUARE_THUMB_WIDTH', 75);
define('IMAGE_HOST_SQUARE_THUMB_HEIGHT', 75);
define('IMAGE_HOST_MAX_PROPORTIONAL_THUMB_WIDTH', 127);
define('IMAGE_HOST_MAX_PROPORTIONAL_THUMB_HEIGHT', 127);


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
            '<img src="'.the_twitter_image_url().'" class="aligncenter twitter_image" />'.
           (the_twitter_full_image_url() ? '</a>' : '');
}


// ========================
// =     Entry Point      =
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
    $request = ($siteSubdirectory ? preg_replace("/\/\/+/", "/", '/'.str_replace($siteSubdirectory, '/', $_SERVER['REQUEST_URI'])) : $_SERVER['REQUEST_URI']);
    
    if ( preg_match('/^\/?twitter-image-host(?:\/(.*))?/', $request, &$matches) ) {
        // API call
        twitter_image_host_server($matches[1]);
        exit;
    }
    
    if ( !twitter_image_host_setup(preg_replace('/\.\.+/', '', basename($_SERVER['REQUEST_URI']))) ) {
        if ( $_REQUEST['p'] ) {
            // Try using the post id
            twitter_image_host_setup(base_convert($_REQUEST['p'], 10, 36));
        }
    }
    
    if ( strstr($_SERVER['REQUEST_URI'], 'wp-trackback.php') !== false ) {
        twitter_image_host_setup_state();
    }
}

/**
 * API entry point
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_server($command) {

    foreach ( array("username", "password", "message", "title") as $var ) {
        $_REQUEST[$var] = stripslashes($_REQUEST[$var]);
    }

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
    if ( $t->directMessages() === false ) {
        if ( $t->responseInfo['http_code'] == 401 ) {
            twitter_image_host_error(INVALID_USER_OR_PASS, 'Invalid username or password');
        } else {
            twitter_image_host_error(TWITTER_OFFLINE, "Twitter may be offline (".($t->responseInfo['http_code'] ? "response code ".$t->responseInfo['http_code'] : "couldn't connect").")");
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
    $url = preg_replace('/\/$/', '', (get_option('twitter_image_host_override_url_prefix') ? get_option('twitter_image_host_override_url_prefix') : get_option('siteurl'))).'/'.$tag;
    
    // Post to twitter if asked to
    if ( $command == 'uploadAndPost' || ($_REQUEST['from_form'] && $_REQUEST['tweet']) ) {
        $status = ($_REQUEST['message'] ? $_REQUEST['message'].' '.$url : $url);
        if ( strlen($status) > 140 ) {
            $count = strlen($status)-140;
            twitter_image_host_error(TWEET_TOO_LONG, "Tweet is too long by $count characters");
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            return;
        }
        $response = $t->update($status);
        if ( !$response ) {
            if ( $t->responseInfo['http_code'] == 401 ) {
                twitter_image_host_error(INVALID_USER_OR_PASS, 'Invalid username or password');
            } else {
                twitter_image_host_error(TWITTER_OFFLINE, "Error posting to Twitter (".($t->responseInfo['http_code'] ? "response code ".$t->responseInfo['http_code'] : "couldn't connect").")");
            }
            
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
 * Widget
 *
 * @param args Widget arguments
 * @param params Multi-widget parameters
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_widget($args, $params) {

    if ( is_admin() ) return; // Don't bother when admin

    // Load options specific to this widget instance
    if ( is_numeric($params) ) $params = array( 'number' => $params );
    $params = wp_parse_args( $params, array( 'number' => -1 ) );
    $id = $params['number'];
    $allOptions = get_option('twitter_image_host_widget');
    if ( !$allOptions ) $allOptions = array();
    $options = &$allOptions[$id];
    
    echo $args['before_widget'];
    if ( $options['title'] ) {
		echo $args['before_title'].htmlspecialchars($options['title']).$args['after_title'];
	}

    $items = twitter_image_host_all_items($options);
    twitter_image_host_render_items($items, $options);
    
    echo $args['after_widget'];
}


/**
 * Shortcode function
 *
 *  Available parameters:
 *      count           Number of items to display
 *      view            Image thumbnail view: squares, proportional, or large
 *      author          Comma-separated list of Twitter account names to limit results to
 *      columns         Number of columns of images to display 
 *      lightbox        'true' to use Lightbox/Thickbox
 *
 * @param options Attributes from shortcode
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_images_shortcode($options) {
    $options = array_map('html_entity_decode', $options);
    ob_start();
    
    $items = twitter_image_host_all_items($options);
    twitter_image_host_render_items($items, $options);
    
    $contents = ob_get_contents();
    ob_end_clean();
    
    return $contents;
}


// ========================
// =      Renderers       =
// ========================


/**
 * Render posted images
 *
 * @param items Array of objects, each representing an item with properties id, page, thumbnail, image, title, author
 * @param options Options associative array
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_render_items($items, $options) {
    ?>
    <div class="twitter_image_host_images_container">
    <?php if ( count($items) == 0 ) : ?>
        <p class="twitter_image_host_message">There are no items to display</p>
    <?php else : ?>
        <?php $count = 0; ?>
        <?php foreach ( $items as $item ) : ?>

            <div class="twitter_image_host_item twitter_image_host_item_view_<?php echo ( $options['view'] ? $options['view'] : 'squares' ) ?>">
                <?php if ( $options['lightbox'] ) : ?>
                    <a href="<?php echo $item->image ?>" class="thickbox" rel="lightbox[twitter_image_host]" 
                        title="<?php echo htmlspecialchars("$item->title from <a href=\"http://twitter.com/$item->author\">$item->author</a> | <a href=\"$item->page\">View</a>") ?>">
                <?php else : ?>
                    <a href="<?php echo $item->page ?>">
                <?php endif; ?>
                <span></span>
                <img src="<?php echo $item->thumbnail ?>" title="<?php echo htmlspecialchars($item->title) ?>" alt="<?php echo htmlspecialchars($item->title) ?>" />
                </a>
            </div>
            
            <?php 
            $count++;
            if ( $options['columns'] && ($count%$options['columns'])==0 ) echo '<br />';
            ?>
        <?php endforeach; ?>
        
    <?php endif; ?>
    
    </div>
    <?php
}

// ========================
// =        Helpers       =
// ========================



/**
 * Locate image by name
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function twitter_image_host_setup($name) {
    $name = strtolower($name);
    
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
    
    $GLOBALS['__twitter_image_host_data'] = array(
        "tag" => $tag,
        "numeric_tag" => base_convert($tag, 36, 10),
        "name" => $name, 
        "title" => trim($title), 
        "author" => trim($author), 
        "full" => $full);
        
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


/**
 * Load all posted images
 *
 * @return Array of objects, each representing an item with properties id, page, thumbnail, image, title, author
 * @param options Options associative array
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_all_items($options) {

    $authors = (trim($options['author']) ? array_map('trim', explode(',', $options['author'])) : null);
    
    if ( !($dir = @opendir(IMAGE_HOST_FOLDER)) ) return array();
    $items = array();
    while (($file = readdir($dir))) {
        if ( !preg_match('/^([a-z0-9]{5})\.(jpg|jpeg|png|gif)$/i', $file, $matches) ) continue;

        $id = $matches[1];
        $extension = $matches[2];
        $title = $author = $full = $thumbnail = $view = null;
        
        if ( file_exists(IMAGE_HOST_FOLDER."/$id.meta") ) {
            list($title, $author) = file(IMAGE_HOST_FOLDER."/$id.meta");
        }
        if ( $authors && !in_array($author, $authors) ) continue; 

        if ( file_exists(IMAGE_HOST_FOLDER."/$id-full.$extension") ) {
            $full = "$id-full.$extension";
        }
        
        $view = ($options['view'] ? $options['view'] : 'squares');
        if ( file_exists(IMAGE_HOST_FOLDER."/$id-thumb-$view.$extension") ) {
            $thumbnail = "$id-thumb-$view.$extension";
        }
        
        if ( !$thumbnail ) {
            require_once(ABSPATH . 'wp-admin/includes/image.php' );
            $thumbnail = "$id-thumb-$view.$extension";
            switch ( $view ) {
                case 'large':
                    $thumbnail = $file;
                    break;
                case 'proportional':
                    $new_file = image_resize(IMAGE_HOST_FOLDER."/".($full ? $full : $file), IMAGE_HOST_MAX_PROPORTIONAL_THUMB_WIDTH, IMAGE_HOST_MAX_PROPORTIONAL_THUMB_HEIGHT);
                    rename($new_file, IMAGE_HOST_FOLDER."/$thumbnail");
                    break;
                case 'squares':
                    $new_file = image_resize(IMAGE_HOST_FOLDER."/".($full ? $full : $file), IMAGE_HOST_SQUARE_THUMB_WIDTH, IMAGE_HOST_SQUARE_THUMB_HEIGHT, true);
                    rename($new_file, IMAGE_HOST_FOLDER."/$thumbnail");
                default:
                    break;
            }
        }
        
        $baseurl = trailingslashit(get_option('siteurl'));
        $item = new StdClass;
        $item->id             = $id;
        $item->page           = $baseurl.$id;
        $item->image          = trailingslashit(IMAGE_HOST_URL).($full ? $full : $file);
        $item->thumbnail      = trailingslashit(IMAGE_HOST_URL).$thumbnail;
        $item->title          = trim($title);
        $item->author         = trim($author);
        $items[] = $item;
    }
    return $items;
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
    twitter_image_host_setup_state();

    if ( is_feed() || is_trackback() ) return;

    $template = locate_template(array('twitter-image-host.php'));
    if ( !$template ) {
        // Fall back to single template
        $template = get_single_template();
    }

    global $wp_query, $post, $posts, $comments;    
    include($template);
    
    exit;
}

/**
 * Method to set up state correctly to display image
 */
function twitter_image_host_setup_state() {
    global $post, $wp_query, $posts, $comments;
    
    // Prepare a pseudo post
    $post = new StdClass;
    $post->ID = $GLOBALS['__twitter_image_host_data']['numeric_tag'];
    $post->post_author = 0;
    $post->post_date = date( 'Y-m-d H:i:s', filemtime(IMAGE_HOST_FOLDER."/".$GLOBALS['__twitter_image_host_data']['name']) );
    $post->post_content = the_twitter_image();
    $post->post_title = the_twitter_image_title();
    $post->comment_status = (get_option('twitter_image_host_comments_open', true) ? 'open' : 'closed');
    $post->ping_status = (get_option('twitter_image_host_comments_open', true) ? 'open' : 'closed');
    wp_cache_add($post->ID, $post, 'posts');
    $posts = array($post);
    $GLOBALS['__twitter_image_host_data']['post'] = $post;
    
    $wp_query->queried_object = $post;
    $wp_query->post_count = 1;
    $wp_query->posts[0] = $post;
    $wp_query->is_404 = false;
    
    if ( $wp_query->is_feed ) {
        // Support comment feed
        $wp_query->comment_count = twitter_image_host_get_comments_number_filter(0);
        $wp_query->comments = $comments = get_comments( array('post_id' => $post->ID, 'status' => 'approve', 'order' => 'ASC') );
        $wp_query->is_comment_feed = true;
    } else {
        $wp_query->is_single = true;
    }
}

// ==================================
// =   Filters to make it all work  =
// ==================================

function twitter_image_host_posts_filter($posts) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $posts;
    return array($GLOBALS['__twitter_image_host_data']['post']);
}

function twitter_image_host_post_link($permalink, $post=null) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $permalink;
    return get_option('siteurl')."/".basename(substr(the_twitter_image_url(), 0, strrpos(the_twitter_image_url(), '.')));
}

function twitter_image_host_post_comments_feed_link_filter($link) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $link;
    if ( '' != get_option('permalink_structure') ) {
        return trailingslashit(get_option('home')) . "comments/feed/?p=".$GLOBALS['__twitter_image_host_data']['numeric_tag'];
    } else {
        return trailingslashit(get_option('home')) . "?feed=comments-rss2&amp;p=".$GLOBALS['__twitter_image_host_data']['numeric_tag'];
    }
}

function twitter_image_host_trackback_url_filter($link) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $link;
    return get_option('siteurl') . '/wp-trackback.php?p=' . $GLOBALS['__twitter_image_host_data']['numeric_tag'];
}

function twitter_image_host_comments_open_filter($open, $post=null) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $open;
    return get_option('twitter_image_host_comments_open', true);
}

function twitter_image_host_edit_post_link_filter($link, $post=null) {
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
    global $wpdb;
    $post_status_query = "SELECT post_status, comment_status FROM $wpdb->posts WHERE ID = ";
    
    if ( strpos($_SERVER['REQUEST_URI'], '/wp-comments-post.php') !== false ) {

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
    return twitter_image_host_post_link().'#comments';
}

function twitter_image_host_get_comments_number_filter($count) {
    if ( !isset($GLOBALS['__twitter_image_host_data']) ) return $count;
    return count(get_comments( array('post_id' => base_convert($GLOBALS['__twitter_image_host_data']['tag'], 36, 10), 'status' => 'approve') ));
    
}


// =====================================================
// =       Widget initialisation & configuration       =
// =====================================================

/**
 * Register widget on startup
 *
 *  Allows multiple copies of widget to be used.  Derived from
 *  the built-in text widget, wp-includes/widgets.php:1037
 *
 * @since 0.5
 * @author Michael Tyson
 */
function twitter_image_host_init() {
    
    // Add stylesheet
    wp_register_style('twitter-image-host-css', WP_PLUGIN_URL.'/twitter-image-host/style.css');
    wp_enqueue_style('twitter-image-host-css');
    
    $options = get_option('twitter_image_host_widget');
    if ( !$options ) $options = array();
    
    $widget_opts  = array('classname' => 'twitter_image_host_widget', 'description' => __('Display images posted to Twitter'));
    $control_opts = array('id_base' => 'twitter_image_host_widget');
    $name         = __('Twitter Images');
    
    if ( count($options) == 0 ) {
        // No widget copies - Register using a generic template
        $identifier = "twitter_image_host_widget-1";
        wp_register_sidebar_widget($identifier, $name, 'twitter_image_host_widget', $widget_opts, array('number' => -1));
        wp_register_widget_control($identifier, $name, 'twitter_image_host_widget_control', $control_opts, array('number' => -1));
        return;
    }
    
    // Iterate through all widget copies
    foreach ( $options as $id => $values ) {
        
        // "Old widgets can have null values for some reason" - wp-includes/widgets.php:1046
        if ( !$values ) continue;
        
        // Register widget and control
        $identifier = "twitter_image_host_widget-$id";
        wp_register_sidebar_widget($identifier, $name, 'twitter_image_host_widget', $widget_opts, array('number' => $id));
        wp_register_widget_control($identifier, $name, 'twitter_image_host_widget_control', $control_opts, array('number' => $id));
    }


}

/**
 * Load default widget preferences
 *
 * @return Associative array of defaults
 * @since 0.1
 * @author Michael Tyson
 **/
function twitter_image_host_widget_defaults() {
    return array(
        'title'       =>  __('Twitter Images'),
        'count'       => 10,
        'columns'     => '',
        'view'        => 'squares',
        'author'      => '',
        'lightbox'    => false
    );
}

/**
 * Update widget options
 *
 *  Allows multiple copies of widget to be used.  Derived from
 *  the built-in text widget, wp-includes/widgets.php:970
 *
 * @since 0.5
 * @author Michael Tyson
 **/
function twitter_image_host_widget_control_update() {
    
    // Only perform this function once when saving, not for each instance
    static $updated = false;
    if ( $updated ) return;
    $updated = true;
	
	global $wp_registered_widgets;
	
    $options = get_option('twitter_image_host_widget');
    if ( !is_array($options) ) $options = array();
    
    // Get name of this sidebar
    $sidebar = $_POST['sidebar'];
    
    // Get array of widgets in sidebar
    $widgets = wp_get_sidebars_widgets();
	if ( is_array($widgets[$sidebar]) )
		$this_sidebar = &$widgets[$sidebar];
	else
		$this_sidebar = array();


    // Check for removals, delete corresponding options
	foreach ( $this_sidebar as $widget_id ) {
		$number = $wp_registered_widgets[$widget_id]['params'][0]['number'];
		
	    // If this is one of our widgets (callback is ours and number specified)
		if ( $wp_registered_widgets[$widget_id]['callback'] == 'twitter_image_host_widget' && is_numeric($number) ) {
		    
			if ( !in_array( "twitter_image_host_widget-$number", $_POST['widget-id'] ) ) {
			    // the widget has been removed.
				unset($options[$number]);
			}
		}
	}

	foreach ( $_POST['twitter_image_host_widget'] as $number => $widget ) {
		if ( !isset($widget['title']) && isset($options[$number]) ) {
		    // User clicked cancel
			continue;
		}
		
		$fields = array_keys(twitter_image_host_widget_defaults());
		
		unset($options[$number]);
		foreach ( $fields as $field ) {
		    $options[$number][$field] = stripslashes($widget[$field]);
	    }
	}
	
	update_option('twitter_image_host_widget', $options);

}

/**
 * Display and process widget options
 *
 *  Allows multiple copies of widget to be used.  Derived from
 *  the built-in text widget, wp-includes/widgets.php:970
 *
 * @param params Multi-widget parameters
 * @since 0.5
 * @author Michael Tyson
 **/
function twitter_image_host_widget_control($params=null) {

    if ( !empty($_POST['twitter_image_host_widget']) ) {
        // Update options
        twitter_image_host_widget_control_update();
    }

    // Load options specific to this instance
    if ( is_numeric($params) ) $params = array( 'number' => $params );
    $params = wp_parse_args( $params, array( 'number' => -1 ) );
    $id = $params['number'];
    $options = get_option('twitter_image_host_widget');
    if ( $id == -1 ) {
        $options = twitter_image_host_widget_defaults(); 
        $id = '%i%';
    } else {
        $options = $options[$id];
    }
    
    $title       = htmlspecialchars($options['title']);
    $author      = htmlspecialchars($options['author']);
    $view        = $options['view'];
    $count       = (int)$options['count'];
    $columns     = $options['columns'];
    $lightbox    = $options['lightbox'];
    
    ?>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_title"><?php _e('Title:') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_title" name="twitter_image_host_widget[<?php echo $id ?>][title]" value="<?php echo $title ?>" />
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_title"><?php _e('Limit to images from Twitter account(s):') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_author" name="twitter_image_host_widget[<?php echo $id ?>][author]" value="<?php echo $author ?>" /><br/>
        <small>One or more Twitter accounts separated by commas.  Leave blank to show images from all Twitter accounts.</small>
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_count"><?php _e('Number of items to show:') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_count" name="twitter_image_host_widget[<?php echo $id ?>][count]" size="4" value="<?php echo $count ?>" />
    </p>
    <p>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_squares" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="squares" <?php echo ($view=='squares'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_squares"><?php _e('View as square thumbnails') ?></label><br/>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_proportional" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="proportional" <?php echo ($view=='proportional'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_proportional"><?php _e('View as proportional thumbnails') ?></label><br/>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_large" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="large" <?php echo ($view=='large'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_large"><?php _e('View as large thumbnails') ?></label><br/>
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_columns"><?php _e('Number of columns to display:') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_columns" name="twitter_image_host_widget[<?php echo $id ?>][columns]" size="4" value="<?php echo $columns ?>" /><br/>
        <small>Leave blank to not separate items into columns</small>
    </p>
    <p>
        <input type="checkbox" id="twitter_image_host_widget_<?php echo $id ?>_lightbox" name="twitter_image_host_widget[<?php echo $id ?>][lightbox]" <?php echo ($lightbox?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_lightbox"><?php _e('Use Lightbox, etc.') ?></label><br/>
        <small>You must have Lightbox/Thickbox/etc installed for this to work</small>
    </p>
    
    <?php
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
	<input type="hidden" name="page_options" value="twitter_image_host_twitter_accounts, twitter_image_host_comments_open, twitter_image_host_override_url_prefix" />
	
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


add_action( 'init', 'twitter_image_host_init' );

add_action( 'plugins_loaded', 'twitter_image_host_run' );
add_action( 'template_redirect', 'twitter_image_host_template_redirect' );
add_action( 'admin_menu', 'twitter_image_host_setup_admin' );

add_filter( 'the_posts', 'twitter_image_host_posts_filter' );
add_filter( 'page_link', 'twitter_image_host_post_link');
add_filter( 'post_link', 'twitter_image_host_post_link');
add_filter( 'post_comments_feed_link', 'twitter_image_host_post_comments_feed_link_filter' );
add_filter( 'trackback_url', 'twitter_image_host_trackback_url_filter' );
add_filter( 'comments_open', 'twitter_image_host_comments_open_filter' );
add_filter( 'pings_open', 'twitter_image_host_comments_open_filter' );
add_filter( 'edit_post_link', 'twitter_image_host_edit_post_link_filter' );
add_filter( 'author_link', 'twitter_image_host_author_link_filter' );
add_filter( 'the_author', 'twitter_image_host_the_author_filter' );
add_filter( 'query', 'twitter_image_host_query_filter' );
add_filter( 'comment_post_redirect', 'twitter_image_host_comment_redirect_filter' );
add_filter( 'get_comments_number', 'twitter_image_host_get_comments_number_filter' );

add_shortcode('twitter-images', 'twitter_image_host_images_shortcode');
