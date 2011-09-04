<?php
/*
Plugin Name: Twitter Image Host
Plugin URI: http://atastypixel.com/blog/wordpress/plugins/twitter-image-host
Description: Host Twitter images from your blog and keep your traffic, rather than using a service like Twitpic and losing your viewers
Version: 0.6.2
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

/**
 * Get the URL to the view page
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_image_permalink() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->page : ($__displayed_twitter_image ? $__displayed_twitter_image->page : false) );
}

/**
 * Get the URL to the image
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_image_url() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->thumbnail : ($__displayed_twitter_image ? $__displayed_twitter_image->thumbnail : false) );
}

/**
 * Get the URL to the full-sized image, if one exists, or false otherwise
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_full_image_url() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->full_image : ($__displayed_twitter_image ? $__displayed_twitter_image->full_image : false) );
}

/**
 * Get the image title/twitter message
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_image_title() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->title : ($__displayed_twitter_image ? $__displayed_twitter_image->title : false) );
}

/**
 * Get the date of the image (a UNIX timestamp, use date() to format)
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function the_twitter_image_date() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->date : ($__displayed_twitter_image ? $__displayed_twitter_image->date : false) );
}

/**
 * Get the associated Twitter account
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_image_author() {
    global $__displayed_twitter_image, $__current_twitter_image;
    return ( has_twitter_images() ? $__current_twitter_image->author : ($__displayed_twitter_image ? $__displayed_twitter_image->author : false) );
}

/**
 * Get HTML to display the image, with Lightbox support
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.1
 **/
function the_twitter_image() {
    return (the_twitter_full_image_url() && get_option('twitter_image_host_link_thumbnails') ? '<a href="'.the_twitter_full_image_url().'" rel="lightbox">' : '') .
            '<img src="'.the_twitter_image_url().'" class="aligncenter twitter_image" alt="'.the_twitter_image_title().'" title="'.the_twitter_image_title().'" />'.
           (the_twitter_full_image_url() && get_option('twitter_image_host_link_thumbnails') ? '</a>' : '');
}

