<?php

namespace Jigoshop\Gateway;

class Payflow extends \jigoshop_payment_gateway
{
    private $vendor;
    private $user;
    private $partner;
    private $password;
    private $currency;
    private $testUrl;
    private $liveUrl;
    private $returnUrl;
    private $merchantCountries = array(
        'AU',
        'CA',
        'NZ',
        'US',
    );
    private $allowedCurrencies = array(
        'AUD',
        'CAD',
        'CHF',
        'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HKD',
        'HUF',
        'JPY',
        'NOK',
        'NZD',
        'PLN',
        'SEK',
        'SGD',
        'USD',
    );

    public function __construct()
    {
        parent::__construct();

        $options = \Jigoshop_Base::get_options();
        $this->id = 'payflow';
        $this->icon = apply_filters('jigoshop_payflow_icon', JIGOSHOP_PAYFLOW_GATEWAY_URL . '/assets/images/paypal.gif');
        $this->enabled = $options->get('jigoshop_payflow_enabled');
        $this->title = $options->get('jigoshop_payflow_title');
        $this->description = $options->get('jigoshop_payflow_description');
        $this->testmode = $options->get('jigoshop_payflow_testmode');

        $this->partner = $options->get('jigoshop_payflow_partner');
        $this->vendor = $options->get('jigoshop_payflow_vendor');
        $this->user = $options->get('jigoshop_payflow_user');
        $this->password = $options->get('jigoshop_payflow_password');
        $this->currency = apply_filters('jigoshop_multi_currencies_currency', $options->get('jigoshop_currency'));
        $this->shopBaseCountry = \jigoshop_countries::get_base_country();
        $this->testUrl = 'https://pilot-payflowpro.paypal.com';
        $this->liveUrl = 'https://payflowpro.paypal.com';
        $this->returnUrl = \jigoshop_request_api::query_request('?js-api=JS_Gateway_Payflow', true);

        add_action('jigoshop_api_js_gateway_payflow', array($this, 'payflowRedirectCheck'));
        add_action('admin_notices', array($this, 'payflowNotices'));
        add_action('receipt_payflow', array($this, 'receiptPayflow'));
    }

    /**
     * Check if SSL is forced on the checkout page.
     **/
    public function payflowNotices()
    {
        if ($this->enabled == 'no') {
            return;
        }

        $options = \Jigoshop_Base::get_options();
        if (!in_array($this->currency, $this->allowedCurrencies)) {
            echo '<div class="error"><p>' . sprintf(__('The Payflow gateway accepts payments in currencies of %s.  Your current currency is %s.  Payflow won\'t work until you change the Jigoshop currency to an accepted one. Payflow is <strong>currently disabled</strong> on the Payment Gateways settings tab.', 'jigoshop_payflow_gateway'), implode(', ', $this->allowedCurrencies), $this->currency) . '</p></div>';
            $options->set('jigoshop_payflow_enabled', 'no');
        }

        if (!in_array($this->shopBaseCountry, $this->merchantCountries)) {
            echo '<div class="error"><p>' . sprintf(__('Payflow gateway is available for merchants from %s. Your country is %s. Payflow gateway won\'t work until you change the Jigoshop base country to one accepted.', 'jigoshop_payflow_gateway'), implode(', ', $this->merchantCountries), $this->shopBaseCountry) . '</p></div>';
            $options->set('jigoshop_payflow_enabled', 'no');
        }

        if ($options->get('jigoshop_force_ssl_checkout') == 'no' && $this->testmode == 'no') {
            echo '<div class="error"><p>' . __('Payflow is enabled to accept live payments, but your checkout page is not secured with SSL. Please enable the "Force SSL on checkout" option under Settings > General > Checkout Page > Force SSL on checkout. <br/>If you do not enable SSL, your payments will be refused from Payflow system.', 'jigoshop_payflow_gateway') . '</p></div>';
        }
    }

