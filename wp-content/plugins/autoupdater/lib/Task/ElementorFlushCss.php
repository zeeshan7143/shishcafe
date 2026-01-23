<?php

defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_ElementorFlushCss extends AutoUpdater_Task_Base
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
                'message' => 'Slug does not match either Elementor or Elementor Pro.',
            );
        }

        // Elementor is the core plugin that has to be active in order to flush CSS.
        // Elementor Pro is only an extension of the core plugin.
        if (!is_plugin_active('elementor/elementor.php')) {
            return array(
                'success' => true,
                'message' => 'Elementor plugin is not active, skipping flushing CSS.',
            );
        }

        /** @see https://plugins.svn.wordpress.org/elementor/trunk/ */
        $plugin_file = WP_PLUGIN_DIR . '/elementor/elementor.php';
        $manager_file = WP_PLUGIN_DIR . '/elementor/core/files/manager.php';

        $data = get_file_data($plugin_file, array('Version' => 'Version'));
        $version = $data['Version'];

        if (version_compare($version, '1.2.0', '<')) {
            return array(
                'success' => true,
                'message' => 'Elementor ' . $version . ' is too old.',
            );
        }

        if (file_exists($plugin_file) && file_exists($manager_file)) {
            include_once $plugin_file; // phpcs:ignore
            include_once $manager_file; // phpcs:ignore
        }

        /** @since 1.2.0 */
        $manager_class = '\Elementor\Core\Files\Manager';

        if (!class_exists($manager_class)) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  $manager_class . ' class not found. Version ' . $version,
            );
        }

        $manager = new $manager_class();

        /** @since 1.2.0 */
        if (!method_exists($manager, 'clear_cache')) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  $manager_class . '::clear_cache method not found. Version ' . $version,
            );
        }

        $generate_css = version_compare($version, '3.25.0', '>=');
        /** @since 3.25.0 */
        if ($generate_css && !method_exists($manager, 'generate_css')) {
            return array(
                'success' => true,
                'needs_refactor' => true,
                'message' =>  $manager_class . '::generate_css method not found. Version ' . $version,
            );
        }

        $network = !empty($assoc_args['network']) && is_multisite();
        if ($network) {
            $blogs = get_sites();
            foreach($blogs as $keys => $blog) {
                switch_to_blog($blog_id);
                $manager->clear_cache();
                if ($generate_css) $manager->generate_css();
                $manager->clear_cache();
                restore_current_blog();
            }
        } else {
            $manager->clear_cache();
            if ($generate_css) $manager->generate_css();
            $manager->clear_cache();
        }

        return array(
            'success' => true,
	        'message' => 'Elementor ' . $version . ' CSS cache flushed successfully.',
        );
    }
}
