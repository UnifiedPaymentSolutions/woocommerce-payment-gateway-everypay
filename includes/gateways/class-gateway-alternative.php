<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

/**
 * WooCommerce EveryPay.
 *
 * @class   Gateway_Alternative
 * @extends Gateway
 * @version 1.2.0
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class Gateway_Alternative extends Gateway
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
        
        // Payment methods to display
        add_action('woocommerce_everypay_form_start', array($this, 'payment_method_options'));
    }

    /**
     * Get bank methods.
     *
     * @return object[]
     */
    public function get_payment_methods()
    {
        return Helper::filter_payment_methods(parent::get_payment_methods(), Gateway::TYPE_ALTER);
    }
}