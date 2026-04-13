<?php
/**
 * WooCommerce Settings integration.
 *
 * Adds an "AI Shopping" tab to WooCommerce > Settings with 4 sections:
 * General, Discovery, Extensions, Endpoints.
 *
 * @package AIShopping\Admin
 */

namespace AIShopping\Admin;

defined( 'ABSPATH' ) || exit;

use AIShopping\Extensions\Extension_Detector;

/**
 * WC_Settings_AI_Shopping class.
 */
class WC_Settings_AI_Shopping extends \WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = '211j-ai-shopping-for-woocommerce';
		$this->label = __( '211j AI Shopping', '211j-ai-shopping-for-woocommerce' );

		// Render promo footer on every section.
		add_action( 'woocommerce_after_settings_' . $this->id, array( $this, 'render_promo_footer' ) );

		parent::__construct();
	}

	/**
	 * Get own sections.
	 *
	 * @return array
	 */
	protected function get_own_sections() {
		return array(
			''           => __( 'General', '211j-ai-shopping-for-woocommerce' ),
			'discovery'  => __( 'Discovery', '211j-ai-shopping-for-woocommerce' ),
			'extensions' => __( 'Extensions', '211j-ai-shopping-for-woocommerce' ),
			'endpoints'  => __( 'Endpoints', '211j-ai-shopping-for-woocommerce' ),
		);
	}

	/**
	 * Get settings for the default (General) section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		return array(
			// Protocols.
			array(
				'title' => __( 'Protocols', '211j-ai-shopping-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Enable or disable AI commerce protocol adapters.', '211j-ai-shopping-for-woocommerce' ),
				'id'    => 'ais_protocols_options',
			),
			array(
				'title'   => __( 'Agentic Commerce Protocol (ACP)', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_acp',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Enable ACP endpoints (OpenAI/Stripe)', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Universal Commerce Protocol (UCP)', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_ucp',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Enable UCP endpoints (Shopify/Google)', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Model Context Protocol (MCP)', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_mcp',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Enable MCP tools (Anthropic)', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ais_protocols_options',
			),

			// Rate Limiting.
			array(
				'title' => __( 'Rate Limiting', '211j-ai-shopping-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'ais_rate_limit_options',
			),
			array(
				'title'             => __( 'Read requests/minute', '211j-ai-shopping-for-woocommerce' ),
				'id'                => 'ais_rate_limit_read',
				'type'              => 'number',
				'default'           => '60',
				'css'               => 'width:80px;',
				'custom_attributes' => array( 'min' => '0' ),
				'desc'              => __( '0 = unlimited. Default: 60.', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'             => __( 'Write requests/minute', '211j-ai-shopping-for-woocommerce' ),
				'id'                => 'ais_rate_limit_write',
				'type'              => 'number',
				'default'           => '30',
				'css'               => 'width:80px;',
				'custom_attributes' => array( 'min' => '0' ),
				'desc'              => __( '0 = unlimited. Default: 30.', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ais_rate_limit_options',
			),

			// Webhooks.
			array(
				'title' => __( 'Webhooks', '211j-ai-shopping-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'ais_webhook_options',
			),
			array(
				'title' => __( 'Webhook URL', '211j-ai-shopping-for-woocommerce' ),
				'id'    => 'ais_webhook_url',
				'type'  => 'url',
				'css'   => 'width:400px;',
				'desc'  => __( 'URL to receive order status change events.', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title' => __( 'Webhook Secret', '211j-ai-shopping-for-woocommerce' ),
				'id'    => 'ais_webhook_secret',
				'type'  => 'text',
				'css'   => 'width:400px;',
				'desc'  => __( 'Used to HMAC-sign webhook payloads.', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ais_webhook_options',
			),

			// Security.
			array(
				'title' => __( 'Security', '211j-ai-shopping-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'ais_security_options',
			),
			array(
				'title'   => __( 'Allow HTTP', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_allow_http',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Allow API access over HTTP (for local development).', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Debug Logging', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_logging',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'Log API requests (written to WooCommerce logs).', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ais_security_options',
			),
		);
	}

	/**
	 * Get settings for the Discovery section.
	 *
	 * @return array
	 */
	protected function get_settings_for_discovery_section() {
		return array(
			array(
				'title' => __( 'Discovery Settings', '211j-ai-shopping-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Control which AI discovery mechanisms are active on your store.', '211j-ai-shopping-for-woocommerce' ),
				'id'    => 'ais_discovery_options',
			),
			array(
				'title'   => __( 'Master Toggle', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_discovery',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Enable AI discovery layer (controls all mechanisms below)', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Schema.org Enhancement', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_schema_enhancement',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Enhance product JSON-LD with brand, GTIN, dimensions, reviews, etc.', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Agent Card', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_agent_json',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Serve A2A Agent Card at /.well-known/agent.json', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'LLMs.txt', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_llms_txt',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Serve store overview at /llms.txt', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Product Feed', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_product_feed',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Serve full product catalog at /ai-shopping-feed.json', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Markdown Negotiation', '211j-ai-shopping-for-woocommerce' ),
				'id'      => 'ais_enable_markdown_negotiation',
				'type'    => 'checkbox',
				'default' => 'yes',
				'desc'    => __( 'Serve markdown product data when Accept: text/markdown header is present', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'ais_discovery_options',
			),
		);
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section, $hide_save_button; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core variable.

		switch ( $current_section ) {
			case 'extensions':
				$hide_save_button = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core variable.
				$this->render_extensions_output();
				break;

			case 'endpoints':
				$hide_save_button = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce core variable.
				$this->render_endpoints_output();
				break;

			case 'discovery':
				// Render WC fields first, then append status dashboard.
				parent::output();
				$this->render_discovery_status();
				break;

			default:
				parent::output();
				break;
		}
	}

	/**
	 * Save settings — skip for custom sections.
	 */
	public function save() {
		global $current_section;

		if ( in_array( $current_section, array( 'extensions', 'endpoints' ), true ) ) {
			return;
		}

		parent::save();
	}

	/**
	 * Render the Extensions section.
	 */
	private function render_extensions_output() {
		$results = Extension_Detector::get_scan_results();
		?>
		<h2><?php esc_html_e( 'Extension Compatibility Report', '211j-ai-shopping-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'AI Shopping automatically detects and integrates with these WooCommerce extensions.', '211j-ai-shopping-for-woocommerce' ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Extension', '211j-ai-shopping-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', '211j-ai-shopping-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $results as $slug => $data ) : ?>
					<tr>
						<td><?php echo esc_html( $data['name'] ); ?></td>
						<td>
							<?php if ( $data['active'] ) : ?>
								<span style="color: #00a32a; font-weight: bold;">&#10003; <?php esc_html_e( 'Active — Integrated', '211j-ai-shopping-for-woocommerce' ); ?></span>
							<?php else : ?>
								<span style="color: #888;">&#8212; <?php esc_html_e( 'Not installed', '211j-ai-shopping-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Endpoints section.
	 */
	private function render_endpoints_output() {
		$base = rest_url( 'ai-shopping/v1' );
		?>
		<h2><?php esc_html_e( 'API Endpoints Reference', '211j-ai-shopping-for-woocommerce' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: API base URL */
				esc_html__( 'Base URL: %s', '211j-ai-shopping-for-woocommerce' ),
				'<code>' . esc_url( $base ) . '</code>'
			);
			?>
		</p>

		<h3><?php esc_html_e( 'Core Storefront API', '211j-ai-shopping-for-woocommerce' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Method', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Endpoint', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Description', '211j-ai-shopping-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
				<tr><td><code>GET</code></td><td><code>/products</code></td><td><?php esc_html_e( 'Search and filter products', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/products/{id}</code></td><td><?php esc_html_e( 'Product detail with variations', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/categories</code></td><td><?php esc_html_e( 'Product categories', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/cart</code></td><td><?php esc_html_e( 'Create cart session', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/cart</code></td><td><?php esc_html_e( 'Get cart with totals', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/cart/items</code></td><td><?php esc_html_e( 'Add item to cart', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/checkout/order</code></td><td><?php esc_html_e( 'Place order', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/orders/{id}</code></td><td><?php esc_html_e( 'Order details', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/store</code></td><td><?php esc_html_e( 'Store configuration', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'ACP (Agentic Commerce Protocol)', '211j-ai-shopping-for-woocommerce' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Method', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Endpoint', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Description', '211j-ai-shopping-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
				<tr><td><code>POST</code></td><td><code>/acp/checkout</code></td><td><?php esc_html_e( 'Create ACP checkout', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/acp/checkout/{id}</code></td><td><?php esc_html_e( 'Update checkout', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/acp/checkout/{id}/complete</code></td><td><?php esc_html_e( 'Complete checkout', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>DELETE</code></td><td><code>/acp/checkout/{id}</code></td><td><?php esc_html_e( 'Cancel checkout', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'UCP (Universal Commerce Protocol)', '211j-ai-shopping-for-woocommerce' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Method', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Endpoint', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Description', '211j-ai-shopping-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
				<tr><td></td><td><code>/.well-known/ucp</code></td><td><?php esc_html_e( 'Merchant profile', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/ucp/negotiate</code></td><td><?php esc_html_e( 'Capability negotiation', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>GET</code></td><td><code>/ucp/catalog/search</code></td><td><?php esc_html_e( 'Product search', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/ucp/checkout</code></td><td><?php esc_html_e( 'Create UCP session', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'MCP (Model Context Protocol)', '211j-ai-shopping-for-woocommerce' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Method', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Endpoint', '211j-ai-shopping-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Description', '211j-ai-shopping-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
				<tr><td><code>GET</code></td><td><code>/mcp/tools</code></td><td><?php esc_html_e( 'List available MCP tools', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
				<tr><td><code>POST</code></td><td><code>/mcp/tools/{tool}</code></td><td><?php esc_html_e( 'Execute MCP tool', '211j-ai-shopping-for-woocommerce' ); ?></td></tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Discovery status dashboard (appended below WC fields).
	 */
	private function render_discovery_status() {
		$master_enabled = 'yes' === get_option( 'ais_enable_discovery', 'yes' );

		// Show cache cleared notice.
		if ( isset( $_GET['cache_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Product feed cache cleared.', '211j-ai-shopping-for-woocommerce' );
			echo '</p></div>';
		}

		$mechanisms = array(
			array(
				'name'   => __( 'Schema.org Enhancement', '211j-ai-shopping-for-woocommerce' ),
				'option' => 'ais_enable_schema_enhancement',
				'test'   => null,
				'desc'   => __( 'Enhanced JSON-LD on product pages', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'Agent Card', '211j-ai-shopping-for-woocommerce' ),
				'option' => 'ais_enable_agent_json',
				'test'   => home_url( '/.well-known/agent.json' ),
				'desc'   => __( 'A2A discovery at /.well-known/agent.json', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'LLMs.txt', '211j-ai-shopping-for-woocommerce' ),
				'option' => 'ais_enable_llms_txt',
				'test'   => home_url( '/llms.txt' ),
				'desc'   => __( 'Plain text store overview at /llms.txt', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'Product Feed', '211j-ai-shopping-for-woocommerce' ),
				'option' => 'ais_enable_product_feed',
				'test'   => home_url( '/ai-shopping-feed.json' ),
				'desc'   => __( 'Full product catalog at /ai-shopping-feed.json', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'Markdown Negotiation', '211j-ai-shopping-for-woocommerce' ),
				'option' => 'ais_enable_markdown_negotiation',
				'test'   => null,
				'desc'   => __( 'Serves markdown on product pages when Accept: text/markdown', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'HTTP Headers', '211j-ai-shopping-for-woocommerce' ),
				'option' => null,
				'test'   => null,
				'desc'   => __( 'X-AI-Shopping + Link headers on every response', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'HTML Head Tags', '211j-ai-shopping-for-woocommerce' ),
				'option' => null,
				'test'   => null,
				'desc'   => __( 'link rel="ai-commerce" + meta tags in <head>', '211j-ai-shopping-for-woocommerce' ),
			),
			array(
				'name'   => __( 'robots.txt', '211j-ai-shopping-for-woocommerce' ),
				'option' => null,
				'test'   => home_url( '/robots.txt' ),
				'desc'   => __( 'Allow directives for discovery endpoints', '211j-ai-shopping-for-woocommerce' ),
			),
		);
		?>
		<hr />
		<h2><?php esc_html_e( 'AI Discovery Status', '211j-ai-shopping-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'These mechanisms make your store discoverable by AI agents. All are enabled by default.', '211j-ai-shopping-for-woocommerce' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mechanism', '211j-ai-shopping-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', '211j-ai-shopping-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Description', '211j-ai-shopping-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Test', '211j-ai-shopping-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $mechanisms as $mech ) : ?>
					<?php
					if ( ! $master_enabled ) {
						$status      = false;
						$status_text = __( 'Disabled (master toggle off)', '211j-ai-shopping-for-woocommerce' );
					} elseif ( null === $mech['option'] ) {
						$status      = true;
						$status_text = __( 'Active', '211j-ai-shopping-for-woocommerce' );
					} else {
						$status      = 'yes' === get_option( $mech['option'], 'yes' );
						$status_text = $status ? __( 'Enabled', '211j-ai-shopping-for-woocommerce' ) : __( 'Disabled', '211j-ai-shopping-for-woocommerce' );
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $mech['name'] ); ?></strong></td>
						<td>
							<?php if ( $status ) : ?>
								<span style="color: #00a32a; font-weight: bold;">&#10003; <?php echo esc_html( $status_text ); ?></span>
							<?php else : ?>
								<span style="color: #888;">&#8212; <?php echo esc_html( $status_text ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $mech['desc'] ); ?></td>
						<td>
							<?php if ( $mech['test'] ) : ?>
								<a href="<?php echo esc_url( $mech['test'] ); ?>" target="_blank" class="button button-small">
									<?php esc_html_e( 'Test', '211j-ai-shopping-for-woocommerce' ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<hr />
		<h2><?php esc_html_e( 'Product Feed Cache', '211j-ai-shopping-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'The product feed is cached for 1 hour and automatically refreshed when products are added, updated, or deleted.', '211j-ai-shopping-for-woocommerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ais_clear_feed_cache' ); ?>
			<input type="hidden" name="action" value="ais_clear_feed_cache" />
			<?php submit_button( __( 'Clear Feed Cache Now', '211j-ai-shopping-for-woocommerce' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/**
	 * Render the promotional footer.
	 */
	public function render_promo_footer() {
		?>
		<div style="margin-top: 40px; padding: 20px 24px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap;">
			<div style="flex: 1; min-width: 200px;">
				<p style="margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #999;">Built by</p>
				<p style="margin: 0 0 6px;">
					<a href="https://211j.com" target="_blank" rel="noopener" style="font-weight: 600; color: #1d2327; text-decoration: none; font-size: 14px;">211j</a>
				</p>
				<p style="margin: 0; color: #646970; font-size: 13px;">WordPress &amp; WooCommerce development. We build tools that make stores smarter.</p>
			</div>
			<div style="flex: 1; min-width: 200px;">
				<p style="margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #999;">Also from us</p>
				<p style="margin: 0 0 6px;">
					<a href="https://uphost.ly" target="_blank" rel="noopener" style="font-weight: 600; color: #1d2327; text-decoration: none; font-size: 14px;">Uphost</a>
				</p>
				<p style="margin: 0; color: #646970; font-size: 13px;">Managed WordPress hosting with built-in support. Fast, reliable, human.</p>
			</div>
		</div>
		<?php
	}
}
