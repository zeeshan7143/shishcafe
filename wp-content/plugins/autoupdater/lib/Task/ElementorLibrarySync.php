<?php

defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_ElementorLibrarySync extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        $plugin_slug = $this->input('slug');

        if (substr($plugin_slug, -4) !== '.php') {
            $plugin_slug .= '.php';
        }

        if ($plugin_slug !== 'elementor-pro/elementor-pro.php' && $plugin_slug !== 'elementor/elementor.php') {
            return array(
                'success' => true,
                'message' => 'Slug does not match either Elementor or Elementor Pro'
            );
        }

        // Elementor is the core plugin that has to be active in order to flush CSS.
        // Elementor Pro is only an extension of the core plugin.
        if (!is_plugin_active('elementor/elementor.php')) {
            return array(
                'success' => true,
                'message' => 'Elementor plugin is not active, skipping library sync.',
            );
        }

        /** @see https://plugins.svn.wordpress.org/elementor/trunk/ */
        $plugin_file = WP_PLUGIN_DIR . '/elementor/elementor.php';
        $api_file = WP_PLUGIN_DIR . '/elementor/includes/api.php';

        $data = get_file_data($plugin_file, array('Version' => 'Version'));
        $version = $data['Version'];

        if (version_compare($version, '2.0.0', '<')) {
            return array(
                'success' => true,
                'message' => 'Elementor ' . $version . ' is too old.',
            );
        }

        if (file_exists($plugin_file) && file_exists($api_file)) {
            include_once $plugin_file; // phpcs:ignore
            include_once $api_file; // phpcs:ignore
        }

        /** @since 1.0.0 */
        $api_class = '\Elementor\Api';

        if (!class_exists($api_class)) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  $api_class . ' class not found. Version ' . $version,
            );
        }

        $api = new $api_class();

        /** @since 2.0.0 released on Feb, 2018 */
        if (!method_exists($api_class, 'get_library_data')) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' => $api_class . '::get_library_data method not found. Version ' . $version,
            );
        }

        $data = $api->get_library_data(true);
        if (empty($data)) {
            return array(
                'success' => false,
                'message' =>  'Elementor ' . $version . ' library sync failed.',
            );
        }

        return array(
            'success' => true,
            'message' => 'Elementor ' . $version . ' library sync was successful.',
        );
    }
}