    /**
     * Default Option settings for WordPress Settings API using the Jigoshop_Options class
     */
    protected function get_default_options()
    {
        $defaults = array(
            array(
                'name' => __('Payflow Payment Gateway', 'jigoshop_payflow_gateway'),
                'type' => 'title',
                'desc' => __("Payflow is secure, open payment gateway. You can choose to have PayPal host your payment pages or have total control over the payment process. Payflow allows merchants to choose any Internet Merchant Account to accept debit or credit card payments and connect to any major processor.", 'jigoshop_payflow_gateway')
            ),
            array(
                'name' => __('Enable Payflow', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => '',
                'id' => 'jigoshop_payflow_enabled',
                'std' => 'yes',
                'type' => 'checkbox',
                'choices' => array(
                    'no' => __('No', 'jigoshop_payflow_gateway'),
                    'yes' => __('Yes', 'jigoshop_payflow_gateway')
                )
            ),
            array(
                'name' => __('Method Title', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('This controls the title which the user sees during checkout.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_title',
                'std' => __('Payflow', 'jigoshop_payflow_gateway'),
                'type' => 'longtext'
            ),
            array(
                'name' => __('Description', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('This controls the description which the user sees during checkout.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_description',
                'std' => __("Pay using your credit or debit card.", 'jigoshop_payflow_gateway'),
                'type' => 'textarea'
            ),
            array(
                'name' => __('Partner', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('Your Payflow Partner name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_partner',
                'std' => '',
                'type' => 'longtext'
            ),
            array(
                'name' => __('Merchant Login Name', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('Your Payflow Merchant Login Name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_vendor',
                'std' => '',
                'type' => 'longtext'
            ),
            array(
                'name' => __('User Login Name', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('Your Payflow User Login Name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_user',
                'std' => '',
                'type' => 'longtext'
            ),
            array(
                'name' => __('Password', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => __('Your Payflow Password is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_password',
                'std' => '',
                'type' => 'longtext'
            ),
            array(
                'name' => __('Enable Test Mode', 'jigoshop_payflow_gateway'),
                'desc' => '',
                'tip' => '',
                'id' => 'jigoshop_payflow_testmode',
                'std' => 'yes',
                'type' => 'checkbox',
                'choices' => array(
                    'no' => __('No', 'jigoshop_payflow_gateway'),
                    'yes' => __('Yes', 'jigoshop_payflow_gateway')
                )
            )
        );

        return $defaults;
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    /**
     * Process the payment and return the result
     *
     * @param int $orderId
     * @return array
     */
    public function process_payment($orderId)
    {
        $order = new \jigoshop_order($orderId);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg(array('order' => $order->id, 'key' => $order->order_key), get_permalink(jigoshop_get_page_id('pay'))),
        );
    }

    /**
     *  Receipt_page
     *
     * @param $orderId
     */
    public function receiptPayflow($orderId)
    {
        $order = new \jigoshop_order($orderId);

        try {
            $result = $this->payflowRequest($order);
            if ($result['RESULT'] == 0) {
                $secureToken = $result['SECURETOKEN'];
                $secureTokenId = $result['SECURETOKENID'];
                if ($this->testmode == 'yes') {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $mode = 'TEST';
                } else {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $mode = 'LIVE';
                }

                /** @noinspection PhpUnusedLocalVariableInspection */
                $url = 'https://payflowlink.paypal.com?SECURETOKEN=' . $secureToken . '&SECURETOKENID=' . $secureTokenId . '&MODE=' . $mode;
                include(JIGOSHOP_PAYFLOW_GATEWAY_DIR . '/templates/form.php');
            }
        } catch (\Exception $e) {
            //Add order note that an error occurred
            $order->add_order_note(sprintf(__('Error occured setting up the order. Error message: %s', 'jigoshop_payflow_gateway'), $e->getMessage()));
            //Show error to the customer
            \jigoshop::add_error(__('Error occured while setting up the order. Please try again or contact the administrator.', 'jigoshop_payflow_gateway'));
            \jigoshop::show_messages();
        }
    }

    public function payflowRedirectCheck()
    {
        if (!empty($_GET['payflowListener']) && ($_GET['payflowListener'] == 'return' || ($_GET['payflowListener'] == 'error' && !empty($_GET['order'])))) {
            $this->processPayflowResponse($_POST);
        } else {
            wp_die('Payflow Request Failure');
        }
    }

    private function processPayflowResponse($posted)
    {
        if ($posted['RESULT'] == 0 && !empty($posted['ORDERID'])) {
            $order = new \jigoshop_order($posted['ORDERID']);
            if ($order) {
                $order->payment_complete();
                $order->add_order_note(__('Payflow payment completed', 'jigoshop_payflow_gateway'));
                wp_safe_redirect(add_query_arg(array('key' => $order->order_key, 'order' => $order->id), get_permalink(jigoshop_get_page_id('thanks'))));
                exit;
            }
        } else if ($posted['RESULT'] == 12) {
            $errorMsg = sprintf(__('Order payment was declined. Payflow Gateway Message: %s', 'jigoshop_payflow_gateway'), $posted['RESPMSG']);
            $orderId = intval(sanitize_text_field($_GET['order']));
            $order = new \jigoshop_order($orderId);
            if ($order) {
                $order->update_status('on-hold', $errorMsg);
                \jigoshop::add_error($errorMsg);
                wp_safe_redirect(add_query_arg(array('key' => $order->order_key, 'order' => $order->id), get_permalink(jigoshop_get_page_id('checkout'))));
                exit;
            }
        } else {
            $errorMsg = sprintf(__('Payflow Gateway Error: (%s)', 'jigoshop_payflow_gateway'), $posted['RESPMSG']);
            $orderId = intval(sanitize_text_field($_GET['order']));
            $order = new \jigoshop_order($orderId);
            if ($order) {
                $order->update_status('cancelled', $errorMsg);
                \jigoshop::add_error(sprintf(__('Error occurred while processing the order. (%s)', 'jigoshop_payflow_gateway'), $errorMsg));
                wp_safe_redirect(add_query_arg(array('key' => $order->order_key, 'order' => $order->id), get_permalink(jigoshop_get_page_id('checkout'))));
                exit;
            }
        }
    }

    private function payflowRequest(\jigoshop_order $order)
    {
        $params = $this->payflowParams($order);
        $paramList = array();

        foreach ($params as $index => $value) {
            $paramList[] = $index . "[" . strlen($value) . "]=" . $value;
        }

        if ($this->testmode == 'yes') {
            $url = $this->testUrl;
        } else {
            $url = $this->liveUrl;
        }
        $apiStr = implode("&", $paramList);

        // Initialize our cURL handle.
        $result = wp_remote_post($url, ['method' => 'POST', 'sslverify' => false, 'body' => $apiStr]);

        if (is_wp_error($result)) {
            throw new \Exception("Error: " . $result->get_error_message());
        }
        else{
            $response = wp_remote_retrieve_body($result);
        }

        return $this->parsePayflowString($response);
    }

    private function parsePayflowString($str)
    {

        $workstr = $str;
        $out = array();

        while (strlen($workstr) > 0) {
            $loc = strpos($workstr, '=');
            if ($loc === false) {
                // Truncate the rest of the string, it's not valid
                $workstr = "";
                continue;
            }

            $substr = substr($workstr, 0, $loc);
            $workstr = substr($workstr, $loc + 1); // "+1" because we need to get rid of the "="

            if (preg_match('/^(\w+)\[(\d+)]$/', $substr, $matches)) {
                // This one has a length tag with it.  Read the number of characters
                // specified by $matches[2].
                $count = intval($matches[2]);

                $out[$matches[1]] = substr($workstr, 0, $count);
                $workstr = substr($workstr, $count + 1); // "+1" because we need to get rid of the "&"
            } else {
                // Read up to the next "&"
                $count = strpos($workstr, '&');
                if ($count === false) { // No more "&"'s, read up to the end of the string
                    $out[$substr] = $workstr;
                    $workstr = "";
                } else {
                    $out[$substr] = substr($workstr, 0, $count);
                    $workstr = substr($workstr, $count + 1); // "+1" because we need to get rid of the "&"
                }
            }
        }

        return $out;
    }

    private function payflowParams(\jigoshop_order $order)
    {
        $returnUrl = add_query_arg('payflowListener', 'return', $this->returnUrl);
        $errorUrl = add_query_arg('order', $order->id, add_query_arg('payflowListener', 'error', $this->returnUrl));
        $cancelUrl = $order->get_cancel_order_url();

        $result = array(
            "PARTNER" => $this->partner,
            "VENDOR" => $this->vendor,
            "USER" => $this->user,
            "PWD" => $this->password,
            "TENDER" => "C",
            "TRXTYPE" => "S",
            "TEMPLATE" => "TEMPLATEA",
            "CURRENCY" => $this->currency,
            "AMT" => round($order->order_total, 2),
            "ORDERID" => $order->id,
            "CREATESECURETOKEN" => "Y",
            "SECURETOKENID" => uniqid(), //Should be unique, never used before
            "RETURNURL" => $returnUrl,
            "CANCELURL" => $cancelUrl,
            "ERRORURL" => $errorUrl,
            "BILLTOFIRSTNAME" => $order->billing_first_name,
            "BILLTOLASTNAME" => $order->billing_last_name,
            "BILLTOSTREET" => $order->billing_address_1,
            "BILLTOCITY" => $order->billing_city,
            "BILLTOSTATE" => $order->billing_state,
            "BILLTOZIP" => $order->billing_postcode,
            "BILLTOCOUNTRY" => $order->billing_country,
        );

        if ($order->shipping_address_1) {
            $result["SHIPTOFIRSTNAME"] = $order->shipping_first_name;
            $result["SHIPTOLASTNAME"] = $order->shipping_last_name;
            $result["SHIPTOSTREET"] = $order->shipping_address_1;
            $result["SHIPTOCITY"] = $order->shipping_city;
            $result["SHIPTOSTATE"] = $order->shipping_state;
            $result["SHIPTOZIP"] = $order->shipping_postcode;
            $result["SHIPTOCOUNTRY"] = $order->billing_country;
        }

        return $result;
    }
}
