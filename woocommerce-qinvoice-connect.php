<?php
/**
 * Plugin Name: WooCommerce q-invoice connect
 * Plugin URI: www.q-invoice.com
 * Description: Print order invoices directly through q-invoice
 * Version: 2.1.4
 * Author: Sandro Pirtskhalava
 * License: GPLv3 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 * Text Domain: woocommerce-qinvoice-connect
 * Domain Path: /languages/
 */

if ( !class_exists( 'WooCommerce_Qinvoice_Connect' ) ) {

	class WooCommerce_Qinvoice_Connect {
	
		public static $plugin_prefix;
		public static $plugin_url;
		public static $plugin_path;
		public static $plugin_basename;
		
		public $writepanels;
		public $settings;
		public $export;

		public static $version = '2.1.4';

		/**
		 * Constructor
		 */
		public function __construct() {
			self::$plugin_prefix = 'wcqc_';
			self::$plugin_basename = plugin_basename(__FILE__);
			self::$plugin_url = plugin_dir_url(self::$plugin_basename);
			self::$plugin_path = trailingslashit(dirname(__FILE__));

			$this->general_settings = get_option('wcqc_general_settings');


			add_action('wp', array( $this , 'qinvoice_call' ) );
			
			// load the localisation & classes
			add_action( 'plugins_loaded', array( $this, 'translations' ) ); // or use init?
			add_action( 'init', array( $this, 'load_classes' ) );

			add_action( 'woocommerce_order_status_completed', array( &$this, 'qinvoice_woocommerce_payment_completed' ) ); // Add menu.
			add_action( 'woocommerce_payment_complete', array( &$this, 'qinvoice_woocommerce_payment_complete' ) ); // Add menu.
			add_action( 'woocommerce_checkout_order_processed', array( &$this, 'qinvoice_woocommerce_checkout_order_processed' ) ); // Add menu.
		}

		/**
		 * Load the translation / textdomain files
		 */
		public function translations() {
			load_plugin_textdomain( 'woocommerce-qinvoice-connect', false, dirname( self::$plugin_basename ) . '/languages' );
		}

		/**
		 * Load the main plugin classes and functions
		 */
		public function includes() {
			include_once( 'includes/class-wcqc-settings.php' );
			include_once( 'includes/class-wcqc-writepanels.php' );
			include_once( 'includes/class-wcqc-export.php' );
			include_once( 'includes/class-wcqc-stock.php' );
		}
			
		public function qinvoice_call(){
		    if(isset($_GET['qc']) ) {

		    	$secret = $this->general_settings['webshop_secret'];

				$data = explode("|", $_GET['qc_data']);

				foreach($data as $d){
					$values = explode("=", $d);
					$$values[0] = trim($values[1]);
				}



		        switch($_GET['qc']){
		        	case 'test':
		        		echo 'OK';
		        	break;
		        	case 'stock':
		        	
		        		if($this->stock->update($params)){
		        			echo 'Success';
		        		}else{
		        			echo $this->stock->error;
		        		}
		        	break;

		        	case 'export':
		        	
		        		if($this->stock->export($params) != false){
		        			echo $this->stock->json;
		        		}else{
		        			echo $this->stock->error;
		        		}
		        	break;


		        }
		        exit();
		    }
		    
		}

		/**
		 * Instantiate classes when woocommerce is activated
		 */
		public function load_classes() {
			if ( $this->is_woocommerce_activated() ) {
				$this->includes();
				$this->settings = new WooCommerce_Qinvoice_Connect_Settings();
				$this->writepanels = new WooCommerce_Qinvoice_Connect_Writepanels();
				$this->export = new WooCommerce_Qinvoice_Connect_Export();
				$this->stock = new WooCommerce_Qinvoice_Connect_Stock();
			} else {
				// display notice instead
				add_action( 'admin_notices', array ( $this, 'need_woocommerce' ) );
			}

		}

		/**
		 * Check if woocommerce is activated
		 */
		public function is_woocommerce_activated() {
			$blog_plugins = get_option( 'active_plugins', array() );
			$site_plugins = get_site_option( 'active_sitewide_plugins', array() );

			if ( in_array( 'woocommerce/woocommerce.php', $blog_plugins ) || isset( $site_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			} else {
				return false;
			}
		}
		
		/**
		 * WooCommerce not active notice.
		 *
		 * @return string Fallack notice.
		 */
		 
		public function need_woocommerce() {
			$error = sprintf( __( 'WooCommerce Qinvoice Connect requires %sWooCommerce%s to be installed & activated!' , 'wcqc' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>' );
			
			$message = '<div class="error"><p>' . $error . '</p></div>';
		
			echo $message;
		}

		function qinvoice_woocommerce_payment_completed( $order_id ) {
		    if($this->general_settings['invoice_trigger'] == 'completed'){
		    	$this->export->process_request($this->general_settings['request_type'],array($order_id), false);
		    }
		}

		function qinvoice_woocommerce_payment_complete( $order_id ) {
		    if($this->general_settings['invoice_trigger'] == 'payment'){
		    	$payment_method = get_post_meta($order_id,'_payment_method', true);
		    	if(in_array($payment_method,$this->general_settings['exclude_payment_method'])){
		    		return true;
		    	}
		    	$this->export->process_request(strlen($this->general_settings['request_type']) > 0 ? $this->general_settings['request_type'] : 'invoice',array($order_id), false);
		    	
		    }
		}
		function qinvoice_woocommerce_checkout_order_processed( $order_id ) {
		    if($this->general_settings['invoice_trigger'] == 'order'){
		    	$this->export->process_request(strlen($this->general_settings['request_type']) > 0 ? $this->general_settings['request_type'] : 'invoice',array($order_id), false);
		    }
		}
	}		
}

// Load main plugin class
$wcqc = new WooCommerce_Qinvoice_Connect();




