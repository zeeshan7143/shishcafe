<?php

namespace DebugLogConfigTool\Controllers;

class TerminalController
{
    /**
     * Execute a terminal command
     */
    public function executeCommand()
    {
        // Verify nonce for security - check both possible nonce field names
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : (isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'dlct-nonce')) {
            wp_send_json_error([
                'message' => 'Security verification failed',
                'success' => false
            ]);
            return;
        }

        // Check if terminal is enabled
        if (!(new TerminalSettingsController())->isTerminalEnabled()) {
            wp_send_json_error([
                'message' => 'Terminal is disabled. Enable it in Terminal Settings.',
                'success' => false
            ]);
            return;
        }

        // Check if user is an administrator
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'You do not have permission to execute commands',
                'success' => false
            ]);
            return;
        }

        // Get the command from the request
        $command = isset($_POST['command']) ? sanitize_text_field($_POST['command']) : '';

        if (empty($command)) {
            wp_send_json_error([
                'message' => 'No command provided',
                'success' => false
            ]);
            return;
        }

        // Parse the command and arguments
        $parts = explode(' ', $command);
        $cmd = $parts[0];
        $args = array_slice($parts, 1);

        // Check if this is a WP-CLI style command (with colon or space separator)
        if (strpos($cmd, ':') !== false) {
            $cmdParts = explode(':', $cmd);
            $mainCmd = $cmdParts[0];
            $subCmd = $cmdParts[1];

            // Reconstruct args with the subcommand as the first argument
            array_unshift($args, $subCmd);
            $cmd = $mainCmd;
        }

        try {
            // Execute the appropriate command
            switch ($cmd) {
                case 'wp':
                    // If no subcommand is provided, show available commands
                    if (empty($args)) {
                        $result = [
                            'output' => [
                                "<strong>WP-CLI Commands:</strong>",
                                "Usage: wp <command>",
                                "",
                                "Available commands:",
                                "  core       Core WordPress commands",
                                "  plugin     Manage plugins",
                                "  theme      Manage themes",
                                "  cron       Manage WP-Cron events and schedules",
                                "  db         Perform basic database operations",
                                "  log        View and manage debug logs",
                                "  hook       Manage WordPress hooks",
                                "  option     Manage WordPress options"
                            ],
                            'type' => 'info'
                        ];
                        break;
                    }

                    // Get the subcommand
                    $subcommand = $args[0];
                    $subargs = array_slice($args, 1);

                    // Execute the appropriate subcommand
                    switch ($subcommand) {
                        case 'core':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Core Commands:</strong>",
                                        "Usage: wp core <command>",
                                        "",
                                        "Available commands:",
                                        "  version    Display WordPress version",
                                        "  check      Check WordPress core"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'version') {
                                $result = $this->getWordPressVersion();
                            } else if ($subargs[0] === 'check') {
                                $result = $this->checkWordPressCore();
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp core {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'plugin':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Plugin Commands:</strong>",
                                        "Usage: wp plugin <command>",
                                        "",
                                        "Available commands:",
                                        "  list       List plugins",
                                        "  info       Get plugin info"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'list') {
                                $result = $this->listPlugins();
                            } else if ($subargs[0] === 'info') {
                                $pluginSlug = isset($subargs[1]) ? $subargs[1] : '';
                                $result = $this->getPluginInfo($pluginSlug);
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp plugin {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'theme':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Theme Commands:</strong>",
                                        "Usage: wp theme <command>",
                                        "",
                                        "Available commands:",
                                        "  list       List themes",
                                        "  info       Get theme info"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'list') {
                                $result = $this->listThemes();
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp theme {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'cron':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Cron Commands:</strong>",
                                        "Usage: wp cron <command>",
                                        "",
                                        "Available commands:",
                                        "  list       List cron events"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'list') {
                                $result = $this->listCronJobs();
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp cron {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'log':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Log Commands:</strong>",
                                        "Usage: wp log <command>",
                                        "",
                                        "Available commands:",
                                        "  tail       Show last N lines of debug log",
                                        "  search     Search debug log for a term",
                                        "  stats      Show error statistics"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'tail') {
                                $lines = isset($subargs[1]) ? intval($subargs[1]) : 10;
                                $result = $this->tailLog($lines);
                            } else if ($subargs[0] === 'search') {
                                $searchTerm = isset($subargs[1]) ? $subargs[1] : '';
                                $result = $this->findInLog($searchTerm);
                            } else if ($subargs[0] === 'stats') {
                                $result = $this->getErrorStats();
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp log {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'hook':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Hook Commands:</strong>",
                                        "Usage: wp hook <command>",
                                        "",
                                        "Available commands:",
                                        "  list       List hooks and their callbacks"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'list') {
                                $hookName = isset($subargs[1]) ? $subargs[1] : '';
                                $result = $this->listHooks($hookName);
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp hook {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'option':
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP Option Commands:</strong>",
                                        "Usage: wp option <command>",
                                        "",
                                        "Available commands:",
                                        "  list       List autoloaded options"
                                    ],
                                    'type' => 'info'
                                ];
                            } else if ($subargs[0] === 'list') {
                                $result = $this->checkAutoloadOptions();
                            } else {
                                $result = [
                                    'output' => ["Unknown command: wp option {$subargs[0]}"],
                                    'type' => 'error'
                                ];
                            }
                            break;

                        case 'db':
                            // Check if there's a subcommand
                            if (empty($subargs)) {
                                $result = [
                                    'output' => [
                                        "<strong>WP-CLI DB Commands:</strong>",
                                        "Usage: wp db <command>",
                                        "",
                                        "Available commands:",
                                        "  tables    List database tables",
                                        "  size      Display database size",
                                        "  query     Execute a SQL query",
                                        "  prefix    Display the database table prefix",
                                        "  optimize  Optimize database tables",
                                        "  columns   Display information about a specific table"
                                    ],
                                    'type' => 'info'
                                ];
                                break;
                            }

                            // Get the subcommand
                            $db_subcommand = $subargs[0];
                            $db_subargs = array_slice($subargs, 1);

                            // Check if database commands are enabled
                            if (!(new TerminalSettingsController())->isDatabaseCommandsEnabled()) {
                                $result = [
                                    'output' => ["Database commands are disabled. Enable them in Terminal Settings."],
                                    'type' => 'error'
                                ];
                                break;
                            }

                            // Check if user is a super admin
                            if (!is_super_admin()) {
                                $result = [
                                    'output' => ["Only super administrators can execute database commands."],
                                    'type' => 'error'
                                ];
                                break;
                            }

                            // Execute the appropriate subcommand
                            switch ($db_subcommand) {
                                case 'tables':
                                    $result = $this->getDatabaseTables();
                                    break;

                                case 'size':
                                    $result = $this->getDatabaseSize();
                                    break;

                                case 'query':
                                    // Join the arguments but preserve the original query structure
                                    $query = implode(' ', $db_subargs);
                                    // Remove any escaped quotes that might have been added
                                    $query = str_replace(['\\"', "\\\'",'\"', "\'",'&quot;'], ['"', "'", '"', "'", '"'], $query);
                                    $result = $this->executeDatabaseQuery($query);
                                    break;

                                case 'prefix':
                                    $result = $this->getDatabasePrefix();
                                    break;

                                case 'optimize':
                                    $result = $this->optimizeDatabaseTables($db_subargs);
                                    break;

                                case 'columns':
                                    $table = isset($db_subargs[0]) ? $db_subargs[0] : '';
                                    $result = $this->getTableColumns($table);
                                    break;

                                default:
                                    $result = [
                                        'output' => ["Unknown subcommand: wp db {$db_subcommand}. Try 'wp db' for a list of available commands."],
                                        'type' => 'error'
                                    ];
                            }
                            break;

                        default:
                            $result = [
                                'output' => ["Unknown command: wp {$subcommand}. Try 'wp' for a list of available commands."],
                                'type' => 'error'
                            ];
                    }
                    break;

                case 'php':
                    if (empty($args)) {
                        $result = [
                            'output' => [
                                "<strong>PHP Commands:</strong>",
                                "Usage: php <command>",
                                "",
                                "Available commands:",
                                "  info       Display PHP configuration information",
                                "  memory     Show memory usage information"
                            ],
                            'type' => 'info'
                        ];
                    } else if ($args[0] === 'info') {
                        $result = $this->getPhpInfo();
                    } else if ($args[0] === 'memory') {
                        $result = $this->getMemoryUsage();
                    } else {
                        $result = [
                            'output' => ["Unknown command: php {$args[0]}. Try 'php' for a list of available commands."],
                            'type' => 'error'
                        ];
                    }
                    break;

                case 'db':
                    // Check if there's a subcommand
                    if (empty($args)) {
                        $result = [
                            'output' => [
                                "<strong>WP-CLI DB Commands:</strong>",
                                "Usage: db <command>",
                                "",
                                "Available commands:",
                                "  tables    List database tables",
                                "  size      Display database size",
                                "  query     Execute a SQL query",
                                "  prefix    Display the database table prefix",
                                "  optimize  Optimize database tables",
                                "  columns   Display information about a specific table"
                            ],
                            'type' => 'info'
                        ];
                        break;
                    }

                    // Get the subcommand
                    $subcommand = $args[0];
                    $subargs = array_slice($args, 1);

                    // Check if database commands are enabled
                    if (!(new TerminalSettingsController())->isDatabaseCommandsEnabled()) {
                        wp_send_json_error([
                            'message' => "Database commands are disabled. Enable them in Terminal Settings.",
                            'success' => false
                        ]);
                        return;
                    }

                    // Check if user is a super admin
                    if (!is_super_admin()) {
                        wp_send_json_error([
                            'message' => "Only super administrators can execute database commands.",
                            'success' => false
                        ]);
                        return;
                    }

                    // Execute the appropriate subcommand
                    switch ($subcommand) {
                        case 'tables':
                            $result = $this->getDatabaseTables();
                            break;

                        case 'size':
                            $result = $this->getDatabaseSize();
                            break;

                        case 'query':
                            // Join the arguments but preserve the original query structure
                            $query = implode(' ', $subargs);
                            // Remove any escaped quotes that might have been added
                            $query = str_replace(['\\\'', '\\"'], ['\'', '"'], $query);
                            $result = $this->executeDatabaseQuery($query);
                            break;

                        case 'prefix':
                            $result = $this->getDatabasePrefix();
                            break;

                        case 'optimize':
                            $result = $this->optimizeDatabaseTables($subargs);
                            break;

                        case 'columns':
                            $table = isset($subargs[0]) ? $subargs[0] : '';
                            $result = $this->getTableColumns($table);
                            break;

                        default:
                            $result = [
                                'output' => ["Unknown subcommand: $subcommand. Try 'db' for a list of available commands."],
                                'type' => 'error'
                            ];
                    }
                    break;

                case 'check-options':
                    $result = $this->checkAutoloadOptions();
                    break;

                // Shell command support
                case 'shell':
                    if (empty($args)) {
                        wp_send_json_error([
                            'message' => "Please specify a shell command",
                            'success' => false
                        ]);
                        return;
                    }
                    $result = $this->executeShellCommand($args);
                    break;

                default:
                    // Check if this might be a shell command
                    // Get the list of allowed commands from the executeShellCommand method
                    $allowed_shell_commands = ['ls', 'cat', 'df', 'du', 'pwd', 'whoami', 'date', 'uptime', 'hostname', 'head', 'tail', 'wc', 'sort', 'uniq'];

                    if (in_array($cmd, $allowed_shell_commands)) {
                        $shellArgs = array_merge([$cmd], $args);
                        $result = $this->executeShellCommand($shellArgs);
                    } else {
                        wp_send_json_error([
                            'message' => "Unknown command: $cmd. Try 'help' for a list of available commands.",
                            'success' => false
                        ]);
                        return;
                    }
            }

            wp_send_json_success([
                'output' => $result['output'],
                'type' => $result['type'],
                'success' => true
            ]);

        } catch (\Exception $e) {
            // Ensure error message is a string
            $errorMessage = $e->getMessage();
            if (empty($errorMessage)) {
                $errorMessage = 'An unknown error occurred';
            }

            wp_send_json_error([
                'message' => $errorMessage,
                'success' => false
            ]);
        } catch (\Error $e) {
            // Catch PHP 7+ errors
            $errorMessage = $e->getMessage();
            if (empty($errorMessage)) {
                $errorMessage = 'A PHP error occurred';
            }

            wp_send_json_error([
                'message' => $errorMessage,
                'success' => false
            ]);
        } catch (\Throwable $t) {
            // Catch any other throwables
            $errorMessage = $t->getMessage();
            if (empty($errorMessage)) {
                $errorMessage = 'An unexpected error occurred';
            }

            wp_send_json_error([
                'message' => $errorMessage,
                'success' => false
            ]);
        }
    }

    /**
     * Get WordPress version information
     */
    private function getWordPressVersion()
    {
        global $wp_version, $wp_db_version;

        $output = [
            "WordPress Version: <strong>$wp_version</strong>",
            "Database Version: <strong>$wp_db_version</strong>",
            "PHP Version: <strong>" . phpversion() . "</strong>",
            "MySQL Version: <strong>" . $this->getMySQLVersion() . "</strong>",
            "",
            "WordPress URL: " . get_bloginfo('wpurl'),
            "Site URL: " . get_bloginfo('url'),
            "Is Multisite: " . (is_multisite() ? 'Yes' : 'No')
        ];

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Check WordPress core files and configuration
     */
    private function checkWordPressCore()
    {
        global $wp_version, $required_php_version, $required_mysql_version;

        $output = ["<strong>WordPress Core Check:</strong>"];

        // Check WordPress version
        $output[] = "\nWordPress version: $wp_version";

        // Check PHP version
        $current_php = phpversion();
        $php_check = version_compare($current_php, $required_php_version, '>=');
        $output[] = "PHP version: $current_php " . ($php_check ? '✓' : '✗ (Required: ' . $required_php_version . ')');

        // Check MySQL version
        $mysql_version = $this->getMySQLVersion();
        $mysql_check = version_compare($mysql_version, $required_mysql_version, '>=');
        $output[] = "MySQL version: $mysql_version " . ($mysql_check ? '✓' : '✗ (Required: ' . $required_mysql_version . ')');

        // Check file permissions
        $output[] = "\n<strong>File Permissions:</strong>";
        $wp_content = WP_CONTENT_DIR;
        $uploads_dir = wp_upload_dir();

        $output[] = "wp-content directory: " . (is_writable($wp_content) ? 'Writable ✓' : 'Not writable ✗');
        $output[] = "uploads directory: " . (is_writable($uploads_dir['basedir']) ? 'Writable ✓' : 'Not writable ✗');

        // Check if debug mode is enabled
        $output[] = "\n<strong>Debug Settings:</strong>";
        $output[] = "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled');
        $output[] = "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled');
        $output[] = "WP_DEBUG_DISPLAY: " . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled');

        // Check active theme
        $theme = wp_get_theme();
        $output[] = "\n<strong>Active Theme:</strong> {$theme->get('Name')} (v{$theme->get('Version')})";

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get MySQL version
     */
    private function getMySQLVersion()
    {
        global $wpdb;
        $version = $wpdb->get_var("SELECT VERSION()");
        return $version;
    }

    /**
     * List installed plugins
     *
     * @param string $status Filter plugins by status: 'active', 'inactive', or 'all' (default)
     * @return array Command output
     */
    private function listPlugins($status = 'all')
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active_plugins = get_option('active_plugins');
        $all_plugins = get_plugins();

        // Filter plugins based on status
        $filtered_plugins = [];
        $status_label = '';

        switch ($status) {
            case 'active':
                $status_label = 'Active';
                foreach ($all_plugins as $plugin_path => $plugin_data) {
                    if (in_array($plugin_path, $active_plugins)) {
                        $filtered_plugins[$plugin_path] = $plugin_data;
                    }
                }
                break;

            case 'inactive':
                $status_label = 'Inactive';
                foreach ($all_plugins as $plugin_path => $plugin_data) {
                    if (!in_array($plugin_path, $active_plugins)) {
                        $filtered_plugins[$plugin_path] = $plugin_data;
                    }
                }
                break;

            case 'all':
            default:
                $status_label = 'Installed';
                $filtered_plugins = $all_plugins;
                break;
        }

        $output = ["<strong>{$status_label} Plugins (" . count($filtered_plugins) . "):</strong>"];

        if ($status === 'all') {
            $output[] = "Active plugins: " . count($active_plugins);
            $output[] = "Inactive plugins: " . (count($all_plugins) - count($active_plugins));
        }

        $output[] = "";

        if (empty($filtered_plugins)) {
            $output[] = "No {$status_label} plugins found.";
        } else {
            foreach ($filtered_plugins as $plugin_path => $plugin_data) {
                $active = in_array($plugin_path, $active_plugins) ? ' [ACTIVE]' : '';
                $version = isset($plugin_data['Version']) ? $plugin_data['Version'] : 'Unknown';
                $author = isset($plugin_data['Author']) ? $plugin_data['Author'] : 'Unknown';

                $output[] = "• {$plugin_data['Name']} (v{$version}){$active}";
                $output[] = "  Author: {$author}";
                $output[] = "  Path: {$plugin_path}";
                $output[] = "";
            }
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get detailed information about a specific plugin
     */
    private function getPluginInfo($plugin_slug)
    {
        if (empty($plugin_slug)) {
            return [
                'output' => ['Please specify a plugin slug.'],
                'type' => 'error'
            ];
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $found = false;
        $plugin_data = null;
        $plugin_path = null;

        // First try to find by slug in the path
        foreach ($all_plugins as $path => $data) {
            if (strpos($path, $plugin_slug . '/') === 0 || $path === $plugin_slug . '.php') {
                $found = true;
                $plugin_data = $data;
                $plugin_path = $path;
                break;
            }
        }

        // If not found, try to match by name
        if (!$found) {
            foreach ($all_plugins as $path => $data) {
                if (strtolower($data['Name']) === strtolower($plugin_slug) ||
                    strpos(strtolower($data['Name']), strtolower($plugin_slug)) !== false) {
                    $found = true;
                    $plugin_data = $data;
                    $plugin_path = $path;
                    break;
                }
            }
        }

        if (!$found) {
            return [
                'output' => ["Plugin '$plugin_slug' not found."],
                'type' => 'error'
            ];
        }

        $active = in_array($plugin_path, $active_plugins) ? 'Active' : 'Inactive';

        $output = ["<strong>Plugin: {$plugin_data['Name']}</strong>"];
        $output[] = "Version: {$plugin_data['Version']}";
        $output[] = "Status: $active";
        $output[] = "Author: {$plugin_data['Author']}";

        if (!empty($plugin_data['PluginURI'])) {
            $output[] = "Plugin URI: {$plugin_data['PluginURI']}";
        }

        if (!empty($plugin_data['Description'])) {
            $output[] = "\nDescription: {$plugin_data['Description']}";
        }

        $output[] = "\nFile: $plugin_path";

        // Get update information if available
        $update_plugins = get_site_transient('update_plugins');
        if ($update_plugins && isset($update_plugins->response[$plugin_path])) {
            $update_info = $update_plugins->response[$plugin_path];
            $output[] = "\n<strong>Update Available:</strong> {$update_info->new_version}";
            $output[] = "Compatibility: Up to WordPress {$update_info->tested}";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * List installed themes
     */
    private function listThemes()
    {
        $themes = wp_get_themes();
        $current_theme = wp_get_theme();

        $output = ["<strong>Current Theme:</strong> {$current_theme->get('Name')} (v{$current_theme->get('Version')})"];
        $output[] = "Author: {$current_theme->get('Author')}";
        $output[] = "";
        $output[] = "<strong>Installed Themes (" . count($themes) . "):</strong>";

        foreach ($themes as $theme_slug => $theme) {
            $active = ($theme->get('Name') == $current_theme->get('Name')) ? ' [ACTIVE]' : '';
            $output[] = "• {$theme->get('Name')} (v{$theme->get('Version')}){$active}";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get memory usage information
     */
    private function getMemoryUsage()
    {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = size_format(memory_get_usage());
        $memory_peak = size_format(memory_get_peak_usage());
        $wp_memory_limit = WP_MEMORY_LIMIT;
        $wp_max_memory_limit = WP_MAX_MEMORY_LIMIT;

        $output = [
            "<strong>Current Memory Usage:</strong> $memory_usage",
            "<strong>Peak Memory Usage:</strong> $memory_peak",
            "<strong>PHP Memory Limit:</strong> $memory_limit",
            "<strong>WP Memory Limit:</strong> $wp_memory_limit",
            "<strong>WP Max Memory Limit:</strong> $wp_max_memory_limit"
        ];

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get PHP configuration information
     */
    private function getPhpInfo()
    {
        $output = [
            "<strong>PHP Version:</strong> " . phpversion(),
            "<strong>PHP SAPI:</strong> " . php_sapi_name(),
            "<strong>Operating System:</strong> " . PHP_OS,
            "",
            "<strong>PHP Configuration:</strong>",
            "• max_execution_time: " . ini_get('max_execution_time') . " seconds",
            "• max_input_time: " . ini_get('max_input_time') . " seconds",
            "• upload_max_filesize: " . ini_get('upload_max_filesize'),
            "• post_max_size: " . ini_get('post_max_size'),
            "• display_errors: " . (ini_get('display_errors') ? 'On' : 'Off'),
            "• memory_limit: " . ini_get('memory_limit'),
            "",
            "<strong>Extensions:</strong>",
            "• mysqli: " . (extension_loaded('mysqli') ? 'Enabled' : 'Disabled'),
            "• curl: " . (extension_loaded('curl') ? 'Enabled' : 'Disabled'),
            "• gd: " . (extension_loaded('gd') ? 'Enabled' : 'Disabled'),
            "• openssl: " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled')
        ];

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get error statistics from debug log
     */
    private function getErrorStats()
    {
        $logController = new LogController();
        $logData = $logController->loadLogs();

        if (empty($logData['logs'])) {
            return [
                'output' => ['No log entries found.'],
                'type' => 'info'
            ];
        }

        $logs = $logData['logs'];
        $errorTypes = [];
        $totalErrors = count($logs);

        foreach ($logs as $log) {
            $type = !empty($log['error_type']) ? $log['error_type'] : 'Unknown';
            if (!isset($errorTypes[$type])) {
                $errorTypes[$type] = 0;
            }
            $errorTypes[$type]++;
        }

        $output = ["<strong>Error Statistics (Total: $totalErrors)</strong>"];

        foreach ($errorTypes as $type => $count) {
            $percentage = round(($count / $totalErrors) * 100, 2);
            $output[] = "• $type: $count ($percentage%)";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get the last N lines of the debug log
     */
    private function tailLog($lines = 10)
    {
        $logController = new LogController();
        $logData = $logController->loadLogs($lines);

        if (empty($logData['logs'])) {
            return [
                'output' => ['No log entries found.'],
                'type' => 'info'
            ];
        }

        $logs = $logData['logs'];
        $output = ["<strong>Last $lines log entries:</strong>"];

        foreach ($logs as $log) {
            $date = $log['date'] . ' ' . $log['time'];
            $type = !empty($log['error_type']) ? "[{$log['error_type']}] " : '';
            $details = $log['details'];

            $output[] = "$date - $type$details";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Search for text in the debug log
     */
    private function findInLog($searchTerm)
    {
        if (empty($searchTerm)) {
            return [
                'output' => ['Please provide a search term.'],
                'type' => 'error'
            ];
        }

        $logController = new LogController();
        $logData = $logController->loadLogs(false);

        if (empty($logData['logs'])) {
            return [
                'output' => ['No log entries found.'],
                'type' => 'info'
            ];
        }

        $logs = $logData['logs'];
        $matchingLogs = [];

        foreach ($logs as $log) {
            if (stripos($log['details'], $searchTerm) !== false) {
                $matchingLogs[] = $log;
            }
        }

        if (empty($matchingLogs)) {
            return [
                'output' => ["No log entries found containing '$searchTerm'."],
                'type' => 'info'
            ];
        }

        $output = ["<strong>Found " . count($matchingLogs) . " log entries containing '$searchTerm':</strong>"];

        foreach ($matchingLogs as $log) {
            $date = $log['date'] . ' ' . $log['time'];
            $type = !empty($log['error_type']) ? "[{$log['error_type']}] " : '';
            $details = $log['details'];

            // Highlight the search term
            $details = str_ireplace($searchTerm, "<span style='background-color: yellow; color: black;'>$searchTerm</span>", $details);

            $output[] = "$date - $type$details";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * List hooks and their callbacks
     */
    private function listHooks($hookName = '')
    {
        global $wp_filter;

        if (!empty($hookName)) {
            // Show details for a specific hook
            if (!isset($wp_filter[$hookName])) {
                return [
                    'output' => ["Hook '$hookName' not found."],
                    'type' => 'error'
                ];
            }

            $hook = $wp_filter[$hookName];
            $output = ["<strong>Hook: $hookName</strong>"];
            $output[] = "Priority: " . implode(', ', array_keys($hook->callbacks));
            $output[] = "";
            $output[] = "<strong>Callbacks:</strong>";

            foreach ($hook->callbacks as $priority => $callbacks) {
                $output[] = "Priority $priority:";

                foreach ($callbacks as $idx => $callback) {
                    $callback_name = $this->getCallbackName($callback['function']);
                    $output[] = "• $callback_name";
                }
            }

            return [
                'output' => $output,
                'type' => 'info'
            ];
        }

        // List all hooks
        $hooks = array_keys($wp_filter);
        sort($hooks);

        $output = ["<strong>Available Hooks (" . count($hooks) . "):</strong>"];
        $output[] = "Use 'list-hooks [hook_name]' to see details for a specific hook.";
        $output[] = "";

        $actions = [];
        $filters = [];

        foreach ($hooks as $hook) {
            if (substr($hook, 0, 4) === 'wp_') {
                $actions[] = $hook;
            } else {
                $filters[] = $hook;
            }
        }

        $output[] = "<strong>Common Action Hooks:</strong>";
        $common_actions = ['wp_loaded', 'init', 'admin_init', 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'wp_footer', 'wp_head'];

        foreach ($common_actions as $action) {
            if (in_array($action, $hooks)) {
                $callbacks_count = $this->countCallbacks($wp_filter[$action]);
                $output[] = "• $action ($callbacks_count callbacks)";
            }
        }

        $output[] = "";
        $output[] = "<strong>Common Filter Hooks:</strong>";
        $common_filters = ['the_content', 'the_title', 'the_excerpt', 'body_class', 'post_class'];

        foreach ($common_filters as $filter) {
            if (in_array($filter, $hooks)) {
                $callbacks_count = $this->countCallbacks($wp_filter[$filter]);
                $output[] = "• $filter ($callbacks_count callbacks)";
            }
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Count callbacks for a hook
     */
    private function countCallbacks($hook)
    {
        $count = 0;
        foreach ($hook->callbacks as $callbacks) {
            $count += count($callbacks);
        }
        return $count;
    }

    /**
     * Get a readable name for a callback
     */
    private function getCallbackName($callback)
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '->' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        }

        if (is_object($callback)) {
            $callback_class = get_class($callback);
            if ($callback_class === 'Closure') {
                return 'Anonymous function';
            } else {
                return $callback_class;
            }
        }

        return 'Unknown callback';
    }

    /**
     * List scheduled cron jobs
     */
    private function listCronJobs()
    {
        $cron_jobs = _get_cron_array();

        if (empty($cron_jobs)) {
            return [
                'output' => ['No scheduled cron jobs found.'],
                'type' => 'info'
            ];
        }

        $output = ["<strong>Scheduled Cron Jobs:</strong>"];

        foreach ($cron_jobs as $timestamp => $crons) {
            $date = date('Y-m-d H:i:s', $timestamp);
            $output[] = "";
            $output[] = "<strong>$date</strong> (" . human_time_diff(time(), $timestamp) . " from now)";

            foreach ($crons as $hook => $events) {
                foreach ($events as $key => $event) {
                    $schedule = isset($event['schedule']) ? $event['schedule'] : 'once';
                    $output[] = "• $hook ($schedule)";

                    if (!empty($event['args'])) {
                        $args = json_encode($event['args']);
                        $output[] = "  Args: $args";
                    }
                }
            }
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get database tables and their sizes
     */
    private function getDatabaseTables()
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

        if (empty($tables)) {
            return [
                'output' => ['No database tables found.'],
                'type' => 'error'
            ];
        }

        $output = ["<strong>Database Tables:</strong>"];

        foreach ($tables as $table) {
            $table_name = $table[0];
            $size_query = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'");

            if ($size_query) {
                $size_kb = round(($size_query->Data_length + $size_query->Index_length) / 1024, 2);
                $rows = $size_query->Rows;

                $output[] = "• $table_name ($rows rows, $size_kb KB)";
            } else {
                $output[] = "• $table_name";
            }
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get database size information
     */
    private function getDatabaseSize()
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

        if (empty($tables)) {
            return [
                'output' => ['No database tables found.'],
                'type' => 'error'
            ];
        }

        $total_size = 0;
        $total_rows = 0;
        $table_sizes = [];

        foreach ($tables as $table) {
            $table_name = $table[0];
            $size_query = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'");

            if ($size_query) {
                $size_bytes = $size_query->Data_length + $size_query->Index_length;
                $total_size += $size_bytes;
                $total_rows += $size_query->Rows;

                $table_sizes[] = [
                    'name' => $table_name,
                    'size' => $size_bytes,
                    'rows' => $size_query->Rows
                ];
            }
        }

        // Sort tables by size (largest first)
        usort($table_sizes, function($a, $b) {
            return $b['size'] - $a['size'];
        });

        $output = ["<strong>Database Size Information:</strong>"];
        $output[] = "Total Size: " . size_format($total_size);
        $output[] = "Total Tables: " . count($tables);
        $output[] = "Total Rows: " . number_format($total_rows);
        $output[] = "";
        $output[] = "<strong>Largest Tables:</strong>";

        // Show top 10 largest tables
        $count = 0;
        foreach ($table_sizes as $table) {
            if ($count++ >= 10) break;
            $size_formatted = size_format($table['size']);
            $rows_formatted = number_format($table['rows']);
            $output[] = "• {$table['name']}: $size_formatted ($rows_formatted rows)";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get database prefix
     */
    private function getDatabasePrefix()
    {
        global $wpdb;

        $output = ["<strong>Database Prefix:</strong> {$wpdb->prefix}"];
        $output[] = "Base Prefix: {$wpdb->base_prefix}";

        if (is_multisite()) {
            $output[] = "\nThis is a multisite installation. Each site uses a different table prefix.";
            $output[] = "The base prefix is used for global tables, while each site has its own prefix.";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Optimize database tables
     */
    private function optimizeDatabaseTables($tables = [])
    {
        global $wpdb;

        // Check if user is a super admin
        if (!is_super_admin()) {
            return [
                'output' => ["Only super administrators can optimize database tables."],
                'type' => 'error'
            ];
        }

        // If no specific tables are provided, get all tables
        if (empty($tables)) {
            $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
            $tables = [];

            foreach ($all_tables as $table) {
                $tables[] = $table[0];
            }
        }

        if (empty($tables)) {
            return [
                'output' => ['No tables to optimize.'],
                'type' => 'error'
            ];
        }

        $output = ["<strong>Optimizing Database Tables:</strong>"];
        $success_count = 0;

        foreach ($tables as $table) {
            // Sanitize table name to prevent SQL injection
            $table = sanitize_text_field($table);

            // Only optimize tables with the WordPress prefix for safety
            if (strpos($table, $wpdb->prefix) !== 0) {
                $output[] = "Skipping $table (not a WordPress table)";
                continue;
            }

            $result = $wpdb->query("OPTIMIZE TABLE `$table`");

            if ($result !== false) {
                $output[] = "Optimized: $table ✓";
                $success_count++;
            } else {
                $output[] = "Failed to optimize: $table ✗";
            }
        }

        $output[] = "\nOptimized $success_count out of " . count($tables) . " tables.";

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Get table columns information
     */
    private function getTableColumns($table)
    {
        global $wpdb;

        if (empty($table)) {
            return [
                'output' => ['Please specify a table name.'],
                'type' => 'error'
            ];
        }

        // Sanitize table name to prevent SQL injection
        $table = sanitize_text_field($table);

        // Add prefix if not already included
        if (strpos($table, $wpdb->prefix) !== 0) {
            $table = $wpdb->prefix . $table;
        }

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

        if (!$table_exists) {
            return [
                'output' => ["Table '$table' does not exist."],
                'type' => 'error'
            ];
        }

        // Get table structure
        $columns = $wpdb->get_results("DESCRIBE `$table`");

        if (empty($columns)) {
            return [
                'output' => ["No columns found in table '$table'."],
                'type' => 'error'
            ];
        }

        $output = ["<strong>Table Structure: $table</strong>"];
        $output[] = "";
        $output[] = "Column Name | Type | Null | Key | Default | Extra";
        $output[] = "-----------|------|------|-----|---------|------";

        foreach ($columns as $column) {
            $output[] = "{$column->Field} | {$column->Type} | {$column->Null} | {$column->Key} | {$column->Default} | {$column->Extra}";
        }

        // Get table status
        $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");

        if ($status) {
            $output[] = "";
            $output[] = "<strong>Table Information:</strong>";
            $output[] = "Engine: {$status->Engine}";
            $output[] = "Rows: {$status->Rows}";
            $output[] = "Data Size: " . size_format($status->Data_length);
            $output[] = "Index Size: " . size_format($status->Index_length);
            $output[] = "Total Size: " . size_format($status->Data_length + $status->Index_length);
            $output[] = "Created: {$status->Create_time}";
            $output[] = "Updated: {$status->Update_time}";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Execute a database query
     */
    private function executeDatabaseQuery($query)
    {
        global $wpdb;

        // Check if user is a super admin
        if (!is_super_admin()) {
            return [
                'output' => ["Only super administrators can execute database queries."],
                'type' => 'error'
            ];
        }

        if (empty($query)) {
            return [
                'output' => ['Please specify a SQL query.'],
                'type' => 'error'
            ];
        }

        // Sanitize query (basic protection, not foolproof)
        $query = trim($query);

        // Fix quotes in the query that might have been escaped during transmission
        $query = str_replace(['\\"', "\\\'",'\"', "\'",'&quot;'], ['"', "'", '"', "'", '"'], $query);

        // Make sure we have a clean query string for checking
        $clean_query = preg_replace('/^\s+/', '', $query); // Remove any leading whitespace

        // Remove any quotes at the beginning that might be part of the command string
        $clean_query = preg_replace('/^["\']+/', '', $clean_query);

        // Get first 15 chars (to account for quotes and spaces) and convert to uppercase
        $clean_query = strtoupper(substr($clean_query, 0, 15));

        // Prevent destructive queries
        $disallowed_queries = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'UPDATE', 'INSERT', 'REPLACE', 'CREATE', 'GRANT'];

        foreach ($disallowed_queries as $disallowed) {
            if (strpos($clean_query, strtoupper($disallowed)) === 0) {
                return [
                    'output' => ["Error: $disallowed queries are not allowed for security reasons. Only SELECT queries are permitted."],
                    'type' => 'error'
                ];
            }
        }

        // Only allow SELECT queries
        if (strpos($clean_query, 'SELECT') !== 0) {
            return [
                'output' => [
                    "Error: Only SELECT queries are allowed for security reasons.",
                    "Your query must start with 'SELECT'.",
                    "Debug: Original query: '" . substr($query, 0, 30) . "...'",
                    "Debug: Cleaned query: '" . $clean_query . "'"
                ],
                'type' => 'error'
            ];
        }

        // Execute the query with a timeout
        $wpdb->query("SET SESSION MAX_EXECUTION_TIME=5000"); // 5 seconds timeout

        // Strip any surrounding quotes from the query before execution
        $execution_query = trim($query);
        $execution_query = preg_replace('/^["\'](.+)["\']$/', '$1', $execution_query);

        $start_time = microtime(true);
        $results = $wpdb->get_results($execution_query, ARRAY_A);
        $execution_time = microtime(true) - $start_time;

        if ($wpdb->last_error) {
            return [
                'output' => [
                    "Error: {$wpdb->last_error}",
                    "Debug: Original query: '" . substr($query, 0, 50) . "...'",
                    "Debug: Execution query: '" . substr($execution_query, 0, 50) . "...'"
                ],
                'type' => 'error'
            ];
        }

        if (empty($results)) {
            return [
                'output' => ["Query executed successfully but returned no results.", "Execution time: " . round($execution_time * 1000, 2) . " ms"],
                'type' => 'info'
            ];
        }

        // Get column names from first row
        $columns = array_keys($results[0]);

        $output = ["<strong>Query Results:</strong>"];
        $output[] = "Rows returned: " . count($results);
        $output[] = "Execution time: " . round($execution_time * 1000, 2) . " ms";
        $output[] = "";

        // Create header row
        $output[] = implode(' | ', $columns);
        $output[] = str_repeat('-', strlen(implode(' | ', $columns)));

        // Limit to 100 rows to prevent overwhelming the UI
        $limit = min(count($results), 100);

        for ($i = 0; $i < $limit; $i++) {
            $row_values = [];

            foreach ($columns as $column) {
                // Truncate long values
                $value = $results[$i][$column];
                if (is_string($value) && strlen($value) > 50) {
                    $value = substr($value, 0, 47) . '...';
                }
                $row_values[] = $value;
            }

            $output[] = implode(' | ', $row_values);
        }

        if (count($results) > $limit) {
            $output[] = "... (output truncated, showing $limit of " . count($results) . " rows)";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Check autoloaded options
     */
    private function checkAutoloadOptions()
    {
        global $wpdb;

        $autoloaded = $wpdb->get_results("
            SELECT option_name, length(option_value) as option_size
            FROM {$wpdb->options}
            WHERE autoload = 'yes'
            ORDER BY option_size DESC
            LIMIT 20
        ");

        if (empty($autoloaded)) {
            return [
                'output' => ['No autoloaded options found.'],
                'type' => 'info'
            ];
        }

        $total_size = 0;
        foreach ($autoloaded as $option) {
            $total_size += $option->option_size;
        }

        $total_size_kb = round($total_size / 1024, 2);

        $output = ["<strong>Top 20 Autoloaded Options (Total: $total_size_kb KB)</strong>"];
        $output[] = "Large autoloaded options can impact site performance.";
        $output[] = "";

        foreach ($autoloaded as $option) {
            $size_kb = round($option->option_size / 1024, 2);
            $output[] = "• {$option->option_name}: $size_kb KB";
        }

        return [
            'output' => $output,
            'type' => 'info'
        ];
    }

    /**
     * Execute a shell command with security restrictions
     *
     * @param array $args Command and arguments
     * @return array Command output
     */
    private function executeShellCommand($args)
    {
        // Security check - only allow if user is an administrator
        if (!current_user_can('manage_options')) {
            return [
                'output' => ['Error: You do not have permission to execute shell commands.'],
                'type' => 'error'
            ];
        }

        // Get the command
        $cmd = is_array($args) ? $args[0] : $args;

        // List of allowed commands - restricted to safer commands only
        $allowed_commands = [
            'ls', 'cat', 'df', 'du', 'pwd', 'whoami', 'date', 'uptime', 'hostname',
            'head', 'tail', 'wc', 'sort', 'uniq'
        ];

        // Check if command is allowed
        if (!in_array($cmd, $allowed_commands)) {
            return [
                'output' => ["Error: Command '$cmd' is not allowed for security reasons."],
                'type' => 'error'
            ];
        }

        // Build the command string with arguments
        $command_string = $cmd;
        if (is_array($args) && count($args) > 1) {
            $command_args = array_slice($args, 1);

            // Sanitize arguments to prevent command injection
            $sanitized_args = [];
            foreach ($command_args as $arg) {
                // Remove any potentially dangerous characters
                $sanitized_arg = preg_replace('/[;&|`$><\\\/]/', '', $arg);
                $sanitized_args[] = escapeshellarg($sanitized_arg);
            }

            $command_string .= ' ' . implode(' ', $sanitized_args);
        }

        // Add safety options for certain commands
        switch ($cmd) {
            case 'find':
                // Limit depth for find command
                if (!strpos($command_string, '-maxdepth')) {
                    $command_string .= ' -maxdepth 3';
                }
                break;

            case 'cat':
            case 'head':
            case 'tail':
                // Prevent access to sensitive files
                if (strpos($command_string, '/etc/passwd') !== false ||
                    strpos($command_string, '/etc/shadow') !== false ||
                    strpos($command_string, 'wp-config.php') !== false) {
                    return [
                        'output' => ['Error: Access to this file is restricted.'],
                        'type' => 'error'
                    ];
                }
                break;
        }

        // Execute the command with a timeout
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        // Check if timeout command exists (Linux) or gtimeout (macOS with coreutils)
        $timeout_exists = shell_exec('which timeout') !== null;
        $gtimeout_exists = shell_exec('which gtimeout') !== null;

        $cmd_prefix = '';
        if ($timeout_exists) {
            $cmd_prefix = 'timeout 10 ';
        } elseif ($gtimeout_exists) {
            $cmd_prefix = 'gtimeout 10 ';
        }
        $process = proc_open($cmd_prefix . $command_string, $descriptorspec, $pipes, ABSPATH);

        if (is_resource($process)) {
            // Close stdin
            fclose($pipes[0]);

            // Read stdout
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Read stderr
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            // Close process
            $return_value = proc_close($process);

            // Check for errors
            if ($return_value !== 0) {
                if (!empty($error)) {
                    // Sanitize error message
                    $error_message = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
                    return [
                        'output' => ["Error executing command: $error_message"],
                        'type' => 'error'
                    ];
                } else {
                    return [
                        'output' => ["Command exited with code $return_value"],
                        'type' => 'error'
                    ];
                }
            }

            // Process output
            $output_lines = explode("\n", $output);
            $output_lines = array_filter($output_lines, function($line) {
                return trim($line) !== '';
            });

            // Process output with proper sanitization
            $sanitized_lines = [];
            foreach ($output_lines as $line) {
                // Allow certain HTML tags but sanitize the content
                $allowed_html = [
                    'strong' => [],
                    'em' => [],
                    'code' => [],
                    'a' => [
                        'href' => [],
                        'target' => [],
                        'rel' => []
                    ],
                    'br' => []
                ];

                // Use WordPress's sanitization function
                $sanitized_line = wp_kses($line, $allowed_html);
                $sanitized_lines[] = $sanitized_line;
            }

            // Limit output to 100 lines to prevent overwhelming the UI
            if (count($sanitized_lines) > 100) {
                $sanitized_lines = array_slice($sanitized_lines, 0, 100);
                $sanitized_lines[] = "... (output truncated, showing first 100 lines)";
            }

            return [
                'output' => $sanitized_lines,
                'type' => 'info'
            ];
        } else {
            return [
                'output' => ['Error: Failed to execute command.'],
                'type' => 'error'
            ];
        }
    }
}
