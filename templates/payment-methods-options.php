<?php if(!empty($methods)): ?>

    <div class="payment-method-options">
        <?php foreach ($methods as $method): ?>
            <label class="payment-method-option <?php if($method->country && $preferred_country != $method->country) echo 'hidden'; ?>">
                <?php if($method->logo): ?>
                    <img src="<?php echo esc_attr($method->logo); ?>" alt="<?php echo esc_attr($method->name); ?>">
                <?php else: ?>
                    <span><?php echo esc_html($method->name); ?></span>
                <?php endif; ?>
                <input type="radio"
                    data-country="<?php echo esc_attr($method->country); ?>"
                    name="<?php echo esc_attr($gateway_id); ?>[method]"
                    value="<?php echo esc_attr($method->source); ?>"
                    >
            </label>
        <?php endforeach; ?>
    </div>

<?php endif; ?>