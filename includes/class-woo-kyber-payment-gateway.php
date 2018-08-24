<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 
 * @link developer.kyber.network
 * @since 0.0.1
 * 
 * @package Woo_Kyber_Payment
 * @subpackage Woo_Kyber_Payment/includes
 */

class WC_Kyber_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'kyber';
        $this->method_title = __( 'Kyber', 'woocommerce-gateway-kyber' );
        $this->method_description = sprintf( __('Kyber allow user to pay by using tokens') );
        $this->order_button_text = __( 'Proceed to Kyber', 'woocommerce-gateway-kyber' );
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
            'tokenization',
            'add_payment_method'
        );

        $this->init_form_fields();

        $this->init_settings();

        $this->title = $this->get_option( 'title' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->description = $this->get_option( 'description' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_kyber_callback', array( $this, 'handle_kyber_callback' ) );
    }

    public function init_form_fields() {
        $this->form_fields = require( plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/kyber-settings.php' );
    }

    public function get_icon() {
		$icons_str = '<img src="' . WC_KYBER_PLUGIN_URL . '/admin/images/kyber.svg" class="stripe-visa-icon stripe-icon" alt="Kyber" />';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
    }

    public function process_payment( $order_id ) {

        global $woocommerce;
        $order = new WC_Order( $order_id );

        // Mark as on-hold (we're awaiting cheque)
        // $order->update_status('on-hold', __("Awaiting cheque payment", "woocommerce-gateway-kyber"));

        // Reduce stock levels
        // $order->reduce_order_stock();

        // Remove cart
        // $woocommerce->cart->empty_cart();
        syslog(LOG_INFO, 'It works!');

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_checkout_url( $order )
        );
    }

    public function get_checkout_url( $order ) {
        $endpoint = "https://widget.knstats.com?mode=tab&theme=light&paramForwarding=true&";
        $callback_url = urlencode($this->get_option( 'site_url_for_dev' ) . '/wc-api/kyber_callback');

        // TODO: check if receive address is valid
        $receiveAddr = $this->get_option( 'receive_addr' );

        // TODO: check if receive token is supported
        $receiveToken = $this->get_option( 'receive_token_symbol' );
       
        // TODO: check if network is valid
        $network = $this->get_option( 'network' );

        /// TODO: turn receive amount from USD to token
        // $receiveAmount = $order->get_total();
        $receiveAmount = '10'; // 10 KNC for dev

        $endpoint .= 'receiveAddr=' . $receiveAddr . '&receiveToken=' . $receiveToken . '&callback=' . $callback_url . '&receiveAmount=' . $receiveAmount;

        // add custom params

        $oder_id = $order->get_id();

        $endpoint .= '&order_id=' . strval($order_id);

        return $endpoint;
    }

    public function handle_kyber_callback() {
        if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] )) {
			return;
        }

        //TODO: validate request body 

        $request_body    = file_get_contents( 'php://input' );

        error_log( $request_body );

        $order_id = $request_body['order_id'];

        $order = wc_get_order( $order_id );


        // Mark as on-hold (we're awaiting cheque)
        $order->update_status('on-hold', __("Awaiting cheque payment", "woocommerce-gateway-kyber"));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        $woocommerce->cart->empty_cart();
    }

}