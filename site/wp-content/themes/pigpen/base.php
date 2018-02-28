<?php

use Roots\Sage\Setup;
use Roots\Sage\Wrapper;

?>

  <!doctype html>
  <html <?php language_attributes(); ?>>
  <?php get_template_part('templates/head'); ?>

  <body <?php body_class(); ?>>
    <!--[if IE]>
      <div class="alert alert-warning">
        <?php _e('You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.', 'sage'); ?>
      </div>
    <![endif]-->
    <?php
      do_action('get_header');
      get_template_part('templates/header');
    ?>
      <div class="content" role="document">
        <main class="main">
          <?php include Wrapper\template_path(); ?>
        </main>
      </div>
      <?php
      do_action('get_footer');
      get_template_part('templates/footer');
      wp_footer();
    ?>
        <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.5.18/webfont.js"></script>
        <script>
          WebFont.load({
            google: {
              families: ['PT+Serif:400,700']
            }
          });

        </script>
  </body>

  </html>
