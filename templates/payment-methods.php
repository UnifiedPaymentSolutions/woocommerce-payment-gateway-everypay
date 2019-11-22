<?php
/**
 * Display payment methods selection.
 *
 * Variables:
 * $gateway WC_Gateway_Everypay
 */

    $language = $gateway->get_default_language();
    $langauges = $gateway->get_method_languages();
?>

<?php if(!empty($langauges)): ?>
    <div class="method-languages">
        <?php foreach ($langauges as $lang): ?>
            <label>
                <?php echo esc_html($lang->name); ?>
                <input type="radio" name="<?php echo esc_attr($gateway->get_input_name('language')); ?>" value="<?php echo esc_html($lang->code); ?>" <?php if($language == $lang->code) echo 'checked'; ?>>
            </label>
        <?php endforeach ?>
    </div>
<?php endif; ?>

<?php $methods = $gateway->get_methods(WC_Gateway_Everypay::TYPE_CARD); ?>

<?php if(count($methods)): ?>

    <?php if($gateway->get_token_hint()): ?>
        <p><?php esc_html_e( 'To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'everypay') ?></p>
    <?php endif; ?>

    <p><?php echo esc_html($gateway->get_card_title()); ?></p>
    <fieldset id="<?php echo esc_attr($gateway->get_id()); ?>-cc-form">

        <?php do_action('woocommerce_credit_card_form_start', $gateway->get_id()); ?>

        <?php include(WC_Everypay()->plugin_path('templates/payment-methods-options.php')); ?>

        <?php if($gateway->get_token_enabled() && !empty($gateway->get_user_tokens())): ?>
            <p class="form-row form-row-wide">

                <?php foreach($gateway->get_user_tokens() as $token): ?>
                    <?php if(true === $token['active']): ?>
                        <label class="payment-token-option">
                            <img src="<?php echo esc_attr(plugins_url('/assets/images/', dirname(__FILE__)) . $token['cc_type']); ?>.png" alt="<?php echo esc_attr($token['labels']['type_name']); ?>" width="47" height="30">
                            <?php echo esc_html($token['labels']['card_name']); ?>
                            <input type="radio" name="<?php echo esc_attr($gateway->get_input_name('token')); ?>" value="<?php echo esc_attr($token['cc_token']); ?>"/>
                        </label>
                        <br/>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if($myaccount_page_id): ?>
                    <a style="float:right;" href="<?php echo esc_attr(get_permalink($myaccount_page_id)); ?>#saved-cards" class="wc_everypay_manage_cards" target="_blank"><?php esc_html_e('Manage cards', 'everypay'); ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php do_action( 'woocommerce_credit_card_form_end', $gateway->get_id()); ?>

    </fieldset>
<?php endif; ?>

<?php $methods = $gateway->get_methods(WC_Gateway_Everypay::TYPE_BANK); ?>

<?php if(count($methods)): ?>
    <p><?php echo esc_html($gateway->get_bank_title()); ?></p>
    <fieldset id="<?php echo esc_attr($gateway->get_id()); ?>-ob-form">

        <?php include(WC_Everypay()->plugin_path('templates/payment-methods-options.php')); ?>

    </fieldset>
<?php endif; ?>

<?php $methods = $gateway->get_methods(WC_Gateway_Everypay::TYPE_ALTER); ?>

<?php if(count($methods)): ?>
    <p><?php echo esc_html($gateway->get_alternative_title()); ?></p>
    <fieldset id="<?php echo esc_attr($gateway->get_id()); ?>-alt-form">

        <?php include(WC_Everypay()->plugin_path('templates/payment-methods-options.php')); ?>

    </fieldset>
<?php endif; ?>