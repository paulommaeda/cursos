<?php
/**
 * Plugin Name: Nexo Cursos
 * Description: Gestão de cursos EAD ou presenciais, matrículas, avaliações, gerentes e vendas com WooCommerce.
 * Plugin URI: https://github.com/paulommaeda/cursos
 * Update URI: https://github.com/paulommaeda/cursos
 * Version: 1.4.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Nexo Cursos
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexo-cursos
 * WC requires at least: 8.2
 */
defined( 'ABSPATH' ) || exit;
define( 'NEXO_VERSION', '1.4.0' );
define( 'NEXO_FILE', __FILE__ );
define( 'NEXO_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXO_URL', plugin_dir_url( __FILE__ ) );
require_once NEXO_DIR . 'includes/class-brand.php';

function nexo_settings(): array {
    $defaults = array( 'brand_name' => 'Nexo Cursos', 'logo_id' => 0, 'login_title' => 'Bem-vindo de volta', 'login_message' => 'Seu próximo passo começa aqui.' );
    $defaults = array_merge($defaults, \NexoCursos\Brand::defaults());
    $stored = get_option( 'nexo_settings', array() );
    $options = wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
    foreach ( array( 'brand_name', 'login_title', 'login_message' ) as $key ) { $options[ $key ] = sanitize_text_field( $options[ $key ] ); }
    foreach ( array_keys(\NexoCursos\Brand::colors()) as $key ) { $options[ $key ] = sanitize_hex_color( $options[ $key ] ) ?: $defaults[ $key ]; }
    $options['logo_id'] = absint( $options['logo_id'] );
    $options = \NexoCursos\Brand::sanitize($options,$options);
    return apply_filters( 'nexo_settings', $options );
}
/** CSS declarations for an escaped style attribute or stylesheet rule. */
function nexo_brand_styles(): string {
    $o = nexo_settings();
    return '--nexo-primary:' . $o['primary_color'] . ';--nexo-accent:' . $o['accent_color'] . ';--nexo-brand:' . $o['primary_color'] . ';--nexo-highlight:' . $o['accent_color'] . ';' . \NexoCursos\Brand::variables($o);
}
function nexo_page_url( string $key ): string {
    $pages = get_option( 'nexo_pages', array() );
    $id = isset( $pages[ $key ] ) ? absint( $pages[ $key ] ) : 0;
    if ( $id && 'publish' === get_post_status( $id ) ) { return (string) get_permalink( $id ); }
    if ( 'login' === $key ) { return wp_login_url(); }
    if ( 'dashboard' === $key ) { return home_url('/painel-dos-cursos/'); }
    return home_url( '/' );
}
require_once NEXO_DIR . 'includes/class-core.php';
require_once NEXO_DIR . 'includes/class-commerce.php';
require_once NEXO_DIR . 'includes/class-calendar.php';
require_once NEXO_DIR . 'includes/class-classroom.php';
require_once NEXO_DIR . 'includes/class-portal.php';
require_once NEXO_DIR . 'includes/class-portal-courses.php';
require_once NEXO_DIR . 'includes/class-portal-people.php';
require_once NEXO_DIR . 'includes/class-portal-orders.php';
require_once NEXO_DIR . 'includes/class-portal-sales.php';
require_once NEXO_DIR . 'includes/class-portal-brand.php';
require_once NEXO_DIR . 'includes/class-admin.php';
require_once NEXO_DIR . 'includes/class-frontend.php';
require_once NEXO_DIR . 'includes/class-bootstrap.php';
require_once NEXO_DIR . 'includes/class-updater.php';
register_activation_hook( __FILE__, array( '\NexoCursos\Bootstrap', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
add_action( 'before_woocommerce_init', static function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NEXO_FILE, true );
    }
} );
add_action( 'plugins_loaded', static function () {
    \NexoCursos\Core::boot();
    \NexoCursos\Portal::boot();
    \NexoCursos\PortalOrders::boot();
    \NexoCursos\Commerce::boot();
    \NexoCursos\Calendar::boot();
    \NexoCursos\Classroom::boot();
    \NexoCursos\Admin::boot();
    \NexoCursos\Frontend::boot();
    \NexoCursos\Bootstrap::boot();
    \NexoCursos\Updater::boot();
} );
