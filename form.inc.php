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
  <form method="post" enctype="multipart/form-data" action="/twitter-image-host/upload">
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
          <input type="checkbox" name="tweet" <?php echo ($_REQUEST['tweet'] ? 'checked="checked"' : '') ?> /> Post to Twitter too, with optional message:<br/>
          <input type="text" name="message" value="<?php echo $_REQUEST['message'] ?>" class="text" />
      </p>
      <input type="hidden" name="from_form" value="true" />
      <input type="submit" class="button" value="Post" />
  </form>
  </div>
</body>
</html>