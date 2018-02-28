<section id="section:banner" class="spacing-lg--btm-only">
  <div class="container">
    <div class="row">
      <div class="col-xs-12 | text-center | spacing-md--btm-only | banner__large-logo">
        <img src="<?php bloginfo('template_directory'); ?>/dist/images/icons/logo-dark-lg.svg" alt="<?php bloginfo('name'); ?>">
      </div>
      <div class="col-md-10 col-md-offset-1 | text-center | banner__headlines">
        <h1 class="spacing-sm--btm-only">
          <?php the_field('home_headline'); ?>
        </h1>
        <h4>
          <?php the_field('home_subheadline'); ?>
        </h4>
      </div>
    </div>
  </div>
</section>
<section id="section:category">
  <div class="container">
    <div class="row">
      <?php while( have_rows('home_categories') ) : the_row(); ?>
      <div class="col-sm-6 col-md-3">
        <div class="category-tab">
          <div class="category-tab__img">
            <?php $img = get_sub_field('image'); ?>
            <img src="<?php echo $img['sizes']['tall-category']; ?>" alt="<?php the_sub_field('title'); ?>">
          </div>
          <div class="flex align-center justify-space-between | spacing-sm--top-only | category-tab__info">
            <h5>
              <?php the_sub_field('title'); ?>
            </h5>
            <a href="/shop">
              <p>View all</p>
            </a>
          </div>

        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</section>
<section id="section:about" class="spacing-lg">
  <div class="container">
    <div class="row">
      <div class="col-md-8">
        <div class="cta cta--primary | about">
          <div class="cta__headline">
            <h2 class="spacing-sm--btm-only">
              <?php the_field('global_about_headline', 'option'); ?>
            </h2>
            <h4 class="spacing-md--btm-only">
              <?php the_field('global_about_subheadline', 'option'); ?>
            </h4>
            <p class=" spacing-md--btm-only | cta__text">
              <?php the_field('global_about_text', 'option'); ?>
            </p>
          </div>
        </div>
        text
      </div>
      <div class="col-md-4">
        image with facebook</div>
    </div>
  </div>
</section>
