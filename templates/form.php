<form action="<?php echo esc_url($url); ?>" method="post" id="payflow_payment_form">
	<input type="submit" class="button-alt" id="submit_payflow_payment_form" value="<?php _e('Pay via Payflow', 'jigoshop_payflow_gateway'); ?>" />
    <a class="button cancel" href="<?php esc_url($cancelUrl); ?>"><?php _e('Cancel order &amp; restore cart', 'jigoshop_payflow_gateway');?></a>
</form>