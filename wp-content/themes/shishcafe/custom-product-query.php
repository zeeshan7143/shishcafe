<?php
// function custom_products_with_location_pricing($atts) {
//     $atts = shortcode_atts(
//         array(
//             'category' => '',
//             'tag'      => '',
//             'limit'    => 12,
//             'orderby'  => 'date',
//             'order'    => 'DESC',
//         ),
//         $atts,
//         'custom_products'
//     );

//     $args = array(
//         'post_type'      => 'product',
//         'posts_per_page' => intval($atts['limit']),
//         'tax_query'      => array(),
//         'orderby'        => sanitize_text_field($atts['orderby']),
//         'order'          => strtoupper(sanitize_text_field($atts['order'])),
//     );

//     if (!empty($atts['category'])) {
//         $args['tax_query'][] = array(
//             'taxonomy' => 'product_cat',
//             'field'    => 'slug',
//             'terms'    => explode(',', $atts['category']),
//         );
//     }

//     if (!empty($atts['tag'])) {
//         $args['tax_query'][] = array(
//             'taxonomy' => 'product_tag',
//             'field'    => 'slug',
//             'terms'    => explode(',', $atts['tag']),
//         );
//     }

//     $query = new WP_Query($args);
//     ob_start();

//     $location_prices = [];
//     $shortcode_id = uniqid('custom_products_'); // Unique ID for each shortcode instance

//     if ($query->have_posts()) {
//         echo '<div class="custom-product-woocommerce" id="' . esc_attr($shortcode_id) . '">';
//         echo '<ul class="custom-product-query">';

//         while ($query->have_posts()) {
//             $query->the_post();
//             global $product;
//             $product_id = get_the_ID();

//             if ($product->is_type('variable')) {
//                 $variations = $product->get_available_variations();

//                 foreach ($variations as $variation) {
//                     $location = $variation['attributes']['attribute_location'] ?? null;
//                     $price = $variation['display_price'];

//                     if ($location) {
//                         if (!isset($location_prices[$product_id])) {
//                             $location_prices[$product_id] = [];
//                         }
//                         if (!isset($location_prices[$product_id][$location])) {
//                             $location_prices[$product_id][$location] = [];
//                         }
//                         $location_prices[$product_id][$location][] = $price;
//                     }
//                 }
//             }

//             echo '<li class="custom-product" data-product-id="' . esc_attr($product_id) . '" data-shortcode-id="' . esc_attr($shortcode_id) . '">';
//             echo '<div class="custom-product-image">' . get_the_post_thumbnail($product_id, 'medium') . '</div>';
//             echo '<div class="custom-product-content">';
//             echo '<h2 class="custom-product-title"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h2>';
//                         echo '<div class="custom-product-price">';
//             echo '<span class="price woocommerce-Price-amount amount"><bdi>' . $product->get_price_html() . '</bdi></span>';
//             echo '</div>';
//             echo '</div>';
//             echo '</li>';
//         }

//         echo '</ul>';
//         echo '</div>';
//     } else {
//         echo '<p>No products found.</p>';
//     }

//     wp_reset_postdata();

//     $currency_symbol = get_woocommerce_currency_symbol();

//     // Enqueue script only once
//     static $script_enqueued = false;
//     if (!$script_enqueued) {
//         wp_enqueue_script('custom-product-query', get_template_directory_uri() . '/js/customproductquery.js', array('jquery'), null, true);
//         $script_enqueued = true;
//     }

//     // Pass data specific to this shortcode instance
//     wp_localize_script('custom-product-query', $shortcode_id . '_data', [
//         'prices'   => $location_prices,
//         'currency' => $currency_symbol,
//         'shortcodeId' => $shortcode_id
//     ]);

//     return ob_get_clean();
// }

// add_shortcode('custom_products', 'custom_products_with_location_pricing');

// New Code 

function custom_products_with_location_pricing($atts)
{
    $atts = shortcode_atts(
        array(
            'category' => '',
            'tag'      => '',
            'limit'    => 12,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ),
        $atts,
        'custom_products'
    );

    // ✅ Added: Use cookie as tag if shortcode tag is empty
//     if (empty($atts['tag']) && isset($_COOKIE['selectedLocationCookie'])) {
//         $cookie_tag = sanitize_text_field($_COOKIE['selectedLocationCookie']);
//         if (!empty($cookie_tag)) {
//             $atts['tag'] = $cookie_tag;
//         }
//     }

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => intval($atts['limit']),
        'tax_query'      => array(),
        'orderby'        => sanitize_text_field($atts['orderby']),
        'order'          => strtoupper(sanitize_text_field($atts['order'])),
    );

    if (!empty($atts['category'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => explode(',', $atts['category']),
        );
    }

//     if (!empty($atts['tag'])) {
//         $args['tax_query'][] = array(
//             'taxonomy' => 'product_tag',
//             'field'    => 'slug',
//             'terms'    => explode(',', $atts['tag']),
//         );
//     }

    $query = new WP_Query($args);
    ob_start();

    $location_prices = [];
    $shortcode_id = uniqid('custom_products_'); // Unique ID for each shortcode instance

    if ($query->have_posts()) {
        echo '<div class="custom-product-woocommerce" id="' . esc_attr($shortcode_id) . '">';
        echo '<ul class="custom-product-query">';

        while ($query->have_posts()) {
            $query->the_post();
            global $product;
            $product_id = get_the_ID();

            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();

                foreach ($variations as $variation) {
                    $location = $variation['attributes']['attribute_location'] ?? null;
                    $price = $variation['display_price'];

                    if ($location) {
                        if (!isset($location_prices[$product_id])) {
                            $location_prices[$product_id] = [];
                        }
                        if (!isset($location_prices[$product_id][$location])) {
                            $location_prices[$product_id][$location] = [];
                        }
                        $location_prices[$product_id][$location][] = $price;
                    }
                }
            }

            echo '<li class="custom-product" data-product-id="' . esc_attr($product_id) . '" data-shortcode-id="' . esc_attr($shortcode_id) . '">';
            echo '<div class="custom-product-image">' . get_the_post_thumbnail($product_id, 'medium') . '</div>';
            echo '<div class="custom-product-content">';
//             echo '<h2 class="custom-product-title"><a href="' . get_permalink() . '">' . get_the_title() . '</a></h2>';
               echo '<h2 class="custom-product-title">' . get_the_title() . '</h2>';
            echo '<div class="custom-product-price">';
//             echo '<span class="price woocommerce-Price-amount amount"><bdi>' . $product->get_price_html() . '</bdi></span>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    } else {
        echo '<p>No products found.</p>';
    }

    wp_reset_postdata();

    $currency_symbol = get_woocommerce_currency_symbol();

    // Enqueue script only once
    static $script_enqueued = false;
    if (!$script_enqueued) {
        wp_enqueue_script('custom-product-query', get_template_directory_uri() . '/js/customproductquery.js', array('jquery'), null, true);
        $script_enqueued = true;
    }

    // Pass data specific to this shortcode instance
    wp_localize_script('custom-product-query', $shortcode_id . '_data', [
        'prices'   => $location_prices,
        'currency' => $currency_symbol,
        'shortcodeId' => $shortcode_id
    ]);

    return ob_get_clean();
}

add_shortcode('custom_products', 'custom_products_with_location_pricing');


?>