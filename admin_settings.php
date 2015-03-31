<?php
$settings = get_option( 'wcaf_settings' );
?>
<div class="wrap woocommerce">
    <div id="icon-options-general" class="icon32"><br></div>
    <h2 class="">
    	<?php _e( 'Account Funds Settings', 'wc_account_funds' ); ?>
    </h2>
    
    <?php 
    if ( isset($_GET['message']) ):
        if ( $_GET['message'] == 1 ):
    ?>
    <div class="message updated"><p><?php _e('Settings updated', 'wc_account_funds'); ?></p></div>
    <?php
        endif; // message == 1
    endif;
    ?>
    
    <form action="admin-post.php" method="post">
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row" colspan="2">
                        <label for="apply_discount">
                            <input name="give_discount" type="checkbox" id="apply_discount" value="1" <?php if ($settings['give_discount'] == 1) echo 'checked'; ?> />
                            <?php _e('Apply a discount when Account Funds is used to purchase items', 'wc_account_funds'); ?>
                        </label>
                    </th>
                </tr>
                <tr valign="top" class="use_discounts">
                    <th scope="row">
                        <label for="discount_type"><?php _e('Discount Type', 'wc_account_funds'); ?></label>
                    </th>
                    <td>
                        <select name="discount_type" id="discount_type">
                            <option value="fixed" <?php if ($settings['discount_type'] == 'fixed') echo 'selected'; ?>><?php _e('Fixed Price', 'wc_account_funds'); ?></option>
                            <option value="percentage" <?php if ($settings['discount_type'] == 'percentage') echo 'selected'; ?>><?php _e('Percentage', 'wc_account_funds'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top" class="use_discounts">
                    <th scope="row">
                        <label for="discount_amount"><?php _e('Discount Amount', 'wc_account_funds'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="discount_amount" id="discount_amount" value="<?php echo esc_attr(floatval($settings['discount_amount'])); ?>" class="small-text" placeholder="e.g. 5" />
                        <span class="description"><?php _e('Enter the numbers only. Do not include the percentage sign.', 'wc_account_funds'); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="hidden" name="action" value="wcaf_save_settings" />
            <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Save Changes', 'wc_account_funds'); ?>">
        </p>
    </form>
</div>
<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery("#apply_discount").change(function() {
        if (jQuery(this).attr("checked")) {
            jQuery(".use_discounts").show();
        } else {
            jQuery(".use_discounts").hide();
        }
    });
    
    if (jQuery("#apply_discount").attr("checked")) {
        jQuery(".use_discounts").show();
    } else {
        jQuery(".use_discounts").hide();
    }
});
</script>