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
 * @class   Gateway_Card
 * @extends Gateway
 * @version 1.2.0
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class Gateway_Card extends Gateway
{
    /**
     * @var string
     */
    public $id = 'everypay_card';

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return mixed
     */
    public function __construct()
    {
        parent::__construct();

        add_action('woocommerce_everypay_fieldset_start', array($this, 'token_hint_html'), 20, 1);
        add_action('woocommerce_everypay_form_end', array($this, 'tokens_html'), 10, 1);
    }

    /**
     * Setup gateway.
     *
     * @return void
     */
    protected function setup()
    {
        $this->title = $this->get_option('title_card');

        // Add country selector for everypay payment methods
        add_action('woocommerce_everypay_fieldset_start', array($this, 'country_selector_html'), 10, 1);
    }

    /**
     * Display token hint html.
     *
     * @param string $id
     * @return void
     */
    public function token_hint_html($id)
    {
        if($id == $this->id && !is_user_logged_in() && $this->token_enabled) {
            ?><p><?php esc_html_e('To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'everypay'); ?></p><?php
        }
    }

    /**
     * Display tokens list html.
     *
     * @return void
     */
    public function tokens_html($id)
    {
        if($id == $this->id && $this->token_enabled) {
            $tokens = $this->get_user_tokens();

            if(empty($tokens)) {
                return;
            }

            $args = array(
                'gateway_id' => $this->id,
                'tokens' => $tokens,
                'myaccount_page_id' => get_option('woocommerce_myaccount_page_id')
            );

            wc_get_template('tokens.php', $args, '', WC_Everypay()->template_path());
        }
    }

    /**
     * Get card methods.
     *
     * @return object[]
     */
    public function get_payment_methods()
    {
        return Helper::filter_payment_methods(parent::get_payment_methods(), Gateway::TYPE_CARD);
    }
}