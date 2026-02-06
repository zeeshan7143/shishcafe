<?php

/**
 * Plugin Name: Shinsh Cafe API
 * Description: Custom REST API endpoints for fetching and printing WooCommerce order data.
 * Version: 1.2
 * Author: Enigmatix Global
 * License: GPLv2 or later
 * Text Domain: shinsh-cafe-api
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// ✅ Include the cache-busting logic
// require_once __DIR__ . '/includes/class-nocache-routes.php';
// require_once __DIR__ . '/includes/class-cache-cleaner.php';
// Additional FUnctional file
require_once plugin_dir_path(__FILE__) . '/includes/shinsh-cafe-extra.php';
/**
 * Always set new orders to On-Hold right after creation
 */
// add_action('woocommerce_new_order', function ($order_id) {
//     $order = wc_get_order($order_id);

//     if ($order && $order->get_status() !== 'on-hold') {
//         $order->update_status('on-hold', 'Forced default status to on-hold for custom order management.');
//     }
// }, 999); // Run late to override gateways

/**
 * Prevent payment gateways from changing status to "processing" or "completed"
 */
// add_filter('woocommerce_payment_complete_order_status', function ($status, $order_id, $order) {
//     return 'on-hold';
// }, 999, 3);
add_action('woocommerce_order_status_processing', function ($order_id) {
    $order = wc_get_order($order_id);

    if ($order) {
        $order->update_status(
            'on-hold',
            'Payment received, moved to on-hold for manual confirmation.'
        );
    }
}, 20);


/**
 * Extra safety: if order is submitted at checkout, force On-Hold
 */
// add_action('woocommerce_checkout_order_processed', function ($order_id, $posted_data, $order) {
//     if ($order && $order->get_status() !== 'on-hold') {
//         $order->update_status('on-hold', 'Forced to on-hold at checkout.');
//     }
// }, 999, 3);

// =======================
// REST API ROUTES
// =======================
add_action('rest_api_init', 'shinsh_cafe_register_api_routes');

function shinsh_cafe_register_api_routes()
{
    register_rest_route('print/v1', '/order/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'shinsh_cafe_get_order_print_data',
        'permission_callback' => 'shinsh_cafe_validate_auth_key',
    ]);

    register_rest_route('print/v1', '/latest-order-data-print', [
        'methods'             => 'GET',
        'callback'            => 'shinsh_cafe_get_latest_order_id',
        'permission_callback' => 'shinsh_cafe_validate_auth_key',
    ]);

    register_rest_route('print/v1', '/recent-orders', [
        'methods'             => 'GET',
        'callback'            => 'shinsh_cafe_get_recent_orders',
        'permission_callback' => 'shinsh_cafe_validate_auth_key',
    ]);
    register_rest_route('order-manager/v1', '/update-order', [
        'methods'             => 'POST',
        'callback'            => 'custom_update_order_status',
        'permission_callback' => 'shinsh_cafe_validate_auth_key',
    ]);
}

// =======================
// AUTH VALIDATION
// =======================
function shinsh_cafe_validate_auth_key($request)
{
    if (!defined('PRINT_API_KEY')) {
        return new WP_Error('api_key_not_defined', 'API key is not defined in wp-config.php.', ['status' => 500]);
    }

    return $request->get_header('auth-key') === PRINT_API_KEY;
}

