<?php
/** Educational records are retained unless the site owner explicitly opts in. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
if ( ! defined( 'NEXO_DELETE_DATA' ) || true !== NEXO_DELETE_DATA ) { return; }
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nexo_enrollments" );
foreach ( array( 'nexo_settings', 'nexo_pages', 'nexo_version', 'nexo_woo_pages_ready', 'nexo_db_version' ) as $option ) { delete_option( $option ); }
$ids = get_posts( array( 'post_type' => array( 'nexo_course', 'nexo_topic', 'nexo_lesson', 'nexo_quiz' ), 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\\_nexo\\_progress\\_%' OR meta_key LIKE '\\_nexo\\_attendance%'" );
remove_role( 'nexo_manager' );
// Owned pages, WooCommerce products and financial records are retained.
