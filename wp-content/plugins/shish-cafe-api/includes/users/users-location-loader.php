<?php

if (!defined('ABSPATH')) {
    exit;
}

// Include all user-related modules
require_once __DIR__ . '/class-shish-cafe-user-service.php';
require_once __DIR__ . '/class-shish-cafe-user-routes.php';
require_once __DIR__ . '/class-shish-cafe-user-locations.php';
require_once __DIR__ . '/class-shish-cafe-manager-role.php';
require_once __DIR__ . '/class-shish-cafe-locations-admin.php';
require_once __DIR__ . '/user-api/class-shish-cafe-users-api.php';

// Initialize all user-related class modules
// (Function-based modules auto-init via their action hooks)
Shish_Cafe_Locations_Admin::init();
Shish_Cafe_Manager_Role::init();
Shish_Cafe_User_Locations::init();
