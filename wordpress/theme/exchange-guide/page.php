<?php get_header(); while(have_posts()){the_post();?><article class="cg-page"><h1><?php the_title(); ?></h1><div class="cg-content"><?php the_content(); ?></div></article><?php } get_footer(); ?>
