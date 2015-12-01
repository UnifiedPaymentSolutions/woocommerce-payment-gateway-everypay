<?php
/**
 * Manage saved cards section on My Account page
 *
 * User: petskratt
 * Date: 01/12/15
 * Time: 10:25
 */



?><h2 id="saved-cards" style="margin-top:40px;"><?php _e( 'Manage saved cards', 'everypay' ); ?></h2>
<table class="shop_table everypay_manage_tokens">
	<thead>
	<tr>
		<th class="everypay_manage_tokens__card"><?php _e( 'Card', 'everypay' ); ?></th>
		<th class="everypay_manage_tokens__actions"><?php _e( 'Actions', 'everypay' ); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $tokens as $token ) : ?>
		<tr>
			<td class="everypay_manage_tokens__card"><?php
				echo '<img src="' . plugins_url( '/assets/images/', dirname( __FILE__ ) ) . $token['cc_type'] . '.png" alt="' . $token['cc_type'] . '" width="47" height="30" style="padding-right: 0.5em">';
				echo '&nbsp;****&nbsp;****&nbsp;****&nbsp;' . $token['cc_last_four_digits'];
				$expired_class = $token['active'] ? '' : 'class="everypay_manage_tokens__card_inactive"';
				echo ' <span ' . $expired_class . '>('.$token['cc_month'] . '/' . $token['cc_year'] . ')</span>';
				?></td>
			<td class="everypay_manage_tokens__actions"><?php if (isset($token['default']) && true === $token['default'] ) {
					echo __('Default', 'everypay');
				} else if (true === $token['active']) { ?>
					<form method="POST">
						<?php echo $nonce_field; ?>
						<input type="hidden" name="everypay_set_default" value="<?php echo esc_attr( $token['cc_token'] ); ?>">
						<input type="submit" class="button everypay_manage_tokens__button_default" value="<?php _e( 'Make Default', 'everypay' ); ?>">
					</form>
				<?php
				}
				?>
				<form method="POST">
					<?php echo $nonce_field; ?>
					<input type="hidden" name="everypay_remove_token" value="<?php echo esc_attr( $token['cc_token'] ); ?>">
					<input type="submit" class="button everypay_manage_tokens__button_delete" value="<?php _e( 'Delete', 'everypay' ); ?>">
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<style>
	table.everypay_manage_tokens {
		font-size: 0.85em;
	}

	td.everypay_manage_tokens__actions, th.everypay_manage_tokens__actions {
		text-align: right;
	}

	.woocommerce input.button.everypay_manage_tokens__button_delete, .woocommerce input.button.everypay_manage_tokens__button_default {
		margin-bottom: 0.35em;
		text-transform: none;
	}

	.everypay_manage_tokens__card_inactive {
		color: red;
	}

</style>

