=== EveryPay WooCommerce Payment Gateway ===
Contributors: petskratt
Tags: everypay, woocommerce, payment, payment gateway, credit card, debit card
Requires at least: 4.2
Tested up to: 4.3.1
Stable tag: master
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

EveryPay WooCommerce payment gateway allows you to accept Visa and MasterCard
credit and debit card payments on your WooCommerce store.

== Description ==

EveryPay is a payment service provider, enabling e-commerce merchants
to collect credit and debit card online payments. This plugin adds EveryPay as
payment method to your WooCommerce store, the only required configuration step
is copying API username and password from EveryPay Merchant Portal to Checkout Options
page

> **Note:** EveryPay is currently available for businesses with account in Estonian LHV Pank.

For detailed information and signup please visit: [every-pay.com](https://every-pay.com)

= Accepting payment cards on website with EveryPay is easy =

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

= Features =

* easy configuration in WooCommerce - only API username and password need to be copied from EveryPay Merchant Portal
* easy customization - payment method name and description can be changed easily
* easy payment page customization - payment page is hosted on secure EveryPay servers, but can be easily cusomized in Merchant Portal to include your logo etc
* payments can be taken as 'Charge' where payment authorisation is followed by immediate transaction and 'Authorisation' where required sum is reserved on cardholder account and actual transaction takes place either automatically after preset time or on manual action in Merchant Portal (for example after stock level has been checked or product is ready for shipping)

== Screenshots ==

1. Installing WooCommerce Payment Gateway for EveryPay

2. Configuration options

3. EveryPay on checkout page

== Installation ==

Suggested installation and update of EveryPay plugin is using GitHub Updater plugin:

1. Download the latest [tagged archive](https://github.com/afragen/github-updater/releases) (choose the "zip" option).
2. Unzip the archive, rename the folder correctly to `github-updater`, then re-zip the file.
3. Go to the __Plugins -> Add New__ screen and click the __Upload__ tab.
4. Upload the zipped archive directly.
5. Go to the Plugins screen and click __Activate__.
6. Go to 'Settings' > 'GitHub Updater' > 'Install Plugin'
7. Enter `UnifiedPaymentSolutions/woocommerce-payment-gateway-everypay` as 'Plugin URI' and select 'GitHub' as 'Remote Repository Host', click 'Install Plugin', then 'Activate Plugin'
8. Go to 'WooCommerce' > 'Settings' > 'Checkout' > 'EveryPay', enable it and enter your API username and password that can be found in EveryPay Merchant Portal
9. You can optionally enable debug logging and test mode with separate API username and password that directs payments to test environment where real payments are not made (you'll see a warning in WordPress admin area about test mode being active)

EveryPay has support for English and Estonian included, additional translations can be added by translating .pot file found in `langugages` directory or with WPML's String Translation.

== Changelog ==

= 0.9.4 =
* added support for iFrame payment form
* added notification warning about non-https checkouts
* added better support for WPML using wpml-config.xml
* added .pot to enable unified translation
* fix: debug logging can now be disabled

= 0.9.3 =
* added support of API hmac_fields (future-proof)
* fix: billing email was not sent with payment data


= 0.9.2 =
* first public version

== Upgrade Notice ==

= 0.9.4 =
Adds support for iFrame payment form and improves WPML support.