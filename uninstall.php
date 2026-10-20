<?php
/**
 * Uninstall 211j AI Shopping for WooCommerce.
 *
 * Removes all plugin data: custom tables, options, transients.
 *
 * @package TwoElevenJay\AIShopping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
$twoelevenjay_ais_tables = array(
	$wpdb->prefix . 'twoelevenjay_ais_cart_sessions',
	$wpdb->prefix . 'twoelevenjay_ais_rate_limits',
);

foreach ( $twoelevenjay_ais_tables as $twoelevenjay_ais_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$twoelevenjay_ais_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// Delete options.
$twoelevenjay_ais_options = array(
	'twoelevenjay_ais_version',
	'twoelevenjay_ais_db_version',
	'twoelevenjay_ais_rewrite_version',
	'twoelevenjay_ais_enable_acp',
	'twoelevenjay_ais_enable_ucp',
	'twoelevenjay_ais_enable_mcp',
	'twoelevenjay_ais_rate_limit_read',
	'twoelevenjay_ais_rate_limit_write',
	'twoelevenjay_ais_enable_logging',
	'twoelevenjay_ais_allow_http',
	'twoelevenjay_ais_webhook_url',
	'twoelevenjay_ais_webhook_secret',
	// Discovery options.
	'twoelevenjay_ais_enable_discovery',
	'twoelevenjay_ais_enable_schema_enhancement',
	'twoelevenjay_ais_enable_llms_txt',
	'twoelevenjay_ais_enable_agent_json',
	'twoelevenjay_ais_enable_product_feed',
	'twoelevenjay_ais_enable_markdown_negotiation',
);

foreach ( $twoelevenjay_ais_options as $twoelevenjay_ais_option ) {
	delete_option( $twoelevenjay_ais_option );
}

// Delete transients.
delete_transient( 'twoelevenjay_ais_extension_scan' );
delete_transient( 'twoelevenjay_ais_product_feed' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'twoelevenjay_ais_daily_extension_scan' );
wp_clear_scheduled_hook( 'twoelevenjay_ais_cleanup_expired_carts' );
wp_clear_scheduled_hook( 'twoelevenjay_ais_cleanup_rate_limits' );
