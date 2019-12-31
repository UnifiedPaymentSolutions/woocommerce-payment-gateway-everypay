<?php
/*
 * Plugin Name:       EveryPay payment gateway for WooCommerce
 * Plugin URI:        https://every-pay.com/documentation-overview/
 * Description:       Payment gateway for adding EveryPay (https://every-pay.com/) credit card payments support to WooCommerce.
 * Version:           1.2.0
 * Author:            EveryPay AS
 * Author URI:        https://every-pay.com/documentation-overview/
 * Requires at least: 4.4
 * Tested up to:      4.9.1
 * Text Domain:       everypay
 * Domain Path:       languages
 * WC requires at least: 3.0.0
 * WC tested up to:   3.7.0
 * Network:           false
 * GitHub Plugin URI: https://github.com/UnifiedPaymentSolutions/woocommerce-payment-gateway-everypay
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 * @package  EveryPay
 * @author   EveryPay AS
 * @category Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Required functions
 */
require_once( 'woo-includes/woo-functions.php' );

if ( ! class_exists( 'WC_Everypay' ) ) {



	/**
	 * WooCommerce EveryPay main class.
	 *
	 * @class   Everypay
	 * @version 1.0.0
	 */
	final class WC_Everypay {

		/**
		 * Instance of this class.
		 *
		 * @access protected
		 * @access static
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Slug
		 *
		 * @access public
		 * @var    string
		 */
		public $gateway_slug = 'everypay';

		/**
		 * Text Domain
		 *
		 * @access public
		 * @var    string
		 */
		public $text_domain = 'everypay';

		/**
		 * Gateway name.
		 *
		 * @NOTE   Do not put WooCommerce in front of the name. It is already applied.
		 * @access public
		 * @var    string
		 */
		public $name = "Gateway Everypay";

		/**
		 * Gateway version.
		 *
		 * @access public
		 * @var    string
		 */
		public $version = '1.2.0';

		/**
		 * Required woocommerce version.
		 *
		 * @var string
		 */
		protected $woocommerce_version = '3.0.0';

		/**
		 * The Gateway URL.
		 *
		 * @access public
		 * @var    string
		 */
		public $web_url = "https://every-pay.com/";

		/**
		 * The Gateway documentation URL.
		 *
		 * @access public
		 * @var    string
		 */
		public $doc_url = "https://every-pay.com/documentation-overview/";

		/**
		 * Wordpress schedule hook for automatic payment method updates.
		 *
		 * @var string
		 */
		protected static $schedule_hook = 'everypay_update_methods';

		/**
		 * @var boolean
		 */
		protected $sub_methods_enabled = false;

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Throw error on object clone
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return void
		 */
		public function __clone()
		{
			// Cloning instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'everypay' ), $this->version );
		}

		/**
		 * Disable unserializing of the class
		 *
		 * @since  1.0.0
		 * @access public
		 * @return void
		 */
		public function __wakeup()
		{
			// Unserializing instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'everypay' ), $this->version );
		}

		/**
		 * Initialize the plugin public actions.
		 *
		 * @access private
		 */
		private function __construct()
		{
			// Hooks.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Is WooCommerce activated?
			if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

				return false;
			} else {
				// Check we have the minimum version of WooCommerce required before loading the gateway.
				if (defined('WC_VERSION') && version_compare(WC_VERSION, $this->woocommerce_version, '>=')) {
					if (class_exists('WC_Payment_Gateway')) {

						$this->includes();

						add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));

						/*
						add_filter( 'woocommerce_currencies', array( $this, 'add_currency' ) );
						add_filter( 'woocommerce_currency_symbol', array( $this, 'add_currency_symbol' ), 10, 2 );
						*/

						// Update payment methods ajax
						add_action('wp_ajax_update_payment_methods', array($this, 'update_payment_methods'));

						// Payment methods schedule update action
						add_action(self::$schedule_hook, array($this, 'update_payment_methods'));
						$this->schedule_payment_methods_update();

						add_action('wp_enqueue_scripts', array($this, 'scripts'));

						add_action('woocommerce_checkout_update_order_review', array($this, 'enable_sub_methods'), 10);
						add_action('woocommerce_checkout_order_review', array($this, 'enable_sub_methods'), 19);
						add_action('woocommerce_checkout_order_review', array($this, 'disable_sub_methods'), 21);
						
						// Process checkout posted data
						add_filter('woocommerce_checkout_posted_data', array($this, 'process_checkout_posted_data'));
						add_action('woocommerce_checkout_update_order_meta', array($this, 'update_order_checkout_meta'), 10, 2);

						add_filter('woocommerce_available_payment_gateways', array($this, 'multiply_methods'));
					}
				} else {
					add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );

					return false;
				}
			}
		}

		/**
		 * Enable sub payment methods.
		 *
		 * @return void
		 */
		public function enable_sub_methods()
		{
			$this->sub_methods_enabled = true;
		}

		/**
		 * Disable sub payment methods.
		 *
		 * @return void
		 */
		public function disable_sub_methods()
		{
			$this->sub_methods_enabled = false;
		}

		/**
		 * Remove base gateway and add frontend oriented gateways based on availible methods.
		 *
		 * @param WC_Payment_Gateway[] $_available_gateways
		 * @return array
		 */
		public function multiply_methods($_available_gateways)
		{
			if($this->sub_methods_enabled) {
				$methods = array();
				$gateway = $this->get_gateway();
				$payment_methods = $gateway->get_payment_methods();

				if(Everypay\Helper::has_payment_methods($payment_methods, WC_Gateway_Everypay::TYPE_CARD)) {
					$methods[$this->gateway_slug . '_card'] = new Everypay\Gateway_Card();
				}

				if(Everypay\Helper::has_payment_methods($payment_methods, WC_Gateway_Everypay::TYPE_BANK)) {
					$methods[$this->gateway_slug . '_bank'] = new Everypay\Gateway_Bank();
				}

				if(Everypay\Helper::has_payment_methods($payment_methods, WC_Gateway_Everypay::TYPE_ALTER)) {
					$methods[$this->gateway_slug . '_alter'] = new Everypay\Gateway_Alternative();
				}

				$offset = array_search($this->gateway_slug, array_keys($_available_gateways));

				// Replace everypay method with multiple payment methods
				if($offset !== false) {
					$start = array_slice($_available_gateways, 0, $offset, true);
					$end = array_slice($_available_gateways, $offset + 1, null, true);

					$_available_gateways = $start + $methods + $end;
				}
			}
			return $_available_gateways;
		}

		/**
		 * Process checkout posted data.
		 *
		 * @param array $data
		 * @return array
		 */
		public function process_checkout_posted_data($data)
		{
			$payment_method = $data['payment_method'];
			if(strpos($payment_method, $this->gateway_slug) === 0) {

				if(isset($_POST[$payment_method])) {
					$options = $_POST[$payment_method];
					foreach ($options as $key => $value) {
						$options[$key] = wc_clean(wp_unslash($value));
					}
					$data[$this->gateway_slug . '_options'] = $options;
				}

				$data['payment_method'] = $this->gateway_slug;

			}
			return $data;
		}

		/**
		 * Save checkout special meta to order.
		 *
		 * @param int $order_id
		 * @param array $data
		 * @return void
		 */
		public function update_order_checkout_meta($order_id, $data)
		{
			$order = wc_get_order($order_id);
			$options_key = $this->gateway_slug . '_options';
			
			if($data['payment_method'] != $this->gateway_slug || !isset($data[$options_key])) {
				$order->update_meta_data(WC_Gateway_Everypay::META_METHOD, null);
				$order->update_meta_data(WC_Gateway_Everypay::META_TOKEN, null);
				$order->update_meta_data(WC_Gateway_Everypay::META_COUNTRY, null);
			} else {
				$options = isset($data[$options_key]) ? $data[$options_key] : null;

				$method = isset($options['method']) ? $options['method'] : null;
				$token = isset($options['token']) ? $options['token'] : null;
				$preferred_country = isset($options['preferred_country']) ? $options['preferred_country'] : null;

				$order->update_meta_data(WC_Gateway_Everypay::META_METHOD, $method);
				$order->update_meta_data(WC_Gateway_Everypay::META_TOKEN, $token);
				$order->update_meta_data(WC_Gateway_Everypay::META_COUNTRY, $preferred_country);
			}

			$order->save();
		}

		/**
		 * Load required assets.
		 *
		 * @return void
		 */
		public function scripts()
		{
			if(is_checkout()) {
				$style_handle = $this->gateway_slug . '-style';
				$script_handle = $this->gateway_slug . '-script';

				wp_enqueue_style($style_handle, $this->plugin_url() . '/assets/css/style.css', array(), '20191011');

				wp_register_script($script_handle, $this->plugin_url() . '/assets/js/script.js', array('jquery'), '20191011', true);
				wp_localize_script($script_handle, 'payment_method_settings', array(
					'name' => $this->gateway_slug,
					'names' => array(
						$this->gateway_slug . '_' . WC_Gateway_Everypay::TYPE_CARD,
						$this->gateway_slug . '_' . WC_Gateway_Everypay::TYPE_BANK,
						$this->gateway_slug . '_' . WC_Gateway_Everypay::TYPE_ALTER
					)
				));
				wp_enqueue_script($script_handle);
			}
		}

		/**
		 * Maybe schedule payment methods update with wordpress schedule.
		 *
		 * @return void
		 */
		protected function schedule_payment_methods_update($value='')
		{
			if(!wp_next_scheduled(self::$schedule_hook)) {

				$today = new DateTime();
				$zone = new DateTimeZone('+0300');
				$today->setTimezone($zone);
				$today->setTime(17, 00);
				$schedule_time = $today->getTimestamp();

				wp_schedule_event($schedule_time, 'daily', self::$schedule_hook);
			}
		}

		/**
		 * Update payment methods.
		 * Executed manually by ajax or by cron.
		 *
		 * @return void
		 */
		public function update_payment_methods()
		{
			$gateway = $this->get_gateway();
			$api = $gateway->get_api();

			if(!$api->is_configured()) {
				$type = 'error';
				$message = esc_html__('API not configured!', 'everypay');
			} elseif(empty($gateway->get_account_id())) {
				$type = 'error';
				$message = esc_html__('Processing account not defined!', 'everypay');
			} else {
				$response = $api->processing_account($gateway);

				if(empty($response)) {
					$type = 'error';
					$message = esc_html__('API request failed!', 'everypay');
				} elseif(!empty($response->error)) {
					$type = 'error';
					$message = esc_html($response->error);
				} else {
					$gateway->update_payment_methods($response->payment_methods);
					$type = 'success';
					$message = esc_html__('Payment methods updated!', 'everypay');
				}
			}

			echo json_encode(array(
				'type' => $type,
				'message' => $message
			));
			exit;
		}

		/**
		 * Plugin action links.
		 *
		 * @access public
		 *
		 * @param  mixed $links
		 * @return mixed $links
		 */
		public function action_links( $links ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->gateway_slug ) . '">' . __( 'Payment Settings', 'everypay' ) . '</a>',
				);

				return array_merge( $plugin_links, $links );
			}

			return $links;
		}

		/**
		 * Plugin row meta links
		 *
		 * @access public
		 *
		 * @param  array $input already defined meta links
		 * @param  string $file plugin file path and name being processed
		 *
		 * @return array $input
		 */
		public function plugin_row_meta( $input, $file ) {
			if ( plugin_basename( __FILE__ ) !== $file ) {
				return $input;
			}

			$links = array(
				'<a href="' . esc_url( $this->doc_url ) . '">' . __( 'Documentation', 'everypay' ) . '</a>',
			);

			$input = array_merge( $input, $links );

			return $input;
		}

		/**
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any
		 * following ones if the same translation is present.
		 *
		 * @access public
		 * @return void
		 */
		public function load_plugin_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
			$lang_dir = apply_filters( 'woocommerce_' . $this->gateway_slug . '_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->text_domain );
			$mofile = sprintf( '%1$s-%2$s.mo', $this->text_domain, $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/' . $this->text_domain . '/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/plugin-name/ folder
				load_textdomain( $this->text_domain, $mofile_global );
			} else if ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/plugin-name/languages/ folder
				load_textdomain( $this->text_domain, $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( $this->text_domain, false, $lang_dir );
			}
		}

		/**
		 * Include files.
		 *
		 * @access private
		 * @return void
		 */
		private function includes()
		{
			require_once('includes/class-logger.php');
			require_once('includes/class-helper.php');
			require_once('includes/class-api.php');
			require_once('includes/class-wc-gateway-everypay.php');
			require_once('includes/class-account.php');
			require_once('includes/methods/class-gateway-card.php');
			require_once('includes/methods/class-gateway-bank.php');
			require_once('includes/methods/class-gateway-alternative.php');
			new Everypay\Account();
		}

		/**
		 * Add the gateway.
		 *
		 * @access public
		 *
		 * @param  array $methods WooCommerce payment methods.
		 * @return array WooCommerce gateway.
		 */
		public function add_gateway($methods)
		{
			$methods[] = 'WC_' . str_replace( ' ', '_', $this->name );

			return $methods;
		}

		/**
		 * WooCommerce Fallback Notice.
		 *
		 * @access public
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first. Please install <a href="%s">WooCommerce</a> first.', $this->text_domain ), $this->name, admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ) ) . '</p></div>';
		}

		/**
		 * WooCommerce Payment Gateway Upgrade Notice.
		 *
		 * @access public
		 * @return string
		 */
		public function upgrade_notice() {
			echo '<div class="updated woocommerce-message wc-connect"><p>' . sprintf( __( 'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! Please upgrade before activating.', 'payment-gateway-everypay' ), $this->name ) . '</p></div>';
		}

		/**
		 * Plugin activation.
		 *
		 * @return void
		 */
		public static function activate()
		{

		}

		/**
		 * Plugin deactivation.
		 *
		 * @return void
		 */
		public static function deactivate()
		{
			$timestamp = wp_next_scheduled(self::$schedule_hook);
			if($timestamp) {
   				wp_unschedule_event($timestamp, self::$schedule_hook);
			}
		}

		/** helper functions ******************************************************/

		/**
		 * Get gateway instance.
		 *
		 * @return WC_Gateway_Everypay
		 */
		protected function get_gateway()
		{
			return WC()->payment_gateways->payment_gateways()[$this->gateway_slug];
		}

		/**
		 * Get plugin version.
		 *
		 * @return string
		 */
		public function get_version()
		{
			return $this->version;
		}

		/**
		 * Get the plugin url.
		 *
		 * @access public
		 * @return string
		 */
		public function plugin_url()
		{
			return untrailingslashit(plugins_url('/', __FILE__));
		}

		/**
		 * Get the plugin path.
		 *
		 * @access public
		 * @return string
		 */
		public function plugin_path($append = '')
		{
			return untrailingslashit(plugin_dir_path(__FILE__)) . ($append ? '/' . $append : '');
		}

		/**
		 * Get the templates path.
		 *
		 * @access public
		 * @return string
		 */
		public function template_path($append = '')
		{
			return untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/' . ($append ? trim($append, '/') : '');
		}
	} // end if class

	add_action('plugins_loaded', array(WC_Everypay::class, 'get_instance'), 0);

	register_activation_hook(__FILE__, array(WC_Everypay::class, 'activate'));
	register_deactivation_hook(__FILE__, array(WC_Everypay::class, 'deactivate'));

} // end if class exists.

/**
 * Returns the main instance of WC_Everypay to prevent the need to use globals.
 *
 * @return WooCommerce Everypay
 */
function WC_Everypay() {
	return WC_Everypay::get_instance();
}


