<?php
if (! defined('ABSPATH')) exit;

/**
 * Enqueue separate JS file
 */
add_action('wp_enqueue_scripts', function () {

    if (! class_exists('WooCommerce')) return;

    wp_enqueue_script(
        'location-cart-guard',
        get_stylesheet_directory_uri() . '/js/location-check.js',
        ['jquery'],
        time(),
        true
    );

    $cart_locations = [];
    $cart_data = [];

    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

            $product_id = $cart_item['product_id'];
            $locations = [];

            // 🔹 Get selected variant location
            if (! empty($cart_item['variation'])) {

                foreach ($cart_item['variation'] as $attr_name => $attr_value) {
                    // pick only location attribute (adjust key if needed)
                    if (strpos(strtolower($attr_name), 'location') !== false && $attr_value) {
                        $locations[] = strtolower($attr_value);
                    }
                }
            }

            // 🔹 fallback: use product tags if no variant selected
            if (empty($locations)) {
                $terms = wp_get_post_terms($product_id, 'product_tag');
                if (! is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $term) {
                        $locations[] = strtolower($term->slug);
                    }
                }
            }

            if (! empty($locations)) {
                $cart_locations = array_merge($cart_locations, $locations);
            }

            // 🔹 cart_data for debug or JS use per product
            $cart_data[] = [
                'id'        => $product_id,
                'locations' => $locations,
            ];
        }
    }

    wp_localize_script('location-cart-guard', 'wc_loc_guard', [
        'ajax_url'       => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('wc_loc_guard_nonce'),
        'cart_count'     => WC()->cart->get_cart_contents_count(),
        'cart_locations' => array_values(array_unique($cart_locations)), // selected variant locations only
        'cart_data'      => $cart_data,
        'checkout_url'   => wc_get_checkout_url(),
    ]);
});

/**
 * AJAX: Clear cart only
 */
add_action('wp_ajax_wc_clear_cart_only', 'wc_clear_cart_only');
add_action('wp_ajax_nopriv_wc_clear_cart_only', 'wc_clear_cart_only');

function wc_clear_cart_only() {

    check_ajax_referer( 'wc_loc_guard_nonce', 'nonce' );

    if ( WC()->cart ) {
        WC()->cart->empty_cart();
        WC()->session->set( 'cart', null );
        WC()->session->set( 'cart_totals', null );
    }

    wp_send_json_success();
}