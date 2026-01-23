<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_DatabaseUpdateAcf extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */

    public function doTask()
    {
        $success = false;
        $message = '';
        $acf_slug = $this->input('slug');
        $acf_dir = '/' . strtok($acf_slug, '/');
        $acf_name = 'Advanced Custom Fields' . (strpos($acf_slug, 'pro') !== false ? ' Pro' : '');

        if (substr($acf_slug, -4) !== '.php') {
            $acf_slug .= '.php';
        }

        if (!is_plugin_active($acf_slug)) {
            return array(
                'success' => true,
                'message' => $acf_name . ' plugin is not active, skipping database update.',
            );
        }

        $plugin_file = WP_PLUGIN_DIR . $acf_dir . '/acf.php';
        if (file_exists($plugin_file)) {
            include_once $plugin_file; // phpcs:ignore
        }

        if (file_exists(WP_PLUGIN_DIR . $acf_dir . '/includes/upgrades.php')
        && (!function_exists('acf_upgrade_all') || !function_exists('acf_has_upgrade'))) {
            include_once WP_PLUGIN_DIR . $acf_dir . '/includes/upgrades.php';
        }

        $data = get_file_data($plugin_file, array('Version' => 'Version'));
        $version = $data['Version'];

        if (!defined('ACF') || !defined('ACF_VERSION')) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' => $acf_name . ' ' . $version . ' plugin not loaded.',
            );
        }

        if (!function_exists('acf_upgrade_all') || !function_exists('acf_has_upgrade')) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' => $acf_name . ' ' . $version . ' plugin upgrade functions not found.',
            );
        }

        if (!acf_has_upgrade()) {
            return array(
                'success' => true,
                'message' => $acf_name . ' ' . $version . ' plugin database is up to date.',
            );
        }

        try {
            acf_upgrade_all();
            $success = true;
            $message = $acf_name . ' ' . $version . ' plugin database upgraded successfully.';
        } catch (Exception $err) {
            $message = $err->getMessage();
        }

        return array(
            'success' => $success,
            'message' => $message,
        );
    }
}
