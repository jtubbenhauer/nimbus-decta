<?php 
/*
PLugin Name: NimbusDectaGateway
Description: NimbusDecta WooCommerce Payment Gateway
Version: 0.1
Author: Jack Tubbenhauer
AUthor URI:
Copyright: 2022 Jack Tubbenhauer
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

//https://woocommerce.com/document/payment-gateway-api/
//https://api-docs.novattipayments.com/#section/Checkouts - Direct form API stuff

require_once 'novatiipayments-api.php';
require_once 'logger.php';

add_action('plugins_loaded', 'init_nimbus_gateway_class');

//Tell WooCommerce we exist
function add_gateway( $methods ) {
    $methods[] = 'WC_Nimbus_Gateway';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_gateway');

function init_nimbus_gateway_class() {

    // https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html
    class WC_Nimbus_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'nimbusgatway';
        $this->method_title = 'Nimbus Gateway';
        $this->icon = '';
        $this->has_fields = true;
        $this->init_form_fields();
        $this->init_settings();
        $this->title = 'Card Payment';
        $this->description = 'Pay using credit/debit card';

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function payment_fields() {
        if ( $this->description ) {
          echo wpautop(wp_kses_post($this->description));
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . 'cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
        <input id="nimbus_ccNum" type="text" autocomplete="off">
        </div>
        <div class="form-row form-row-first">
          <label>Expiry Date <span class="required">*</span></label>
          <input id="nimbus_expdate" type="text" autocomplete="off" placeholder="MM/YY">
        </div>
        <div class="form-row form-row-last">
          <label>Card Code (CVV) <span class="required">*</span></label>
          <input id="nimbus_cvv" type="password" autocomplete="off" placeholder="CVC">
        </div>
        <div class="clear"></div>';

        echo '<div class="clear"></div></fieldset>';
    }


    public function init_form_fields() {
        $this->form_fields = array(
          'enabled' => array(
            'title' => 'Enable/Disable',
            'label' => 'Enable Nimbus Gateway',
            'type' => 'Checkbox',
            'description' => '',
            'default' => 'no'
          ),
          'public-key' => array(
            'title' => 'Public Key',
            'type' => 'text',
            'description' => 'Please enter your public key',
            'default' => ''
          ),
          'private-key' => array(
            'title' => 'Private key',
            'type' => 'text',
            'description' => 'Please enter your private key',
            'default' => ''
          )
        );
    }

    public function process_payment($order_id) {

    }

    }




}

