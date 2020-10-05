<?php

namespace Everypay;

if(!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly.

use DateTime;
use stdClass;
use WP_Locale;
use WC_Order;
use WC_Payment_Gateway;

/**
 * WooCommerce EveryPay.
 *
 * @class   Gateway
 * @extends WC_Payment_Gateway
 * @version 1.2.1
 * @package WooCommerce Payment Gateway Everypay/Includes
 * @author  EveryPay
 */
class Gateway extends WC_Payment_Gateway
{
    /**
     * @var string
     */
    public $id = 'everypay';

    /**
     * @var int
     */
    const _VERIFY_ERROR = 0;    // Other error
    const _VERIFY_SUCCESS = 1;  // payment successful
    const _VERIFY_FAIL = 2;     // payment failed
    const _VERIFY_CANCEL = 3;   // payment cancelled by user

    /**
     * @var string[]
     */
    protected $status_messages = array();

    /**
     * @var string
     */
    const TYPE_CARD = 'card';
    const TYPE_BANK = 'bank';
    const TYPE_ALTER = 'alternative';

    /**
     * @var string
     */
    const FORM_IFRAME = 'iframe';
    const FORM_REDIRECT = 'redirect';

    /**
     * @var string
     */
    const META_PREF = '_wc_everypay';

    /**
     * @var string
     */
    const META_COUNTRY = self::META_PREF . '_preferred_country';
    const META_METHOD = self::META_PREF . '_payment_method';
    const META_TOKEN = self::META_PREF . '_token';
    const META_LINK = self::META_PREF . '_payment_link';
    const META_REFERENCE = self::META_PREF . '_payment_reference';
    const META_TOKENS = self::META_PREF . '_tokens';
    const META_STATUS = self::META_PREF . '_payment_status';

    /**
     * Frontend gateway type.
     *
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $account_id;

    /**
     * @var string
     */
    protected $payment_form;

    /**
     * @var boolean
     */
    protected $sandbox;

    /**
     * @var boolean
     */
    protected $debug;
    
    /**
     * @var object[]
     */
    protected $payment_methods;

    /**
     * @var string
     */
    protected $api_endpoint;

    /**
     * @var string
     */
    protected $api_username;

    /**
     * @var string
     */
    protected $api_secret;

    /**
     * @var boolean
     */
    protected $token_enabled;

    /**
     * @var string
     */
    protected $default_country;

    /**
     * @var string
     */
    protected $skin_name;

    /**
     * @var string
     */
    protected $notify_url;

    /**
     * @var string
     */
    protected $iframe_return_url;

    /**
     * @var string
     */
    protected $customer_redirect_url;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @var string
     */
    protected $card_title;

    /**
     * @var string
     */
    protected $bank_title;

    /**
     * @var string
     */
    protected $alternative_title;

    /**
     * @var string
     */
    protected $live_endpoint = 'https://pay.every-pay.eu/api/v3';

    /**
     * @var string
     */
    protected $test_endpoint = 'https://igw-demo.every-pay.com/api/v3';

    /**
     * @var string[]
     */
    protected $cc_types = array(
        'visa'        => "Visa",
        'master_card' => "MasterCard",
    );

    /**
     * @var Api
     */
    protected $api;

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return mixed
     */
    public function __construct()
    {
        // Load the settings.
        $this->init_settings();

        $this->sandbox = $this->get_option('sandbox') === 'yes' ? true : false;

        $this->api_endpoint = $this->sandbox === false ? $this->live_endpoint : $this->test_endpoint;
        $this->api_username = $this->sandbox === false ? $this->get_option( 'api_username' ) : $this->get_option( 'sandbox_api_username' );
        $this->api_secret   = $this->sandbox === false ? $this->get_option( 'api_secret' ) : $this->get_option( 'sandbox_api_secret' );
        $this->account_id = $this->get_option( 'account_id' );

        $this->setup();

        // $this->icon = apply_filters('woocommerce_gateway_everypay_icon', plugins_url('/assets/images/mastercard_visa.png', dirname(__FILE__)));

        $this->has_fields = true;

        // Title/description for WooCommerce admin
        $this->method_title       = __('EveryPay', 'everypay');
        $this->method_description = __('E-commerce payments provided by Everypay', 'everypay');

        $this->supports = array('products');

        // Get setting values.
        $this->enabled = $this->get_option('enabled');

        $this->payment_form = $this->get_option('payment_form');
        $this->skin_name = $this->get_option('skin_name');
        $this->default_country = $this->get_option('default_country');
        $this->token_enabled = $this->get_option('token_enabled') === 'yes' ? true : false;

        // Log is created always for main transaction points - debug option adds more logging points during transaction
        $this->debug = $this->get_option('debug') === 'yes' ? true : false;
        $this->log = new Logger();
        $this->log->set_debug($this->debug);

        // Initialize API
        $this->api = new Api($this->api_endpoint, $this->api_username, $this->api_secret, Base::get_instance()->get_version(), $this->debug);

        // Payment methods to display in payment method
        $payment_methods = $this->get_option('payment_methods', false);
        $this->payment_methods = $payment_methods ? json_decode($payment_methods) : array();

        $this->status_messages = array(
            self::_VERIFY_FAIL => __('Payment was declined. Please try again.', 'everypay'),
            self::_VERIFY_CANCEL => __('Payment cancelled.', 'everypay'),
            self::_VERIFY_ERROR => __('An error occurred while processing the payment response, please notify merchant!', 'everypay')
        );
    }

    /**
     * Setup gateway.
     *
     * @return void
     */
    protected function setup()
    {
        $this->title = __('Everypay', 'everypay');

        // Hooks
        if (is_admin()) {
            add_action('admin_notices', array($this, 'checks') );
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }

        // Receipt page creates POST to gateway or hosts iFrame
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        $callback = 'WC_Gateway_' . Helper::api_url_case($this->id);
        // Api callback handler
        add_action('woocommerce_api_' . strtolower($callback), array($this, 'api_callback_handler'));
        // Returning iframe customer handler
        add_action('woocommerce_api_' . strtolower($callback) . '_iframe', array($this, 'iframe_return_handler'));
        // Redirect customer to final page success/cancel/cart
        add_action('woocommerce_api_' . strtolower($callback) . '_redirect', array($this, 'customer_redirect_handler'));

        $this->notify_url = WC()->api_request_url($callback);
        $this->iframe_return_url = WC()->api_request_url($callback . '_Iframe');
        $this->customer_redirect_url = WC()->api_request_url($callback . '_Redirect');

        // Load the form fields.
        $this->init_form_fields();
    }

    /**
     * Display payment method options.
     *
     * @param int $gateway_id
     * @return void
     */
    public function payment_method_options()
    {
        $methods = $this->get_payment_methods();

        $args = array(
            'gateway_id' => $this->id,
            'methods' => $this->select_method_option($methods),
            'preferred_country' => Helper::get_preferred_country($this->get_available_country_codes(), $this->get_default_country())
        );

        wc_get_template('payment-methods-options.php', $args, '', Base::get_instance()->template_path());
    }

    /**
     * Marks selected method.
     *
     * @param array $methods
     * @return array
     */
    protected function select_method_option($methods)
    {
        $single = count($methods) == 1;
        return array_map(function($method) use ($single) {
            $method->selected = $single ? true : false;
            return $method;
        }, $methods);
    }

    /**
     * Display country select html.
     *
     * @param string $id
     * @return void
     */
    public function country_selector_html($id)
    {
        $countries = $this->get_payment_method_countries();
        $preferred_country = Helper::get_preferred_country($this->get_available_country_codes(), $this->get_default_country());

        if(!empty($countries)): ?>
            <div class="preferred-country">
                <select name="<?php echo esc_attr($this->id); ?>[preferred_country]">
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo esc_attr($country->code); ?>" <?php selected($preferred_country, $country->code); ?>>
                            <?php echo esc_html($country->name); ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php endif;
    }

    /**
     * Returns base gateway settings.
     *
     * @return string
     */
    public function get_option_key()
    {
        return $this->plugin_id . 'everypay_settings';
    }

    /**
     * Register scripts for front-end
     *
     * @access public
     * @return void
     */
    public function script_manager()
    {
        wp_register_script('wc-payment-' . $this->id, Base::get_instance()->plugin_url() . '/assets/js/payment-handler.js', array('jquery'), '1.0', true);
        wp_enqueue_script('wc-payment-'. $this->id);
    }

    /**
     * Admin Panel Options
     *
     * @access public
     * @return void
     */
    public function admin_options()
    {
        ?>
        <h3><?php esc_html_e( 'EveryPay', 'everypay' ); ?></h3>
        <p><?php esc_html_e( 'EveryPay is a payment gateway service provider, enabling e-commerce merchants to online payments from their customers.', 'everypay' ); ?></p>
        <p><a href="https://portal.every-pay.eu/"><?php esc_attr_e( 'Merchant Portal', 'everypay' ); ?></a> | <a
                href="https://every-pay.com/contact/"><?php esc_attr_e( 'Contacts', 'everypay' ); ?></a> | <a
                href="https://every-pay.com/documentation-overview/"><?php esc_attr_e( 'Documentation', 'everypay' ); ?></a>
        </p>

        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <script type="text/javascript">
                jQuery(function($) {
                    var $sandbox = $('#woocommerce_<?php echo esc_html($this->id); ?>_sandbox'),
                        $paymentForm = $('#woocommerce_<?php echo esc_html($this->id); ?>_payment_form'),
                        $sandboxApiFields = $('#woocommerce_<?php echo esc_html($this->id); ?>_sandbox_api_username, #woocommerce_<?php echo esc_html($this->id); ?>_sandbox_api_secret').closest('tr'),
                        $liveApiFields = $('#woocommerce_<?php echo esc_html($this->id); ?>_api_username, #woocommerce_<?php echo esc_html($this->id); ?>_api_secret').closest('tr'),
                        $skinname = $('#woocommerce_<?php echo esc_html($this->id); ?>_skin_name').closest('tr');

                    if($sandbox.val() == 'yes') {
                        $sandboxApiFields.show();
                        $liveApiFields.hide();
                    } else {
                        $sandboxApiFields.hide();
                        $liveApiFields.show();
                    }

                    if($paymentForm.val() == 'iframe') {
                        $skinname.show();
                    } else {
                        $skinname.hide();
                    }

                    $sandbox.change(function () {
                        if ($(this).val() == 'yes') {
                            $liveApiFields.hide("slow", function() {
                                $sandboxApiFields.show("slow");
                            });
                        } else {
                            $sandboxApiFields.hide("slow", function() {
                                $liveApiFields.show("slow");
                            });
                        }
                    });

                    $paymentForm.change(function () {
                        if($(this).val() == 'iframe') {
                            $skinname.show("slow");
                        } else {
                            $skinname.hide("slow");
                        }
                    });
                });

                /**
                 * Make ajax request on button click.
                 *
                 * @param string action
                 * @return void
                 */
                function updateButton(element, action) {
                    var $button = jQuery(element),
                        $loader = $button.siblings('.spinner'),
                        $message = $button.siblings('.update-result');

                    $button.prop('disabled', true);
                    $loader.addClass('is-active');

                    jQuery.post(ajaxurl, {
                        action: action
                    }, null, 'json').always(function(response) {
                        if(response.message) {
                            $message.text(response.message);
                            $message.addClass(response.success ? 'success' : 'error');
                            setTimeout(function() {
                                $message.removeClass('success error');
                                $message.text('');
                            }, 5000);
                        }
                        $button.prop('disabled', false);
                        $loader.removeClass('is-active');
                    });
                }
            </script>
        </table>
        <?php
    }

    /**
     * Check if payment method can be activated
     *
     * @access public
     */
    public function checks()
    {
        if($this->enabled == 'no') {
            return;
        }

        // PHP Version.
        if (version_compare(phpversion(), '5.3', '<' )) {
            echo '<div class="error" id="wc_everypay_notice_phpversion"><p>' . esc_html(sprintf(__('EveryPay Error: EveryPay requires PHP 5.3 and above. You are using version %s.', 'everypay'), phpversion())) . '</p></div>';
        } // Check required fields.
        else if (!$this->api_username || !$this->api_secret) {
            if ($this->sandbox === false) {
                echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . esc_html__('EveryPay Error: Please enter your API username and secret.', 'everypay') . '</p></div>';
            } else {
                echo '<div class="error" id="wc_everypay_notice_credentials"><p>' . esc_html__('EveryPay Error: Please enter your TEST API username and secret.', 'everypay') . '</p></div>';
            }
        }
        if (!$this->account_id) {
            echo '<div class="error" id="wc_everypay_notice_account"><p>' . esc_html__('EveryPay Error: Please enter your Processing Account.', 'everypay') . '</p></div>';
        }

        // warn about test payments
        if ($this->sandbox === true) {
            echo '<div class="update-nag" id="wc_everypay_notice_sandbox"><p>' . esc_html__("EveryPay payment gateway is in test mode, real payments not processed!", 'everypay') . '</p></div>';
        }

        // warn about unsecure use if: iFrame in use and wordpress is not using ssl
        if (($this->payment_form === self::FORM_IFRAME) && !is_ssl()) {
            echo '<div class="error" id="wc_everypay_notice_ssl"><p>' . esc_html__('EveryPay iFrame mode is enabled, but your site is not using HTTPS. While EveryPay iFrame remains secure users may feel insecure due to missing confirmation in browser address bar. Please ensure your server has a valid SSL certificate!', 'everypay') . '</p></div>';
        }
    }

    /**
     * Check if this gateway is enabled.
     *
     * @access public
     */
    public function is_available()
    {
        if ($this->enabled == 'no') {
            return false;
        }

        if (!$this->api_username || !$this->api_secret) {
            return false;
        }

        return true;
    }

    /**
     * Generate update button
     *
     * @param string $key
     * @param array $data
     * @return string
     */
    public function generate_update_button_html($key, $data)
    {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <button id="<?php echo esc_attr( $field_key ); ?>" class="button <?php echo esc_attr( $data['class'] ); ?>" onclick="updateButton(this, '<?php echo wp_kses_post( $data['action'] ); ?>'); return false;" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?>><?php echo wp_kses_post( $data['label'] ); ?></button>
                    <span class="update-result"></span>
                    <span class="spinner" style="float: none;"></span>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Generate Text Input HTML.
     *
     * @param string $key Field key.
     * @param array  $data Field data.
     * @since  1.0.0
     * @return string
     */
    public function generate_info_html($key, $data)
    {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'info'             => '',
            'class'             => '',
            'css'               => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <span class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>"><?php echo wp_kses_post( $data['info'] ); ?></span>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     */
    public function init_form_fields()
    {
        $translation_notice = '';

        if ( function_exists( 'icl_object_id' ) ) {
            $translation_notice = '<br>' . __( 'For translation with WPML use English here and translate in String Translation. Detailed instructions <a href="https://wpml.org/documentation/support/translating-woocommerce-sites-default-language-english/">here</a>.', 'everypay' );
        }

        $this->form_fields = array(
            'enabled'         => array(
                'title'       => __( 'Enable/Disable', 'everypay' ),
                'label'       => __( 'Enable EveryPay', 'everypay' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'sandbox'         => array(
                'title'       => __( 'Gateway URL', 'everypay' ),
                'type'        => 'select',
                'options'     => array(
                    'no' => __( 'LIVE', 'everypay' ),
                    'yes'   => __( 'TEST', 'everypay' ),
                ),
                'description' => __( "With TEST Gateway Url use the payment gateway in test mode using test API credentials (real payments will not be taken).", 'everypay' ),
                'default'     => 'no',
                'desc_tip'    => false,
            ),
            'callback_info'  => array(
                'title'       => __('Callback Notification URL', 'everypay'),
                'info'        => $this->notify_url,
                'type'        => 'info',
                'disabled'    => true,
                'desc_tip'    => false,
                'description' => __('Add this URL to Callback Notification URL in EveryPay merchant portal.', 'everypay')
            ),
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
            'account_id'           => array(
                'title'       => __( 'API Account Name', 'everypay' ),
                'type'        => 'text',
                'description' => __( "You'll find this in EveryPay Merchant Portal, under 'Settings' > 'Processing accounts' section (looks like: EUR1).", 'everypay' ),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'update_methods'  => array(
                'title'       => __( 'Update Payment Methods', 'everypay' ),
                'label'       => __( 'Update', 'everypay' ),
                'type'        => 'update_button',
                'disabled'    => !$this->api_username || !$this->api_secret || !$this->account_id,
                'action'      => 'update_payment_methods_' . $this->id,
                'desc_tip'    => false,
            ),
            'title_card'      => array(
                'title'       => __( 'Title of Card Payment', 'everypay' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees on card payments.', 'everypay' ) . $translation_notice,
                'default'     => __( 'Card payment', 'everypay' )
            ),
            'title_bank'      => array(
                'title'       => __( 'Title of Bank Payment', 'everypay' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees on bank payments.', 'everypay' ) . $translation_notice,
                'default'     => __( 'Bank payment', 'everypay' )
            ),
            'title_alternative'      => array(
                'title'       => __( 'Title of Alternative Payment', 'everypay' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees on alternative payments.', 'everypay' ) . $translation_notice,
                'default'     => __( 'Alternative payment', 'everypay' )
            ),
            'payment_form'         => array(
                'title'       => __( 'Payment Integration Variants ', 'everypay' ),
                'type'        => 'select',
                'options'     => array(
                    self::FORM_REDIRECT => __( 'Redirect to hosted form on EveryPay server', 'everypay' ),
                    self::FORM_IFRAME   => __( 'iFrame payment form integrated into checkout', 'everypay' ),
                ),
                'description' => __( "Hosted form on EveryPay server is the secure solution of choice, while iFrame provides better customer experience (https strongly advised)", 'everypay' ),
                'default'     => 'redirect',
                'desc_tip'    => false,
            ),
            'default_country' => array(
                'title'       => __( 'Default Country ', 'everypay' ),
                'type'        => 'select',
                'options'     => array(
                    ''   => __( 'Default (by locale)', 'everypay' ),
                    'EE' => __( 'Estonia', 'everypay' ),
                    'LV' => __( 'Latvia', 'everypay' ),
                    'LT' => __( 'Lithuaina', 'everypay' )
                ),
                'description' => __( "By default country selection is attempted by currently active locale", 'everypay' ),
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
            'debug'                => array(
                'title'       => __( 'Debug Log', 'everypay' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'everypay' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log EveryPay events inside <code>%s</code>', 'everypay' ), wc_get_log_file_path( $this->id ) )
            ),
        );
    }

    /**
     * Format payment method settings before save.
     *
     * @return string
     */
    public function validate_text_field($key, $value = null)
    {
        if(in_array($key, array('account_id', 'api_username', 'api_secret', 'sandbox_api_username', 'sandbox_api_secret'))) {
            $field = $this->get_field_key($key);
            if (isset($_POST[$field])) {
                $value = trim(wp_strip_all_tags(stripslashes($_POST[$field])));
            }
            return $value;
        } else {
            return parent::validate_text_field($key, $value);
        }
    }

    /**
     * Display payment method methods selection.
     *
     * @return void
     */
    public function payment_fields()
    {
        wc_get_template('payment-methods.php', array('gateway_id' => $this->id), '', Base::get_instance()->template_path());
    }

    /**
     * Get user tokens.
     * Add formated labels.
     *
     * @return array
     */
    public function get_user_tokens()
    {
        if((true === $this->token_enabled) && is_user_logged_in()) {
            $tokens = maybe_unserialize(get_user_meta(get_current_user_id(), self::META_TOKENS, true));
        }

        if(!isset($tokens) || !is_array($tokens)) {
            $tokens = array();
        }

        /*
        $tokens = array(
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
        );
        */

        // Mark expired cards as inactive - should not be used and marked default, can be deleted
        $now = new DateTime();

        foreach($tokens as $key => $token) {

            $cc_expires = DateTime::createFromFormat('n Y', $token['cc_month'] . ' ' . $token['cc_year']);

            if ($cc_expires < $now) {
                $tokens[$key]['active'] = false;
                $tokens[$key]['default'] = false; // can't be default anymore
            } else {
                $tokens[$key]['active'] = true;
            }

            $card_name = '';
            if(!empty($token['cc_last_four_digits'])) {
                $card_name = sprintf('**** **** **** %s', $token['cc_last_four_digits']);
            }
            if(!empty($token['cc_month']) && !empty($token['cc_year'])) {
                $card_name .= sprintf(' (%s %s/%s)',
                    esc_html__('expires', 'everypay'),
                    str_pad($token['cc_month'], 2, '0', STR_PAD_LEFT),
                    $token['cc_year']
                );
            }

            $labels = array(
                'type_name' => $this->get_token_type_fullname($token['cc_type']),
                'card_name' => $card_name
            );
            $tokens[$key]['labels'] = $labels;
        }
        return $tokens;
    }

    /**
     * Gets token type fullname.
     *
     * @param string $type
     * @return string
     */
    protected function get_token_type_fullname($type)
    {
        return isset($this->cc_types[$type]) ? $this->cc_types[ $type ] : '';
    }

    /**
     * @param string $token
     * @return boolean
     */
    public function remove_user_token($token = '')
    {
        $tokens = $this->get_user_tokens();

        if(!empty($tokens[$token])) {

            // handle removal of default card by possibly assigning one added before that
            if(isset($tokens[$token]['default']) && true === $tokens[$token]['default']) {

                unset($tokens[$token]);

                // try finding new default
                $latest = 0;
                $new_default = null;

                foreach($tokens as $key => $token) {
                    if(true === $token['active'] && $token['added'] > $latest) {
                        $latest = $token['added'];
                        $new_default = $key;
                    }
                }

                if(!is_null($new_default)) {
                    $tokens[ $new_default ]['default'] = true;
                }

            } else {
                unset($tokens[$token]);
            }

            return (bool) update_user_meta(get_current_user_id(), self::META_TOKENS, $tokens);
        }
        return false;
    }

    /**
     * @param string $token
     * @return boolean
     */
    public function set_user_token_default( $token = '' ) {

        $tokens = $this->get_user_tokens();

        if ( ! empty( $tokens[ $token ] ) ) {

            foreach ( $tokens as $key => $value ) {
                $tokens[ $key ]['default'] = false;
            }

            $tokens[ $token ]['default'] = true;

            return (bool) update_user_meta( get_current_user_id(), self::META_TOKENS, $tokens );

        }

        return false;

    }

    /**
     * Run on submitting the checkout page - process payment fields and redirect to payment e.g receipt page
     *
     * @access public
     *
     * @param  int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        
        if(!$this->valid_method($order)) {
            wc_add_notice(__('Payment method not selected!', 'everypay'), 'error');
            return;
        }

        $validation_errors = $this->validate_order_data($order);
        if(!empty($validation_errors)) {
            foreach ($validation_errors as $validation_error) {
                wc_add_notice($validation_error, 'error');
            }
            return;
        }

        $is_token = !$order->get_meta(self::META_METHOD) && $order->get_meta(self::META_TOKEN);

        if($is_token && true === $this->token_enabled) {
            $response = $this->get_api()->payment_cit($order, $this);
        } else {
            $response = $this->get_api()->payment_oneoff($order, $this);
        }

        // API request failed, return error
        if(isset($response->error) || $response == false) {
            if(isset($response->error)) {
                $message = ': ' . $response->error->message;
            } else {
                $message = '';
            }
            wc_add_notice(__('Payment error', 'everypay') . $message, 'error');
            return;
        }

        $this->order_add_respone_data($order, $response);
        $order->save();

        $this->log->debug(ucfirst($this->id) . ' selected for order #' . $order->get_id());

        $redirect = $this->useIframe($order) ? $order->get_checkout_payment_url(true) : $order->get_meta(self::META_LINK);
        $this->log->debug('Redirect to: ' . $redirect);

        // Redirect to receipt page for iframe payment
        return array(
            'result' => 'success',
            'redirect' => $redirect
        );
    }

    /**
     * Validates order data that can not be used for API calls later.
     *
     * @param WC_Order $order
     * @return array
     */
    public function validate_order_data(WC_Order $order)
    {
        $errors = array();

        if(!empty($order->get_billing_state()) && preg_match('/\A[\p{L}\- ]{3,30}\z/', $order->get_billing_state()) !== 1) {
            $errors[] = __('Invalid billing state!', 'everypay');
        }

        return $errors;
    }

    /**
     * If iframe will be used for payment.
     *
     * @param WC_Order $order
     * @return boolean
     */
    public function useIframe(WC_Order $order)
    {
        return $this->get_payment_form() == self::FORM_IFRAME && $this->iframe_availible($order);
    }

    /**
     * If iframe can be used to pay for supplied order.
     *
     * @param WC_Order $order
     * @return boolean
     */
    protected function iframe_availible(WC_Order $order)
    {
        $method = $order->get_meta(self::META_METHOD);
        $card_methods = Helper::filter_payment_methods($this->get_payment_methods(), self::TYPE_CARD);

        return count(array_filter($card_methods, function($card_method) use ($method) {
            return $card_method->source === $method;
        })) || $order->get_meta(self::META_TOKEN);
    }

    /**
     * Validate method selection.
     *
     * @param WC_Order $order
     * @return boolean
     */
    protected function valid_method(WC_Order $order)
    {
        return $order->get_meta(self::META_METHOD) || $order->get_meta(self::META_TOKEN);
    }

    /**
     * Save response data to order.
     *
     * @param WC_Order $order
     * @param object $respone
     * @return
     */
    protected function order_add_respone_data($order, $response)
    {
        $payment_link = $this->extract_payment_link($response, $order->get_meta(self::META_METHOD));

        $order->update_meta_data(self::META_LINK, $payment_link);
        $order->update_meta_data(self::META_REFERENCE, $response->payment_reference);
    }

    /**
     * Extract payment link from response.
     *
     * @param array $respone
     * @param string|null $method
     * @return string
     */
    protected function extract_payment_link($response, $method = null)
    {
        if(isset($response->payment_link)) {
            if($method) {
                foreach ($response->payment_methods as $payment_method) {
                    if($payment_method->source == $method) {
                        return $payment_method->payment_link;
                    }
                }
            }
            return $response->payment_link;
        }
        return null;
    }

    /**
     * Output redirect or iFrame form on receipt page
     *
     * @access public
     *
     * @param $order_id
     */
    public function receipt_page($order_id) 
    {
        $order = new WC_Order($order_id);

        $this->script_manager();

        $script_data = array(
            'uri'       => get_site_url(),
            'redirect'  => $this->get_customer_redirect_url($order_id),
            'sandbox'   => $this->sandbox,
            'ping'      => !$this->useIframe($order),
            'order_id'  => $order_id,
            'ajax_url'  => admin_url('admin-ajax.php')
        );

        $tempalte_args = array(
            'use_iframe' => false,
            'redirect_url'  => $this->get_customer_redirect_url($order_id)
        );

        if($this->useIframe($order)) {
            $script_data['ping'] = false;
            $tempalte_args['use_iframe'] = true;
            $tempalte_args['payment_link'] = $order->get_meta(self::META_LINK);
        } else {
            $script_data['ping'] = true;
        }

        wp_localize_script('wc-payment-' . $this->id, 'wc_payment_params', $script_data);

        wc_get_template('payment.php', $tempalte_args, '', Base::get_instance()->template_path());
    }

    /**
     * Process automatic callback from everypay
     *
     * @return void
     */
    public function api_callback_handler()
    {
        $this->log->debug('Callback handler GET = ' . json_encode($_GET));

        @ob_clean();

        header('HTTP/1.1 200 OK');

        $order_id = $_GET['order_reference'];

        $this->log->debug('Callback handler started: order id = ' . print_r($order_id, true));

        $order = wc_get_order($order_id);

        if(!$order) {
            $this->log->debug('Invalid order ID received: ' . print_r($order_id, true));
            wc_add_notice(__('Invalid order received!', 'everypay'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // $this->log->debug('Order ' . var_export($order, true));

        if(!$order->has_status(wc_get_is_paid_statuses())) {
            $this->log->debug('Process order status');
            $this->process_order_status($order);
        }

        exit;
    }

    /**
     * Handle returning iframe payments.
     *
     * @return void
     */
    public function iframe_return_handler()
    {
        ?>
        <script type="text/javascript">
            window.parent.postMessage("start_ping", "<?php echo get_site_url(); ?>");
        </script>
        <?php
        exit;
    }

    /**
     * Redirect customer to final order page
     *
     * @param WC_Order|null $order
     * @return void
     */
    public function customer_redirect_handler()
    {
        $this->log->debug('Redirect handler GET = ' . json_encode($_GET));

        $order_id = (int) $_GET['order_id'];
        $initial = isset($_GET['init']) && $_GET['init'];

        $order = wc_get_order($order_id);

        // Default redirect url
        $redirect_url = wc_get_checkout_url();

        // Order not found, redirect to checkout
        if(!$order) {
            $this->log->debug('Order not found = ' . $order_id . ' GET ' . $_GET['order_id']);
            wc_add_notice(__('Order not found!', 'everypay'), 'error');
            wp_redirect($redirect_url);
            exit;
        }

        $this->log->debug('Order status = ' . $order->get_status());

        // Pending order must have empty payment status, order processing in progress, wait and reload order
        if($initial && $order->has_status(wc_get_is_pending_statuses()) && $order->get_meta(self::META_STATUS) !== '') {
            $this->log->debug('Order processing in progress, WAIT and reload');
            sleep(1);
            $order = wc_get_order($order_id);
            $this->log->debug('Order status after processing = ' . $order->get_status());
        }

        // Return from everypay, order not paid yet, redirect to payment page for status pinging.
        if($initial && $order->has_status(wc_get_is_pending_statuses())) {
            $redirect_url = $order->get_checkout_payment_url(true);
            $this->log->debug('Redirect to ping = ' . $redirect_url);
            wp_redirect($redirect_url);
            exit;
        }

        $this->change_language($order->get_id());

        $payment_status = $order->get_meta(self::META_STATUS);

        if($payment_status === '') {
            // No status/response message
            wc_add_notice(__('No payment status response received, please notify merchant!', 'everypay'), 'error');
        } else if($payment_status == self::_VERIFY_SUCCESS) {
            // Successfull payment redirect
            $redirect_url = $this->get_return_url($order);
        } else if($payment_status == self::_VERIFY_FAIL) {
            // Failed payment message
            wc_add_notice($this->status_messages[self::_VERIFY_FAIL], 'error');
        } else if($payment_status == self::_VERIFY_CANCEL) {
            // Canceled payment redirect and message
            wc_add_notice($this->status_messages[self::_VERIFY_CANCEL], 'error');
            $redirect_url = $order->get_cancel_order_url();
        } else if($payment_status == self::_VERIFY_ERROR) {
            // General error message
            wc_add_notice($this->status_messages[self::_VERIFY_ERROR], 'error');
        }

        $this->log->debug(sprintf('Do redirect, payment status = %s, redirect url = %s', $payment_status, $redirect_url));
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Change active language.
     *
     * @param int $order_id
     * @return void
     */
    public function change_language($order_id)
    {
        if(function_exists('icl_object_id')) {

            // adapted from WooCommerce Multilingual /inc/emails.class.php
            $lang = get_post_meta($order_id, 'wpml_language', true);

            if (!empty($lang)) {
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
     * @return bool
     */
    public function process_order_status($order)
    {
        // No payment reference, don't do antything
        if(!$order->get_meta(self::META_REFERENCE)) {
            $this->log->debug(sprintf('Can\'t process order %s status, missing meta reference', $order->get_id()));
            return;
        }

        $response = $this->get_api()->payment_status($order);
        $status = $this->verify_everypay_response($response);

        // Unknown status, don't do anything
        if($status === null) {
            return;
        }

        $this->log->debug(sprintf('Save order %s status %s = %s', $order->get_id(), self::META_STATUS, $status));
        $order->update_meta_data(self::META_STATUS, $status);
        $order->save();

        $message = false;

        if(self::_VERIFY_SUCCESS === $status) {
            // Payment complete
            $order->payment_complete($order->get_meta(self::META_REFERENCE));

            // Add order note
            $order->add_order_note(sprintf(__('Card payment was successfully processed by EveryPay (Reference: %s, Timestamp: %s)', 'everypay'), $order->get_meta(self::META_REFERENCE), $response->payment_created_at));

            // Store the transaction ID for WC 2.2 or later.
            $order->update_meta_data('_transaction_id', $order->get_meta(self::META_REFERENCE));

            $this->maybe_add_token($order, $response);

            // Remove cart
            WC()->cart->empty_cart();

        } elseif(self::_VERIFY_FAIL === $status) {
            $order->update_status('failed', $this->status_messages[self::_VERIFY_FAIL]);
            $this->log->debug('Payment was declined by payment processor.');

        } elseif(self::_VERIFY_CANCEL === $status) {
            $order->update_status('cancelled', $this->status_messages[self::_VERIFY_CANCEL]);
            $this->log->debug('Payment was cancelled by user.');

        } else {
            $order->update_status('failed', $this->status_messages[self::_VERIFY_ERROR]);
            $this->log->debug('An error occurred while processing the payment response.');
        }
    }

    /**
     * Verify EveryPay response
     *
     * Expects following data as input:
     *
     * for successful and failed payments:
     *
     * array(
     * 'api_username' => api username,
     * 'account_name' => account name in EveryPay system
     * 'amount' => amount to pay,
     * 'order_reference' => order reference number,
     * 'nonce' => return nonce
     * 'payment_reference' => payment reference number,
     * 'payment_state' => payment state,
     * 'payment_created_at' => timestamp,
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
     * @param object $response
     *
     * @return int 1 - verified successful payment, 2 - verified failed payment, 3 - user cancelled, 0 - error
     * @throws Exception
     */
    public function verify_everypay_response($response)
    {
        $statuses = array(
            'settled' => self::_VERIFY_SUCCESS,
            'authorised' => self::_VERIFY_SUCCESS,
            'failed'    => self::_VERIFY_FAIL,
            'cancelled' => self::_VERIFY_CANCEL,
            'waiting_for_3ds_response' => self::_VERIFY_CANCEL,
        );

        if($response->api_username !== $this->api_username) {
            $this->log->debug('EveryPay error: API username in response does not match, order not completed!');
            return self::_VERIFY_ERROR;
        }

        $created_at = strtotime($response->payment_created_at);
        $now = time() + 60;
        if (($created_at > $now) || ($created_at < ($now - 600))) {
            $this->log->debug('EveryPay error: response is older than 10 minutes, order not completed!');
            return self::_VERIFY_ERROR;
        }

        $status = isset($statuses[$response->payment_state]) ? $statuses[$response->payment_state] : null;

        return $status;
    }

    /**
     * Maybe add new token to user - when processing callback
     *
     * @param WC_Order $order
     * @param object $response
     * @return void
     */
    protected function maybe_add_token($order, $response)
    {
        if($this->token_enabled &&
            !empty($response->cc_details->token) &&
            !empty($response->cc_details->last_four_digits) &&
            !empty($response->cc_details->year) &&
            !empty($response->cc_details->month) &&
            !empty($response->cc_details->type)
        ) {
            // 'return to merchant' may have token, but does not carry rest of the information needed to build card selector on next purchase
            $user = $order->get_user();

            if(isset($user->ID)) {
                $tokens = maybe_unserialize(get_user_meta($user->ID, self::META_TOKENS, true));

                if(empty($tokens)) {
                    $tokens = array();
                }

                if(!isset($tokens[$response->cc_details->token])) {

                    $new_token = array(
                        'cc_token'            => $response->cc_details->token,
                        'cc_last_four_digits' => $response->cc_details->last_four_digits,
                        'cc_year'             => $response->cc_details->year,
                        'cc_month'            => $response->cc_details->month,
                        'cc_type'             => $response->cc_details->type,
                        'default'             => false,
                        'added'               => time(),
                    );

                    if(0 === count($tokens)) {
                        $new_token['default'] = true;
                    }

                    $tokens[$response->cc_details->token] = $new_token;
                    update_user_meta($user->ID, self::META_TOKENS, $tokens);
                }
            }
        }
    }

    /**
     * Get payment methods.
     *
     * @return object[]
     */
    public function get_payment_methods()
    {
        return $this->payment_methods;
    }

    /**
     * Get list of unique languages.
     *
     * @return string[]
     */
    public function get_payment_method_countries()
    {
        $payment_countries = array();
        $countries = WC()->countries->get_countries();

        foreach ($this->get_payment_methods() as $method) {
            $code = strtoupper($method->country);
            if($method->country && !isset($payment_countries[$code])) {
                $country = new stdClass;
                $country->code = $code;
                $country->name = isset($countries[$code]) ? $countries[$code] : $code;
                $payment_countries[$code] = $country;
            }
        }

        return $payment_countries;
    }

    /**
     * Get availible country codes from payment methods.
     *
     * @return array
     */
    public function get_available_country_codes()
    {
        $country_codes = array_unique(
            array_map(function($method) {
                return $method->country;
            }, $this->get_payment_methods())
        );

        sort($country_codes);

        return $country_codes;
    }

    /**
     * Update payment methods for gateway.
     *
     * @param array $methods
     * @return boolean
     */
    public function update_payment_methods($methods)
    {
        $formated = $this->format_payment_methods($methods);
        if($this->update_option('payment_methods', $formated)) {
            $this->payment_methods = json_decode($formated);
            return true;
        }
        return false;
    }

    /**
     * Format payment methods form api.
     *
     * @param array $methods
     * @return array
     */
    protected function format_payment_methods($methods)
    {
        $formated = array();
        foreach ($methods as $method) {
            $formated_method = new stdClass;
            $formated_method->source = $method->source;
            $formated_method->name = $method->display_name;
            $formated_method->country = $method->country_code;
            $formated_method->logo = $method->logo_url;
            $formated[] = $formated_method;
        }
        return json_encode($formated);
    }

    /**
     * Get account Id.
     *
     * @return string
     */
    public function get_account_id()
    {
        return $this->account_id;
    }

    /**
     * Get API instance.
     *
     * @return Api
     */
    public function get_api()
    {
        return $this->api;
    }

    /**
     * Get value from post.
     * Scopes values only for payment method options unless directed otherwise.
     *
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function get_input_value($name, $default = null)
    {
        return isset($_POST[$this->id][$name]) && $_POST[$this->id][$name] ? trim($_POST[$this->id][$name]) : $default;
    }

    /**
     * Get notify url.
     *
     * @param array $params
     * @return string
     */
    public function get_notify_url($params = null)
    {
        if($params) {
            $url = add_query_arg($params, $this->notify_url);
        } else {
            $url = $this->notify_url;
        }
        return $url;
    }

    /**
     * Get iframe return url.
     *
     * @return string
     */
    public function get_iframe_return_url()
    {
        return $this->iframe_return_url;
    }

    /**
     * Get customer redirect url.
     *
     * @param int $order_id
     * @param bool $initial
     * @return string
     */
    public function get_customer_redirect_url($order_id, $initial = false)
    {
        $arguments = array(
            'order_id' => $order_id
        );
        if($initial) {
            $arguments['init'] = 1;
        }
        return add_query_arg($arguments, $this->customer_redirect_url);
    }

    /**
     * Get default selected country.
     *
     * @return string
     */
    public function get_default_country()
    {
        return $this->default_country;
    }

    /**
     * Get skin name used for iframe.
     *
     * @return string
     */
    public function get_skin_name()
    {
        return $this->skin_name;
    }

    /**
     * Return payment form type.
     *
     * @return string
     */
    public function get_payment_form()
    {
        return $this->payment_form;
    }

    /**
     * If tokens are enabled.
     *
     * @return boolean
     */
    public function get_token_enabled()
    {
        return $this->token_enabled;
    }
}