<?php

namespace DebugLogConfigTool\Classes;
use DebugLogConfigTool\Controllers\ConfigController;
use DebugLogConfigTool\Controllers\NotificationController;

class DeActivator
{

    public function run()
    {
        @ConfigController::getInstance()->restoreInitialState();
        (new NotificationController())->deactivate();
    }

}
