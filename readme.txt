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
* Rounding adjustments: absorb a small difference (for example from tax rounding) with one click instead of asking for a payment. You can turn on automatic rounding, which is off by default.
* Partially paid order status while a balance payment is outstanding.
* Customers see their balance and a Pay now button in My Account.
* Payments screen (WooCommerce > Order Balance): every balance payment in one list, with search, status filters and a page of its own where you can cancel it.
* Choose which emails are sent: order updated, payment received (customer and store), payment cancelled.
* Setup wizard on first activation walks through every setting in about two minutes.
* Each balance payment has a screen laid out like a WooCommerce order: details, lines, totals, refunds, actions and notes.
* Works with High-Performance Order Storage (HPOS) and legacy order storage.

The free version takes one balance payment per order. A payment uses the place once it is paid, and refunding it in full frees the place again. While the payment is waiting, further changes to the order update it with one click, so the customer keeps one link and pays once. Order Balance Pro adds unlimited and custom-amount payments, refunds of single payments, changing who pays, combined invoices, payment reminders and an optional order change fee.

== Frequently Asked Questions ==

= Does it change the checkout? =

No. The free version only works with orders that have already been paid.

= Where are balance payments stored? =

Each balance payment is a separate order of its own type, linked to the original order. It does not appear in the orders list or in sales reports, so revenue is not counted twice.

= What happens with payment method fees and surcharges? =

If a payment method adds a fee or surcharge to a balance payment, only the requested amount counts toward the order. The fee never makes the order look overpaid. By default a refund returns a matching share of the fee, as card network rules usually require for card surcharges. You can turn this off under Payment fees, and refunds are then limited to the requested amount. Check the rules for your country and payment provider before you charge or keep fees.

= Can I charge a fee for changing an order? =

Order Balance Pro can add an order change fee to balance payments: a fixed amount or a percentage, off by default. It is a separate line on the payment and does not count toward the order. It applies only to orders paid at checkout that were changed afterwards. Orders paid later (cash on delivery, bank transfer, orders created in the admin) and payments you record manually never get a fee. You choose whether the fee is taxed and when it is refunded: with a full refund (default), in proportion, or never.

Check the rules before you charge a fee. In some countries a fee for paying later counts as consumer credit and needs a licence, so describe it as a charge for changing the order. Fees on sales of taxable goods are usually taxable. Consumers who cancel a distance sale may be entitled to a refund of all payments, fees included. Ask your accountant or legal adviser.

= How is tax handled on balance payments? =

Tax is calculated on the order itself. A balance payment pays part of that total, so it carries no tax of its own and tax is not charged twice. If the customer pays a balance payment with store credit or a wallet whose top-ups were already taxed, tax on that part has been collected twice. Ask your accountant how to treat these cases.

= What if the order changes again before the customer pays? =

The Balance box shows how the waiting payment would change, for example from 12.00 to 30.00, and updates it with one click. The customer keeps the same pay link. If the order no longer needs the payment, it is cancelled instead. Order Balance Pro can do this automatically when the order is recalculated, sending one email a few minutes after the last change, or request the difference as a separate payment.

= What happens to my data when I delete the plugin? =

By default balance payments and settings are kept, so you can install the plugin again without losing anything. Orders that are "Partially paid" are moved to "On hold" so they stay visible. To remove everything, including balance payments with their refunds and notes, turn on Settings > Data > Delete all data before you delete the plugin. Orders keep their items and totals.

= Can I edit the email? =

Yes. Go to WooCommerce > Settings > Emails > Order updated. Templates can be overridden in your theme under `order-balance/`.

== Changelog ==

= 0.1.0 =
* First release.
