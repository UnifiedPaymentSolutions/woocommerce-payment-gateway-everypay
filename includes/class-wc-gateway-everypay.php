<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * WooCommerce EveryPay.
 *
 * @class   WC_Gateway_Everypay
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class WC_Gateway_Everypay extends WC_Payment_Gateway {

	const _VERIFY_ERROR = 0;    // HMAC mismatch or other error
	const _VERIFY_SUCCESS = 1;  // payment successful
	const _VERIFY_FAIL = 2;     // payment failed
	const _VERIFY_CANCEL = 3;   // payment cancelled by user

  /**
   * Constructor for the gateway.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    $this->id                 = 'everypay';
    $this->icon               = apply_filters( 'woocommerce_gateway_everypay_icon', plugins_url( '/assets/images/mastercard_visa.png', dirname( __FILE__ ) ) );
    $this->has_fields         = false;
    $this->credit_fields      = false;

    $this->order_button_text  = __( 'Pay with card', 'everypay' );

    // Title/description for WooCommerce admin
    $this->method_title       = __( 'EveryPay', 'everypay' );
    $this->method_description = __( 'Pay with credit cards via EveryPay.', 'everypay' );

    // URL for callback / user redirect from gateway
    $this->notify_url         = WC()->api_request_url( 'WC_Gateway_Everypay' );

    $this->supports           = [ 'products' ];

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    // Get setting values.
    $this->enabled        = $this->get_option( 'enabled' );

    // Title/description for payment method selection page
    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    
    $this->account_id     = $this->get_option( 'account_id' );
    $this->transaction_type   = $this->get_option( 'transaction_type' );
    $this->sandbox        = $this->get_option( 'sandbox' );
    $this->api_endpoint   = $this->sandbox == 'no' ? 'https://pay.every­-pay.eu/transactions/' : 'https://igw-demo.every-pay.com/transactions/';
    $this->api_username   = $this->sandbox == 'no' ? $this->get_option( 'api_username' ) : $this->get_option( 'sandbox_api_username' );
    $this->api_secret     = $this->sandbox == 'no' ? $this->get_option( 'api_secret' ) : $this->get_option( 'sandbox_api_secret' );

    $this->debug          = $this->get_option( 'debug' );

    // Logs
    if( $this->debug == 'yes' ) {
      if( class_exists( 'WC_Logger' ) ) {
        $this->log = new WC_Logger();
      }
      else {
        $this->log = $woocommerce->logger();
      }
    }

    // Hooks
    if( is_admin() ) {
      add_action( 'admin_notices', array( $this, 'checks' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
    
    // Receipt page creates POST to gateway
    add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    
    // Displays additional information about payment on thankyou page and confirmation email
    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
    // add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

    // Add returning user / callback handler to WC API
    add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'everypay_return_handler'));

  }

  /**
   * Admin Panel Options
   *
   * @access public
   * @return void
   */
  public function admin_options() {
    ?>
        <h3><?php _e( 'EveryPay', 'everypay' ); ?></h3>
        <p><?php _e( 'EveryPay is a card payment gateway service provider, enabling e-commerce merchants to collect credit and debit card online payments from their customers.', 'everypay' ); ?></p>
        <p><a href="https://portal.every-pay.eu/"><?= __('Merchant Portal', 'everypay'); ?></a> | <a href="https://every-pay.com/contact/"><?= __('Contacts', 'everypay'); ?></a> | <a href="https://every-pay.com/documentation-overview/"><?= __('Documentation', 'everypay'); ?></a>
        </p>

        <table class="form-table">
          <?php $this->generate_settings_html(); ?>
          <script type="text/javascript">
          jQuery( '#woocommerce_gateway_everypay_sandbox' ).change( function () {
            var sandbox = jQuery( '#woocommerce_gateway_everypay_sandbox_api_username, #woocommerce_gateway_everypay_sandbox_api_secret' ).closest( 'tr' ),
            production  = jQuery( '#woocommerce_gateway_everypay_api_username, #woocommerce_gateway_everypay_api_secret' ).closest( 'tr' );
        
            if ( jQuery( this ).is( ':checked' ) ) {
              sandbox.show();
              production.hide();
            } else {
              sandbox.hide();
              production.show();
            }
          }).change();
          </script>
        </table>
    
    <?php
  }

  /**
   * Check if payment method can be activated
   *
   * @access public
   */
  public function checks() {
    if( $this->enabled == 'no' ) {
      return;
    }

    // PHP Version.
    if( version_compare( phpversion(), '5.3', '<' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'EveryPay Error: EveryPay requires PHP 5.3 and above. You are using version %s.', 'everypay' ), phpversion() ) . '</p></div>';
    }

    // Check required fields.
    else if( !$this->api_username || !$this->api_secret ) {
      if ( $this->sandbox == 'no' ) {
        echo '<div class="error"><p>' . __( 'EveryPay Error: Please enter your API username and secret.', 'everypay' ) . '</p></div>';        
      } else {
        echo '<div class="error"><p>' . __( 'EveryPay Error: Please enter your TEST API username and secret.', 'everypay' ) . '</p></div>';            
      }
    }

    if ( $this->sandbox == 'yes' ) {
      echo '<div class="update-nag"><p>' . __("EveryPay payment gateway is in test mode, real payments not processed!", 'everypay') . '</p></div>';
    }
  }


  /**
   * Check if this gateway is enabled.
   *
   * @access public
   */
  public function is_available() {
    if( $this->enabled == 'no' ) {
      return false;
    }

    if( !$this->api_username || !$this->api_secret ) {
      return false;
    }

    return true;
  }

  /**
   * Initialise Gateway Settings Form Fields
   *
   * @access public
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'       => __( 'Enable/Disable', 'everypay' ),
        'label'       => __( 'Enable EveryPay', 'everypay' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'debug' => array(
        'title'       => __( 'Debug Log', 'everypay' ),
        'type'        => 'checkbox',
        'label'       => __( 'Enable logging', 'everypay' ),
        'default'     => 'no',
        'description' => sprintf( __( 'Log EveryPay events inside <code>%s</code>', 'everypay' ), wc_get_log_file_path( $this->id ) )
      ),
      'sandbox' => array(
        'title'       => __( 'Test', 'everypay' ),
        'label'       => __( 'Enable Test Mode', 'everypay' ),
        'type'        => 'checkbox',
        'description' => __( 'Place the payment gateway in test mode using test API credentials (real payments will not be taken).', 'everypay' ),
        'default'     => 'no'
      ),
     'title' => array(
          'title' => __( 'Title', 'everypay' ),
          'type' => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'everypay' ),
          'default' => __( 'EveryPay', 'everypay' )
          ),
     'description' => array(
          'title' => __( 'Description', 'everypay' ),
          'type' => 'textarea',
          'description' => __( 'This controls the description which the user sees during checkout.', 'everypay' ),
          'default' => __("Pay with credit or debit card.", 'everypay')
           ),
      'account_id' => array(
        'title'       => __( 'Processing Account', 'everypay' ),
        'type'        => 'text',
        'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'Processing accounts' section (looks like: EUR1).", 'everypay' ),
        'default'     => '',
        'desc_tip'    => false,
      ),
      'transaction_type' => array(
        'title'       => __( 'Transaction Type', 'everypay' ),
        'type'        => 'select',
          'options' => array(
            'charge'        => __( 'Charge', 'everypay' ),
            'authorisation' => __( 'Authorisation', 'everypay' ),
          ),
        'description' => __( "Authorisation: A process of getting approval for the current payment. Funds are reserved on cardholder's account. Charge: An authorisation followed by immediate automatic capture.", 'everypay' ),
        'default'     => 'charge',
        'desc_tip'    => false,
      ),
      'api_username' => array(
        'title'       => __( 'API username', 'everypay' ),
        'type'        => 'text',
        'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'General settings' section (looks like: 49bcdd6fed983c12).", 'everypay' ),
        'default'     => '',
        'desc_tip'    => false
      ),
      'api_secret' => array(
        'title'       => __( 'API secret', 'everypay' ),
        'type'        => 'text',
        'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'General settings' section (looks like: e7ed4e55d5f73158f6cf2890fb1c950e).", 'everypay' ),
        'default'     => '',
        'desc_tip'    => false
      ),
      'sandbox_api_username' => array(
        'title'       => __( 'Test API username', 'everypay' ),
        'type'        => 'text',
        'description' => __( 'Optional: API username for testing payments.', 'everypay' ),
        'default'     => '',
        'desc_tip'    => false
      ),
      'sandbox_api_secret' => array(
        'title'       => __( 'Test API secret', 'everypay' ),
        'type'        => 'text',
        'description' => __( 'Optional: API secret for testing payments.', 'everypay' ),
        'default'     => '',
        'desc_tip'    => false
      ),
    );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   * @return void
   */
  public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );
		
				$return_url = '';
		$transaction_id = $order->get_transaction_id();

		if ( ! empty( $this->view_transaction_url ) && ! empty( $transaction_id ) ) {
			$return_url = $this->view_transaction_url;
		}
		
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with credit card.', 'everypay' ) . '</p>';

		$args        = $this->get_everypay_args( $order );
		
    $args_array = [];

		foreach ($args as $key => $value) {
			$args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
		}
		
			wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to payment gateway.', 'everypay' ) ) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		  "24px",
						}
					});
				jQuery("#everypay-button").click();
			' );

    echo '<form action="' . esc_url( $this->api_endpoint ) . '" method="post" id="payment_form" target="_top">';
    echo implode('', $args_array);
		echo '
			<input type="submit" class="button alt" id="everypay-button" value="' . __( 'Pay with credit or debit card', 'everypay' ) . '"> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'everypay' ) . '</a>
			';
		echo '</form>';

  }

  /**
   * Output for the thankyou page.
   *
   * @access public
   */
  public function thankyou_page( $order_id ) {
    // echo '<h2>' . __( 'Extra Details', 'everypay' ) . '</h2>' . PHP_EOL;
  }

  /**
   * Add content to the WC emails.
   *
   * @access public
   * @param  WC_Order $order
   * @param  bool $sent_to_admin
   * @param  bool $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {

      echo '<h2>' . __( 'Payment Details', 'everypay' ) . '</h2>' . PHP_EOL;
      echo '<p>' . __( 'Please note that the charge on your credit card will appear as "EveryPay AS".', 'everypay' ) . '</p>' . PHP_EOL;

    }
  }

  /**
   * Process the payment and return the result.
   *
   * @access public
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
      
    $order = new WC_Order( $order_id );

    // Redirect to receipt page for automatic post to external gateway

    if( $this->debug == 'yes' ) {
      $this->log->add( $this->id, 'EveryPay selected for order #' . $order_id );
    }

  	return array(
  		'result' => 'success',
  		'redirect' => $order->get_checkout_payment_url( true )
  	);

  }

	/**
	 * Prepare data package for signing
	 *
	 * @param array $args
	 * @return string
	 */
	protected function get_everypay_args($order)
	{

    if ( defined(ICL_LANGUAGE_CODE) ) {
      $language = ICL_LANGUAGE_CODE;
    } else {
    	switch ( get_locale() )
    	{
    		case 'et_EE':
    		case 'et':
    			$language = 'et';
    			break;
    		case 'ru_RU':
    		case 'ru':
    			$language = 'ru';
    			break;
    		default:
    			$language = 'en';
    			break;
    	}      
    }

    $args = [
        'account_id' => $this->account_id,
        'amount' => number_format($order->get_total(), 2, '.', ''),
        'api_username' => $this->api_username,
        'billing_address' => $order->billing_address_1,
        'billing_city' => $order->billing_city,
        'billing_country' => $order->billing_country,
        'billing_postcode' => $order->billing_postcode,
        'callback_url' => WC()->api_request_url( 'WC_Gateway_Everypay' ),
        'customer_url' => WC()->api_request_url( 'WC_Gateway_Everypay' ),
        'delivery_address' => $order->shipping_address_1,
        'delivery_city' => $order->shipping_city,
        'delivery_country' => $order->shipping_country,
        'delivery_postcode' => $order->shipping_postcode,
        'email' => $billing_email,
        'nonce' => uniqid(true),
        'order_reference' => $order->id,
        'timestamp' => time(),
        'transaction_type' => $this->transaction_type,
        'user_ip' => $_SERVER['REMOTE_ADDR'],
    ];

    $args['hmac'] = $this->sign_everypay_request($this->prepare_everypay_string($args));
      
  	$args['locale'] = $language;

    if( $this->debug == 'yes' ) {
      $this->log->add( $this->id, 'EveryPay payment request prepared and signed. ' . print_r($args, true) );
    }

    return $args;
	}

	/**
	 * Prepare EveryPay data package for signing
	 *
	 * @param array $args
	 * @return string
	 */
	protected function prepare_everypay_string(array $args)
	{
		$arr = array();
		ksort($args);
		foreach ($args as $k => $v)
		{
			$arr[] = $k . '=' . $v;
		}
		
		$str = implode('&', $arr);
		
		return $str;
	}

	/**
	 * Sign EveryPay payment request
	 *
	 * @param string $request
	 * @return string
	 */
	 
	protected function sign_everypay_request($request)
	{
		return hash_hmac('sha1', $request, $this->api_secret);
	}

	/**
	 * Process returning user or automatic callback
	 *
	 */

	public function everypay_return_handler() {
		@ob_clean();

    header( 'HTTP/1.1 200 OK' );

		$_REQUEST = stripslashes_deep( $_REQUEST );
		
		if (!isset($_REQUEST['api_username']) ||
		    !isset($_REQUEST['nonce']) ||
		    !isset($_REQUEST['order_reference']) ||
		    !isset($_REQUEST['payment_state']) ||
		    !isset($_REQUEST['timestamp']) ||
		    !isset($_REQUEST['transaction_result']) ) {
          die();
		    }

    $order_id  = absint( $_REQUEST['order_reference'] );
		$order     = wc_get_order( $order_id );
		
		if (!$order) {
  		$this->log->add( $this->id, 'Invalid order ID received: ' . print_r($order_id, true) );
  		die("Order was lost during payment attempt - please inform merchant about WooCommerce EveryPay gateway problem.");
		}
				
    if( $this->debug == 'yes' ) {
      $this->log->add( $this->id, 'EveryPay return handler started. ' . print_r($_REQUEST, true) );
  		$this->log->add( $this->id, 'Order '. var_export($order, true) );
    }

    $this->change_language($order->id);

		$order_complete = $this->process_order_status( $order, $_REQUEST );
		
		if ( $order_complete === self::_VERIFY_SUCCESS ) {
  		$this->log->add( $this->id, 'Order complete');
  		$redirect_url = $this->get_return_url( $order );
  				
		}	else {
      
		  $redirect_url = $order->get_cancel_order_url();
  		
  		switch ($order_complete)
    		{
    			case self::_VERIFY_FAIL:
    			  $order->update_status( 'failed', __( 'Payment was declined by payment processor.', 'everypay' ) );
        		$this->log->add( $this->id, 'Payment was declined by payment processor.' );
    			  break;
    			case self::_VERIFY_CANCEL:
    			  $order->update_status( 'failed', __( 'Payment was cancelled by user.', 'everypay' ) );
        		$this->log->add( $this->id, 'Payment was cancelled by user.' );
    			  break;    		
          default:
    			  $order->update_status( 'failed', __( 'An error occurred while processing the payment response - please notify merchant!', 'everypay' ) );
        		$this->log->add( $this->id, 'An error occurred while processing the payment response.' );
    			  break;
    		}
		}

    if( $this->debug == 'yes' ) {
      $this->log->add( $this->id, 'Redirected to ' . $redirect_url );
    }

		wp_redirect( $redirect_url );
		

	}

  private function change_language($order_id) {

    if ( function_exists('icl_object_id') ) {
        
      // adapted from WooCommerce Multilingual /inc/emails.class.php      
      $lang = get_post_meta($order_id, 'wpml_language', TRUE);
      
      if(!empty($lang)){
        global $sitepress,$woocommerce;
        $sitepress->switch_lang($lang,true);
        unload_textdomain('woocommerce');
        unload_textdomain('default');
        $woocommerce->load_plugin_textdomain();
        load_default_textdomain();
        global $wp_locale;
        $wp_locale = new WP_Locale();
      }
    }
  }


	/**
	 * Process the order status
	 *
	 * @param  WC_Order $order
	 * @param  string   $payment_id
	 * @param  string   $status
	 * @param  string   $auth_code
	 *
	 * @return bool
	 */
	public function process_order_status( $order ) {
  	
  	$result = $this->verify_everypay_response($_REQUEST);

		if ( self::_VERIFY_SUCCESS === $result ) {
			// Payment complete
			$order->payment_complete( $_REQUEST['payment_reference'] );
			// Add order note
			$order->add_order_note( sprintf( __( 'EveryPay payment approved (Reference: %s, Timestamp: %s)', 'everypay' ), $_REQUEST['payment_reference'], $_REQUEST['timestamp'] ) );
      // Store the transaction ID for WC 2.2 or later.
      add_post_meta( $order->id, '_transaction_id', $_REQUEST['payment_reference'], true );

			// Remove cart
			WC()->cart->empty_cart();
		}

		return $result;
	}

	/**
	 * Verify EveryPay response
	 *
	 * Expects following data as input:
	 *
	 * for successful and failed payments:
	 *
	  array(
	  'account_id' => account id in EveryPay system
	  'amount' => amount to pay,
	  'api_username' => api username,
	  'nonce' => return nonce
	  'order_reference' => order reference number,
	  'payment_reference' => payment reference number,
	  'payment_state' => payment state,
	  'timestamp' => timestamp,
	  'transaction_result' => transaction result
	  );
	 *
	 * for cancelled payments:
	 *
	  array(
	  'api_username' => api username,
	  'nonce' => return nonce
	  'order_reference' => order reference number,
	  'payment_state' => payment state,
	  'timestamp' => timestamp,
	  'transaction_result' => transaction result
	  );
	 *
	 * @param $data array
	 *
	 * @return int 1 - verified successful payment, 2 - verified failed payment, 3 - user cancelled, 0 - error
	 * @throws Exception
	 */
	public function verify_everypay_response(array $data)
	{
  	
  	$statuses = [
  		'completed' => self::_VERIFY_SUCCESS,
  		'failed' => self::_VERIFY_FAIL,
  		'cancelled' => self::_VERIFY_CANCEL,
  	];

  	
  	
		if ($data['api_username'] !== $this->api_username) {
      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'EveryPay error: API username in response does not match, order not completed!' );
      }
      return self::_VERIFY_ERROR;
		}

		$now = time();
		if (($data['timestamp'] > $now) || ($data['timestamp'] < ($now - 300))){
      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'EveryPay error: response is older than 5 minutes, order not completed!' );
      }
      return self::_VERIFY_ERROR;
		}

		$status = $statuses[$data['transaction_result']];

		$verify = array(
			'api_username' => $data['api_username'],
			'nonce' => $data['nonce'],
			'order_reference' => $data['order_reference'],
			'payment_state' => $data['payment_state'],
			'timestamp' => $data['timestamp'],
			'transaction_result' => $data['transaction_result']
		);

		switch ($data['transaction_result'])
		{
			case 'completed':
			case 'failed':
				// only in automatic callback message
				if (isset($data['processing_errors']))
				{
					$verify['processing_errors'] = $data['processing_errors'];
				}

				if (isset($data['processing_warnings']))
				{
					$verify['processing_warnings'] = $data['processing_warnings'];
				}

				$verify['account_id'] = $data['account_id'];
				$verify['amount'] = $data['amount'];
				$verify['payment_reference'] = $data['payment_reference'];
				break;
			case 'cancelled':
				break;
		}

		$hmac = $this->sign_everypay_request($this->prepare_everypay_string($verify));

		if ($data['hmac'] != $hmac) {
      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'EveryPay error: signature does not match data! '. print_r($data, true) . print_r($hmac, true) . print_r($this->prepare_everypay_string($verify), true) );
        file_put_contents('verify.dump', $this->prepare_everypay_string($verify));
      }
      return self::_VERIFY_ERROR;
		}

		return $status;
	}

 
} // end class.

?>