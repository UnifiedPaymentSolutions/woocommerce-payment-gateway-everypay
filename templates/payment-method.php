<?php
/**
 * Display payment methods selection.
 *
 * Variables:
 * $method_id string
 * $token_hint boolean
 * $token_enabled boolean
 * $token_ask boolean
 * $tokens array
 * $myaccount_page_id int
 * $has_card boolean
 * $bank_methods array

 * $methods array

 */
?>

<?php if(!empty($method_languages)): ?>
    <div class="method-languages">
        <?php foreach ($method_languages as $language): ?>
            <label>
                <?php echo esc_html($language->name); ?>
                <input type="radio" name="method_language" value="<?php echo esc_html($language->code); ?>" <?php if($default_language == $language->code) echo 'checked'; ?>>
            </label>
        <?php endforeach ?>
    </div>
<?php endif; ?>

<?php if($has_card): ?>
    <?php if($token_hint): ?>
        <p><?php esc_html_e( 'To save your card securely for easier future payments, sign up for an account or log in to your existing account.', 'everypay') ?></p>
    <?php endif; ?>
    <!-- Card method -->
    <p><?php echo esc_html($card_title); ?></p>
    <fieldset id="<?php echo esc_attr($method_id); ?>-cc-form">

        <?php do_action('woocommerce_credit_card_form_start', $method_id); ?>

        <?php if($token_enabled && !empty($tokens)): ?>
            <p class="form-row form-row-wide">
                <?php foreach($tokens as $token): ?>
                    <?php if(true === $token['active']): ?>
                        <input type="radio" id="wc_everypay_token_<?php echo esc_attr($token['cc_token']); ?>" name="wc_everypay_token" class="wc_everypay_token" style="width:auto;" value="<?php echo esc_attr($token['cc_token']); ?>"/>
                        <label class="wc_everypay_token_label" for="wc_everypay_token_<?php echo esc_attr($token['cc_token']); ?>" style="display: inline-block">
                            <img src="<?php echo esc_attr(plugins_url('/assets/images/', dirname(__FILE__)) . $token['cc_type']); ?>.png" alt="<?php echo esc_attr($token['labels']['type_name']); ?>" width="47" height="30" style="padding-right: 0.5em">
                            <?php echo esc_html($token['labels']['card_name']); ?>
                        </label>
                        <br/>
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="radio" id="wc_everypay_token_add" name="wc_everypay_token" class="wc_everypay_token" style="width:auto;" value="add_new" />
                <label class="wc_everypay_token_label" for="wc_everypay_token_add" style="display: inline-block"><?php esc_html_e('Use a new card', 'everypay'); ?></label>
                <?php if($myaccount_page_id): ?>
                    <a style="float:right;" href="<?php echo esc_attr(get_permalink($myaccount_page_id)); ?>#saved-cards" class="wc_everypay_manage_cards" target="_blank"><?php esc_html_e('Manage cards', 'everypay'); ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php do_action( 'woocommerce_credit_card_form_end', $method_id); ?>

        <div class="clear"></div>

        <?php if($token_ask): ?>
            <p class="form-row form-row-wide" style="<?php if(!empty($tokens)) echo 'display: none'; ?>">
                <input name="wc_everypay_tokenize_payment" id="wc_everypay_tokenize_payment" type="checkbox" value="true" style="width:auto;" />
                <label for="wc_everypay_tokenize_payment" style="display:inline;"><?php echo apply_filters('wc_everypay_tokenize_payment', esc_html__('Save card securely', 'everypay')); ?></label>
            </p>
        <?php else: ?>
            <input name="wc_everypay_tokenize_payment" id="wc_everypay_tokenize_payment" type="hidden" value="true" />
        <?php endif; ?>
    </fieldset>
<?php endif; ?>

<?php if(!empty($bank_methods)): ?>
    <!-- Bank methods -->
    <p><?php echo esc_html($bank_title); ?></p>
    <fieldset id="<?php echo esc_attr($method_id); ?>-ob-form">
        <?php foreach ($bank_methods as $key => $method): ?>
            <label class="payment-method-option <?php if($method->country && $default_language != $method->country) echo 'hidden'; ?>">
                <img src="<?php echo esc_attr($method->logo); ?>" alt="<?php echo esc_attr($method->name); ?>">
                <input type="radio" data-language="<?php echo esc_attr($method->country); ?>" name="<?php echo esc_attr($method_id); ?>-method" value="<?php echo esc_attr($method->source); ?>">
            </label>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>

<?php if(!empty($alternative_methods)): ?>
    <!-- Alternative methods -->
    <p><?php echo esc_html($alternative_title); ?></p>
    <fieldset id="<?php echo esc_attr($method_id); ?>-alt-form">
        <?php foreach ($alternative_methods as $key => $method): ?>
            <label class="payment-method-option <?php if($method->country && $default_language != $method->country) echo 'hidden'; ?>">
                <img src="<?php echo esc_attr($method->logo); ?>" alt="<?php echo esc_attr($method->name); ?>">
                <input type="radio" data-language="<?php echo esc_attr($method->country); ?>" name="<?php echo esc_attr($method_id); ?>-method" value="<?php echo esc_attr($method->source); ?>">
            </label>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>

<script type="text/javascript">
    jQuery(function($) {
        var $wrapper = $(".payment_method_<?php echo esc_attr($method_id); ?>"),
            $method_options = $wrapper.find('label.payment-method-option input');

        $wrapper.find('.method-languages input').change(function() {
            var country = this.value;

            $('label.payment-method-option').removeClass('selected');
            $method_options.prop('checked', false).each(function() {
                var language = $(this).data('language');
                if(!language || language == country) {
                    $(this).parents('label.payment-method-option').removeClass('hidden');
                } else {
                    $(this).parents('label.payment-method-option').addClass('hidden');
                }
            });
        });

        $method_options.change(function() {
            $('label.payment-method-option').removeClass('selected');
            if(this.checked) {
                $(this).parents('label.payment-method-option').addClass('selected');
            }
        });
    });

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