/**
 * Search for Twitter images
 *
 * Available parameters (passed as associative array):
 *
 *      count                    Number of items to display
 *      id                       Single ID (eg 'abcde') of one image to display, or multiple IDs separated by commas (abcde,fghij)
 *      author                   Comma-separated list of Twitter account names to limit results to
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function query_twitter_images($options=array()) {
    global $__twitter_images;
    $__twitter_images = twitter_image_host_find_items($options);
    if ( count((array)$__twitter_images) == 0 ) {
        unset($__twitter_images);
        return false;
    }
    return true;
}

/**
 * Use with loop: Determine if there are more images
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function has_twitter_images() {
    return count((array)$GLOBALS['__twitter_images']) > 0;
}

/**
 * Use with loop: Get the next image
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function next_twitter_image() {
    global $__twitter_images, $__current_twitter_image;
    if ( count((array)$__twitter_images) > 0 ) {
        $__current_twitter_image = array_shift($__twitter_images);
        return $__current_twitter_image;
    }
    unset($__current_twitter_image);
    unset($__twitter_images);
    return false;
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
    $request = preg_replace("/\?.*/", "", $request);
    
    if ( preg_match('/^\/?twitter-image-host(?:\/(.*))?/', $request, &$matches) ) {
        // API call
        twitter_image_host_server($matches[1]);
        exit;
    }
    
    if ( !twitter_image_host_initialise_displayed_image(basename(preg_replace(array('/\.\.+/', '@/?(\?.*)?$@'), '', $_SERVER['REQUEST_URI']))) ) {
        if ( $_REQUEST['p'] ) {
            // Try using the post id
            twitter_image_host_initialise_displayed_image(base_convert($_REQUEST['p'], 10, 36));
        }
    }
    
    if ( strstr($_SERVER['REQUEST_URI'], 'wp-trackback.php') !== false ) {
        twitter_image_host_initialise_displayed_image_state();
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
    require_once('class.rsp.php');
    require_once('lib/twitteroauth.php');    
    
    global $current_user;
    get_currentuserinfo();
    $access_token = get_option('twitter_image_host_oauth_' . $current_user->user_login);
    
    if ( isset($_REQUEST['oauth_verifier']) ) {
        // Process login response from Twitter OAuth
        $connection = new TwitterOAuth(get_option('twitter_image_host_oauth_consumer_key'), 
                                       get_option('twitter_image_host_oauth_consumer_secret'), 
                                       get_option('twitter_image_host_oauth_token_' . $current_user->user_login),
                                       get_option('twitter_image_host_oauth_token_secret_' . $current_user->user_login));

        $access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);

        if ( empty($access_token) ) {
            delete_option('twitter_image_host_oauth_token_' . $current_user->user_login);
            delete_option('twitter_image_host_oauth_token_secret_' . $current_user->user_login);
            twitter_image_host_error(NOT_LOGGED_IN, __("Authentication error", "twitter-image-host"));
            return;
        }
        
        update_option('twitter_image_host_oauth_' . $current_user->user_login, $access_token);
        delete_option('twitter_image_host_oauth_token_' . $current_user->user_login);
        delete_option('twitter_image_host_oauth_token_secret_' . $current_user->user_login);
        
        $map = get_option('twitter_image_host_author_twitter_account_map');
        if ( !is_array($map) ) $map = array();
        update_option('twitter_image_host_author_twitter_account_map', array_merge($map, array($access_token['screen_name'] => $current_user->ID)));
        
        header('Location: ' . get_admin_url() . 'edit.php?page=twitter_image_host_posts');
        return;
    }

    foreach ( array("key", "message", "title") as $var ) {
        $_REQUEST[$var] = stripslashes($_REQUEST[$var]);
    }

    if ( !$command ) {
        // No command: Redirect to admin page
        header('Location: ' . get_admin_url() . 'edit.php?page=twitter_image_host_posts');
        return;
    }
    
    if ( $_REQUEST["key"] != get_option('twitter_image_host_access_key') ) {
        twitter_image_host_error(INVALID_REQUEST, __('Incorrect key', "twitter-image-host"));
        return;
    }
    
    // Sanity check
    if ( !in_array($command, array("upload", "uploadAndPost")) ) {
        twitter_image_host_error(INVALID_REQUEST, __('Invalid request', "twitter-image-host"));
        return;
    }
    if ( !isset($_FILES['media']) || !$_FILES['media']['tmp_name'] || !file_exists($_FILES['media']['tmp_name']) ) {
        twitter_image_host_error(IMAGE_NOT_FOUND, __('No image provided', "twitter-image-host"));
        return;
    }
    
    // Generate tag
    $extension = strtolower(substr($_FILES['media']['name'], strrpos($_FILES['media']['name'], '.')+1));
    do {
        $tag = strtolower(substr(str_replace("=","",base64_encode(rand())), -5));
    } while ( file_exists(IMAGE_HOST_FOLDER."/$tag.$extension") );
    
    // Look for duplicates
    $md5 = md5_file($_FILES['media']['tmp_name']);
    if ( ($dir=@opendir(IMAGE_HOST_FOLDER)) )
    while ( ($file=@readdir($dir)) ) {
        if ( preg_match('/^([a-z0-9]+)\.meta$/', $file, $matches) && ($info=file(IMAGE_HOST_FOLDER."/$file")) && trim($info[2]) == $md5 ) {
            $duplicate = $matches[1];
            break;
        }
    }
    if ( isset($duplicate) ) {
        $tag = $duplicate;
    } else {
    
        // Accept file
        if ( !file_exists(IMAGE_HOST_FOLDER) ) @mkdir(IMAGE_HOST_FOLDER, 0755);
        if ( !move_uploaded_file($_FILES['media']['tmp_name'], IMAGE_HOST_FOLDER."/$tag.$extension") ) {
            twitter_image_host_error(INTERNAL_ERROR, __("Couldn't save uploaded file", "twitter-image-host"));
            return;
        }
    
        list($width,$height) = @getimagesize(IMAGE_HOST_FOLDER."/$tag.$extension");
    
        if ( !$width ) {
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            twitter_image_host_error(INVALID_IMAGE, __("Invalid image", "twitter-image-host"));
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
    
        $title = ( $_REQUEST['title'] ? $_REQUEST['title'] : ( $_REQUEST['message'] ? $_REQUEST['message'] : $_FILES['media']['name'] ) );
    
        // Write metadata
        if ( ($fd = fopen(IMAGE_HOST_FOLDER."/$tag.meta", "w")) ) {
            fwrite($fd, $title."\n".($access_token ? $access_token['screen_name'] : $_REQUEST['username'])."\n".$md5);
            fclose($fd);
        }
    }
        
    // Generate URL
    $url = preg_replace('/\/$/', '', (get_option('twitter_image_host_override_url_prefix') ? get_option('twitter_image_host_override_url_prefix') : get_option('siteurl'))).'/'.$tag;
    
    // Post to twitter if asked to
    if ( isset($_REQUEST['from_admin']) && $_REQUEST['tweet'] ) {
        $status = ($_REQUEST['message'] ? $_REQUEST['message'].' '.$url : $url);
        if ( strlen($status) > 140 ) {
            $count = strlen($status)-140;
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            twitter_image_host_error(TWEET_TOO_LONG, sprintf(__("Tweet is too long by %d characters", "twitter-image-host"), $count));
            return;
        }
        
        if ( empty($access_token) ) {
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            twitter_image_host_error(NOT_LOGGED_IN, __("Not logged in to Twitter", "twitter-image-host"));
            return;
        }
        
        $connection = new TwitterOAuth(get_option('twitter_image_host_oauth_consumer_key'), get_option('twitter_image_host_oauth_consumer_secret'), $access_token['oauth_token'], $access_token['oauth_token_secret']);
        $response = $connection->post('statuses/update', array('status' => $status));
        
        if ( $connection->http_code != 200 || !$response->id ) {
            @unlink(IMAGE_HOST_FOLDER."/$tag.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag-full.$extension");
            @unlink(IMAGE_HOST_FOLDER."/$tag.meta");
            
            if ( $connection->http_code == 401 ) {
                delete_option('twitter_image_host_oauth_' . $current_user->user_login);
                twitter_image_host_error(NOT_LOGGED_IN, __('Twitter authentication error', 'twitter-image-host'));
            } else {
                twitter_image_host_error(TWITTER_OFFLINE, sprintf(__("Error posting to Twitter (%s)", "twitter-image-host"), $connection->http_code ? sprintf(__("response code %d", "twitter-image-host"), $connection->http_code) : __("couldn't connect or unexpected response", "twitter-image-host")));
            }
            
            return;
        }
        
        $userid = $response->user->id_str;
        $statusid = $response->id_str;
    }
    
    // Report success
    twitter_image_host_response($tag, $url, $userid, $statusid);
    return;
}

