<?php
/**
 * Display payment methods selection.
 */
?>

<?php 
/**
 * Hook: woocommerce_everypay_fieldset_start.
 *
 * @hooked Everypay/Gateway_Card::token_hint_html - 20
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

    <?php 
    /**
     * Hook: woocommerce_everypay_form_end.
     *
     * @hooked Everypay/Gateway_Card::tokens_html - 10
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