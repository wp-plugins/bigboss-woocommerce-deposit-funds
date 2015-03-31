<?php

/**
 * WC_Account_Funds class.
 * 
 * @extends WC_Payment_Gateway
 */
class WC_Account_Funds extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id           = 'accountfunds';
        $this->method_title = __('Account Funds', 'woocommerce');

        // Support subscriptions
        $this->supports     = array(
            'subscriptions',
            'products',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_payment_method_change',
            'subscription_date_changes'
        );
        
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
        
        $this->title        = $this->settings['title'];
        $wcaf_settings      = get_option( 'wcaf_settings' );
        
        $desc = sprintf( __("Available balance: %s", 'wc_account_funds'), x3m_get_account_funds() );
        
        if ( $wcaf_settings['give_discount'] == 1 && $wcaf_settings['discount_amount'] > 0 ) {
            $desc   .= __('<br/>Use your account funds and get a %s discount on your order', 'wc_account_funds');
            $amount = floatval( $wcaf_settings['discount_amount'] );
            
            if ( $wcaf_settings['discount_type'] == 'fixed' ) {
                $desc = sprintf( $desc, woocommerce_price($amount) );
            } else {
                $desc = sprintf( $desc, $amount .'%' );
            }
        }
        
        $this->description = $desc;
        
        add_action( 'woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options') );
        add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array(&$this, 'process_admin_options') );

        // Subscriptons
        add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );

        // display the current payment method used for a subscription in the "My Subscriptions" table
        add_filter( 'woocommerce_my_subscriptions_recurring_payment_method', array( $this, 'subscription_payment_method_name' ), 10, 3 );
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woothemes' ),
                'type' => 'checkbox',
                'label' => __( 'Enable', 'woothemes' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __( 'Title', 'woothemes' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
                'default' => __( 'Account Funds', 'wc_account_funds' )
            )
        );
    }
    
    public function admin_options() {
    	?>
        <h3><?php _e('Account Funds', 'wc_account_funds'); ?></h3>
        
        <table class="form-table">
        <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
        ?>
        </table>
        <?php
    } // End admin_options()
    
    public function process_payment( $order_id ) {
		global $woocommerce;
        
        $me     = wp_get_current_user();
		$order  = new WC_Order( $order_id );
        
        if ( $me->ID == 0 ) {
            $woocommerce->add_error(__('Payment error:', 'woothemes') . __('You must be logged in to use this payment method', 'wc_account_funds'));
            return;
        }
        
        $funds  = get_user_meta( $me->ID, 'account_funds', true );
        
        if ( !$funds ) {
            $funds = 0;
        }
        
        if ( $funds < $order->order_total ) {
            $woocommerce->add_error(__('Payment error:', 'woothemes') . __('Insufficient account balance', 'wc_account_funds'));
            return;
        }
        
        // Payment complete
        $order->payment_complete();
        
        // deduct amount from account funds
        $new_funds = $funds - $order->order_total;
        update_user_meta( $me->ID, 'account_funds', $new_funds );

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thank you page redirect
        if ( method_exists($order, 'get_checkout_order_received_url') ) {
            return array(
                'result' 	=> 'success',
                'redirect'	=> $order->get_checkout_order_received_url()
            );
        } else {
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))))
            );
        }

	}

    /**
     * @param float $amount
     * @param WC_Order $order
     * @param int $product_id
     * @return bool|WP_Error
     */
    public function scheduled_subscription_payment( $amount, $order, $product_id ) {
        global $x3m_account_funds;

        $order_items        = $order->get_items();
        $product            = $order->get_product_from_item( array_shift( $order_items ) );
        $subscription_name  = sprintf( __( 'Subscription for "%s"', 'wc_account_funds' ), $product->get_title() ) . ' ' . sprintf( __( '(Order %s)', 'wc_account_funds' ), $order->get_order_number() );
        $user_id            = get_post_meta( $order->id, '_customer_user', true );
        $error              = false;

        if ( ! $user_id ) {
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
            return new WP_Error( 'accountfunds', __( 'Customer not found', 'wc_account_funds' ) );
        }

        $funds = $x3m_account_funds->get_account_funds( $user_id, false );

        if ( $amount > $funds ) {
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
            return new WP_Error( 'accountfunds', __( 'Insufficient funds', 'wc_account_funds' ) );
        }

        $funds -= $amount;
        update_user_meta( $user_id, 'account_funds', $funds );

        $order->add_order_note( __('Account Funds subscription payment completed', 'wc_account_funds' ) );
        WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
        return true;

    }

    public function subscription_payment_method_name( $payment_method_to_display, $subscription_details, WC_Order $order ) {
        // bail for other payment methods
        if ( $this->id !== $order->recurring_payment_method || ! $order->customer_user )
            return $payment_method_to_display;

        return sprintf( __( 'Via %s', 'wc_account_funds' ), $this->method_title );
    }
    
}

function add_accountfunds_gateway( $methods ) {
    $methods[] = 'WC_Account_Funds'; 
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_accountfunds_gateway' );