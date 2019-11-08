<?php

/**
 * Communicate with API v3.
 */
class WC_Everypay_Api
{
    /**
     * @var string
     */
    const POST = 'post';
    const GET = 'get';

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
     * @var WC_Logger
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
        $this->log = new WC_Everypay_Logger();
        $this->log->set_debug($debug);
    }

    /**
     * Initiate One-Off payment.
     *
     * @param Order $order
     * @param WC_Gateway_Everypay $gateway
     * @return array
     */
    public function payment_oneoff($order, $gateway)
    {
        $data = array(
            'api_username' => $this->api_username,
            'account_name' => $gateway->get_account_id(),
            'amount' => number_format($order->get_total(), self::DECIMALS, '.', ''),
            'token_agreement' => 'unscheduled', # with token
            'order_reference' => $order->get_order_number(),
            'nonce' => 'TODO', # chatis midagi
            'email' => $order->get_billing_email(),
            'customer_ip' => $order->get_customer_ip_address(),
            'customer_url' => $gateway->get_notify_url(),
            'billing_city' => $order->get_billing_city(),
            'billing_country' => $order->get_billing_country(),
            'billing_line1' => $order->get_billing_address_1(),
            'billing_line2' => $order->get_billing_address_2(),
            'billing_line3' => '',
            'billing_code' => wc_format_postcode($order->get_billing_postcode(), $order->get_billing_country()),
            'billing_state' => $order->get_billing_state(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_country' => $order->get_shipping_country(),
            'shipping_line1' => $order->get_shipping_address_1(),
            'shipping_line2' => $order->get_shipping_address_2(),
            'shipping_line3' => '',
            'shipping_code' => wc_format_postcode($order->get_shipping_postcode(), $order->get_shipping_country()),
            'shipping_state' => $order->get_shipping_state(),
            'locale' => $gateway->get_locale(),
            'request_token' => 'TODO',
            'timestamp' => 'TODO',
            'skin_name' => 'TODO',
            'integration_details' => $this->get_integration()
        );

        return $this->request('payments', 'oneoff', $data, self::POST);
    }

    /**
     * Initiate CIT payment.
     *
     * @return array
     */
    public function payment_cit()
    {
        $data = array(
            'api_username' => $this->api_username,
            /* ... */
            'integration_details' => $this->get_integration()
        );

        return $this->request('payments', 'cit', $data, self::POST);
    }

    /**
     * Get processing account data.
     *
     * @param WC_Gateway_Everypay $gateway
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
    protected function request($endpoint, $parameter = null, $data, $method)
    {
        $url = $this->api_url . '/' . $endpoint;

        $this->log->debug('API request: ' . wc_print_r(array(
            'url' => $url,
            'method' => $method,
            'data' => $this->mask_data($data)
        ), true));

        if($parameter) {
            $url .= '/' . $parameter;
        }

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
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $result = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($result);

        $this->log->debug('API response: ' . wc_print_r($decoded, true));

        return $decoded;
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
     * @param array $data
     * @return array
     */
    protected function mask_data($data)
    {
        $mask = array(
            'email' => '***',
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

        return array_merge($data, array_intersect_key($mask, $data));
    }
}