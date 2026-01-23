<?php

namespace DebugLogConfigTool\Classes;

use DebugLogConfigTool\Controllers\LogController;

class DashboardWidget
{
    public function init()
    {
        ob_start(); // Start output buffering

        $logs = (new LogController)->loadLogs(5);
        if (!$logs || !empty($logs)) {
            echo 'You can see your submission stats here';
        } else {
            $this->printStats($logs['logs']);
        }

        $output = ob_get_clean();

        echo $output;
    }

    private function printStats($stats)
    {
        ?>
        <div class="">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('Count', 'debug-log-config-tool'); ?></th>
                    <th><?php _e('Details', 'debug-log-config-tool'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($stats as $stat): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo $stat['details']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
