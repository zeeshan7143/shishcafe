<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'shish_cafe_register_users_api_routes');

function shish_cafe_register_users_api_routes()
{
    register_rest_route('v1', '/user-login', [
        'methods' => 'POST',
        'callback' => 'shish_cafe_search_users',
        'permission_callback' => 'shish_cafe_validate_auth_key',
    ]);
}

/**
 * Authenticate user by username/email and password (POST request)
 * Required body parameters: (email OR username) AND password
 * Only allowed parameters: username, email, password
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function shish_cafe_search_users(WP_REST_Request $request)
{
    // Get all body parameters
    $all_params = $request->get_json_params();
    
    if (!is_array($all_params)) {
        $all_params = [];
    }
    
    // Define allowed parameters
    $allowed_params = ['username', 'email', 'password'];
    
    // Check for unexpected parameters
    $unexpected_params = array_diff_key($all_params, array_flip($allowed_params));
    
    if (!empty($unexpected_params)) {
        $extra_params = implode(', ', array_keys($unexpected_params));
        return new WP_Error(
            'invalid_parameters',
            'Invalid parameters: ' . $extra_params . '. Only username, email, and password are allowed.',
            ['status' => 400]
        );
    }

    // Get body parameters
    $email = isset($all_params['email']) ? $all_params['email'] : '';
    $username = isset($all_params['username']) ? $all_params['username'] : '';
    $password = isset($all_params['password']) ? $all_params['password'] : '';

    // Sanitize parameters
    $email = !empty($email) ? sanitize_email($email) : '';
    $username = !empty($username) ? sanitize_text_field($username) : '';
    $password = !empty($password) ? sanitize_text_field($password) : '';

    // Validate required parameters
    // Either email or username is required
    if (empty($email) && empty($username)) {
        return new WP_Error(
            'missing_identifier',
            'Either email or username is required.',
            ['status' => 400]
        );
    }

    // Password is required
    if (empty($password)) {
        return new WP_Error(
            'missing_password',
            'Password is required.',
            ['status' => 400]
        );
    }

    // Find user by email or username
    $user = null;

    if (!empty($username)) {
        $user = get_user_by('login', $username);
    }

    if (!$user && !empty($email)) {
        $user = get_user_by('email', $email);
    }

    // If user not found
    if (!$user) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'No user found with the provided credentials.',
        ], 404);
    }

    // Verify password
    if (!wp_check_password($password, $user->user_pass)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid username or password. Please check your credentials.',
        ], 401);
    }

    // Build and return the response
    $user_roles = is_array($user->roles) ? $user->roles : [];
    $user_location = get_user_meta($user->ID, 'shish_cafe_user_location', true);
    $user_fcm_token = get_user_meta($user->ID, 'shish_cafe_fcm_token', true);

    // Convert shish_manager role to Orders Manager
    $displayed_roles = [];
    foreach ($user_roles as $role) {
        if ($role === 'shinsh_manager' || $role === 'shish_manager') {
            $displayed_roles[] = 'Orders Manager';
        } else {
            $displayed_roles[] = $role;
        }
    }

    $response = [
        'success' => true,
        'user' => [
            'id' => (int) $user->ID,
            'email' => (string) $user->user_email,
            'username' => (string) $user->user_login,
            'registered_at' => (string) $user->user_registered,
            'roles' => !empty($displayed_roles) ? implode(', ', $displayed_roles) : '',
            'location' => (string) $user_location,
            'fcm_token' => (string) $user_fcm_token,
        ],
    ];

    return new WP_REST_Response($response, 200);
}
