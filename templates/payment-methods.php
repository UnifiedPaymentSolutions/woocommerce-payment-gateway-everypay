<?php
/**
 * Display payment methods selection.
 */
?>

<div class="everypay-wrapper">

    <?php 
    /**
     * Hook: woocommerce_everypay_fieldset_start.
     *
     * @hooked Everypay/Gateway_Card::token_hint_html - 20
     */
    do_action('woocommerce_fieldset_start_' . $gateway_id);
    ?>

    <fieldset id="<?php echo esc_attr($gateway_id); ?>-form">

        <?php 
        /**
         * Hook: woocommerce_everypay_form_start.
         *
         * @hooked Everypay/Gateway_Card::payment_method_options - 10
         * @hooked Everypay/Gateway_Bank::payment_method_options - 10
         * @hooked Everypay/Gateway_Alternative::payment_method_options - 10
         */
        do_action('woocommerce_form_start_' . $gateway_id);
        ?>

        <?php 
        /**
         * Hook: woocommerce_everypay_form_end.
         *
         * @hooked Everypay/Gateway_Card::tokens_html - 10
         */
        do_action('woocommerce_form_end_' . $gateway_id);
        ?>

    </fieldset>

    <?php 
    /**
     * Hook: woocommerce_everypay_fieldset_end.
     */
    do_action('woocommerce_fieldset_end_' . $gateway_id);
    ?>

</div>