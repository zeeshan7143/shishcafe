<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Firebase Service Class
 * Handles communication with Firebase backend using service account key
 */
class Shish_Cafe_Firebase_Service {

    private $service_account;
    private $project_id;
    private $access_token;
    private $token_expiry;

    public function __construct() {
        $this->load_service_account();
    }

    /**
     * Load service account key from WordPress options
     */
    private function load_service_account() {
        // Load from wp-config.php constant or from a config file
        $key_json = '';
        
        // Check for constant defined in wp-config.php
        if (defined('SHISH_CAFE_FIREBASE_KEY')) {
            $key_json = SHISH_CAFE_FIREBASE_KEY;
        } else {
            // Try to load from a config file in the plugin
            $config_file = plugin_dir_path(__FILE__) . 'firebase-config.json';
            if (file_exists($config_file)) {
                $key_json = file_get_contents($config_file);
            }
        }
        
        if (!empty($key_json)) {
            $this->service_account = json_decode($key_json, true);
            $this->project_id = isset($this->service_account['project_id']) ? $this->service_account['project_id'] : '';
        }
    }

    /**
     * Check if Firebase is configured
     * 
     * @return bool
     */
    public function is_configured() {
        return !empty($this->service_account) && !empty($this->project_id);
    }

    /**
     * Get or refresh Firebase access token
     * 
     * @return string|WP_Error
     */
    private function get_access_token() {
        // Return cached token if still valid
        if (!empty($this->access_token) && !empty($this->token_expiry) && time() < $this->token_expiry) {
            return $this->access_token;
        }

        if (!isset($this->service_account['private_key']) || !isset($this->service_account['client_email'])) {
            return new WP_Error('invalid_key', 'Invalid Firebase service account key');
        }

        // Create JWT token for authentication
        $jwt = $this->create_jwt(
            $this->service_account['private_key'],
            $this->service_account['client_email']
        );

        if (is_wp_error($jwt)) {
            return $jwt;
        }

        // Exchange JWT for access token
        $token_response = $this->exchange_jwt_for_token($jwt);
        
        if (is_wp_error($token_response)) {
            return $token_response;
        }

        $this->access_token = $token_response['access_token'];
        $this->token_expiry = time() + $token_response['expires_in'] - 300; // Refresh 5 min before expiry

        return $this->access_token;
    }

    /**
     * Create JWT token for Firebase authentication
     * 
     * @param string $private_key
     * @param string $client_email
     * @return string|WP_Error
     */
    private function create_jwt($private_key, $client_email) {
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);

        $now = time();
        $payload = json_encode([
            'iss' => $client_email,
            'sub' => $client_email,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/firebase.messaging'
        ]);

        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature_input = $header_encoded . '.' . $payload_encoded;

        if (!openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            return new WP_Error('jwt_error', 'Failed to sign JWT');
        }

