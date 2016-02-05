<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Perform plugin name swap to keep it activated
 */

$active_plugins = get_option( 'active_plugins', array() );
$update_needed  = false;

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strpos( $active_plugin, '/woocommerce-payment-gateway-everypay.php' ) !== false ) {
		$active_plugins[ $key ] = str_replace( '/woocommerce-payment-gateway-everypay.php', '/everypay-payment-gateway-for-woocommerce.php', $active_plugin );
		$update_needed          = true;
	}
}

if ( $update_needed === true ) {
	update_option( 'active_plugins', $active_plugins );
}
