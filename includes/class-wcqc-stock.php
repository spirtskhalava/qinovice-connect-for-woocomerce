<?php
/**
 * XML Export class
 */
if ( ! class_exists( 'WooCommerce_Qinvoice_Connect_Stock' ) ) {

	class WooCommerce_Qinvoice_Connect_Stock {

		public $error;
		public $json;

		/**
		 * Constructor
		 */
		public function __construct() {					
			global $woocommerce;
			//$this->order = new WC_Order();
			
			//add_action( 'wp_ajax_generate_wcqc', array($this, 'process_request_ajax' ));
		}
		
		public function update($params){

			global $wpdb;

			
			if($params['sku'] == ''){
    			$this->error = __('SKU is missing');
    			return false;
    		}


			$string = 'sku='. $params['sku'] .'|qty='. $params['qty'];
			if(md5($string.$secret) != $check){
				$this->error = __('Incorrect checksum. Check your secret key.');
				return false;
			}

    		$id = wc_get_product_id_by_sku( $sku );

    		if($id == 0){
    			$this->error = __('Product not found: '. $params['sku']);
    			return false;
    		}

			$sql = "UPDATE $wpdb->postmeta SET meta_value = '". $params['qty'] ."' WHERE post_id = '". $id ."' and meta_key = '_stock'";
			
			
			if($wpdb->query( $sql ) === false){
				$this->error = __('Error executing query');
				return false;
			}else{
				return true;
			}
		}

		public function export($params){
			$full_product_list = array();
			$loop = new WP_Query( array( 'post_type' => array('product', 'product_variation'), 'posts_per_page' => -1 ) );
		 
			while ( $loop->have_posts() ) : $loop->the_post();
				$theid = get_the_ID();
				$product = new WC_Product($theid);
				// its a variable product
				if( get_post_type() == 'product_variation' ){
					$parent_id = wp_get_post_parent_id($theid );
					$sku = get_post_meta($theid, '_sku', true );
					$stock = get_post_meta($theid, '_stock', true );
					$price = get_post_meta($theid, '_price', true );
					$thetitle = get_the_title( $parent_id);

					$product_array['price_with'] =  woocommerce_price($product->get_price_including_tax());
		 
		    // ****** Some error checking for product database *******
		            // check if variation sku is set
		            if ($sku == '') {
		                if ($parent_id == 0) {
		            		// Remove unexpected orphaned variations.. set to auto-draft
		            		$false_post = array();
		                    $false_post['ID'] = $theid;
		                    $false_post['post_status'] = 'auto-draft';
		                    wp_update_post( $false_post );
		                    if (function_exists(add_to_debug)) add_to_debug('false post_type set to auto-draft. id='.$theid);
		                } else {
		                    // there's no sku for this variation > copy parent sku to variation sku
		                    // & remove the parent sku so the parent check below triggers
		                    $sku = get_post_meta($parent_id, '_sku', true );
		                    if (function_exists(add_to_debug)) add_to_debug('empty sku id='.$theid.'parent='.$parent_id.'setting sku to '.$sku);
		                    update_post_meta($theid, '_sku', $sku );
		                    update_post_meta($parent_id, '_sku', '' );
		                }
		            }
		 	// ****************** end error checking *****************
		 
		        // its a simple product
		        } else {
		            $sku = get_post_meta($theid, '_sku', true );
		            $thetitle = get_the_title();
		            $stock = get_post_meta($theid, '_stock', true );
					$price = get_post_meta($theid, '_price', true );
					$thetitle = get_the_title( $parent_id);
		        }
			        // add product to array but don't add the parent of product variations
			    if (!empty($sku)){
			    	$product_array['sku'] = $sku;
			    	$product_array['stock'] = $stock;
			    	$product_array['price'] = $price;
			    	$product_array['price_with'] =  $product->get_price_including_tax();
			    	$product_array['vat'] =  $product->get_tax_class();
			    	$product_array['title'] = $thetitle;
			    	$product_array['id'] = $theid;	
			    	$product_array['thumbnail'] = function_exists('the_post_thumbnail_url') ? the_post_thumbnail_url() : '';

			    } 

			endwhile; 

			wp_reset_query();
			    // sort into alphabetical order, by title
			//sort($full_product_list);
			$this->json = json_encode($product_array);
		    //print_r( $product_array );
		    wp_reset_postdata();
		    return true;
		}
						

	}
}