        $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $signature_input . '.' . $signature_encoded;
    }

    /**
     * Exchange JWT token for Google access token
     * 
     * @param string $jwt
     * @return array|WP_Error
     */
    private function exchange_jwt_for_token($jwt) {
        $args = [
            'method' => 'POST',
            'timeout' => 10,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]
        ];

        $response = wp_remote_post('https://oauth2.googleapis.com/token', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            error_log('Firebase Token Error: ' . wp_remote_retrieve_body($response));
            return new WP_Error('token_error', 'Failed to get access token from Firebase');
        }

        return $body;
    }

    /**
     * Send new order notification to Firebase
     * 
     * @param int $order_id WooCommerce Order ID
     * @param string $order_type Order type (e.g., 'dine-in', 'delivery', 'takeaway')
     * @return bool|WP_Error
     */
    public function send_order_notification($order_id, $order_type = 'takeaway') {
        
        if (!$this->is_configured()) {
            return new WP_Error(
                'firebase_not_configured',
                'Firebase is not configured. Please upload service account key in plugin settings.',
                ['status' => 500]
            );
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error(
                'order_not_found',
                'Order not found.',
                ['status' => 404]
            );
        }

        // Prepare order data for backend
        $order_data = [
            'order_id' => (int) $order_id,
            'order_type' => sanitize_text_field($order_type),
            'timestamp' => current_time('timestamp'),
        ];

        // Send to Firebase backend
        return $this->send_to_firebase_backend('orders/new', $order_data);
    }

    /**
     * Send data to Firebase Firestore via REST API
     * Creates a new document with an explicit or auto-generated ID
     * 
     * @param string $collection_path Firebase collection path (e.g., 'orders')
     * @param array $data Data to send
     * @param string $document_id Optional explicit document ID
     * @return bool|WP_Error
     */
    public function send_to_firebase_backend($collection_path, $data, $document_id = '') {
        
        if (!$this->is_configured()) {
            return new WP_Error('firebase_not_configured', 'Firebase credentials missing.');
        }

        // Get access token
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            error_log('Firebase Token Error: ' . $token->get_error_message());
            return $token;
        }

        // Clean collection path
        $collection_path = ltrim($collection_path, '/');
        
        // Build Firebase Firestore REST API URL
        $firebase_url = 'https://firestore.googleapis.com/v1/projects/' . $this->project_id . '/databases/(default)/documents/' . $collection_path;
        
        // If document_id is provided, use createDocument endpoint with documentId parameter
        if (!empty($document_id)) {
            $document_id = sanitize_text_field($document_id);
            $firebase_url = $firebase_url . '?documentId=' . urlencode($document_id);
            $method = 'POST';
        } else {
            // Auto-generated ID
            $method = 'POST';
        }

        // Prepare request
        $args = [
            'method' => $method,
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'fields' => $this->prepare_firestore_fields($data)
            ])
        ];

        // Send request
        $response = wp_remote_request($firebase_url, $args);

        if (is_wp_error($response)) {
            error_log('Firebase Error: ' . $response->get_error_message());
            return new WP_Error('firebase_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $error_body = wp_remote_retrieve_body($response);
            error_log('Firebase Response Error (' . $response_code . '): ' . $error_body);
            return new WP_Error('firebase_response_error', 'Firebase returned status ' . $response_code);
        }

        return true;
    }

    /**
     * Convert data array to Firestore field format
     * 
     * @param array $data
     * @return array
     */
    private function prepare_firestore_fields($data) {
        $fields = [];
        
        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $fields[$key] = ['integerValue' => (string) $value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } else {
                $fields[$key] = ['stringValue' => (string) $value];
            }
        }
        
        return $fields;
    }

    /**
     * Update Firebase service account key
     * 
     * @param string $json_key JSON service account key
     * @return bool|WP_Error
     */
    public function update_credentials($json_key) {
        $decoded = json_decode($json_key, true);
        
        if (!is_array($decoded) || empty($decoded['project_id']) || empty($decoded['private_key'])) {
            return new WP_Error('invalid_key', 'Invalid Firebase service account key format');
        }

        update_option('shish_cafe_firebase_key_json', sanitize_text_field($json_key));
        $this->load_service_account();
        
        return true;
    }

    /**
     * Send Firebase Cloud Messaging (FCM) push notification
     * 
     * @param string $fcm_token Device FCM token
     * @param array $notification Notification data (title, body, etc.)
     * @param array $data Custom data payload
     * @return bool|WP_Error
     */
    public function send_fcm_message($fcm_token, $notification = [], $data = []) {
        
        if (!$this->is_configured()) {
            return new WP_Error('firebase_not_configured', 'Firebase credentials missing.');
        }

        if (empty($fcm_token)) {
            return new WP_Error('missing_token', 'FCM token is required.');
        }

        // Get access token
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            error_log('Firebase Token Error: ' . $token->get_error_message());
            return $token;
        }

        // Build FCM API URL
        $fcm_url = 'https://fcm.googleapis.com/v1/projects/' . $this->project_id . '/messages:send';

        // Prepare message payload
        $message = [
            'token' => $fcm_token,
        ];

        if (!empty($notification)) {
            $message['notification'] = $notification;
        }

        if (!empty($data)) {
            $message['data'] = $data;
        }

        $payload = [
            'message' => $message
        ];

        // Prepare request
        $args = [
            'method' => 'POST',
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload)
        ];

        // Send request
        $response = wp_remote_post($fcm_url, $args);

        if (is_wp_error($response)) {
            error_log('Firebase FCM Error: ' . $response->get_error_message());
            return new WP_Error('fcm_error', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $error_body = wp_remote_retrieve_body($response);
            error_log('Firebase FCM Response Error (' . $response_code . '): ' . $error_body);
            return new WP_Error('fcm_response_error', 'FCM returned status ' . $response_code);
        }

        return true;
    }

    /**
     * Get current Firebase configuration status
     * 
     * @return array
     */
    public function get_config_status() {
        return [
            'configured' => $this->is_configured(),
            'project_id' => !empty($this->project_id) ? $this->project_id : 'Not set',
            'key_loaded' => !empty($this->service_account),
        ];
    }
}
