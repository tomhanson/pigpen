<?php 
  $bannerImg = get_field('global_header_image', 'option'); 
?>
<header class="bg-image | header header--primary" style="background-image: url('<?php echo $bannerImg['sizes']['large']; ?>');">
  <div class="container-fluid | flex align-center">
    <div class="col-xs-6">
      <a class="brand" href="<?= esc_url(home_url('/')); ?>">
        <img src="<?php bloginfo('template_directory'); ?>/dist/images/icons/logo-dark.svg" alt="<?php bloginfo('name'); ?>">
        <div class="sr-only">>
          <?php bloginfo('name'); ?>
        </div>
      </a>
    </div>
    <div class="col-xs-6">
      <nav class="nav nav--primary">
        <?php
      if (has_nav_menu('primary_navigation')) :
        wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'nav']);
      endif;
      ?>
      </nav>
    </div>
  </div>
</header>
