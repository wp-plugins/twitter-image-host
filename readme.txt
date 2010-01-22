=== Twitter Image Host ===

Donate link: http://atastypixel.com/blog/wordpress/plugins/twitter-image-host
Tags: images,twitter,hosting
Requires at least: 2.6
Tested up to: 2.9
Stable tag: 0.4.4

Host Twitter images from your blog and keep your traffic, rather than using a service like Twitpic and losing your viewers.

== Description ==

Keep your traffic in the family!  Host Twitter images on your own site, with support for comments and trackbacks, image
resizing and thumbnailing with Lightbox.

Twitter doesn’t yet come with its own inline image support, so we tend to be limited to using image hosting services, 
and linking to them with short URLs. So, services like Tweetpic host the image, and we direct traffic to them in return.

Better to take advantage of that traffic, and host images on your own site.  This way, viewers come to your site, instead
of someone else's!

Posted images are displayed in your normal Wordpress template, with support for comments and trackbacks, without any 
setup required.  Most themes should work with this, but if not, or if a different layout is required, a custom theme template 
can also be provided (see 'Creating a Template').

Provides an HTML form for posting image content, as well as an API modelled on that of [img.ly](http://img.ly/pages/API),
compatible with Tweetie (for iPhone) and any other Twitter clients that speak this protocol and offer configuration of
custom image hosting services.

Uses Twitter's authentication and a list of authorised accounts, so you can let others use your image host too.  You can even 
post status updates to Twitter while submitting images.

Provides a widget and shortcode to display uploaded images.  This supports filtering by Twitter account, styling with CSS,
and Lightbox/Thickbox.

Mac users, grab the Automator service from the [plugin's homepage](http://atastypixel.com/blog/wordpress/plugins/twitter-image-host) to
be able to upload via a context menu item in the Finder!

== Installation ==

1. Unzip the package, and upload `twitter-image-host` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit the settings page and add your Twitter account to the list of authorised accounts
4. Start submitting images - See the 'Posting Images' section for more

== Widget ==

To use the widget, simply visit the Widgets page and drag the "Twitter Images" widget into a sidebar and configure it.

== Shortcode ==

Shortcodes are snippets of text that can be inserted into pages and posts.  These snippets are replaced by various generated content.
Twitter Image Host provides a 'twitter-images' shortcode to display images you have uploaded within a page/post.

Available parameters:

      count                    Number of items to display
      id                       Single ID (eg 'abcde') of one image to display, or multiple IDs separated by commas (abcde,fghij)
      view                     Image thumbnail view: squares, proportional, large or custom
      custom_thumbnail_width   Custom width for thumbnails, when 'view' is 'custom'
      custom_thumbnail_height  Custom width for thumbnails, when 'view' is 'custom'
      custom_thumbnail_crop    Whether to crop custom thumbnails
      author                   Comma-separated list of Twitter account names to limit results to
      columns                  Number of columns of images to display 
      lightbox                 'true' to use Lightbox/Thickbox

Example:

      [twitter-images columns=4 lightbox="true"]
  


== Creating a Template ==

By default, this plugin will use the standard post template ('single.php').  However, if you wish, you can create a 
custom template to display hosted images.  The template should be called 'twitter-image-host.php', located within your
current theme directory.

There are five template tags available:

*`the_twitter_image_url`*

Contains the full URL to the image, or the image thumbnail if the original image was large

*`the_twitter_full_image_url`*

Contains the full URL to the full-sized image, if there is one

*`the_twitter_image`*

Returns HTML to display the image and a link to the full-sized image if it exists, with Lightbox rel tags.

*`the_twitter_image_title`*

The title of the image

*`the_twitter_image_date`*

The date (timestamp) of the image

*`the_twitter_image_author`*

The associated Twitter account

Creating a template to use this information is fairly straightforward if you have just a little knowledge of HTML or PHP:

 1. On your server (via an FTP program, etc.), navigate to your current theme.  This will live within `wp-content/themes`.
 2. Copy an existing template - `single.php` is usually a good candidate - and call it `twitter-image-host.php`.
 3. Open up `twitter_image_host.php`, and delete everything that looks post-related: This usually includes everything between
    the `have_posts` call and the matching `endif`, and may include some other surrounding content like an 'Edit this post' link.
 4. Replace that which you have just deleted with something like the following:

        <?php echo the_twitter_image() ?>
        <h1 class="center"><?php echo the_twitter_image_title() ?></h1>
        <p class="center">From <a href="http://twitter.com/<?php echo the_twitter_image_author() ?>"> <?php echo the_twitter_image_author() ?></a></p>

 5. Save the file, add some content (see the 'Posting Images' section), and see how it looks.

== Posting Images ==

To start posting straight away, a simple form is provided at http://your-blog-url/twitter-image-host.
Enter a title for your image, your Twitter account details, and select your image.  Hit Submit, and you will be given the URL for the image.

To access this facility from an application, use the access point http://your-blog-url/twitter-image-host.

The API is the same as that of [img.ly](http://img.ly/pages/API).

To post from Tweetie 2 for iPhone, visit Tweetie's settings, and within *Services, Image Service*, select 'Custom', then
enter http://your-blog.com/twitter-image-host/upload

For Mac users, an Automator service has been created to upload images by right-clicking on a file in Finder, then selecting
a context menu item.  This service can be downloaded from the [plugin's homepage](http://atastypixel.com/blog/wordpress/plugins/twitter-image-host).

== Making the URL even shorter ==

If you run Wordpress from a sub-directory (for example, http://your-site.com/blog), then the short URLs generated by this plugin will
look like http://your-site/blog/xxxxx.  You can remove that 'blog' component via a little `.htaccess` trickery.

Here's how:

1. Create and open a new file in your site's webroot called ".htaccess". If there's one already there, just open that up and prepare to edit at the bottom.
2. Add the following, replacing 'blog' with the real subdirectory under which Wordpress is installed:
        <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
        RewriteCond %{REQUEST_URI} ^/([^/]+)/?$
        RewriteCond %{DOCUMENT_ROOT}/blog/wp-content/twitter-image-host-content/%1.jpg -f [OR]
        RewriteCond %{DOCUMENT_ROOT}/blog/wp-content/twitter-image-host-content/%1.png -f [OR]
        RewriteCond %{DOCUMENT_ROOT}/blog/wp-content/twitter-image-host-content/%1.jpeg -f
        RewriteRule (.*) /blog/$1 [L]
        </IfModule>

  This will take any requests that:
  * Are located in the web-root (start with a slash, followed by anything but a slash until the end)
  * Have a corresponding file within Twitter Image Host's content directory
  Then, it'll rewrite the request silently to the real Twitter Image Host URL, without the viewer seeing.
3. In Twitter Image Host settings, set the 'Override URL Prefix' option to 'http://your-site.com/'

== Changelog ==

= 0.5 =
 * Implemented a widget and shortcode to display uploaded images (see documentation)

= 0.4.4 =
 * Fix to HTML submission form for WP installations within a sub-directory
 * If a tweet is too long, report by how many characters

= 0.4.3 =
 * Minor tweak to suppress missing argument warnings

= 0.4.2 =
 * Improved Twitter error reporting
 * Fixed bug causing incorrect API response
 
= 0.4.1 =
 * Bugfix in URL creation
 
= 0.4 =
 * Improved support for running out of a sub-directory
 
= 0.3 =
 * Fixed bug that interferes with some other plugins
 
= 0.2 =
 * Proper support for trackbacks, comment feeds, fixed a bug which caused plugin to say Twitter was unavailable when the Twitter account has no direct messages
 
= 0.1 =
 * Initial release
 
== Upgrade Notice ==

= 0.4.4 =
This update fixes the HTML image submission form for WP installations within a sub-directory

= 0.4.3 =
If you are getting 'missing argument' warnings, install this update.

= 0.4.2 =
This update fixes a bug that stopped Tweetie 2 for iPhone working with this plugin.