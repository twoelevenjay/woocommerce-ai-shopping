<?php
/**
 * Uninstall 211j AI Shopping for WooCommerce.
 *
 * Removes all plugin data: custom tables, options, transients.
 *
 * @package AIShopping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
$ai_shopping_tables = array(
	$wpdb->prefix . 'ais_cart_sessions',
	$wpdb->prefix . 'ais_rate_limits',
);

foreach ( $ai_shopping_tables as $ai_shopping_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$ai_shopping_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// Delete options.
$ai_shopping_options = array(
	'ais_version',
	'ais_db_version',
	'ais_enable_acp',
	'ais_enable_ucp',
	'ais_enable_mcp',
	'ais_rate_limit_read',
	'ais_rate_limit_write',
	'ais_enable_logging',
	'ais_allow_http',
	'ais_webhook_url',
	'ais_webhook_secret',
	// Discovery options.
	'ais_enable_discovery',
	'ais_enable_schema_enhancement',
	'ais_enable_llms_txt',
	'ais_enable_agent_json',
	'ais_enable_product_feed',
	'ais_enable_markdown_negotiation',
);

foreach ( $ai_shopping_options as $ai_shopping_option ) {
	delete_option( $ai_shopping_option );
}

// Delete transients.
delete_transient( 'ais_extension_scan' );
delete_transient( 'ais_product_feed' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'ais_daily_extension_scan' );
wp_clear_scheduled_hook( 'ais_cleanup_expired_carts' );
wp_clear_scheduled_hook( 'ais_cleanup_rate_limits' );