// =======================
// LATEST ORDER ENDPOINT
// =======================
function shinsh_cafe_get_latest_order_id()
{
    nocache_headers();
    $orders = wc_get_orders([
        'limit'   => 1,
        'orderby' => 'id',
        'order'   => 'DESC',
    ]);

    if (!empty($orders)) {
        $order = $orders[0];
        return new WP_REST_Response([
            'success'  => true,
            'order_id' => $order->get_id()
        ], 200);
    }

    return new WP_REST_Response(['success' => false, 'message' => 'No orders found'], 404);
}
// =======================
// ORDER MANAGEMENT ENDPOINT
// =======================
function custom_update_order_status(WP_REST_Request $request)
{
    $order_id = $request->get_param('order_id');
    $status   = $request->get_param('status');
    $time     = $request->get_param('time');
    $reason   = $request->get_param('reason');

    if (!$order_id || !$status) {
        return new WP_Error('missing_data', 'Order ID and Status are required.', ['status' => 400]);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return new WP_Error('invalid_order', 'Invalid Order ID.', ['status' => 404]);
    }

    // ✅ Update status logic
    if ($status === 'accepted' || $status === 'processing') {
        $order->update_status('processing', 'Order accepted via app.');
        $order->update_meta_data('_order_status_action', 'processing');
        if ($time) {
            $order->update_meta_data('_order_prep_time', sanitize_text_field($time));
        }
        $order->delete_meta_data('_order_reject_reason');
    } elseif ($status === 'rejected' || $status === 'cancelled') {
        $order->update_status('cancelled', 'Order rejected via app.');
        $order->update_meta_data('_order_status_action', 'cancelled');
        if ($reason) {
            $order->update_meta_data('_order_reject_reason', sanitize_text_field($reason));
        }
        $order->delete_meta_data('_order_prep_time');
    } elseif ($status === 'completed') {
        $order->update_status('completed', 'Order completed via app.');
        $order->update_meta_data('_order_status_action', 'completed');
        $order->delete_meta_data('_order_prep_time');
        $order->delete_meta_data('_order_reject_reason');
    }

    $order->save();

    // regenerate print file (keep it fresh)
    shinsh_cafe_generate_order_html($order);

    // ✅ Build same structured response as /recent-orders
    $locations = [];
    foreach ($order->get_items() as $item) {
        $loc = $item->get_meta('location');
        if (!empty($loc)) {
            $locations[] = $loc;
        }
    }
    $location = !empty($locations) ? implode(', ', array_unique($locations)) : 'Unknown';

    $products = [];
    foreach ($order->get_items() as $item) {
        $products[] = [
            'product_name' => $item->get_name(),
            'quantity'     => $item->get_quantity(),
            'total'        => wp_strip_all_tags(wc_price($item->get_total())),
        ];
    }

    $clean_total = wp_strip_all_tags($order->get_formatted_order_total());

    //     $address_parts = implode(', ', array_filter([
    //         $wc_order->get_billing_address_1(),                // Woo default field
    //         $$wc_order->get_meta('billing_building_type'),      // custom field
    //         $$wc_order->get_meta('_billing_autocomplete_address')
    //     ]));
    //     // 		        $$address_parts = implode(', ', array_filter([
    //     //             $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
    //     //             $wc_order->get_billing_company(),
    //     //             $wc_order->get_billing_address_1(),
    //     //             $wc_order->get_billing_address_2(),
    //     //             $wc_order->get_billing_city(),
    //     //             $wc_order->get_billing_state(),
    //     //             $wc_order->get_billing_postcode(),
    //     //             $wc_order->get_billing_country(),
    //     //         ]));
    //     $billing_address = implode(', ', $address_parts);

    $address_parts = array_filter([
        $order->get_billing_address_1(),
        $order->get_meta('billing_building_type'),
        $order->get_meta('_billing_autocomplete_address')
    ]);
    $billing_address = implode(', ', $address_parts);

    $custom_status = wc_get_order_status_name($order->get_status());

    return new WP_REST_Response([
        'success' => true,
        'order'   => [
            'order_id'  => $order->get_id(),
            'date'      => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
            'location'  => $location,
            'products'  => $products,
            'address'   => $billing_address,
            'total'     => $clean_total,
            'status'    => $custom_status,
            'prep_time' => $order->get_meta('_order_prep_time'),
            'reject_reason' => $order->get_meta('_order_reject_reason'),
        ]
    ], 200);
}

