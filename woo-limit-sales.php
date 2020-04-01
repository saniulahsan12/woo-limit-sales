<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/**
 * @package WooLimitSales
 */
/*
Plugin Name: Woo Limit Sales
Plugin URI: #
Description: The aim of this plugin is to limit sales through a woocommerce web site depending on the total amount of sales per month
Version: 1.0
Author: Saniul Ahsan
Author URI: http://saniulahsan.info
Text Domain: woo-limi-sales
*/

/*
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

Copyright 2019 Saniul Ahsan, Inc.
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	die( 'No script kiddies please!' );
}


if( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ):

	register_activation_hook(__FILE__, 'woo_sales_limit_copy_files');
	register_deactivation_hook(__FILE__, 'woo_sales_limit_destroy_files');
	add_action('admin_menu', 'woo_limit_sales_menu');

	function woo_sales_limit_copy_files(){
	    $plugin_dir = plugin_dir_path(__FILE__) . 'cron_woo_sales_limit.php';
			$theme_dir = get_stylesheet_directory() . '/cron_woo_sales_limit.php';

	    if( !copy($plugin_dir, $theme_dir) ):
	        echo "failed to copy file";
	    endif;
	}

	function woo_sales_limit_destroy_files(){
	    if( !unlink(get_stylesheet_directory() . '/cron_woo_sales_limit.php') ):
	        echo "failed to remove file";
	    endif;
	}

	function site_get_total_sales() {

  		global $wpdb;

  		$order_totals = apply_filters( 'woocommerce_reports_sales_overview_order_totals', $wpdb->get_row( "

  		SELECT SUM(meta.meta_value) AS total_sales, COUNT(posts.ID) AS total_orders FROM {$wpdb->posts} AS posts

  		LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id

  		WHERE meta.meta_key = '_order_total'

  		AND posts.post_type = 'shop_order'
  		AND posts.post_date >= '".get_option('start_date')."'
 		  AND posts.post_date <= '".get_option('end_date')."'

  		AND posts.post_status IN ( '" . implode( "','", array( 'wc-completed', 'wc-processing', 'wc-on-hold' ) ) . "' )

  		" ) );

  		return absint( $order_totals->total_sales);

  }
	//print_r(site_get_total_sales());exit;

  // display message when order exceeded
	if( get_option('active_sales_limit') == true ){
			if(!is_admin()){
					add_action('woocommerce_init', 'display_custom_notice');
			}
		  add_action('woocommerce_before_calculate_totals', 'display_custom_notice');
		  add_action('woocommerce_before_shop_loop', 'display_custom_notice');
		  add_action('woocommerce_before_cart', 'display_custom_notice');
		  add_action('woocommerce_single_product_summary', 'display_custom_notice');
		  add_action('woocommerce_before_checkout_form', 'display_custom_notice');
			// Replacing the button add to cart by an inactive button on single product pages
		  add_action( 'woocommerce_single_product_summary', 'remove_add_to_cart_button', 1 );
			// Replacing the button add to cart by a link to the product in Shop and archives pages
		  add_filter( 'woocommerce_loop_add_to_cart_link', 'replace_loop_add_to_cart_button', 10, 2 );
			// Checking and validating when products are added to cart
			add_filter( 'woocommerce_add_to_cart_validation', 'items_allowed_add_to_cart', 10, 3 );
			add_filter( 'woocommerce_update_cart_validation', 'items_allowed_cart_update', 10, 4 );

	}

  function display_custom_notice( $cart ) {

      if( site_get_total_sales() >= get_option('sales_limit') ){
          // Display a custom notice
          wc_clear_notices();

					if( get_option('notice_message') ){
          		wc_add_notice( __( get_option('notice_message') ,  "woocommerce"), 'notice' );
					}

					if( get_option('notification_email_message') ){
							wp_mail( get_bloginfo('admin_email'), 'Sales limit exceeded', get_option('notification_email_message') );
					}
       }
   }

  function replace_loop_add_to_cart_button( $button, $product  ) {
      // Only when total volume is up to 68
      if( site_get_total_sales() >= get_option('sales_limit') ){
 		     $small_text = '<br><em style="font-size:85%;">(' . __( "Max sales limit reached", "woocommerce" ) . ')</em>';
 		     $button_text = __( "View product", "woocommerce" ) . $small_text;
 		     return '<a class="button" href="' . $product->get_permalink() . '">' . $button_text . '</a>';
 		 } else{
 			 	 return $button;
 		 }
  }

  function remove_add_to_cart_button() {
 		 if( site_get_total_sales() >= get_option('sales_limit') ){
 			 	global $product;
 			 	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
 			 	remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
 			 	add_action( 'woocommerce_single_product_summary', 'inactive_add_to_cart_button', 20 );
 		 } else{
 				return;
 		 }

  }

function items_allowed_add_to_cart( $passed, $product_id, $quantity ) {
		$passed = true;

    if( floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_cart_subtotal() ) ) >= abs( (float)get_option('sales_limit') - (float)site_get_total_sales() )  ){
    		$passed = false;
        wc_add_notice( __( get_option('notice_message'), "woocommerce" ), "error" );
    }

    return $passed;
}

  // Utility function: displays a custom innactive add to cart button replacement
  function inactive_add_to_cart_button(){
      global $product;

      $style = 'style="color:#fff;cursor:not-allowed;background-color:#999;"';
      echo '<a class="button" '.$style.'>' . __ ( 'Max sales limit reached', 'woocommerce' ) . '</a>';
  }


	function woo_limit_sales_menu() {
  		add_menu_page( 'Woo Limit Sales', 'Woo Limit Sales', 'manage_options', 'woo-limit-sales', 'woo_limit_sales_func' );
	}
	function woo_limit_sales_func(){

			if( isset( $_POST['submit_woo_sales_options'] ) ){
					update_option('sales_limit', $_POST['sales_limit']);
					update_option('start_date', $_POST['start_date']);
					update_option('end_date', date("Y-m-t", strtotime($_POST['start_date'])) );
					update_option('admin_email', $_POST['admin_email']);
					update_option('notice_message', $_POST['notice_message']);
					update_option('notification_email_message', $_POST['notification_email_message']);
					update_option('notification_email_store_opened', $_POST['notification_email_store_opened']);

					update_option('active_sales_limit', true);
			}

			if( isset( $_POST['active_inactive_woo_sales_options'] ) ){
					if( get_option('active_sales_limit') == true ){
							update_option('active_sales_limit', false);
					} else {
							update_option('active_sales_limit', true);

							if( get_option('notification_email_store_opened') ):

									$args = array('orderby' => 'display_name');
									$wp_user_query = new WP_User_Query($args);
									$authors = $wp_user_query->get_results();
									$mail_list = [];
									if (!empty($authors)):
											foreach($authors as $author):
													$author_info = get_userdata($author->ID);
													$mail_list[] = $author_info->user_email;
											endforeach;
											wp_mail( $mail_list, get_bloginfo('name') . ' is now open for business again', get_option('notification_email_store_opened') );
									endif;

							endif;
					}
			}

			?>
			<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
			<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.min.css" />
			<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js"></script>
			<div class="container">
					<div class="col">
							<h2>Woo Limit Sales Options</h2>
							<h6>Todays Date <?php echo date('Y-m-d');?></h6>
							<form method="post" action="">
									<p><strong>Sales Limit (£):</strong><br />
											<input class="form-control" type="text" name="sales_limit" size="45" value="<?php echo get_option('sales_limit'); ?>" />
									</p>
									<p><strong>Start Date:</strong><br />
											<input class="datepicker form-control" type="text" name="start_date" size="45" value="<?php echo get_option('start_date'); ?>" />
									</p>
									<p><strong>End Date (Last date of the month):</strong><br />
											<input readonly class="datepicker form-control" type="text" name="end_date" size="45" value="<?php echo get_option('end_date'); ?>" />
									</p>
									<p><strong>Admin Email:</strong><br />
											<input class="form-control" type="text" name="admin_email" size="45" value="<?php echo get_option('admin_email'); ?>" />
									</p>
									<p><strong>Notice Message:</strong><br />
											<input class="form-control" type="text" name="notice_message" size="45" value="<?php echo get_option('notice_message'); ?>" />
									</p>
									<p><strong>Notification Email Message:</strong><br />
											<input class="form-control" type="text" name="notification_email_message" size="45" value="<?php echo get_option('notification_email_message'); ?>" />
									</p>
									<p><strong>Notification Email For Store Opened:</strong><br />
											<input class="form-control" type="text" name="notification_email_store_opened" size="45" value="<?php echo get_option('notification_email_store_opened'); ?>" />
									</p>
									<p><input type="submit" name="submit_woo_sales_options" value="Save Sales Options" class="btn btn-info"/></p>

									<?php if( get_option('active_sales_limit') == true ): ?>
											<p><input type="submit" name="active_inactive_woo_sales_options" value="Operational" class="btn btn-success"/></p>
									<?php else:?>
											<p><input type="submit" name="active_inactive_woo_sales_options" value="Shut Down" class="btn btn-danger"/></p>
									<?php endif; ?>

									<input type="hidden" name="page" value="woo-limit-sales" />

							</form>
							<?php if( get_option('active_sales_limit') == true ): ?>
							<div class="alert alert-info">
									Total Order Sales: <?php echo '£'.site_get_total_sales(); ?>
									<br>
									Sales Limit This Month: <?php echo '£'.get_option('sales_limit'); ?>
									<br>
									Remaining Sales: <?php echo '£'. (get_option('sales_limit') - site_get_total_sales()); ?>
						 	</div>
							<?php endif; ?>
					</div>
			</div>
			<script type="text/javascript">
					jQuery(document).ready(function($) {
							jQuery(".datepicker").datepicker({
							    format: 'yyyy-mm-dd',
							    startDate: '-3d'
							});
					});
			</script>
			<?php
	}
endif;
