<?php
/**
 * Core plugin orchestration.
 *
 * @package TwoElevenJay\AIShopping
 */

namespace TwoElevenJay\AIShopping;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton).
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wire everything up.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Admin.
		if ( is_admin() ) {
			new Admin\Admin_Page();
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );
		}

		// Scheduled events.
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_action( 'twoelevenjay_ais_daily_extension_scan', array( $this, 'run_extension_scan' ) );
		add_action( 'twoelevenjay_ais_cleanup_expired_carts', array( $this, 'cleanup_expired_carts' ) );
		add_action( 'twoelevenjay_ais_cleanup_rate_limits', array( $this, 'cleanup_rate_limits' ) );

		// UCP well-known endpoint.
		add_action( 'init', array( $this, 'register_wellknown_rewrite' ) );
		add_action( 'template_redirect', array( $this, 'handle_wellknown' ) );
		add_filter( 'redirect_canonical', array( $this, 'skip_wellknown_canonical_redirect' ) );

		// Webhook dispatcher.
		new Webhooks\Webhook_Dispatcher();

		// Discovery layer.
		new Discovery\Discovery();
	}

	/**
	 * Add AI Shopping settings page to WooCommerce Settings.
	 *
	 * @param array $settings Array of WC_Settings_Page instances.
	 * @return array
	 */
	public function add_settings_page( $settings ) {
		$settings[] = new Admin\WC_Settings_AI_Shopping();
		return $settings;
	}

	/**
	 * Register all REST API routes.
	 */
	public function register_routes() {
		// Core API endpoints.
		( new Api\Products() )->register_routes();
		( new Api\Cart() )->register_routes();
		( new Api\Checkout() )->register_routes();
		( new Api\Orders() )->register_routes();
		( new Api\Store() )->register_routes();
		( new Api\Account() )->register_routes();

		// Protocol adapters.
		if ( 'yes' === get_option( 'twoelevenjay_ais_enable_acp', 'yes' ) ) {
			( new Protocols\ACP_Adapter() )->register_routes();
		}
		if ( 'yes' === get_option( 'twoelevenjay_ais_enable_ucp', 'yes' ) ) {
			( new Protocols\UCP_Adapter() )->register_routes();
		}
		if ( 'yes' === get_option( 'twoelevenjay_ais_enable_mcp', 'yes' ) ) {
			( new Protocols\MCP_Adapter() )->register_routes();
		}
	}

	/**
	 * Schedule recurring events.
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( 'twoelevenjay_ais_daily_extension_scan' ) ) {
			wp_schedule_event( time(), 'daily', 'twoelevenjay_ais_daily_extension_scan' );
		}
		if ( ! wp_next_scheduled( 'twoelevenjay_ais_cleanup_expired_carts' ) ) {
			wp_schedule_event( time(), 'hourly', 'twoelevenjay_ais_cleanup_expired_carts' );
		}
		if ( ! wp_next_scheduled( 'twoelevenjay_ais_cleanup_rate_limits' ) ) {
			wp_schedule_event( time(), 'hourly', 'twoelevenjay_ais_cleanup_rate_limits' );
		}
	}

	/**
	 * Run extension scan.
	 */
	public function run_extension_scan() {
		Extensions\Extension_Detector::scan();
	}

	/**
	 * Clean up expired cart sessions.
	 */
	public function cleanup_expired_carts() {
		Cart\Cart_Session::cleanup_expired();
	}

	/**
	 * Clean up old rate limit entries.
	 */
	public function cleanup_rate_limits() {
		Security\Rate_Limiter::cleanup();
	}

	/**
	 * Register rewrite rule for /.well-known/ucp.
	 */
	public function register_wellknown_rewrite() {
		add_rewrite_rule( '^\.well-known/ucp/?$', 'index.php?twoelevenjay_ais_wellknown_ucp=1', 'top' );
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'twoelevenjay_ais_wellknown_ucp';
				return $vars;
			}
		);
	}

	/**
	 * Disable the canonical (trailing slash) redirect for /.well-known/ucp.
	 *
	 * @param string|false $redirect_url Canonical URL WordPress would redirect to.
	 * @return string|false
	 */
	public function skip_wellknown_canonical_redirect( $redirect_url ) {
		return get_query_var( 'twoelevenjay_ais_wellknown_ucp' ) ? false : $redirect_url;
	}

	/**
	 * Handle /.well-known/ucp requests.
	 */
	public function handle_wellknown() {
		if ( ! get_query_var( 'twoelevenjay_ais_wellknown_ucp' ) ) {
			return;
		}

		if ( 'yes' !== get_option( 'twoelevenjay_ais_enable_ucp', 'yes' ) ) {
			status_header( 404 );
			exit;
		}

		$adapter = new Protocols\UCP_Adapter();
		$profile = $adapter->get_merchant_profile();

		header( 'Access-Control-Allow-Origin: *' );
		wp_send_json( $profile, 200 );
	}
}