// =======================
// RECENT 10 ORDERS ENDPOINT (Fresh Data)
// =======================
function shinsh_cafe_get_recent_orders(WP_REST_Request $request)
{
    $location_filter = sanitize_text_field($request->get_param('location'));

    // --- call WooCommerce REST API instead of wc_get_orders() ---
    $url = rest_url('wc/v3/orders');
    $url = add_query_arg([
        'orderby'        => 'date',
        'order'          => 'desc',
        'per_page'        => 30,
        'consumer_key'   => SHINSH_CAFE_CONSUMER_KEY,
        'consumer_secret' => SHINSH_CAFE_CONSUMER_SECRET
    ], $url);

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => ['Cache-Control' => 'no-cache']
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Failed to fetch WooCommerce orders', ['status' => 500]);
    }

    $orders = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($orders)) {
        return new WP_REST_Response(['success' => true, 'orders' => []], 200);
    }
    $cutoff = strtotime('-1000 hours');
    $data = [];
    foreach ($orders as $order) {
        $wc_order = wc_get_order($order['id']); // reuse WC object for meta + formatting
        if (!$wc_order) continue;
        $created = $wc_order->get_date_created();
        if (!$created || $created->getTimestamp() < $cutoff) {
            continue; // skip if older than 24h
        }

        // Extract location(s)
        $locations = [];
        foreach ($wc_order->get_items() as $item) {
            $loc = $item->get_meta('location');
            if (!empty($loc)) $locations[] = $loc;
        }
        $location = !empty($locations) ? implode(', ', array_unique($locations)) : 'Unknown';
        // ✅ Order type (Delivery / Pickup)
        $order_type = $wc_order->get_meta('_custom_delivery_option');
        if (!$order_type) {
            $order_type = get_post_meta($wc_order->get_id(), '_custom_delivery_option', true);
        }
        // Location filter
        if ($location_filter && strcasecmp($location, $location_filter) !== 0) continue;

        // Products
        $products = [];
        foreach ($wc_order->get_items() as $item) {
            $product = $item->get_product();
            $product_name = $product ? $product->get_title() : $item->get_name();
            $products[] = [
                'product_name' => $product_name,
                'quantity'     => $item->get_quantity(),
                'total'        => wp_strip_all_tags(wc_price($item->get_total())),
            ];
        }

        // Address
        $address = implode(', ', array_filter([
            $wc_order->get_billing_address_1(),                // Woo default field
            $wc_order->get_meta('billing_building_type'),      // custom field
            $wc_order->get_meta('_billing_autocomplete_address')
        ]));
        // 		        $address = implode(', ', array_filter([
        //             $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
        //             $wc_order->get_billing_company(),
        //             $wc_order->get_billing_address_1(),
        //             $wc_order->get_billing_address_2(),
        //             $wc_order->get_billing_city(),
        //             $wc_order->get_billing_state(),
        //             $wc_order->get_billing_postcode(),
        //             $wc_order->get_billing_country(),
        //         ]));

        $data[] = [
            'order_id'      => $wc_order->get_id(),
            'date'          => $wc_order->get_date_created()?->date('d-m-y h:i A') ?? '',
            'location'      => $location,
            'order_type'    => $order_type,
            'products'      => $products,
            'address'       => $address,
            'total'         => wp_strip_all_tags($wc_order->get_formatted_order_total()),
            'status'        => wc_get_order_status_name($wc_order->get_status()),
            'prep_time'     => $wc_order->get_meta('_order_prep_time'),
            'reject_reason' => $wc_order->get_meta('_order_reject_reason'),
        ];
    }

    return new WP_REST_Response([
        'success' => true,
        'orders'  => $data,
    ], 200);
}

// =======================
// ORDER PRINT GENERATOR (new)
// =======================

/**
 * Generate HTML print file for given order.
 * Accepts either WC_Order object or order ID.
 * Returns full file path on success, false on failure.
 */
