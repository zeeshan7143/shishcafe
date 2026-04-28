<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shish_Cafe_Locations_Admin
{
    const OPTION_KEY = 'shish_cafe_locations';
    const PAGE_SLUG = 'shish-cafe-add-locations';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_users_submenu']);
        add_action('admin_post_shish_cafe_add_location', [__CLASS__, 'handle_add_location']);
        add_action('admin_post_shish_cafe_delete_location', [__CLASS__, 'handle_delete_location']);
    }

    public static function register_users_submenu()
    {
        add_users_page(
            'Add Locations',
            'Add Locations',
            'list_users',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page()
    {
        if (!current_user_can('list_users')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'shish-cafe-api'));
        }

        $locations = self::get_locations();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Add Locations', 'shish-cafe-api'); ?></h1>
            <p><?php echo esc_html__('Manage the list of registered locations available in user profiles.', 'shish-cafe-api'); ?></p>

            <?php settings_errors('shish_cafe_locations'); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 540px; margin-top: 20px;">
                <input type="hidden" name="action" value="shish_cafe_add_location" />
                <?php wp_nonce_field('shish_cafe_add_location'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="shish-cafe-location-name"><?php echo esc_html__('Location Name', 'shish-cafe-api'); ?></label>
                        </th>
                        <td>
                            <input
                                name="location_name"
                                type="text"
                                id="shish-cafe-location-name"
                                class="regular-text"
                                maxlength="120"
                                required
                            />
                            <p class="description"><?php echo esc_html__('Example: Leith Walk, Morningside, City Centre.', 'shish-cafe-api'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Add Location', 'shish-cafe-api')); ?>
            </form>

            <hr style="margin: 28px 0;" />

            <h2><?php echo esc_html__('Registered Locations', 'shish-cafe-api'); ?></h2>

            <?php if (empty($locations)) : ?>
                <p><?php echo esc_html__('No locations added yet.', 'shish-cafe-api'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width: 680px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Location', 'shish-cafe-api'); ?></th>
                            <th style="width: 120px;"><?php echo esc_html__('Action', 'shish-cafe-api'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location) : ?>
                            <tr>
                                <td><?php echo esc_html($location); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this location?', 'shish-cafe-api')); ?>');">
                                        <input type="hidden" name="action" value="shish_cafe_delete_location" />
                                        <input type="hidden" name="location_name" value="<?php echo esc_attr($location); ?>" />
                                        <?php wp_nonce_field('shish_cafe_delete_location'); ?>
                                        <button type="submit" class="button button-link-delete"><?php echo esc_html__('Delete', 'shish-cafe-api'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_add_location()
    {
        if (!current_user_can('list_users')) {
            wp_die(esc_html__('You do not have permission to do this action.', 'shish-cafe-api'));
        }

        check_admin_referer('shish_cafe_add_location');

        $name = isset($_POST['location_name']) ? sanitize_text_field((string) wp_unslash($_POST['location_name'])) : '';
        $name = trim($name);

        if ($name === '') {
            add_settings_error('shish_cafe_locations', 'empty_location', __('Location name is required.', 'shish-cafe-api'), 'error');
            set_transient('settings_errors', get_settings_errors('shish_cafe_locations'), 30);
            wp_safe_redirect(self::get_page_url());
            exit;
        }

        $locations = self::get_locations();

        if (in_array($name, $locations, true)) {
            add_settings_error('shish_cafe_locations', 'duplicate_location', __('This location already exists.', 'shish-cafe-api'), 'error');
            set_transient('settings_errors', get_settings_errors('shish_cafe_locations'), 30);
            wp_safe_redirect(self::get_page_url());
            exit;
        }

        $locations[] = $name;
        $locations = self::sanitize_locations($locations);
        update_option(self::OPTION_KEY, $locations, false);

        add_settings_error('shish_cafe_locations', 'location_added', __('Location added successfully.', 'shish-cafe-api'), 'updated');
        set_transient('settings_errors', get_settings_errors('shish_cafe_locations'), 30);

        wp_safe_redirect(self::get_page_url());
        exit;
    }

    public static function handle_delete_location()
    {
        if (!current_user_can('list_users')) {
            wp_die(esc_html__('You do not have permission to do this action.', 'shish-cafe-api'));
        }

        check_admin_referer('shish_cafe_delete_location');

        $name = isset($_POST['location_name']) ? sanitize_text_field((string) wp_unslash($_POST['location_name'])) : '';
        $name = trim($name);

        $locations = array_values(array_filter(
            self::get_locations(),
            static function ($item) use ($name) {
                return $item !== $name;
            }
        ));

        update_option(self::OPTION_KEY, self::sanitize_locations($locations), false);

        add_settings_error('shish_cafe_locations', 'location_deleted', __('Location deleted.', 'shish-cafe-api'), 'updated');
        set_transient('settings_errors', get_settings_errors('shish_cafe_locations'), 30);

        wp_safe_redirect(self::get_page_url());
        exit;
    }

    public static function get_locations()
    {
        $locations = get_option(self::OPTION_KEY, []);

        if (!is_array($locations)) {
            return [];
        }

        return self::sanitize_locations($locations);
    }

    private static function sanitize_locations(array $locations)
    {
        $clean = [];

        foreach ($locations as $location) {
            $value = trim(sanitize_text_field((string) $location));
            if ($value === '') {
                continue;
            }
            $clean[] = $value;
        }

        $clean = array_values(array_unique($clean));

        return $clean;
    }

    private static function get_page_url()
    {
        return admin_url('users.php?page=' . self::PAGE_SLUG);
    }
}
