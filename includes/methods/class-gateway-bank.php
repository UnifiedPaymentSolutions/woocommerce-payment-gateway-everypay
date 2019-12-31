<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

use Everypay\Helper;
use WC_Gateway_Everypay as Gateway;

/**
 * WooCommerce EveryPay.
 *
 * @class   Gateway_Bank
 * @extends Gateway
 * @version 1.2.0
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class Gateway_Bank extends Gateway
{
    /**
     * @var string
     */
    public $id = 'everypay_bank';

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
        $this->title = $this->get_option('title_bank');

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
        return Helper::filter_payment_methods(parent::get_payment_methods(), Gateway::TYPE_BANK);
    }
}