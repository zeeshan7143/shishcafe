<?php

/** @var \DebugLogConfigTool\Router $router */
$router->get('get_log', 'LogController@get');
$router->post('clear_debug_logs', 'LogController@clearDebugLog');
$router->post('clear_query_logs', 'LogController@clearQueryLog');
$router->post('generate_test_logs', 'LogController@generateTestLogs');
$router->post('terminal_command', 'TerminalController@executeCommand');
$router->get('get_settings', 'SettingsController@get');
$router->post('update_settings', 'SettingsController@update');
$router->get('get_notification_email', 'NotificationController@getNotificationEmail');
$router->post('update_notification_email', 'NotificationController@updateNotificationEmail');
$router->post('update_safe_mode', 'SafeModeController@update');
$router->get('get_safe_mode', 'SafeModeController@get');


