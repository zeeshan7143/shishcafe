<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shish_Cafe_Manager_Role
{
    const ROLE_KEY = 'Orders Manager';

    public static function init()
    {
        add_action('init', [__CLASS__, 'ensure_role_exists']);
        add_action('admin_init', [__CLASS__, 'block_admin_access_for_manager']);

        add_filter('editable_roles', [__CLASS__, 'move_manager_role_to_end']);
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar_for_manager']);
        add_filter('login_redirect', [__CLASS__, 'force_frontend_login_redirect'], 10, 3);

        add_action('personal_options_update', [__CLASS__, 'block_profile_updates']);
        add_action('edit_user_profile_update', [__CLASS__, 'block_profile_updates']);

        add_action('template_redirect', [__CLASS__, 'block_frontend_account_edit']);
    }

    public static function ensure_role_exists()
    {
        // Remove old 'Manager' role if it still exists and migrate users
        $old_manager_role = get_role('Manager');
        if ($old_manager_role) {
            // Get all users with the old 'Manager' role
            $users = get_users(['role' => 'Manager']);
            
            // Migrate each user to the new 'Orders Manager' role
            foreach ($users as $user) {
                $user->set_role('Orders Manager');
            }
            
            // Remove the old 'Manager' role
            remove_role('Manager');
        }

        if (get_role(self::ROLE_KEY)) {
            return;
        }

        add_role(
            self::ROLE_KEY,
            __('Orders Manager', 'shish-cafe-api'),
            [
                'read' => true,
            ]
        );
    }

    public static function block_admin_access_for_manager()
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (!self::is_manager_user()) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        wp_safe_redirect(home_url('/'));
        exit;
    }

    public static function hide_admin_bar_for_manager($show)
    {
        if (self::is_manager_user()) {
            return false;
        }

        return $show;
    }

    public static function move_manager_role_to_end($roles)
    {
        // Remove old legacy shinsh_manager role if it exists
        if (isset($roles['shinsh_manager'])) {
            unset($roles['shinsh_manager']);
        }

        if (!is_array($roles) || !isset($roles[self::ROLE_KEY])) {
            return $roles;
        }

        $manager_role = $roles[self::ROLE_KEY];
        unset($roles[self::ROLE_KEY]);
        $roles[self::ROLE_KEY] = $manager_role;

        return $roles;
    }

    public static function force_frontend_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if ($user instanceof WP_User && in_array(self::ROLE_KEY, (array) $user->roles, true)) {
            return home_url('/');
        }

        return $redirect_to;
    }

    public static function block_profile_updates($user_id)
    {
        if (!is_user_logged_in()) {
            return;
        }

        if ((int) get_current_user_id() !== (int) $user_id) {
            return;
        }

        if (!self::is_manager_user()) {
            return;
        }

        wp_die(
            esc_html__('Manager users are view-only and cannot update profile details.', 'shish-cafe-api'),
            esc_html__('Permission denied', 'shish-cafe-api'),
            ['response' => 403]
        );
    }

    public static function block_frontend_account_edit()
    {
        if (!is_user_logged_in() || !self::is_manager_user()) {
            return;
        }

        if (!function_exists('is_account_page')) {
            return;
        }

        $is_account_page = (bool) call_user_func('is_account_page');
        if (!$is_account_page) {
            return;
        }

        if (!function_exists('is_wc_endpoint_url')) {
            return;
        }

        $is_edit_account_endpoint = (bool) call_user_func('is_wc_endpoint_url', 'edit-account');
        if (!$is_edit_account_endpoint) {
            return;
        }

        wp_safe_redirect(home_url('/'));
        exit;
    }

    private static function is_manager_user($user = null)
    {
        if (!$user instanceof WP_User) {
            $user = wp_get_current_user();
        }

        if (!$user instanceof WP_User || empty($user->ID)) {
            return false;
        }

        return in_array(self::ROLE_KEY, (array) $user->roles, true);
    }
}

Shish_Cafe_Manager_Role::init();
