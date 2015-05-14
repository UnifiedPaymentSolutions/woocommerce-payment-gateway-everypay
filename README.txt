=== EveryPay WooCommerce Payment Gateway ===
Contributors: petskratt
Tags: everypay, woocommerce, payment, payment gateway, credit card, debit card
Requires at least: 4.2
Tested up to: 4.2.2
Stable tag: master
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

EveryPay WooCommerce payment gateway allows you to accept Visa and MasterCard
credit and debit card payments on your WooCommerce store.

== Description ==

EveryPay is an card payment gateway service provider, enabling e-commerce merchants
to collect credit and debit card online payments. This plugin adds EveryPay as
payment method to your WooCommerce store, the only required configuration step
is copying API username and password from EveryPay Merchant Portal to Checkout Options
page

> **Note:** EveryPay is currently available for businesses with account in Estonian LHV Pank.

For detailed information and signup please visit: [every-pay.com](https://every-pay.com)

=== Accepting payment cards on website with EveryPay is easy ===

**Simple setup**
EveryPay toolbox comes with free modules for most popular e-commerce platforms which makes it smooth to integrate with our payment gateway.

**It takes just one agreement and one integration**
EveryPay will deal with acquiring bank for you and make applying for a merchant bank account easy. Once the agreement with the acquirer is done it takes just one integration and you can start accepting card payments on your web shop.

**No fees for setting up**
Setting up EveryPay with your e-commerce is free of charge. Our pricing is and always will be straightforward and we want to offer flexible pricing model for all size of merchants.

**Settlement on next day**
With EveryPay you get money transfer for the payments made on your e-shop on next day already.

**Vast array of currencies**
With EveryPay your customers can make purchases in many currencies. We support  EUR, USD, GBP, SEK, DKK, NOK, CAD  and CHF.

=== Features ==

* easy configuration in WooCommerce - only API username and password need to be copied from EveryPay Merchant Portal
* easy customization - payment method name and description can be changed easily
* easy payment page customization - payment page is hosted on secure EveryPay servers, but can be easily cusomized in Merchant Portal to include your logo etc
* payments can be taken as 'Charge' where payment authorisation is followed by immediate transaction and 'Authorisation' where required sum is reserved on cardholder account and actual transaction takes place either automatically after preset time or on manual action in Merchant Portal (for example after stock level has been checked or product is ready for shipping)

== Installation ==

Suggested installation and update of EveryPay plugin is using GitHub Updater plugin:

1. Download GitHub Updater as [latest tagged archive](https://github.com/afragen/github-updater/releases)
1. Rename the archive to `github-updater.zip` removing any added version numbers
1. Go to 'Plugins' > 'Add New' > 'Upload' `/wp-admin/plugin-install.php?tab=upload` and choose the ZIP file
1. Activate the GitHub Updater plugin and got to 'Settings' > 'GitHub Updater' > 'Install Plugin'
1. Enter `eepohsbit/woocommerce-payment-gateway-everypay` as 'Plugin URI' and select 'Bitbucket' as 'Remote Repository Host', click 'Install Plugin', then 'Activate Plugin'
1. Go to 'WooCommerce' > 'Settings' > 'Checkout' > 'EveryPay', enable it and enter your API username and password that can be found in EveryPay Merchant Portal
1. You can optionally enable debug logging and test mode with separate API username and password that directs payments to test environment where real payments are not made (you'll see a warning in WordPress admin area about test mode being active)

== Changelog ==

= 0.9.2 =
* first public version
