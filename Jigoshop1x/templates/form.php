<form action="<?php echo esc_url($url); ?>" method="post" id="payflow_payment_form">
	<input type="submit" class="button-alt" id="submit_payflow_payment_form" value="<?php _e('Pay via Payflow', 'jigoshop_payflow_gateway'); ?>" />
	<?php
	jigoshop_add_script('jigoshop-payflow-gateway', JIGOSHOP_PAYFLOW_GATEWAY_URL.'/assets/js/script.js', array('jquery'), array('page' => JIGOSHOP_CHECKOUT));
	jigoshop_localize_script('jigoshop-payflow-gateway', 'jigoshop_payflow_gateway', array(
		'assets_url' => JIGOSHOP_URL,
		'i18n' => array(
			'thank_you' => __('Thank you for your order. We are now redirecting you to Payflow to make a payment.', 'jigoshop_payflow_gateway'),
			'redirecting' => __('Redirecting...', 'jigoshop')
		),
	)); ?>
</form>