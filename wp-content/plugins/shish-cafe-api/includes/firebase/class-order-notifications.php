<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Order Notifications
 * Hooks into WooCommerce order events and sends notifications to Firebase
 */
class Shish_Cafe_Order_Notifications {

    private $firebase_service;

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'class-firebase-service.php';
        $this->firebase_service = new Shish_Cafe_Firebase_Service();
        $this->init_hooks();
    }

    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        // Hook when a new order is created
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 1);
        
        // Hook when order status changes
        // add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);
    }

    /**
     * Get order location from various sources
     * 
     * @param WC_Order $order
     * @return string Location name or default location
     */
    private function get_order_location($order) {
        // Try to get from order meta first (custom field)
        $location = $order->get_meta('_order_location');
        if (!empty($location)) {
            return sanitize_text_field($location);
        }

        // Try to get from order items
        $locations = [];
        foreach ($order->get_items() as $item) {
            $item_location = $item->get_meta('location');
            if (!empty($item_location)) {
                $locations[] = $item_location;
            }
        }

        if (!empty($locations)) {
            return sanitize_text_field(reset($locations));
        }

        // Try to get customer's location from their user meta (if registered user)
        $customer_id = $order->get_customer_id();
        if (!empty($customer_id)) {
            $user_location = get_user_meta($customer_id, 'shish_cafe_user_location', true);
            if (!empty($user_location)) {
                return sanitize_text_field($user_location);
            }
        }

        // Get default location from settings (first configured location)
        $locations = get_option('shish_cafe_locations', []);
        if (!empty($locations) && is_array($locations)) {
            return sanitize_text_field(reset($locations));
        }

        // Last resort fallback
        return 'Unknown';
    }

    /**
     * Get staff users assigned to a specific location
     * 
     * @param string $location Location name
     * @return array Array of WP_User objects assigned to location
     */
    private function get_staff_for_location($location) {
        if (empty($location) || $location === 'Unknown') {
            return [];
        }

        $args = [
            'role' => 'Orders Manager',
            'meta_key' => 'shish_cafe_user_location',
            'meta_value' => $location,
            'meta_compare' => '='
        ];

        return get_users($args);
    }

    /**
     * Validate FCM token format
     * 
     * @param string $token FCM token
     * @return bool
     */
    private function is_valid_fcm_token($token) {
        // FCM tokens should be non-empty strings of reasonable length (typically 150+ chars)
        if (empty($token) || !is_string($token)) {
            return false;
        }

        // FCM tokens are alphanumeric with some special chars, typically 150+ characters
        // Basic validation - check length and character set
        if (strlen($token) < 100) {
            return false;
        }

        // Additional check - should not contain obvious invalid data
        if (strpos($token, ' ') !== false || strpos($token, "\n") !== false) {
            return false;
        }

        return true;
    }

    /**
     * Send FCM notifications to staff at order location
     * 
     * @param int $order_id Order ID
     * @param string $location Location name
     * @param array $fcm_data Custom FCM data
     */
    private function send_notifications_to_location_staff($order_id, $location, $fcm_data = []) {
        
        // Skip if location is unknown
        if (empty($location) || $location === 'Unknown') {
            error_log('Cannot send FCM notifications - order location is unknown for Order #' . $order_id);
            return;
        }

        $staff = $this->get_staff_for_location($location);
        
        if (empty($staff)) {
            error_log('No staff assigned to location: ' . $location . ' for Order #' . $order_id);
            return;
        }

        // Prepare notification payload
        $notification = [
            'title' => 'New Order #' . $order_id,
            'body' => 'Order ' . $order_id . ' from ' . $location . ' location'
        ];

        $custom_data = array_merge([
            'order_id' => (string) $order_id,
            'location' => $location,
        ], $fcm_data);

        // Send to each staff member
        foreach ($staff as $staff_member) {
            $fcm_token = get_user_meta($staff_member->ID, 'shish_cafe_fcm_token', true);
            
            // Validate token before sending
            if (!$this->is_valid_fcm_token($fcm_token)) {
                error_log('Invalid or missing FCM token for staff user #' . $staff_member->ID . ' at location: ' . $location . '. Token: ' . substr($fcm_token, 0, 20) . '...');
                continue; // Skip this user, continue with next
            }

            $result = $this->firebase_service->send_fcm_message($fcm_token, $notification, $custom_data);
            
            if (is_wp_error($result)) {
                error_log('FCM notification failed for user #' . $staff_member->ID . ' (Order #' . $order_id . '): ' . $result->get_error_message());
            } else {
                error_log('FCM notification sent to user #' . $staff_member->ID . ' (Order #' . $order_id . ')');
            }
        }
    }

    /**
     * Handle new order submission
     * 
     * @param int $order_id
     */
    public function handle_new_order($order_id) {
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get order type and location
        $order_type = $order->get_meta('_shish_cafe_order_type');
        if (empty($order_type)) {
            $order_type = 'takeaway';
        }

        $location = $this->get_order_location($order);

        // Send to Firestore with order ID as document ID
        $order_data = [
            'order_id' => (int) $order_id,
            'order_type' => sanitize_text_field($order_type),
            'location' => $location,
            'timestamp' => current_time('timestamp'),
            'created_at' => gmdate('c'), // ISO 8601 format
        ];

        $result = $this->firebase_service->send_to_firebase_backend('orders', $order_data, 'order_' . $order_id);

        if (is_wp_error($result)) {
            error_log('Order notification failed for Order #' . $order_id . ': ' . $result->get_error_message());
        } else {
            error_log('Order notification sent to Firebase for Order #' . $order_id);
        }

        // Send FCM notifications to staff at the order's location
        $this->send_notifications_to_location_staff($order_id, $location, [
            'order_type' => $order_type,
            'event' => 'new_order',
        ]);
    }

    /**
     * Handle order status changes
     * 
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     */
    public function handle_order_status_change($order_id, $old_status, $new_status) {
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order_type = $order->get_meta('_shish_cafe_order_type');
        if (empty($order_type)) {
            $order_type = 'takeaway';
        }

        $location = $this->get_order_location($order);

        // Prepare status update data with explicit document ID
        $status_data = array(
            'order_id' => (int) $order_id,
            'order_type' => sanitize_text_field($order_type),
            'location' => $location,
            'old_status' => sanitize_text_field($old_status),
            'new_status' => sanitize_text_field($new_status),
            'timestamp' => current_time('timestamp'),
            'updated_at' => gmdate('c'), // ISO 8601 format
        );

        // Send status update to Firebase with unique document ID
        $document_id = 'status_update_' . $order_id . '_' . current_time('U');
        $result = $this->firebase_service->send_to_firebase_backend('status-updates', $status_data, $document_id);

        if (is_wp_error($result)) {
            error_log('Order status update failed for Order #' . $order_id . ': ' . $result->get_error_message());
        } else {
            error_log('Order status update sent to Firebase for Order #' . $order_id);
        }

        // Send FCM notifications about status change to location staff
        $this->send_notifications_to_location_staff($order_id, $location, [
            'order_type' => $order_type,
            'event' => 'status_change',
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }

    /**
     * Get Firebase service instance
     * 
     * @return Shish_Cafe_Firebase_Service
     */
    public function get_firebase_service() {
        return $this->firebase_service;
    }
}
