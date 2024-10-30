<?php
/**
 * Plugin Name: Jigoshop Payflow Gateway
 * Plugin URI: https://wordpress.org/plugins/jigoshop-payflow-gateway/
 * Description: Allows you to use <a href="https://www.paypal.com/us/webapps/mpp/payflow-payment-gateway">Payflow</a> gateway with the Jigoshop plugin.
 * Version: 3.0.5
 * Author: Jigo Ltd
 * Author URI: https://www.jigoshop.com
 */
// Define plugin name
define('JIGOSHOP_PAYFLOW_GATEWAY_NAME', 'Jigoshop Payflow Gateway');
add_action('plugins_loaded', function () {
    load_plugin_textdomain('jigoshop_payflow_gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    if (class_exists('\Jigoshop\Core')) {
        //Check version.
        if (\Jigoshop\addRequiredVersionNotice(JIGOSHOP_PAYFLOW_GATEWAY_NAME, '2.1.6')) {
            return;
        }
        // Define plugin directory for inclusions
        define('JIGOSHOP_PAYFLOW_GATEWAY_DIR', dirname(__FILE__));
        // Define plugin URL for assets
        define('JIGOSHOP_PAYFLOW_GATEWAY_URL', plugins_url('', __FILE__));
        //Init components.
        require_once(JIGOSHOP_PAYFLOW_GATEWAY_DIR . '/src/Jigoshop/Extension/PayflowGateway/Common.php');
        if (is_admin()) {
            require_once(JIGOSHOP_PAYFLOW_GATEWAY_DIR . '/src/Jigoshop/Extension/PayflowGateway/Admin.php');
        }
    } elseif (class_exists('jigoshop')) {
        //Check version.
        if (jigoshop_add_required_version_notice(JIGOSHOP_PAYFLOW_GATEWAY_NAME, '1.17')) {
            return;
        }
        // Define plugin directory for inclusions
        define('JIGOSHOP_PAYFLOW_GATEWAY_DIR', dirname(__FILE__) . '/Jigoshop1x');
        // Define plugin URL for assets
        define('JIGOSHOP_PAYFLOW_GATEWAY_URL', plugins_url('', __FILE__) . '/Jigoshop1x');
        //Init components.
        require_once(JIGOSHOP_PAYFLOW_GATEWAY_DIR . '/jigoshop-payflow-gateway.php');
    } else {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>';
            printf(__('%s requires Jigoshop plugin to be active. Code for plugin %s was not loaded.',
                'jigoshop_payflow_gateway'), JIGOSHOP_PAYFLOW_GATEWAY_NAME, JIGOSHOP_PAYFLOW_GATEWAY_NAME);
            echo '</p></div>';
        });
    }
});