function shinsh_cafe_generate_order_html($order_or_id)
{
    if (is_numeric($order_or_id)) {
        $order = wc_get_order(intval($order_or_id));
    } elseif ($order_or_id instanceof WC_Order) {
        $order = $order_or_id;
    } else {
        return false;
    }

    if (!$order) return false;

    // Build data (same logic as your print assembly)
    $order_id = $order->get_id();
    // Get order type (Delivery / Pickup)
    $order_type = $order->get_meta('_custom_delivery_option');
    if (!$order_type) {
        $order_type = get_post_meta($order_id, '_custom_delivery_option', true);
    }
    // Collect unique locations
    $locations = [];
    foreach ($order->get_items() as $item) {
        $loc = $item->get_meta('location');
        if (!empty($loc)) $locations[] = $loc;
    }
    $unique_locations = array_unique($locations);
    $location = !empty($unique_locations) ? implode(', ', $unique_locations) : 'Unknown';

    // Build products
    // --- Delivery Fee ---
    $delivery_fee = 0;
    foreach ($order->get_fees() as $fee) {
        if ($fee->get_name() === 'Delivery Charges') {
            $charges = wp_strip_all_tags(wc_price($fee->get_total()));
            $delivery_fee = str_replace('&pound;', '£', wp_strip_all_tags($charges));
            break;
        }
    }

    // --- Totals ---
    $order_total    = $order->get_total();          // Includes delivery
    $order_subtotal = $order_total - floatval(str_replace('£', '', $delivery_fee));

    // Products 
    $products = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $product_is_variation = $product->is_type('variation');
        if ($product_is_variation) {
            $product = wc_get_product($product->get_parent_id());
        }
        $product_name = $product->get_title();
        $quantity     = $item->get_quantity();
        $total        = wp_strip_all_tags(wc_price($item->get_total()));

        $short_description = $product->get_short_description();
        if (empty($short_description)) {
            $short_description = $product->get_description();
        }

        $variations = [];
        $ppom_options_display = [];
        $ppom_total = 0;

        foreach ($item->get_formatted_meta_data() as $meta) {
            if ($meta->display_key === 'Location') {
                continue;
            }

            // Variations
            if (in_array($meta->display_key, ['Size', 'Choose your crust'])) {
                $clean_value = wp_strip_all_tags(str_replace('"', ' inches', $meta->display_value));
                $variations[] = [
                    'label' => $meta->display_key,
                    'value' => $clean_value,
                ];
                continue;
            }

            if (strpos($meta->key, 'attribute_') === 0) {
                $clean_value = wp_strip_all_tags($meta->display_value);
                $variations[] = [
                    'label' => $meta->display_key,
                    'value' => $clean_value,
                    'qty'   => $quantity,
                ];
                continue;
            }

            // PPOM
            if (strpos($meta->display_value, '<p>') !== false) {
                $label = $meta->display_key;

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($meta->display_value);
                libxml_clear_errors();

                foreach ($dom->getElementsByTagName('p') as $p) {
                    $p_content = $p->textContent;
                    $values = explode(',', $p_content);

                    foreach ($values as $val) {
                        $current_val = trim($val);
                        if (!empty($current_val)) {
                            $item_price_num = 0;
                            $item_price_str = '';

                            if (preg_match('/\[\+£([\d\.]+)\]/', $current_val, $m)) {
                                $item_price_num = (float)$m[1];
                                $item_price_str = '£' . number_format($item_price_num, 2);
                                $ppom_total += $item_price_num;
                                $current_val = trim(str_replace($m[0], '', $current_val));
                            }

                            $full_price = $item_price_num > 0 ? $quantity * $item_price_num : 0;

                            $ppom_options_display[] = [
                                'label'      => $label,
                                'qty'        => $quantity,
                                'value'      => $current_val,
                                'item_price' => $item_price_str,
                                'full_price' => $full_price > 0 ? '£' . number_format($full_price, 2) : '',
                            ];
                        }
                    }
                }
                continue;
            }
        }

        $final_ppom_total = $ppom_total * $quantity;
        $item_total_raw = $item->get_total();
        $base_price = $item_total_raw - $final_ppom_total;
        $unit_price = $base_price / $quantity;
        // Get product categories
        $category_slugs = [];
        $category_names = [];

        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $category_slugs[] = $term->slug;
                $category_names[] = $term->name;
            }
        }
        $products[] = [
            'product_name' => $product_name,
            'categories_slug'   => $category_slugs,
            'category_names'    => $category_names,
            'quantity'     => $quantity,
            'unit_price'   => '£' . number_format($unit_price, 2),
            'base_price'   => '£' . number_format($base_price, 2),
            'ppom_total'   => '£' . number_format($final_ppom_total, 2),
            'total'        => $total,
            'variations'   => $variations,
            'ppom_options' => $ppom_options_display,
        ];
    }

    // Build billing address
    $address_parts = array_filter([
        $order->get_billing_address_1(),                // Woo default field
        $order->get_meta('billing_building_type'),      // custom field
        $order->get_meta('_billing_autocomplete_address') // custom field
    ]);
    $billing_address = implode(', ', $address_parts);

    // Clean total
    $clean_total = wp_strip_all_tags($order->get_formatted_order_total());

    // Build HTML (same structure you use)
    ob_start();
