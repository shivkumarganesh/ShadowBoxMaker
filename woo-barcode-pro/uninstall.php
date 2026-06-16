<?php
/**
 * Uninstall WooBarcode Pro — removes all plugin data.
 *
 * @package WCBarcodePro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove options.
delete_option( 'wcbp_settings' );
delete_option( 'wcbp_db_version' );
delete_transient( 'wcbp_just_activated' );

// Remove user meta for all users.
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wcbp_tutorial_step' ) );
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wcbp_tutorial_complete' ) );

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcbp_print_queue" );      // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcbp_price_templates" );   // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wcbp_label_templates" );   // phpcs:ignore WordPress.DB
