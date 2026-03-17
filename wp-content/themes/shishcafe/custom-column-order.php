<?php
// Add a custom column to the Orders table
// 1. Add Custom Column
add_filter('manage_woocommerce_page_wc-orders_columns', function($columns) {
    $new_columns = [];

    $after_column = 'order_number'; // Change this to the column key you want to insert after

    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;

        if ($column_name === $after_column) {
            $new_columns['custom_order_location'] = 'Location';
        }
    }

    return $new_columns;
}, 20);

// Display the value of the 'location' meta key from product item details
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function( $column, $post_id ) {
    if ( $column === 'custom_order_location' ) {
        $order = wc_get_order( $post_id );
        if ( $order ) {
            $location_data = [];
            foreach ( $order->get_items() as $item ) {
                $meta_data = $item->get_meta_data();
                foreach ( $meta_data as $meta ) {
                    if ( $meta->key === 'location' ) {
                        $location_data[] = esc_html( $meta->value );
                        break; // Found the location for this item, move to the next
                    }
                }
            }

            if ( ! empty( $location_data ) ) {
                $unique_locations = array_unique( $location_data );
                echo implode( ', ', $unique_locations );
            } else {
                echo '—';
            }
        } else {
            echo '—';
        }
    }
}, 10, 2 );
?>