?>

    <html>

    <head>
        <meta charset="utf-8" />
        <title>Order Receipt</title>
        <!-- 		<style>
			@page{{
				size : 80mm auto;
				margin: 2mm;
				}}
		</style> -->
    </head>

    <body style="margin:0;padding:0;font-family:Arial, Helvetica, sans-serif;color:#000;">
        <div style="width:300px;padding:8px;margin:0 auto;box-sizing:border-box;color:#000;">
            <div style="text-align:center;margin-bottom:6px;">
                <img src="<?php echo esc_url(get_site_url() . '/wp-content/uploads/2025/03/shishcafe-logo-200x60-1.png'); ?>"
                    alt="Logo" style="max-width:180px;display:block;margin:0 auto;" />
            </div>
            <div style="text-align:center;font-size:14px;font-weight:bold;margin-bottom:4px;line-height:1.9;">
                Order #<?php echo esc_html($order->get_order_number()); ?>
            </div>
            <?php if ($order_type): ?>
                <div style="text-align:center;font-size:15px;font-weight:bold;margin-bottom:10px;">
                    <strong>Order Type:</strong> <?php echo esc_html(ucfirst($order_type)); ?>
                </div>
            <?php endif; ?>

            <div style="font-size:12px;margin-bottom:8px;line-height:1.7;">
                <div><strong>Date:</strong> <?php echo esc_html(wc_format_datetime($order->get_date_created(), 'd-m-y h:i A')); ?></div>
                <div><strong>Customer:</strong> <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></div>
                <div><strong>Phone:</strong> <?php echo esc_html($order->get_billing_phone()); ?></div>
                <div><strong>Address:</strong> <?php echo esc_html($billing_address); ?></div>
            </div>

            <div style="border-top:1px solid #000;margin:6px 0;"></div>

            <div style="font-size:12px;font-weight:bold;margin:4px 0;">Items:</div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr>
                        <th style="border:0px solid #000;padding:4px;text-align:left;width:64%; visibility: hidden;">Product Name</th>
                        <th style="border:0px solid #000;padding:4px;text-align:right;width:16%;">Price</th>
                        <th style="border:0px solid #000;padding:4px;text-align:right;width:20%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                        <?php
                        $is_pizza_or_fatayer = false;

                        if (!empty($prod['category_names']) && is_array($prod['category_names'])) {
                            $is_pizza_or_fatayer = array_intersect(
                                ['Pizzas', 'Fatayers'],
                                $prod['category_names']
                            );
                        }
                        ?>
                        <!-- Main product row -->
                        <tr>
                            <?php
                            $size_value = '';
                            $filtered_variations = [];

                            if (!empty($prod['variations']) && $is_pizza_or_fatayer) {
                                foreach ($prod['variations'] as $var) {
                                    if (strtolower($var['label']) === 'size') {
                                        // $size_value = $var['value'];
                                        preg_match('/\d+(\.\d+)?/', $var['value'], $matches);
                                        $size_value = $matches[0] ?? '';
                                        continue; // hide size from variations
                                    }
                                    $filtered_variations[] = $var;
                                }
                            } else {
                                $filtered_variations = $prod['variations'] ?? [];
                            }
                            ?>
                            <td style="border:0px solid #000;padding:4px;text-align:left;vertical-align:top; display: flex;">
                                <span style="font-weight: 600; margin-right: 3px;">
                                    <?php echo esc_html($prod['quantity']); ?> x
                                    <?php echo esc_html($prod['product_name']); ?>
                                </span>
                                <span style="font-weight: 600; width: 30px; text-align: right;">
                                    <?php if ($size_value): ?>
                                        <?php echo esc_html($size_value); ?> "
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="border:0px solid #000;padding:4px;text-align:right;vertical-align:top;">
                                <?php echo esc_html($prod['unit_price'] ?? '£0.00'); ?>
                            </td>
                            <td style="border:0px solid #000;padding:4px;text-align:right;vertical-align:top;">
                                <?php echo esc_html($prod['base_price'] ?? '£0.00'); ?>
                            </td>
                        </tr>

                        <!-- Variations -->
                        <?php if (!empty($filtered_variations)): ?>
                            <?php foreach ($filtered_variations as $var): ?>
                                <tr>
                                    <td style="border:0px solid #000;padding:4px;text-align:left; padding-left: 8px; font-size: 11px;">
                                        <span style="font-weight: bold;">
                                            <?php echo esc_html($var['label']); ?>:
                                        </span><br />
                                        <span style="padding-left: 10px; padding-top: 3px; display: inline-block;">
                                            <?php echo esc_html($var['value']); ?>
                                        </span>
                                    </td>
                                    <td style="border:0px solid #000;"></td>
                                    <td style="border:0px solid #000;"></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>


                        <!-- PPOM Options -->
                        <?php if (!empty($prod['ppom_options'])): ?>
                            <?php foreach ($prod['ppom_options'] as $ppom): ?>
                                <tr>
                                    <td style="border:0px solid #000;padding:4px;text-align:left; padding-left: 8px; font-size: 11px;">
                                        <span style="font-weight: bold;"><?php echo esc_html($ppom['label']); ?>:</span></br>
                                        <span style="padding-left: 10px; padding-top: 3px; display: inline-block;"><?php echo esc_html($ppom['qty'] . ' x ' . $ppom['value']); ?></span>
                                    </td>
                                    <td style="border:0px solid #000;padding:4px;text-align:right;">
                                        <?php echo esc_html(! empty($ppom['item_price']) ? $ppom['item_price'] : '£0.00'); ?>
                                        <?php // echo esc_html($ppom['item_price']  ?? '£0.00'); 
                                        ?>
                                    </td>
                                    <td style="border:0px solid #000;padding:4px;text-align:right;">
                                        <?php echo esc_html(! empty($ppom['full_price']) ? $ppom['full_price'] : '£0.00'); ?>
                                        <?php // echo esc_html($ppom['full_price'] ?? '£0.00'); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Item total row -->
                        <tr style="display: none">
                            <td></td>
                            <td style="border:1px solid #000;padding:4px;text-align:left;">
                                <em>Item Total</em>
                            </td>
                            <td style="border:1px solid #000;padding:4px;text-align:right;">
                                <strong><?php echo esc_html($prod['total']); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <table style="width:100%;border-collapse:collapse;font-size:12px; margin-top: 20px;">
                <tr>
                    <td colspan="2" style="border:0px solid #000;padding:4px;text-align:right;"><strong>Subtotal:</strong></td>
                    <td style="border:1px solid #000;padding:4px;text-align:right;">
                        <?php echo '£' . number_format($order_subtotal, 2); ?>
                    </td>
                </tr>

                <?php if ($delivery_fee && floatval(str_replace('£', '', $delivery_fee)) > 0): ?>
                    <tr>
                        <td colspan="2" style="border:0px solid #000;padding:4px;text-align:right;"><strong>Delivery Fee:</strong></td>
                        <td style="border:1px solid #000;padding:4px;text-align:right;">
                            <?php echo $delivery_fee; ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2" style="border:0px solid #000;padding:4px;text-align:right;"><strong>Total:</strong></td>
                    <td style="border:1px solid #000;padding:4px;text-align:right;">
                        <?php echo wp_strip_all_tags(wc_price($order_total)); ?>
                    </td>
                </tr>
            </table>
            <div style="margin-top:8px;font-size:13px;font-weight:bold;display:none;justify-content:space-between;">
                <span>Total:</span>
                <span><?php echo esc_html($clean_total); ?></span>
            </div>

            <div style="text-align:center;font-size:11px;margin-top:10px;">
                Thank you for your order!
            </div>

        </div>
    </body>

    </html>
