<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * WooCommerce EveryPay.
 *
 * @class   WC_Gateway_Everypay
 * @extends WC_Payment_Gateway
 * @version 1.0.3
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class WC_Gateway_Everypay extends WC_Payment_Gateway {

	const _VERIFY_ERROR = 0;    // HMAC mismatch or other error
	const _VERIFY_SUCCESS = 1;  // payment successful
	const _VERIFY_FAIL = 2;     // payment failed
	const _VERIFY_CANCEL = 3;   // payment cancelled by user

	protected $account_id;
	protected $transaction_type;
	protected $payment_form;
	protected $skin_name;
	protected $token_enabled;
	protected $token_ask;
	protected $sandbox;
	protected $api_endpoint;
	protected $api_username;
	protected $api_secret;
	protected $debug;
	protected $notify_url;
	protected $log;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return mixed
	 */
	public function __construct() {
		$this->id         = 'everypay';
		$this->icon       = apply_filters( 'woocommerce_gateway_everypay_icon', plugins_url( '/assets/images/mastercard_visa.png', dirname( __FILE__ ) ) );
		$this->has_fields = true;

		$this->order_button_text = __( 'Pay by card', 'everypay' );

		// Title/description for WooCommerce admin
		$this->method_title       = __( 'EveryPay', 'everypay' );
		$this->method_description = __( 'Card payments are provided by EveryPay', 'everypay' );

		// URL for callback / user redirect from gateway
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_Everypay' );

		$this->supports = array( 'products' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->enabled = $this->get_option( 'enabled' );

		// Title/description for payment method selection page
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->account_id = $this->get_option( 'account_id' );
		// implemented initially, but removed in favor of 'capture delay' that can be confed in merchant portal
		// $this->transaction_type = $this->get_option( 'transaction_type' );
		$this->transaction_type = 'charge';
		$this->payment_form     = $this->get_option( 'payment_form' );
		$this->skin_name        = $this->get_option( 'skin_name' );
		$this->token_enabled    = $this->get_option( 'token_enabled' ) === 'yes' ? true : false;
		// asking for permission is not optional for regulatory reasons
		// $this->token_ask        = $this->get_option( 'token_ask' ) === 'yes' ? true : false;
		$this->token_ask    = true;
		$this->sandbox      = $this->get_option( 'sandbox' ) === 'yes' ? true : false;
		$this->api_endpoint = $this->sandbox === false ? 'https://pay.every­-pay.eu/transactions/' : 'https://igw-demo.every-pay.com/transactions/';
		$this->api_username = $this->sandbox === false ? $this->get_option( 'api_username' ) : $this->get_option( 'sandbox_api_username' );
		$this->api_secret   = $this->sandbox === false ? $this->get_option( 'api_secret' ) : $this->get_option( 'sandbox_api_secret' );

		// Log is created always for main transaction points - debug option adds more logging points during transaction
		$this->debug = $this->get_option( 'debug' );
		$this->log   = new WC_Logger();


		// Hooks
		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'checks' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}

		// Receipt page creates POST to gateway or hosts iFrame
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// If receipt page hosts iFrame and is being shown enqueue required JS

		// moving registering scripts to iFrame receipt_page so they can be easily loaded for redirect mode's hidden iframe
		/*		if ( is_wc_endpoint_url( 'order-pay' ) ) {
					if ( $this->payment_form === 'iframe' ) {
						add_action( 'wp_enqueue_scripts', array( $this, 'script_manager' ) );
					}
				}*/

		// Add returning user / callback handler to WC API
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'everypay_return_handler' ) );

		// Add token management to user's account page
		add_action( 'woocommerce_after_my_account', array( $this, 'generate_account_page_html' ) );

	}

	/**
	 * Register scripts for front-end
	 *
	 * @access public
	 * @return void
	 */

	public function script_manager() {

		// wp_register_script template ( $handle, $src, $deps, $ver, $in_footer );
		wp_register_script( 'wc-everypay-iframe', plugins_url( '/assets/js/everypay-iframe-handler.js', dirname( __FILE__ ) ), array( 'jquery' ), false, true );
		wp_enqueue_script( 'wc-everypay-iframe' );

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
		<p><a href="https://portal.every-pay.eu/"><?= __( 'Merchant Portal', 'everypay' ); ?></a> | <a
				href="https://every-pay.com/contact/"><?= __( 'Contacts', 'everypay' ); ?></a> | <a
				href="https://every-pay.com/documentation-overview/"><?= __( 'Documentation', 'everypay' ); ?></a>
		</p>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
			<script type="text/javascript">
				jQuery('#woocommerce_everypay_sandbox').change(function () {
					var sandbox = jQuery('#woocommerce_everypay_sandbox_api_username, #woocommerce_everypay_sandbox_api_secret').closest('tr');

					if (jQuery(this).is(':checked')) {
						sandbox.show("slow");
					} else {
						sandbox.hide("slow");
					}
				}).change();

				jQuery('#woocommerce_everypay_payment_form').change(function () {
					var skinname = jQuery('#woocommerce_everypay_skin_name').closest('tr');

					if (jQuery(this).val() == 'iframe') {
						skinname.show("slow");
					} else {
						skinname.hide("slow");
					}
				}).change();

				jQuery('#woocommerce_everypay_token_enabled').change(function () {
					var tokenask = jQuery('#woocommerce_everypay_token_ask').closest('tr');

					if (jQuery(this).is(':checked')) {
						tokenask.show("slow");
					} else {
						tokenask.hide("slow");
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
		if ( $this->enabled == 'no' ) {
			return;
		}

		// PHP Version.
		if ( version_compare( phpversion(), '5.3', '<' ) ) {
			echo '<div class="error" id="wc_everypay_notice_phpversion"><p>' . sprintf( __( 'EveryPay Error: EveryPay requires PHP 5.3 and above. You are using version %s.', 'everypay' ), phpversion() ) . '</p></div>';
		} // Check required fields.
		else if ( ! $this->api_username || ! $this->api_secret ) {
			if ( $this->sandbox === false ) {
				echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . __( 'EveryPay Error: Please enter your API username and secret.', 'everypay' ) . '</p></div>';
			} else {
				echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . __( 'EveryPay Error: Please enter your TEST API username and secret.', 'everypay' ) . '</p></div>';
			}
		}
		if ( ! $this->account_id ) {
			echo '<div class="error" id="wc_everypay_notice_account"><p>' . __( 'EveryPay Error: Please enter your Processing Account.', 'everypay' ) . '</p></div>';
		}

		// warn about test payments
		if ( $this->sandbox === true ) {
			echo '<div class="update-nag" id="wc_everypay_notice_sandbox"><p>' . __( "EveryPay payment gateway is in test mode, real payments not processed!", 'everypay' ) . '</p></div>';
		}

		// warn about unsecure use if: iFrame in use and either WC force SSL or plugin with same effect is not active (borrowing logics from Stripe)
		if ( ( $this->payment_form === 'iframe' ) && ( get_option( 'woocommerce_force_ssl_checkout' ) === 'no' ) && ! class_exists( 'WordPressHTTPS' ) ) {
			echo '<div class="error" id="wc_everypay_notice_ssl"><p>' . sprintf( __( 'EveryPay iFrame mode is enabled, but your checkout is not forced to use HTTPS. While EveryPay iFrame remains secure users may feel insecure due to missing confirmation in browser address bar. Please <a href="%s">enforce SSL</a> and ensure your server has a valid SSL certificate!', 'everypay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}

	}


	/**
	 * Check if this gateway is enabled.
	 *
	 * @access public
	 */
	public function is_available() {
		if ( $this->enabled == 'no' ) {
			return false;
		}

		if ( ! $this->api_username || ! $this->api_secret ) {
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

		$translation_notice = '';

		if ( function_exists( 'icl_object_id' ) ) {
			$translation_notice = ' ' . __( 'For translation with WPML use English here and translate in String Translation. Detailed instructions <a href="https://wpml.org/documentation/support/translating-woocommerce-sites-default-language-english/">here</a>.', 'everypay' );
		}

		$this->form_fields = array(
			'enabled'              => array(
				'title'       => __( 'Enable/Disable', 'everypay' ),
				'label'       => __( 'Enable EveryPay', 'everypay' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'payment_form'         => array(
				'title'       => __( 'Payment form type', 'everypay' ),
				'type'        => 'select',
				'options'     => array(
					'redirect' => __( 'Redirect to hosted form on EveryPay server', 'everypay' ),
					'iframe'   => __( 'iFrame payment form integrated into checkout', 'everypay' ),
				),
				'description' => __( "Hosted form on EveryPay server is the secure solution of choice, while iFrame provides better customer experience (https strongly advised)", 'everypay' ),
				'default'     => 'redirect',
				'desc_tip'    => false,
			),
			'skin_name'            => array(
				'title'       => __( 'Skin name', 'everypay' ),
				'type'        => 'text',
				'class'       => 'everypay_iframe_option',
				'description' => __( "Appearance of payment area can be set up in EveryPay Merchant Portal, under 'Settings' > 'iFrame skins'", 'everypay' ),
				'default'     => 'default'
			),
			'token_enabled'        => array(
				'title'       => __( 'Saved cards', 'everypay' ),
				'label'       => __( 'Enable payments with saved cards', 'everypay' ),
				'type'        => 'checkbox',
				'description' => __( "When card token payments are enabled users get an option to store reference to credit card and can make future purchases without need to enter card details.", 'everypay' ),
				'default'     => 'no'
			),
			// for regulatory reasons asking is currently mandatory
			/*			'token_ask'            => array(
							'title'       => __( 'Ask when storing cards', 'everypay' ),
							'label'       => __( 'Storing cards is user-selectable', 'everypay' ),
							'type'        => 'checkbox',
							'description' => __( "When this option is cleared all purchases store card token on user's account.", 'everypay' ),
							'default'     => 'yes'
						),*/
			'title'                => array(
				'title'       => __( 'Title', 'everypay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'everypay' ) . $translation_notice,
				'default'     => __( 'Card payment', 'everypay' )
			),
			'description'          => array(
				'title'       => __( 'Description', 'everypay' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'everypay' ) . $translation_notice,
				'default'     => __( "Card payments are provided by EveryPay", 'everypay' )
			),
			'account_id'           => array(
				'title'       => __( 'Processing Account', 'everypay' ),
				'type'        => 'text',
				'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'Processing accounts' section (looks like: EUR1).", 'everypay' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			// implemented initially, but removed in favor of 'capture delay' that can be confed in merchant portal
			/*			'transaction_type'     => array(
							'title'       => __( 'Transaction Type', 'everypay' ),
							'type'        => 'select',
							'options'     => array(
								'charge'        => __( 'Charge', 'everypay' ),
								'authorisation' => __( 'Authorisation', 'everypay' ),
							),
							'description' => __( "Authorisation: A process of getting approval for the current payment. Funds are reserved on cardholder's account. Charge: An authorisation followed by immediate automatic capture.", 'everypay' ),
							'default'     => 'charge',
							'desc_tip'    => false,
						),*/
			'api_username'         => array(
				'title'       => __( 'API username', 'everypay' ),
				'type'        => 'text',
				'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'General settings' section (looks like: 49bcdd6fed983c12).", 'everypay' ),
				'default'     => '',
				'desc_tip'    => false
			),
			'api_secret'           => array(
				'title'       => __( 'API secret', 'everypay' ),
				'type'        => 'text',
				'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'General settings' section (looks like: e7ed4e55d5f73158f6cf2890fb1c950e).", 'everypay' ),
				'default'     => '',
				'desc_tip'    => false
			),
			'debug'                => array(
				'title'       => __( 'Debug Log', 'everypay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'everypay' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log EveryPay events inside <code>%s</code>', 'everypay' ), wc_get_log_file_path( $this->id ) )
			),
			'sandbox'              => array(
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
			'sandbox_api_secret'   => array(
				'title'       => __( 'Test API secret', 'everypay' ),
				'type'        => 'text',
				'description' => __( 'Optional: API secret for testing payments.', 'everypay' ),
				'default'     => '',
				'desc_tip'    => false
			),
		);
	}

	public function validate_text_field( $key, $value = null ) {
		if( in_array( $key, array('account_id', 'api_username', 'api_secret', 'sandbox_api_username', 'sandbox_api_secret') ) ) {
			$field = $this->get_field_key( $key );
			if ( isset( $_POST[ $field ] ) ) {
				$value = trim( wp_strip_all_tags( stripslashes( $_POST[ $field ] ) ) );
			}
			return $value;
		} else {
			return parent::validate_text_field( $key, $value );
		}
	}

	public function payment_fields() {

		if ( ! empty( $this->description ) ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( ! is_user_logged_in() && $this->token_enabled ) {
			echo wpautop( __( 'To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'everypay' ) );
		}

		if ( $this->token_enabled ) {
			$this->credit_card_form();
		}

	}

	public function credit_card_form( $args = array(), $fields = array() ) {

		?>
		<fieldset id="<?php echo $this->id; ?>-cc-form">
			<?php
			do_action( 'woocommerce_credit_card_form_start', $this->id );

			$tokenized_cards_html = $this->generate_tokenized_cards_html();

			echo $tokenized_cards_html;

			do_action( 'woocommerce_credit_card_form_end', $this->id );
			?>
			<div class="clear"></div>
			<?php echo $this->generate_tokenize_checkbox_html( ! empty( $tokenized_cards_html ) ); ?>
		</fieldset>
		<script type="text/javascript">
			jQuery('input#createaccount').change(function () {
				var tokenize = jQuery('#wc_everypay_tokenize_payment').closest('p.form-row');

				if (jQuery(this).is(':checked')) {
					tokenize.show("slow");
				} else {
					tokenize.hide("slow");
				}
			}).change();
			jQuery('input[name=wc_everypay_token]').change(function () {
				var tokenize = jQuery('#wc_everypay_tokenize_payment').closest('p.form-row');
				if (jQuery(this).val() == 'add_new') {
					tokenize.show("slow");
				} else {
					tokenize.hide("slow");
				}
			});
		</script>
		<?php
	}


	protected function generate_tokenize_checkbox_html( $has_tokens = false ) {

		$html = '';

		if ( $this->token_enabled ) {
			if ( ! $this->token_ask ) {
				$html .= '<input name="wc_everypay_tokenize_payment" id="wc_everypay_tokenize_payment" type="hidden" value="true" />';
			} else {

				// hidden if existing tokenized card is shown and   selected
				if ( true === $has_tokens ) {
					$display = "display: none";
				} else {
					$display = '';
				}
				$html .= '<p class="form-row form-row-wide" style="' . $display . '">';
				$html .= '<input name="wc_everypay_tokenize_payment" id="wc_everypay_tokenize_payment" type="checkbox" value="true" style="width:auto;" />';
				$html .= '<label for="wc_everypay_tokenize_payment" style="display:inline;">' . apply_filters( 'wc_everypay_tokenize_payment', __( 'Save card securely', 'everypay' ) ) . '</label>';
				$html .= '</p>';
			}
		}

		return $html;
	}

	protected function generate_tokenized_cards_html() {

		$html = '';

		if ( $this->token_enabled ) {

			$tokens = $this->get_user_tokens();


			if ( ! empty( $tokens ) ) {
				$html .= '<p class="form-row form-row-wide">';

				foreach ( $tokens as $token ) {

					if ( true === $token['active'] ) {
						$checked = '';

						if ( isset( $token['default'] ) && true === $token['default'] ) {
							$checked = 'checked="checked"';
						}
						$html .= '<input type="radio" id="wc_everypay_token_' . $token['cc_token'] . '" name="wc_everypay_token" class="wc_everypay_token" style="width:auto;" value="' . $token['cc_token'] . '" ' . $checked . '/>';

						$html .= '<label class="wc_everypay_token_label" for="wc_everypay_token_' . $token['cc_token'] . '" style="display: inline-block">';

						$html .= '<img src="' . plugins_url( '/assets/images/', dirname( __FILE__ ) ) . $token['cc_type'] . '.png" alt="' . $this->get_token_type_fullname( $token['cc_type'] ) . '" width="47" height="30" style="padding-right: 0.5em"> ';
						// $html .= $this->get_token_type_fullname( $token['cc_type'] );
						if ( ! empty( $token['cc_last_four_digits'] ) ) {
							$html .= ' **** **** **** ' . $token['cc_last_four_digits'];
						}

						if ( ! empty( $token['cc_month'] ) && ! empty( $token['cc_year'] ) ) {
							$html .= ' (' . __( 'expires', 'everypay' ) . ' ' . str_pad($token['cc_month'], 2, '0', STR_PAD_LEFT) . '/' . $token['cc_year'] . ')';
						}


						$html .= '</label><br />';
					}

				}

				$html .= $this->generate_add_or_manage_cards_html();
				$html .= '</p>';

			}


		}

		return $html;
	}

	public function get_user_tokens() {

		if ( ( true === $this->token_enabled ) && is_user_logged_in() ) {
			$tokens = maybe_unserialize( get_user_meta( get_current_user_id(), '_wc_everypay_tokens', true ) );
		}

		if ( empty( $tokens ) ) {
			$tokens = array( );
		}


		/*		$tokens = array(
					array(
						'cc_token'            => 'e54aa3d4c766ac3f1584be20',
						'cc_last_four_digits' => '1234',
						'cc_year'             => '2017',
						'cc_month'            => '1',
						'cc_type'             => 'visa',
						'added'               => 1452440229,

					),
					array(
						'cc_token'            => 'e54aa3d4c766ac3f1584be20',
						'cc_last_four_digits' => '2335',
						'cc_year'             => '2015',
						'cc_month'            => '11',
						'cc_type'             => 'master_card',
						'added'               => 1452441229,
						'default'             => true,
					),
				);*/

		// mark expired cards as inactive - should not be used and marked default, can be deleted

		$now = new DateTime();

		foreach ( $tokens as $key => $token ) {

			$cc_expires = DateTime::createFromFormat( 'n Y', $token['cc_month'] . ' ' . $token['cc_year'] );

			if ( $cc_expires < $now ) {
				$tokens[ $key ]['active']  = false;
				$tokens[ $key ]['default'] = false; // can't be default anymore
			} else {
				$tokens[ $key ]['active'] = true;
			}
		}

		return $tokens;
	}


	public function remove_user_token( $token = '' ) {

		$tokens = $this->get_user_tokens();

		if ( ! empty( $tokens[ $token ] ) ) {

			// handle removal of default card by possibly assigning one added before that

			if ( isset( $tokens[ $token ]['default'] ) && true === $tokens[ $token ]['default'] ) {

				unset( $tokens[ $token ] );

				// try finding new default
				$latest      = 0;
				$new_default = null;

				foreach ( $tokens as $key => $token ) {
					if ( true === $token['active'] && $token['added'] > $latest ) {
						$latest      = $token['added'];
						$new_default = $key;
					}
				}

				if ( ! is_null( $new_default ) ) {
					$tokens[ $new_default ]['default'] = true;
				}

			} else {
				unset( $tokens[ $token ] );
			}

			return (bool) update_user_meta( get_current_user_id(), '_wc_everypay_tokens', $tokens );

		}

		return false;

	}

	public function set_user_token_default( $token = '' ) {

		$tokens = $this->get_user_tokens();

		if ( ! empty( $tokens[ $token ] ) ) {

			foreach ( $tokens as $key => $value ) {
				$tokens[ $key ]['default'] = false;
			}

			$tokens[ $token ]['default'] = true;

			return (bool) update_user_meta( get_current_user_id(), '_wc_everypay_tokens', $tokens );

		}

		return false;

	}


	protected function get_token_type_fullname( $type ) {

		$cc_types = array(
			'visa'        => "Visa",
			'master_card' => "MasterCard",
		);

		$fullname = isset( $cc_types[ $type ] ) ? $cc_types[ $type ] : '';

		return $fullname;
	}


	protected function generate_add_or_manage_cards_html() {

		$html = '';

		$html .= '<input type="radio" id="wc_everypay_token_add" name="wc_everypay_token" class="wc_everypay_token" style="width:auto;" value="add_new" />';
		$html .= '<label class="wc_everypay_token_label" for="wc_everypay_token_add" style="display: inline-block">' . __( 'Use a new card', 'everypay' ) . '</label>';

		$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
		if ( $myaccount_page_id ) {
			$myaccount_page_url = get_permalink( $myaccount_page_id );
			$html .= '<a style="float:right;" href="' . $myaccount_page_url . '#saved-cards" class="wc_everypay_manage_cards" target="_blank">' . __( 'Manage cards', 'everypay' ) . '</a>';
		}

		return $html;
	}

	/**
	 * Run on submitting the checkout page - process payment fields and redirect to payment e.g receipt page
	 *
	 * @access public
	 *
	 * @param  int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		if ( true === $this->token_enabled ) {

			// expected in POST:
			// 'wc_everypay_token' => '123456789abc123456789def',
			// 'wc_everypay_tokenize_payment' => 'true',

			if ( trim( $_POST['wc_everypay_token'] ) !== false ) {
				update_post_meta( $order_id, '_wc_everypay_token', trim( $_POST['wc_everypay_token'] ) );
			} else {
				delete_post_meta( $order_id, '_wc_everypay_token' );
			}

			if ( true === $this->token_ask ) {

				if ( isset( $_POST['wc_everypay_tokenize_payment'] ) && trim( $_POST['wc_everypay_tokenize_payment'] ) !== false ) {
					$tokenize = trim( $_POST['wc_everypay_tokenize_payment'] ) === 'true' ? true : false;
					update_post_meta( $order_id, '_wc_everypay_tokenize_payment', $tokenize );
				} else {
					update_post_meta( $order_id, '_wc_everypay_tokenize_payment', false );
				}

			} else {
				update_post_meta( $order_id, '_wc_everypay_tokenize_payment', true );
			}

		}

		// Redirect to receipt page for automatic post to external gateway

		if ( $this->debug == 'yes' ) {
			$this->log->add( $this->id, 'EveryPay selected for order #' . $order_id );
		}

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);

	}


	/**
	 * Output redirect or iFrame form on receipt page
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );
		$args  = $this->get_everypay_args( $order );

		// iFrame should be used IF configured in settings OR doing payment with existing token
		// (logic currently implemented in get_everypay_args)
		if ( isset( $args['skin_name'] ) ) {
			$this->script_manager();
			echo $this->generate_iframe_form_html( $args, $order );
		} else {
			// defaults to redirect
			echo $this->generate_redirect_form_html( $args, $order );
		}
	}


	/**
	 * Prepare data package for signing
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function get_everypay_args( $order ) {

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$language = ICL_LANGUAGE_CODE;
		} else {
			switch ( get_locale() ) {
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

		$args = array(
			'account_id'        => $this->account_id,
			'amount'            => number_format( $order->get_total(), 2, '.', '' ),
			'api_username'      => $this->api_username,
			'billing_address'   => $order->billing_address_1,
			'billing_city'      => $order->billing_city,
			'billing_country'   => $order->billing_country,
			'billing_postcode'  => $order->billing_postcode,
			'callback_url'      => WC()->api_request_url( 'WC_Gateway_Everypay' ),
			'customer_url'      => WC()->api_request_url( 'WC_Gateway_Everypay' ),
			'delivery_address'  => $order->shipping_address_1,
			'delivery_city'     => $order->shipping_city,
			'delivery_country'  => $order->shipping_country,
			'delivery_postcode' => $order->shipping_postcode,
			'email'             => $order->billing_email,
			'nonce'             => uniqid( '', true ),
			'order_reference'   => $order->id . '_' . date( DATE_W3C ),
			'request_cc_token'  => '0',
			'timestamp'         => time(),
			'transaction_type'  => $this->transaction_type,
			'user_ip'           => $_SERVER['REMOTE_ADDR'],
		);

		// handle iFrame skin
		if ( $this->payment_form === 'iframe' ) {
			$args['skin_name'] = $this->skin_name;
		}

		// handle request / provide token
		if ( $this->token_enabled ) {
			$token = get_post_meta( $order->id, '_wc_everypay_token', true );

			if ( empty ( $token ) || ( $token === 'add_new' ) ) {
				if ( true === $this->token_ask ) {
					$args['request_cc_token'] = (bool) get_post_meta( $order->id, '_wc_everypay_tokenize_payment', true ) ? '1' : '0';
				} else {
					$args['request_cc_token'] = '1';
				}
			} else {
				$args['cc_token'] = $token;
				// payments with existing token are always (hidden) iFrame payments
				$args['skin_name'] = $this->skin_name;
			}
		}

		// prepare and sign fields string
		$args['hmac_fields'] = '';

		ksort( $args );

		$args['hmac_fields'] = implode( ',', array_keys( $args ) );

		$args['hmac'] = $this->sign_everypay_request( $this->prepare_everypay_string( $args ) );

		$args['locale'] = $language;

		if ( $this->debug == 'yes' ) {
			$this->log->add( $this->id, 'EveryPay payment request prepared and signed. ' . print_r( $args, true ) );
		}

		return $args;
	}

	/**
	 * Prepare EveryPay data package for signing
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected function prepare_everypay_string( array $args ) {
		$arr = array( );
		ksort( $args );
		foreach ( $args as $k => $v ) {
			$arr[] = $k . '=' . $v;
		}

		$str = implode( '&', $arr );

		return $str;
	}

	/**
	 * Sign EveryPay payment request
	 *
	 * @param string $request
	 *
	 * @return string
	 */

	protected function sign_everypay_request( $request ) {
		return hash_hmac( 'sha1', $request, $this->api_secret );
	}

	/**
	 * Process returning user or automatic callback
	 *
	 */

	public function everypay_return_handler() {

		global $woocommerce;
		@ob_clean();

		header( 'HTTP/1.1 200 OK' );

		$_REQUEST = stripslashes_deep( $_REQUEST );

		if ( ! isset( $_REQUEST['api_username'] ) ||
		     ! isset( $_REQUEST['nonce'] ) ||
		     ! isset( $_REQUEST['order_reference'] ) ||
		     ! isset( $_REQUEST['payment_state'] ) ||
		     ! isset( $_REQUEST['timestamp'] ) ||
		     ! isset( $_REQUEST['transaction_result'] )
		) {
			die();
		}

		$order_explosive = explode( '_', $_REQUEST['order_reference'] );

		$order_id = absint( $order_explosive[0] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->log->add( $this->id, 'Invalid order ID received: ' . print_r( $order_id, true ) );
			die( "Order was lost during payment attempt - please inform merchant about WooCommerce EveryPay gateway problem." );
		}

		if ( $this->debug == 'yes' ) {
			$this->log->add( $this->id, 'EveryPay return handler started. ' . print_r( $_REQUEST, true ) );
			$this->log->add( $this->id, 'Order ' . var_export( $order, true ) );
		}

		$this->change_language( $order->id );

		$order_complete = $this->process_order_status( $order );

		if ( self::_VERIFY_SUCCESS === $order_complete ) {
			$this->log->add( $this->id, 'Order complete' );
			$redirect_url = $this->get_return_url( $order );

		} else {

			switch ( $order_complete ) {
				case self::_VERIFY_FAIL:
					$order->update_status( 'failed', __( 'Payment was declined. Please verify the card data and try again with the same or different card.', 'everypay' ) );
					$this->log->add( $this->id, 'Payment was declined by payment processor.' );
					$redirect_url = $woocommerce->cart->get_checkout_url();
					break;
				case self::_VERIFY_CANCEL:
					$order->cancel_order( __( 'Payment cancelled.', 'everypay' ) );
					$this->log->add( $this->id, 'Payment was cancelled by user.' );
					$redirect_url = $order->get_cancel_order_url();
					break;
				default:
					$order->update_status( 'failed', __( 'An error occurred while processing the payment response, please notify merchant!', 'everypay' ) );
					$this->log->add( $this->id, 'An error occurred while processing the payment response.' );
					$redirect_url = $woocommerce->cart->get_checkout_url();
					break;
			}
		}

		if ( $this->debug == 'yes' ) {
			$this->log->add( $this->id, 'Redirected to ' . $redirect_url );
		}
		
		wp_redirect( $redirect_url );

	}

	public function change_language( $order_id ) {

		if ( function_exists( 'icl_object_id' ) ) {

			// adapted from WooCommerce Multilingual /inc/emails.class.php
			$lang = get_post_meta( $order_id, 'wpml_language', true );

			if ( ! empty( $lang ) ) {
				global $sitepress, $woocommerce;
				$sitepress->switch_lang( $lang, true );
				unload_textdomain( 'woocommerce' );
				unload_textdomain( 'default' );
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
	 *
	 * @return bool
	 */
	public function process_order_status( $order ) {

		$result = $this->verify_everypay_response( $_REQUEST );

		if ( self::_VERIFY_SUCCESS === $result ) {
			// Payment complete
			$order->payment_complete( $_REQUEST['payment_reference'] );
			// Add order note
			$order->add_order_note( sprintf( __( 'Card payment was successfully processed by EveryPay (Reference: %s, Timestamp: %s)', 'everypay' ), $_REQUEST['payment_reference'], $_REQUEST['timestamp'] ) );
			// Store the transaction ID for WC 2.2 or later.
			add_post_meta( $order->id, '_transaction_id', $_REQUEST['payment_reference'], true );

			$this->maybe_add_token( $order );

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
	 * array(
	 * 'account_id' => account id in EveryPay system
	 * 'amount' => amount to pay,
	 * 'api_username' => api username,
	 * 'nonce' => return nonce
	 * 'order_reference' => order reference number,
	 * 'payment_reference' => payment reference number,
	 * 'payment_state' => payment state,
	 * 'timestamp' => timestamp,
	 * 'transaction_result' => transaction result
	 * );
	 *
	 * for cancelled payments:
	 *
	 * array(
	 * 'api_username' => api username,
	 * 'nonce' => return nonce
	 * 'order_reference' => order reference number,
	 * 'payment_state' => payment state,
	 * 'timestamp' => timestamp,
	 * 'transaction_result' => transaction result
	 * );
	 *
	 * @param $data array
	 *
	 * @return int 1 - verified successful payment, 2 - verified failed payment, 3 - user cancelled, 0 - error
	 * @throws Exception
	 */
	public function verify_everypay_response( array $data ) {

		$statuses = array(
			'settled' => self::_VERIFY_SUCCESS,
			'authorised' => self::_VERIFY_SUCCESS,
			'failed'    => self::_VERIFY_FAIL,
			'cancelled' => self::_VERIFY_CANCEL,
			'waiting_for_3ds_response' => self::_VERIFY_CANCEL,
		);


		if ( $data['api_username'] !== $this->api_username ) {
			if ( $this->debug == 'yes' ) {
				$this->log->add( $this->id, 'EveryPay error: API username in response does not match, order not completed!' );
			}

			return self::_VERIFY_ERROR;
		}

		$now = time()+60;
		if ( ( $data['timestamp'] > $now ) || ( $data['timestamp'] < ( $now - 600 ) ) ) {
			if ( $this->debug == 'yes' ) {
				$this->log->add( $this->id, 'EveryPay error: response is older than 10 minutes, order not completed!' );
			}

			return self::_VERIFY_ERROR;
		}

		$status = $statuses[ $data['payment_state'] ];

		$verify      = array();
		$hmac_fields = explode( ',', $data["hmac_fields"] );

		foreach ( $hmac_fields as $value ) {
			$verify[ $value ] = isset( $data[ $value ] ) ? $data[ $value ] : '';
		}

		if ( $this->debug == 'yes' ) {
			$this->log->add( $this->id, '$verify array: ' . var_export( $verify, true ) );
		}

		$hmac = $this->sign_everypay_request( $this->prepare_everypay_string( $verify ) );

		if ( $data['hmac'] != $hmac ) {
			if ( $this->debug == 'yes' ) {
				$this->log->add( $this->id, 'EveryPay error: signature does not match data! ' . print_r( $data, true ) . print_r( $hmac, true ) . print_r( $this->prepare_everypay_string( $verify ), true ) );
			}

			return self::_VERIFY_ERROR;
		}

		return $status;
	}

	/**
	 * Build iFrame form for receipt page
	 *
	 * @param array $args
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	protected function generate_iframe_form_html( $args, $order ) {

		global $woocommerce;

		$html         = '';
		$cancel_style = '';

		// if doing a token payment hide the iFrame (and commnunicate through messages) until there is a direct api solution
		if ( empty( $args['cc_token'] ) ) {
			$html .= '<div class="wc_everypay_iframe_form_detail" id="wc_everypay_iframe_payment_container" style="border: 0; min-width: 460px; min-height: 325px;">' . PHP_EOL;
		} else {
			$html .= '<div class="wc_everypay_iframe_messager" id="wc_everypay_iframe_messager">';
			$html .= apply_filters( 'wc_everypay_iframe_processing', __( 'Processing payment with saved card...', 'everypay' ) );
			$html .= '</div>' . PHP_EOL;
			$html .= '<div class="wc_everypay_iframe_form_detail" id="wc_everypay_iframe_payment_container" style="display: none;">' . PHP_EOL;
			$cancel_style = 'style="display: none;"';
		}

		$html .= '<iframe id="wc_everypay_iframe" name="wc_everypay_iframe" width="460" height="400" style="border: 0;"></iframe>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;
		$html .= '<form action="' . $this->api_endpoint . '" id="wc_everypay_iframe_form" method="post" style="display: none" target="wc_everypay_iframe">' . PHP_EOL;
		$args_array = array( );

		foreach ( $args as $key => $value ) {
			$args_array[] = '<input name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
		$html .= implode( PHP_EOL, $args_array ) . PHP_EOL;
		$html .= '<input type="submit" value="submit">' . PHP_EOL;
		$html .= '</form>' . PHP_EOL;


		$html .= '<div id="wc_everypay_iframe_buttons">' . PHP_EOL;

		// cancel is hidden for token payments, retry for all payments - enabling happens on payment fail
		$html .= '<a href="' . esc_url( $order->get_cancel_order_url() ) . '" id="wc_everypay_iframe_cancel" class="button cancel" ' . $cancel_style . '>'
		         . apply_filters( 'wc_everypay_iframe_cancel', __( 'Cancel order', 'everypay' ) ) . '</a> ';
		$html .= '<a href="' . esc_url( $woocommerce->cart->get_checkout_url() ) . '" id="wc_everypay_iframe_retry" class="button alt" style="display: none;">'
		         . apply_filters( 'wc_everypay_iframe_retry', __( 'Try paying again', 'everypay' ) ) . '</a>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;


		// used during testing to display iFrame message:
		// echo '<div class="transaction_result"></div>' . PHP_EOL;

		$params = array(
			'uri'       => 'https://' . parse_url( $this->api_endpoint, PHP_URL_HOST ),
			'completed' => $this->get_return_url( $order ),
			// 'failed' => $order->get_cancel_order_url(),
			'sandbox'   => $this->sandbox,
		);

		wp_localize_script( 'wc-everypay-iframe', 'wc_everypay_params', $params );

		return $html;
	}

	/**
	 * Build redirect form & autosubmit for receipt page
	 *
	 * @param array $args
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	protected function generate_redirect_form_html( $args, $order ) {

		$html = '';

		$args_array = array( );

		foreach ( $args as $key => $value ) {
			$args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		$html .= '<p id="wc_everypay_redirect_explanation">' . apply_filters( 'wc_everypay_redirect_explanation', __( 'Thank you for your order. Please click the button below to complete the card payment.', 'everypay' ) ) . '</p>';

		$html .= '<form action="' . esc_url( $this->api_endpoint ) . '" method="post" id="payment_form" target="_top">';

		$html .= implode( '', $args_array );
		$html .= '<input type="submit" class="button alt" id="wc_everypay_redirect_pay" value="'
		         . apply_filters( 'wc_everypay_redirect_pay', __( 'Pay by card', 'everypay' ) ) . '">
  		      <a class="button cancel" id="wc_everypay_redirect_cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">'
		         . apply_filters( 'wc_everypay_redirect_cancel', __( 'Cancel order &amp; restore cart', 'everypay' ) ) . '</a>';

		$html .= '</form>';

		wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( apply_filters( 'wc_everypay_redirect_message', __( 'Thank you for your order. We are now redirecting you to payment gateway.', 'everypay' ) ) ) . '",
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
							lineHeight:		"24px",
						}
					});
				$("#wc_everypay_redirect_pay").click();
			' );

		return $html;
	}

	/**
	 * Maybe add new token to user - when processing callback
	 *
	 * @param WC_order $order
	 */
	protected function maybe_add_token( $order ) {

		if ( $this->token_enabled && ! empty( $_REQUEST['cc_token'] ) && ! empty( $_REQUEST['cc_last_four_digits'] ) && ! empty( $_REQUEST['cc_year'] ) && ! empty( $_REQUEST['cc_month'] ) && ! empty( $_REQUEST['cc_type'] ) ) {
			// 'return to merchant' may have token, but does not carry rest of the information needed to build card selector on next purchase
			$user = $order->get_user();

			if ( isset( $user->ID ) ) {
				$tokens = maybe_unserialize( get_user_meta( $user->ID, '_wc_everypay_tokens', true ) );

				if ( empty( $tokens ) ) {
					$tokens = array( );
				}

				if ( ! isset( $tokens[ $_REQUEST['cc_token'] ] ) ) {

					$new_token = array(
						'cc_token'            => $_REQUEST['cc_token'],
						'cc_last_four_digits' => $_REQUEST['cc_last_four_digits'],
						'cc_year'             => $_REQUEST['cc_year'],
						'cc_month'            => $_REQUEST['cc_month'],
						'cc_type'             => $_REQUEST['cc_type'],
						'default'             => false,
						'added'               => time(),
					);

					if ( 0 === count( $tokens ) ) {
						$new_token['default'] = true;
					}

					$tokens[ $_REQUEST['cc_token'] ] = $new_token;
					update_user_meta( $user->ID, '_wc_everypay_tokens', $tokens );
				}
			}

		}
	}


	public function generate_account_page_html() {
		// unused
	}


} // end class.
