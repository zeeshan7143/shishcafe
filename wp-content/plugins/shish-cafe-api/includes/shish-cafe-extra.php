<?php
// =======================
// DISABLE CACHING FOR print/v1 routes
// =======================
add_filter('rest_post_dispatch', function ($response, $server, $request) {
    $route = $request->get_route();

    if (strpos($route, '/print/v1/') === 0) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
    }

    return $response;
}, 10, 3);

// =======================
// Show extra meta in admin
// =======================
add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
    $prep_time     = $order->get_meta('_order_prep_time');
    $reject_reason = $order->get_meta('_order_reject_reason');
    $completed = $order->get_meta('_order_status_action');
	
		

    echo '<div class="order-custom-meta" style="margin-top: 20px; display: inline-block;">';
    echo '<h2><strong>Order Status</strong></h2>';

    if ($prep_time) {
        echo '<h3 style="color: green;">Order Accepted</h3>';
        echo '<p><strong>Preparation Time:</strong> ' . esc_html($prep_time) . ' minutes</p>';
    }

    if ($reject_reason) {
        echo '<h3 style="color: red;">Order Rejected</h3>';
        echo '<p><strong>Rejection Reason:</strong> ' . esc_html($reject_reason) . '</p>';
    }
    if ($completed === 'completed') {
        echo '<h3 style="color: green;">Order Completed Successfully.</h3>';
    }
    echo '</div>';
});

// Save custom checkout field (radio: Delivery / Pickup)
// add_action('woocommerce_checkout_update_order_meta', function($order_id) {
//     if (isset($_POST['billing_status_for_delivery'])) {
//         update_post_meta(
//             $order_id,
//             'billing_status_for_delivery',   // meta key
//             sanitize_text_field($_POST['billing_status_for_delivery'])
//         );
//     }
// });
// add_action('woocommerce_admin_order_data_after_billing_address', function($order){
//     $order_type = get_post_meta($order->get_id(), 'billing_status_for_delivery', true);
//     if ($order_type) {
//         echo '<p><strong>Order Type:</strong> ' . ucfirst($order_type) . '</p>';
//     }
// });
//  Show only product name not tags and variaions with titlt at all 
add_filter('woocommerce_order_item_name', function($item_name, $item){
    $product = $item->get_product();
    if ($product) {
        return $product->get_title(); // show only product title
    }
    return $item_name;
}, 10, 2);
?>
