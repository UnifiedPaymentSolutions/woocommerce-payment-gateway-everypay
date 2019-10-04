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
     * @var string
     */
    protected $apiUsername;

    /**
     * @var string
     */
    protected $apiSecret;

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @param string $url
     * @param string $username
     * @param string $secret
     */
    public function __construct($url, $username, $secret)
    {
        $this->apiUsername = $username;
        $this->apiSecret = $secret;
        $this->apiUrl = $url;
    }

    /**
     * Make One-Off payment.
     *
     */
    public function paymentOneoff()
    {
        
    }

    /**
     * Make CIT payment.
     *
     */
    public function paymentCit()
    {
        
    }

    /**
     * Get processing account data.
     *
     */
    public function processingAccount($accountName)
    {
        return $this->request('processing_accounts', $accountName, array(
            'api_username' => $this->apiUsername
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
        $url = $this->apiUrl . '/' . $endpoint;

        if($parameter) {
            $url .= '/' . $parameter;
        }

        if($method == self::GET) {
            $url .= '?' . http_build_query($data);
        }

        $curl = curl_init();

        curl_setopt_array($curl,
            array(
                CURLOPT_USERPWD => $this->apiUsername . ":" . $this->apiSecret,
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
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($result);

        return $decoded;
    }

    /**
     * If API has all the necessary data for operation.
     *
     * @return boolean
     */
    public function is_configured()
    {
        return !empty($this->apiUsername) && !empty($this->apiSecret) && !empty($this->apiUrl);
    }
}