<?php 
// ✅ Add product location (or tag) to cart item meta
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    // Example: If you have a custom field or taxonomy "location"
    $location = get_post_meta($product_id, 'location', true); // from ACF or custom field
    // OR if you want product_tag instead of meta:
    // $terms = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
    // $location = !empty($terms) ? $terms[0] : '';

    if (!empty($location)) {
        $cart_item_data['product_location'] = $location;
    }
    return $cart_item_data;
}, 10, 3);


// ✅ Display location in cart & checkout under product name
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['product_location'])) {
        $item_data[] = [
            'key'   => __('Location', 'woocommerce'),
            'value' => wc_clean($cart_item['product_location']),
        ];
    }
    return $item_data;
}, 10, 2);


// ✅ Save location to order items
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['product_location'])) {
        $item->add_meta_data(__('Location', 'woocommerce'), $values['product_location']);
    }
}, 10, 4);
