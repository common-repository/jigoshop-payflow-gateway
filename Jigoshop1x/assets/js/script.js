jQuery(function($){
	$("#payflow_payment_form").payment({
		message: jigoshop_payflow_gateway.i18n.thank_you,
		redirect: jigoshop_payflow_gateway.i18n.redirecting
	});
});
