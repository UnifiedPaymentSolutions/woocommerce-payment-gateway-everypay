<?php
/**
 * Display payment methods selection.
 */
?>

<?php 
/**
 * Hook: woocommerce_everypay_fieldset_start.
 *
 * @hooked WC_Gateway_Everypay::token_hint_html - 20
 */
do_action('woocommerce_everypay_fieldset_start', $gateway_id);
?>

<fieldset id="<?php echo esc_attr($gateway_id); ?>-form">

    <?php 
    /**
     * Hook: woocommerce_everypay_form_start.
     */
    do_action('woocommerce_everypay_form_start', $gateway_id);
    ?>

    <?php include(WC_Everypay()->template_path('payment-methods-options.php')); ?>

    <?php 
    /**
     * Hook: woocommerce_everypay_form_end.
     *
     * @hooked WC_Gateway_Everypay::tokens_html - 10
     */
    do_action('woocommerce_everypay_form_end', $gateway_id);
    ?>

</fieldset>

<?php 
/**
 * Hook: woocommerce_everypay_fieldset_end.
 */
do_action('woocommerce_everypay_fieldset_end', $gateway_id);
?>