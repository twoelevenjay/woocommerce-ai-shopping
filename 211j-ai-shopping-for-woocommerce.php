<?php
/**
 * Plugin Name: 211j AI Shopping for WooCommerce
 * Plugin URI:  https://github.com/twoelevenjay/ai-shopping
 * Description: Expose your WooCommerce storefront to AI agents via ACP (2026-01-30), UCP (2026-01-11), and MCP (2025-11-25) protocols. Zero-config product discovery, cart management, checkout, and order tracking for any AI agent.
 * Version:     1.1.1
 * Author:      flavflavor
 * Author URI:  https://flavflavor.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 211j-ai-shopping-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.6.1
 *
 * @package TwoElevenJay\AIShopping
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'TWOELEVENJAY_AIS_VERSION', '1.1.1' );
define( 'TWOELEVENJAY_AIS_PLUGIN_FILE', __FILE__ );
define( 'TWOELEVENJAY_AIS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWOELEVENJAY_AIS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TWOELEVENJAY_AIS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for TwoElevenJay\AIShopping classes.
 *
 * Maps TwoElevenJay\AIShopping\Sub\ClassName to includes/sub/class-classname.php
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'TwoElevenJay\\AIShopping\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';
		$path     = TWOELEVENJAY_AIS_PLUGIN_DIR . 'includes/';

		if ( ! empty( $parts ) ) {
			$path .= strtolower( implode( '/', $parts ) ) . '/';
		}

		$file = $path . $filename;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Check requirements and boot the plugin.
 */
function twoelevenjay_ais_init() {
	// Check for WooCommerce.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'twoelevenjay_ais_woocommerce_missing_notice' );
		return;
	}

	// Declare HPOS compatibility.
	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	twoelevenjay_ais_maybe_upgrade();

	// Boot the plugin.
	\TwoElevenJay\AIShopping\Plugin::instance();
}
add_action( 'plugins_loaded', 'twoelevenjay_ais_init', 10 );

/**
 * Admin notice when WooCommerce is not active.
 */
function twoelevenjay_ais_woocommerce_missing_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( '211j AI Shopping for WooCommerce requires WooCommerce to be installed and active.', '211j-ai-shopping-for-woocommerce' )
	);
}

/**
 * Create tables, migrate 1.1.x settings and store defaults.
 *
 * Runs on activation and whenever the stored schema version differs from
 * the plugin version, so updates without reactivation are covered too.
 */
function twoelevenjay_ais_install() {
	// Create custom tables.
	require_once TWOELEVENJAY_AIS_PLUGIN_DIR . 'includes/cart/class-cart-session.php';
	\TwoElevenJay\AIShopping\Cart\Cart_Session::create_tables();

	require_once TWOELEVENJAY_AIS_PLUGIN_DIR . 'includes/security/class-rate-limiter.php';
	\TwoElevenJay\AIShopping\Security\Rate_Limiter::create_tables();

	twoelevenjay_ais_migrate_legacy_data();

	// Set default options.
	$defaults = array(
		'twoelevenjay_ais_enable_acp'                  => 'yes',
		'twoelevenjay_ais_enable_ucp'                  => 'yes',
		'twoelevenjay_ais_enable_mcp'                  => 'yes',
		'twoelevenjay_ais_rate_limit_read'             => 60,
		'twoelevenjay_ais_rate_limit_write'            => 30,
		'twoelevenjay_ais_enable_logging'              => 'no',
		'twoelevenjay_ais_allow_http'                  => 'yes',
		// Discovery layer defaults.
		'twoelevenjay_ais_enable_discovery'            => 'yes',
		'twoelevenjay_ais_enable_schema_enhancement'   => 'yes',
		'twoelevenjay_ais_enable_llms_txt'             => 'yes',
		'twoelevenjay_ais_enable_agent_json'           => 'yes',
		'twoelevenjay_ais_enable_product_feed'         => 'yes',
		'twoelevenjay_ais_enable_markdown_negotiation' => 'yes',
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			update_option( $key, $value );
		}
	}

	// Store version for upgrade routines.
	update_option( 'twoelevenjay_ais_version', TWOELEVENJAY_AIS_VERSION );
	update_option( 'twoelevenjay_ais_db_version', TWOELEVENJAY_AIS_VERSION );
}

/**
 * Move settings saved by 1.1.x (unprefixed "ais_" names) to the prefixed names.
 *
 * The old names are only read and deleted here, never written.
 */
function twoelevenjay_ais_migrate_legacy_data() {
	global $wpdb;

	$legacy_options = array(
		'enable_acp',
		'enable_ucp',
		'enable_mcp',
		'rate_limit_read',
		'rate_limit_write',
		'enable_logging',
		'allow_http',
		'webhook_url',
		'webhook_secret',
		'enable_discovery',
		'enable_schema_enhancement',
		'enable_llms_txt',
		'enable_agent_json',
		'enable_product_feed',
		'enable_markdown_negotiation',
	);

	foreach ( $legacy_options as $name ) {
		$value = get_option( 'ais_' . $name );
		if ( false !== $value ) {
			if ( false === get_option( 'twoelevenjay_ais_' . $name ) ) {
				update_option( 'twoelevenjay_ais_' . $name, $value );
			}
			delete_option( 'ais_' . $name );
		}
	}

	foreach ( array( 'version', 'db_version', 'rewrite_version' ) as $name ) {
		delete_option( 'ais_' . $name );
	}
	foreach ( array( 'extension_scan', 'product_feed' ) as $name ) {
		delete_transient( 'ais_' . $name );
	}
	foreach ( array( 'daily_extension_scan', 'cleanup_expired_carts', 'cleanup_rate_limits' ) as $name ) {
		wp_clear_scheduled_hook( 'ais_' . $name );
	}

	// Cart sessions and rate-limit buckets are short-lived; drop the old tables.
	foreach ( array( 'cart_sessions', 'rate_limits' ) as $name ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'ais_' . $name . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
	}
}

/**
 * Run the install routine after an update that skipped the activation hook.
 */
function twoelevenjay_ais_maybe_upgrade() {
	if ( TWOELEVENJAY_AIS_VERSION !== get_option( 'twoelevenjay_ais_db_version' ) ) {
		twoelevenjay_ais_install();
	}
}

/**
 * Activation hook.
 */
function twoelevenjay_ais_activate() {
	twoelevenjay_ais_install();

	// Flush rewrite rules for well-known endpoint.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'twoelevenjay_ais_activate' );

/**
 * Deactivation hook.
 */
function twoelevenjay_ais_deactivate() {
	// Clean up scheduled events.
	wp_clear_scheduled_hook( 'twoelevenjay_ais_daily_extension_scan' );
	wp_clear_scheduled_hook( 'twoelevenjay_ais_cleanup_expired_carts' );
	wp_clear_scheduled_hook( 'twoelevenjay_ais_cleanup_rate_limits' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'twoelevenjay_ais_deactivate' );
