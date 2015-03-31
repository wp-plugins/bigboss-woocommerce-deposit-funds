<?php

class WCAF_Product_Admin {

    protected $fields = null;
    
    public function __construct() {
        // Hooks
        add_filter( 'product_type_selector', array(&$this, 'product_types') );
        add_action( 'woocommerce_process_product_meta_deposit', array(&$this, 'process_product_deposit'), 10 );
        add_action( 'woocommerce_product_write_panels', array( &$this, 'product_write_panel' ) );

        add_filter( 'woocommerce_process_product_meta', array( &$this, 'product_save_data' ) );
        
		/*add_action( 'woocommerce_product_options_product_type', array( &$this, 'is_deposit' ) );*/
    }
    
    public function product_types( $types ) {
        $types['deposit'] = __('Deposit product', 'wc_account_funds');
        return $types;
    }
    
    public function process_product_deposit( $post_id ) {
        $product_type = sanitize_title( stripslashes( $_POST['product-type'] ) );
        
        // Update post meta
        update_post_meta( $post_id, '_regular_price', stripslashes( $_POST['_regular_price'] ) );
        update_post_meta( $post_id, '_sale_price', stripslashes( $_POST['_sale_price'] ) );
        update_post_meta( $post_id, '_tax_status', isset($_POST['_tax_status']) ? stripslashes( $_POST['_tax_status'] ) : '' );
        update_post_meta( $post_id, '_tax_class', isset($_POST['_tax_class']) ? stripslashes( $_POST['_tax_class'] ) : '' );
        update_post_meta( $post_id, '_visibility', stripslashes( $_POST['_visibility'] ) );
        update_post_meta( $post_id, '_purchase_note', stripslashes( $_POST['_purchase_note'] ) );
        if (isset($_POST['_featured'])) update_post_meta( $post_id, '_featured', 'yes' ); else update_post_meta( $post_id, '_featured', 'no' );
        
        // virtual, no weight, lenth, etc
        update_post_meta( $post_id, '_weight', '' );
		update_post_meta( $post_id, '_length', '' );
		update_post_meta( $post_id, '_width', '' );
		update_post_meta( $post_id, '_height', '' );
        
        if ( $product_type == 'deposit' ):
            update_post_meta( $post_id, '_is_deposit', 'yes' );
            update_post_meta( $post_id, '_virtual', 'yes' );
            
            $date_from = (isset($_POST['_sale_price_dates_from'])) ? $_POST['_sale_price_dates_from'] : '';
            $date_to = (isset($_POST['_sale_price_dates_to'])) ? $_POST['_sale_price_dates_to'] : '';
            
            // Dates
            if ($date_from) :
                update_post_meta( $post_id, '_sale_price_dates_from', strtotime($date_from) );
            else :
                update_post_meta( $post_id, '_sale_price_dates_from', '' );	
            endif;
            
            if ($date_to) :
                update_post_meta( $post_id, '_sale_price_dates_to', strtotime($date_to) );
            else :
                update_post_meta( $post_id, '_sale_price_dates_to', '' );	
            endif;
            
            if ($date_to && !$date_from) :
                update_post_meta( $post_id, '_sale_price_dates_from', strtotime('NOW', current_time('timestamp')) );
            endif;

            // Update price if on sale
            if ($_POST['_sale_price'] != '' && $date_to == '' && $date_from == '') :
                update_post_meta( $post_id, '_price', stripslashes($_POST['_sale_price']) );
            else :
                update_post_meta( $post_id, '_price', stripslashes($_POST['_regular_price']) );
            endif;	

            if ($date_from && strtotime($date_from) < strtotime('NOW', current_time('timestamp'))) :
                update_post_meta( $post_id, '_price', stripslashes($_POST['_sale_price']) );
            endif;
            
            if ($date_to && strtotime($date_to) < strtotime('NOW', current_time('timestamp'))) :
                update_post_meta( $post_id, '_price', stripslashes($_POST['_regular_price']) );
                update_post_meta( $post_id, '_sale_price_dates_from', '');
                update_post_meta( $post_id, '_sale_price_dates_to', '');
            endif;
        endif;
    }

    public function product_save_data( $product_id ) {
        if ( $_POST['product-type'] != 'deposit' ) {
            update_post_meta( $product_id, '_is_deposit', 'no' );
        }
    }
    
    public function product_write_panel() {
        global $woocommerce;

        $js = "
        jQuery('select#product-type').change(function(){
            if ( jQuery(this).val() == 'deposit' ) {
                jQuery('.hide_if_virtual').hide();
                jQuery('.show_if_simple').show();
                jQuery('#_virtual').attr('checked', true);
            }
        }).change();

        $('input#_virtual').change(function() {
            if ( jQuery('select#product-type').val() == 'deposit' ) {
                jQuery('.show_if_simple').show();
            }

        });
        ";

        if ( function_exists('wc_enqueue_js') ) {
            wc_enqueue_js( $js );
        } else {
            $woocommerce->add_inline_js( $js );
        }

    }

}
$GLOBALS['WCAF_Product_Admin'] = new WCAF_Product_Admin();