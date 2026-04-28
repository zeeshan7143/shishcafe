<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$upload_dir = wp_upload_dir();
$folder = trailingslashit($upload_dir['basedir']) . 'order-prints/';

if (file_exists($folder)) {
    $files = glob($folder . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($folder); // remove folder after clearing
}
?>