<?php
/**
 * XML Export class
 */
if ( ! class_exists( 'WooCommerce_Qinvoice_Connect_Export' ) ) {

	class WooCommerce_Qinvoice_Connect_Export {

		public $order;
		public $request_type;
		public $order_id;

		/**
		 * Constructor
		 */
		public function __construct() {					
			global $woocommerce;
			//$this->order = new WC_Order();
			$this->general_settings = get_option('wcqc_general_settings');
			add_action( 'wp_ajax_generate_wcqc', array($this, 'process_request_ajax' ));
		}
		
		
		/**
		 * Sends the actual request to the Q-invoice.com API
		 */
		public function send_request( $request_type = 'invoice', $order_id, $output = true )	{



			if ( !class_exists('qinvoice') ) {
				require_once( WooCommerce_Qinvoice_Connect::$plugin_path . "includes/qinvoice.class.php" );
			}
			
			try{
				
				$this->order = new WC_Order( $order_id );
				$document = new qinvoice($this->general_settings['api_username'] ,$this->general_settings['api_password'],$this->general_settings['api_url']);
				$document->identifier =  'wcqc_'. WooCommerce_Qinvoice_Connect::$version;
				$document->setDocumentType($request_type);

				// customer note
				$page_data = get_page( $order_id );
				$excerpt = strip_tags($page_data->post_excerpt);

				
				

				$document->companyname = $this->order->billing_company; 		// Your customers company name

				$document->firstname = $this->order->billing_first_name;
				$document->lastname = $this->order->billing_last_name;
				$document->email = $this->order->billing_email;				// Your customers emailaddress (invoice will be sent here)
				$document->phone = $this->order->billing_phone;
				$document->address = $this->order->billing_address_1; 				// Self-explanatory
				$document->address2 = $this->order->billing_address_2; 				// Self-explanatory
				$document->zipcode = $this->order->billing_postcode; 				// Self-explanatory
				$document->city = $this->order->billing_city; 					// Self-explanatory
				$document->country = $this->order->billing_country; 				// 2 character country code: NL for Netherlands, DE for Germany etc
				
				$document->vatnumber = get_post_meta( $order_id, 'VAT Number', true );

				$document->delivery_firstname = $this->order->shipping_first_name;
				$document->delivery_lastname = $this->order->shipping_last_name; 
				$document->delivery_address = $this->order->shipping_address_1; 
				$document->delivery_address = $this->order->shipping_address_1; 				// Self-explanatory
				$document->delivery_address2 = $this->order->shipping_address_2; 				// Self-explanatory
				$document->delivery_zipcode = $this->order->shipping_postcode; 				// Self-explanatory
				$document->delivery_city = $this->order->shipping_city; 					// Self-explanatory
				$document->delivery_country = $this->order->shipping_country; 				// 2 character country code: NL for Netherlands, DE for Germany etc
				$document->delivery_phone = $this->order->billing_phone;
				

				//https://www.tychesoftwares.com/store/premium-plugins/order-delivery-date-for-woocommerce-lite/
				if( get_post_meta($order_id,'_orddd_lite_timestamp',true) ){
					$document->delivery_date = Date('Y-m-d', get_post_meta($order_id,'_orddd_lite_timestamp',true) );
				}

				// not sure what the field name in pro version is. 'pro'?
				if( get_post_meta($order_id,'_orddd_pro_timestamp',true) ){
					$document->delivery_date = Date('Y-m-d',  get_post_meta($order_id,'_orddd_pro_timestamp',true) );
				}
				// or just plain? 
				if( get_post_meta($order_id,'_orddd_timestamp',true) ){
					$document->delivery_date = Date('Y-m-d',  get_post_meta($order_id,'_orddd_timestamp',true) );
				}
					
				$document->action = (int)$this->general_settings['invoice_action'];
				$document->saverelation = (int)$this->general_settings['save_relation'];
				
				if(defined('WCML_VERSION') && 1==1){
					$currency = get_post_meta($order_id,'_order_currency', true);
					$document->layout = (int)$this->general_settings['layout_code_'.$currency];
					$document->currency = $currency;
				}else{
					$document->layout = (int)$this->general_settings['layout_code'];
					$document->currency = get_woocommerce_currency();
				}
				
				

				$document->calculation_method = $this->general_settings['calculation_method'];

				$document->vat = ''; 					// Self-explanatory

				$order_date = explode(" ",$this->order->order_date);

				$date_format = get_option( 'date_format' );

				$date = new DateTime($order_date[0]);
				

				$remark = $this->general_settings['invoice_remark'];
				$remark = str_replace('{order_id}', $order_id, $remark);
				$remark = str_replace('{order_number}', $this->order->get_order_number(), $remark);
				$remark = str_replace('{order_date}', $date->format($date_format), $remark);
				$remark = str_replace('{customer_note}', $excerpt, $remark);
				$paid_date = get_post_meta($order_id,'_paid_date', true);

				$paid = 0;
				if($paid_date > 0000-00-00){
					// set paid or not
					$paid = 1;
				}
				if($paid && $this->general_settings['send_payment_status'] != "no"){
					$method = get_post_meta($order_id,'_payment_method_title', true);
					$paidremark = $this->general_settings['paid_remark'];
					$remark .= ' '. str_replace('{method}', $method, $paidremark);
				}else{
					// reset value
					$paid = 0;
				}
				
				$document->payment_method = get_post_meta($order_id,'_payment_method_title', true);
				$document->paid = $paid;
				$document->remark = $remark;
				
				if($this->general_settings['invoice_date'] == 'order'){
					$document->date = $order_date[0];
				}else{
					$document->date = Date('Y-m-d');
				}

				// OPTIONAL: Add tags
				$document->addTag($order_id);
				if(strlen($this->general_settings['invoice_tag']) > 0){
					$tag = $this->general_settings['invoice_tag'];
					$tag = str_replace('{method}', $method, $tag);
					$tag = str_replace('{order_number}', $this->order->get_order_number(), $tag);
					$tag = str_replace('{order_date}', $date->format($date_format), $tag);
					$document->addTag($tag);
				}

				if(strlen($this->general_settings['invoice_reference']) > 0){
					$reference = $this->general_settings['invoice_reference'];
					$reference = str_replace('{order_number}', $this->order->get_order_number(), $reference);
					$document->document_reference = $reference;
				}

				$default_ledger = $this->general_settings['default_ledger_account'];
				$products_total = 0;

				foreach($this->get_order_items() as $item){ // Repeat this block for each product
					
					$_product = $item['item'];
					
					$sku = '';
					
					$product = new WC_Product($_product['product_id']);

					$sku = $product->get_sku();

					if($sku == ''){
						$sku = $item['sku'];
					}


					if($item['quantity'] == 0 || $item['quantity'] == ''){
						// skip 
						continue;
					}
					//echo '<hr/>'. $item['categories'] .'<hr/>';
					if($item['price_excl'] == 0 || $item['price_excl'] == ''){
						$vatp = 0;
					}else{
						$vatp = ($item['line_tax']/$item['quantity']) / $item['price_excl'];
					}
					
					$vatp = $vatp * 10000;
					$vatp = $item['tax_rate']*100;
					if($this->order->order_tax == 0){
						// overwrite tax
						$vatp = 0;
					}

					// populate array with attributes
					$attr_array = array();
					foreach(get_post_meta( $product->id , '_product_attributes' ) as $attr){
						foreach($attr as $name=>$attr_data){
							if($attr_data['is_variation'] == 1 || 1==1){
								$attr_array[] = $name;
								$attr_names[$name] = $attr_data['name'];
							}
						}
					}


					
					$item_desc = '';

					
					foreach($item['item']['item_meta'] as $key=>$val){
						$result_array = false;
						if(in_array($key,$attr_array)){

							$result = $val[0];
							$key = str_replace("attribute_pa_","",$key);
							$key = str_replace("attribute_","",$key);
							$key = str_replace("pa_","",$key);
							$item_desc .= "\n";
							$item_desc .= strlen($attr_names[$key]) > 0 ? $attr_names[$key] : ucfirst($key);
							$item_desc .= ': '. $result;
						}else{
							// get meta items that don't start with an underscore and only add them if add_item_meta is set
							if(substr($key, 0,1) != "_" && $this->general_settings['add_item_meta'] == 1){
								$item_desc .= "\n";
								$item_desc .= ucfirst($key);
								$item_desc .= ': '. $val[0];
							}
						}
					}
					
					if(count($this->order->get_used_coupons()) > 0 || $item_total = $item['line_subtotal'] == $item_total = $item['line_total']){
						// use line_subtotal (without discount, discount added seperately)
						$item_total = $item['line_subtotal'];
						$item_tax = $item['line_subtotal_tax'];
					}else{
						// use line_total (including manually added discounts without coupon)
						$item_total = $item['line_total'];
						$item_tax = $item['line_tax'];
					}
					
				
					$price = ($item_total/$item['quantity'])*100;
					
					$params = array(	'code' => $sku,
										'description' => str_replace("&nbsp;","",$item['name'] . $item_desc),		// Item description
										'price' => $price,			// Item price, multiplied by 100: EUR 10 becomes 1000
										'price_incl' => (($item_total + $item_tax)/$item['quantity'])*100,
										'price_vat' => round(($item_tax/$item['quantity'])*100),
										'vatpercentage' => $vatp,		// Item vat percentage, multiplied by 100: 19% becomes 1900 (without '%')
										'discount' => 0,			// Discount percentage, also multiplied by 100 without '%'
										'quantity' => $item['quantity']*100,			// Item quantity, again multiplied by 100 (1.75 becomes 175, 1 becomes 100)
										'categories' => $item['categories'],			// Categories
										'ledgeraccount' => ($item['ledgeraccount'] > 0) ? $item['ledgeraccount'] : $default_ledger			// Ledger account
									);
					

					
					$document->addItem($params);
					$products_total += $price;
				}


				if(method_exists($this->order,'get_total_shipping')){
					$total_shipping = $this->order->get_total_shipping();
				}else{
					$total_shipping = $this->order->get_shipping();
				}

				if($total_shipping > 0){
					$vatp = $this->order->get_shipping_tax() / $total_shipping;
					$vatp = round($vatp*100) * 100;
					$params = array(	'code' => 'SHPMNT',
										'description' => $this->order->get_shipping_method(),		// Item description
										'price_incl' => ($total_shipping + $this->order->get_shipping_tax())*100,				// Item price, multiplied by 100: EUR 10 becomes 1000
										'price' => $total_shipping*100,
										'price_vat' => $this->order->get_shipping_tax()*100,
										'vatpercentage' => round($vatp),		// Item vat percentage, multiplied by 100: 19% becomes 1900 (without '%')
										'discount' => 0,			// Discount percentage, also multiplied by 100 without '%'
										'quantity' => 100,			// Item quantity, again multiplied by 100 (1.75 becomes 175, 1 becomes 100)
										'categories' => 'shipping'			// Categories
									);

					$document->addItem($params);
				}
				
				


				/**
			 * Return the order fees
				 */
				if(method_exists($this->order,'get_fees')){
					if ( $fees = $this->order->get_fees() ) {
						foreach( $fees as $id => $fee ) {
							$tax = new WC_Tax();
							$rates = ($tax->get_rates($fee['tax_class']));
							
							foreach($rates as $rate){
								$tax_rate = $rate['rate'];
							}
							
							$price = ($fee['line_total']*100 / (100+$tax_rate))*100;
							$price_incl = ($fee['line_total'])*100;
							$price_vat = $price_incl - $price;
							
							$params = array( 	
								'code' => 'CSTS',
		     					'description' => $fee['name'],
		     					'price' => $price,
		     					'price_incl' => $price_incl,
								'price_vat' => $price_vat,
		     					'vatpercentage' => $tax_rate*100,
		     					'discount' => 0,
		     					'quantity' => 100,
		     					'categories' => 'fees'
		             		);
		            		$document->addItem($params);
						}
					}
				}
			}catch (Exception $e) {
			    echo 'Caught exception: ',  $e->getMessage(), "\n";
			}

			

			$discount = false;
			$description = '';
			foreach($this->order->get_used_coupons() as $code){
				$coupon = new WC_Coupon($code);
				
				//echo $this->general_settings['coupon_vat'];
                 if($coupon->apply_before_tax == 'no'){
                 	$vatp = 0;
                 }else{
                 	$vatp = get_option( WooCommerce_Qinvoice_Connect::$plugin_prefix . 'coupon_vat' );
                 	if($vatp == ''){
                 		// fallback
                 		$vatp = $this->general_settings['coupon_vat'];
                 	}
                 }
                 $discount = true;
                 $description .= $code .' ';
			}

			// calculate price for discount
			$price = $this->order->get_total_discount();
			$price_incl = round($this->order->get_total_discount()*(100 + $vatp),2)/100;
			$price_vat = $price_incl - $price;
			


			if($discount == true){
				$params = array( 	
							'code' => 'DSCNT',
         					'description' => $description,
         					'price' => $price*-100,
         					'price_incl' => $price_incl*-100,
							'price_vat' => $price_vat*-100,
         					'vatpercentage' => $vatp*100,
         					'discount' => 0,
         					'quantity' => 100,
         					'categories' => 'discount'
                 		);
                $document->addItem($params);
			}
			
			return $result = $document->sendRequest();
			
			unset($this->order);
		}
		
		/**
		 * Send request for each order_id
		 */
		public function process_request( $request_type = 'invoice', $order_ids, $output = false ) {
			$html = '';
			foreach ($order_ids as $order_id) {
				 $result = $this->send_request( $request_type, $order_id );
				 //				 sleep(1);
				 $request_type = explode(".",$request_type);
				 $request_type = $request_type[0];
				 $html .= 'Order '. $order_id .': ';
				 if($result == true){
					//add_post_meta($order_id, '_invoiced', true, true); 
					if($request_type == 'invoice'){
						$html .= __('Invoice generated.','wcqc');
					}elseif($request_type == 'quote'){
						$html .= __('Quote generated.','wcqc');
					}elseif($request_type == 'orderconfirmation'){
						$html .= __('Order confirmation generated.','wcqc');
					}elseif($request_type == 'recurring'){
						$html .= __('Recurring invoice generated.','wcqc');
					}elseif($request_type == 'proforma'){
						$html .= __('Proforma invoice generated.','wcqc');
					}
				}else{
					$html .= __('Uhoh. Something went wrong.','wcqc');
				}
				$html .= '<br />';
			}
			if($output == true){
				echo $html;	
			}
		}

		/**
		 * Load and generate the template output with ajax
		 */
		public function process_request_ajax() {
			// Check the nonce
			if( !is_user_logged_in() || !check_admin_referer( $_GET['action'] ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wcqc' ) );
			}
			
			// Check if all parameters are set
			if( empty( $_GET['request_type'] ) || empty( $_GET['order_ids'] ) ) {
				wp_die( __( 'Missing parameters.', 'wcqc' ) );
			}

			// Check the user privileges
			if( !current_user_can( 'manage_woocommerce_orders' ) && !current_user_can( 'edit_shop_orders' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wcqc' ) );
			}

			$order_ids = (array) explode('x',$_GET['order_ids']);
			// Process oldest first: reverse $order_ids array
			$order_ids = array_reverse($order_ids);
		
			// Generate the output
			$request_type = $_GET['request_type'];
			// die($this->process_template( $request_type, $order_ids )); // or use the filter switch below!

			$document = $this->process_request( $request_type, $order_ids, true );


			echo '<hr /> <a href="javascript:window.close();">'. __('close this window.','wcqc') .'</a>';
			
			
			exit;
		}

		
		/**
		 * Get the current order
		 */
		public function get_order() {
			return $this->order;
		}

		/**
		 * Get the current order items
		 */
		public function get_order_items_v2() {
			global $woocommerce;
			global $_product;

			$items = $this->order->get_items();
			$data_list = array();
		
			if( sizeof( $items ) > 0 ) {
				foreach ( $items as $item ) {
					// Array with data for the pdf template
					$data = array();
					
					// Set the id
					$data['product_id'] = $item['product_id'];
					$data['variation_id'] = $item['variation_id'];

					// Set item name
					$data['name'] = $item['name'];
					
					// Set item quantity
					$data['quantity'] = $item['qty'];

					// Set the line total (=after discount)
					$quantity_divider = ( $item['qty'] == 0 ) ? 1 : $item['qty']; // prevent division by zero
					$data['line_total'] =  $item['line_total'] ;
					$data['single_line_total'] =  $item['line_total'] / $quantity_divider ;
					$data['line_tax'] =  $item['line_tax'] ;
					$data['single_line_tax'] =  $item['line_tax'] / $quantity_divider ;
					$data['tax_rate'] = $this->get_tax_rate( $item['tax_class'], $item['line_total'], $item['line_tax'] );
					
					// Set the line subtotal (=before discount)
					$data['line_subtotal'] =  $item['line_subtotal'] ;
					$data['line_subtotal_tax'] =  $item['line_subtotal_tax'] ;
					$data['ex_price'] =  $item ;
					$data['price'] =  $item ;
					$data['order_price'] = $this->order->get_formatted_line_subtotal( $item ); // formatted according to WC settings

					// Calculate the single price with the same rules as the formatted line subtotal (!)
					// = before discount
					$data['ex_single_price'] = $this->get_formatted_item_price ( $item, 'single', 'excl' );
					$data['single_price'] = $this->get_formatted_item_price ( $item, 'single' );
					
					// Set item meta and replace it when it is empty
					$meta = new WC_Order_Item_Meta( $item['item_meta'] );	
					$data['meta'] = $meta->display( false, true );

					$WC_Tax = new WC_Tax();

					// Pass complete item array
					$data['item'] = $item;
					
					// Create the product to display more info
					$data['product'] = null;
					
					$product = $this->order->get_product_from_item( $item );
					
					// Checking fo existance, thanks to MDesigner0 
					if(!empty($product)) {
										
						// Set item SKU
						$data['sku'] = $product->get_sku();
		
						// Set item weight
						$data['weight'] = $product->get_weight();
						
						// Set item dimensions
						$data['dimensions'] = $product->get_dimensions();
					
						// Pass complete product object
						$data['product'] = $product;

						$data['categories'] = '';

						$data['price_excl'] = $product->get_price_excluding_tax(1);

						//$data['tax_class'] = $product->get_tax_status();

						$data['categories'] = get_post_meta($product->id, 'categories', true );
						//echo '<hr />'. $product->get_tax_class();
						
					
					}

					$data_list[] = apply_filters( 'wcqc_order_item_data', $data );
				}
			}

			return apply_filters( 'wcqc_order_items_data', $data_list );
		}
		
		public function get_order_items() {
			global $woocommerce;
			global $_product;

			$items = $this->order->get_items();
			$tax = $this->order->get_items( 'tax' );
			$data_list = array();
		
			if( sizeof( $items ) > 0 ) {
				//print_r($items);
				//print_r($tax);
				foreach ( $items as $item ) {
					// Array with data for the printing template
					$data = array();

					
					
					// Set the id
					$data['product_id'] = $item['product_id'];
					$data['variation_id'] = $item['variation_id'];
										
					// Set item name
					$data['name'] = $item['name'];
					
					// Set item quantity
					$data['quantity'] = $item['qty'];

					// Set the subtotal for the number of products
					$data['line_total'] = $item['line_total'];
					$data['line_tax'] = $item['line_tax'];
					
					// Set the final subtotal for all products
					$data['line_subtotal'] = $item['line_subtotal'];
					$data['line_subtotal_tax'] = $item['line_subtotal_tax'];
					$data['formatted_line_subtotal'] = $this->order->get_formatted_line_subtotal( $item );
					$data['price'] = $data['formatted_line_subtotal'];
					
					// Set item meta and replace it when it is empty
					$meta = new WC_Order_Item_Meta( $item['item_meta'] );	
					$data['meta'] = $meta->display( false, true );
					$WC_Tax = new WC_Tax();
					

					// Pass complete item array
	                $data['item'] = $item;
					
					// Create the product to display more info
					$data['product'] = null;
					
					$product = $this->order->get_product_from_item( $item );
					
					// Checking fo existance, thanks to MDesigner0 
					if(!empty($product)) {	
						//print_r($product);
						// Set the single price
						$data['single_price'] = $product->get_price();
										
						// Set item SKU
						$data['sku'] = $product->get_sku();
		
						// Set item weight
						$data['weight'] = $product->get_weight();
						
						
						// Set item dimensions
						$data['dimensions'] = $product->get_dimensions();
					
						// Pass complete product object
						$data['product'] = $product;

						$data['categories'] = '';

						$data['price_excl'] = $product->get_price_excluding_tax(1);

						//$data['tax_class'] = $product->get_tax_status();

						$data['categories'] = get_post_meta($product->id, 'categories', true );
						//echo '<hr />'. $product->get_tax_class();
						$rates = ($WC_Tax->get_rates($product->get_tax_class()));
						//print_r($rates);
						foreach($rates as $r){
							$data['tax_rate'] = $r['rate'];
						}
						
						//print_r($data);
					
					}

					$data_list[] = $data;
				}
			}

			return  $data_list ;
		}
		
		/**
		 * Get the tax rates/percentages for a given tax class
		 * @param  string $tax_class tax class slug
		 * @return string $tax_rates imploded list of tax rates
		 */
		public function get_tax_rate( $tax_class, $line_total, $line_tax ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
				// WC 2.1 or newer is used
				if ( $line_tax == 0 ) {
					return '-'; // no need to determine tax rate...
				}

				// if (empty($tax_class))
				// $tax_class = 'standard';// does not appear to work anymore - get_rates does accept an empty tax_class though!
				
				$tax = new WC_Tax();
				$taxes = $tax->get_rates( $tax_class );

				$tax_rates = array();

				foreach ($taxes as $tax) {
					$tax_rates[$tax['label']] = round( $tax['rate'], 2 );
				}

				if (empty($tax_rates)) {
					// one last try: manually calculate
					if ( $line_total != 0) {
						$tax_rates[] = round( ($line_tax / $line_total)*100, 1 );
					} else {
						$tax_rates[] = '-';
					}
				}

				$tax_rates = implode(' ,', $tax_rates );
			} else {
				// Backwards compatibility: calculate tax from line items
				if ( $line_total != 0) {
					$tax_rates = round( ($line_tax / $line_total)*100, 1 );
				} else {
					$tax_rates = '-';
				}
			}
			
			return $tax_rates;
		}

		/**
		 * wrapper for wc2.1 depricated price function
		 */
		public function wc_price( $price, $args = array() ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1' ) >= 0 ) {
				// WC 2.1 or newer is used
				$args['currency'] = $this->order->get_order_currency();
				$formatted_price = wc_price( $price, $args );
			} else {
				$formatted_price = woocommerce_price( $price );
			}

			return $formatted_price;
		}

		/**
		 * Gets price - formatted for display.
		 *
		 * @access public
		 * @param mixed $item
		 * @return string
		 */
		public function get_formatted_item_price ( $item, $type, $tax_display = '' ) {
			$item_price = 0;
			$divider = ($type == 'single' && $item['qty'] != 0 )?$item['qty']:1; //divide by 1 if $type is not 'single' (thus 'total')

			if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) 
				return;

			if ( $tax_display == 'excl' ) {
				$item_price = $this->wc_price( ($this->order->get_line_subtotal( $item )) / $divider );
			} else {
				$item_price = $this->wc_price( ($this->order->get_line_subtotal( $item, true )) / $divider );
			}

			return $item_price;
		}

		/**
		 * Get order custom field
		 */
		public function get_order_field( $field ) {
			if( isset( $this->get_order()->order_custom_fields[$field] ) ) {
				return $this->get_order()->order_custom_fields[$field][0];
			} 
			return;
		}

		
		
	}

}