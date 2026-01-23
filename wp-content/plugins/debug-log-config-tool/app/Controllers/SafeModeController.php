<?php

namespace DebugLogConfigTool\Controllers;

class SafeModeController
{
    protected static $isInSafeMode = false;
    protected $optionKey = 'dlct_safe_mode';
    
    public function get()
    {
        Helper::verifyRequest();
        $allPlugins = $this->getPluginListGroup();
        $selectedPlugins = json_decode(get_option('dlct_selected_active_plugins_list'), true);
        
        $res = [
            'all_plugins'                    => $allPlugins,
            'safe_mode_status'               => get_option('safe_mode_status') == 'on',
            'selected_active_plugins_list'   => $this->selectedPluginsFormatted($selectedPlugins),
        ];
        wp_send_json_success($res);
    }
    
    public function selectedPluginsFormatted($selected_active_plugins_list) {
        $items = [];
        if(empty($selected_active_plugins_list)){
            return $items;
        }
        foreach ($selected_active_plugins_list as $plugin) {
            $plugin_data = [
                "name" => $plugin['name'], // Extracting plugin name from file path
                "value" => $plugin['value']
            ];
            $items[] = $plugin_data;
        }
    
        return $items;
    }
    
    public function update()
    {
        Helper::verifyRequest();
        $safeMode = sanitize_text_field($_POST['safe_mode']) === 'true' || rest_sanitize_boolean($_POST['safe_mode']) === true;
        
        $selectedPlugins = stripslashes($_POST['selected_plugins']);
        update_option('dlct_selected_active_plugins_list', $selectedPlugins);
    
        try {
            if ($safeMode === true) {
                $this->activateSafeMode();
            } else {
                $this->deActivateSafeMode();
            }
        } catch (\Exception $e){
            wp_send_json_error([
                'message' => $e->getMessage(),
                'success' => false
            ]);
        }
       
    }
    
    public function activateSafeMode()
    {
        if (get_option('safe_mode_status') == 'on') {
            $message = 'SafeMode is already activated';
          
        } else {
            $message = 'SafeMode is activated! Reload to see the result.';
            update_option('safe_mode_status', 'on');
        }
        
        $activePlugins = get_option('active_plugins');
        update_option('before_safe_mode_active_plugins_list', $activePlugins);
        
        $selectedPlugins = get_option('dlct_selected_active_plugins_list');
        
        $selectedPlugins = json_decode($selectedPlugins, true);
        if (!is_array($selectedPlugins)) {
            $selectedPlugins = [];
        }
        $selectedPlugins = array_column($selectedPlugins,'value');
        
        $allPlugins = get_plugins();
        
        $pluginsActivated = [];
        $pluginsDeActivated = [];
        
        foreach ($allPlugins as $plugin => $pluginDetails) {
        
            if (in_array($plugin, $selectedPlugins)) {
                if (in_array($plugin, $activePlugins)) {
                    continue;
                }
                $pluginsActivated[$plugin] = $pluginDetails;
                activate_plugins($plugin);
            } else {
                deactivate_plugins($plugin);
                $pluginsDeActivated[$plugin] = $pluginDetails;
            }
        }
        wp_send_json_success([
            'message'             => $message,
            'activated_plugins'   => $pluginsActivated,
            'deactivated_plugins' => $pluginsDeActivated,
            'success'             => true
        ]);
    }
    
    public function deActivateSafeMode()
    {
        // Reset safe mode flag to OFF
        if (get_option('safe_mode_status') == 'off') {
            wp_send_json_success([
                'message' => 'SafeMode is already deactivated',
                'success' => true
            ]);
        } else {
            update_option('safe_mode_status', 'off');
        }
        
        $activePlugins = get_option('active_plugins');
        $beforeSafeModePlugins = get_option('before_safe_mode_active_plugins_list');
        
        // If no plugins were active before safe mode, deactivate all currently active plugins
        if (!is_array($beforeSafeModePlugins)) {
            foreach ($activePlugins as $plugin) {
                deactivate_plugins($plugin);
            }
            return;
        }
        
        
        // Deactivate plugins not in the list of plugins before safe mode
        foreach ($activePlugins as $plugin) {
            if (!in_array($plugin, $beforeSafeModePlugins)) {
                deactivate_plugins($plugin);
            }
        }
        
        // Activate plugins that were in the list before safe mode but are currently inactive
        foreach ($beforeSafeModePlugins as $plugin) {
            if (!in_array($plugin, $activePlugins)) {
                activate_plugin($plugin);
            }
        }
        wp_send_json_success([
            'message' => 'SafeMode Deactivated!Reload to see the result.',
            'success' => true
        ]);
    }
    
    private function getPluginListGroup()
    {
        $all_plugins = get_plugins();
        $formatted_plugins = [];
        foreach ($all_plugins as $key => $plugin_file) {
            $formatted_plugins[] = [
                'name' => $plugin_file['Name'],
                'value' => $key,
            ];
        }
        
        return $formatted_plugins;
    }
}
