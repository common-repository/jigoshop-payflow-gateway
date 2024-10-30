<?php

namespace Jigoshop\Extension\PayflowGateway\Common;

use DOMDocument;
use DOMXPath;
use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Entity\Order;
use Jigoshop\Exception;
use Jigoshop\Frontend\Pages;
use Jigoshop\Helper\Country;
use Jigoshop\Helper\Currency;
use Jigoshop\Helper\Options as OptionsHelper;
use Jigoshop\Integration\Helper\Render;
use Jigoshop\Payment\Method3;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\OrderServiceInterface;

class Method implements Method3
{
    const ID = 'payflow_payment_gateway';
    /** @var  Options */
    private $options;
    /** @var  OrderService */
    private $orderService;
    /** @var  Messages */
    private $messages;
    /**@var CardService */
    private $cartService;
    /** @var array */
    private $settings;

    private $vendor;
    private $user;
    private $partner;
    private $password;
    private $currency;
    private $testUrl;
    private $liveUrl;
    private $returnUrl;
    private $shopBaseCountry;

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

    /**
     * @param Options $options
     * @param CartServiceInterface $cartService
     * @param OrderServiceInterface $orderService
     * @param Messages $messages
     * @internal param string $secretKey
     */

    public function __construct(Options $options, CartServiceInterface $cartService, OrderServiceInterface $orderService, Messages $messages)
    {
        $this->options = $options;
        $this->messages = $messages;
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        $this->shopBaseCountry = $options->get('general.country');
        $this->currency = Currency::code();

        // Merchant URLs
        $this->testUrl = 'https://pilot-payflowpro.paypal.com';
        $this->liveUrl = 'https://payflowpro.paypal.com';
        $this->returnUrl = Method::request('?js-api=Js_Gateway_Payflow', false);

        OptionsHelper::setDefaults('payment.' . self::ID, array(
            'enabled' => false,
            'title' => __('Payflow', 'jigoshop_payflow_gateway'),
            'description' => __('Pay via Payflow', 'jigoshop_payflow_gateway'),
            'vendor' => '',
            'user' => '',
            'partner' => '',
            'password' => '',
            'test_mode' => false,
            'adminOnly' => false,
        ));

        $this->settings = OptionsHelper::getOptions('payment.' . self::ID);

        $this->vendor = $this->settings['vendor'];
        $this->user = $this->settings['user'];
        $this->partner = $this->settings['partner'];
        $this->password = $this->settings['password'];


        add_filter('jigoshop\pay\render', array($this, 'renderPay'), 10, 2);
        add_action('init', array($this, 'payflowRedirectCheck'), 99);
        add_action('valid_payflow_request', array($this, 'payflowStatusResponse'));

    }

    public function payflowRedirectCheck()
    {
        @ob_clean();
        if (isset($_REQUEST['js-api']) && $_REQUEST['js-api'] == 'Js_Gateway_Payflow') {
            header('HTTP/1.1 200 OK');
            do_action("valid_payflow_request", $_REQUEST);
        }
        if (isset($_REQUEST['TYPE']) && $_REQUEST['TYPE'] == 'S') {
            do_action("valid_payflow_request", $_REQUEST);
        }
    }

    public static function request($request, $ssl = null)
    {
        if (is_null($ssl)) {
            $scheme = parse_url(get_option('home'), PHP_URL_SCHEME);
        } elseif ($ssl) {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }
        return esc_url_raw(home_url('/', $scheme) . $request);
    }

    /**
     * @return string ID of payment method.
     */
    public function getId()
    {
        return self::ID;
    }

    /**
     * @return string Human readable name of method.
     */
    public function getName()
    {
        return is_admin() ? $this->getLogoImage() . ' ' . __('Payflow', 'jigoshop_payflow_gateway') : $this->settings['title'];
    }

    private function getLogoImage()
    {
        return '<img src="' . JIGOSHOP_PAYFLOW_GATEWAY_URL . '/assets/images/paypal.gif' . '" alt="Payu Latam" width="" height="" style="border:none !important;" class="shipping-logo" />';
    }

    /**
     * @return bool Whether current method is enabled and able to work.
     */
    public function isEnabled()
    {
        return $this->settings['enabled'];
    }

