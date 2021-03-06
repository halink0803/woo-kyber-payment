<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       developer.kyber.network
 * @since      1.0.0
 *
 * @package    Woo_Kyber_Payment
 * @subpackage Woo_Kyber_Payment/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Kyber_Payment
 * @subpackage Woo_Kyber_Payment/includes
 * @author     Hoang Ha <halink0803@gmail.com>
 */
class Woo_Kyber_Payment {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woo_Kyber_Payment_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PLUGIN_NAME_VERSION' ) ) {
			$this->version = PLUGIN_NAME_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'woo-kyber-payment';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->loader->add_action('plugins_loaded', $this, 'init_kyber_payment');
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woo_Kyber_Payment_Loader. Orchestrates the hooks of the plugin.
	 * - Woo_Kyber_Payment_i18n. Defines internationalization functionality.
	 * - Woo_Kyber_Payment_Admin. Defines all hooks for the admin area.
	 * - Woo_Kyber_Payment_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-kyber-payment-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-kyber-payment-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-woo-kyber-payment-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-woo-kyber-payment-public.php';

		$this->loader = new Woo_Kyber_Payment_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woo_Kyber_Payment_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woo_Kyber_Payment_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woo_Kyber_Payment_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Woo_Kyber_Payment_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	public function missing_woocommerce_notice() {
		echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Kyber Payment requires %s to be installed and active.', 'woocommerce-gateway-stripe' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
	}

	public function init_kyber_payment() {
		if ( ! class_exists("WC_Payment_Gateway") ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-kyber-payment-gateway.php';
		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-kyber-logger.php'; 
		add_filter( 'woocommerce_payment_gateways', array($this, 'add_payment_gateways'),1000 );

		//
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_token_price_fields' ) );
   		add_action( 'woocommerce_process_product_meta', array( $this, 'kyber_save_price_token') );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'kyber_display_price_token' ) );
	}

    /**
     * Adding token price field to a single product
     * 
     * @since 0.0.1
     */
    public function add_token_price_fields() {

		$kyber_settings= get_option( 'woocommerce_kyber_settings', 1 );

		$token_symbol = $kyber_settings['receive_token_symbol'];

        $args = array(
            'id' => 'kyber_token_price',
            'label' => __( sprintf( 'Token price (%s)', $token_symbol ), 'woocommerce-gateway-kyber' ),
            'class' => 'kyber-token-price',
            'desc_tip' => true,
            'description' => __( 'This is price you want to receive by token', 'woocommerce-gateway-kyber' ),
        );

		woocommerce_wp_text_input( $args );
	}
	
	/**
	 * Save the token price 
	 * 
	 * @since 0.0.1
	 */
	function kyber_save_price_token( $post_id ) {
		$product = wc_get_product( $post_id );
		$token_price = isset( $_POST['kyber_token_price'] ) ? $_POST['kyber_token_price'] : '';
		$product->update_meta_data( 'kyber_token_price', sanitize_text_field( $token_price) );
		$product->save();
   }

   /**
	 * Display price token 
	 * 
	 * @since 0.0.1
	 */
	function kyber_display_price_token() {
		global $post;
		// Check for the custom field value
		$product = wc_get_product( $post->ID );
		$price_token = $product->get_meta( 'kyber_token_price' );
		$kyber_settings= get_option( 'woocommerce_kyber_settings', 1 );
		$token_symbol = $kyber_settings['receive_token_symbol'];

		if( $price_token ) {
			// Only display our field if we've got a value for the field title
			printf(
				'<p class="price">%s<span class="woocommerce-Price-amount amount">%s<span class="woocommerce-Price-currencySymbol"></span></span></p>',
				esc_html( $price_token ),
				esc_html( $token_symbol )
			);
		} else {
			printf(
				'<p class="price">This product cannot be paid by token.</p>'
			);
		}
	}

	function  add_payment_gateways ( $methods ) {
		$methods[] = 'WC_Kyber_Payment_Gateway';
		return $methods;
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woo_Kyber_Payment_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
