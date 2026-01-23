<?php

namespace DebugLogConfigTool\Controllers;

class TerminalSettingsController
{
    protected $terminalEnabledOption = 'dlct_terminal_enabled';
    protected $dbCommandsEnabledOption = 'dlct_db_commands_enabled';
    
    /**
     * Get terminal settings
     */
    public function get()
    {
        Helper::verifyRequest();
        
        // Default values
        $terminalEnabled = $this->isTerminalEnabled();
        $dbCommandsEnabled = $this->isDatabaseCommandsEnabled();
        
        wp_send_json_success([
            'terminal_enabled' => $terminalEnabled,
            'db_commands_enabled' => $dbCommandsEnabled,
            'success' => true
        ]);
    }
    
    /**
     * Update terminal settings
     */
    public function update()
    {
        Helper::verifyRequest();
        
        // Get and sanitize values
        $terminalEnabled = isset($_POST['terminal_enabled']) ? 
            (sanitize_text_field($_POST['terminal_enabled']) === 'true' || rest_sanitize_boolean($_POST['terminal_enabled']) === true) : 
            false;
            
        $dbCommandsEnabled = isset($_POST['db_commands_enabled']) ? 
            (sanitize_text_field($_POST['db_commands_enabled']) === 'true' || rest_sanitize_boolean($_POST['db_commands_enabled']) === true) : 
            false;
        
        // Update options
        update_option($this->terminalEnabledOption, $terminalEnabled ? 'yes' : 'no');
        update_option($this->dbCommandsEnabledOption, $dbCommandsEnabled ? 'yes' : 'no');
        
        wp_send_json_success([
            'message' => 'Terminal settings updated successfully',
            'terminal_enabled' => $terminalEnabled,
            'db_commands_enabled' => $dbCommandsEnabled,
            'success' => true
        ]);
    }
    
    /**
     * Check if terminal is enabled
     * 
     * @return bool
     */
    public function isTerminalEnabled()
    {
        $option = get_option($this->terminalEnabledOption, 'yes'); // Default to enabled
        return $option === 'yes';
    }
    
    /**
     * Check if database commands are enabled
     * 
     * @return bool
     */
    public function isDatabaseCommandsEnabled()
    {
        $option = get_option($this->dbCommandsEnabledOption, 'no'); // Default to disabled
        return $option === 'yes';
    }
}
