<?php
/**
 * Agentic Commerce Protocol (ACP) adapter.
 *
 * Updated to ACP spec 2026-01-30:
 * - Endpoints use /checkout_sessions (underscored path).
 * - Cancel uses POST (not DELETE).
 * - API-Version header required.
 * - Capability negotiation in create/update requests and responses.
 * - Amounts in minor units (integer cents).
 * - Buyer object, fulfillment_details, items.id mapping.
 * - Extension and discount support.
 *
 * @package AIShopping\Protocols
 */

namespace AIShopping\Protocols;

defined( 'ABSPATH' ) || exit;

use AIShopping\Api\REST_Controller;
use AIShopping\Cart\Cart_Session;

/**
 * Maps ACP's checkout model to the internal API.
 *
 * ACP endpoints (spec 2026-01-30):
 * - POST   /acp/checkout_sessions                       — Create checkout session
 * - POST   /acp/checkout_sessions/{id}                  — Update checkout session
 * - GET    /acp/checkout_sessions/{id}                  — Get checkout session
 * - POST   /acp/checkout_sessions/{id}/complete         — Complete checkout
 * - POST   /acp/checkout_sessions/{id}/cancel           — Cancel checkout
 *
 * Legacy endpoints (1.0.0) are preserved for backwards compatibility:
 * - POST   /acp/checkout                                — Create (legacy)
 * - POST   /acp/checkout/{id}                           — Update (legacy)
 * - GET    /acp/checkout/{id}                            — Get (legacy)
 * - POST   /acp/checkout/{id}/complete                  — Complete (legacy)
 * - DELETE /acp/checkout/{id}                            — Cancel (legacy)
 */
class ACP_Adapter extends REST_Controller {

	/**
	 * ACP spec version supported.
	 *
	 * @var string
	 */
	const ACP_VERSION = '2026-01-30';

