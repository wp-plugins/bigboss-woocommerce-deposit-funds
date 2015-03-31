<?php

class X3M_BIGBOSSWCDF_Widget extends WP_Widget {

    function __construct() {
		// Instantiate the parent object
		parent::__construct( 'widget_account_funds', __('My Account Funds', 'wc_account_funds') );
	}

	function widget( $args, $instance ) {
        $me = wp_get_current_user();
        
        if ( $me->ID == 0 ) return;
        
        $funds      = get_user_meta( $me->ID, 'account_funds', true );
        
        if ( empty($funds) ) $funds = 0;
        
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $before_widget;
		if ( ! empty( $title ) )
			echo $before_title . $title . $after_title;
        ?>
		<p><?php printf( __('You currently have <b>%s</b> in your account', 'wc_account_funds'), woocommerce_price($funds) ); ?></p>
        <p style="text-align:center;"><a class="button" href="<?php echo get_permalink( woocommerce_get_page_id('myaccount') ); ?>"><?php _e('Deposit Funds', 'wc_account_funds'); ?></a></p>
        <?php
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'My Account Funds', 'wc_account_funds' );
		}
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php 
	}

}

function wcaf_register_widget() {
	register_widget( 'X3M_BIGBOSSWCDF_Widget' );
}