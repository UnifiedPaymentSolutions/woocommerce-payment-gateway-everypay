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
    $this->payment_form   = $this->get_option( 'payment_form' );
    $this->skin_name      = $this->get_option( 'skin_name' );
    $this->sandbox        = $this->get_option( 'sandbox' );
    $this->api_endpoint   = $this->sandbox == 'no' ? 'https://pay.every­-pay.eu/transactions/' : 'https://igw-demo.every-pay.com/transactions/';
    $this->api_username   = $this->sandbox == 'no' ? $this->get_option( 'api_username' ) : $this->get_option( 'sandbox_api_username' );
    $this->api_secret     = $this->sandbox == 'no' ? $this->get_option( 'api_secret' ) : $this->get_option( 'sandbox_api_secret' );

    // Log is created always for main transaction points - debug option adds more logging points during transaction
    $this->debug          = $this->get_option( 'debug' );

    if( class_exists( 'WC_Logger' ) ) {
      $this->log = new WC_Logger();
    }
    else {
      $this->log = $woocommerce->logger();
    }


    // Hooks
    if( is_admin() ) {
      add_action( 'admin_notices', array( $this, 'checks' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    // Receipt page creates POST to gateway or hosts iFrame
    add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

    // If receipt page hosts iFrame and is being shown enqueue required JS

    if ( $this->payment_form === 'iframe' && is_wc_endpoint_url( 'order-pay' ) ) {
      add_action('wp_enqueue_scripts', array ($this, 'script_manager') );
    }

    // Displays additional information about payment on thankyou page and confirmation email
    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

    // Add returning user / callback handler to WC API
    add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'everypay_return_handler'));

  }


  /**
   * Register scripts for front-end
   *
   * @access public
   * @return void
   */

  public function script_manager() {

  	// wp_register_script template ( $handle, $src, $deps, $ver, $in_footer );
  	wp_register_script('wc-everypay-iframe', plugins_url( '/assets/js/everypay-iframe-handler.js', dirname( __FILE__ ) ), array('jquery'), false, true);
  	wp_enqueue_script ('wc-everypay-iframe');

  }

  /**
   * Register scripts for admin
   *
   * @access public
   * @return void
   */

  public function admin_script_manager() {

  	// wp_register_script template ( $handle, $src, $deps, $ver, $in_footer );

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
          jQuery( '#woocommerce_everypay_sandbox' ).change( function () {
            var sandbox = jQuery( '#woocommerce_everypay_sandbox_api_username, #woocommerce_everypay_sandbox_api_secret' ).closest( 'tr' ),
            production  = jQuery( '#woocommerce_everypay_api_username, #woocommerce_everypay_api_secret' ).closest( 'tr' );

            if ( jQuery( this ).is( ':checked' ) ) {
              sandbox.show("slow");
              // production.hide();
            } else {
              sandbox.hide("slow");
              // production.show();
            }
          }).change();

          jQuery( '#woocommerce_everypay_payment_form' ).change( function () {
            var skinname = jQuery( '#woocommerce_everypay_skin_name' ).closest( 'tr' );

            if ( jQuery( this ).val() == 'iframe' ) {
              skinname.show("slow");
              // production.hide();
            } else {
              skinname.hide("slow");
              // production.show();
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
      echo '<div class="error id="wc_everypay_notice_phpversion""><p>' . sprintf( __( 'EveryPay Error: EveryPay requires PHP 5.3 and above. You are using version %s.', 'everypay' ), phpversion() ) . '</p></div>';
    }

    // Check required fields.
    else if( !$this->api_username || !$this->api_secret ) {
      if ( $this->sandbox == 'no' ) {
        echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . __( 'EveryPay Error: Please enter your API username and secret.', 'everypay' ) . '</p></div>';
      } else {
        echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . __( 'EveryPay Error: Please enter your TEST API username and secret.', 'everypay' ) . '</p></div>';
      }
    }

    // warn about test payments
    if ( $this->sandbox == 'yes' ) {
      echo '<div class="update-nag" id="wc_everypay_notice_sandbox"><p>' . __("EveryPay payment gateway is in test mode, real payments not processed!", 'everypay') . '</p></div>';
    }

		// warn about unsecure use if: iFrame in use and either WC force SSL or plugin with same effect is not active (borrowing logics from Stripe)
		if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && ! class_exists( 'WordPressHTTPS' ) ) {
			echo '<div class="error" id="wc_everypay_notice_ssl"><p>' . sprintf( __( 'EveryPay iFrame mode is enabled, but your checkout is not forced to use HTTPS. While EveryPay iFrame remains secure users may feel insecure due to missing confirmation in browser address bar. Please <a href="%s">enforce SSL</a> and ensure your server has a valid SSL certificate!', 'everypay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
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
      'payment_form' => array(
        'title'       => __( 'Payment form type', 'everypay' ),
        'type'        => 'select',
          'options' => array(
            'redirect'        => __( 'Redirect to hosted form on EveryPay server', 'everypay' ),
            'iframe' => __( 'iFrame payment form integrated into checkout', 'everypay' ),
          ),
        'description' => __( "Hosted form on EveryPay server is the secure solution of choice, while iFrame provides better customer experience (https strongly advised)", 'everypay' ),
        'default'     => 'iframe',
        'desc_tip'    => false,
      ),
     'skin_name' => array(
          'title' => __( 'Skin name', 'everypay' ),
          'type' => 'text',
          'class' => 'everypay_iframe_option',
          'description' => __( "Appearance of payment area can be set up in EveryPay Merchant Portal, under 'Settings' > 'iFrame skins'", 'everypay' ),
          'default' => 'default'
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
          'default' => __("Pay with credit or debit card", 'everypay')
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

		$args = $this->get_everypay_args( $order );

		if ($this->payment_form === 'iframe') {
      echo '<div class="wc_everypay_iframe_form_detail" id="wc_everypay_iframe_payment_container" style="border: 0px; min-width: 460px; min-height: 325px">' . PHP_EOL;
  		echo '<iframe id="wc_everypay_iframe" name="wc_everypay_iframe", width="460", height="400"></iframe>' . PHP_EOL;
  		echo '</div>' . PHP_EOL;
  		echo '<form action="'.$this->api_endpoint.'" id="wc_everypay_iframe_form" method="post" style="display: none" target="wc_everypay_iframe">' . PHP_EOL;
      $args_array = [];

  		foreach ($args as $key => $value) {
  			$args_array[] = '<input name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
  		}
      echo implode(PHP_EOL, $args_array) . PHP_EOL;
      echo '<input type="submit" value="submit">' . PHP_EOL;
  		echo '</form>' . PHP_EOL;

      global $woocommerce;

      echo '<div id="wc_everypay_iframe_buttons">' . PHP_EOL;
  		echo '<a href="'.esc_url( $order->get_cancel_order_url() ).'" id="wc_everypay_iframe_cancel" class="button cancel">'
  		      . apply_filters('wc_everypay_iframe_cancel', __( 'Cancel order', 'everypay' )) . '</a> ';
      echo '<a href="'.esc_url( $woocommerce->cart->get_checkout_url() ).'" id="wc_everypay_iframe_retry" class="button alt" style="display: none;">'
            . apply_filters('wc_everypay_iframe_retry', __( 'Try another payment', 'everypay' )) . '</a>' . PHP_EOL;
      echo '</div>' . PHP_EOL;


  		// used during testing only:
  		// echo '<div class="transaction_result"></div>' . PHP_EOL;

  		$is_sandbox = $this->sandbox == 'no' ? 'false' : 'true';

    	$params = array(
        'uri' => 'https://' . parse_url($this->api_endpoint, PHP_URL_HOST),
        'completed' => $this->get_return_url( $order ),
        // 'failed' => $order->get_cancel_order_url(),
        'sandbox' => $this->sandbox == 'no' ? false : true,
    	);

      wp_localize_script( 'wc-everypay-iframe', 'wc_everypay_params', $params );


		} else {
  		// defaults to redirect

  		echo '<p id="wc_everypay_redirect_explanation">' . apply_filters('wc_everypay_redirect_explanation', __( 'Thank you for your order, please click the button below to pay with credit card.', 'everypay' )) . '</p>';

      $args_array = [];

  		foreach ($args as $key => $value) {
  			$args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
  		}

			wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( apply_filters('wc_everypay_redirect_message', __( 'Thank you for your order. We are now redirecting you to payment gateway.', 'everypay' ) ) ) . '",
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
				$("#wc_everypay_redirect_pay").click();
			' );


      echo '<form action="' . esc_url( $this->api_endpoint ) . '" method="post" id="payment_form" target="_top">';
      echo implode('', $args_array);
  		echo '<input type="submit" class="button alt" id="wc_everypay_redirect_pay" value="'
  		      . apply_filters('wc_everypay_redirect_pay', __( 'Pay with credit or debit card', 'everypay' )) . '">
  		      <a class="button cancel" id="wc_everypay_redirect_cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">'
  		      . apply_filters('wc_everypay_redirect_cancel', __( 'Cancel order &amp; restore cart', 'everypay' )) . '</a>';
  		echo '</form>';
		}


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
        'email' => $order->billing_email,
        'nonce' => uniqid('', true),
        'order_reference' => $order->id . '_' . date(DATE_W3C),
        'timestamp' => time(),
        'transaction_type' => $this->transaction_type,
        'user_ip' => $_SERVER['REMOTE_ADDR'],
    ];

    if ($this->payment_form === 'iframe') {
      $args['skin_name'] = $this->skin_name;
    }

    $args['hmac_fields'] = '';

    ksort($args);

    $args['hmac_fields'] = implode(',', array_keys($args));

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

    $order_explosive = explode('_', $_REQUEST['order_reference']);

    $order_id  = absint( $order_explosive[0] );
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
		if (($data['timestamp'] > $now) || ($data['timestamp'] < ($now - 600))){
      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'EveryPay error: response is older than 10 minutes, order not completed!' );
      }
      return self::_VERIFY_ERROR;
		}

		$status = $statuses[$data['transaction_result']];

    $verify = array();
    $hmac_fields = explode(',', $data["hmac_fields"]);

    foreach ($hmac_fields as $value) {
        $verify[$value] = empty($data[$value]) ? '' : $data[$value];
    }

		$hmac = $this->sign_everypay_request($this->prepare_everypay_string($verify));

		if ($data['hmac'] != $hmac) {
      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'EveryPay error: signature does not match data! '. print_r($data, true) . print_r($hmac, true) . print_r($this->prepare_everypay_string($verify), true) );
      }
      return self::_VERIFY_ERROR;
		}

		return $status;
	}

} // end class.

?>