<?php

/**
 * Selcom Payment Gateway
 *
 * Provides an Selcom Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Gateway_Selcom
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Osen Concepts
 */
class WC_Selcom_Gateway extends WC_Payment_Gateway
{
 /**
  * Constructor for the gateway.
  *
  * @return void
  */
 public function __construct()
 {
  $this->id                 = 'selcom';
  $this->icon               = apply_filters('woocommerce_selcom_gateway_icon', plugins_url('selcom.png', __FILE__));
  $this->has_fields         = true;
  $this->method_title       = __('Selcom Gateway', 'rcpro');
  $this->method_description = __('Allows payments through Selcom Gateway.', 'rcpro');
  $this->supports           = array(
   'products',
   'refunds',
  );

  // Load the settings.
  $this->init_form_fields();
  $this->init_settings();

  // Define user set variables.
  $this->title = $this->get_option('title');

  // Actions.
  add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
  add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
 }

 /**
  * Initialise Gateway Settings Form Fields.
  *
  * @return void
  */
 public function init_form_fields()
 {
  $this->form_fields = array(
   'enabled'      => array(
    'title'   => __('Enable/Disable', 'rcpro'),
    'type'    => 'checkbox',
    'label'   => __('Enable Selcom Gateway', 'rcpro'),
    'default' => 'yes',
   ),
   'title'        => array(
    'title'       => __('Title', 'rcpro'),
    'type'        => 'text',
    'description' => __('This controls the title which the user sees during checkout.', 'rcpro'),
    'default'     => __('Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'description'  => array(
    'title'       => __('Description', 'rcpro'),
    'type'        => 'textarea',
    'description' => __('This controls the description which the user sees during checkout.', 'rcpro'),
    'default'     => __('Pay via Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'instructions' => array(
    'title'       => __('Instructions', 'rcpro'),
    'type'        => 'textarea',
    'description' => __('Instructions that will be added to the thank you page.', 'rcpro'),
    'default'     => __('Pay via Selcom Gateway', 'rcpro'),
    'desc_tip'    => true,
   ),
   'merchant'     => array(
    'title'       => __('Merchant ID', 'rcpro'),
    'type'        => 'text',
    'description' => __('Merchant ID provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_key'      => array(
    'title'       => __('API Key', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API Key provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_secret'   => array(
    'title'       => __('API Secret', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API Secret provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
   'api_url'      => array(
    'title'       => __('API URL', 'rcpro'),
    'type'        => 'text',
    'description' => __('This is the API URL provided by Selcom.', 'rcpro'),
    'default'     => '',
    'desc_tip'    => true,
   ),
  );
 }

 public function payment_fields()
 {
  $description = $this->get_description();
  if ($description) {
   echo wpautop(wptexturize($description));
  }

  woocommerce_form_field(
   'phone',
   array(
    'type'        => 'text',
    'class'       => array('form-row-wide'),
    'label'       => __('Phone', 'rcpro'),
    'placeholder' => __('Phone', 'rcpro'),
    'required'    => true,
   )
  );
 }

 public function sendJSONPost($json, $authorization, $digest, $signed_fields, $timestamp)
 {
  $url     = "{$this->settings['api_ur;']}/utilitypayment/process";
  $headers = array(
   "Content-type"   => 'application/json;charset="utf-8"',
   "Accept"         => "application/json",
   "Cache-Control:" => " no-cache",
   "Authorization"  => "SELCOM $authorization",
   "Digest-Method"  => "HS256",
   "Digest"         => $digest,
   "Timestamp"      => $timestamp,
   "Signed-Fields"  => $signed_fields,
  );

  $result = wp_remote_post($url, array(
   'method'      => 'POST',
   'timeout'     => 45,
   'redirection' => 5,
   'httpversion' => '1.0',
   'blocking'    => true,
   'headers'     => $headers,
   'body'        => $json,
   'cookies'     => array(),
  ));

  return is_wp_error($result)
  ? array('result' => 'FAIL', 'message' => $result->get_error_message())
  : json_decode($result['body'], true);
 }

 public function computeSignature($parameters, $signed_fields, $request_timestamp, $api_secret)
 {
  $fields_order = explode(',', $signed_fields);
  $sign_data    = "timestamp=$request_timestamp";
  foreach ($fields_order as $key) {
   $sign_data .= "&$key=" . $parameters[$key];
  }

  //RS256 Signature Method
  #$private_key_pem = openssl_get_privatekey(file_get_contents("path_to_private_key_file"));
  #openssl_sign($sign_data, $signature, $private_key_pem, OPENSSL_ALGO_SHA256);
  #return base64_encode($signature);

  //HS256 Signature Method
  return base64_encode(hash_hmac('sha256', $sign_data, $api_secret, true));
 }

 /**
  * Process the payment and return the result.
  *
  * @param int $order_id
  * @return array
  */
 public function process_payment($order_id)
 {
  $order         = wc_get_order($order_id);
  $authorization = base64_encode($this->settings['api_key']);
  $timestamp     = date('c');
  $req           = array(
   "utilityref" => $order_id,
   "transid"    => $order->get_order_key(),
   "amount"     => round($order->get_total()),
  );

  $signed_fields = implode(',', array_keys($req));
  $digest        = $this->computeSignature($req, $signed_fields, $timestamp, $this->settings['api_secret']);
  $response      = $this->sendJSONPost(wp_json_encode($req), $authorization, $digest, $signed_fields, $timestamp);

  // Mark as on-hold (we're awaiting the payment)
  $order->update_status('on-hold', __('Awaiting payment', 'rcpro'));

  // Reduce stock levels
  $order->reduce_order_stock();

  // Remove cart
  WC()->cart->empty_cart();

  // Return thankyou redirect
  return array(
   'result'   => 'success',
   'redirect' => $this->get_return_url($order),
  );
 }

 /**
  * Output for the order received page.
  */
 public function thankyou_page()
 {
  if ($this->instructions) {
   echo wpautop(wptexturize($this->instructions));
  }
 }

 /**
  * Add content to the WC emails.
  *
  * @access public
  * @param WC_Order $order
  * @param bool $sent_to_admin
  * @param bool $plain_text
  */
 public function email_instructions($order, $sent_to_admin, $plain_text = false)
 {

  if ($this->instructions && !$sent_to_admin && 'Selcom' === $order->payment_method && $order->has_status('on-hold')) {
   echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
  }
 }
}