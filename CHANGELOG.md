

## Changelog

### 1.1.1 03.18.2026
* Plugin check fixes for WordPress.org submission compliance.
* Version bump for resubmission.

### 1.1.0 03.16.2026
* Updated ACP adapter to spec 2026-01-30: new /checkout_sessions endpoints, POST cancel, API-Version header, buyer/fulfillment_details fields, minor-unit amounts, capability negotiation, discount extension support.
* Updated UCP adapter to spec 2026-01-11: date-based version, structured profile with ucp/payment/merchant objects, /checkout-sessions (hyphenated) endpoints, PUT update, POST cancel, UCP message format with severity.
* Updated MCP adapter to spec 2025-11-25: added title to tool definitions, outputSchema for structured results, proper empty-params handling.
* Legacy 1.0.0 endpoints preserved for backwards compatibility.
* Compatibility: WordPress 6.9, WooCommerce 10.5.

### 1.0.0 02.24.2026
* Initial release.
* Core storefront API: products, cart, checkout, orders, store info.
* ACP protocol adapter (4-endpoint checkout model).
* UCP protocol adapter (merchant profile, capability negotiation, shopping service).
* MCP protocol adapter (tool manifest and execution).
* Headless cart session system with custom DB table.
* API key management with permissions (read / read-write / full).
* Rate limiting with configurable per-key and global defaults.
* Webhook dispatcher for order status events with HMAC signing.
* Extension detector for 16+ WooCommerce extensions.
* Admin settings page with protocol toggles and endpoint reference.
