<?php
/*
 * Plugin Name:       EveryPay payment gateway for WooCommerce
 * Plugin URI:        https://support.every-pay.com/
 * Description:       Payment gateway for adding EveryPay (https://every-pay.com/) card and open banking payments support to Woocommerce.
 * Version:           1.3.7
 * Author:            EveryPay AS
 * Author URI:        https://every-pay.com/
 * Requires at least: 4.4
 * Tested up to:      4.9.1
 * Text Domain:       everypay
 * Domain Path:       languages
 * WC requires at least: 3.0.0
 * WC tested up to:   3.7.0
 * Network:           false
 * GitHub Plugin URI: https://github.com/UnifiedPaymentSolutions/woocommerce-payment-gateway-everypay
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 * @package  EveryPay
 * @author   EveryPay AS
 * @category Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Required functions
 */
require_once('woo-includes/woo-functions.php');

/**
 * Base functions
 */
require_once('includes/class-base.php');

add_action('plugins_loaded', function() {
	EveryPay\Base::get_instance(
		plugin_basename(__FILE__)
	);
}, 0);
