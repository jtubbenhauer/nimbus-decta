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

add_action("plugins_loaded", "init_nimbus_gateway_class");

function init_nimbus_gateway_class()
{
  class WC_Nimbus_Gateway extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $this->id = "nimbusgateway";
      $this->method_title = "Nimbus Gateway";
      $this->icon = "https://www.nimbusvapour.com.au/assets/visamaster.png";
      $this->has_fields = true;
      $this->init_form_fields();
      $this->init_settings();
      $this->title = "Card Payment";
      $this->description = "Pay using VISA or Mastercard";
      $this->public_key = $this->get_option("public_key");
      $this->private_key = $this->get_option("private_key");

      add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
        $this,
        "process_admin_options",
      ]);
    }

    public function log($message)
    {
      $logger = new WC_Logger();
      $logger->add("Nimbus Gateway: ", $message);
    }

    public function checkCurlError($ch, $message)
    {
      if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
      }
      if (isset($error_msg)) {
        $this->log($message);
      }
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
          "description" => "Please enter your public key",
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

      $year = substr(date("Y"), 2, 2);

      if ($this->description) {
        echo wpautop(wp_kses_post($this->description));
      }
      ?>
      <div class="form-row form-row-wide">

      <fieldset id='wc-<?php echo esc_attr(
        $this->id
      ); ?>cc-form' class="wc-credit-card-form wc-payment-form" style="background:transparent;">
          <label for="nimbus_name">Cardholder Name <span class="required">*</span></label>
          <input type="text" id="nimbus_name" name="cardholder_name" autocomplete="off" maxlength="40" required>
        </div>
        <div class="form-row form-row-wide">
          <label for="nimbus_ccNum">Card Number <span class="required">*</span></label>
          <input type="text" id="nimbus_ccNum" name="number" autocomplete="off" maxlength="19">
        </div>
        <div class="form-row form-row-first">
          <label for="nimbus_exp_month">Expiry Month <span class="required">*</span></label>
          <select name="exp_month" id="nimbus_exp_month">
            <option value='' selected>--</option>
            <?php for ($i = 1; $i < 13; $i++) {
              if ($i > 0 && $i < 10) {
                echo '<option value="0' . $i . '">0' . $i . "</option>";
              } else {
                echo '<option value="' . $i . '">' . $i . "</option>";
              }
            } ?>
          </select>
        </div>
        <div class="form-row form-row-last">
          <label for="nimbus_exp_year">Expiry Year <span class="required">*</span></label>
          <select name="exp_year" id="nimbus_exp_year">
            <option value=''>--</option>
            <?php for ($i = $year; $i < $year + 20; $i++) {
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

    public function validate_fields()
    {
      global $woocommerce;

      $card_num = strval(str_replace(" ", "", $_POST["number"]));

      if (empty($card_num)) {
        $this->log("Validation error: Empty card number");
        wc_add_notice("Card number is required.", "error");
      }

      if (strlen($card_num) != 13 && strlen($card_num) != 16) {
        $this->log("Validation error: Invalid card number length");
        wc_add_notice(
          "Incorrect card number length. Please try again.",
          "error"
        );
      }

      if (
        substr($card_num, 0, 1) != "2" &&
        substr($card_num, 0, 1) != "4" &&
        substr($card_num, 0, 1) != "5"
      ) {
        $this->log("Validation error: Incorrect card type");
        wc_add_notice(
          "Invalid card type. Please enter a valid VISA or Mastercard number.",
          "error"
        );
      }

      if (empty($_POST["cardholder_name"])) {
        $this->log("Validation error: Empty cardholder name");
        wc_add_notice("Cardholder name is required.", "error");
      }

      if (empty($_POST["exp_month"])) {
        $this->log("Validation error: Empty expiry month");
        wc_add_notice("Expiry month is required.", "error");
      }

      if (empty($_POST["exp_year"])) {
        $this->log("Validation error: Empty expiry year");
        wc_add_notice("Expiry year is required.", "error");
      }

      if (empty($_POST["csc"])) {
        $this->log("Validation error: Empty CVV");
        wc_add_notice("CVV number is required.", "error");
      }
    }

    public function process_payment($order_id)
    {
      global $woocommerce;

      $order = new WC_Order($order_id);

      // ------------------------- Create order
      $createOrderData = [
        "client" => [
          "email" => $order->get_billing_email(),
        ],
        "products" => [
          [
            "price" => $order->calculate_totals(),
            "title" => "Order: " . $order_id,
          ],
        ],
      ];
      $this->log("-------------------------------");
      $this->log("Creating order: " . $order_id . print_r($createOrderData)); //Why doesnt this work?

      $createOrderAuth = "Bearer " . $this->private_key;

      $curl = curl_init();

      curl_setopt_array($curl, [
        CURLOPT_URL => "https://gate.novattipayments.com/api/v0.6/orders/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($createOrderData),
        CURLOPT_HTTPHEADER => [
          "Authorization: " . $createOrderAuth,
          "Content-Type: application/json",
        ],
      ]);

      $response = curl_exec($curl);
      $this->log("Creating order");
      $this->checkCurlError($curl, "Error creating order");

      curl_close($curl);

      $createOrderRes = json_decode($response, true);
      $direct_post = $createOrderRes["direct_post"];
      $novatti_id = $createOrderRes["id"];

      // ------------------ Create form and pay
      $curl = curl_init();

      curl_setopt_array($curl, [
        CURLOPT_URL => $direct_post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => [
          "cardholder_name" => strtoupper($_POST["cardholder_name"]),
          "number" => str_replace(" ", "", $_POST["number"]),
          "exp_month" => $_POST["exp_month"],
          "exp_year" => $_POST["exp_year"],
          "csc" => str_replace(" ", "", $_POST["csc"]),
        ],
      ]);

      $response = curl_exec($curl);
      $this->log("Creating and sending form");
      $this->checkCurlError($curl, "Error sending form");

      curl_close($curl);

      // ---------------  Check if paid
      $curl = curl_init();

      curl_setopt_array($curl, [
        CURLOPT_URL =>
          "https://gate.novattipayments.com/api/v0.6/orders/" . $novatti_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => ["Authorization: Bearer " . $this->private_key],
      ]);

      $response = curl_exec($curl);
      $this->log("Checking payment status");
      $this->checkCurlError($curl, "Error checking payment status");

      curl_close($curl);

      $statusRes = json_decode($response, true);

      if ($statusRes["status"] === "paid") {
        $order->payment_complete();
        $this->log("Payment successful");
        return [
          "result" => "success",
          "redirect" => $this->get_return_url($order),
        ];
      } else {
        $error = $statusRes["transaction_details"]["errors"][0]["description"];
        $this->log("Payment failed: " . $error);

        if ($error === "Decline, refer to card issuer") {
          $error = "Incorrect expiry or CVV number. Please try again.";
        } elseif ($error === "Decline reason message: format error") {
          $error = "Incorrect card number. Please try again.";
        } elseif ($error === "Decline, not sufficient funds") {
          $error = "Card declined, insufficient funds.";
        }

        wc_add_notice(__("Payment error: ", "woothemes") . $error, "error");
        return;
      }
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