    /**
     * @return array List of options to display on Payment settings page.
     */
    public function getOptions()
    {
        return array(
            array(
                'name' => sprintf('[%s][enabled]', self::ID),
                'title' => __('Enable Payflow', 'jigoshop_payflow_gateway'),
                'id' => 'jigoshop_payflow_enabled',
                'type' => 'checkbox',
                'checked' => $this->settings['enabled'],
                'classes' => array('switch-medium'),
            ),
            array(
                'name' => sprintf('[%s][title]', self::ID),
                'title' => __('Method Title', 'jigoshop_payflow_gateway'),
                'tip' => __('This controls the title which the user sees during checkout.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['title'],
            ),
            array(
                'name' => sprintf('[%s][description]', self::ID),
                'title' => __('Description', 'jigoshop_payflow_gateway'),
                'tip' => __('This controls the description which the user sees during checkout.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['description']
            ),
            array(
                'name' => sprintf('[%s][partner]', self::ID),
                'title' => __('Partner', 'jigoshop_payflow_gateway'),
                'tip' => __('Your Payflow Partner name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['partner'],
            ),
            array(
                'name' => sprintf('[%s][vendor]', self::ID),
                'title' => __('Merchant Login Name', 'jigoshop_payflow_gateway'),
                'tip' => __('Your Payflow Merchant Login Name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['vendor'],
            ),
            array(
                'name' => sprintf('[%s][user]', self::ID),
                'title' => __('User Login Name', 'jigoshop_payflow_gateway'),
                'tip' => __('Your Payflow User Login Name is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['user'],
            ),
            array(
                'name' => sprintf('[%s][password]', self::ID),
                'title' => __('Password', 'jigoshop_payflow_gateway'),
                'tip' => __('Your Payflow Password is required to authenticate your request.', 'jigoshop_payflow_gateway'),
                'type' => 'text',
                'value' => $this->settings['password'],
            ),
            array(
                'name' => sprintf('[%s][test_mode]', self::ID),
                'title' => __('Enable Test Mode', 'jigoshop_payflow_gateway'),
                'type' => 'checkbox',
                'checked' => $this->settings['test_mode'],
                'classes' => array('switch-medium'),
            ),
            array(
                'name' => sprintf('[%s][adminOnly]', self::ID),
                'title' => __('Enable Only for Admin', 'jigoshop_payflow_gateway'),
                'type' => 'checkbox',
                'description' => __('Enable this if you would like to test it only for Site Admin', 'jigoshop_payflow_gateway'),
                'checked' => $this->settings['adminOnly'],
                'classes' => array('switch-medium'),
            ),
        );
    }

    /**
     * Validates and returns properly sanitized options.
     *
     * @param $settings array Input options.
     *
     * @return array Sanitized result.
     */
    public function validateOptions($settings)
    {
        $return = null;
        $settings['enabled'] = $settings['enabled'] == 'on';
        $settings['test_mode'] = $settings['test_mode'] == 'on';
        $settings['title'] = trim(htmlspecialchars(strip_tags($settings['title'])));
        $settings['description'] = trim(htmlspecialchars(strip_tags($settings['description'],
            '<p><a><strong><em><b><i>')));
        $settings['adminOnly'] = $settings['adminOnly'] == 'on';
        $settings['partner'] = trim(strip_tags(esc_attr($settings['partner'])));
        $settings['vendor'] = trim(strip_tags(esc_attr($settings['vendor'])));
        $settings['user'] = trim(strip_tags(esc_attr($settings['user'])));
        $settings['password'] = trim(strip_tags(esc_attr($settings['password'])));

        if (!in_array($this->currency, $this->allowedCurrencies)) {
            $this->messages->addError(sprintf(__('The Payflow gateway accepts payments in currencies of %s.  Your current currency is %s.  Payflow won\'t work until you change the Jigoshop currency to an accepted one. Payflow is <strong>currently disabled</strong> on the Payment Gateways settings tab.', 'jigoshop_payflow_gateway'), implode(', ', $this->allowedCurrencies), $this->currency));
            $settings['enabled'] = $return;
        }

        if (!in_array($this->shopBaseCountry, $this->merchantCountries)) {
            $this->messages->addError(sprintf(__('Payflow gateway is available for merchants from %s. Your country is %s. Payflow gateway won\'t work until you change the Jigoshop base country to one accepted.', 'jigoshop_payflow_gateway'), implode(', ', $this->merchantCountries), $this->shopBaseCountry));
            $settings['enabled'] = $return;
        }

        if ($this->options->get('shopping.force_ssl') == false) {
            $this->messages->addError(sprintf(__('Payflow can not be enabled because the force SSL option on the Checkout is disabled; your Checkout may not be secure!  Please enable SSL on the Settings->General tab and ensure your server has a valid SSL certificate.', 'jigoshop_stripe')));
            $settings['enabled'] = $return;
        }


        return $settings;
    }

    /**
     * Renders method fields and data in Checkout page.
     */
    public function render()
    {
        if ($this->settings['description']) {
            echo wpautop(wptexturize($this->settings['description']));
        }
    }

    /**
     * @param Order $order Order to process payment for.
     *
     * @return string URL to redirect to.
     * @throws Exception On any payment error.
     */
    public function process($order)
    {
        return \Jigoshop\Helper\Order::getPayLink($order);
    }

    /**
     * @param Order $order Order.
     */
    public function renderPay($render, $order)
    {
        if ($order->getPaymentMethod()->getId() != self::ID) {
            return $render;
        }
        $return = null;
        $result = $this->payflowRequest($order);

        if (isset($result['RESULT'])) {
            if ($result['RESULT'] == 0) {


                $secureToken = '';
                if (isset($result['SECURETOKEN'])) {
                    $secureToken = $result['SECURETOKEN'];
                }
                $secureTokenId = '';
                if (isset($result['SECURETOKENID'])) {
                    $secureTokenId = $result['SECURETOKENID'];
                }
                if ($this->settings['test_mode'] == true) {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $mode = 'TEST';
                } else {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $mode = 'LIVE';
                }

                /** @noinspection PhpUnusedLocalVariableInspection */
                $url = 'https://payflowlink.paypal.com?SECURETOKEN=' . $secureToken . '&SECURETOKENID=' . $secureTokenId . '&MODE=' . $mode;

                $render = Render::get('payflow_payment_gateway', 'form', array(
                    'url' => $url,
                ));

                $return = $render;
            } else {
                $errorMsg = sprintf(__('Error occurred setting up the order. Error message: <strong>%s</strong>. Order was canceled', 'jigoshop_payflow_gateway'), $result['RESPMSG']);
                /**@var Order $order */
                $order->setStatus(Order\Status::CANCELLED, sprintf(__('Payment by Payflow was canceled. Message: %s. ', 'jigoshop_payflow_gateway'), $errorMsg));
                $this->orderService->save($order);
                $checkoutRedirect = add_query_arg(array('order' => $order->getId(), 'key' => $order->getKey()),
                    get_permalink($this->options->getPageId(Pages::ACCOUNT)));
                wp_safe_redirect($checkoutRedirect);
                $this->messages->addError($errorMsg);
                exit;

            }
        }

        return $return;
    }

    /**
     * @var Order $order
     * @return object
     * @throws \Exception
     */
    private function payflowRequest($order)
    {
        $params = $this->payflowParams($order);

        $paramList = array();

        foreach ($params as $index => $value) {
            $paramList[] = $index . "[" . strlen($value) . "]=" . $value;
        }


        if ($this->settings['test_mode'] == true) {
            $url = $this->testUrl;
        } else {
            $url = $this->liveUrl;
        }
        $apiStr = implode("&", $paramList);

        // Initialize our cURL handle.

        $result = wp_remote_post($url, ['method' => 'POST', 'httpversion' => '1.1.', 'sslverify' => false, 'body' => $apiStr]);

        if (is_wp_error($result)) {
            throw new \Exception("Error: " . $result->get_error_message());
        } else {
            $response = wp_remote_retrieve_body($result);


            return $this->parsePayflowString($response);
        }

    }

    /**
     * @var Order $order
     * @return object
     * @throws \Exception
     */
    private function payflowParams($order)
    {
        $returnUrl = add_query_arg('payflowListener', 'return', $this->returnUrl);
        $errorUrl = add_query_arg('order', $order->getId(), add_query_arg('payflowListener', 'error', $this->returnUrl));
        $cancelUrl = \Jigoshop\Helper\Order::getCancelLink($order);

        $billingAddress = $order->getCustomer()->getBillingAddress();
        $shippingAddress = $order->getCustomer()->getShippingAddress();

        $result = array(
            "PARTNER" => $this->partner,
            "VENDOR" => $this->vendor,
            "USER" => $this->user,
            "PWD" => $this->password,
            "TENDER" => "C",
            "TRXTYPE" => "S",
            "TEMPLATE" => "TEMPLATEA",
            "CURRENCY" => $this->currency,
            "AMT" => round($order->getTotal(), 2),
            "ORDERID" => $order->getId(),
            "CREATESECURETOKEN" => "Y",
            "SECURETOKENID" => uniqid(), //Should be unique, never used before
            "RETURNURL" => $returnUrl,
            "CANCELURL" => $cancelUrl,
            "ERRORURL" => $errorUrl,
            "BILLTOFIRSTNAME" => $billingAddress->getFirstName(),
            "BILLTOLASTNAME" => $billingAddress->getLastName(),
            "BILLTOSTREET" => $billingAddress->getAddress(),
            "BILLTOCITY" => $billingAddress->getCity(),
            "BILLTOSTATE" => $billingAddress->getState(),
            "BILLTOZIP" => $billingAddress->getPostcode(),
            "BILLTOCOUNTRY" => $billingAddress->getCountry(),
            "BUTTONSOURCE" => 'JigoLtd_SP',

        );

        if ($shippingAddress->getAddress()) {
            $result["SHIPTOFIRSTNAME"] = $shippingAddress->getFirstName();
            $result["SHIPTOLASTNAME"] = $shippingAddress->getLastName();
            $result["SHIPTOSTREET"] = $shippingAddress->getAddress();
            $result["SHIPTOCITY"] = $shippingAddress->getCity();
            $result["SHIPTOSTATE"] = $shippingAddress->getState();
            $result["SHIPTOZIP"] = $shippingAddress->getPostcode();
            $result["SHIPTOCOUNTRY"] = $shippingAddress->getCountry();
        }

        return $result;
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

    private function getXmlResponseFromErrorRequest($xmlResponse)
    {
        $urlResponse = urlencode($xmlResponse);

        $ascii = array(
            '%3C' => '<',
            '%3E' => '>',
            '%3D' => '=',
            '%2F' => '/',
            '%3A' => ':',
            '%5C' => '',
            '%22' => '"',
            '+' => ' ',
            '%28' => '(',
            '%29' => ')',
            '%2D' => '-',
        );

        if (!empty($urlResponse)) {
            foreach ($ascii as $k => $v) {
                $urlResponse = str_replace($k, $v, $urlResponse);
            }

            $xmlData = simplexml_load_string($urlResponse, 'SimpleXMLElement', LIBXML_NOCDATA);

            $xmlData = $xmlData->rule->triggeredMessage;

            $response = json_decode(json_encode((array)$xmlData), true);

            return $response[0];
        }

        return false;
    }

    public function payflowStatusResponse()
    {
        if (!empty($_GET['payflowListener']) && ($_GET['payflowListener'] == 'return' || ($_GET['payflowListener'] == 'error' && !empty($_GET['order'])))) {
            if ($_REQUEST['RESULT'] == 0 && !empty($_REQUEST['ORDERID'])) {
                /**@var Order $order */
                $orderId = $_REQUEST['ORDERID'];
                $order = $this->orderService->find($orderId);
                if ($order) {
                    $status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
                    $order->setStatus($status, __('Payflow payment completed', 'jigoshop_payflow_gateway'));
                    $this->orderService->save($order);
                    $checkoutRedirect = add_query_arg(array('order' => $order->getId(), 'key' => $order->getKey()),
                        get_permalink($this->options->getPageId(Pages::THANK_YOU)));
                    wp_safe_redirect($checkoutRedirect);
                    exit;
                }
            } else if ($_REQUEST['RESULT'] == 12) {
                /**@var Order $order */
                $errorMsg = sprintf(__('Order payment was declined. Payflow Gateway Message: %s. Order is on hold', 'jigoshop_payflow_gateway'), $_REQUEST['RESPMSG']);
                $orderId = intval(sanitize_text_field($_GET['order']));
                $order = $this->orderService->find($orderId);
                if ($order) {
                    $order->setStatus(Order\Status::ON_HOLD, __('on-hold', $errorMsg));
                    $this->orderService->save($order);
                    $checkoutRedirect = add_query_arg(array('pay' => $order->getId(), 'key' => $order->getKey()),
                        get_permalink($this->options->getPageId(Pages::CHECKOUT)));
                    wp_safe_redirect($checkoutRedirect);
                    $this->messages->addError($errorMsg);
                    exit;
                }
            } else {
                $orderId = intval(sanitize_text_field($_GET['order']));
                // Xml response
                $xmlResponse = $_REQUEST['FPS_PREXMLDATA'];

                /**@var Order $order */
                $order = $this->orderService->find($orderId);
                if ($order) {
                    $responseMessage = $this->getXmlResponseFromErrorRequest($xmlResponse);

                    $errorMsg = sprintf(__('Payflow Gateway Error: (%s). Payment was canceled', 'jigoshop_payflow_gateway'), $responseMessage);
                    $order->setStatus(Order\Status::CANCELLED, __($responseMessage));

                    $checkoutRedirect = add_query_arg(array('pay' => $order->getId(), 'key' => $order->getKey()),
                        get_permalink($this->options->getPageId(Pages::CHECKOUT)));

                    wp_safe_redirect($checkoutRedirect);
                    $this->messages->addError($errorMsg);
                    exit;
                }
            }
        } else {
            if (isset($_REQUEST['RESPMSG']) && $_REQUEST['RESPMSG'] == 'Approved') {
                $orderId = intval(sanitize_text_field($_REQUEST['ORDERID']));
                if (!empty($_REQUEST['PNREF']) && !empty($_REQUEST['SECURETOKENID'])) {
                    /**@var Order $order */
                    $order = $this->orderService->find($orderId);
                    if ($order) {
                        $status = \Jigoshop\Helper\Order::getStatusAfterCompletePayment($order);
                        $order->setStatus($status, __('Payflow payment completed', 'jigoshop_payflow_gateway'));
                        $this->orderService->save($order);
                        $checkoutRedirect = add_query_arg(array('order' => $order->getId(), 'key' => $order->getKey()),
                            get_permalink($this->options->getPageId(Pages::THANK_YOU)));
                        wp_safe_redirect($checkoutRedirect);
                        exit;
                    }
                }
            } else {
                $orderId = empty($_REQUEST['ORDERID']) ? intval(sanitize_text_field($_GET['order'])) : intval(sanitize_text_field($_REQUEST['ORDERID']));
                /**@var Order $order */
                $order = $this->orderService->find($orderId);
                if ($order) {
                    $errorMsg = sprintf(__('Payflow Gateway Error: (%s). Payment was canceled', 'jigoshop_payflow_gateway'), $_REQUEST['RESPMSG']);
                    $checkoutRedirect = add_query_arg(array('pay' => $order->getId(), 'key' => $order->getKey()),
                        get_permalink($this->options->getPageId(Pages::CHECKOUT)));
                    wp_safe_redirect($checkoutRedirect);
                    $this->messages->addError($errorMsg);
                    exit;
                }
            }
        }
    }

    /**
     * Whenever method was enabled by the user.
     *
     * @return boolean Method enable state.
     */
    public function isActive()
    {
        if (isset($this->settings['enabled'])) {
            return $this->settings['enabled'];
        }
    }

    /**
     * Set method enable state.
     *
     * @param boolean $state Method enable state.
     *
     * @return array Method current settings (after enable state change).
     */
    public function setActive($state)
    {
        $this->settings['enabled'] = $state;

        return $this->settings;
    }

    /**
     * Whenever method was configured by the user (all required data was filled for current scenario).
     *
     * @return boolean Method config state.
     */
    public function isConfigured()
    {

        if (isset($this->settings['partner']) && $this->settings['partner']
            && isset($this->settings['vendor']) && $this->settings['vendor']
            && isset($this->settings['user']) && $this->settings['user']
            && isset($this->settings['password']) && $this->settings['password']
        ) {
            return true;
        }

        return false;
    }

    /**
     * Whenever method has some sort of test mode.
     *
     * @return boolean Method test mode presence.
     */
    public function hasTestMode()
    {
        return true;
    }

    /**
     * Whenever method test mode was enabled by the user.
     *
     * @return boolean Method test mode state.
     */
    public function isTestModeEnabled()
    {
        if (isset($this->settings['test_mode'])) {
            return $this->settings['test_mode'];
        }
    }

    /**
     * Set Method test mode state.
     *
     * @param boolean $state Method test mode state.
     *
     * @return array Method current settings (after test mode state change).
     */
    public function setTestMode($state)
    {
        $this->settings['test_mode'] = $state;

        return $this->settings;
    }

    /**
     * Whenever method requires SSL to be enabled to function properly.
     *
     * @return boolean Method SSL requirment.
     */
    public function isSSLRequired()
    {
        if (true == $this->settings['test_mode']) {
            return false;
        }

        return true;
    }

    /**
     * Whenever method is set to enabled for admin only.
     *
     * @return boolean Method admin only state.
     */
    public function isAdminOnly()
    {
        if (true == $this->settings['adminOnly']) {
            return true;
        }

        return false;
    }

    /**
     * Sets admin only state for the method and returns complete method options.
     *
     * @param boolean $state Method admin only state.
     *
     * @return array Complete method options after change was applied.
     */
    public function setAdminOnly($state)
    {
        $this->settings['adminOnly'] = $state;

        return $this->settings;
    }
}