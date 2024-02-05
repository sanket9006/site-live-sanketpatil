=== Credit Card Payments for Woocommerce with CyberSource ===
Contributors: manidev
Tags: woocommerce, cybersource, payments, PCI
Requires at least: 4.0
Tested up to: 5.1.0
Requires PHP: 5.6
Stable tag: 1.0.2
License: GPLv3 or later License
URI: http://www.gnu.org/licenses/gpl-3.0.html

Credit care payments for Woocommerce with CyberSource as the gateway. Tokenizes in the browser to reduce PCI scope and allow cards on file.

== Description ==

The CyberSource credit card payments for Woocommerce allows you to take credit card payments and reduce PCI scope while letting customers save cards on file with their accounts.

This is all done using the Secure Acceptance Flex Microform. This allows CyberSource to host the card collection field at CyberSource in an iframe without impacting the customer experience. This means no redirects disrupting the customer experience and greatly reduced PCI scope for you. Credit cards will be tokenized in the customer browser without touching your server so that even if a server or website is compromised no credit cards are stolen. The tokens created can be used just like a credit card number with CyberSource only.

Major features of this plugin:
* Credit Card Acceptance
* Keep customers card on file safely with tokenization
* Reduce PCI scope without impacting the user experience

Get CyberSource worldwide reach and security with Woocommerce awesome WordPress eCommerce platform.

We are looking for feedback from users of this plugin. To encourage this we are offering free help to get it setup and working for you. Please contact us at support@manidev.net

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Use the Woocommerce->Settings select the Payments tab then select the Credit Cards - CyberSource Method to configure the gateway.

== Changelog ==

= 1.0.2 =
* Fix to production versus test environment selector

= 1.0.1 =
* Additional logging added when logging enabled

= 1.0.0 =
* Initial release
