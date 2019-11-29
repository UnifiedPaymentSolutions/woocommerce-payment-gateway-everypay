<?php if(!empty($methods)): ?>

    <?php foreach ($methods as $method): ?>
        <label class="payment-method-option <?php if($method->country && $preferred_country != $method->country) echo 'hidden'; ?>">
            <img src="<?php echo esc_attr($method->logo); ?>" alt="<?php echo esc_attr($method->name); ?>">
            <input type="radio"
                data-language="<?php echo esc_attr($method->country); ?>"
                name="<?php echo esc_attr($gateway_id); ?>[method]"
                value="<?php echo esc_attr($method->source); ?>"
                >
        </label>
    <?php endforeach; ?>

<?php endif; ?>