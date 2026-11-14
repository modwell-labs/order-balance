=== Modwell Order Balance for WooCommerce ===
Contributors: modwelllabs
Tags: woocommerce, order edit, additional payment, balance due, refund difference
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect the difference when a paid WooCommerce order changes, refund overpayments, and absorb small rounding differences.

== Description ==

When you edit an order that has already been paid, WooCommerce asks the customer to pay the full order total again. Order Balance tracks what the customer has paid and what the order costs now, and lets you settle the difference.

**What you get**

* A Balance panel on the order screen: order total, paid, and balance due or overpaid.
* Payment method rules: choose which methods count as paid at checkout and which are paid later (cash on delivery, bank transfer, cheque, orders created in the admin). Pay-later orders show the whole total as due until it is paid.
* Request the difference: creates a linked balance payment and emails the customer a pay link. The customer pays it with any payment method of your store.
* While a payment is waiting, further changes to the order update that payment with one click, so the customer keeps one link and pays once. If the order no longer needs the payment, it is cancelled instead.
* Mark a balance payment as paid when the customer pays it outside the website, such as in cash or by bank transfer. The same payment is completed and its pay link closes.
* Refund the difference through the original payment method when the order total goes down.
* Rounding adjustments: absorb a small difference (for example from tax rounding) with one click instead of asking for a payment or a refund. Automatic rounding is available and is off by default.
* A "Partially paid" order status while a balance payment is outstanding.
* Customers see their balance and a Pay now button in My Account.
* An "Order updated" email shows the updated order, what has been paid and what is due, in one message. You choose which emails are sent: order updated, payment received (customer and store), payment cancelled.
* Each balance payment has its own screen laid out like a WooCommerce order: details, lines, totals, refunds, actions and notes.
* A setup wizard on first activation walks through every setting in about two minutes.
* Works with High-Performance Order Storage (HPOS) and with legacy order storage.

**Good to know**

* Balance payments are separate orders of their own type, so they stay out of the orders list and of sales reports.
* The plugin makes no external requests, loads no third-party services and collects no data.

A Pro version is in development: [modwelllabs.com/order-balance](https://modwelllabs.com/order-balance/).

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin to the `/wp-content/plugins/` directory, or install it from the Plugins screen, and activate it.
3. Follow the setup wizard, or skip it and use Order Balance > Settings at any time.
4. Edit a paid order. The Balance panel on the order screen shows what is due.

== Frequently Asked Questions ==

= Does it change the checkout? =

Not the checkout itself. Order Balance works after checkout and does not change how orders are placed or which payment methods are offered. On the "Pay for order" page of a balance payment it adds a safeguard: if the amount changes while the customer has the page open, the payment is stopped and the page reloads with the new amount, so nobody is charged an amount they did not see. This also covers express payment buttons that use the WooCommerce Store API.

= Which emails does it send? =

Order updated, payment received (to the customer and to the store) and payment cancelled. Balance payments are orders to WooCommerce, so the standard WooCommerce order emails (new order, processing, completed, refunded and so on) are switched off for them; Order Balance sends its own instead. The emails of your normal orders are not affected. You can turn each Order Balance email on or off under Order Balance > Emails.

= Where are balance payments stored? =

Each balance payment is a separate order of its own type, linked to the original order. It does not appear in the orders list or in sales reports, so revenue is not counted twice.

= What happens with payment method fees and surcharges? =

If a payment method adds a fee or surcharge to a balance payment, only the requested amount counts toward the order. The fee never makes the order look overpaid. By default a refund returns a matching share of the fee, as card network rules usually require for card surcharges. You can turn this off under Order Balance > Settings > Fees, and refunds are then limited to the requested amount. Check the rules for your country and payment provider before you charge or keep fees.

= How is tax handled on balance payments? =

Tax is calculated on the order itself. A balance payment pays part of that total, so it carries no tax of its own and tax is not charged twice. If the customer pays a balance payment with store credit or a wallet whose top-ups were already taxed, tax on that part has been collected twice. Ask your accountant how to treat these cases.

= What if the order changes again before the customer pays? =

The Balance panel shows how the waiting payment would change, for example from 12.00 to 30.00, and updates it with one click. The customer keeps the same pay link. If the order no longer needs the payment, it is cancelled instead.

= What happens to my data when I delete the plugin? =

By default balance payments and settings are kept, so you can install the plugin again without losing anything. Orders that are "Partially paid" are moved to "On hold" so they stay visible. To remove everything, including balance payments with their refunds and notes, turn on Order Balance > Settings > Data > Delete all data before you delete the plugin. Orders keep their items and totals.

= Can I edit the emails? =

Yes. Go to WooCommerce > Settings > Emails > Order updated (and the other Order Balance emails). Templates can be overridden in your theme under `order-balance/`.

= Does it work with the block checkout and with express payment buttons? =

Balance payments use the classic "Pay for order" page, which works with every payment gateway that supports it. Stripe's Apple Pay, Google Pay and Link buttons on that page are covered by the amount safeguard described above.

== Screenshots ==

1. The Balance panel on the order screen: order total, paid, balance due and the balance payment.
2. The order changed again while a payment was waiting: update the same payment and pay link.
3. A balance payment, laid out like a WooCommerce order.
4. The customer pays the balance with any payment method of the store.
5. The "Order updated" email with the new total, the amount paid and a pay button.
6. Settings, grouped by topic, here the rounding of small differences.

== Changelog ==

= 0.1.0 =
* First release.
