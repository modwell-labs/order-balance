# Order Balance

Collect the difference when a paid WooCommerce order changes, refund overpayments, and absorb small rounding differences.

When you edit an order that has already been paid, WooCommerce asks the customer to pay the full order total again. Order Balance tracks what the customer has paid and what the order costs now, and lets you settle the difference with a linked balance payment.

- Balance panel on the order screen: order total, paid, balance due or overpaid.
- Request the difference with a pay link, or record payments received outside the website.
- Refund the difference through the original payment method when the order total goes down.
- Payment method fees and surcharges on a balance payment never count toward the order.
- Works with High-Performance Order Storage (HPOS) and legacy order storage.

See [readme.txt](readme.txt) for the full description and FAQ.

## Requirements

- WordPress 6.4 or later
- WooCommerce 8.0 or later
- PHP 7.4 or later

## Development

Smoke test, run from the WordPress root of a test site:

```
wp eval-file wp-content/plugins/order-balance/tests/smoke.php
```

## License

GPLv2 or later.
