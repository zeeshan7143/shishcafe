<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'shish_cafe_register_user_routes');

function shish_cafe_register_user_routes()
{
    register_rest_route('user-manager/v1', '/lookup', [
        'methods' => 'GET',
        'callback' => 'shish_cafe_user_lookup',
        'permission_callback' => 'shish_cafe_validate_auth_key',
    ]);
}

function shish_cafe_user_lookup(WP_REST_Request $request)
{
    $payload = Shish_Cafe_User_Service::build_user_payload([
        'user_id' => $request->get_param('user_id'),
        'email' => $request->get_param('email'),
        'phone' => $request->get_param('phone'),
    ]);

    if (is_wp_error($payload)) {
        return $payload;
    }

    return new WP_REST_Response($payload, 200);
}
