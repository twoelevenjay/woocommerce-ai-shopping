<?php
/**
 * Account REST API endpoint.
 *
 * @package TwoElevenJay\AIShopping\Api
 */

namespace TwoElevenJay\AIShopping\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Customer account endpoints: profile, orders, addresses, payment methods.
 */
class Account extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$ns = $this->namespace;

		register_rest_route(
			$ns,
			'/account',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_account' ),
				'permission_callback' => array( $this, 'check_account_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_orders' ),
				'permission_callback' => array( $this, 'check_account_permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/account/addresses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_addresses' ),
				'permission_callback' => array( $this, 'check_account_permission' ),
			)
		);
	}

	/**
	 * Get customer profile.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_account( $request ) {
		$customer = new \WC_Customer( $this->get_customer_id( $request ) );
		if ( ! $customer->get_id() ) {
			return $this->error_response( 'customer_not_found', __( 'Customer not found.', '211j-ai-shopping-for-woocommerce' ), 404, $request );
		}

		return $this->success(
			array(
				'id'         => $customer->get_id(),
				'email'      => $customer->get_email(),
				'first_name' => $customer->get_first_name(),
				'last_name'  => $customer->get_last_name(),
				'username'   => $customer->get_username(),
				'date_created' => $customer->get_date_created() ? $customer->get_date_created()->date( 'c' ) : null,
				'orders_count' => $customer->get_order_count(),
				'total_spent'  => (float) $customer->get_total_spent(),
			),
			$request
		);
	}

	/**
	 * Get customer orders.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_orders( $request ) {
		$customer_id = $this->get_customer_id( $request );

		$pagination = $this->get_pagination( $request );

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'limit'       => $pagination['per_page'],
				'page'        => $pagination['page'],
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$data = array();
		foreach ( $orders as $order ) {
			$data[] = array(
				'id'           => $order->get_id(),
				'status'       => $order->get_status(),
				'total'        => (float) $order->get_total(),
				'currency'     => $order->get_currency(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
				'item_count'   => $order->get_item_count(),
			);
		}

		return $this->success( array( 'orders' => $data ), $request );
	}

	/**
	 * Get customer addresses.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_addresses( $request ) {
		$customer_id = $this->get_customer_id( $request );

		$customer = new \WC_Customer( $customer_id );
		if ( ! $customer->get_id() ) {
			return $this->error_response( 'customer_not_found', __( 'Customer not found.', '211j-ai-shopping-for-woocommerce' ), 404, $request );
		}

		return $this->success(
			array(
				'billing'  => array(
					'first_name' => $customer->get_billing_first_name(),
					'last_name'  => $customer->get_billing_last_name(),
					'company'    => $customer->get_billing_company(),
					'address_1'  => $customer->get_billing_address_1(),
					'address_2'  => $customer->get_billing_address_2(),
					'city'       => $customer->get_billing_city(),
					'state'      => $customer->get_billing_state(),
					'postcode'   => $customer->get_billing_postcode(),
					'country'    => $customer->get_billing_country(),
					'email'      => $customer->get_billing_email(),
					'phone'      => $customer->get_billing_phone(),
				),
				'shipping' => array(
					'first_name' => $customer->get_shipping_first_name(),
					'last_name'  => $customer->get_shipping_last_name(),
					'company'    => $customer->get_shipping_company(),
					'address_1'  => $customer->get_shipping_address_1(),
					'address_2'  => $customer->get_shipping_address_2(),
					'city'       => $customer->get_shipping_city(),
					'state'      => $customer->get_shipping_state(),
					'postcode'   => $customer->get_shipping_postcode(),
					'country'    => $customer->get_shipping_country(),
				),
			),
			$request
		);
	}

	/**
	 * Resolve the customer for this request.
	 *
	 * Defaults to the authenticated user. check_account_permission() has already
	 * limited a different customer_id to shop managers.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return int
	 */
	private function get_customer_id( $request ) {
		$customer_id = (int) $request->get_param( 'customer_id' );
		return $customer_id ? $customer_id : get_current_user_id();
	}
}
