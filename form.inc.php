<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Twitter Image Host Content Submission</title>
    <?php
    	wp_admin_css('install', true);
    	if ( ($wp_locale) && ('rtl' == $wp_locale->text_direction) ) {
    		wp_admin_css('login-rtl', true);
    	}
    	do_action('admin_head');
    ?>
    
    <script src="<?php echo site_url( '/wp-includes/js/jquery/jquery.js' )?>" type="text/javascript" charset="utf-8"></script>
    <script src="<?php echo WP_PLUGIN_URL?>/twitter-image-host/form.js" type="text/javascript" charset="utf-8"></script>
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
</head>
<body>
  <h1>Twitter Image Host Content Submission</h1>
  <?php if ( $url ) : ?>
      <p>Your image has been uploaded. The URL is <a href="<?php echo $url; ?>"><?php echo $url; ?></a><?php if ( $statusid ) : ?>,
          and the posted tweet is <a href="http://twitter.com/<?php echo $_REQUEST['username'] ?>/status/<?php echo $statusid ?>">here</a>
      <?php endif; ?></p>
      <p>Upload another:</p>
  <?php elseif ( $error ) : ?>
      <p>There was an error posting the image: <?php echo $error; ?></p>
      <p>Try again:</p>
  <?php endif; ?>
  <div class="form">
  <form method="post" enctype="multipart/form-data" action="<?php echo trailingslashit(get_option('siteurl')) ?>twitter-image-host/upload">
      <p>
          Submission title:<br/>
          <input type="text" name="title" value="<?php echo $_REQUEST['title'] ?>" class="text" /><br/>
          <small>If tweeting too, leave blank to be the same as tweet message below</small>
      </p>
      <p>
          Twitter account name:<br/>
          <input type="text" name="username" value="<?php echo $_REQUEST['username'] ?>" class="text" />
      </p>
      <p>
          Twitter account password:<br/>
          <input type="password" name="password" value="<?php echo $_REQUEST['password'] ?>" class="text" />
      </p>      
      <p>
          Image:<br/>
          <input type="file" name="media" />
      </p>
      <p>
          <?php $available_characters = (140 - strlen(" ".(get_option('twitter_image_host_override_url_prefix') ? get_option('twitter_image_host_override_url_prefix') : get_option('siteurl')).'/12345')); ?>
          <input type="checkbox" name="tweet" <?php echo ($_REQUEST['tweet'] ? 'checked="checked"' : '') ?> /> Post to Twitter too, with optional message:<br/>
          <input type="text" name="message" value="<?php echo $_REQUEST['message'] ?>" class="text" id="tweet" /><span id="character-count"><?php echo $available_characters - (isset($_REQUEST['message']) ? strlen($_REQUEST['message']) : 0) ?></span>
          <script type="text/javascript" charset="utf-8">
            var available_characters = <?php echo $available_characters ?>;
          </script>
      </p>
      <input type="hidden" name="from_form" value="true" />
      <input type="submit" class="button" value="Post" />
  </form>
  </div>
</body>
</html>