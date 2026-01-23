<?php
// 🚫 Disable cache for custom REST routes
add_action('init', function () {
    if (isset($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];

        if (
            strpos($uri, '/wp-json/print/v1/recent-orders') !== false ||
            strpos($uri, '/wp-json/print/v1/order/') !== false ||
            strpos($uri, '/wp-json/print/v1/latest-order-data-print') !== false
        ) {
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
        }
    }
});

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    $route = $request->get_route();

    if (
        strpos($route, '/print/v1/recent-orders') === 0 ||
        strpos($route, '/print/v1/order/') === 0 ||
        strpos($route, '/print/v1/latest-order-data-print') === 0
    ) {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
    }

    return $response;
}, 10, 3);

add_filter('woocommerce_order_data_store_cpt_get_orders_query', function ($query) {
    $query['cache_results'] = false;
    $query['update_post_term_cache'] = false;
    $query['update_post_meta_cache'] = false;
    return $query;
});
?>