<?php

function wcaf_report_overview() {
	global $start_date, $end_date, $woocommerce, $wpdb;

	$times_deposit     = 0;
	$times_used         = 0;
    $amount_deposited   = 0;
	$amount_used        = 0;

	if ( version_compare( WC_VERSION, '2.2.0', '<' ) ) {
		$args = array(
			'numberposts' => -1,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
			'post_type'   => 'shop_order',
			'meta_query'  => array(
				array(
					'key'   => '_funds_deposited',
					'value' => '1',
				)
			),
			'tax_query' => array(
				array(
					'taxonomy' => 'shop_order_status',
					'terms'    => array( 'completed', 'processing', 'on-hold' ),
					'field'    => 'slug',
					'operator' => 'IN'
				)
			)
		);
	} else {
		$args = array(
			'numberposts' => -1,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
			'post_type'   => 'shop_order',
			'post_status' => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
			'meta_query'  => array(
				array(
					'key'   => '_funds_deposited',
					'value' => '1',
				)
			)
		);
	}

	$orders = get_posts( $args );

	foreach ($orders as $order) :
        $order_obj          = new WC_Order( $order->ID );
		$order_items_array  = $order_obj->get_items();

		foreach ($order_items_array as $item) {
			$item_id = (isset($item['product_id'])) ? $item['product_id'] : $item['id'];
            $product = X3M_AccountFunds::get_product($item_id);
            $is_deposit = get_post_meta( $item_id, '_is_deposit', true );

            if ( $is_deposit == 'yes' ) {
                $times_deposit++;
                $amount_deposited += $product->get_price() * $item['qty'];
            }
        }
	endforeach;
	?>
	<div id="poststuff" class="woocommerce-reports-wrap halved">
		<div class="woocommerce-reports-left">
			<div class="postbox">
				<h3><span><?php _e('Total Deposits', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($amount_deposited > 0) echo woocommerce_price($amount_deposited); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>

            <div class="postbox">
				<h3><span><?php _e('Number of Deposits', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($times_deposit>0) echo $times_deposit; else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
		</div>
		<div class="woocommerce-reports-right">
			<div class="postbox">
				<h3><span><?php _e('Average order deposit', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($times_deposit>0) echo woocommerce_price($amount_deposited/$times_deposit); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function wcaf_report_daily() {
    global $start_date, $end_date, $woocommerce, $wpdb;

	$start_date = (isset($_POST['start_date'])) ? $_POST['start_date'] : '';
	$end_date	= (isset($_POST['end_date'])) ? $_POST['end_date'] : '';

	if (!$start_date) $start_date = date('Ymd', strtotime( date('Ym', current_time('timestamp')).'01' ));
	if (!$end_date) $end_date = date('Ymd', current_time('timestamp'));

	$start_date = strtotime($start_date);
	$end_date = strtotime($end_date);

	$amounts_deposited  = 0;
	$num_deposits       = 0;

	// Get orders to display in widget
	//add_filter( 'posts_where', 'orders_within_range' );

	if ( version_compare( WC_VERSION, '2.2.0', '<' ) ) {
		$args = array(
		    'numberposts'     => -1,
		    'orderby'         => 'post_date',
		    'order'           => 'ASC',
		    'post_type'       => 'shop_order',
		    'suppress_filters'=> 0,
	        'meta_query'        => array(
	                                    array(
	                                        'key' => '_funds_deposited',
	                                        'value' => '1',
	                                    )
	                                ),
		    'tax_query' => array(
		    	array(
			    	'taxonomy' => 'shop_order_status',
					'terms' => array('completed', 'processing', 'on-hold'),
					'field' => 'slug',
					'operator' => 'IN'
				)
		    )
		);
	} else {
		$args = array(
			'numberposts'      => -1,
			'orderby'          => 'post_date',
			'order'            => 'ASC',
			'post_type'        => 'shop_order',
			'post_status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
			'suppress_filters' => 0,
			'meta_query'       => array(
                array(
                    'key' => '_funds_deposited',
                    'value' => '1',
                )
            )
		);
	}
	$orders = get_posts( $args );

	$deposit_counts     = array();
	$deposit_amounts    = array();

	// Blank date ranges to begin
	$count = 0;
	$days = ($end_date - $start_date) / (60 * 60 * 24);
	if ($days==0) $days = 1;

	while ($count < $days) :
		$time = strtotime(date('Ymd', strtotime('+ '.$count.' DAY', $start_date))).'000';

		$deposit_counts[$time] = 0;
		$deposit_amounts[$time] = 0;

		$count++;
	endwhile;

	if ($orders) :
		foreach ($orders as $order) :
            $order_obj = new WC_Order( $order->ID );
            $order_items_array = $order_obj->get_items();

            $time = strtotime(date('Ymd', strtotime($order->post_date))) .'000';

            foreach ($order_items_array as $item) {
                $item_id    = (isset($item['product_id'])) ? $item['product_id'] : $item['id'];
                $product    = X3M_AccountFunds::get_product( $item_id );
                $is_deposit = get_post_meta( $item_id, '_is_deposit', true);

                if ( $is_deposit == 'yes' ) {
                    $num_deposits++;
                    $amounts_deposited += $product->get_price() * $item['qty'];

                    if (isset($deposit_counts[$time])) :
                        $deposit_counts[$time]++;
                    else :
                        $deposit_counts[$time] = 1;
                    endif;

                    if (isset($deposit_amounts[$time])) :
                        $deposit_amounts[$time] = $deposit_amounts[$time] + ($product->get_price() * $item['qty']);
                    else :
                        $deposit_amounts[$time] = $product->get_price() * $item['qty'];
                    endif;
                }
                continue 2;
            }
		endforeach;
	endif;

	?>
	<form method="post" action="">
		<p><label for="from"><?php _e('From:', 'woocommerce'); ?></label> <input type="text" name="start_date" id="from" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $start_date) ); ?>" /> <label for="to"><?php _e('To:', 'woocommerce'); ?></label> <input type="text" name="end_date" id="to" readonly="readonly" value="<?php echo esc_attr( date('Y-m-d', $end_date) ); ?>" /> <input type="submit" class="button" value="<?php _e('Show', 'woocommerce'); ?>" /></p>
	</form>

	<div id="poststuff" class="woocommerce-reports-wrap">
		<div class="woocommerce-reports-sidebar">
			<div class="postbox">
				<h3><span><?php _e('Total deposits in range', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($amounts_deposited>0) echo woocommerce_price($amounts_deposited); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
			<div class="postbox">
				<h3><span><?php _e('Total deposits in range', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($num_deposits>0) echo $num_deposits; else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
			<div class="postbox">
				<h3><span><?php _e('Average deposit in range', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($amounts_deposited>0) echo woocommerce_price($amounts_deposited/$num_deposits); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
		</div>
		<div class="woocommerce-reports-main">
			<div class="postbox">
				<h3><span><?php _e('Deposits in range', 'wc_account_funds'); ?></span></h3>
				<div class="inside chart">
					<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
				</div>
			</div>
		</div>
	</div>
	<?php

	$deposit_counts_array = array();
	foreach ($deposit_counts as $key => $count) :
		$deposit_counts_array[] = array($key, $count);
	endforeach;

	$deposit_amounts_array = array();
	foreach ($deposit_amounts as $key => $amount) :
		$deposit_amounts_array[] = array($key, $amount);
	endforeach;

	$deposit_data = array( 'deposit_counts' => $deposit_counts_array, 'deposit_amounts' => $deposit_amounts_array );

	$chart_data = json_encode($deposit_data);
	?>
	<script type="text/javascript">
		jQuery(function(){
			var deposit_data = jQuery.parseJSON( '<?php echo $chart_data; ?>' );
            console.log(deposit_data);
			var d = deposit_data.deposit_counts;
		    var d2 = deposit_data.deposit_amounts;

			for (var i = 0; i < d.length; ++i) d[i][0] += 60 * 60 * 1000;
		    for (var i = 0; i < d2.length; ++i) d2[i][0] += 60 * 60 * 1000;

			var placeholder = jQuery("#placeholder");

			var plot = jQuery.plot(placeholder, [ { label: "Number of deposits", data: d }, { label: "Deposit amount", data: d2, yaxis: 2 } ], {
				series: {
					lines: { show: true },
					points: { show: true }
				},
				grid: {
					show: true,
					aboveData: false,
					color: '#ccc',
					backgroundColor: '#fff',
					borderWidth: 2,
					borderColor: '#ccc',
					clickable: false,
					hoverable: true,
					markings: weekendAreas
				},
				xaxis: {
					mode: "time",
					timeformat: "%d %b",
					tickLength: 1,
					minTickSize: [1, "day"]
				},
				yaxes: [ { min: 0, tickSize: 10, tickDecimals: 0 }, { position: "right", min: 0, tickDecimals: 2 } ],
		   		colors: ["#8a4b75", "#47a03e"]
		 	});

		 	placeholder.resize();

			<?php woocommerce_weekend_area_js(); ?>
			<?php woocommerce_tooltip_js(); ?>
			<?php woocommerce_datepicker_js(); ?>
		});
	</script>
	<?php
}

function wcaf_report_monthly() {
    global $start_date, $end_date, $woocommerce, $wpdb;

	$first_year = $wpdb->get_var("SELECT post_date FROM $wpdb->posts ORDER BY post_date ASC LIMIT 1;");
	if ($first_year) $first_year = date('Y', strtotime($first_year)); else $first_year = date('Y');

	$current_year = (isset($_POST['show_year'])) ? $_POST['show_year'] : date('Y', current_time('timestamp'));

	$start_date = (isset($_POST['start_date'])) ? $_POST['start_date'] : '';
	$end_date	= (isset($_POST['end_date'])) ? $_POST['end_date'] : '';

	if (!$start_date) $start_date = $current_year.'0101';
	if (!$end_date) $end_date = date('Ym', current_time('timestamp')).'31';

	$start_date = strtotime($start_date);
	$end_date = strtotime($end_date);

	$amounts_deposited  = 0;
	$num_deposits       = 0;

	if ( version_compare( WC_VERSION, '2.2.0', '<' ) ) {
		$args = array(
		    'numberposts'     => -1,
		    'orderby'         => 'post_date',
		    'order'           => 'ASC',
		    'post_type'       => 'shop_order',
		    'suppress_filters'=> 0,
	        'meta_query'        => array(
	                                    array(
	                                        'key' => '_funds_deposited',
	                                        'value' => '1',
	                                    )
	                                ),
		    'tax_query' => array(
		    	array(
			    	'taxonomy' => 'shop_order_status',
					'terms' => array('completed', 'processing', 'on-hold'),
					'field' => 'slug',
					'operator' => 'IN'
				)
		    )
		);
	} else {
		$args = array(
			'numberposts'      => -1,
			'orderby'          => 'post_date',
			'order'            => 'ASC',
			'post_type'        => 'shop_order',
			'post_status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold' ),
			'suppress_filters' => 0,
			'meta_query'       => array(
                array(
                    'key' => '_funds_deposited',
                    'value' => '1',
                )
            )
		);
	}
	$orders = get_posts( $args );

	$deposit_counts = array();
	$deposit_amounts = array();

	// Blank date ranges to begin
	$count = 0;
	$months = ($end_date - $start_date) / (60 * 60 * 24 * 7 * 4);

	while ($count < $months) :
		$time = strtotime(date('Ym', strtotime('+ '.$count.' MONTH', $start_date)).'01').'000';

		$deposit_counts[$time] = 0;
		$deposit_amounts[$time] = 0;

		$count++;
	endwhile;

	if ($orders) :
		foreach ($orders as $order) :
            $order_obj = new WC_Order( $order->ID );
			$time = strtotime(date('Ym', strtotime($order->post_date)).'01').'000';
			$order_items_array = $order_obj->get_items();

			foreach ($order_items_array as $item) {
				$item_id    = (isset($item['product_id'])) ? $item['product_id'] : $item['id'];
                $product    = X3M_AccountFunds::get_product( $item_id );
                $is_deposit = get_post_meta( $item_id, '_is_deposit', true );

                if ( $is_deposit == 'yes' ) {
                    $num_deposits++;
                    $amounts_deposited += $product->get_price() * $item['qty'];

                    if (isset($deposit_counts[$time])) :
                        $deposit_counts[$time]++;
                    else :
                        $deposit_counts[$time] = 1;
                    endif;

                    if (isset($deposit_amounts[$time])) :
                        $deposit_amounts[$time] = $deposit_amounts[$time] + ($product->get_price() * $item['qty']);
                    else :
                        $deposit_amounts[$time] = $product->get_price() * $item['qty'];
                    endif;
                }
            }
		endforeach;
	endif;

	?>
	<form method="post" action="">
		<p><label for="show_year"><?php _e('Year:', 'woocommerce'); ?></label>
		<select name="show_year" id="show_year">
			<?php
				for ($i = $first_year; $i <= date('Y'); $i++) printf('<option value="%s" %s>%s</option>', $i, selected($current_year, $i, false), $i);
			?>
		</select> <input type="submit" class="button" value="<?php _e('Show', 'woocommerce'); ?>" /></p>
	</form>
	<div id="poststuff" class="woocommerce-reports-wrap">
		<div class="woocommerce-reports-sidebar">
			<div class="postbox">
				<h3><span><?php _e('Total deposits for year', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($amounts_deposited>0) echo woocommerce_price($amounts_deposited); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
			<div class="postbox">
				<h3><span><?php _e('Number of deposits for year', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($num_deposits>0) echo $num_deposits; else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
			<div class="postbox">
				<h3><span><?php _e('Average deposit for year', 'wc_account_funds'); ?></span></h3>
				<div class="inside">
					<p class="stat"><?php if ($amounts_deposited>0) echo woocommerce_price($amounts_deposited/$num_deposits); else _e('n/a', 'woocommerce'); ?></p>
				</div>
			</div>
		</div>
		<div class="woocommerce-reports-main">
			<div class="postbox">
				<h3><span><?php _e('Monthly deposits for year', 'wc_account_funds'); ?></span></h3>
				<div class="inside chart">
					<div id="placeholder" style="width:100%; overflow:hidden; height:568px; position:relative;"></div>
				</div>
			</div>
		</div>
	</div>
	<?php

	$deposit_counts_array = array();
	foreach ($deposit_counts as $key => $count) :
		$deposit_counts_array[] = array($key, $count);
	endforeach;

	$deposit_amounts_array = array();
	foreach ($deposit_amounts as $key => $amount) :
		$deposit_amounts_array[] = array($key, $amount);
	endforeach;

	$deposit_data = array( 'deposit_counts' => $deposit_counts_array, 'deposit_amounts' => $deposit_amounts_array );

	$chart_data = json_encode($deposit_data);
	?>
	<script type="text/javascript">
		jQuery(function(){
			var deposit_data = jQuery.parseJSON( '<?php echo $chart_data; ?>' );

			var d = deposit_data.deposit_counts;
			var d2 = deposit_data.deposit_amounts;

			var placeholder = jQuery("#placeholder");

			var plot = jQuery.plot(placeholder, [ { label: "Number of deposits", data: d }, { label: "Deposit amount", data: d2, yaxis: 2 } ], {
				series: {
					lines: { show: true },
					points: { show: true, align: "left" }
				},
				grid: {
					show: true,
					aboveData: false,
					color: '#ccc',
					backgroundColor: '#fff',
					borderWidth: 2,
					borderColor: '#ccc',
					clickable: false,
					hoverable: true
				},
				xaxis: {
					mode: "time",
					timeformat: "%b %y",
					tickLength: 1,
					minTickSize: [1, "month"]
				},
				yaxes: [ { min: 0, tickSize: 10, tickDecimals: 0 }, { position: "right", min: 0, tickDecimals: 2 } ],
		   		colors: ["#8a4b75", "#47a03e"]
		 	});

		 	placeholder.resize();

			<?php woocommerce_tooltip_js(); ?>
		});
	</script>
	<?php
}