<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main-content" class="shalom-page"><div class="shalom-container entry-content"><?php while(have_posts()){the_post();if(!shalom_platform_page()){echo '<header class="shalom-page-title"><h1>'.esc_html(get_the_title()).'</h1></header>';}the_content();wp_link_pages();} ?></div></main><?php get_footer(); ?>
