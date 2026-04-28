<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Firebase Admin Settings Page
 * Allows admins to upload Firebase service account JSON key
 */
class Shish_Cafe_Firebase_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'save_firebase_settings'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            'Shish Cafe Settings',
            'Shish Cafe',
            'manage_options',
            'shish-cafe-settings',
            array($this, 'render_settings_page'),
            'dashicons-restaurant',
            58
        );

        add_submenu_page(
            'shish-cafe-settings',
            'Firebase Configuration',
            'Firebase Config',
            'manage_options',
            'shish-cafe-firebase',
            array($this, 'render_firebase_settings_page')
        );
    }

    /**
     * Render Firebase settings page
     */
    public function render_firebase_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'class-firebase-service.php';
        $firebase = new Shish_Cafe_Firebase_Service();
        $config = $firebase->get_config_status();
        $json_key = get_option('shish_cafe_firebase_key_json', '');
        ?>
        <div class="wrap">
            <h1>Firebase Configuration</h1>
            <p>Upload your Firebase service account JSON key to enable order notifications.</p>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('shish_cafe_firebase_save', 'shish_cafe_firebase_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="firebase_key_json">Firebase Service Account Key (JSON)</label>
                        </th>
                        <td>
                            <textarea 
                                id="firebase_key_json" 
                                name="firebase_key_json" 
                                class="large-text code" 
                                rows="8"
                                placeholder='{"type": "service_account", "project_id": "...", ...}'
                            ><?php echo esc_textarea($json_key); ?></textarea>
                            <p class="description">
                                Paste your complete Firebase service account JSON key here. Get it from 
                                <a href="https://console.firebase.google.com/project/_/settings/serviceaccounts/adminsdk" target="_blank">
                                    Firebase Console → Project Settings → Service Accounts
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Firebase Configuration'); ?>
            </form>

            <div class="notice notice-info">
                <p><strong>How to get your Firebase JSON key:</strong></p>
                <ol>
                    <li>Go to <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a></li>
                    <li>Select your project</li>
                    <li>Go to Project Settings (gear icon) → Service Accounts</li>
                    <li>Click "Generate New Private Key"</li>
                    <li>Copy the entire JSON content and paste it above</li>
                </ol>
            </div>

            <div class="notice <?php echo $config['configured'] ? 'notice-success' : 'notice-warning'; ?>">
                <p><strong>Configuration Status:</strong></p>
                <ul>
                    <li><?php echo $config['configured'] ? '✓' : '✗'; ?> Status: <?php echo $config['configured'] ? '✓ Configured' : '✗ Not Configured'; ?></li>
                    <li>Project ID: <?php echo esc_html($config['project_id']); ?></li>
                    <li>Key Loaded: <?php echo $config['key_loaded'] ? '✓ Yes' : '✗ No'; ?></li>
                </ul>
            </div>

            <?php if ($config['configured']): ?>
            <div class="notice notice-info">
                <p><strong>Orders will be sent to Firebase with:</strong></p>
                <ul>
                    <li>order_id</li>
                    <li>order_type</li>
                    <li>timestamp</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render main settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Shish Cafe Settings</h1>
            <p>Configure your Shish Cafe API settings here.</p>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=shish-cafe-firebase')); ?>">Firebase Configuration</a></li>
            </ul>
        </div>
        <?php
    }

    /**
     * Save Firebase settings
     */
    public function save_firebase_settings() {
        if (!isset($_POST['shish_cafe_firebase_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['shish_cafe_firebase_nonce'], 'shish_cafe_firebase_save')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (isset($_POST['firebase_key_json'])) {
            // Don't sanitize the JSON textarea - just get raw input and validate it
            $json_key = isset($_POST['firebase_key_json']) ? $_POST['firebase_key_json'] : '';
            $json_key = trim($json_key);
            
            // Validate JSON
            $decoded = json_decode($json_key, true);
            
            if (!is_array($decoded)) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e('Invalid JSON format!', 'shish-cafe-api'); ?></p>
                    </div>
                    <?php
                });
                return;
            }

            if (empty($decoded['project_id']) || empty($decoded['private_key'])) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e('Firebase key must contain "project_id" and "private_key" fields.', 'shish-cafe-api'); ?></p>
                    </div>
                    <?php
                });
                return;
            }

            // Store the raw JSON - don't sanitize as it breaks the private key
            update_option('shish_cafe_firebase_key_json', $json_key);

            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Firebase configuration saved successfully!', 'shish-cafe-api'); ?></p>
                </div>
                <?php
            });
        }
    }
}
