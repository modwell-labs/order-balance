# Modwell Order Balance for WooCommerce

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

Tests run with WP-CLI from the WordPress root of a test site:

```
wp eval-file wp-content/plugins/modwell-order-balance-for-woocommerce/tests/smoke.php [group...]
wp eval-file wp-content/plugins/modwell-order-balance-for-woocommerce/tests/uninstall.php
```

`smoke.php` runs the groups in `tests/groups/`, each independent of the others: `core` (balance, payments, refunds, statuses, emails, rounding), `admin` (payment screen, settings, setup wizard), `later` (pay-later payment methods), `fees` (payment method fees, order change fee, tax) `changes` (the order changes again while a payment is waiting) and `compat` (payment plugins and other plugins finding balance payments; checks for a plugin run only when it is active). With no argument it runs all of them. `uninstall.php` deletes the plugin's data and is destructive on the test site.

Both tests set the plugin to its default settings and put the saved ones back afterwards.

## License

GPLv2 or later.
