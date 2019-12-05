<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * WooCommerce EveryPay.
 *
 * @class   WC_Gateway_Everypay_Alternative
 * @extends WC_Gateway_Everypay
 * @version 1.2.0
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class WC_Gateway_Everypay_Alternative extends WC_Gateway_Everypay
{
    /**
     * @var string
     */
    public $id = 'everypay_alter';

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return mixed
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Setup gateway.
     *
     * @return void
     */
    protected function setup()
    {
        $this->title = $this->get_option('title_alternative');

        // Add country selector for everypay payment methods
        add_action('woocommerce_everypay_fieldset_start', array($this, 'country_selector_html'), 10, 1);
    }

    /**
     * Get bank methods.
     *
     * @return object[]
     */
    public function get_payment_methods()
    {
        return WC_Everypay_Helper::filter_payment_methods(parent::get_payment_methods(), WC_Gateway_Everypay::TYPE_ALTER);
    }
}