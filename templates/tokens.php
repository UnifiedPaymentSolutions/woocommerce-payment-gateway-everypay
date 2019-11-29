<p class="form-row form-row-wide">

    <?php foreach($tokens as $token): ?>
        <?php if(true === $token['active']): ?>
            <label class="payment-token-option">
                <img src="<?php echo esc_attr(plugins_url('/assets/images/', dirname(__FILE__)) . $token['cc_type']); ?>.png" alt="<?php echo esc_attr($token['labels']['type_name']); ?>" width="47" height="30">
                <?php echo esc_html($token['labels']['card_name']); ?>
                <input type="radio" name="<?php esc_attr($gateway_id); ?>[token]" value="<?php echo esc_attr($token['cc_token']); ?>"/>
            </label>
            <br/>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if($myaccount_page_id): ?>
        <a style="float:right;" href="<?php echo esc_attr(get_permalink($myaccount_page_id)); ?>#saved-cards" class="wc_everypay_manage_cards" target="_blank"><?php esc_html_e('Manage cards', 'everypay'); ?></a>
    <?php endif; ?>
</p>