<?php
    $html = ob_get_clean();

    // Save file
    return shinsh_cafe_save_temp_html_file($order_id, $html);
}
// =======================
// ORDER PRINT DATA (updated to regenerate conditionally)
// =======================
function shinsh_cafe_get_order_print_data($data)
{
    $order_id = intval($data['id']);
    $order    = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('no_order', 'Invalid order ID', ['status' => 404]);
    }

    // Collect unique locations
    $locations = [];
    foreach ($order->get_items() as $item) {
        $loc = $item->get_meta('location');
        if (!empty($loc)) $locations[] = $loc;
    }
    $unique_locations = array_unique($locations);
    $location = !empty($unique_locations) ? implode(', ', $unique_locations) : 'Unknown';

    // Ensure upload folder exists
    $upload_dir = wp_upload_dir();
    $folder = trailingslashit($upload_dir['basedir']) . 'order-prints/';
    if (!file_exists($folder)) {
        wp_mkdir_p($folder);
    }

    // File path & url
    $file_path = $folder . 'order-' . $order_id . '.html';
    $file_url  = trailingslashit($upload_dir['baseurl']) . 'order-prints/order-' . $order_id . '.html';

    // Conditions to (re)generate:
    $regen = false;
    if (!file_exists($file_path)) {
        $regen = true;
    } else {
        $file_mtime = filemtime($file_path);
        if ($file_mtime < (time() - DAY_IN_SECONDS)) {
            $regen = true;
        } else {
            $order_modified = 0;
            if (method_exists($order, 'get_date_modified') && $order->get_date_modified()) {
                $order_modified = $order->get_date_modified()->getTimestamp();
            } elseif ($order->get_date_created()) {
                $order_modified = $order->get_date_created()->getTimestamp();
            }
            if ($order_modified && $file_mtime < $order_modified) {
                $regen = true;
            }
        }
    }

    if ($regen) {
        shinsh_cafe_generate_order_html($order);
    }

    // Collect products
    $products = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $product_is_variation = $product->is_type('variation');
        if ($product_is_variation) {
            $product = wc_get_product($product->get_parent_id());
        }
        $product_name = $product->get_title();
        $quantity     = $item->get_quantity();
        $total        = wp_strip_all_tags(wc_price($item->get_total()));

        $short_description = $product->get_short_description();
        if (empty($short_description)) {
            $short_description = $product->get_description();
        }

        $variations = [];
        $meta_display = [];
        $ppom_options_display = [];
        $ppom_total = 0;

        foreach ($item->get_formatted_meta_data() as $meta) {
            // Do not show "Location" in any array
            if ($meta->display_key === 'Location') {
                continue;
            }

            // Explicitly move specific PPOM fields to variations & clean value
            if (in_array($meta->display_key, ['Size', 'Choose your crust'])) {
                // Clean HTML tags and replace " with inches
                $clean_value = wp_strip_all_tags(str_replace('"', ' inches', $meta->display_value));
                $variations[] = [
                    'label' => $meta->display_key,
                    'value' => $clean_value,
                ];
                continue;
            }

            // Standard WooCommerce variation attributes
            if (strpos($meta->key, 'attribute_') === 0) {
                $clean_value = wp_strip_all_tags($meta->display_value);
                $variations[] = [
                    'label' => $meta->display_key,
                    'value' => $clean_value,
                ];
                continue;
            }

            // PPOM data contains <p> tags
            if (strpos($meta->display_value, '<p>') !== false) {
                $label = $meta->display_key;

                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML($meta->display_value);
                libxml_clear_errors();

                foreach ($dom->getElementsByTagName('p') as $p) {
                    $p_content = $p->textContent;
                    $values = explode(',', $p_content);

                    foreach ($values as $val) {
                        $current_val = trim($val);
                        if (!empty($current_val)) {
                            $item_price = null;
                            $price_match = [];

                            if (preg_match('/\[\+£([\d\.]+)\]/', $current_val, $price_match)) {
                                $item_price = '£' . number_format($price_match[1], 2);
                                $ppom_total += (float)$price_match[1];
                                $current_val = trim(str_replace($price_match[0], '', $current_val));
                            }

                            $ppom_options_display[] = [
                                'label' => $label,
                                'value' => $current_val,
                                'price' => $item_price,
                            ];
                        }
                    }
                }
                continue;
            }

            // Standard meta data
            $meta_display[] = $meta->display_key . ': ' . wp_kses_post($meta->display_value);
        }

        $final_ppom_total = $ppom_total * $quantity;
        $item_total_raw = $item->get_total();
        $base_price = $item_total_raw - $final_ppom_total;
        $unit_price = $base_price / $quantity;
        $order_type = $order->get_meta('_custom_delivery_option');
        if (!$order_type) {
            $order_type = get_post_meta($order->get_id(), '_custom_delivery_option', true);
        }
        // Get product categories
        $category_slugs = [];
        $category_names = [];

        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $category_slugs[] = $term->slug;
                $category_names[] = $term->name;
            }
        }
        $products[] = [
            'product_name'      => $product_name,
            'categories_slug'   => $category_slugs,
            'category_names'    => $category_names,
            'quantity'          => $quantity,
            'unit_price'        => '£' . number_format($unit_price, 2),
            'base_price'        => '£' . number_format($base_price, 2),
            'ppom_total'        => '£' . number_format($final_ppom_total, 2),
            'total'             => $total,
            'short_description' => $short_description,
            'variations'        => $variations,
            'meta'              => implode(', ', $meta_display),
            'ppom_options'      => $ppom_options_display,
            'order_type'        => $order_type,
        ];
    }

    $clean_total = wp_strip_all_tags($order->get_formatted_order_total());
    $final_total = str_replace('&pound;', '£', wp_strip_all_tags($clean_total));
    $address_parts = array_filter([
        $order->get_billing_address_1(),
        $order->get_meta('billing_building_type'),
        $order->get_meta('_billing_autocomplete_address')
    ]);
    $billing_address = implode(', ', $address_parts);

    $delivery_fee = 0;
    foreach ($order->get_fees() as $fee) {
        if ($fee->get_name() === 'Delivery Charges') {
            $charges = wp_strip_all_tags(wc_price($fee->get_total()));
            $delivery_fee = str_replace('&pound;', '£', wp_strip_all_tags($charges));
            break;
        }
    }

    return new WP_REST_Response([
        'success'         => true,
        'order_id'        => $order_id,
        'location'        => $location,
        'products'        => $products,
        'address'         => $billing_address,
        'delivery_fee'    => $delivery_fee,
        'total'           => $final_total,
        'status'          => wc_get_order_status_name($order->get_status()),
        'print_file_path' => $file_path,
        'print_file_url'  => $file_url,
    ], 200);
}



