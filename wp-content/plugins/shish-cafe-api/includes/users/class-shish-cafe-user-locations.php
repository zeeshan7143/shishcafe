<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shish_Cafe_User_Locations
{
    const META_KEY = 'shish_cafe_user_location';
    const MANAGER_ROLE = 'Orders Manager';

    public static function init()
    {
        add_action('show_user_profile', [__CLASS__, 'render_user_location_field']);
        add_action('edit_user_profile', [__CLASS__, 'render_user_location_field']);
        add_action('user_new_form', [__CLASS__, 'render_new_user_location_field']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        add_action('personal_options_update', [__CLASS__, 'save_user_location']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_user_location']);
        add_action('user_register', [__CLASS__, 'save_user_location']);

        add_filter('manage_users_columns', [__CLASS__, 'add_users_column']);
        add_filter('manage_users_custom_column', [__CLASS__, 'render_users_column'], 10, 3);
    }

    public static function enqueue_admin_assets($hook_suffix)
    {
        // Enqueue on both new user and edit user pages
        if ($hook_suffix === 'user-new.php' || $hook_suffix === 'user-edit.php') {
            wp_enqueue_script(
                'shish-cafe-user-location',
                plugins_url('../../assets/js/shish-cafe-user-location.js', __FILE__),
                [],
                '1.0.0',
                true
            );
        }
    }

    public static function render_user_location_field($user)
    {
        if (!($user instanceof WP_User)) {
            return;
        }

        // Always render the field on edit page - JavaScript will show/hide based on role
        $selected = (string) get_user_meta($user->ID, self::META_KEY, true);
        self::render_field_markup($selected);
    }

    public static function render_new_user_location_field($operation)
    {
        if ($operation !== 'add-new-user') {
            return;
        }

        self::render_field_markup('');
    }

    private static function render_field_markup($selected)
    {
        $locations = Shish_Cafe_Locations_Admin::get_locations();
        $current_selection = $selected;
        // The JavaScript will handle visibility based on role selection
        ?>
        <div id="shish-cafe-location-wrap" data-manager-role="<?php echo esc_attr(self::MANAGER_ROLE); ?>">
        <h2 style="display: none;"><?php echo esc_html__('Shish Cafe', 'shish-cafe-api'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="shish-cafe-user-location"><?php echo esc_html__('Location', 'shish-cafe-api'); ?></label></th>
                <td>
                    <select name="shish_cafe_user_location" id="shish-cafe-user-location">
                        <option value="" <?php echo $current_selection === '' ? 'selected' : ''; ?>>
                            <?php echo esc_html__('— Select Location —', 'shish-cafe-api'); ?>
                        </option>
                        <?php if (empty($locations)) : ?>
                            <option value="" disabled><?php echo esc_html__('No locations available', 'shish-cafe-api'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($locations as $location) : ?>
                            <option value="<?php echo esc_attr($location); ?>" <?php selected($current_selection, $location); ?>>
                                <?php echo esc_html($location); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Select a registered location for this manager user.', 'shish-cafe-api'); ?></p>
                </td>
            </tr>
        </table>
        </div>
        <?php
    }

    public static function save_user_location($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $location = isset($_POST['shish_cafe_user_location'])
            ? sanitize_text_field((string) wp_unslash($_POST['shish_cafe_user_location']))
            : '';

        $is_manager_target = self::is_manager_target_user($user_id);
        if (!$is_manager_target) {
            delete_user_meta($user_id, self::META_KEY);
            return;
        }

        $locations = Shish_Cafe_Locations_Admin::get_locations();

        if ($location === '' || !in_array($location, $locations, true)) {
            delete_user_meta($user_id, self::META_KEY);
            return;
        }

        update_user_meta($user_id, self::META_KEY, $location);
    }

    private static function is_manager_target_user($user_id)
    {
        // First check if role is being set in the form submission
        if (isset($_POST['role'])) {
            $posted_role = sanitize_key((string) wp_unslash($_POST['role']));
            if ($posted_role === self::MANAGER_ROLE) {
                return true;
            }
        }

        // Check if user already has the manager role
        $user = get_user_by('id', (int) $user_id);
        if (!$user instanceof WP_User) {
            return false;
        }

        return in_array(self::MANAGER_ROLE, (array) $user->roles, true);
    }

    public static function add_users_column($columns)
    {
        $columns['shish_cafe_user_location'] = __('Location', 'shish-cafe-api');
        return $columns;
    }

    public static function render_users_column($value, $column_name, $user_id)
    {
        if ($column_name !== 'shish_cafe_user_location') {
            return $value;
        }

        $location = (string) get_user_meta($user_id, self::META_KEY, true);

        return $location !== '' ? esc_html($location) : '&mdash;';
    }
}

Shish_Cafe_User_Locations::init();
