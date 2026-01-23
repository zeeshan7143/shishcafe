<?php

/**
 *
 * Plugin Name:       Debug Log Manager Tool
 * Plugin URI:        https://nkb-bd.github.io/
 * Description:       Debug & Query log Helper tool with additional CLI like tools
 * Version:           2.0.1
 * Author:            Lukman Nakib
 * Author URI:        https://nkb-bd.github.io/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       debug-log-config-tool
 */


use DebugLogConfigTool\Classes\DLCT_Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}
define('DLCT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DLCT_PLUGIN_VERSION', '1.5.3');
define('DLCT_PLUGIN_MAIN_FILE', __FILE__);
define('DLCT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('DLCT_PLUGIN_DIR', plugin_dir_path(__FILE__));

include dirname( __FILE__ ) . '/autoload.php';
add_action('init', function () {
    $dlct_bootstrap = DLCT_Bootstrap::getInstance();
    $dlct_bootstrap->init();
}, 10, 1);
register_activation_hook(DLCT_PLUGIN_MAIN_FILE, function () {
    DLCT_Bootstrap::activate();
});

register_deactivation_hook(DLCT_PLUGIN_MAIN_FILE, function () {
    DLCT_Bootstrap::deactivate();
});


add_action('shutdown', function (){
    \DebugLogConfigTool\Controllers\LogController::maybeCacheQueries();
});


