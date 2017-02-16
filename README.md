### EveryPay WooCommerce Payment Gateway ###
Contributors: petskratt, aedes
Tags: everypay, woocommerce, payment, payment gateway, credit card, debit card
Requires at least: 4.2
Tested up to: 4.7.2
Stable tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

EveryPay WooCommerce payment gateway allows you to accept Visa and MasterCard
credit and debit card payments on your WooCommerce store.

## Description ##

EveryPay is a payment service provider, enabling e-commerce merchants
to collect credit and debit card online payments. This plugin adds EveryPay as
payment method to your WooCommerce store, the only required configuration step
is copying API username and password from EveryPay Merchant Portal to Checkout Options
page

> **Note:** EveryPay is currently available for businesses with account in Estonian LHV Pank.

For detailed information and signup please visit: [every-pay.com](https://every-pay.com)

# Accepting payment cards on website with EveryPay is easy #

**Simple setup***
EveryPay toolbox comes with free modules for most popular e-commerce platforms which makes it smooth to integrate with our payment gateway.

**It takes just one agreement and one integration**
EveryPay will deal with acquiring bank for you and make applying for a merchant bank account easy. Once the agreement with the acquirer is done it takes just one integration and you can start accepting card payments on your web shop.

**No fees for setting up**
Setting up EveryPay with your e-commerce is free of charge. Our pricing is and always will be straightforward and we want to offer flexible pricing model for all size of merchants.

**Settlement on next day**
With EveryPay you get money transfer for the payments made on your e-shop on next day already.

**Vast array of currencies**
With EveryPay your customers can make purchases in many currencies. We support  EUR, USD, GBP, SEK, DKK, NOK, CAD  and CHF.

# Features #

* easy configuration in WooCommerce - only API username and password need to be copied from EveryPay Merchant Portal
* easy customization - payment method name and description can be changed easily
* easy payment page customization - payment page is hosted on secure EveryPay servers, but can be easily cusomized in Merchant Portal to include your logo etc
* payments can be taken as 'Charge' where payment authorisation is followed by immediate transaction and 'Authorisation' where required sum is reserved on cardholder account and actual transaction takes place either automatically after preset time or on manual action in Merchant Portal (for example after stock level has been checked or product is ready for shipping)

## Installation ##

1. Upload `everypay-payment-gateway` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce - Settings
4. Everypay payment gateway will be available in "Checkout" settings

EveryPay has support for English and Estonian included, additional translations can be added by translating .pot file found in `langugages` directory or with WPML's String Translation.

## Changelog ##

# 1.0.5 # 
* Added responsive design in Iframe

# 1.0.4 # 
* Settings validation fix
* Latest wp version compatibility tested

# 1.0.3 #
* Fixed some PHP 5.3 compatibility issues

# 1.0.2 #
* Removed border from iFrame payment form
* Added extra validation for admin settings
* Moved dimmed background so that it wouldn't block iFrame

# 1.0.1 #
* Payment status return value changed from 'transaction_result' to 'payment_state'
* Successful payment state changed from 'completed' to 'settled'
* Timestamp check gives a 60 second leeway to account for differences in server clock settings between the payment gateway and website host

# 1.0.0 #
* first public version in WP.org plugin directory

# 0.9.7 #
* fix: php 5.4 compatibility requirement + update test

# 0.9.6 #
* added support for saved credit cards (token payments)

# 0.9.5 #
* fix: bug in calculating hmac signature in callbacks

# 0.9.4 #
* added support for iFrame payment form
* added notification warning about non-https checkouts
* added better support for WPML using wpml-config.xml
* added .pot to enable unified translation
* fix: debug logging can now be disabled

# 0.9.3 #
* added support of API hmac_fields (future-proof)
* fix: billing email was not sent with payment data

# 0.9.2 #
* first public version