function twitter_image_host_error($code, $message) {
    if ( isset($_REQUEST['from_admin']) || isset($_REQUEST['oauth_verifier']) ) {
        header('Location: ' . get_admin_url() . 'edit.php?page=twitter_image_host_posts&error=' . urlencode($message));
        return;
    }
    RSP::error($code, $message);
}

function twitter_image_host_response($tag, $url, $userid=null, $statusid=null) {
    if ( isset($_REQUEST['from_admin']) || isset($_REQUEST['oauth_verifier']) ) {
        header('Location: ' . get_admin_url() . 'edit.php?page=twitter_image_host_posts&tag=' . urlencode($tag) . '&url=' . urlencode($url) . '&userid=' . urlencode($userid) . '&statusid=' . urlencode($statusid));
        return;
    }
    RSP::response($tag, $url, $userid, $statusid);
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
    $options = array_merge(twitter_image_host_widget_shortcode_defaults(), $allOptions[$id]);
    
    echo $args['before_widget'];
    if ( $options['title'] ) {
		echo $args['before_title'].htmlspecialchars($options['title']).$args['after_title'];
	}

    $items = twitter_image_host_find_items($options);
    twitter_image_host_render_items($items, $options);
    
    echo $args['after_widget'];
}


/**
 * Shortcode function
 *
 *  Available parameters:
 *      count                    Number of items to display
 *      id                       Single ID (eg 'abcde') of one image to display, or multiple IDs separated by commas (abcde,fghij)
 *      view                     Image thumbnail view: squares, proportional, large or custom
 *      custom_thumbnail_width   Custom width for thumbnails, when 'view' is 'custom'
 *      custom_thumbnail_height  Custom width for thumbnails, when 'view' is 'custom'
 *      custom_thumbnail_crop    Whether to crop custom thumbnails
 *      author                   Comma-separated list of Twitter account names to limit results to
 *      columns                  Number of columns of images to display 
 *      lightbox                 'true' to use Lightbox/Thickbox
 *
 * @param options Attributes from shortcode
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_images_shortcode($options) {
    $options = array_merge(twitter_image_host_widget_shortcode_defaults(), array_map('html_entity_decode', $options));
    ob_start();
    
    $items = twitter_image_host_find_items($options);
    twitter_image_host_render_items($items, $options);
    
    $contents = ob_get_contents();
    ob_end_clean();
    
    return $contents;
}



/**
 * PHP function
 *
 *  Available parameters:
 *      count                    Number of items to display
 *      id                       Single ID (eg 'abcde') of one image to display, or multiple IDs separated by commas (abcde,fghij)
 *      view                     Image thumbnail view: squares, proportional, large or custom
 *      custom_thumbnail_width   Custom width for thumbnails, when 'view' is 'custom'
 *      custom_thumbnail_height  Custom width for thumbnails, when 'view' is 'custom'
 *      custom_thumbnail_crop    Whether to crop custom thumbnails
 *      author                   Comma-separated list of Twitter account names to limit results to
 *      columns                  Number of columns of images to display 
 *      lightbox                 'true' to use Lightbox/Thickbox
 *
 * @param options Attributes from shortcode
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_images($options) {
    $options = array_merge(twitter_image_host_widget_shortcode_defaults(), $options);
    $items = twitter_image_host_find_items($options);
    twitter_image_host_render_items($items, $options);
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
        <p class="twitter_image_host_message"><?php _e("There are no items to display", "twitter-image-host") ?></p>
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
function twitter_image_host_initialise_displayed_image($name) {
    $name = strtolower($name);
    
    if ( !$name || !file_exists(IMAGE_HOST_FOLDER) || !($result=array_filter((array)glob(IMAGE_HOST_FOLDER."/$name.*"), create_function('$elt', 'return in_array(strtolower(substr($elt,-4)), array(".jpg", "jpeg", ".gif", ".png"));'))) ) {
        return false;
    }
    
    $name = basename(array_shift($result));
    $id   = substr($name, 0, strrpos($name,'.'));
    
    global $__displayed_twitter_image;
    $results = twitter_image_host_find_items(array('id' => $id));
    if ( count($results) == 0 ) return false;
    
    $__displayed_twitter_image = $results[0];
    
    return true;
}

/**
 * Load all posted images
 *
 * @return Array of objects, each representing an item
 * @param options Options associative array
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.5
 **/
