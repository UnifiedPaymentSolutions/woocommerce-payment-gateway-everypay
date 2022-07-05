<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

/**
 * Communicate with API v3.
 */
class Api
{
    /**
     * @var string
     */
    const POST = 'post';
    const GET = 'get';

    /**
     * @var string
     */
    const AGREEMENT_UNSCHEDULED = 'unscheduled';

    /**
     * @var int
     */
    const DECIMALS = 2;

    /**
     * @var string
     */
    protected $api_username;

    /**
     * @var string
     */
    protected $api_secret;

    /**
     * @var string
     */
    protected $api_url;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @param string $url
     * @param string $username
     * @param string $secret
     * @param string $version
     */
    public function __construct($url, $username, $secret, $version, $debug = false)
    {
        $this->api_username = $username;
        $this->api_secret = $secret;
        $this->api_url = $url;
        $this->version = $version;
        $this->log = new Logger();
        $this->log->set_debug($debug);
    }

    /**
     * Initiate One-Off payment.
     *
     * @param Order $order
     * @param Gateway $gateway
     * @return array
     */
    public function payment_oneoff($order, $gateway)
    {
        $data = array(
            'api_username' => $this->api_username,
            'account_name' => $gateway->get_account_id(),
            'amount' => number_format($order->get_total(), self::DECIMALS, '.', ''),
            'order_reference' => $order->get_order_number(),
            'token_agreement' => self::AGREEMENT_UNSCHEDULED,
            'nonce' => $this->nonce(),
            'email' => $order->get_billing_email(),
            'customer_ip' => $order->get_customer_ip_address(),
            'locale' => Helper::get_locale(),
            'request_token' => $gateway->get_token_enabled() && is_user_logged_in(),
            'timestamp' => get_date_from_gmt(current_time('mysql', true), 'c'),
            'integration_details' => $this->get_integration()
        );

        $data = array_merge($data, $this->get_billing_fields($order));
        $data = array_merge($data, $this->get_shipping_fields($order));

        if($preferred_country = Helper::get_order_preferred_country($order)) {
            $data['preferred_country'] = $preferred_country;
        }

        if($gateway->useIframe($order)) {
            $data['skin_name'] = $gateway->get_skin_name();
            $data['customer_url'] = $gateway->get_iframe_return_url();
        } else {
            $data['customer_url'] = $gateway->get_customer_redirect_url($order->get_id(), true);
        }

        return $this->request('payments', 'oneoff', $data, self::POST);
    }

    /**
     * Initiate CIT payment.
     *
     * @param Order $order
     * @param Gateway $gateway
     * @return array
     */
    public function payment_cit($order, $gateway)
    {
        $data = array(
            'api_username' => $this->api_username,
            'account_name' => $gateway->get_account_id(),
            'amount' => number_format($order->get_total(), self::DECIMALS, '.', ''),
            'order_reference' => $order->get_order_number(),
            'token_agreement' => self::AGREEMENT_UNSCHEDULED,
            'nonce' => $this->nonce(),
            'email' => $order->get_billing_email(),
            'customer_ip' => $order->get_customer_ip_address(),
            'timestamp' => get_date_from_gmt(current_time('mysql', true), 'c'),
            'token' => $order->get_meta(Gateway::META_TOKEN),
            'integration_details' => $this->get_integration()
        );

        $data = array_merge($data, $this->get_billing_fields($order));
        $data = array_merge($data, $this->get_shipping_fields($order));

        if($gateway->useIframe($order)) {
            $data['skin_name'] = $gateway->get_skin_name();
            $data['customer_url'] = $gateway->get_iframe_return_url();
        } else {
            $data['customer_url'] = $gateway->get_customer_redirect_url($order->get_id());
        }
        
        return $this->request('payments', 'cit', $data, self::POST);
    }

    /**
     * Get order status.
     *
     * @param Order $order
     * @return array
     */
    public function payment_status($order)
    {
        return $this->request('payments', $order->get_meta(Gateway::META_REFERENCE), array(
            'api_username' => $this->api_username
        ), self::GET);
    }

    /**
     * Get processing account data.
     *
     * @param Gateway $gateway
     * @return array
     */
    public function processing_account($gateway)
    {
        return $this->request('processing_accounts', $gateway->get_account_id(), array(
            'api_username' => $this->api_username
        ), self::GET);
    }

