<?php
/**
 * Functions used for modifying the users panel
 */

function wcaf_user_columns( $columns ) {
	if (!current_user_can('manage_woocommerce')) return $columns;

	$columns['wcaf_funds'] = __('Account Funds', 'wc_account_funds');
	return $columns;
}

function wcaf_user_column_values($value, $column_name, $user_id) {
	global $woocommerce, $wpdb;
    
    if ( $column_name == 'wcaf_funds' ) {
        $funds = get_user_meta( $user_id, 'account_funds', true );
        
        if ( empty($funds) ) $funds = 0;
        $value = woocommerce_price($funds);
    }
    
    return $value;
}

function wcaf_customer_meta_fields( $user ) { 
	if (!current_user_can('manage_woocommerce')) return $columns;
    $funds = get_user_meta( $user->ID, 'account_funds', true );
    $funds = (empty($funds)) ? 0 : $funds;
    ?>
		<h3><?php _e('Account Funds'); ?></h3>
		<table class="form-table">
			<tr>
                <th><label for="account_funds"><?php _e('Account Funds'); ?></label></th>
                <td>
                    <input type="text" name="account_funds" id="account_funds" value="<?php echo esc_attr( $funds ); ?>" class="small-text" /><br/>
                    <span class="description"><?php _e('Funds this user can use to purchase items', 'wc_account_funds'); ?></span>
                </td>
            </tr>
		</table>
		<?php
}

function wcaf_save_customer_meta_fields( $user_id ) {
	if (!current_user_can('manage_woocommerce')) return $columns;
 	
    if (isset($_POST['account_funds'])) update_user_meta( $user_id, 'account_funds', trim(esc_attr( $_POST['account_funds'] )) );
}