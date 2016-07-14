<?php

/**
 * Manage saved cards on My Account page (needs to be active as WC does not initialize gateways on all pages)
 *
 * User: petskratt
 * Date: 01/12/15
 * Time: 09:04
 */
class WC_Gateway_Everypay_Account {


	public function __construct() {
		add_action( 'wp', array( $this, 'manage_token' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'output' ) );
	}

	public function output() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$gateway = new WC_Gateway_Everypay();
		$tokens  = $gateway->get_user_tokens();

		if ( ! empty( $tokens ) ) {

			$args = array(
				'tokens'      => $tokens,
				'nonce_field' => wp_nonce_field( 'everypay_manage_tokens_uid_' . get_current_user_id(), '_wpnonce', true, false )
			);

			wc_get_template( 'my-account.php', $args, '', WC_Everypay::get_instance()->plugin_path() . '/templates/' );
		}

	}

	public function manage_token() {
		if ( ! ( isset( $_POST['everypay_remove_token'] ) || isset( $_POST['everypay_set_default'] ) ) || ! is_account_page() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! wp_verify_nonce( $_POST['_wpnonce'], "everypay_manage_tokens_uid_" . get_current_user_id() ) ) {
			wp_die( __( 'Unable to verify action, please try again.', 'everypay' ) );
		}

		if ( isset( $_POST['everypay_remove_token'] ) ) {
			$this->remove_token( $_POST['everypay_remove_token'] );
		} else if ( isset( $_POST['everypay_set_default'] ) ) {
			$this->set_token_default( $_POST['everypay_set_default'] );
		}

		$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
		if ( $myaccount_page_id ) {
			wp_safe_redirect( get_permalink( $myaccount_page_id ) );
		}

		exit;
	}


	public function remove_token( $token ) {

		$gateway = new WC_Gateway_Everypay();
		$result  = $gateway->remove_user_token( sanitize_text_field( $token ) );

		if ( true === $result ) {
			wc_add_notice( __( 'Card deleted.', 'everypay' ), 'success' );
		} else {
			wc_add_notice( __( 'Unable to delete card.', 'everypay' ), 'error' );
		}

		return;
	}

	public function set_token_default( $token ) {

		$gateway = new WC_Gateway_Everypay();
		$result  = $gateway->set_user_token_default( sanitize_text_field( $token ) );

		if ( true === $result ) {
			wc_add_notice( __( 'Card set as default.', 'everypay' ), 'success' );
		} else {
			wc_add_notice( __( 'Unable to update card.', 'everypay' ), 'error' );
		}

		return;

	}

}

new WC_Gateway_Everypay_Account();