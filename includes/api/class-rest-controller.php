<?php
/**
 * Base REST controller with rate limiting and error formatting.
 *
 * Catalog and store reads are open (like a storefront). Starting a session
 * requires an authenticated user, and every route that touches a session,
 * an order or an account checks that the user may access it. Rate limiting is per-IP.
 *
 * @package TwoElevenJay\AIShopping\Api
 */

namespace TwoElevenJay\AIShopping\Api;

defined( 'ABSPATH' ) || exit;

use TwoElevenJay\AIShopping\Cart\Cart_Session;
use TwoElevenJay\AIShopping\Security\Rate_Limiter;

/**
 * Abstract base for all AI Shopping REST controllers.
 */
abstract class REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'twoelevenjay-ais/v1';

	/**
	 * Rate limit results per request object.
	 *
	 * WordPress runs every handler's permission callback on a route to build the
	 * Allow header, so the limiter must only be charged once per request. Kept
	 * out of the request params so a client cannot supply its own value.
	 *
	 * @var array<string, array>
	 */
	private static $rate_results = array();

	/**
	 * Register routes (implemented by each controller).
	 */
	abstract public function register_routes();

	/**
	 * Permission check for public catalog and store data: open to everyone, rate limited.
	 *
	 * Never use this for customer, cart, checkout or order data.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public function check_read_permission( $request ) {
		return $this->check_permission( $request, 'read', false );
	}

	/**
	 * Permission check for starting a new cart or checkout session: requires an authenticated user.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public function check_write_permission( $request ) {
		return $this->check_permission( $request, 'write', true );
	}

	/**
	 * Permission check for routes that act on an existing cart or checkout session.
	 *
	 * The session token (route "id", X-Cart-Token header or cart_token param) must
	 * resolve to an unexpired session owned by the authenticated user. Shop
	 * managers may act on any session.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public function check_session_permission( $request ) {
		$operation = 'GET' === $request->get_method() ? 'read' : 'write';
		$allowed   = $this->check_permission( $request, $operation, true );
		if ( true !== $allowed ) {
			return $allowed;
		}

		$token = isset( $request['id'] ) ? sanitize_text_field( $request['id'] ) : Cart_Session::get_token_from_request( $request );
		if ( ! $token ) {
			return $this->error(
				'missing_cart_token',
				__( 'Missing X-Cart-Token header. Create a cart first with POST /cart.', '211j-ai-shopping-for-woocommerce' ),
				400
			);
		}

		$session = Cart_Session::load( $token );
		if ( ! $session ) {
			return $this->error(
				'session_not_found',
				__( 'Cart or checkout session not found or expired.', '211j-ai-shopping-for-woocommerce' ),
				404
			);
		}

		if ( ! Cart_Session::current_user_can_access( $session ) ) {
			return $this->error(
				'forbidden',
				__( 'This session belongs to another user.', '211j-ai-shopping-for-woocommerce' ),
				403
			);
		}

		return true;
	}

	/**
	 * Permission check for order routes.
	 *
	 * Allowed with the order key (as returned when the order was placed), or for
	 * the authenticated user who placed or owns the order, or for shop managers.
	 * Writes always require an authenticated user.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public function check_order_permission( $request ) {
		$is_write = 'GET' !== $request->get_method();
		$allowed  = $this->check_permission( $request, $is_write ? 'write' : 'read', $is_write );
		if ( true !== $allowed ) {
			return $allowed;
		}

		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order || ! Orders::current_user_can_view( $order, (string) $request->get_param( 'order_key' ) ) ) {
			// Same answer for "missing" and "not yours" so order ids cannot be probed.
			return $this->error( 'order_not_found', __( 'Order not found.', '211j-ai-shopping-for-woocommerce' ), 404 );
		}

		return true;
	}

	/**
	 * Permission check for customer account routes.
	 *
	 * Requires an authenticated user. A customer_id other than the current user
	 * requires the manage_woocommerce capability.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public function check_account_permission( $request ) {
		$allowed = $this->check_permission( $request, 'read', true );
		if ( true !== $allowed ) {
			return $allowed;
		}

		$customer_id = (int) $request->get_param( 'customer_id' );
		if ( $customer_id && get_current_user_id() !== $customer_id && ! current_user_can( 'manage_woocommerce' ) ) {
			return $this->error(
				'forbidden',
				__( 'You can only read your own account.', '211j-ai-shopping-for-woocommerce' ),
				403
			);
		}

		return true;
	}

	/**
	 * Core permission and rate limit check.
	 *
	 * @param \WP_REST_Request $request      The request.
	 * @param string           $operation    'read' or 'write' (selects the rate limit bucket).
	 * @param bool             $require_auth Whether an authenticated WordPress user is required.
	 * @return true|\WP_Error
	 */
	protected function check_permission( $request, $operation = 'read', $require_auth = false ) {
		// Allow HTTP in local dev if configured.
		if ( 'yes' !== get_option( 'twoelevenjay_ais_allow_http', 'no' ) && ! is_ssl() ) {
			return $this->error( 'https_required', __( 'HTTPS is required for API access.', '211j-ai-shopping-for-woocommerce' ), 403 );
		}

		if ( $require_auth && ! is_user_logged_in() ) {
			return $this->error(
				'unauthorized',
				__( 'Authentication required. Send WordPress Application Password credentials to perform this action.', '211j-ai-shopping-for-woocommerce' ),
				401
			);
		}

		// Rate limit check (by IP), once per request.
		$request_key = spl_object_hash( $request );
		if ( ! isset( self::$rate_results[ $request_key ] ) ) {
			self::$rate_results[ $request_key ] = Rate_Limiter::check( $operation );
		}
		$rate = self::$rate_results[ $request_key ];
		if ( ! $rate['allowed'] ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please wait and try again.', '211j-ai-shopping-for-woocommerce' ),
				array(
					'status'      => 429,
					'retry_after' => max( 1, $rate['reset'] - time() ),
				)
			);
		}

		return true;
	}

	/**
	 * Build a success response.
	 *
	 * @param mixed            $data    Response data.
	 * @param \WP_REST_Request $request The request (for rate limit headers).
	 * @param int              $status  HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success( $data, $request = null, $status = 200 ) {
		$envelope = array(
			'success' => true,
			'data'    => $data,
			'meta'    => $this->get_meta(),
		);

		$response = new \WP_REST_Response( $envelope, $status );
		$this->add_rate_headers( $response, $request );
		return $response;
	}

	/**
	 * Build an error WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error
	 */
	protected function error( $code, $message, $status = 400 ) {
		return new \WP_Error(
			$code,
			$message,
			array( 'status' => $status )
		);
	}

	/**
	 * Build a formatted error response (for direct return).
	 *
	 * @param string           $code    Error code.
	 * @param string           $message Human-readable message.
	 * @param int              $status  HTTP status.
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	protected function error_response( $code, $message, $status = 400, $request = null ) {
		$envelope = array(
			'success' => false,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
				'status'  => $status,
			),
		);

		$response = new \WP_REST_Response( $envelope, $status );
		$this->add_rate_headers( $response, $request );
		return $response;
	}

	/**
	 * Get standard response meta.
	 *
	 * @param string $protocol Override protocol identifier.
	 * @return array
	 */
	protected function get_meta( $protocol = 'rest' ) {
		return array(
			'protocol'  => $protocol,
			'version'   => TWOELEVENJAY_AIS_VERSION,
			'store'     => get_bloginfo( 'name' ),
			'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'timestamp' => gmdate( 'c' ),
		);
	}

	/**
	 * Add rate limit headers to response.
	 *
	 * @param \WP_REST_Response     $response The response.
	 * @param \WP_REST_Request|null $request  The request.
	 */
	protected function add_rate_headers( $response, $request = null ) {
		if ( ! $request ) {
			return;
		}

		$rate = self::$rate_results[ spl_object_hash( $request ) ] ?? null;
		if ( $rate && ! empty( $rate['limit'] ) ) {
			$response->header( 'X-RateLimit-Limit', $rate['limit'] );
			$response->header( 'X-RateLimit-Remaining', $rate['remaining'] );
			$response->header( 'X-RateLimit-Reset', $rate['reset'] );
		}
	}

	/**
	 * Parse pagination params from request.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array{page: int, per_page: int}
	 */
	protected function get_pagination( $request ) {
		return array(
			'page'     => max( 1, (int) $request->get_param( 'page' ) ?: 1 ),
			'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) ),
		);
	}
}
