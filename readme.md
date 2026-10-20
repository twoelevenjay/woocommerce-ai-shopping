# 211j AI Shopping for WooCommerce

**Contributors:**         twoelevenjay
**Donate link:**          https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=leon%40211j%2ecom&lc=MQ&item_name=Two%20Eleven%20Jay&no_note=0&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHostedGuest
**Tags:**                 woocommerce, ai, api, mcp, agentic-commerce
**Requires at least:**    6.4
**Tested up to:**         7.1
**Requires PHP:**         7.4
**Stable tag:**           1.2.0
**License:**              GPLv2 or later
**License URI:**          http://www.gnu.org/licenses/gpl-2.0.html

Instantly expose your WooCommerce storefront to AI agents via ACP, UCP, and MCP protocols.

## Description

211j AI Shopping for WooCommerce lets any AI agent (ChatGPT, Gemini, Claude, Copilot, or a custom agent) discover products, build carts, negotiate checkout, and track orders on your WooCommerce store without ever rendering a browser page.

**Minimal setup.** Activate the plugin and the catalog is readable by AI agents right away. Agents that build carts and place orders sign in as a WordPress user with an Application Password.

211j AI Shopping for WooCommerce requires the most current version of WooCommerce. You can find that [here](https://wordpress.org/plugins/woocommerce/).

### Three Protocols, One Plugin

* **Agentic Commerce Protocol (ACP):** the OpenAI / Stripe standard for AI-powered checkout (spec 2026-01-30). Supports capability negotiation, extensions, discounts, payment handlers, and amounts in minor units.
* **Universal Commerce Protocol (UCP):** the Shopify / Google standard with `/.well-known/ucp` discovery and capability negotiation (spec 2026-01-11). Supports structured capabilities, cancel endpoint, and UCP message format.
* **Model Context Protocol (MCP):** the Anthropic standard for tool-based AI interaction (spec 2025-11-25). Includes `title`, `outputSchema`, and proper empty-params handling.

### Features:

* Full product catalog API with search, filtering by category, price range, attributes, stock status, sale status, and more.
* Headless cart management with token-based sessions, no browser cookies required.
* Complete checkout flow: set addresses, choose shipping, select payment method, place order.
* Order tracking and customer account endpoints.
* Webhook notifications for order status changes with HMAC-signed payloads.
* Rate limiting with standard HTTP headers.
* Auto-detection and integration for 16+ popular WooCommerce extensions.
* Settings tab under WooCommerce > Settings with protocol toggles, discovery controls, and a full endpoint reference.

### Security Model

* Catalog and store endpoints (products, categories, reviews, store info, payment method list) are public, like the storefront.
* Creating a cart or checkout session requires a WordPress user authenticated with an [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/).
* Every cart, ACP checkout session and UCP checkout session belongs to the user who created it. Only that user (or a shop manager) can read, change, complete or cancel it.
* Orders can be read with their order key, by their customer, by the user who placed them, or by a shop manager.
* Account endpoints only return the signed-in user's own account. Reading another customer requires the `manage_woocommerce` capability.

### Extension Compatibility

When detected, 211j AI Shopping automatically extends the API for:

* WooCommerce Subscriptions
* WooCommerce Product Bundles
* WooCommerce Composite Products
* WooCommerce Product Add-Ons
* WooCommerce Memberships
* WooCommerce Bookings
* WooCommerce Mix and Match Products
* WooCommerce Points and Rewards
* WooCommerce Gift Cards
* WooCommerce Stripe Gateway
* WooCommerce PayPal Payments
* WPML WooCommerce Multilingual
* Advanced Custom Fields (ACF)
* YITH WooCommerce Wishlist
* WooCommerce Dynamic Pricing
* All Products for WooCommerce Subscriptions

### External Services

This plugin does not contact any external service on its own. If the store administrator enters a Webhook URL in the settings, order status changes (event name, order ID, old and new status, total, currency and a timestamp) are sent to that URL, signed with the configured webhook secret. Nothing is sent when the field is empty.

### 3rd Party Resources

* [WooCommerce](https://wordpress.org/plugins/woocommerce/) from [Automattic](https://automattic.com/).
* [ACP Spec](https://agenticcommerce.dev) (2026-01-30) from OpenAI and Stripe.
* [UCP Spec](https://ucp.dev) (2026-01-11) from Shopify and Google.
* [MCP Spec](https://modelcontextprotocol.io/specification/2025-11-25) (2025-11-25) from Anthropic.

### Contribution

All contributions welcome. Please open an issue or pull request on the [GitHub repository](https://github.com/twoelevenjay/woocommerce-ai-shopping).

## Installation

1. Extract the .zip file for this plugin and upload its contents to the `/wp-content/plugins/` directory. Alternatively, you can install directly from the Plugin directory within your WordPress Install.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Review the settings under **WooCommerce > Settings > 211j AI Shopping**.
4. For agents that build carts and place orders, create a WordPress user for the agent and an Application Password under **Users > Profile > Application Passwords**. The agent sends it with HTTP Basic authentication over HTTPS.

## Frequently Asked Questions

**Q: Do I need to configure anything?**

**A: No. Catalog discovery works on activation. Cart and checkout need an Application Password for the agent's WordPress user.**

**Q: Which AI agents work with this plugin?**

**A: Any AI agent that can make HTTP requests. The plugin supports ACP (OpenAI/Stripe), UCP (Shopify/Google), and MCP (Anthropic).**

**Q: Is HTTPS required?**

**A: Yes, by default. For local development you can allow plain HTTP in the settings.**

**Q: Does the plugin add any credit or link to my storefront?**

**A: No. Nothing is added to the public site that credits the plugin or its authors.**

**Q: Does this plugin process payments?**

**A: The plugin uses WooCommerce's payment gateway infrastructure. It does not process payments directly but passes payment tokens to your configured payment gateways.**

FAQ's usually end up in the [github wiki](https://github.com/twoelevenjay/woocommerce-ai-shopping/wiki).
