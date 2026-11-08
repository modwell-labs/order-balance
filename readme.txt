=== Order Balance ===
Contributors: modwelllabs
Tags: woocommerce, order edit, additional payment, balance due, refund difference
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect the difference when a paid WooCommerce order changes, refund overpayments, and absorb small rounding differences.

== Description ==

When you edit an order that has already been paid, WooCommerce asks the customer to pay the full order total again. Order Balance tracks what the customer has paid and what the order costs now, and lets you settle the difference.

* Balance panel on the order screen: order total, paid, balance due or overpaid.
* Payment method rules: choose which methods count as paid at checkout and which are paid later (cash on delivery, bank transfer, cheque, orders created in the admin). Pay-later orders show the whole total as due until it is paid.
* Record payments received outside the website, such as cash or a bank transfer, in full or in part.
* Request the difference: creates a linked balance payment and emails the customer a pay link.
* Order updated email: the updated order, what has been paid and what is due, in one message.
* Refund the difference through the original payment method when the order total goes down.
* Rounding adjustments: small differences from tax rounding are absorbed with an adjustment line instead of a payment.
* Partially paid order status while a balance payment is outstanding.
* Customers see their balance and a Pay now button in My Account.
* Payments screen (WooCommerce > Order Balance): every balance payment in one list, with search, status filters and a page of its own where you can cancel it.
* Choose which emails are sent: order updated, payment received (customer and store), payment cancelled.
* Works with High-Performance Order Storage (HPOS) and legacy order storage.

The free version supports one extra payment per order. Order Balance Pro adds unlimited and custom-amount payments, refunds of single payments, changing who pays, combined invoices and payment reminders.

== Frequently Asked Questions ==

= Does it change the checkout? =

No. The free version only works with orders that have already been paid.

= Where are balance payments stored? =

Each balance payment is a separate order of its own type, linked to the original order. It does not appear in the orders list or in sales reports, so revenue is not counted twice.

= Can I edit the email? =

Yes. Go to WooCommerce > Settings > Emails > Order updated. Templates can be overridden in your theme under `order-balance/`.

== Changelog ==

= 0.1.0 =
* First release.
