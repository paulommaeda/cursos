<?php defined('ABSPATH') || exit; ?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head><body <?php body_class('shalom-theme'); ?>><?php wp_body_open(); ?><a class="shalom-skip" href="#main-content">Ir para o conteúdo</a>
<div class="shalom-announcement">Igreja Shalom <span aria-hidden="true">·</span> Uma fé que cresce. Uma vida que serve. <a href="<?php echo esc_url(home_url('/#proposito')); ?>">Conheça nosso propósito ↗</a></div>
<header class="shalom-header"><div class="shalom-container shalom-header-inner"><a class="shalom-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Igreja Shalom — início"><?php
$logo=get_theme_mod('custom_logo');if(!$logo&&function_exists('nexo_settings')){$logo=nexo_settings()['logo_id'];}
if($logo){echo wp_get_attachment_image($logo,'medium',false,['class'=>'shalom-logo','alt'=>'Igreja Shalom']);}
else{echo '<span class="shalom-brand-emblem" aria-hidden="true"><svg width="26" height="30" viewBox="0 0 26 30" fill="none"><path d="M13 2v26M3 11h20M5 28h16" stroke="currentColor" stroke-width="1.5"/></svg></span><span>Shalom<small>FORMAÇÃO & PROPÓSITO</small></span>';}
?></a><?php if(shortcode_exists('nexo_navigation')){echo do_shortcode('[nexo_navigation brand="no"]');}else{echo '<a class="shalom-link" href="#cursos">Conheça os cursos</a>';} ?></div></header>
