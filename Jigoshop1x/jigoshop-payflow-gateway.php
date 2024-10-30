<?php
    
require_once(JIGOSHOP_PAYFLOW_GATEWAY_DIR . '/src/Jigoshop/Gateway/Payflow.php');

add_filter('jigoshop_payment_gateways', function ($methods) {
    $methods[] = '\Jigoshop\Gateway\Payflow';

    return $methods;
});

add_filter('plugin_action_links_' . plugin_basename(dirname(JIGOSHOP_PAYFLOW_GATEWAY_DIR) . '/bootstrap.php'),
    function ($links) {
        $links[] = '<a href="https://www.jigoshop.com/documentation/jigoshop-paypal-payflow-pro-gateway/" target="_blank">Documentation</a>';
        $links[] = '<a href="https://www.jigoshop.com/support/" target="_blank">Support</a>';
        $links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/jigoshop#$postform" target="_blank">Rate Us</a>';
        $links[] = '<a href="https://www.jigoshop.com/product-category/extensions/" target="_blank">More plugins for Jigoshop</a>';

        return $links;
    });
