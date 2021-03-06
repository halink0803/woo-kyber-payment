<?php

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
use Web3\Web3;    
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

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
        $this->order_button_text = __( 'Place order', 'woocommerce-gateway-kyber' );
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
        add_action( 'woocommerce_order_details_after_order_table_items', array( $this, 'add_tx_hash_to_order' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'embed_kyber_widget_button' ) );
    }

    /**
     * Init admin settings fields
     * 
     * @since 0.0.1
     */
    public function init_form_fields() {
        $this->form_fields = require( plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/kyber-settings.php' );
    }

    /**
     * Insert Kyber logo into checkout page
     * 
     * @since 0.0.1
     */
    public function get_icon() {
		$icons_str = '<img src="' . WC_KYBER_PLUGIN_URL . '/admin/images/kyber.svg" class="kyber-icon" alt="Kyber" />';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
    }

    /**
     * Get list token supported from kyber
     * 
     * @since 0.0.1
     */
    public function get_list_token_supported() {
        $tracker_url = sprintf('https://tracker.kyber.network/api/tokens/supported?chain=%s', $this->get_option( 'network' ) );
        $response = wp_remote_get( $tracker_url );

        $response_body= $response['body'];
        $data = json_decode( $response_body );

        $result = array();
        for ( $index = 0; $index < count( $data); $index++ ) {
            $result[$data[$index]->symbol] = $data[$index]->symbol;
        }

        return $result;
    }

    /**
     * Override process_payment from WC_Payment_Gateway class
     * 
     * @since 0.0.1 
     */
    public function process_payment( $order_id ) {
        
        global $woocommerce;
        $order = new WC_Order( $order_id );

        // check if setting is correct for payment
        $setting_ok = $this->validate_gateway_settings();
        if ( !$setting_ok ) {
            return;
        }

        // check if all products in order are supported pay by token
        $products_ok = $this->get_order_total_amount_by_token( $order );
        if ( !$products_ok ) {
            return;
        }

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    /**
     * Display transaction hash on order detail page
     * 
     * @since 0.0.1
     */
    public function add_tx_hash_to_order( $order ) {
        $order_tx = $order->get_meta("tx");
        $network = $order->get_meta( 'network' );

        if ( $order_tx != "" ) {
            printf("<tr class='woocommerce-table__line-item order_item' >
            <td class='woocommerce-table__product-name product-name'> 
            Order transaction hash: </td>
            <td class='woocommerce-table__product-total product-total'>
            <a href='https://%s.etherscan.io/tx/%s' target='_blank'>%s</a>
            </td></tr>", $network, $order_tx, $order_tx);
        }
    }

    public function monitor_tx_status ( $order ) {

        $web3 = new Web3(new HttpProvider(new HttpRequestManager('https://ropsten.infura.io', 5)));
        $tx = '0x940b6606c878919dff9fa5ac5f556b0ee33ecd27327f4596dec22d899bebc49e';
        $web3->eth->getTransactionReceipt($tx, function ($err, $transaction) {
            if ($transaction) {
                error_log(print_r($transaction, true));
            }
        });

        $web3->eth->getTransactionByHash($tx, function ($err, $transaction) {
            if ($transaction) {
                error_log(print_r($transaction, true));
            }
        });

    }

    /**
     * Check if Kyber gateway config is correct
     * 
     * @since 0.0.1
     */
    public function validate_gateway_settings() {
        $receiveAddr = $this->get_option( 'receive_addr' );
        if ( !$receiveAddr ) {
            wc_add_notice( __('Receive address is empty. You cannot use pay by token, please contact your shop about this issue', 'woocommerce-gateway-kyber'), 'error' );
            return false;
        }

        $receiveToken = $this->get_option( 'receive_token_symbol' );
        if ( !$receiveToken ) {
            wc_add_notice( __('Receive token is not supported.', 'woocommerce-gateway-kyber'), 'error' );
            return false;
        }
       
        $network = $this->get_option( 'network' );
        if ( $network != 'ropsten' && $network != 'mainnet' ) {
            wc_add_notice( __('Network is not valid.', 'woocommerce-gateway-kyber'), 'error' );
            return false;
        }

        $mode = $this->get_option( 'mode' );
        if ( $mode != 'tab' && $mode != 'iframe' && $mode != 'dom' ) {
            wc_add_notice( __('Network is not valid.', 'woocommerce-gateway-kyber'), 'error' );
            return false;
        }

        return true;
    }

    /**
     * Return total amount by token
     * if there is an item in order which does not support pay by token
     * then return 0 
     * 
     * @since 0.0.1
     */
    public function get_order_total_amount_by_token( $order ) {
        $products = $order->get_items();

        $total = 0;
        foreach( $products as $product ) {
            $token_price = $product->get_meta( 'kyber_token_price' );
            if ( !$token_price ) {
                wc_add_notice( __( sprintf( 'Item %s does not support pay by token.', $product->get_name() ), 'woocommerce-gateway-kyber' ), 'error' );
                return 0;
            }
            $total += $token_price;
        }

        if ( $total == 0 ) {
            wc_add_notice( __( sprintf( 'Order total should be greater than zero' ), 'woocommerce-gateway-kyber' ), 'error' );
            return 0;
        }

        return true;
    }

    /**
     * Build Kyber widget redirect url
     * 
     * @since 0.0.1
     */
    public function get_checkout_url( $order ) {
        $endpoint = "https://widget.knstats.com?theme=light&paramForwarding=true&";
        $callback_url = urlencode($this->get_option( 'site_url_for_dev' ) . '/wc-api/kyber_callback');

        if ( !$this->validate_gateway_settings() ) {
            return;
        }

        $receiveAddr = $this->get_option( 'receive_addr' );
        $receiveToken = $this->get_option( 'receive_token_symbol' );
        $network = $this->get_option( 'network' );
        $mode = $this->get_option( 'mode' );

        $receiveAmount = $this->get_order_total_amount_by_token($order);

        $endpoint .= 'mode='. $mode .'&receiveAddr=' . $receiveAddr . '&receiveToken=' . $receiveToken . '&callback=' . $callback_url . '&receiveAmount=' . $receiveAmount;
        $endpoint .= '&network=' . $network;

        // add custom params
        $order_id = $order->get_id();

        $endpoint .= '&order_id=' . strval($order_id);

        return $endpoint;
    }

    /**
     * Handle callback from Kyber widget
     * 
     * @since 0.0.1
     * 
     */
    public function handle_kyber_callback() {
        if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] )) {
			return;
        }

        $request_body    = file_get_contents( 'php://input' );
        $request_header = $this->get_request_headers();

        if ( $request_header['Content-Type'] == 'application/x-www-form-urlencoded' ) {
            parse_str( $request_body, $dataStr );
            $dataJSON = json_encode($dataStr);
            $data = json_decode($dataJSON);
        }

        $valid = $this->validate_callback_params($data);
        if ( !$valid ) {
            return;
        }
        
        // get data from callback
        $order_id = $data->order_id;
        $tx = $data->tx;
        $network = $data->network;

        $order = wc_get_order( $order_id );

        // Mark as on-hold (we're awaiting cheque)
        $order->update_status('on-hold', __("Awaiting cheque payment", "woocommerce-gateway-kyber"));

        // Save transaction hash to order
        $order->update_meta_data("tx", $tx);
        $order->update_meta_data("network", $network);
        $order->update_meta_data("tx_status", "pending");
        $order->save();

        // Reduce stock levels
        $order->reduce_order_stock();
    }

    /**
     * Validate callback params
     * 
     * @since 0.0.1
     */
    private function validate_callback_params( $request ) {
        $order_id = $request->order_id;

        return true;
    }


	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 *
	 * @since 0.0.1
	 * @version 0.0.1
	 */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = array();

			foreach ( $_SERVER as $name => $value ) {
				if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

    /**
     * 
     * Embed widget into order received page
     * 
     * @since 0.0.1
     */
    public function embed_kyber_widget_button( $order_id ) {
        $order = wc_get_order($order_id);

        if ( $order->get_payment_method() == 'kyber' ) {
            $endpoint = $this->get_checkout_url( $order );

            $widget_text = apply_filters( 'kyber_widget_text', 'Pay by tokens' );

            printf("<a href='%s'
            class='kyber-widget-button' name='KyberWidget - Powered by KyberNetwork' title='Pay by tokens'
            target='_blank'>%s</a>", $endpoint, $widget_text);
        }
    }

}