function twitter_image_host_find_items($options) {

    $authors = (trim($options['author']) ? array_map('trim', explode(',', $options['author'])) : null);
    $ids = (trim($options['id']) ? array_map('trim', explode(',', $options['id'])) : null);
    
    if ( !($dir = @opendir(IMAGE_HOST_FOLDER)) ) return array();
    $files = array();
    while (($file = readdir($dir))) {
        if ( !preg_match('/^([a-z0-9]{5})\.(jpg|jpeg|png|gif)$/i', $file) ) continue;
        $files[] = $file;
    }
    
    usort($files, create_function('$a, $b', 'return filemtime(IMAGE_HOST_FOLDER."/$a") < filemtime(IMAGE_HOST_FOLDER."/$b");'));
    
    foreach ( $files as $file ) {
        preg_match('/^([a-z0-9]{5})\.(jpg|jpeg|png|gif)$/i', $file, $matches);
        $id = $matches[1];
        $extension = $matches[2];
        $title = $author = $full = $thumbnail = $view = null;
        
        if ( $ids && !in_array($id, $ids) ) continue; 
        
        if ( file_exists(IMAGE_HOST_FOLDER."/$id.meta") ) {
            list($title, $author) = file(IMAGE_HOST_FOLDER."/$id.meta");
        }
        
        $title = trim($title); $author = trim($author);

        if ( $authors && !in_array($author, $authors) ) continue; 

        if ( file_exists(IMAGE_HOST_FOLDER."/$id-full.$extension") ) {
            $full = "$id-full.$extension";
        }
        
        $view = $options['view'];
        $suffix = ($view == 'custom' 
                    ? 'custom-'.$options['custom_thumbnail_width'].'x'.$options['custom_thumbnail_height'].($options['custom_thumbnail_crop']=='true'?'-crop':'')
                    : $view);
        if ( file_exists(IMAGE_HOST_FOLDER."/$id-thumb-$suffix.$extension") ) {
            $thumbnail = "$id-thumb-$suffix.$extension";
        }
        
        if ( !$thumbnail ) {
            require_once(ABSPATH . 'wp-admin/includes/image.php' );
            $thumbnail = "$id-thumb-$suffix.$extension";
            switch ( $view ) {
                case 'proportional':
                    $new_file = image_resize(IMAGE_HOST_FOLDER."/".($full ? $full : $file), IMAGE_HOST_MAX_PROPORTIONAL_THUMB_WIDTH, IMAGE_HOST_MAX_PROPORTIONAL_THUMB_HEIGHT);
                    break;
                case 'custom':
                    $new_file = image_resize(IMAGE_HOST_FOLDER."/".($full ? $full : $file), $options['custom_thumbnail_width'], $options['custom_thumbnail_height'], $options['custom_thumbnail_crop']=='true');
                    break;
                case 'squares':
                    $new_file = image_resize(IMAGE_HOST_FOLDER."/".($full ? $full : $file), IMAGE_HOST_SQUARE_THUMB_WIDTH, IMAGE_HOST_SQUARE_THUMB_HEIGHT, true);
                    break;
                case 'large':
                default:
                    break;
            }
            
            if ( !$new_file || is_wp_error($new_file) || !file_exists($new_file) )
                $thumbnail = $file;
            else
                rename($new_file, IMAGE_HOST_FOLDER."/$thumbnail");
        }
        
        if ( !$thumbnail ) $thumbnail = $file;
        
        $baseurl = trailingslashit(get_option('siteurl'));
        
        $item = new StdClass;
        $item->id             = $id;
        $item->page           = $baseurl.$id;
        $item->full_image     = ($full ? trailingslashit(IMAGE_HOST_URL).$full : false);
        $item->image          = trailingslashit(IMAGE_HOST_URL).($full ? $full : $file);
        $item->thumbnail      = trailingslashit(IMAGE_HOST_URL).$thumbnail;
        $item->title          = trim($title);
        $item->author         = trim($author);
        $item->date           = filemtime(IMAGE_HOST_FOLDER."/$file");
        $item->numeric_id     = base_convert($id, 36, 10);
        $items[] = $item;
        
        if ( $options['count'] && count($items) == $options['count'] ) break;
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
    
    if ( !isset($GLOBALS['__displayed_twitter_image']) ) return;
    twitter_image_host_initialise_displayed_image_state();
    
    if ( is_feed() || is_trackback() ) return;

    $template = locate_template(array('twitter-image-host.php'));
    if ( !$template ) {
        // Fall back to single template
        $template = get_single_template();
    }
    if ( !$template && file_exists(TEMPLATEPATH . "/index.php") ) {
        // Fall back to index
		$template = TEMPLATEPATH . "/index.php";
    }
    
    if ( !$template ) return;

    global $wp_query, $post, $posts, $comments;    
    include($template);
    
    exit;
}

/**
 * Method to set up state correctly to display image
 */
function twitter_image_host_initialise_displayed_image_state() {
    global $post, $wp_query, $posts, $comments, $authordata, $__displayed_twitter_image;
    
    $map = get_option('twitter_image_host_author_twitter_account_map');
    if ( is_array($map) ) {
        $author = $map[$__displayed_twitter_image->author];
        if ( $author ) {
            $authordata = get_userdata($author);
        }
    }
    if ( !$author ) $author = 0;
    
    // Prepare a pseudo post
    $post = new StdClass;
    $post->ID = $__displayed_twitter_image->numeric_id;
    $post->post_author = $author;
    $post->post_date = date( 'Y-m-d H:i:s', $__displayed_twitter_image->date );
    $post->post_content = the_twitter_image();
    $post->post_title = $__displayed_twitter_image->title;
    $post->guid = $__displayed_twitter_image->page;
    $post->post_status = 'publish';
    $post->post_date_gmt = $__displayed_twitter_image->date - (get_option('gmt_offset') * 3600);
    $post->comment_status = 'closed';
    $post->ping_status = 'closed';
    wp_cache_add($post->ID, $post, 'posts');
    $posts = array($post);
    $__displayed_twitter_image->post = &$post;
    
    $wp_query->queried_object = $post;
    $wp_query->post_count = 1;
    $wp_query->posts[0] = $post;
    $wp_query->is_404 = false;
    $wp_query->is_page = false;
    
    $wp_query->is_single = true;
}

/**
 * Add image_src to header
 */
function twitter_image_host_head() {
    global $__displayed_twitter_image;
    if ( $__displayed_twitter_image ) {
        ?><link rel="image_src" href="<?php echo the_twitter_image_url(); ?>" /><?php
    }
}

// ==================================
// =   Filters to make it all work  =
// ==================================

function twitter_image_host_posts_filter($posts, $query) {
    global $__displayed_twitter_image;
    if ( !isset($__displayed_twitter_image) || $query->query_vars['pagename'] != $__displayed_twitter_image->id ) return $posts;
    
    // Inject pretend post
    $array = array_merge((array)$posts, array($__displayed_twitter_image->post));
    usort($array, create_function('$a, $b', 'return strtotime($a->post_date) < strtotime($b->post_date);'));
    return $array;
}

function twitter_image_host_post_link($permalink, $post) {
    global $__displayed_twitter_image;
    if ( !isset($__displayed_twitter_image) || (is_object($post) ? $post->ID : $post) != $__displayed_twitter_image->numeric_id ) return $permalink;
    return $__displayed_twitter_image->page;
}

function twitter_image_host_edit_post_link_filter($link, $post) {
    global $__displayed_twitter_image;
    if ( !isset($__displayed_twitter_image) || (is_object($post) ? $post->ID : $post) != $__displayed_twitter_image->numeric_id ) return $link;
    return '';
}

function twitter_image_host_author_link_filter($link, $authorid, $author_nicename) {
    global $__displayed_twitter_image;
    if ( !isset($__displayed_twitter_image) ) return $link;
    return 'http://twitter.com/'.$__displayed_twitter_image->author;
}

function twitter_image_host_the_author_filter($author) {
    global $__displayed_twitter_image, $authordata;
    if ( !isset($__displayed_twitter_image) || $authordata ) return $author;
    return $__displayed_twitter_image->author;
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
    load_plugin_textdomain( 'twitter-image-host', false, 'twitter-image-host/languages' );
        
    // Add stylesheet
    wp_register_style('twitter-image-host-css', WP_PLUGIN_URL.'/twitter-image-host/style.css');
    wp_enqueue_style('twitter-image-host-css');
    
    $options = get_option('twitter_image_host_widget');
    if ( !$options ) $options = array();
    
    $widget_opts  = array('classname' => 'twitter_image_host_widget', 'description' => __('Display images posted to Twitter', 'twitter-image-host'));
    $control_opts = array('id_base' => 'twitter_image_host_widget');
    $name         = __('Twitter Images', 'twitter-image-host');
    
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
function twitter_image_host_widget_shortcode_defaults() {
    return array(
        'title'                   =>  __('Twitter Images', 'twitter-image-host'),
        'count'                   => 10,
        'columns'                 => '',
        'view'                    => 'squares',
        'custom_thumbnail_width'  => 75,
        'custom_thumbnail_height' => 75,
        'custom_thumbnail_crop'   => true,
        'author'                  => '',
        'lightbox'                => false
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
		
		$fields = array_keys(twitter_image_host_widget_shortcode_defaults());
		
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
        $options = twitter_image_host_widget_shortcode_defaults(); 
        $id = '%i%';
    } else {
        $options = $options[$id];
    }
    
    $options = array_merge(twitter_image_host_widget_shortcode_defaults(), $options);
    
    $title       = htmlspecialchars($options['title']);
    $author      = htmlspecialchars($options['author']);
    $view        = $options['view'];
    $count       = (int)$options['count'];
    $columns     = $options['columns'];
    $lightbox    = $options['lightbox'];
    $custom_thumbnail_width     = $options['custom_thumbnail_width'];
    $custom_thumbnail_height    = $options['custom_thumbnail_height'];
    $custom_thumbnail_crop = $options['custom_thumbnail_crop'];
    
    ?>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_title"><?php _e('Title:', 'twitter-image-host') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_title" name="twitter_image_host_widget[<?php echo $id ?>][title]" value="<?php echo $title ?>" />
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_title"><?php _e('Limit to images from Twitter account(s):', 'twitter-image-host') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_author" name="twitter_image_host_widget[<?php echo $id ?>][author]" value="<?php echo $author ?>" /><br/>
        <small><?php _e("One or more Twitter accounts separated by commas.  Leave blank to show images from all Twitter accounts.", "twitter-image-host") ?></small>
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_count"><?php _e('Number of items to show:', 'twitter-image-host') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_count" name="twitter_image_host_widget[<?php echo $id ?>][count]" size="4" value="<?php echo $count ?>" />
    </p>
    <p>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_squares" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="squares" <?php echo ($view=='squares'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_squares"><?php _e('View as square thumbnails', 'twitter-image-host') ?></label><br/>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_proportional" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="proportional" <?php echo ($view=='proportional'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_proportional"><?php _e('View as proportional thumbnails', 'twitter-image-host') ?></label><br/>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_large" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="large" <?php echo ($view=='large'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_large"><?php _e('View as large thumbnails', 'twitter-image-host') ?></label><br/>
        <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_view_custom" name="twitter_image_host_widget[<?php echo $id ?>][view]" value="custom" <?php echo ($view=='custom'?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_view_custom"><?php _e('View as custom-sized thumbnails:', 'twitter-image-host') ?></label><br/>
        <blockquote style="border-left: 1px dotted #aaa; padding-left:8px;">
            <small><b><?php _e("Custom settings:", "twitter-image-host") ?></b></small><br/>
            <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_width" name="twitter_image_host_widget[<?php echo $id ?>][custom_thumbnail_width]" size="5" value="<?php echo $custom_thumbnail_width ?>" />
            <label for="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_width"><?php _e('Width', 'twitter-image-host') ?></label><br/>
            <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_height" name="twitter_image_host_widget[<?php echo $id ?>][custom_thumbnail_height]" size="5" value="<?php echo $custom_thumbnail_height ?>" />
            <label for="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_height"><?php _e('Height', 'twitter-image-host') ?></label><br/>
            <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_crop_yes" name="twitter_image_host_widget[<?php echo $id ?>][custom_thumbnail_crop]" value="true" <?php echo ($custom_thumbnail_crop=='true'?'checked':'') ?> />
            <label for="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_crop_yes"><?php _e('Crop to exact size', 'twitter-image-host') ?></label><br/>
            <input type="radio" id="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_crop_no" name="twitter_image_host_widget[<?php echo $id ?>][custom_thumbnail_crop]" value="false" <?php echo ($custom_thumbnail_crop!='true'?'checked':'') ?> />
            <label for="twitter_image_host_widget_<?php echo $id ?>_custom_thumbnail_crop_no"><?php _e('Fit within dimensions', 'twitter-image-host') ?></label>
        </blockquote>
    </p>
    <p>
        <label for="twitter_image_host_widget_<?php echo $id ?>_columns"><?php _e('Number of columns to display:', 'twitter-image-host') ?></label>
        <input type="text" id="twitter_image_host_widget_<?php echo $id ?>_columns" name="twitter_image_host_widget[<?php echo $id ?>][columns]" size="4" value="<?php echo $columns ?>" /><br/>
        <small><?php _e("Leave blank to not separate items into columns", "twitter-image-host") ?></small>
    </p>
    <p>
        <input type="checkbox" id="twitter_image_host_widget_<?php echo $id ?>_lightbox" name="twitter_image_host_widget[<?php echo $id ?>][lightbox]" <?php echo ($lightbox?'checked':'') ?> />
        <label for="twitter_image_host_widget_<?php echo $id ?>_lightbox"><?php _e('Use Lightbox, etc.', 'twitter-image-host') ?></label><br/>
        <small><?php _e("You must have Lightbox/Thickbox/etc installed for this to work", "twitter-image-host") ?></small>
    </p>
    
    <?php
}



// =======================
// =        Admin        =
// =======================

/** 
 * Initialisation
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.6
 */
function twitter_image_host_admin_init() {
    if ( !get_option('twitter_image_host_access_key') ) {
        update_option('twitter_image_host_access_key', strtolower(substr(str_replace("=","",base64_encode(rand())), -5)));
    }
    
    
    wp_enqueue_script('twitter-image-host-form', WP_PLUGIN_URL.'/twitter-image-host/form.js', 'jquery');
}

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
	<h2><?php _e("Twitter Image Host", "twitter-image-host") ?></h2>
	
	<div style="margin: 30px; border: 1px solid #ccc; padding: 20px; width: 400px;">
	    <p><?php _e("The API access point for your Twitter Image Host installation (for use with Twitter for iOS, etc) is:", "twitter-image-host") ?></p>
	    <p><strong><?php bloginfo('url') ?>/twitter-image-host/upload?key=<?php echo get_option('twitter_image_host_access_key'); ?></strong></p>
    </div>
	
	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
	
	<table class="form-table">
    	
        <tr valign="top">
            <th scope="row"><?php _e('Twitter API keys:', 'twitter-image-host')?></th>
            <td>
                <?php if ( !get_option('twitter_image_host_oauth_consumer_key') ) : ?>
                <table><tr><td>
                <?php endif; ?>
                
                <?php _e('OAuth Consumer Key:', 'twitter-image-host') ?><br />
                <input type="text" id="twitter_image_host_oauth_consumer_key" name="twitter_image_host_oauth_consumer_key" value="<?php echo get_option('twitter_image_host_oauth_consumer_key') ?>" /><br />
                <?php _e('OAuth Consumer Secret:', 'twitter-image-host') ?><br />
                <input type="text" id="twitter_image_host_oauth_consumer_secret" name="twitter_image_host_oauth_consumer_secret" value="<?php echo get_option('twitter_image_host_oauth_consumer_secret') ?>" /><br />
                </td>
                
                <?php if ( !get_option('twitter_image_host_oauth_consumer_key') ) : ?>
                </td><td>
                <?php echo sprintf(__('You can register for these at %s.', 'twitter-image-host'), '<a href="https://dev.twitter.com/apps/new">https://dev.twitter.com/apps/new</a>') ?>
                    <ul>
                        <li>Application Type: <b>Browser</b></li>
                        <li>Callback URL: <b><?php echo bloginfo('url').'/twitter-image-host'?></b></li>
                        <li>Default Access type: <b>Read &amp; Write</b>
                        <li>Tick "Yes, use Twitter for Login"</li>
                    </ul>
                </td></tr></table>
                <?php endif; ?>
        </tr>
    	
    	<tr valign="top">
    		<th scope="row"><?php _e('Image dimensions:', 'twitter-image-host') ?></th>
    		<td>
    		    <?php _e("Maximum width", "twitter-image-host") ?><br/>
    			<input type="text" name="twitter_image_host_max_width" value="<?php echo get_option('twitter_image_host_max_width', 500) ?>" /><br/>
    			<?php _e("Maximum height", "twitter-image-host") ?><br/>
    			<input type="text" name="twitter_image_host_max_height" value="<?php echo get_option('twitter_image_host_max_height', 500) ?>" /><br/>
    			<input type="checkbox" name="twitter_image_host_link_thumbnails" <?php if ( get_option('twitter_image_host_link_thumbnails') ) echo "checked" ?>> <?php _e("Link to full-size images", "twitter-image-host") ?>
    		</td>
    	</tr>
    	
    	<tr valign="top">
    		<th scope="row"><?php _e('Override URL prefix:', 'twitter-image-host') ?></th>
    		<td>
    			<input type="text" name="twitter_image_host_override_url_prefix" value="<?php echo get_option('twitter_image_host_override_url_prefix') ?>" /><br/>
                <small><?php _e("If you have your own .htaccess rewrite rules in place, override the short URL prefix here (Advanced)", "twitter-image-host") ?></small>
    		</td>
    	</tr>
	
	</table>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="twitter_image_host_oauth_consumer_key, twitter_image_host_oauth_consumer_secret, twitter_image_host_max_width, twitter_image_host_max_height, twitter_image_host_link_thumbnails, twitter_image_host_override_url_prefix" />
	
	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Save Changes', 'twitter-image-host') ?>" />
	</p>
	
	</form>
	</div>
	<?php
}


/**
 * Posts page
 *
 * @author Michael Tyson
 * @package Twitter Image Host
 * @since 0.6
 **/
function twitter_image_host_posts_page() {
    global $current_user;
    get_currentuserinfo();
    
    if ( isset($_REQUEST['login'] ) ) {
        // Perform OAuth login
        if ( !get_option('twitter_image_host_oauth_consumer_key') ) {
            // Not setup
            echo sprintf(__('Not set up. Please %sConfigure Twitter Image Host%s.', 'twitter-image-host'), '<a href="'.get_admin_url().'options-general.php?page=twitter_image_host_options">', '</a>');
            return;
        }
        
        require_once('lib/twitteroauth.php');

        // Redirect to Twitter for login
        $connection = new TwitterOAuth(get_option('twitter_image_host_oauth_consumer_key'), get_option('twitter_image_host_oauth_consumer_secret'));
        $request_token = $connection->getRequestToken();
        
        update_option('twitter_image_host_oauth_token_' . $current_user->user_login, $request_token['oauth_token']);
        update_option('twitter_image_host_oauth_token_secret_' . $current_user->user_login, $request_token['oauth_token_secret']);
        
        if ( $connection->http_code == 200 ) {
            $url = $connection->getAuthorizeURL($request_token['oauth_token']);
            ?>
            <script type="text/javascript">
                document.location = "<?php echo $url ?>";
            </script>
            <p>Click <a href="<?php echo $url ?>">here</a> if you are not redirected within a few seconds.</p>
            <?php
        } else {
            echo sprintf(__('Could not connect to Twitter. Refresh the page or try again later. (Error code %d)', 'twitter-image-host'), $connection->http_code);
        }
        return;
    } else if ( isset($_REQUEST['logout']) ) {
        delete_option('twitter_image_host_oauth_' . $current_user->user_login);
    }
    
    $access_token = get_option('twitter_image_host_oauth_' . $current_user->user_login);

    ?>
    <style type="text/css" media="screen">
        .form {
            width: 300px;
            margin: 0 auto;
            margin-top: 50px;
        }
        
        .form input.text {
            width: 100%;
        }
        
        .form .button {
            display: block;
            width: 100px;
            margin: 0 auto;
            margin-top: 50px;
        }
        
        #character-count {
        	float: right;
        	font-size: 1.7em;
        	position: relative;
        	top: -21px;
        	right: -55px;
        	color: #cbcbcb;
        }
        
        #character-count.illegal {
            color: #B96B6B;
        }
    </style>
    
    <div class="wrap">
    <h2><?php _e("Twitter Images", "twitter-image-host") ?></h2>

    <?php if ( $_REQUEST['url'] ) : ?>
        <p>
        <?php if ( $_REQUEST['statusid'] )
            echo sprintf(__("Your image has been uploaded and the %stweet%s has been posted.", "twitter-image-host"), "<a href=\"http://twitter.com/".$access_token['screen_name']."/status/".$_REQUEST['statusid']."\">", "</a>");
        else
            _e("Your image has been uploaded.", "twitter-image-host");
        ?>
        <?php echo sprintf(__("The URL is %s", "twitter-image-host"), '<a href="'. $_REQUEST['url']. '">'. $_REQUEST['url']. '</a>'); ?>
        </p>
        
        <p><?php _e("Upload another:", "twitter-image-host") ?></p>
    <?php elseif ( $_REQUEST['error'] ) : ?>
        <div class="error"><?php _e("There was an error:", "twitter-image-host") ?> <?php echo $_REQUEST['error']; ?></div>
        <p><?php _e("Try again:", "twitter-image-host") ?></p>
    <?php endif; ?>
    
    <div class="form-wrap" style="width: 400px;">
    <h3><?php _e("Upload New Image", "twitter-image-host") ?></h3>
    <form method="post" enctype="multipart/form-data" action="<?php echo trailingslashit(get_option('siteurl')) ?>twitter-image-host/upload">
        <div class="form-field">
        	<label for="title"><?php _e("Title", "twitter-image-host") ?></label> 
        	<input name="title" id="title" type="text" value="<?php echo $_REQUEST['title'] ?>" size="40" /> 
        	<p><?php _e("If tweeting too, leave blank to be the same as tweet message below.", "twitter-image-host") ?></p> 
        </div>
        
        <div class="form-field">
        	<label for="media"><?php _e("Image", "twitter-image-host") ?></label> 
            <input type="file" id="media" name="media" />
        </div>

        <div class="form-field">
            <?php 
            $post_available = (get_option('twitter_image_host_oauth_consumer_key') && !empty($access_token));
            $available_characters = (140 - strlen(" ".(get_option('twitter_image_host_override_url_prefix') ? get_option('twitter_image_host_override_url_prefix') : get_option('siteurl')).'/12345')); 
            ?>
            <input type="checkbox" style="width: auto; float: left; margin-right: 10px;" name="tweet" <?php if ( !$post_available ) echo 'disabled' ?> <?php echo ($_REQUEST['tweet'] ? 'checked="checked"' : '') ?> /> <?php _e("Post to Twitter too, with optional message:", "twitter-image-host") ?><br/>
            <input type="text" name="message" <?php if ( !$post_available ) echo 'disabled' ?> value="<?php echo $_REQUEST['message'] ?>" id="tweet" /><span id="character-count"><?php echo $available_characters - (isset($_REQUEST['message']) ? strlen($_REQUEST['message']) : 0) ?></span>
            <script type="text/javascript" charset="utf-8">
              var available_characters = <?php echo $available_characters ?>;
            </script>
            <?php if ( !$post_available ) : ?>
                <?php if ( !get_option('twitter_image_host_oauth_consumer_key') ) : ?>
                    <p><i><?php echo sprintf(__("%sConfigure Twitter Image Host%s, then log into Twitter to enable this feature.", "twitter-image-host"), '<a href="'. get_admin_url(). 'options-general.php?page=twitter_image_host_options">', '</a>') ?></i></p>
                <?php else: ?>
                    <p><i><?php echo sprintf(__("%sLog in to Twitter%s to enable this feature.", "twitter-image-host"), '<a href="'. get_admin_url(). 'edit.php?page=twitter_image_host_posts&amp;login">', '</a>') ?></i></p>
                <?php endif; ?>
            <?php else: ?>
                <p><i><?php echo sprintf(__("Logged in as %s. %sLogout%s", "twitter-image-host"), $access_token['screen_name'], '<a href="'. get_admin_url(). 'edit.php?page=twitter_image_host_posts&amp;logout">', '</a>') ?></i></p>
            <?php endif; ?>
        </div>
        <input type="hidden" name="key" value="<?php echo get_option('twitter_image_host_access_key') ?>" />
        <input type="hidden" name="from_admin" value="true" />
        <input type="submit" class="button" value="<?php _e("Post", "twitter-image-host") ?>" />
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
function twitter_image_host_initialise_displayed_image_admin() {
	add_options_page( __('Twitter Image Host', 'twitter-image-host'), __('Twitter Image Host', 'twitter-image-host'), 5, 'twitter_image_host_options', 'twitter_image_host_options_page' );
	add_posts_page( __('Twitter Images', 'twitter-image-host'), __('Twitter Images', 'twitter-image-host'), 5, 'twitter_image_host_posts', 'twitter_image_host_posts_page' );
}

add_action( 'init', 'twitter_image_host_init' );

add_action( 'plugins_loaded', 'twitter_image_host_run' );
add_action( 'template_redirect', 'twitter_image_host_template_redirect' );
add_action( 'admin_menu', 'twitter_image_host_initialise_displayed_image_admin' );
add_action( 'admin_init', 'twitter_image_host_admin_init' );
add_action( 'wp_head', 'twitter_image_host_head' );

add_filter( 'the_posts', 'twitter_image_host_posts_filter', 10, 2 );
add_filter( 'page_link', 'twitter_image_host_post_link', 10, 2);
add_filter( 'post_link', 'twitter_image_host_post_link', 10, 2);
add_filter( 'edit_post_link', 'twitter_image_host_edit_post_link_filter', 10, 2 );
add_filter( 'author_link', 'twitter_image_host_author_link_filter', 10, 3 );
add_filter( 'the_author', 'twitter_image_host_the_author_filter' );

add_shortcode('twitter-images', 'twitter_image_host_images_shortcode');
