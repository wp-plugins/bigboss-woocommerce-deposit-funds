jQuery(document).ready(function() {
    jQuery("input[name=payment_method]").live("change", function() {
        jQuery('body').trigger('update_checkout');
    });
});