    /**
     * Send request to API.
     *
     * @param string $endpoint
     * @param string|null $parameter
     * @param array $data
     * @param string $method
     * @return array
     */
    protected function request($endpoint, $parameter, $data, $method)
    {
        $url = $this->api_url . '/' . $endpoint;

        if($parameter) {
            $url .= '/' . $parameter;
        }
        
        $this->log->info('API request: ' . wc_print_r(array(
            'url' => $url,
            'method' => $method,
            'data' => $this->mask_data($data)
        ), true));

        if($method == self::GET) {
            $url .= '?' . http_build_query($data);
        }

        $curl = curl_init();

        curl_setopt_array($curl,
            array(
                CURLOPT_USERPWD => $this->api_username . ":" . $this->api_secret,
                CURLOPT_URL => $url,
                CURLOPT_POST => $method == self::POST,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json'
                ),
                CURLOPT_RETURNTRANSFER => true
            )
        );

        if($method == self::POST) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $result = curl_exec($curl);

        if($result === false || empty($result)) {
            $decoded = false;
            $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $this->log->info('API error: ' . wc_print_r('code - ' . $code, true));
        } else {
            $decoded = json_decode($result);
            $this->log->info('API response: ' . wc_print_r($this->mask_data($decoded), true));
        }

        curl_close($curl);

        return $decoded;
    }

    /**
     * Get billing fields.
     *
     * @param Order $order
     * @return array
     */
    protected function get_billing_fields($order)
    {
        $fields = array(
            'billing_city' => $order->get_billing_city(),
            'billing_country' => $order->get_billing_country(),
            'billing_line1' => $order->get_billing_address_1(),
            'billing_line2' => $order->get_billing_address_2(),
            'billing_line3' => '',
            'billing_code' => wc_format_postcode($order->get_billing_postcode(), $order->get_billing_country()),
            'billing_state' => $order->get_billing_state(),
        );

        return array_filter($fields);
    }

    /**
     * Get shipping fields.
     *
     * @param Order $order
     * @return array
     */
    protected function get_shipping_fields($order)
    {
        $fields = array(
            'shipping_city' => $order->get_shipping_city(),
            'shipping_country' => $order->get_shipping_country(),
            'shipping_line1' => $order->get_shipping_address_1(),
            'shipping_line2' => $order->get_shipping_address_2(),
            'shipping_line3' => '',
            'shipping_code' => wc_format_postcode($order->get_shipping_postcode(), $order->get_shipping_country()),
            'shipping_state' => $order->get_shipping_state(),
        );

        return array_filter($fields);
    }

    /**
     * If API has all the necessary data for operation.
     *
     * @return boolean
     */
    public function is_configured()
    {
        return !empty($this->api_username) && !empty($this->api_secret) && !empty($this->api_url);
    }

    /**
     * Get integration details.
     *
     * @return array
     */
    protected function get_integration()
    {
        return array(
            'software' => 'woocommerce',
            'version' => $this->version,
            'integration' => 'plugin',
        );
    }

    /**
     * Masks sensitive data for logging.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function mask_data($data)
    {
        $mask = array(
            'email' => '***@***',
            'billing_city' => '***',
            'billing_country' => '***',
            'billing_line1' => '***',
            'billing_line2' => '***',
            'billing_line3' => '***',
            'billing_code' => '***',
            'billing_state' => '***',
            'shipping_city' => '***',
            'shipping_country' => '***',
            'shipping_line1' => '***',
            'shipping_line2' => '***',
            'shipping_line3' => '***',
            'shipping_code' => '***',
            'shipping_state' => '***'
        );

        if(is_array($data)) {
            return array_merge($data, array_intersect_key($mask, $data));
        }

        if(is_object($data)) {
            $dataMasked = clone $data;
            foreach ($mask as $field => $masked) {
                if(isset($dataMasked->{$field})) {
                    $dataMasked->{$field} = $masked;
                }
            }
            return $dataMasked;
        }

        return $data;
    }

    /**
     * Generate nonce.
     *
     * @return string
     */
    protected function nonce()
    {
        $random = '';
        for ($i = 0; $i < 32; $i++) {
            $random .= chr(mt_rand(0, 255));
        }
        return hash('sha512', $random);
    }
}