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

// require_once 'novatiipayments-api.php';
// require_once 'logger.php';

add_action("plugins_loaded", "init_nimbus_gateway_class");

function debugConsole($msg)
{
  $msg = str_replace('"', "''", $msg); # weak attempt to make sure there's not JS breakage
  echo "<script>console.debug( \"PHP DEBUG: $msg\" );</script>";
}

function console_log($output, $with_script_tags = true)
{
  $js_code = "console.log(" . json_encode($output, JSON_HEX_TAG) . ");";
  if ($with_script_tags) {
    $js_code = "<script>" . $js_code . "</script>";
  }
  echo $js_code;
}

function init_nimbus_gateway_class()
{
  // https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html
  class WC_Nimbus_Gateway extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $this->id = "nimbusgatway";
      $this->method_title = "Nimbus Gateway";
      $this->icon = null;
      $this->has_fields = true;
      $this->init_form_fields();
      $this->init_settings();
      $this->title = "Card Payment";
      $this->description = "Pay using credit/debit card";
      $this->public_key = $this->get_option("public_key");
      $this->private_key = $this->get_option("private_key");

      add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
        $this,
        "process_admin_options",
      ]);

      add_action("wp_enqueue_scripts", [$this, "payment_scripts"]);
    }

    public function init_form_fields()
    {
      $this->form_fields = [
        "enabled" => [
          "title" => "Enable/Disable",
          "label" => "Enable Nimbus Gateway",
          "type" => "Checkbox",
          "description" => "",
          "default" => "no",
        ],
        "public_key" => [
          "title" => "Public Key",
          "type" => "text",
          "description" => "Please enter your public key WORKS", //delete this
          "default" => "",
        ],
        "private_key" => [
          "title" => "Private key",
          "type" => "password",
          "description" => "Please enter your private key",
          "default" => "",
        ],
      ];
    }

    public function payment_fields()
    {
      do_action("woocommerce_credit_card_form_start", $this->id);

      if ($this->description) {
        echo wpautop(wp_kses_post($this->description));
      }
      ?>

      <fieldset id='wc-<?php echo esc_attr(
        $this->id
      ); ?>cc-form' class="wc-credit-card-form wc-payment-form" style="background:transparent;">
        <div class="form-row form-row-wide">
          <label for="nimbus_name">Cardholder Name <span class="required">*</span></label>
          <input type="text" id="nimbus_name" name="cardholder_name" autocomplete="off" maxlength="40">
        </div>
        <div class="form-row form-row-wide">
          <label for="nimbus_ccNum">Card Number <span class="required">*</span></label>
          <input type="text" id="nimbus_ccNum" name="number" autocomplete="off" maxlength="19">
        </div>
        <div class="form-row form-row-first">
          <label for="nimbus_exp_month">Expiry Month <span class="required">*</span></label>
          <select name="exp_month" id="nimbus_exp_month">
            <?php for ($i = 1; $i < 13; $i++) {
              echo '<option value="' . $i . '">' . $i . "</option>";
            } ?>
          </select>
        </div>
        <div class="form-row form-row-last">
          <label for="nimbus_exp_year">Expiry Year <span class="required">*</span></label>
          <select name="exp_year" id="nimbus_exp_year">
            <?php for ($i = 22; $i < 43; $i++) {
              echo '<option value="' . $i . '">' . $i . "</option>";
            } ?>
          </select>
        </div>
        <div class="form-row form-row-first">
          <label for="nimbus_cvc">CVV <span class="required">*</span></label>
          <input type="text" id="nimbus_cvc" name="csc" maxlength="4">
        </div>
        <div class="clear"></div>
        <div class="clear"></div>
      </fieldset>

      <?php do_action("woocommerce_credit_card_form_end", $this->id);
    }

    public function payment_scripts()
    {
      // if( ! is_cart() && ! is_checkout() && ! isset( $GET['pay_for_order'] ) ) {
      //     return;
      // }

      // if( $this->enabled === 'no' ) {
      //     return;
      // }

      wp_enqueue_script(
        "nimbus_js",
        "https://gate.novattipayments.com/api/v0.6/orders/"
      );

      wp_register_script(
        "woocommerce_nimbus",
        plugin_dir_url(__FILE__) . "nimbus-gateway.js",
        ["jquery", "nimbus_js"]
      );

      wp_localize_script("woocommerce_nimbus", "nimbus_params", [
        "secretKey" => $this->private_key,
      ]);

      wp_enqueue_script("woocommerce_nimbus");
    }

    // public function validate_fields()
    // {
    //   // https://github.com/adrianjonmiller/vadsupplies/blob/master/plugins/woocommerce-gateway-elavon/includes/class-wc-gateway-elavon-vm.php
    // }

    // https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
    //     Customer fills his card data and clicks “Place Order” button.
    // We delay the form submission using checkout_place_order event in WooCommerce and send AJAX request with card data directly to our payment processor,
    // If customer details are OK, the processor returns a token and we add it to our form below,
    // Now we can submit the form (in JS of course),
    // We use the token in PHP to capture a payment via payment processor’s API.

    public function process_payment($order_id)
    {
      global $woocommerce;

      $order = wc_get_order($order_id);
      //   debugConsole(json_encode($_POST));
    }
  }

  //Tell WooCommerce we exist
  function add_gateway($methods)
  {
    $methods[] = "WC_Nimbus_Gateway";
    return $methods;
  }

  add_filter("woocommerce_payment_gateways", "add_gateway");
}
