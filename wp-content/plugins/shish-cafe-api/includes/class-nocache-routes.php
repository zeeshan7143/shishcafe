<?php
// 🔄 Auto flush caches
if (!wp_next_scheduled('custom_auto_cache_flush')) {
    wp_schedule_event(time(), 'one_minute', 'custom_auto_cache_flush');
}

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['one_minute'])) {
        $schedules['one_minute'] = [
            'interval' => 60,
            'display'  => __('Every 1 Minute'),
        ];
    }
    return $schedules;
});

add_action('custom_auto_cache_flush', function () {
    wp_cache_flush();

    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");

    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients();
    }
    if (function_exists('wc_delete_shop_order_transients')) {
        wc_delete_shop_order_transients();
    }

    if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_varnish_cache')) {
        WpeCommon::purge_varnish_cache();
    }
});
?>