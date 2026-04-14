<?php
/**
 * Plugin Name: Remove Seraphinite Accelerator Banner
 * Description: Removes Seraphinite banner using external JS file.
 * Version: 1.0
 * Author: Enigmatix Global
 * link: https://enigmatix.io
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueue JS file
 */
add_action('wp_enqueue_scripts', function () {

    wp_enqueue_script(
        'remove-seraphinite-banner',
        plugin_dir_url(__FILE__) . 'assets/js/remove-banner.js',
        [],
        '1.0',
        true // load in footer
    );

}, 999);