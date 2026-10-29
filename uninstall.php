<?php
/** Educational records are retained unless the site owner explicitly opts in. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
if ( ! defined( 'NEXO_DELETE_DATA' ) || true !== NEXO_DELETE_DATA ) { return; }
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nexo_enrollments" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nexo_session_attendance" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nexo_order_courses" );
wp_clear_scheduled_hook('nexo_order_index_backfill');
foreach ( array( 'nexo_settings', 'nexo_pages', 'nexo_version', 'nexo_woo_pages_ready', 'nexo_db_version', 'nexo_session_db_version', 'nexo_portal_roles', 'nexo_order_index_db', 'nexo_order_index_done', 'nexo_order_index_page' ) as $option ) { delete_option( $option ); }
$ids = get_posts( array( 'post_type' => array( 'nexo_course', 'nexo_topic', 'nexo_lesson', 'nexo_quiz', 'nexo_session' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_nexo\\_progress\\_%' OR meta_key LIKE '\\_nexo\\_attendance%'" );
remove_role( 'nexo_manager' );
remove_role( 'nexo_coordinator' );
// Owned pages, WooCommerce products and financial records are retained.
