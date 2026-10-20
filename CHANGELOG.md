

## Changelog

### 1.2.0 10.20.2026
* Security: every cart, ACP checkout and UCP checkout route now checks that the session belongs to the signed-in user (shop managers excepted). Creating a session requires a signed-in user.
* Security: order routes require the order key, the order's customer, the user who placed it, or a shop manager. Account routes require login and only return the user's own account.
* Security: MCP cart and order tools apply the same ownership checks.
* Security: discovery markdown and llms.txt are escaped at output; JSON documents use wp_send_json(); JSON-LD cannot be closed early by store data.
* Security: the rate limiter no longer trusts X-Forwarded-For unless the twoelevenjay_ais_trust_proxy_headers filter allows it, and no longer fails with a fatal error when the limit is reached. Plain HTTP API access is off by default.
* Removed the "Powered by" line from the public /llms.txt.
* Breaking: all functions, constants, options, hooks, filters, tables and the PHP namespace use one prefix (twoelevenjay_ais_, TWOELEVENJAY_AIS_, TwoElevenJay\AIShopping). The REST namespace is now twoelevenjay-ais/v1. Settings from 1.1.x are migrated automatically. Filters renamed from ai_shopping_* to twoelevenjay_ais_*.
* Discovery endpoints (/.well-known/agent.json, /llms.txt, /ai-shopping-feed.json, /.well-known/ucp) resolve when rewrite rules were not flushed, are flushed once per plugin version, and skip the trailing-slash redirect.
* Fixed the Discovery settings redirect after clearing the feed cache.
* Plugin and Author URIs corrected. Documentation describes Application Password authentication instead of the API keys removed in 1.1.0.
* Compatibility: WordPress 7.1, WooCommerce 11.2.

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