// =======================
// SAVE HTML FILE
// =======================
function shinsh_cafe_save_temp_html_file($order_id, $html)
{
    $upload_dir = wp_upload_dir();
    $folder = trailingslashit($upload_dir['basedir']) . 'order-prints/';

    if (!file_exists($folder)) {
        if (!wp_mkdir_p($folder)) {
            return false;
        }
    }

    $file_path = $folder . 'order-' . $order_id . '.html';
    if (file_put_contents($file_path, $html) === false) {
        return false;
    }

    return $file_path;
}

// =======================
// CLEANUP: delete files older than 24h (cron)
// =======================

// Schedule event if not already scheduled
add_action('init', function () {
    if (!wp_next_scheduled('shinsh_cafe_cleanup_old_prints')) {
        wp_schedule_event(time(), 'daily', 'shinsh_cafe_cleanup_old_prints');
    }
});

// The cleanup handler
add_action('shinsh_cafe_cleanup_old_prints', function () {
    $upload_dir = wp_upload_dir();
    $dir        = trailingslashit($upload_dir['basedir']) . 'order-prints/';
    if (!is_dir($dir)) return;

    foreach (glob($dir . 'order-*.html') as $file) {
        if (filemtime($file) < (time() - DAY_IN_SECONDS)) {
            @unlink($file);
        }
    }
});

