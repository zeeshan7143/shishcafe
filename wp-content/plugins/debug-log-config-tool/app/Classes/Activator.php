<?php

namespace DebugLogConfigTool\Classes;

use DebugLogConfigTool\Controllers\ConfigController;
use DebugLogConfigTool\Controllers\NotificationController;
use DebugLogConfigTool\Controllers\SettingsController;

class Activator
{
    public function run()
    {
        try {
            $this->saveInitialConstants();
            $this->updateDebugConstants();
        } catch (\Exception $e){

        }

    }

    private function saveInitialConstants()
    {
        ConfigController::getInstance()->storeInitialValues();
    }

    /**
     * Add new if not existent constants
     * @return void
     */
    private function updateDebugConstants()
    {
        $constantManager = ConfigController::getInstance();
        $updatedConstants = [];
        $debugConstants = (new \DebugLogConfigTool\Controllers\SettingsController())->getConstants();
        foreach ($debugConstants as $constantKey=>$constant) {
            $value = $constant['value'];
            // Set all debug constants to true or get the existing value if already true
            $success = $constantManager->update($constantKey, $value, '');
            if (!$success) {
                $value = $constantManager->getValue($constantKey);
            }

            $updatedConstants[] = [
                'name'  => strtoupper($constantKey),
                'value' => $value,
                'type'  => 'raw'
            ];
        }
        (new SettingsController())->store($updatedConstants);
    }
}
