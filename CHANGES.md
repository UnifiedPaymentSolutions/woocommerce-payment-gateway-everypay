## Changelog ##

# 1.0.6 # 
* Iframe responsive design fix

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