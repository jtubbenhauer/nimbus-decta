<?php 
/*
PLugin Name: NimbusDectaGateway
Description: NimbusDecta WooCommerce Payment Gateway
Version: 1.0
Author: Jack Tubbenhauer
AUthor URI:
Copyright: 2022 Jack Tubbenhauer
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

//https://woocommerce.com/document/payment-gateway-api/

require_once 'novatiipayments-api.php';
require_once 'logger.php';

add_action('plugins_loaded', 'init_nimbus_gateway');

function init_nimbus_gateway() {

    // https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html
    class WC_Nimbus_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'nimbusgatway';
        $this->method_title = 'Card Payment';
        
        $this->init_form_fields();
        $this->init_settings();
    }

    public function init_form_fields() {

    }

    public function init_settings() {
        
    }

    }

    //Tell WooCommerce we exist
    function add_gateway( $methods ) {
        $methods[] = 'WC_Nimbus_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_gateway');


}

