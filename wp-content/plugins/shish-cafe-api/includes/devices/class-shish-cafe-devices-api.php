<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'shish_cafe_register_device_api_routes');

function shish_cafe_register_device_api_routes()
{
    register_rest_route('/v1', '/register-device', [
        'methods' => ['GET', 'POST'],
        'callback' => 'shish_cafe_register_device',
        'permission_callback' => '__return_true',
    ]);
}

/**
 * Register device with FCM token
 * Required parameters: token, location, user_id
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
/**
 * Main handler - routes GET and POST to separate functions
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function shish_cafe_register_device(WP_REST_Request $request)
{
    $method = $request->get_method();

    if ($method === 'GET') {
        return shish_cafe_get_user_data($request);
    } elseif ($method === 'POST') {
        return shish_cafe_update_user_data($request);
    }

    return new WP_Error(
        'invalid_method',
        'Invalid request method. Only GET and POST are allowed.',
        ['status' => 405]
    );
}

/**
 * GET: Retrieve user data
 * Required parameters: location, user_id
 * Optional parameters: role
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function shish_cafe_get_user_data(WP_REST_Request $request)
{
    $params = $request->get_query_params();

    // Define allowed parameters
    $allowed_params = ['location', 'user_id', 'role'];
    
    // Check for unexpected parameters
    $unexpected_params = array_diff_key($params, array_flip($allowed_params));
    
    if (!empty($unexpected_params)) {
        $extra_params = implode(', ', array_keys($unexpected_params));
        return new WP_Error(
            'invalid_parameters',
            'Invalid parameters: ' . $extra_params . '. Only location, user_id, and role are allowed.',
            ['status' => 400]
        );
    }

    // Get parameters
    $location = isset($params['location']) ? $params['location'] : '';
    $user_id = isset($params['user_id']) ? $params['user_id'] : '';
    $role = isset($params['role']) ? $params['role'] : '';

    // Sanitize parameters
    $location = sanitize_text_field($location);
    $user_id = intval($user_id);
    $role = sanitize_text_field($role);

    // Validate required parameters
    if (empty($location)) {
        return new WP_Error(
            'missing_location',
            'Location is required.',
            ['status' => 400]
        );
    }

    if (empty($user_id) || $user_id <= 0) {
        return new WP_Error(
            'missing_user_id',
            'Valid user_id is required.',
            ['status' => 400]
        );
    }

    // Get user
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'User not found.',
        ], 404);
    }

    // Get user metadata and roles
    $user_roles = is_array($user->roles) ? $user->roles : [];
    $user_location = get_user_meta($user->ID, 'shish_cafe_user_location', true);
    $user_fcm_token = get_user_meta($user->ID, 'shish_cafe_fcm_token', true);

    // Validate that provided location matches user's stored location
    if ($user_location !== $location) {
        return new WP_Error(
            'location_mismatch',
            'The provided location does not match the user\'s assigned location. User location is: ' . $user_location,
            ['status' => 401]
        );
    }

    // Validate optional role parameter if provided
    if (!empty($role)) {
        // Convert user roles to display format for comparison
        $displayed_roles = [];
        foreach ($user_roles as $user_role) {
            if ($user_role === 'shinsh_manager' || $user_role === 'shish_manager') {
                $displayed_roles[] = 'Manager';
            } else {
                $displayed_roles[] = $user_role;
            }
        }

        // Check if provided role matches any of user's actual roles
        if (!in_array($role, $displayed_roles, true)) {
            return new WP_Error(
                'role_mismatch',
                'The provided role does not match the user\'s assigned roles. User roles are: ' . implode(', ', $displayed_roles),
                ['status' => 401]
            );
        }
    }

    // Convert shish_manager role to Manager
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

/**
 * POST: Update FCM token for user
 * Required parameters: fcm_token, user_id
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function shish_cafe_update_user_data(WP_REST_Request $request)
{
    $body = $request->get_json_params();

    // Check if body is empty
    if (!is_array($body) || empty($body)) {
        return new WP_Error(
            'empty_body',
            'Request body is required. Please provide fcm_token and user_id.',
            ['status' => 400]
        );
    }

    // Define allowed parameters
    $allowed_params = ['fcm_token', 'user_id'];

    // Check for unexpected parameters
    $unexpected_params = array_diff_key($body, array_flip($allowed_params));

    if (!empty($unexpected_params)) {
        $extra_params = implode(', ', array_keys($unexpected_params));
        return new WP_Error(
            'invalid_parameters',
            'Invalid parameters: ' . $extra_params . '. Only fcm_token and user_id are allowed.',
            ['status' => 400]
        );
    }

    // Get parameters
    $token = isset($body['fcm_token']) ? $body['fcm_token'] : '';
    $user_id = isset($body['user_id']) ? $body['user_id'] : '';

    // Sanitize parameters
    $token = sanitize_text_field($token);
    $user_id = intval($user_id);

    // Validate required parameters
    if (empty($token)) {
        return new WP_Error(
            'missing_fcm_token',
            'FCM Token is required.',
            ['status' => 400]
        );
    }

    if (empty($user_id) || $user_id <= 0) {
        return new WP_Error(
            'missing_user_id',
            'Valid user_id is required.',
            ['status' => 400]
        );
    }

    // Get user
    $user = get_user_by('ID', $user_id);

    if (!$user) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'User not found.',
        ], 404);
    }

    // Update only the FCM token
    update_user_meta($user_id, 'shish_cafe_fcm_token', $token);

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
