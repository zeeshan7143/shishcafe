<?php

defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_DatabaseUpdateElementor extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        $success = false;
        $message = '';
        $plugin_slug = $this->input('slug');

        if (substr($plugin_slug, -4) !== '.php') {
            $plugin_slug .= '.php';
        }

        if ($plugin_slug != 'elementor-pro/elementor-pro.php' && $plugin_slug != 'elementor/elementor.php') {
            return array(
                'success' => true,
                'message' => 'Slug does not match either Elementor or Elementor Pro slugs.',
            );
        }

        // Elementor is the core plugin that has to be active in order to flush CSS.
        // Elementor Pro is only an extension of the core plugin.
        if (!is_plugin_active('elementor/elementor.php')) {
            return array(
                'success' => true,
                'message' => 'Elementor plugin is not active, skipping database update.',
            );
        }

        /** @see https://plugins.svn.wordpress.org/elementor/trunk/ */
        $plugin_file = WP_PLUGIN_DIR . '/elementor/elementor.php';
        $manager_file = WP_PLUGIN_DIR . '/elementor/core/upgrade/manager.php';

        if (file_exists($plugin_file) && file_exists($manager_file)) {
            include_once $plugin_file; // phpcs:ignore
            include_once $manager_file; // phpcs:ignore
        }

        $data = get_file_data($plugin_file, array('Version' => 'Version'));
        $version = $data['Version'];

        /** @since at least 3.0.0 */
        $manager_class = '\Elementor\Core\Upgrade\Manager';

        if (!class_exists($manager_class)) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  $manager_class . ' class not found. Version ' . $version,
            );
        }

        $manager = new $manager_class();

        if (
            !method_exists($manager, 'should_upgrade')
            || !method_exists($manager, 'get_task_runner')
            || !method_exists($manager, 'get_upgrade_callbacks')
            || !method_exists($manager, 'on_runner_complete')
        ) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  'One of ' . $manager_class . ' methods not found. Version ' . $version,
            );
        }

        if (!$manager->should_upgrade()) {
            return array(
                'success' => true,
                'message' => 'Elementor ' . $version . ' plugin database is up to date.',
            );
        }

        try {
            $updater = $manager->get_task_runner();
            $callbacks = $manager->get_upgrade_callbacks();
            $did_tasks = false;
            if (!empty($callbacks)) {
                $updater->handle_immediately($callbacks);
                $did_tasks = true;
            }
            $manager->on_runner_complete($did_tasks);
            $success = true;
            $message = 'Elementor ' . $version . ' plugin database upgraded successfully.';
        } catch (Exception $err) {
            $message = $err->getMessage();
        }

        return array(
            'success' => $success,
            'message' => $message,
        );
    }
}
