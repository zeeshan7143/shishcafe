<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shish_Cafe_User_Service
{
    public static function build_user_payload(array $params)
    {
        if (!function_exists('wc_get_orders')) {
            return new WP_Error(
                'woocommerce_missing',
                'WooCommerce is required for this endpoint.',
                ['status' => 500]
            );
        }

        $user_id = isset($params['user_id']) ? absint($params['user_id']) : 0;
        $email = isset($params['email']) ? sanitize_email((string) $params['email']) : '';
        $phone = isset($params['phone']) ? sanitize_text_field((string) $params['phone']) : '';

        if ($user_id <= 0 && $email === '' && $phone === '') {
            return new WP_Error(
                'missing_identifier',
                'Please provide at least one identifier: user_id, email, or phone.',
                ['status' => 400]
            );
        }

        $user = null;

        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
        }

        if (!$user && $email !== '') {
            $user = get_user_by('email', $email);
        }

        if (!$user && $phone !== '') {
            $user = self::find_user_by_phone($phone);
        }

        $resolved_user_id = $user ? (int) $user->ID : 0;
        $resolved_email = $email;

        if ($resolved_email === '' && $resolved_user_id > 0) {
            $resolved_email = (string) get_user_meta($resolved_user_id, 'billing_email', true);
            if ($resolved_email === '') {
                $resolved_email = (string) $user->user_email;
            }
        }

        $orders = self::get_user_orders($resolved_user_id, $resolved_email);
        $stats = self::build_order_stats($orders);

        return [
            'success' => true,
            'user_found' => (bool) $user,
            'user' => self::build_user_data($user),
            'summary' => $stats,
            'recent_orders' => self::build_recent_orders_data($orders),
        ];
    }

    private static function find_user_by_phone($phone)
    {
        $normalized_phone = self::normalize_phone($phone);
        if ($normalized_phone === '') {
            return null;
        }

        $users = get_users([
            'number' => 50,
            'meta_query' => [
                [
                    'key' => 'billing_phone',
                    'value' => $phone,
                    'compare' => '=',
                ],
            ],
        ]);

        foreach ($users as $candidate) {
            $candidate_phone = (string) get_user_meta($candidate->ID, 'billing_phone', true);
            if (self::normalize_phone($candidate_phone) === $normalized_phone) {
                return $candidate;
            }
        }

        return null;
    }

    private static function normalize_phone($phone)
    {
        return preg_replace('/[^0-9\+]/', '', (string) $phone);
    }

    private static function get_user_orders($user_id, $email)
    {
        $args = [
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
            'status' => ['processing', 'completed', 'on-hold', 'cancelled', 'pending', 'failed', 'refunded'],
        ];

        if ($user_id > 0) {
            $args['customer_id'] = $user_id;
        } elseif ($email !== '') {
            $args['billing_email'] = $email;
        } else {
            return [];
        }

        $orders = wc_get_orders($args);

        return is_array($orders) ? $orders : [];
    }

    private static function build_order_stats(array $orders)
    {
        $total_orders = count($orders);
        $total_spent = 0.0;
        $last_order_date = '';

        foreach ($orders as $index => $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            if (!in_array($order->get_status(), ['cancelled', 'failed', 'refunded'], true)) {
                $total_spent += (float) $order->get_total();
            }

            if ($index === 0 && $order->get_date_created()) {
                $last_order_date = $order->get_date_created()->date('Y-m-d H:i:s');
            }
        }

        return [
            'total_orders' => $total_orders,
            'total_spent' => wc_format_decimal($total_spent, 2),
            'last_order_date' => $last_order_date,
        ];
    }

    private static function build_recent_orders_data(array $orders)
    {
        $recent = array_slice($orders, 0, 5);
        $rows = [];

        foreach ($recent as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $rows[] = [
                'order_id' => $order->get_id(),
                'date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'status' => wc_get_order_status_name($order->get_status()),
                'total' => wp_strip_all_tags($order->get_formatted_order_total()),
            ];
        }

        return $rows;
    }

    private static function build_user_data($user)
    {
        if (!$user instanceof WP_User) {
            return null;
        }

        return [
            'id' => (int) $user->ID,
            'email' => (string) $user->user_email,
            'first_name' => (string) get_user_meta($user->ID, 'first_name', true),
            'last_name' => (string) get_user_meta($user->ID, 'last_name', true),
            'display_name' => (string) $user->display_name,
            'phone' => (string) get_user_meta($user->ID, 'billing_phone', true),
            'registered_at' => (string) $user->user_registered,
            'billing' => [
                'address_1' => (string) get_user_meta($user->ID, 'billing_address_1', true),
                'address_2' => (string) get_user_meta($user->ID, 'billing_address_2', true),
                'city' => (string) get_user_meta($user->ID, 'billing_city', true),
                'postcode' => (string) get_user_meta($user->ID, 'billing_postcode', true),
                'country' => (string) get_user_meta($user->ID, 'billing_country', true),
            ],
        ];
    }
}
