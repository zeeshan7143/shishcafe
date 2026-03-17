<?php
// Add Product Desription to the Orders table
add_action( 'woocommerce_before_order_itemmeta', function( $item_id, $item, $product ) {
    if ( is_admin() && $product ) {
        // If it's a variation, get the parent product
        if ( $product->is_type( 'variation' ) ) {
            $parent_product = wc_get_product( $product->get_parent_id() );
        } else {
            $parent_product = $product;
        }
        if ( $parent_product ) {
            $description = $parent_product->get_short_description();
            if ( empty( $description ) ) {
                $description = $parent_product->get_description(); // Fallback to full description
            }

            if ( ! empty( $description ) ) {
                echo '<div style="margin-top: 5px; font-size: 13px; color: #333;">';
                echo '<strong>Description:</strong> ' . wpautop( wp_kses_post( $description ) );
                echo '</div>';
            }
        }
    }
}, 10, 3 );

// Save product description to order item meta
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    $product = $item->get_product();
    if ($product) {
        $description = $product->get_short_description();
        if (empty($description)) {
            $description = $product->get_description(); // fallback to full description
        }
        if (!empty($description)) {
            $item->add_meta_data('_product_description', $description, true);
        }
    }
}, 10, 4);

?>