// Optional: clear scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('shinsh_cafe_cleanup_old_prints');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'shinsh_cafe_cleanup_old_prints');
    }
});

// =======================
// Regenerate HTML when order is updated in admin
// =======================
add_action('save_post_shop_order', function ($post_id, $post, $update) {
    // When an order is updated/saved, regenerate the print html immediately
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    $order = wc_get_order($post_id);
    if ($order) {
        shinsh_cafe_generate_order_html($order);
    }
}, 10, 3);

// =======================
// AUTO CLEANUP (delete old order print files > 7 days)
// =======================
add_action('shinsh_cafe_cleanup_prints', 'shinsh_cafe_delete_old_prints');

function shinsh_cafe_delete_old_prints()
{
    $upload_dir = wp_upload_dir();
    $folder = trailingslashit($upload_dir['basedir']) . 'order-prints/';

    if (!file_exists($folder)) return;

    $files = glob($folder . 'order-*.html');
    $max_age = 2 * DAY_IN_SECONDS; // keep 7 days

    foreach ($files as $file) {
        if (filemtime($file) < (time() - $max_age)) {
            unlink($file);
        }
    }
}

// Schedule daily cleanup on plugin activation
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('shinsh_cafe_cleanup_prints')) {
        wp_schedule_event(time(), 'daily', 'shinsh_cafe_cleanup_prints');
    }
});

// Clear schedule on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('shinsh_cafe_cleanup_prints');
});
