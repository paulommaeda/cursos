<?php
namespace NexoCursos;
defined( 'ABSPATH' ) || exit;
/** Installs pages without replacing an existing WooCommerce checkout or shop. */
final class Bootstrap {
    public static function boot(): void {
        add_action( 'admin_init', array( __CLASS__, 'upgrade' ) );
        add_action( 'admin_post_nexo_repair_pages', array( __CLASS__, 'repair_pages' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( NEXO_FILE ), array( __CLASS__, 'plugin_links' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
        add_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'account_url' ), 10, 4 );
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ) );
    }
    public static function activate(): void {
        Core::activate();
        Calendar::install();
        Calendar::register_type();
        add_option( 'nexo_settings', nexo_settings() );
        self::setup_pages();
        update_option( 'nexo_version', NEXO_VERSION );
        flush_rewrite_rules();
    }
    public static function upgrade(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        if ( NEXO_VERSION !== get_option( 'nexo_version' ) ) { self::activate(); }
        if ( class_exists( 'WooCommerce' ) && ! get_option( 'nexo_woo_pages_ready' ) ) { self::setup_pages(); }
    }
    public static function setup_pages(): void {
        $definitions = array(
            'catalog' => array( 'Cursos', 'cursos', '[nexo_catalog]' ),
            'account' => array( 'Meus cursos', 'meus-cursos', '[nexo_my_courses]' ),
            'login' => array( 'Entrar', 'entrar', '[nexo_login]' ),
            'dashboard' => array( 'Painel dos cursos', 'painel-dos-cursos', '[nexo_manager_dashboard]' ),
        );
        $pages = get_option( 'nexo_pages', array() );
        $pages = is_array( $pages ) ? $pages : array();
        if ( class_exists( 'WooCommerce' ) ) {
            $definitions += array(
                'products' => array( 'Produtos', 'produtos', '' ),
                'cart' => array( 'Carrinho', 'carrinho', '[woocommerce_cart]' ),
                'checkout' => array( 'Finalizar compra', 'finalizar-compra', '[woocommerce_checkout]' ),
                'woo_account' => array( 'Minha conta', 'minha-conta', '[woocommerce_my_account]' ),
            );
        }
        $woo_options = array( 'products' => 'woocommerce_shop_page_id', 'cart' => 'woocommerce_cart_page_id', 'checkout' => 'woocommerce_checkout_page_id', 'woo_account' => 'woocommerce_myaccount_page_id' );
        foreach ( $definitions as $key => $definition ) {
            $woo_id = isset( $woo_options[ $key ] ) ? absint( get_option( $woo_options[ $key ] ) ) : 0;
            if ( $woo_id && 'publish' === get_post_status( $woo_id ) && 'page' === get_post_type( $woo_id ) ) { $pages[ $key ] = $woo_id; continue; }
            $existing_id = isset( $pages[ $key ] ) ? absint( $pages[ $key ] ) : 0;
            if ( ! $existing_id || 'publish' !== get_post_status( $existing_id ) || 'page' !== get_post_type( $existing_id ) ) {
                $owned = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => '_nexo_page', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids' ) );
                $existing_id = $owned ? (int) $owned[0] : 0;
                if ( ! $existing_id ) {
                    $result = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $definition[0], 'post_name' => $definition[1], 'post_content' => $definition[2], 'comment_status' => 'closed', 'meta_input' => array( '_nexo_page' => $key ) ), true );
                    if ( is_wp_error( $result ) ) { continue; }
                    $existing_id = (int) $result;
                }
                $pages[ $key ] = $existing_id;
            }
            if ( isset( $woo_options[ $key ] ) ) { update_option( $woo_options[ $key ], $existing_id ); }
        }
        update_option( 'nexo_pages', $pages );
        if ( class_exists( 'WooCommerce' ) ) { update_option( 'nexo_woo_pages_ready', true ); }
    }
    public static function repair_pages(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Você não tem permissão para esta ação.', 'nexo-cursos' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'nexo_repair_pages' );
        self::setup_pages();
        wp_safe_redirect( admin_url( 'admin.php?page=nexo-cursos&nexo_pages_repaired=1' ) );
        exit;
    }
    public static function notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'nexo' ) ) { return; }
        if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="notice notice-info"><p>' . esc_html__( 'Nexo Cursos está no modo de gestão. Ative o WooCommerce para vender cursos e criar as páginas da loja, carrinho e checkout.', 'nexo-cursos' ) . '</p></div>'; }
        if ( isset( $_GET['nexo_pages_repaired'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Páginas verificadas e criadas. As páginas existentes foram preservadas.', 'nexo-cursos' ) . '</p></div>'; }
    }
    public static function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( nexo_page_url('dashboard') ) . '">' . esc_html__( 'Abrir painel', 'nexo-cursos' ) . '</a>' );
        return $links;
    }
    public static function account_menu( array $items ): array {
        $logout = $items['customer-logout'] ?? null;
        unset( $items['customer-logout'] );
        $items['nexo-cursos'] = __( 'Meus cursos', 'nexo-cursos' );
        if ( $logout ) { $items['customer-logout'] = $logout; }
        return $items;
    }
    public static function account_url( string $url, string $endpoint, $value, string $permalink ): string {
        return 'nexo-cursos' === $endpoint ? nexo_page_url( 'account' ) : $url;
    }
    public static function thankyou( $order_id ): void {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
        if ( ! $order || ! $order->get_user_id() || get_current_user_id() !== $order->get_user_id() ) { return; }
        echo '<p class="nexo-order-access"><a class="button" href="' . esc_url( nexo_page_url( 'account' ) ) . '">' . esc_html__( 'Acompanhar meus cursos', 'nexo-cursos' ) . '</a></p>';
    }
}
