<?php

namespace DebugLogConfigTool\Classes;

use DebugLogConfigTool\Controllers\NotificationController;
use DebugLogConfigTool\Controllers\ConfigController;
use Exception;

/**
 * Main Bootstrap class for Debug Log Config Tool plugin
 */
final class DLCT_Bootstrap
{
    const DLCT_LOG = 'dlct_logs';

    /**
     * All registered keys.
     *
     * @var array
     */
    protected static $registry = [];

    /**
     * @var DLCT_Bootstrap
     */
    protected static $instance;

    /**
     * Get singleton instance
     *
     * @return DLCT_Bootstrap
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind a new key/value into the container.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function bind($key, $value)
    {
        static::$registry[$key] = $value;
    }

    /**
     * Retrieve a value from the registry.
     *
     * @param string $key
     * @return mixed
     * @throws Exception
     */
    public static function get($key)
    {
        if (!array_key_exists($key, static::$registry)) {
            throw new Exception("No {$key} is bound in the container.");
        }
        return static::$registry[$key];
    }

    /**
     * Plugin activation hook
     */
    public static function activate()
    {
        (new Activator())->run();
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate()
    {
        (new DeActivator())->run();
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        if (!is_admin()) {
            return;
        }

        $this->loadTextDomain();
        $this->registerHooks();
        $this->initializeComponents();
    }

    /**
     * Register all WordPress hooks
     */
    private function registerHooks()
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('wp_before_admin_bar_render', [$this, 'adminTopMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('wpdd_admin_page_render', [$this, 'showMsg']);
        add_action('admin_init', [$this, 'msgDismissed']);
        add_action('wp_ajax_dlct_toggle_debug', [$this, 'toggleDebug']);
        add_action('wp_dashboard_setup', [$this, 'dashboardWidget']);

        if (isset($_GET['page']) && $_GET['page'] === self::DLCT_LOG) {
            add_filter('admin_footer_text', [$this, 'customFooterText']);
        }
    }

    /**
     * Initialize plugin components
     */
    private function initializeComponents()
    {
        (new NotificationController())->boot();
        (new NotificationController())->scheduleCron();
        (new AjaxHandler())->boot();
        $this->pluginActionMenu();
    }

    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain()
    {
        load_plugin_textdomain(
            'debug-log-config-tool',
            false,
            dirname(plugin_basename(DLCT_PLUGIN_MAIN_FILE)) . '/languages'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts()
    {
        // Add CSS for the debug indicator in admin bar
        wp_add_inline_style('admin-bar', $this->getAdminBarStyles());

        // Add JavaScript for AJAX toggle
        wp_add_inline_script('jquery', $this->getDebugToggleScript());

        // Load plugin-specific assets for the admin page
        if (isset($_GET['page']) && sanitize_text_field($_GET['page']) === self::DLCT_LOG) {
            $this->loadPluginAssets();
        }
    }

    /**
     * Get admin bar indicator styles
     *
     * @return string CSS styles
     */
    private function getAdminBarStyles()
    {
        return '
            #wp-admin-bar-debug_log_config_tool_id > .ab-item {
                display: flex !important;
                align-items: center;
            }
            .dlct-debug-enabled, .dlct-debug-disabled {
                display: inline-block;
                width: 10px!important;
                height: 10px!important;
                border-radius: 50%;
                margin-right: 5px!important;
            }
            .dlct-debug-enabled {
                background-color: #46b450;
                box-shadow: 0 0 5px #46b450;
            }
            .dlct-debug-disabled {
                background-color: #dc3232;
                box-shadow: 0 0 5px #dc3232;
            }
        ';
    }

    /**
     * Get debug toggle JavaScript
     *
     * @return string JavaScript code
     */
    /**
     * Get simplified debug toggle JavaScript
     *
     * @return string JavaScript code
     */
    private function getDebugToggleScript()
    {
        return '
    jQuery(document).ready(function($) {
        $(".dlct-toggle-debug").on("click", function(e) {
            e.preventDefault();

            var $toggleButton = $(this);
            var originalHtml = $toggleButton.find(".ab-item").html() || $toggleButton.html();

            // Store position and parent info
            var $parent = $toggleButton.parent();
            var nextElements = $toggleButton.next().length ? $toggleButton.next() : null;

            // Add loading spinner without modifying structure
            $toggleButton.find(".ab-item").addClass("dlct-loading")
                         .append(\' <span class="dlct-spinner" style="display:inline-block;width:10px;height:10px;border:2px solid rgba(255,255,255,0.3);border-radius:50%;border-top-color:#fff;animation:dlct-spin 1s linear infinite;"></span>\');

            // Add spin animation if needed
            if (!$("#dlct-spinner-style").length) {
                $("head").append(\'<style id="dlct-spinner-style">@keyframes dlct-spin{to{transform:rotate(360deg)}}</style>\');
            }

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "dlct_toggle_debug",
                    nonce: "' . wp_create_nonce('dlct_toggle_debug_nonce') . '"
                },
                success: function(response) {
                    // Remove spinner
                    $toggleButton.find(".dlct-spinner").remove();
                    $toggleButton.find(".ab-item").removeClass("dlct-loading");

                    if (response.success) {
                        // Update only the text portion carefully
                        $toggleButton.find(".ab-item").contents().filter(function() {
                            return this.nodeType === 3; // Text nodes only
                        }).first().replaceWith(response.data.toggle_text);

                        // Update indicator class (the status indicator)
                        var $indicator = $("#wp-admin-bar-dlct_logs_id > a span");
                        console.log(response.data)
                        if (response.data.debug_enabled == true) {
                                console.log($indicator)

                            $indicator.removeClass("dlct-debug-disabled").addClass("dlct-debug-enabled");
                        } else {
                            $indicator.removeClass("dlct-debug-enabled").addClass("dlct-debug-disabled");
                        }
                        $(document).trigger("dlct:debug_status_changed", {
                            debug_enabled: response.data.debug_enabled,
                        });

                        // Show success message
                        if (typeof wp !== "undefined" && wp.notices) {
                            wp.notices.success(response.data.message);
                        } else {
                            alert(response.data.message);
                        }
                    } else {
                        // Restore original content safely
                        if ($toggleButton.find(".ab-item").length) {
                            $toggleButton.find(".ab-item").html(originalHtml);
                        } else {
                            $toggleButton.html(originalHtml);
                        }
                        alert(response.data.message || "An error occurred");
                    }
                },
                error: function() {
                    // Remove spinner
                    $toggleButton.find(".dlct-spinner").remove();
                    $toggleButton.find(".ab-item").removeClass("dlct-loading");

                    // Restore original content safely
                    if ($toggleButton.find(".ab-item").length) {
                        $toggleButton.find(".ab-item").html(originalHtml);
                    } else {
                        $toggleButton.html(originalHtml);
                    }

                    alert("An error occurred while toggling WP_DEBUG");
                }
            });
        });
    });
';
    }

    /**
     * Load plugin-specific assets
     */
    private function loadPluginAssets()
    {
        wp_enqueue_style(
            'dlct_style',
            DLCT_PLUGIN_URL . 'dist/wpdebuglog-admin-css.css',
            [],
            DLCT_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'dlct_main_js',
            DLCT_PLUGIN_URL . 'dist/wpdebuglog-admin.js',
            ['jquery'],
            DLCT_PLUGIN_VERSION
        );

        global $wp;
        $url = home_url($wp->request);

        wp_localize_script(
            'dlct_main_js',
            'dlct_wpdebuglog',
            [
                'ajax_url'      => admin_url('admin-ajax.php'),
                'base_url'      => $url,
                'action'        => 'dlct_logs_admin',
                'nonce'         => wp_create_nonce('dlct-nonce'),
                'current_color' => get_user_option('admin_color', get_current_user_id())
            ]
        );
    }

    /**
     * Custom footer text for the plugin admin page
     *
     * @return string
     */
    public function customFooterText()
    {
        $message = __('Thanks for using it! If you like this plugin a nice review will be appreciated', 'debug-log-config-tool');
        $reviewBtn = '<a class="" target="_blank" href="https://wordpress.org/plugins/debug-log-config-tool">' .
            __('Give Review', 'debug-log-config-tool') . '</a>';
        return "<span><p>{$message} {$reviewBtn}</p></span>";
    }

    /**
     * Register admin menu
     */
    public function adminMenu()
    {
        add_submenu_page(
            'tools.php',
            __('Debug Logs', 'debug-log-config-tool'),
            __('Debug Logs', 'debug-log-config-tool'),
            $this->getAccessRole(),
            self::DLCT_LOG,
            [$this, 'adminPage']
        );
    }

    /**
     * Check if WP_DEBUG is enabled
     *
     * @return bool True if WP_DEBUG is enabled, false otherwise
     */
    private function isDebugEnabled()
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Toggle WP_DEBUG status via AJAX
     */
    public function toggleDebug()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dlct_toggle_debug_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'debug-log-config-tool')]);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have sufficient permissions to perform this action.', 'debug-log-config-tool')
            ]);
            return;
        }

        // Get current debug status and toggle it
        $isDebugEnabled = $this->isDebugEnabled();
        $newStatus = !$isDebugEnabled;

        // Update the config
        $configController = ConfigController::getInstance();
        $configController->update('WP_DEBUG', $newStatus);

        // Set toggle text based on new status
        $toggleText = $newStatus
            ? __('Disable WP_DEBUG', 'debug-log-config-tool')
            : __('Enable WP_DEBUG', 'debug-log-config-tool');

        wp_send_json_success([
            'debug_enabled' => $newStatus,
            'toggle_text' => $toggleText,
            'message' => sprintf(
                __('WP_DEBUG has been %s', 'debug-log-config-tool'),
                $newStatus ? __('enabled', 'debug-log-config-tool') : __('disabled', 'debug-log-config-tool')
            )
        ]);
    }

    /**
     * Add debug indicator to admin bar
     */
    public function adminTopMenu()
    {
        global $wp_admin_bar;

        // Check if WP_DEBUG is enabled
        $isDebugEnabled = $this->isDebugEnabled();

        // Create debug status indicator
        $indicator = $isDebugEnabled
            ? '<span class="dlct-debug-enabled"></span>'
            : '<span class="dlct-debug-disabled"></span>';

        // Add main menu item with indicator
        $wp_admin_bar->add_menu([
            'id'     => self::DLCT_LOG . '_id',
            'parent' => false,
            'title'  => $indicator . __('Debug Logs', 'debug-log-config-tool'),
            'href'   => admin_url('tools.php?page=' . self::DLCT_LOG . '#/'),
        ]);

        // Add toggle debug submenu
        $toggleText = $isDebugEnabled
            ? __('Disable WP_DEBUG', 'debug-log-config-tool')
            : __('Enable WP_DEBUG', 'debug-log-config-tool');

        $wp_admin_bar->add_menu([
            'id'     => self::DLCT_LOG . '_toggle_debug',
            'parent' => self::DLCT_LOG . '_id',
            'title'  => $toggleText,
            'href'   => '#',
            'meta'   => [
                'class'     => 'dlct-toggle-debug',
                'onclick'   => 'return false;'
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function adminPage()
    {
        do_action('wpdd_admin_page_render');
        echo '<div id="main-app"></div>';
        do_action('wpdd_admin_page_render_after');
    }

    /**
     * Show review notice
     */
    public function showMsg()
    {
        static $messageShown = false;
        if (!get_option('DLCT_LOGconfig_notice_dismissed_20') && !$messageShown) {
            $class = 'notice notice-success is-dismissible';
            $message = __('If you like this plugin a nice review will be appreciated :)', 'debug-log-config-tool');
            $reviewBtn = sprintf(
                '<a class="button" target="_blank" href="https://wordpress.org/plugins/debug-log-config-tool">%s</a>',
                __('Give Review', 'debug-log-config-tool')
            );
            $closeBtn = sprintf(
                '<a class="button" href="%s">%s</a>',
                admin_url('tools.php?page=' . self::DLCT_LOG . '&dimiss_msg=true'),
                __('Dismiss', 'debug-log-config-tool')
            );

            printf(
                '<div class="%1$s"><p>%2$s %3$s %4$s</p></div>',
                esc_attr($class),
                esc_html($message),
                $reviewBtn,
                $closeBtn
            );

            $messageShown = true;
        }
    }

    /**
     * Handle message dismissal
     */
    public function msgDismissed()
    {
        if (isset($_GET['page']) && $_GET['page'] === self::DLCT_LOG && isset($_GET['dimiss_msg'])) {
            update_option('DLCT_LOGconfig_notice_dismissed_20', true);
        }
    }

    /**
     * Add dashboard widget
     */
    public function dashboardWidget()
    {
        if (!current_user_can($this->getAccessRole())) {
            return;
        }

        wp_add_dashboard_widget(
            'dlct_widget',
            __('Recent Debug Logs', 'debug-log-config-tool'),
            function () {
                (new DashboardWidget())->init();
            }
        );
    }

    /**
     * Get role required to access plugin features
     *
     * @return string
     */
    public function getAccessRole()
    {
        return apply_filters('DLCT_LOG_admin_access_role', 'manage_options');
    }

    /**
     * Add plugin action links
     */
    public function pluginActionMenu()
    {
        $plugin = plugin_basename(DLCT_PLUGIN_MAIN_FILE);

        add_filter("plugin_action_links_{$plugin}", function ($links) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url('tools.php?page=' . self::DLCT_LOG),
                __('View Log', 'debug-log-config-tool')
            );

            array_unshift($links, $settings_link);
            return $links;
        });
    }
}
