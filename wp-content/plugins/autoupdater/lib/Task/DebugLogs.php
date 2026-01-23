<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_DebugLogs extends AutoUpdater_Task_Base
{
    protected $encrypt = false;

    /**
     * @return array
     */
    public function doTask()
    {
        $date = preg_replace('/[^0-9-:+TZ\.]/', '', $this->input('date'));
        $date_from = new DateTime($date ? $date : 'now');
        if (strlen($date) <= 10) {
            // When no time given, set the begining of the day
            $date_from->setTime(0, 0, 0);
        }
        $path = AutoUpdater_Log::getInstance()->getLogsFilePath($date_from);

        $filemanager = AutoUpdater_Filemanager::getInstance();
        if (!$filemanager->is_file($path)) {
            throw new AutoUpdater_Exception_Response('Logs file with date: ' . $date_from->format('Y-m-d') . ' was not found', 404);
        }

        $logs = $this->getLogsFromFile($path, $date_from);

        $output = preg_replace('/[^a-z]/', '', $this->input('output', 'text'));

        return array(
            'success' => true,
            'logs' => $output === 'json' ? $logs : $this->logsToString($logs),
        );
    }

    /**
     * @param string $path
     * @param DateTime $date_from
     *
     * @return array
     */
    protected function getLogsFromFile($path, $date_from)
    {
        $logs = array();
        $handle = @fopen($path, 'r'); // phpcs:ignore
        if (!$handle) {
            return $logs;
        }

        $message_buffer = '';
        $tmp_log = array();

        while (($line = fgets($handle)) !== false) { // phpcs:ignore
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]{1}\d{2}:\d{2}:\d{2}(?:\+\d{2}\:\d{2}|Z)?)\] (DEBUG|INFO|WARN|ERROR) (.+)$/', $line, $raw_log)) {
                // Store the previous log with the full message
                if (isset($tmp_log['date'])) {
                    $tmp_log['message'] = $message_buffer;
                    $logs[] = $tmp_log;
                }

                $log_date = new DateTime($raw_log[1]);
                // Skip too old logs
                if ($log_date->getTimestamp() < $date_from->getTimestamp()) {
                    $message_buffer = '';
                    $tmp_log = array();
                    continue;
                }

                // Preapre the next log
                $message_buffer = $raw_log[3];
                $tmp_log = array(
                    'date' => $log_date->format('c'),
                    'level' => $raw_log[2],
                );
            } else {
                // When no new log detected, then store it as the next line of the message
                $message_buffer .= $line;
            }
        }

        // Store the last log with the full message
        if (isset($tmp_log['date'])) {
            $tmp_log['message'] = $message_buffer;
            $logs[] = $tmp_log;
        }

        fclose($handle); // phpcs:ignore

        return $logs;
    }

    /**
     * @param array $logs
     *
     * @return string
     */
    protected function logsToString($logs)
    {
        $output = '';
        foreach ($logs as $log) {
            $output .= sprintf("[%s] %s %s\n", $log['date'], $log['level'], $log['message']);
        }

        return $output;
    }
}
