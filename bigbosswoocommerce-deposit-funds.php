<?php
/*
Plugin Name: Bigboss WooCommerce deposit funds
Plugin URI: http://wordpress.org/plugins/bigboss-woocomerce-account-funds
Description:Bigboss WooCommerce Account Funds  Allow customers or user to deposit funds into their accounts and they will able to buy using there depost funds
Version: 1.0
Author: Bulbul bigboss
Author URI: http://www.bigbosstheme.com/about-us
Requires at least: 3.5
tag:woo,woo funds, woocommerce deposit, WooCommerce deposit funds,deposit funds,bigboss,bigboss deposit funds, wordpress deposit funds
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 *Bigboss WooCommerce Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
    require_once( 'woo-includes/woo-functions.php' );


if ( is_woocommerce_active() ) {

    /**
     *Bigboss WooCommerce Localisation
     **/
    load_plugin_textdomain( 'wc_account_funds', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    class X3M_AccountFunds {

        function __construct() {
            // Activation
            register_activation_hook( __FILE__, array($this, 'activate') );

            // Includes
            $this->required_files();

            // admin menu
            add_action( 'admin_menu', array($this, 'admin_menu') );

            // checkout page script
            add_action( 'wp_enqueue_scripts', array($this, 'checkout_script') );

            // Admin Settings
            add_action( 'admin_post_wcaf_save_settings', array($this, 'update_settings') );

            // My Account
            add_action( 'woocommerce_before_my_account', array($this, 'my_account') );

            // Add to cart
            add_action( 'woocommerce_deposit_add_to_cart', array($this, 'add_to_cart') );

            // Widget
            add_action( 'widgets_init', 'wcaf_register_widget' );

            // User Admin
            add_filter( 'manage_users_columns', 'wcaf_user_columns', 10, 1 );
            add_action( 'manage_users_custom_column', 'wcaf_user_column_values', 10, 3 );

            add_action( 'show_user_profile', 'wcaf_customer_meta_fields' );
            add_action( 'edit_user_profile', 'wcaf_customer_meta_fields' );
            add_action( 'personal_options_update', 'wcaf_save_customer_meta_fields' );
            add_action( 'edit_user_profile_update', 'wcaf_save_customer_meta_fields' );

            // Order Review AJAX call
            add_action( 'woocommerce_calculate_totals', array($this, 'calculate_totals') );

            // if WC 2.1, display the discount manually
            add_action( 'woocommerce_review_order_before_order_total', array($this, 'display_discount') );

            // checkout validation
            add_action( 'woocommerce_after_checkout_validation', array($this, 'checkout_validation') );

            // completed order
            add_action( 'woocommerce_order_status_completed', array($this, 'order_completed') );

            // shortcode to get the current account funds
            add_shortcode( 'get-account-funds', array($this, 'sc_get_account_funds') );

            // payment gateway
            add_action( 'plugins_loaded', array($this, 'init_gateway'), 0 );
            add_filter( 'woocommerce_available_payment_gateways', array($this, 'available_payment_gateways') );

            // reports
            add_action( 'woocommerce_reports_charts', array($this, 'reports_charts') );

            // pdf invoices
            add_filter( 'woocommerce_pdf_invoice_order_status', array($this, 'skip_sending_invoice'), 10, 2 );
        }

        function required_files() {
            if ( is_admin() ) {
                require_once dirname(__FILE__) .'/class-wcaf-product-admin.php';
                require_once dirname(__FILE__) .'/report-functions.php';
                require_once dirname(__FILE__) .'/user-admin.php';
            }
            require_once dirname(__FILE__) .'/widget.php';
        }

        function activate() {
            global $wpdb, $woocommerce;

            $settings = get_option( 'wcaf_settings', array() );

            if ( empty( $settings ) ) {
                $settings = array(
                    'give_discount'     => 0,
                    'discount_type'     => 'fixed',
                    'discount_amount'   => 0
                );
                update_option( 'wcaf_settings', $settings );
            }
        }

        function admin_menu() {
            add_submenu_page('woocommerce', __('Account Funds', 'wc_account_funds'),  __('Account Funds', 'wc_account_funds') , 'manage_woocommerce', 'wc-account-funds', array($this, 'admin_settings'));
        }

        function admin_settings() {
            include dirname(__FILE__).'/admin_settings.php';
        }

        function update_settings() {
            $_POST = array_map( 'stripslashes_deep', $_POST );

            if ( isset($_POST['give_discount']) && $_POST['give_discount'] == 1 ) {
                if ( $_POST['give_discount'] == 1 ) {
                    $settings = array(
                        'give_discount'     => 1,
                        'discount_type'     => ($_POST['discount_type'] == 'fixed') ? 'fixed' : 'percentage',
                        'discount_amount'   => floatval($_POST['discount_amount'])
                    );
                } else {
                    $settings = array(
                        'give_discount'     => 0,
                        'discount_type'     => 'fixed',
                        'discount_amount'   => 0
                    );
                }
                update_option( 'wcaf_settings', $settings );
            } else {
                $settings = array(
                    'give_discount'     => 0,
                    'discount_type'     => 'fixed',
                    'discount_amount'   => 0
                );
                update_option( 'wcaf_settings', $settings );
            }

            wp_redirect( 'admin.php?page=wc-account-funds&message=1' );
            exit;
        }

        function checkout_script() {
            global $woocommerce;
            $checkout_id    = woocommerce_get_page_id('checkout');
            $enabled        = false;
            $user           = wp_get_current_user();

            if ( is_page($checkout_id) && $user->ID > 0 ) {
                wp_enqueue_script( 'account_funds', plugins_url('wcaf.js', __FILE__), array('jquery','woocommerce') );
            }
        }

        function calculate_totals($cart) {

            if ( isset($_POST['payment_method']) && $_POST['payment_method'] == 'accountfunds' ) {
                $discount_amount = $this->calculate_discount($cart->cart_contents_total);

                if ( $discount_amount ) {

                    // make sure to not add the discount twice (recurring payments)
                    if ( isset($cart->recurring_discount_total) && $cart->recurring_discount_total == $discount_amount ) {
                        return $cart;
                    }

                    $cart->discount_total += $discount_amount;
                }

            }

            return $cart;
        }

        function display_discount() {
            global $woocommerce;

            if ( isset($_POST['payment_method']) && $_POST['payment_method'] == 'accountfunds' ) {
                $discount_amount = $this->calculate_discount($woocommerce->cart->cart_contents_total);

                if ( $discount_amount > 0 ) {
            ?>
            <tr class="order-discount account-funds-discount">
                <th><?php _e('Order Discount'); ?></th>
                <td>-<?php echo woocommerce_price( $discount_amount ); ?></td>
            </tr>
            <?php
                }
            }
        }

        function calculate_discount( $total_amount ) {
            $discount   = 0;
            $settings   = get_option( 'wcaf_settings' );

            if ( $settings['give_discount'] == 1 && $settings['discount_amount'] > 0 ) {
                $amount = floatval( $settings['discount_amount'] );

                if ( $settings['discount_type'] == 'fixed' ) {
                    $discount = $amount;
                } else {
                    $deduct     = $total_amount * ($amount / 100);
                    $discount   = $deduct;
                }
            }

            return $discount;
        }

        function checkout_validation( $posted ) {
            global $woocommerce;

            $order_has_deposit = false;

            if (! is_user_logged_in() && empty($posted['createaccount']) ) {

                foreach ( $woocommerce->cart->get_cart() as $item ) {
                    $_product = self::get_product( $item['product_id'] );

                    if ( self::product_is_deposit($_product) ) {
                        $order_has_deposit = true;
                        break;
                    }
                }

                if ( $order_has_deposit ) {
                    if ( function_exists('wc_add_notice') ) {
                        wc_add_notice( __('You cannot deposit funds without an active account', 'wc_account_funds'), 'error' );
                    } else {
                        $woocommerce->add_error( __('You cannot deposit funds without an active account', 'wc_account_funds') );
                    }
                }

            }

        }

        function order_completed($order_id) {
            global $wpdb, $woocommerce;

            $order          = new WC_Order($order_id);
            $items          = $order->get_items();
            $customer_id    = $order->user_id;

            if ( ! get_post_meta( $order_id, '_funds_deposited', true ) ) {
                if ( $customer_id <= 0 ) return;

                $user = new WP_User( $customer_id );

                foreach ( $items as $item ) {
                    $item_id = isset( $item['product_id'] ) ? $item['product_id'] : $item['id'];
                    $product = X3M_AccountFunds::get_product( $item_id );

                    if ( X3M_AccountFunds::product_is_deposit( $product ) ) {
                        // get the quantity
                        $qty = $item['qty'];

                        // add to the user's current funds
                        $funds = get_user_meta( $user->ID, 'account_funds', true );

                        if (! $funds ) {
                            $funds = 0;
                        }

                        $funds += $product->get_price() * $qty;
                        update_user_meta( $user->ID, 'account_funds', $funds );
                        update_post_meta( $order_id, '_funds_deposited', 1 );
                    }
                }
            }
        }

        function available_payment_gateways($gateways) {
            global $woocommerce;

            $cart       = $woocommerce->cart;
            $me         = wp_get_current_user();

            if ( isset($gateways['accountfunds']) ) {
                if ( $me->ID == 0 ) {
                    unset( $gateways['accountfunds'] );
                    return $gateways;
                }

                // make sure we aren't depositing funds into our account, using funds from our account
                foreach ( $cart->cart_contents as $data ) {
                    $product = X3M_AccountFunds::get_product( $data['product_id'] );

                    if ( X3M_AccountFunds::product_is_deposit($product) ) {
                        unset( $gateways['accountfunds'] );
                        return $gateways;
                    }
                }

                $funds = $this->get_account_funds( $me->ID, false );

                // get the real total, including any discounts that customers will be getting
                // if they pay using account funds
                $cart->calculate_totals();
                $cart_total = $cart->total;

                $settings = get_option( 'wcaf_settings' );

                if ( $settings['give_discount'] == 1 && $settings['discount_amount'] > 0 ) {
                    $amount = floatval( $settings['discount_amount'] );

                    if ( $settings['discount_type'] == 'fixed' ) {
                        $cart_total -= $amount;
                    } else {
                        $deduct = $cart_total * ($amount / 100);
                        $cart_total -= $deduct;
                    }
                }

                if ( $funds >= $cart_total ) {
                    $gateways['accountfunds']->title = $gateways['accountfunds']->settings['title'];
                    return $gateways;
                } else {
                    unset( $gateways['accountfunds'] );
                    return $gateways;
                }
            }
            return $gateways;
        }

        function add_to_cart() {
            woocommerce_simple_add_to_cart();
        }

        function my_account() {
            global $product;

            $funds  = $this->get_account_funds();
            $args   = array(
                'post_type'     => 'product',
                'meta_query'    => array(
                    array(
                        'key'   => '_is_deposit',
                        'value' => 'yes',
                    )
                )
            );

            query_posts( $args );

            echo '<h2>'. __('Account Funds', 'wc_account_funds') .'</h2>';
            echo '<p>'. sprintf( __('You currently have <b>%s</b> in your account.', 'wc_account_funds'), $funds ) .'</p>';

            if ( have_posts() ) :
                do_action('woocommerce_before_shop_loop');
            ?>
                <ul class="products">
                    <?php woocommerce_product_subcategories(); ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <?php woocommerce_get_template_part( 'content', 'product' ); ?>
                    <?php
                    endwhile; // end of the loop.

                    // Reset Post Data
                    wp_reset_query();
                    ?>
                </ul>

            <?php
                do_action('woocommerce_after_shop_loop');
            endif;

            $this->my_account_orders();
        }

        function my_account_orders() {
            global $woocommerce;

            $customer_id = get_current_user_id();

            if ( version_compare( WC_VERSION, '2.2.0', '<' ) ) {
                $args = array(
                    'numberposts' => 50,
                    'meta_key'    => '_customer_user',
                    'meta_value'  => $customer_id,
                    'post_type'   => 'shop_order',
                    'meta_query'  => array(
                        array(
                            'key' => '_funds_deposited',
                            'value' => '1',
                        )
                    )
                );
            } else {
                $args = array(
                    'numberposts' => 50,
                    'meta_key'    => '_customer_user',
                    'meta_value'  => $customer_id,
                    'post_type'   => 'shop_order',
                    'post_status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
                    'meta_query'  => array(
                        array(
                            'key' => '_funds_deposited',
                            'value' => '1',
                        )
                    )
                );
            }
            $deposits = get_posts($args);
            ?>
            <h2><?php _e('Recent Deposits', 'wc_account_funds'); ?></h2>
            <?php if ($deposits) : ?>
            <table class="shop_table my_account_deposits">

                <thead>
                    <tr>
                        <th class="order-number"><span class="nobr"><?php _e('Order', 'woocommerce'); ?></span></th>
                        <th class="order-date"><span class="nobr"><?php _e('Date', 'woocommerce'); ?></span></th>
                        <th class="order-total"><span class="nobr"><?php _e('Total', 'woocommerce'); ?></span></th>
                        <th class="order-status" colspan="2"><span class="nobr"><?php _e('Status', 'woocommerce'); ?></span></th>
                    </tr>
                </thead>

                <tbody><?php
                    foreach ($deposits as $deposit) :
                        $order = new WC_Order();

                        $order->populate( $deposit );

                        $status = get_term_by('slug', $order->status, 'shop_order_status');

                        ?><tr class="order">
                            <td class="order-number" width="1%">
                                <a href="<?php echo esc_url( add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('view_order'))) ); ?>"><?php echo $order->get_order_number(); ?></a>
                            </td>
                            <td class="order-date"><time title="<?php echo esc_attr( strtotime($order->order_date) ); ?>"><?php echo date_i18n(get_option('date_format'), strtotime($order->order_date)); ?></time></td>
                            <td class="order-total" width="1%"><?php echo $order->get_formatted_order_total(); ?></td>
                            <td class="order-status" style="text-align:left; white-space:nowrap;">
                                <?php echo ucfirst( __( $status->name, 'woocommerce' ) ); ?>
                                <?php if (in_array($order->status, array('pending', 'failed'))) : ?>
                                    <a href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>" class="cancel" title="<?php _e('Click to cancel this order', 'woocommerce'); ?>">(<?php _e('Cancel', 'woocommerce'); ?>)</a>
                                <?php endif; ?>
                            </td>
                            <td class="order-actions" style="text-align:right; white-space:nowrap;">

                                <?php if (in_array($order->status, array('pending', 'failed'))) : ?>
                                    <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php _e('Pay', 'woocommerce'); ?></a>
                                <?php endif; ?>

                                <a href="<?php echo esc_url( add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('view_order'))) ); ?>" class="button"><?php _e('View', 'woocommerce'); ?></a>


                            </td>
                        </tr><?php
                    endforeach;
                ?></tbody>

            </table>
            <?php
            else :
            ?>
            <p><?php _e('You have no recent deposits.', 'wc_account_funds'); ?></p>
            <?php
            endif;
        }

        function sc_get_account_funds() {
            $me = wp_get_current_user();
            return $this->get_account_funds( $me->ID );
        }

        function get_account_funds( $user_id = null, $formatted = true ) {
            if ( is_null( $user_id ) ) $user_id = get_current_user_id();

            if ( !$user_id || $user_id == 0 ) return 0;

            $user   = new WP_User( $user_id );
            $funds  = get_user_meta( $user->ID, 'account_funds', true );

            if ( !$funds ) {
                $funds = 0;
            }

            if ( $formatted ) {
                $funds = woocommerce_price($funds);
            }

            return $funds;
        }

        function init_gateway() {
            if ( ! class_exists( 'wc_payment_gateway' ) ) { return; }
            require_once dirname( __FILE__ ) .'/Bigboss-WooCommerce-deposit-funds-getaways.php';
        }

        function reports_charts($charts) {
            $charts['deposits'] = array(
                'title'     => __('Deposits', 'wc_account_funds'),
                'charts'    => array(
                    array(
                        'title' => __('Overview', 'wc_account_funds'),
                        'description' => '',
                        'hide_title' => true,
                        'function' => 'wcaf_report_overview'
                    ),
                    array(
                        'title' => __('Deposits by Day', 'wc_account_funds'),
                        'description' => '',
                        'function' => 'wcaf_report_daily'
                    ),
                    array(
                        'title' => __('Deposits by Month', 'wc_account_funds'),
                        'description' => '',
                        'function' => 'wcaf_report_monthly'
                    )
                )
            );

            return $charts;
        }

        public function skip_sending_invoice( $order_statuses, $order_id ) {
            $order = new WC_Order($order_id);

            // if this order is to deposit funds, skip sending of invoice
            $deposit_product = false;

            foreach ( $order->get_items() as $item ) {
                $_product = X3M_AccountFunds::get_product( $item['product_id'] );

                if ( X3M_AccountFunds::product_is_deposit($_product) ) {
                    $deposit_product = true;
                    break;
                }
            }

            if ( $deposit_product ) {
                return array();
            }

            return $order_statuses;
        }

        public static function get_product( $id ) {
            if ( function_exists('get_product') ) {
                return get_product($id);
            } else {
                return new WC_Product($id);
            }
        }

        public static function is_wc2() {
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
                return false;
            } else {
                return true;
            }
        }

        public static function product_is_deposit( $product ) {
            $is_deposit = get_post_meta( $product->id, '_is_deposit', true );

            if ( $is_deposit && $is_deposit == 'yes' ) return true;

            return false;
        }

    }


    $x3m_account_funds = new X3M_AccountFunds();

    function x3m_get_account_funds() {
        global $x3m_account_funds;
        $me = wp_get_current_user();

        return $x3m_account_funds->get_account_funds( $me->ID );
    }

}
