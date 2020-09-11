<?php
/**
 * Display payment form.
 */
?>

<?php if($use_iframe): ?>
	<iframe id="wc_payment_iframe" name="wc_payment_iframe" width="405" height="479" style="border: 0;" src="<?php echo esc_attr($payment_link); ?>"></iframe>
<?php endif;?>

<div class="ping-message-wrapper">
	<p class="status-message pending" style="display: none;"><?php esc_html_e('Please wait while we check your payment status…', 'everypay'); ?></p>
	<p class="status-message success" style="display: none;"><?php esc_html_e('Payment successful. Redirecting to success page.', 'everypay'); ?><br><?php echo str_replace(['{', '}'], ['<a href="' . $redirect_url . '">', '</a>'], esc_html__('If you are not automatically redirected within a few seconds click {here}.', 'everypay')); ?></p>
	<p class="status-message failed" style="display: none;"><?php esc_html_e('Payment failed.', 'everypay'); ?></p>
</div>