<?php
/*
Plugin Name: NovattiPaymentsGateway
Description: NovattiPayments WooCommerce payment gateway
Version: 2.0
Author: Novatti Payments
Author URI:
Copyright: © 2018 Novatti Payments
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// based on http://docs.woothemes.com/document/woocommerce-payment-gateway-plugin-base/
// docs http://docs.woothemes.com/document/payment-gateway-api/

require_once dirname(__FILE__) . '/novattipayments_api.php';
require_once dirname(__FILE__) . '/novattipayments_logger_wc.php';

add_action('plugins_loaded', 'wc_novattipaymentslv_init');
function wc_novattipaymentslv_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    /**
     * Localisation
     */
    load_plugin_textdomain('woocommerce-novattipayments', false, dirname(plugin_basename(__FILE__)) . '/languages');

    /**
     * Gateway class
     */
    class WC_Novattipayments_Gateway extends WC_Payment_Gateway
    {

        /** Logging is disabled by default */
        public static bool $log_enabled = false;
        /** Logger instance */
        public static bool $log = false;
        private bool $use_iframe;

        public function __construct()
        {
            $this->id = 'novattipayments';
            $this->method_title = "NovattiPaymentsGateway";
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = "Visa / MasterCard";
            $this->description = "Pay using the bank card";
            $this->debug = 'yes' === $this->get_option('debug', 'no');
            $this->use_iframe = 'yes' === $this->get_option('iniframe', 'no');
            self::$log_enabled = $this->debug;

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')
            );
            add_action('woocommerce_receipt_' . $this->id, array($this, 'novattipayments_receipt_page'));
            str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Novattipayments_Gateway', home_url('/')));
            add_action('woocommerce_api_wc_gateway_novattipayments', array($this, 'handle_callback'));
        }

        public function init_form_fields()
        {
            // transaction options
            $tx_options = array(
                'payment' => __('Payment', 'woocommerce-novattipayments'),
                'authorization' => __('Authorization', 'woocommerce-novattipayments')
            );

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable API', 'woocommerce'),
                    'label' => __('Enable API', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'public-key' => array(
                    'title' => __('Public key', 'woocommerce-novattipayments'),
                    'type' => 'text',
                    'description' => __('Please enter your public key.', 'woocommerce-novattipayments'),
                    'default' => ''
                ),
                'private-key' => array(
                    'title' => __('Secret key', 'woocommerce-novattipayments'),
                    'type' => 'text',
                    'description' => __('Please enter your secret key.', 'woocommerce-novattipayments'),
                    'default' => ''
                ),
                'debug' => array(
                    'title' => __('Debug Log', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woocommerce'),
                    'default' => 'yes',
                    'description' => sprintf(
                        __('Log NovattipaymentsGateway events, inside <code>%s</code>', 'woocommerce'),
                        wc_get_log_file_path('NovattipaymentsGateway')
                    )
                ),
                'iniframe' => array(
                    'title' => __('Iframe payment form.', 'woocommerce-novattipayments'),
                    'type' => 'checkbox',
                    'label' => __('Build-in payment form on the website.', 'woocommerce'),
                    'description' => __('Payment form will be located on your website.'),
                    'default' => 'no',
                ),
            );
        }

        function novattipayments_receipt_page($order)
        {
            if ($this->use_iframe) {
                $order = new WC_Order($order);
                $form_html = '<div id="complex_wrapper" style="text-align: -webkit-center;">';

                $form_html .= '<iframe id="payment_iframe"
                               src="' . WC()->session->get('iframe_checkout') . '"
                               name="paymentform"
                               height="400" frameborder="0"
                               style="
                               width:100%">
                       </iframe>';
                echo $form_html;
            }
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $novattipayments = new NovattipaymentsAPI(
                $this->settings['private-key'],
                $this->settings['public-key'],
                new NovattipaymentsLoggerWC(self::$log_enabled)
            );
            $params = array(
                'number' => (string)$order->get_order_number(),
                'referrer' => 'woocommerce v4.x module ' . NOVATTI_PAYMENTS_MODULE_VERSION,
                'language' => $this->_language('en'),
                'success_redirect' => home_url() . '/?wc-api=wc_gateway_novattipayments&action=paid&id=' . $order_id,
                'failure_redirect' => home_url() . '/?wc-api=wc_gateway_novattipayments&action=sent&id=' . $order_id,
                'currency' => $order->get_currency()
            );
            $this->addUserData($novattipayments, $order, $params);
            $this->addProducts($order, $params);

            $novattipayments->log_info("Params for payment: " . print_r($params['products'], true));
            $payment = $novattipayments->create_payment($params);
            WC()->session->set('novattipayments_payment_id', $payment['id']);
            $novattipayments->log_info('Got checkout url, redirecting');
            $payment['result'] = 'success';
            if ($this->use_iframe) {
                $payment['redirect'] = $order->get_checkout_payment_url(true);
                WC()->session->set('iframe_checkout', $payment['iframe_checkout']);
                $set = 'Set-Cookie: payment_id=' . $payment['id'] . '; SameSite=None; Secure';
                header($set, false);
            } else {
                $payment['redirect'] = $payment['full_page_checkout'];
            }
            return $payment;
        }

        public function handle_callback()
        {
            // Docs http://docs.woothemes.com/document/payment-gateway-api/
            // Handle the thing here! http://127.0.0.1/wordpress/?wc-api=wc_gateway_novattipayments&id=&action={paid,sent}
            // The new URL scheme (http://127.0.0.1/wordpress/wc-api/wc_gateway_novattipayments) is broken for some reason.
            // Old one still works.
            global $woocommerce;
            $order = new WC_Order($_GET["id"]);
            $novattipayments = new NovattipaymentsAPI(
                $this->settings['private-key'],
                $this->settings['public-key'],
                new NovattipaymentsLoggerWC(self::$log_enabled)
            );
            $novattipayments->log_info('Success callback');
            $payment_id = WC()->session->get('novattipayments_payment_id');
            if ($this->use_iframe) {
                if ($_COOKIE['payment_id']) {
                    $payment_id = $_COOKIE['payment_id'];
                    setcookie("payment_id","",time()-10000, '/');
                } elseif ($_GET['payment_id']) {
                    $payment_id = $_GET['payment_id'];
                } else {
                    $order->update_status('wc-failed', __('ERROR: Payment id not found'));
                    die('<script>window.top.location.href = "' . $this->get_return_url($order) . '";</script>');
                }
            }
            if ($novattipayments->was_payment_successful($order->get_order_number(), $payment_id)) {
                $order->payment_complete();
                $order->reduce_order_stock();
                $order->add_order_note(__('Payment successful.', 'woocommerce'));
            } else {
                $order->update_status('wc-failed', __('ERROR: Payment was received, but order verification failed.'));
            }

            $novattipayments->log_info('Done processing success, redirecting');
            if ($this->use_iframe) {
                die('<script>window.top.location.href = "' . $this->get_return_url($order) . '";</script>');
            } else {
                header("Location: " . $this->get_return_url($order));
            }
        }

        protected function addUserData($novattipayments, $order, &$params)
        {
            $user_data = array(
                'email' => $order->billing_email,
                'phone' => $order->billing_phone,
                'first_name' => $order->billing_first_name,
                'last_name' => $order->billing_last_name,
                'send_to_email' => true
            );
            $findUser = $novattipayments->getUser($user_data['email'], $user_data['phone']);
            if (!$findUser) {
                if ($novattipayments->createUser($user_data)) {
                    $findUser = $novattipayments->getUser($user_data['email'], $user_data['phone']);
                }
            }
            $user_data['original_client'] = $findUser['id'];
            $params['client'] = $user_data;
        }

        protected function addProducts($order, &$params)
        {
            $params['products'][] = [
                'price' => round($order->get_total(), 2),
                'title' => __('Invoice for payment #') . (string)$order->get_order_number(),
                'quantity' => 1
            ];
        }

        public static function _language($lang_id)
        {
            $languages = array('en', 'ru', 'lv');

            if (in_array(strtolower($lang_id), $languages)) {
                return $lang_id;
            } else {
                return 'en';
            }
        }
    }

    /**
     * Add the Gateway to WooCommerce
     * @param $methods
     * @return mixed
     */
    function woocommerce_add_novattipayments_gateway($methods)
    {
        $methods[] = 'WC_Novattipayments_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_novattipayments_gateway');
}