	/**
	 * Register ACP routes.
	 */
	public function register_routes() {
		$ns = $this->namespace;

		// Product discovery (ACP agents need this too).
		register_rest_route(
			$ns,
			'/acp/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// ── Spec-compliant endpoints (2026-01-30) ──

		register_rest_route(
			$ns,
			'/acp/checkout_sessions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_checkout' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/acp/checkout_sessions/(?P<id>[a-zA-Z0-9]+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_checkout' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_checkout' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/acp/checkout_sessions/(?P<id>[a-zA-Z0-9]+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_checkout' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/acp/checkout_sessions/(?P<id>[a-zA-Z0-9]+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_checkout' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
			)
		);

		// ── Legacy endpoints (1.0.0 backwards compat) ──

		register_rest_route(
			$ns,
			'/acp/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_checkout' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/acp/checkout/(?P<id>[a-zA-Z0-9]+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_checkout' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_checkout' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'cancel_checkout' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/acp/checkout/(?P<id>[a-zA-Z0-9]+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_checkout' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
			)
		);
	}

	/**
	 * Search products (ACP wrapper).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function search_products( $request ) {
		$products_api = new \AIShopping\Api\Products();
		$response     = $products_api->list_products( $request );
		return $response;
	}

	/**
	 * Create a new ACP checkout session.
	 *
	 * Accepts product items, optional buyer info, fulfillment details, and capabilities.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function create_checkout( $request ) {
		$key_row = $request->get_param( '_ais_key' );
		$items   = $request->get_param( 'items' );

		if ( empty( $items ) || ! is_array( $items ) ) {
			return $this->error_response(
				'missing_items',
				__( 'Missing required field "items". Provide an array of {id, quantity} or {product_id, quantity} or {sku, quantity}.', 'ai-shopping' ),
				400,
				$request
			);
		}

		// Create cart session.
		$token     = Cart_Session::create( $key_row ? $key_row['id'] : 0 );
		$cart_data = array( 'items' => array(), 'coupons' => array() );

		foreach ( $items as $item ) {
			$product_id   = 0;
			$variation_id = 0;

			// Support ACP spec `id` field, our `product_id`, and `sku`.
			if ( ! empty( $item['id'] ) ) {
				// Try as product ID first, then SKU.
				$test_product = wc_get_product( (int) $item['id'] );
				if ( $test_product ) {
					$product_id = (int) $item['id'];
				} else {
					$product_id = wc_get_product_id_by_sku( sanitize_text_field( $item['id'] ) );
				}
			} elseif ( ! empty( $item['product_id'] ) ) {
				$product_id = (int) $item['product_id'];
			} elseif ( ! empty( $item['sku'] ) ) {
				$product_id = wc_get_product_id_by_sku( sanitize_text_field( $item['sku'] ) );
			}

			if ( ! empty( $item['variation_id'] ) ) {
				$variation_id = (int) $item['variation_id'];
			}

			if ( ! $product_id ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$item_key = md5( $product_id . '-' . $variation_id );

			$cart_data['items'][] = array(
				'_key'         => $item_key,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variation'    => array(),
				'quantity'     => $quantity,
			);
		}

		// Handle fulfillment_details (ACP spec) or billing/shipping_address (legacy).
		$customer = array();
		$fulfillment = $request->get_param( 'fulfillment_details' );
		if ( is_array( $fulfillment ) ) {
			$address = array();
			if ( ! empty( $fulfillment['address'] ) ) {
				$address = $this->map_acp_address( $fulfillment['address'] );
			}
			$customer['shipping_address'] = $address;
			$customer['billing_address']  = $address;
			if ( ! empty( $fulfillment['email'] ) ) {
				$customer['billing_address']['email'] = sanitize_email( $fulfillment['email'] );
			}
			if ( ! empty( $fulfillment['name'] ) ) {
				$parts = explode( ' ', sanitize_text_field( $fulfillment['name'] ), 2 );
				$customer['billing_address']['first_name'] = $parts[0];
				$customer['billing_address']['last_name']  = $parts[1] ?? '';
			}
		}

		// Handle buyer object (ACP spec 2026-01-30).
		$buyer = $request->get_param( 'buyer' );
		if ( is_array( $buyer ) ) {
			if ( ! empty( $buyer['first_name'] ) ) {
				$customer['billing_address']['first_name'] = sanitize_text_field( $buyer['first_name'] );
			}
			if ( ! empty( $buyer['last_name'] ) ) {
				$customer['billing_address']['last_name'] = sanitize_text_field( $buyer['last_name'] );
			}
			if ( ! empty( $buyer['email'] ) ) {
				$customer['billing_address']['email'] = sanitize_email( $buyer['email'] );
			}
			if ( ! empty( $buyer['phone_number'] ) ) {
				$customer['billing_address']['phone'] = sanitize_text_field( $buyer['phone_number'] );
			}
		}

		// Handle discount codes (ACP discount extension).
		$discounts = $request->get_param( 'discounts' );
		if ( is_array( $discounts ) ) {
			foreach ( $discounts as $disc ) {
				$code = $disc['code'] ?? ( is_string( $disc ) ? $disc : '' );
				if ( $code ) {
					$cart_data['coupons'][] = sanitize_text_field( $code );
				}
			}
			$cart_data['coupons'] = array_unique( $cart_data['coupons'] );
		}

		Cart_Session::save( $token, $cart_data );
		if ( ! empty( $customer ) ) {
			Cart_Session::save_customer_data( $token, $customer );
		}

		$calculated = Cart_Session::calculate_totals( $cart_data, $customer );

		// Build capabilities response.
		$capabilities = $this->get_seller_capabilities();

		// Get available payment methods.
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$payment_methods = array();
		foreach ( $gateways as $gateway ) {
			$payment_methods[] = array(
				'id'    => $gateway->id,
				'title' => $gateway->get_title(),
			);
		}

		$response = array(
			'id'                    => $token,
			'status'                => 'open',
			'items'                 => $this->format_acp_items( $calculated['items'] ),
			'subtotal'              => $this->to_minor_units( $calculated['subtotal'] ),
			'tax_total'             => $this->to_minor_units( $calculated['tax_total'] ),
			'shipping_total'        => $this->to_minor_units( $calculated['shipping_total'] ),
			'total'                 => $this->to_minor_units( $calculated['total'] ),
			'currency'              => $calculated['currency'],
			'payment_methods'       => $payment_methods,
			'needs_shipping'        => $calculated['needs_shipping'],
			'fulfillment_options'   => $calculated['needs_shipping'] ? array( array( 'type' => 'shipping' ) ) : array( array( 'type' => 'digital' ) ),
			'capabilities'          => $capabilities,
		);

		$envelope = array(
			'success' => true,
			'data'    => $response,
			'meta'    => $this->get_meta( 'acp' ),
		);

		$rest_response = new \WP_REST_Response( $envelope, 201 );
		$rest_response->header( 'X-Checkout-ID', $token );
		$rest_response->header( 'API-Version', self::ACP_VERSION );
		$this->add_rate_headers( $rest_response, $request );
		return $rest_response;
	}

	/**
	 * Get checkout state.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_checkout( $request ) {
		$session = Cart_Session::load( sanitize_text_field( $request['id'] ) );
		if ( ! $session ) {
			return $this->error_response( 'checkout_not_found', __( 'Checkout session not found or expired.', 'ai-shopping' ), 404, $request );
		}

		$calculated = Cart_Session::calculate_totals( $session['cart_data'], $session['customer_data'] );

		$data = array(
			'id'             => $session['token'],
			'status'         => 'open',
			'items'          => $this->format_acp_items( $calculated['items'] ),
			'subtotal'       => $this->to_minor_units( $calculated['subtotal'] ),
			'tax_total'      => $this->to_minor_units( $calculated['tax_total'] ),
			'shipping_total' => $this->to_minor_units( $calculated['shipping_total'] ),
			'total'          => $this->to_minor_units( $calculated['total'] ),
			'currency'       => $calculated['currency'],
			'buyer'          => $this->format_buyer( $session['customer_data'] ),
			'capabilities'   => $this->get_seller_capabilities(),
		);

		$envelope = array(
			'success' => true,
			'data'    => $data,
			'meta'    => $this->get_meta( 'acp' ),
		);

		$rest_response = new \WP_REST_Response( $envelope, 200 );
		$rest_response->header( 'API-Version', self::ACP_VERSION );
		return $rest_response;
	}

	/**
	 * Update an ACP checkout (items, buyer, fulfillment, discounts).
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function update_checkout( $request ) {
		$token   = sanitize_text_field( $request['id'] );
		$session = Cart_Session::load( $token );

		if ( ! $session ) {
			return $this->error_response( 'checkout_not_found', __( 'Checkout session not found or expired.', 'ai-shopping' ), 404, $request );
		}

		$cart_data = $session['cart_data'];
		$customer  = $session['customer_data'];

		// Update items if provided.
		$items = $request->get_param( 'items' );
		if ( is_array( $items ) ) {
			$cart_data['items'] = array();
			foreach ( $items as $item ) {
				$product_id   = 0;
				$variation_id = (int) ( $item['variation_id'] ?? 0 );

				if ( ! empty( $item['id'] ) ) {
					$test_product = wc_get_product( (int) $item['id'] );
					if ( $test_product ) {
						$product_id = (int) $item['id'];
					} else {
						$product_id = wc_get_product_id_by_sku( sanitize_text_field( $item['id'] ) );
					}
				} elseif ( ! empty( $item['product_id'] ) ) {
					$product_id = (int) $item['product_id'];
				}

				$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );

				if ( $product_id ) {
					$cart_data['items'][] = array(
						'_key'         => md5( $product_id . '-' . $variation_id ),
						'product_id'   => $product_id,
						'variation_id' => $variation_id,
						'variation'    => array(),
						'quantity'     => $quantity,
					);
				}
			}
		}

		// Update fulfillment_details (ACP spec).
		$fulfillment = $request->get_param( 'fulfillment_details' );
		if ( is_array( $fulfillment ) ) {
			if ( ! empty( $fulfillment['address'] ) ) {
				$address = $this->map_acp_address( $fulfillment['address'] );
				$customer['shipping_address'] = $address;
				$customer['billing_address']  = array_merge( $customer['billing_address'] ?? array(), $address );
			}
			if ( ! empty( $fulfillment['email'] ) ) {
				$customer['billing_address']['email'] = sanitize_email( $fulfillment['email'] );
			}
			if ( ! empty( $fulfillment['name'] ) ) {
				$parts = explode( ' ', sanitize_text_field( $fulfillment['name'] ), 2 );
				$customer['billing_address']['first_name'] = $parts[0];
				$customer['billing_address']['last_name']  = $parts[1] ?? '';
			}
		}

		// Update buyer (ACP spec 2026-01-30).
		$buyer = $request->get_param( 'buyer' );
		if ( is_array( $buyer ) ) {
			if ( ! empty( $buyer['first_name'] ) ) {
				$customer['billing_address']['first_name'] = sanitize_text_field( $buyer['first_name'] );
			}
			if ( ! empty( $buyer['last_name'] ) ) {
				$customer['billing_address']['last_name'] = sanitize_text_field( $buyer['last_name'] );
			}
			if ( ! empty( $buyer['email'] ) ) {
				$customer['billing_address']['email'] = sanitize_email( $buyer['email'] );
			}
		}

		// Legacy address fields.
		$billing = $request->get_param( 'billing_address' );
		if ( is_array( $billing ) ) {
			$customer['billing_address'] = array_map( 'sanitize_text_field', $billing );
		}
		$shipping = $request->get_param( 'shipping_address' );
		if ( is_array( $shipping ) ) {
			$customer['shipping_address'] = array_map( 'sanitize_text_field', $shipping );
		}

		// Fulfillment option selection (ACP spec).
		$selected_fulfillment = $request->get_param( 'selected_fulfillment_options' );
		if ( is_array( $selected_fulfillment ) ) {
			foreach ( $selected_fulfillment as $opt ) {
				if ( 'shipping' === ( $opt['type'] ?? '' ) && ! empty( $opt['shipping']['option_id'] ) ) {
					$customer['shipping_method'] = sanitize_text_field( $opt['shipping']['option_id'] );
				}
			}
		}

		// Legacy shipping/payment method.
		$shipping_method = $request->get_param( 'shipping_method' );
		if ( $shipping_method ) {
			$customer['shipping_method'] = sanitize_text_field( $shipping_method );
		}
		$payment_method = $request->get_param( 'payment_method' );
		if ( $payment_method ) {
			$customer['payment_method'] = sanitize_text_field( $payment_method );
		}

		// Discount codes (ACP discount extension or legacy).
		$discounts = $request->get_param( 'discounts' );
		if ( is_array( $discounts ) ) {
			foreach ( $discounts as $disc ) {
				$code = $disc['code'] ?? ( is_string( $disc ) ? $disc : '' );
				if ( $code ) {
					$cart_data['coupons'][] = sanitize_text_field( $code );
				}
			}
			$cart_data['coupons'] = array_unique( $cart_data['coupons'] );
		}
		$discount_code = $request->get_param( 'discount_code' );
		if ( $discount_code ) {
			$cart_data['coupons'][] = sanitize_text_field( $discount_code );
			$cart_data['coupons']   = array_unique( $cart_data['coupons'] );
		}

		Cart_Session::save( $token, $cart_data );
		Cart_Session::save_customer_data( $token, $customer );

		$calculated = Cart_Session::calculate_totals( $cart_data, $customer );

		$data = array(
			'id'             => $token,
			'status'         => 'open',
			'items'          => $this->format_acp_items( $calculated['items'] ),
			'subtotal'       => $this->to_minor_units( $calculated['subtotal'] ),
			'tax_total'      => $this->to_minor_units( $calculated['tax_total'] ),
			'shipping_total' => $this->to_minor_units( $calculated['shipping_total'] ),
			'discount_total' => $this->to_minor_units( $calculated['discount_total'] ),
			'total'          => $this->to_minor_units( $calculated['total'] ),
			'currency'       => $calculated['currency'],
			'buyer'          => $this->format_buyer( $customer ),
			'capabilities'   => $this->get_seller_capabilities(),
		);

		$envelope = array(
			'success' => true,
			'data'    => $data,
			'meta'    => $this->get_meta( 'acp' ),
		);

		$rest_response = new \WP_REST_Response( $envelope, 200 );
		$rest_response->header( 'API-Version', self::ACP_VERSION );
		return $rest_response;
	}

	/**
	 * Complete checkout and place order.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function complete_checkout( $request ) {
		$token   = sanitize_text_field( $request['id'] );
		$session = Cart_Session::load( $token );

		if ( ! $session ) {
			return $this->error_response( 'checkout_not_found', __( 'Checkout session not found or expired.', 'ai-shopping' ), 404, $request );
		}

		$cart_data = $session['cart_data'];
		$customer  = $session['customer_data'];

		if ( empty( $cart_data['items'] ) ) {
			return $this->error_response( 'empty_cart', __( 'Checkout has no items.', 'ai-shopping' ), 400, $request );
		}

		// Accept buyer info at completion (ACP spec).
		$buyer = $request->get_param( 'buyer' );
		if ( is_array( $buyer ) ) {
			if ( ! empty( $buyer['first_name'] ) ) {
				$customer['billing_address']['first_name'] = sanitize_text_field( $buyer['first_name'] );
			}
			if ( ! empty( $buyer['last_name'] ) ) {
				$customer['billing_address']['last_name'] = sanitize_text_field( $buyer['last_name'] );
			}
			if ( ! empty( $buyer['email'] ) ) {
				$customer['billing_address']['email'] = sanitize_email( $buyer['email'] );
			}
			Cart_Session::save_customer_data( $token, $customer );
		}

		// Calculate final totals.
		$calculated = Cart_Session::calculate_totals( $cart_data, $customer );

		// Create WooCommerce order.
		$order = wc_create_order();

		foreach ( $calculated['items'] as $item ) {
			$product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
			if ( $product ) {
				$order->add_product( $product, $item['quantity'] );
			}
		}

		if ( ! empty( $customer['billing_address'] ) ) {
			$order->set_address( $customer['billing_address'], 'billing' );
		}
		if ( ! empty( $customer['shipping_address'] ) ) {
			$order->set_address( $customer['shipping_address'], 'shipping' );
		}

		// Payment: accept payment_data (ACP spec) or payment_method (legacy).
		$payment_data = $request->get_param( 'payment_data' );
		if ( is_array( $payment_data ) && ! empty( $payment_data['provider'] ) ) {
			$order->set_payment_method( sanitize_text_field( $payment_data['provider'] ) );
		} else {
			$payment_method = $request->get_param( 'payment_method' ) ?: ( $customer['payment_method'] ?? '' );
			if ( $payment_method ) {
				$order->set_payment_method( sanitize_text_field( $payment_method ) );
			}
		}

		if ( ! empty( $cart_data['coupons'] ) ) {
			foreach ( $cart_data['coupons'] as $code ) {
				$order->apply_coupon( $code );
			}
		}

		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->add_order_note( __( 'Order placed via ACP (Agentic Commerce Protocol).', 'ai-shopping' ) );
		$order->save();

		// Clean up checkout session.
		Cart_Session::delete( $token );

		$data = array(
			'id'           => $token,
			'status'       => 'completed',
			'order'        => array(
				'id'         => $order->get_id(),
				'order_key'  => $order->get_order_key(),
				'status'     => $order->get_status(),
				'total'      => $this->to_minor_units( (float) $order->get_total() ),
				'currency'   => $order->get_currency(),
				'created_at' => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			),
		);

		$envelope = array(
			'success' => true,
			'data'    => $data,
			'meta'    => $this->get_meta( 'acp' ),
		);

		$rest_response = new \WP_REST_Response( $envelope, 200 );
		$rest_response->header( 'API-Version', self::ACP_VERSION );
		return $rest_response;
	}

	/**
	 * Cancel a checkout.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function cancel_checkout( $request ) {
		$token   = sanitize_text_field( $request['id'] );
		$session = Cart_Session::load( $token );

		if ( ! $session ) {
			return $this->error_response( 'checkout_not_found', __( 'Checkout session not found or expired.', 'ai-shopping' ), 404, $request );
		}

		Cart_Session::delete( $token );

		$envelope = array(
			'success' => true,
			'data'    => array(
				'id'      => $token,
				'status'  => 'canceled',
				'message' => __( 'Checkout canceled and cart released.', 'ai-shopping' ),
			),
			'meta'    => $this->get_meta( 'acp' ),
		);

		$rest_response = new \WP_REST_Response( $envelope, 200 );
		$rest_response->header( 'API-Version', self::ACP_VERSION );
		return $rest_response;
	}

	/**
	 * Get seller capabilities for ACP responses.
	 *
	 * @return array
	 */
	private function get_seller_capabilities() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$payment_handlers = array();
		foreach ( $gateways as $gateway ) {
			$payment_handlers[] = array(
				'id'   => $gateway->id,
				'name' => $gateway->get_title(),
			);
		}

		$extensions = array( 'discount' );

		return array(
			'payment'    => array( 'handlers' => $payment_handlers ),
			'extensions' => $extensions,
		);
	}

	/**
	 * Map ACP address format to WooCommerce address fields.
	 *
	 * @param array $acp_address ACP address object.
	 * @return array WooCommerce address fields.
	 */
	private function map_acp_address( $acp_address ) {
		return array(
			'first_name' => sanitize_text_field( $acp_address['name'] ?? '' ),
			'address_1'  => sanitize_text_field( $acp_address['line_one'] ?? '' ),
			'address_2'  => sanitize_text_field( $acp_address['line_two'] ?? '' ),
			'city'       => sanitize_text_field( $acp_address['city'] ?? '' ),
			'state'      => sanitize_text_field( $acp_address['state'] ?? '' ),
			'postcode'   => sanitize_text_field( $acp_address['postal_code'] ?? '' ),
			'country'    => sanitize_text_field( $acp_address['country'] ?? '' ),
		);
	}

	/**
	 * Convert a float dollar amount to minor units (integer cents).
	 *
	 * @param float $amount Amount in major currency units.
	 * @return int Amount in minor units.
	 */
	private function to_minor_units( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return (int) round( (float) $amount * pow( 10, $decimals ) );
	}

	/**
	 * Format cart items for ACP response.
	 *
	 * @param array $items Calculated cart items.
	 * @return array ACP-formatted items.
	 */
	private function format_acp_items( $items ) {
		$formatted = array();
		foreach ( $items as $item ) {
			$product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
			$formatted[] = array(
				'id'          => (string) ( $item['variation_id'] ?: $item['product_id'] ),
				'name'        => $product ? $product->get_name() : '',
				'unit_amount' => $product ? $this->to_minor_units( (float) $product->get_price() ) : 0,
				'quantity'    => $item['quantity'],
				'product_id'  => $item['product_id'],
			);
		}
		return $formatted;
	}

	/**
	 * Format customer data as ACP buyer object.
	 *
	 * @param array $customer Customer data.
	 * @return array|null ACP buyer object or null.
	 */
	private function format_buyer( $customer ) {
		if ( empty( $customer['billing_address'] ) ) {
			return null;
		}
		$billing = $customer['billing_address'];
		$buyer   = array();
		if ( ! empty( $billing['first_name'] ) ) {
			$buyer['first_name'] = $billing['first_name'];
		}
		if ( ! empty( $billing['last_name'] ) ) {
			$buyer['last_name'] = $billing['last_name'];
		}
		if ( ! empty( $billing['email'] ) ) {
			$buyer['email'] = $billing['email'];
		}
		if ( ! empty( $billing['phone'] ) ) {
			$buyer['phone_number'] = $billing['phone'];
		}
		return ! empty( $buyer ) ? $buyer : null;
	}
}
