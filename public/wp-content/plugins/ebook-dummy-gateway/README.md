# eBook Dummy Payment Gateway (TEST ONLY)

A fake WooCommerce payment method for testing the eBook Store. **No money is ever charged.**

At checkout the customer picks:

- **Simulate successful payment** → the order is paid (`$order->payment_complete()`), it completes automatically (virtual + downloadable eBooks), download access is granted and the normal WooCommerce emails are sent — exactly what happens with a real gateway.
- **Simulate failed payment** → the order is marked **Failed**, an error is shown, and the customer gets **no download access and no download link**. The next attempt creates a new order, so the failed one stays on record.

## On / off

| Where | Setting |
|-------|---------|
| `.env` (main switch) | `DUMMY_GATEWAY_ENABLED=true` → available · `false` → the gateway does not exist at all (not at checkout, not in settings) |
| Admin | WooCommerce → Settings → Payments → *eBook Dummy Payment Gateway (TEST ONLY)* → Enable/Disable, title, description |

While it is active, every admin screen shows: *"Dummy payment gateway is active — disable before going live."*

## Removing it (when the real gateway is installed)

Nothing else in the project depends on this plugin.

1. Install and test the real payment gateway (e.g. WooPayments, Stripe, PayPal).
2. Set `DUMMY_GATEWAY_ENABLED=false` in `.env` (the gateway disappears immediately).
3. Optional, recommended: **Plugins → eBook Dummy Payment Gateway (TEST ONLY) → Deactivate → Delete**
   (or delete the folder `wp-content/plugins/ebook-dummy-gateway/`).

Past test orders keep their data; they simply show "Test payment" as the payment method.

## How the real gateway takes over

eBook delivery does not depend on this plugin. It relies on standard WooCommerce behaviour:

1. A gateway confirms the payment by calling `$order->payment_complete()`.
2. WooCommerce sets virtual + downloadable orders to **Completed**, grants download permissions ("Grant access to downloadable products after payment") and sends the customer the order email with download links.

Any real gateway that calls `payment_complete()` (all mainstream gateways do) keeps exactly the same delivery flow.

## Technical notes

- Extends `WC_Payment_Gateway`, classic checkout (`[woocommerce_checkout]`) only.
- Declares HPOS (`custom_order_tables`) compatibility; not integrated with Cart/Checkout Blocks.
- Reads `DUMMY_GATEWAY_ENABLED` via the project's `env()` helper (falls back to `getenv()`).
- Safety guard: when `APP_ENV=production` the gateway is offered (and processes payments) only for logged-in staff with `manage_woocommerce`, so customers can never use it even if the switch is left on.
