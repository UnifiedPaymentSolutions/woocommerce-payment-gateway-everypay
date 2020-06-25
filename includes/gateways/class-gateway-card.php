<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

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

        add_action('woocommerce_fieldset_start_' . $this->id, array($this, 'token_hint_html'), 20);
        add_action('woocommerce_form_end_' . $this->id, array($this, 'tokens_html'), 10);
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
        add_action('woocommerce_fieldset_start_' . $this->id, array($this, 'country_selector_html'), 10);
        
        // Payment methods to display
        add_action('woocommerce_form_start_' . $this->id, array($this, 'payment_method_options'), 10);
    }

    /**
     * Display token hint html.
     *
     * @param string $id
     * @return void
     */
    public function token_hint_html()
    {
        if(!is_user_logged_in() && $this->token_enabled) {
            ?><p><?php esc_html_e('To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'everypay'); ?></p><?php
        }
    }

    /**
     * Display tokens list html.
     *
     * @return void
     */
    public function tokens_html()
    {
        if($this->token_enabled) {
            $tokens = $this->get_user_tokens();

            if(empty($tokens)) {
                return;
            }

            $args = array(
                'gateway_id' => $this->id,
                'tokens' => $tokens,
                'myaccount_page_id' => get_option('woocommerce_myaccount_page_id')
            );

            wc_get_template('tokens.php', $args, '', Base::get_instance()->template_path());
        }
    }

    /**
     * Get card methods.
     *
     * @return object[]
     */
    public function get_payment_methods()
    {
        $methods = Helper::filter_payment_methods(parent::get_payment_methods(), Gateway::TYPE_CARD);

        $no_tokens = false;
        if(is_user_logged_in() && $this->token_enabled) {
            $no_tokens = empty($this->get_user_tokens());
        }

        if((!is_user_logged_in() || $no_tokens) && count($methods) == 1) {
            foreach ($methods as $key => $method) {
                $methods[$key]->selected = true;
            }
        }
        return $methods;
    }
}