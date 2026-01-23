<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_ExtensionsUpdatesPurge extends AutoUpdater_Task_Base
{
    protected $admin_privileges = true;

    /**
     * @return array
     */
    public function doTask()
    {
        $type = $this->input('type', '');

        switch ($type) {
            case 'plugin':
                wp_cache_delete('plugins', 'plugins');
                delete_site_transient('update_plugins');
                break;
            case 'theme':
                delete_site_transient('update_themes');
                break;
            default:
                wp_cache_delete('plugins', 'plugins');
                delete_site_transient('update_plugins');
                delete_site_transient('update_themes');
        }

        return array(
            'success' => true,
            'message' => 'Updates purged successfully',
        );
    }
}
