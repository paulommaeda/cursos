<?php
/** Companion theme for the Nexo Cursos navigation and learning screens. */
defined('ABSPATH') || exit;
function shalom_setup(): void {
    load_theme_textdomain('shalom-formacao',get_template_directory().'/languages');
    add_theme_support('title-tag');add_theme_support('post-thumbnails');
    add_theme_support('custom-logo',['height'=>90,'width'=>280,'flex-height'=>true,'flex-width'=>true]);
    add_theme_support('html5',['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('responsive-embeds');add_theme_support('align-wide');
    add_theme_support('woocommerce');add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('nexo-navigation');
}
add_action('after_setup_theme','shalom_setup');
function shalom_brand(array $settings): array {
    if($settings['brand_name']==='Nexo Cursos'){$settings['brand_name']='Igreja Shalom';}
    if($settings['primary_color']==='#167b60'){$settings['primary_color']='#173b34';}
    if($settings['accent_color']==='#dbef8c'){$settings['accent_color']='#c6a56c';}
    return $settings;
}
add_filter('nexo_settings','shalom_brand');
function shalom_assets(): void {
    if(defined('NEXO_URL')){wp_enqueue_style('nexo-frontend',NEXO_URL.'assets/frontend.css',[],NEXO_VERSION);}
    wp_enqueue_style('shalom-formacao',get_stylesheet_uri(),function_exists('nexo_settings')?['nexo-frontend']:[],wp_get_theme()->get('Version'));
    if(function_exists('nexo_brand_styles')){wp_add_inline_style('shalom-formacao',':root{'.nexo_brand_styles().'}');}
}
add_action('wp_enqueue_scripts','shalom_assets',30);
function shalom_url(string $key): string {
    return function_exists('nexo_page_url')?nexo_page_url($key):home_url('/');
}
function shalom_platform_page(): bool {
    if(is_singular('nexo_course')){return true;}
    $post=get_post();
    if(!$post){return false;}
    foreach(['nexo_catalog','nexo_course','nexo_my_courses','nexo_login','nexo_manager_dashboard'] as $shortcode){if(has_shortcode($post->post_content,$shortcode)){return true;}}
    return false;
}
function shalom_activate(): void {
    $owned=get_posts(['post_type'=>'page','post_status'=>['publish','draft'],'numberposts'=>1,'meta_key'=>'_shalom_home','meta_value'=>'1']);
    $id=$owned?(int)$owned[0]->ID:wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Formação Shalom','post_content'=>'','meta_input'=>['_shalom_home'=>'1']],true);
    if(is_wp_error($id)){return;}
    if(get_post_status($id)!=='publish'){wp_update_post(['ID'=>$id,'post_status'=>'publish']);}
    if((int)get_option('page_on_front')!==$id){update_option('shalom_previous_home',['show_on_front'=>get_option('show_on_front'),'page_on_front'=>get_option('page_on_front')]);}
    update_option('show_on_front','page');update_option('page_on_front',$id);
}
add_action('after_switch_theme','shalom_activate');
function shalom_restore_home(string $name,\WP_Theme $new,\WP_Theme $old): void {
    if($old->get_stylesheet()!=='shalom-formacao'||$new->get_stylesheet()==='shalom-formacao'){return;}
    $current=(int)get_option('page_on_front');$before=get_option('shalom_previous_home');
    if(get_post_meta($current,'_shalom_home',true)==='1'&&is_array($before)){
        update_option('show_on_front',$before['show_on_front']);update_option('page_on_front',$before['page_on_front']);
    }
}
add_action('switch_theme','shalom_restore_home',10,3);
function shalom_dependency_notice(): void {
    if(class_exists('NexoCursos\\Commerce')||!current_user_can('activate_plugins')){return;}
    echo '<div class="notice notice-warning"><p>O tema Shalom Formação usa o Nexo Cursos para catálogo, navegação e matrículas. Instale ou atualize para Nexo Cursos 1.2.0 ou superior e ative o plugin que acompanha o tema.</p><p><a href="'.esc_url(admin_url('plugins.php')).'">Abrir plugins</a></p></div>';
}
add_action('admin_notices','shalom_dependency_notice');
function shalom_customize(\WP_Customize_Manager $customize): void {
    $customize->add_section('shalom_home',['title'=>'Página inicial Shalom','priority'=>30]);
    foreach(['headline'=>['Título principal',"Crescer em Cristo.\nServir com propósito."],'intro'=>['Apresentação','Uma jornada para discipular, aperfeiçoar e enviar. Cursos livres da Igreja Shalom para fortalecer a fé e preparar cada pessoa para servir.']] as $key=>$data){
        $customize->add_setting('shalom_'.$key,['default'=>$data[1],'sanitize_callback'=>'sanitize_textarea_field']);
        $customize->add_control('shalom_'.$key,['section'=>'shalom_home','label'=>$data[0],'type'=>'textarea']);
    }
}
add_action('customize_register','shalom_customize');
add_action('after_setup_theme',static function(){
    if(!class_exists('WooCommerce')){return;}
    remove_action('woocommerce_sidebar','woocommerce_get_sidebar',10);
    remove_action('woocommerce_before_main_content','woocommerce_output_content_wrapper',10);
    remove_action('woocommerce_after_main_content','woocommerce_output_content_wrapper_end',10);
    add_action('woocommerce_before_main_content',static function(){echo '<main id="main-content" class="shalom-page"><div class="shalom-container">';},10);
    add_action('woocommerce_after_main_content',static function(){echo '</div></main>';},10);
});

add_filter('document_title_parts', static function(array $parts): array {
    $brand=function_exists('nexo_settings')?nexo_settings()['brand_name']:'Igreja Shalom';
    if(is_front_page()){$parts['title']=$brand.' — Discipular, Aperfeiçoar e Enviar';unset($parts['tagline']);}
    else{$parts['site']=$brand;}
    return $parts;
});
