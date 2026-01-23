<?php

namespace DebugLogConfigTool\Controllers;

use DebugLogConfigTool\Classes\DLCT_Bootstrap;

class LogController
{
    private $logFilePath;
    private $originalLogFilePath;

    public function __construct()
    {
        $debugPath =  $this->setRandomLogPath();
        $this->logFilePath = apply_filters('wp_dlct_log_file_path', $debugPath);
    }

    public function get()
    {
        Helper::verifyRequest();

        if(empty( $this->logFilePath )){
            $this->logFilePath = $this->setRandomLogPath();
        }
        try {
            if (!file_exists($this->logFilePath)) {
                wp_send_json_error(['message' => 'Debug log file not found']);
            }

            // Check if we should only get new logs
            $lastModified = isset($_GET['last_modified']) ? intval($_GET['last_modified']) : 0;
            $lastSize = isset($_GET['last_size']) ? intval($_GET['last_size']) : 0;
            $currentSize = $this->getFilesize();

            // If file hasn't changed, return empty logs with current size
            if ($lastSize > 0 && $lastSize === $currentSize) {
                wp_send_json_success([
                    'success' => true,
                    'log_path' => $this->logFilePath,
                    'logs' => [],
                    'error_types' => [],
                    'file_size' => $currentSize,
                    'last_modified' => time(),
                    'no_changes' => true
                ]);
                return;
            }

            // Load logs, potentially only new ones
            $logData = $this->loadLogs(false, $lastModified);
            $isSaveQueryOn = \DebugLogConfigTool\Controllers\ConfigController::getInstance()->getValue('SAVEQUERIES');

            wp_send_json_success([
                'success' => true,
                'log_path'    => $this->logFilePath,
                'logs'        => $logData['logs'] ?? '',
                'error_types' => $logData['unique_error_types'] ?? '',
                'file_size'   => $currentSize,
                'last_modified' => time(),
                'query_logs'    => $this->getQueryLogs($isSaveQueryOn),
                'is_save_query_on' => $isSaveQueryOn === true || $isSaveQueryOn == 'true'
            ]);
        } catch (\Exception $e) {
            wp_send_json_success([
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getFilesize()
    {
        return file_exists($this->logFilePath) ? filesize($this->logFilePath) : false;
    }

    public function loadLogs($limit = false, $lastModified = 0)
    {
        // Check if log file exists

        if (!file_exists($this->logFilePath)) {
            // Log file doesn't exist, return empty array
            return [];
        }

        $fileSize = filesize($this->logFilePath);

        if ($fileSize === 0) {
            // Log file is empty
            return [
                'logs' => [],
                'unique_error_types' => [],
            ];
        }

        self::maybeCopyLogFromDefaultLogFile();

        $fh = fopen($this->logFilePath, 'r');

        if (!$fh) {
            // Failed to open log file
            return '';
        }

        $logs = [];
        $errorTypes = []; // Initialize error types array
        $i = 0;
        $fileModTime = filemtime($this->logFilePath);

        // If we're only looking for new logs and file hasn't been modified, return empty
        if ($lastModified > 0 && $fileModTime <= $lastModified) {
            // File not modified since last check
            fclose($fh);
            return [
                'logs' => [],
                'unique_error_types' => [],
            ];
        }

        // If we're looking for new logs, try to optimize by seeking to the end minus a reasonable buffer
        if ($lastModified > 0) {
            $fileSize = filesize($this->logFilePath);
            $seekPosition = max(0, $fileSize - 50000); // Look at last ~50KB of the file for new logs
            fseek($fh, $seekPosition);

            // If we're not at the beginning, discard the first line as it might be partial
            if ($seekPosition > 0) {
                fgets($fh);
            }
        }

        $lineCount = 0;
        $parsedCount = 0;
        $failedCount = 0;

        while ($line = @fgets($fh)) {
            $lineCount++;

            // Skip empty lines
            if (trim($line) === '') {
                continue;
            }

            // Debug first few lines
            // Debug line processing removed

            $logEntry = $this->parseLogLine($line);

            if ($logEntry !== false) {
                $parsedCount++;

                // If we're only looking for new logs, check the timestamp
                if ($lastModified > 0) {
                    $entryTime = strtotime($logEntry['date'] . ' ' . $logEntry['time']);
                    if ($entryTime && $entryTime <= $lastModified) {
                        continue; // Skip older entries
                    }
                }

                $logs[] = $logEntry;

                // Extract and store error type
                if (is_array($logEntry) && !empty($logEntry['error_type'])) {
                    $errorTypes[$logEntry['error_type']] = true;
                }

                $i++; // Increment the counter

                if ($limit && $i >= $limit) {
                    break;
                }
            } else {
                // Check if this is a continuation line (like a stack trace) that should be appended to the previous log entry
                if (!empty($logs)) {
                    $trimmedLine = trim($line);
                    $lastIndex = count($logs) - 1;
                    $isStackTraceLine = false;

                    // Check for standard stack trace lines
                    if (preg_match('/^\s*#\d+\s+/', $line) || strpos($trimmedLine, 'thrown in') === 0) {
                        $isStackTraceLine = true;
                    }
                    // Check for lines that might be part of a stack trace but don't match the standard pattern
                    else if (strpos($trimmedLine, '{main}') !== false ||
                             strpos($trimmedLine, '...') === 0 ||
                             (strpos($trimmedLine, '->') !== false && strpos($trimmedLine, '.php') !== false) ||
                             (strpos($trimmedLine, '::') !== false && strpos($trimmedLine, '.php') !== false)) {
                        $isStackTraceLine = true;
                    }
                    // Check if this might be a continuation of a previous stack trace line
                    else if (isset($logs[$lastIndex]['details']) &&
                             (strpos($logs[$lastIndex]['details'], 'Stack trace:') !== false ||
                              strpos($logs[$lastIndex]['details'], 'Backtrace:') !== false)) {
                        // If the previous log entry contains a stack trace header, this might be part of it
                        $isStackTraceLine = true;
                    }

                    if ($isStackTraceLine) {
                        // This is likely a stack trace line, append it to the details of the last log entry
                        $logs[$lastIndex]['details'] .= "\n" . $line;

                        // If we have a stack trace array, add this line to it
                        if (!isset($logs[$lastIndex]['stack_trace'])) {
                            $logs[$lastIndex]['stack_trace'] = [];
                        }
                        $logs[$lastIndex]['stack_trace'][] = $trimmedLine;

                        // Re-extract the stack trace from the updated details
                        $logs[$lastIndex]['stack_trace'] = $this->extractStackTrace($logs[$lastIndex]['details']);
                    } else {
                        $failedCount++;
                        // Debug first few failed lines
                        // Debug failed parsing removed
                    }
                } else {
                    $failedCount++;
                    // Debug first few failed lines
                    // Debug failed parsing removed
                }
            }
        }

        fclose($fh);
        $uniqueErrorTypes = array_keys($errorTypes);

        // Removed error_log statements to reduce memory usage

        return [
            'logs'               => array_reverse($logs),
            'unique_error_types' => $uniqueErrorTypes,
        ];
    }



    private function parseLogLine($line)
    {
        // Try to handle different log formats
        $sep = '$!$';

        // Check if this is a continuation of a stack trace (starts with '#' followed by a number)
        if (preg_match('/^\s*#\d+\s+/', $line) || strpos(trim($line), 'thrown in') === 0) {
            // This is likely part of a stack trace, not a new log entry
            // Return false so it can be appended to the previous log entry
            return false;
        }

        // Standard WordPress log format: [YYYY-MM-DD HH:MM:SS timezone] message
        $standardFormat = preg_replace("/^\[([0-9a-zA-Z-]+) ([0-9:]+) ([a-zA-Z_\/]+)\] (.*)$/i", "$1" . $sep . "$2" . $sep . "$3" . $sep . "$4", $line);

        // Check if the line was matched by the standard format
        if ($standardFormat !== $line) {
            $parts = explode($sep, $standardFormat);
            if (count($parts) >= 4) {
                // Standard format matched
                return $this->parseStandardFormat($parts, $line);
            }
        }



        // Alternative format: [YYYY-MM-DD HH:MM:SS] message (no timezone)
        if (preg_match("/^\[([0-9-]+) ([0-9:]+)\] (.*)$/i", $line, $matches)) {
            $logTime = strtotime($matches[1] . ' ' . $matches[2]);
            if ($logTime === false) {
                // If we can't parse the time, use current time
                $logTime = current_time('U');
            }

            $details = $matches[3];

            // Extract stack trace if available
            $stackTrace = $this->extractStackTrace($details);

            // Extract file location and line number
            $fileLocation = '';
            $lineNumber = '';
            if (preg_match('/in\s+([^\s]+)\s+on\s+line\s+(\d+)/', $details, $matches)) {
                $fileLocation = $matches[1];
                $lineNumber = $matches[2];
            }

            return [
                'date' => date('d/m/y', $logTime),
                'time' => $this->formatTimeAgo($logTime),
                'raw_time' => $logTime,
                'timezone' => '',
                'details' => $details,
                'error_type' => $this->extractErrorType($details),
                'plugin_name' => $this->extractPluginName($details),
                'file_location' => $fileLocation,
                'line_number' => $lineNumber,
                'stack_trace' => $stackTrace
            ];
        }

        // Simple format: just the message without timestamp
        if (strpos($line, 'PHP ') === 0 && (strpos($line, 'Notice') !== false || strpos($line, 'Warning') !== false ||
            strpos($line, 'Fatal error') !== false || strpos($line, 'Parse error') !== false || strpos($line, 'Deprecated') !== false)) {
            $currentTime = current_time('U');

            // Extract stack trace if available
            $stackTrace = $this->extractStackTrace($line);

            // Extract file location and line number
            $fileLocation = '';
            $lineNumber = '';
            if (preg_match('/in\s+([^\s]+)\s+on\s+line\s+(\d+)/', $line, $matches)) {
                $fileLocation = $matches[1];
                $lineNumber = $matches[2];
            }

            return [
                'date' => date('d/m/y', $currentTime),
                'time' => $this->formatTimeAgo($currentTime),
                'raw_time' => $currentTime,
                'timezone' => '',
                'details' => $line,
                'error_type' => $this->extractErrorType($line),
                'plugin_name' => $this->extractPluginName($line),
                'file_location' => $fileLocation,
                'line_number' => $lineNumber,
                'stack_trace' => $stackTrace
            ];
        }

        // If we couldn't parse the line with any known format, return false
        return false;
    }

    private function parseStandardFormat($parts, $line)
    {
        $info = stripslashes($parts[3]);
        $time = strtotime($parts[1]);

        $pluginName = $this->extractPluginName($info);
        $errorType = $this->extractErrorType($info);

        // Check if the line contains a PHP array
        if (preg_match('/\[(.*?)\]/', $info, $matches)) {
            // Extracting the PHP array string
            $arrayString = $matches[1];
            return [
                'date' => date('d/m/y', $time),
                'time' => $this->formatTimeAgo($time),
                'raw_time' => $time,
                'timezone' => $parts[2],
                'details' => $arrayString,
                'error_type' => '',
                'plugin_name' => '',
                'file_location' => '',
                'line_number' => '',
                'data_array' => ''
            ];
        } else {
            // Extract file location and line number
            preg_match('/^(.*?) on line (\d+)/', $info, $errorDetails);
            $fileLocation = isset($errorDetails[1]) ? trim($errorDetails[1]) : '';
            // Extract file location using regular expression
            preg_match('/in\s(.*?)(?=\(\d+\))/', $fileLocation, $locationMatches);
            $fileLocation = isset($locationMatches[1]) ? $locationMatches[1] : '';
            // Extracted file location
            $lineNumber = isset($errorDetails[2]) ? $errorDetails[2] : '';

            // Extract stack trace if available
            $stackTrace = $this->extractStackTrace($info);

            // If no stack trace was found but the error message contains line numbers and file paths,
            // create a simple stack trace from the error message
            if (empty($stackTrace) && preg_match('/in\s+([^\s]+)\s+on\s+line\s+(\d+)/', $info, $matches)) {
                $errorFile = $matches[1];
                $errorLine = $matches[2];
                $stackTrace[] = "Error occurred in {$errorFile} on line {$errorLine}";
            }

            return [
                'date' => date('d/m/y', $time),
                'time' => $this->formatTimeAgo($time),
                'raw_time' => $time,
                'timezone' => $parts[2],
                'details' => $info,
                'error_type' => $errorType,
                'plugin_name' => ucwords(str_replace('-', ' ', $pluginName)),
                'file_location' => $fileLocation,
                'line_number' => $lineNumber,
                'stack_trace' => $stackTrace
            ];
        }

        return false;
    }

    private function extractErrorType($logLine)
    {
        $pattern = '/PHP (Fatal error|Notice|Warning|Parse error|Deprecated):/';
        if (preg_match($pattern, $logLine, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function extractPluginName($info)
    {
        $pluginName = '';
        if (preg_match('/\/plugins\/([^\/]+)\//', $info, $pluginMatches)) {
            $pluginName = $pluginMatches[1];
            if (!file_exists(WP_PLUGIN_DIR . '/' . $pluginName . '/' . $pluginName . '.php')) {
                $pluginName = '';
            }
        }

        return $pluginName;
    }

    /**
     * Extract stack trace from log message
     *
     * @param string $logContent The log content to extract stack trace from
     * @return array Array of stack trace lines
     */
    private function extractStackTrace($logContent)
    {
        $stackTrace = [];

        if (strpos($logContent, 'Stack trace:') !== false) {
            // First try to extract the entire stack trace section
            $stackTraceStart = strpos($logContent, 'Stack trace:');
            $stackTraceText = substr($logContent, $stackTraceStart);

            // Split by newlines and process each line
            $stackLines = explode("\n", $stackTraceText);

            // Add the "Stack trace:" header line
            $stackTrace[] = trim($stackLines[0]);

            // Process all lines after the header
            $inStackTrace = true;
            $lastFrameNumber = -1;

            for ($i = 1; $i < count($stackLines); $i++) {
                $trimmedLine = trim($stackLines[$i]);

                // Skip empty lines
                if (empty($trimmedLine)) {
                    continue;
                }

                // Check if we've reached the end of the stack trace
                if (strpos($trimmedLine, 'Variable dump:') === 0) {
                    // We've reached the variable dump section
                    $stackTrace[] = $trimmedLine;
                    continue;
                }

                // Match standard stack trace lines (#0, #1, etc.)
                if (preg_match('/^#(\d+)\s+/', $trimmedLine, $matches)) {
                    $frameNumber = (int)$matches[1];
                    $lastFrameNumber = $frameNumber;
                    $stackTrace[] = $trimmedLine;
                }
                // Match the "thrown in" line that often appears at the end
                else if (strpos($trimmedLine, 'thrown in') === 0) {
                    $stackTrace[] = $trimmedLine;
                }
                // Match any line that looks like a stack frame but might not have the standard format
                else if (preg_match('/^\d+\s+/', $trimmedLine) ||
                         (strpos($trimmedLine, '.php') !== false && strpos($trimmedLine, '(') !== false)) {
                    $stackTrace[] = $trimmedLine;
                }
                // If the line doesn't match any pattern but we're still in the stack trace section
                // and it's not a known section header, it might be a continuation of the previous line
                else if ($inStackTrace &&
                         strpos($trimmedLine, 'Variable dump:') !== 0 &&
                         strpos($trimmedLine, 'Backtrace:') !== 0) {
                    // Check if this might be a continuation of the previous frame
                    // or if it's a line with additional information
                    if (!empty($stackTrace)) {
                        // If the line contains PHP code-like content, it's likely part of the stack trace
                        if (strpos($trimmedLine, '->') !== false ||
                            strpos($trimmedLine, '::') !== false ||
                            strpos($trimmedLine, '()') !== false ||
                            strpos($trimmedLine, '{main}') !== false) {
                            $stackTrace[] = $trimmedLine;
                        }
                        // If the line starts with a character that could indicate a continuation
                        else if (strpos($trimmedLine, '...') === 0 ||
                                 strpos($trimmedLine, '   ') === 0) {
                            $stackTrace[] = $trimmedLine;
                        }
                    }
                }
            }

            // If we couldn't extract any stack trace lines, try a more aggressive approach
            if (count($stackTrace) <= 1) { // Only the header line
                preg_match_all('/#\d+\s+[^#\n]+/', $stackTraceText, $matches);
                if (!empty($matches[0])) {
                    // Add the header line first
                    $stackTrace = [trim($stackLines[0])];
                    // Then add all matched frames
                    foreach ($matches[0] as $match) {
                        $stackTrace[] = trim($match);
                    }
                }
            }
        }

        return $stackTrace;
    }

    /**
     * Format a timestamp into a human-readable time ago string
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted time string
     */
    private function formatTimeAgo($timestamp)
    {
        $current_time = current_time('U');
        $time_diff = $current_time - $timestamp;

        // If the log is from the future (server time issues), show it as 'just now'
        if ($time_diff < 0) {
            return 'Just Now';
        }

        // Use WordPress's human_time_diff function but with some improvements
        if ($time_diff < 60) {
            return 'Just Now';
        } elseif ($time_diff < 3600) {
            $mins = round($time_diff / 60);
            return $mins . ' ' . _n('minute', 'minutes', $mins, 'debug-log-config-tool') . ' ago';
        } elseif ($time_diff < 86400) {
            $hours = round($time_diff / 3600);
            return $hours . ' ' . _n('hour', 'hours', $hours, 'debug-log-config-tool') . ' ago';
        } elseif ($time_diff < 604800) {
            $days = round($time_diff / 86400);
            return $days . ' ' . _n('day', 'days', $days, 'debug-log-config-tool') . ' ago';
        } else {
            // For older logs, show the actual date and time
            return date('M j, Y g:i a', $timestamp);
        }
    }

    public function clearDebugLog()
    {
        Helper::verifyRequest();

        if (file_exists($this->logFilePath)) {
            $open = fopen($this->logFilePath, "r+");

            if (!$open) {
                $msg = 'Could not open file!';
            } else {
                file_put_contents($this->logFilePath, "");
                $msg = 'Log cleared';
            }
        } else {
            $msg = 'No log file yet available!';
        }

        wp_send_json_success($msg);
    }

    public function clearQueryLog()
    {
        Helper::verifyRequest();

        update_option('dlct_db_query_log', '',false);

        $msg = 'clear query log';


        wp_send_json_success($msg);
    }

    public function gmt_to_local_timestamp($gmt_timestamp)
    {
        $iso_date = strtotime('Y-m-d H:i:s', $gmt_timestamp);
        return get_date_from_gmt($iso_date, 'Y-m-d h:i a');
    }

    /**
     * @param mixed $logFilePath
     */
    public function setLogFilePath($logFilePath)
    {
        $this->logFilePath = $logFilePath;
    }

    private function getRandomPath()
    {
        if (get_option('dlct_debug_file_path_generated') == 'yes') {
            $debugPath = get_option('dlct_debug_file_path');
        } else {
            $randomString = uniqid();
            $debugPath = apply_filters('dlct_debug_file_path', ABSPATH . "wp-content/debug-" . $randomString . ".log");
            update_option('dlct_ddebug_file_path', $debugPath, false);
            update_option('dlct_debug_file_path_generated', 'yes', false);
        }
        return $debugPath;
    }

    public function maybeCopyLogFromDefaultLogFile()
    {
        if (get_option('dlct_debug_file_path_generated') != 'yes') {
            return; // If the debug file path is not generated, exit the function
        }

        if (!get_option('dlct_log_file_copied')) {
            $currentLogPath = get_option('dlct_debug_file_path');
            $defaultLogPath = apply_filters('wp_dlct_default_log_file_path', WP_CONTENT_DIR . '/debug.log');

            $content = file_get_contents($defaultLogPath);
            file_put_contents($currentLogPath, $content);
            file_put_contents($defaultLogPath, 'Content Moved');

            update_option('dlct_log_file_copied', true);
        }
    }

    public function getQueryLogs($isSaveQueryOn)
    {
        if(!$isSaveQueryOn){
            return  [];
        }
        global $wpdb;
        $allQueries = get_option('dlct_db_query_log');
        if(!is_array($allQueries) || empty($allQueries)){
            return [];
        }
        $queryLogs = [];
        foreach ($allQueries as $query) {
            $callers = [];
            if (isset($query[0], $query[1], $query[2])) {
                $sql = $query[0];
                $executionTime = $query[1];
                $stack = $query[2];
            } else {
                continue;
            }
            $callers = array_reverse(explode(',', $stack));
            $callers = array_map('trim', $callers);
            $caller = reset($callers);
            $sql = trim($sql);

            $row = [
                'caller'         => $caller,
                'sql'            => $sql,
                'execution_time' => $executionTime,
                'stack'          => $callers,
            ];
            $queryLogs[] = $row; // Store the row in the $rows array
        }
        return array_reverse($queryLogs);
    }



    public static function maybeCacheQueries()
    {
        try{
            global $wpdb;

            $currentQueries = $wpdb->queries ?? [];
            if(empty($currentQueries)){
                return;
            }
            $allQueries = get_option('dlct_db_query_log', array());
            if(!is_array($allQueries)){
                $allQueries = array();
                update_option('dlct_db_query_log', array());
            }
            $allQueries = array_merge($allQueries, $currentQueries,[]);

            $allQueries = array_slice($allQueries, -50);

            update_option('dlct_db_query_log', $allQueries);
        } catch (\Exception $e){

        }


    }

    public function setRandomLogPath()
    {
        $debugPath = '';
        $generatedDebugPath = get_option('dlct_debug_file_path');
        if (get_option('dlct_debug_file_path_generated') === 'yes' && file_exists($generatedDebugPath)) {
            $debugPath = get_option('dlct_debug_file_path');
        } else {
            $randomString = uniqid();
            $debugPath = apply_filters('dlct_debug_file_path', ABSPATH . "wp-content/debug-" . $randomString . ".log");
            update_option('dlct_debug_file_path', $debugPath, false);
            update_option('dlct_debug_file_path_generated', 'yes', false);
            update_option('dlct_log_file_copied',false,false);
            if (!is_file($debugPath)) {
                file_put_contents($debugPath, '');
            }
            ConfigController::getInstance()->update('WP_DEBUG_LOG', "'" . $debugPath . "'");
            (new \DebugLogConfigTool\Controllers\LogController())->maybeCopyLogFromDefaultLogFile();
        }
        return $debugPath;
    }

    /**
     * Generate test logs of different types for demonstration purposes
     */
    public function generateTestLogs()
    {
        Helper::verifyRequest();

        if(empty($this->logFilePath)){
            $this->logFilePath = $this->setRandomLogPath();
        }

        try {
            // Make sure debug logging is enabled
            $configController = \DebugLogConfigTool\Controllers\ConfigController::getInstance();
            $isDebugEnabled = $configController->getValue('WP_DEBUG');
            $isDebugLogEnabled = $configController->getValue('WP_DEBUG_LOG');
            $isDebugBacktraceEnabled = $configController->getValue('WP_DEBUG_BACKTRACE');

            if (!$isDebugEnabled || !$isDebugLogEnabled) {
                // Temporarily enable debug logging
                $configController->update('WP_DEBUG', 'true');
                $configController->update('WP_DEBUG_LOG', 'true');
            }

            // Ensure backtrace is enabled for better test logs
            if (!$isDebugBacktraceEnabled) {
                $configController->update('WP_DEBUG_BACKTRACE', 'true');
            }

            // Generate different types of log entries
            $timestamp = current_time('mysql');

            // Simple format test (should be easier to parse)
            $this->writeTestLog($timestamp, 'Simple test log entry for debugging');

            // Standard WordPress format test
            $this->writeTestLog($timestamp, '[' . date('Y-m-d H:i:s') . ' UTC] Simple WordPress format test log');

            // Notice with stack trace
            $noticeError = 'PHP Notice: Undefined variable: test_var in ' . ABSPATH . 'wp-content/plugins/test-plugin/test-file.php on line 42' . PHP_EOL;
            $noticeError .= 'Stack trace:' . PHP_EOL;
            $noticeError .= '#0 ' . ABSPATH . 'wp-content/plugins/test-plugin/includes/functions.php(25): test_plugin_process_data()' . PHP_EOL;
            $noticeError .= '#1 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): test_plugin_init()' . PHP_EOL;
            $noticeError .= '#2 ' . ABSPATH . 'wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters(NULL, Array)' . PHP_EOL;
            $noticeError .= '#3 ' . ABSPATH . 'wp-includes/plugin.php(517): WP_Hook->do_action(Array)' . PHP_EOL;
            $noticeError .= '#4 ' . ABSPATH . 'wp-settings.php(617): do_action(\'init\')' . PHP_EOL;
            $this->writeTestLog($timestamp, $noticeError);

            // Warning with stack trace
            $warningError = 'PHP Warning: Invalid argument supplied for foreach() in ' . ABSPATH . 'wp-content/plugins/test-plugin/test-file.php on line 53' . PHP_EOL;
            $warningError .= 'Stack trace:' . PHP_EOL;
            $warningError .= '#0 ' . ABSPATH . 'wp-content/plugins/test-plugin/includes/class-data-processor.php(78): TestPlugin\\Core->process_items(NULL)' . PHP_EOL;
            $warningError .= '#1 ' . ABSPATH . 'wp-content/plugins/test-plugin/test-plugin.php(156): TestPlugin\\DataProcessor->run()' . PHP_EOL;
            $warningError .= '#2 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): test_plugin_process_request()' . PHP_EOL;
            $warningError .= '#3 ' . ABSPATH . 'wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters(NULL, Array)' . PHP_EOL;
            $warningError .= '#4 ' . ABSPATH . 'wp-includes/plugin.php(517): WP_Hook->do_action(Array)' . PHP_EOL;
            $this->writeTestLog($timestamp, $warningError);

            // Deprecated with stack trace
            $deprecatedError = 'PHP Deprecated: Function create_function() is deprecated in ' . ABSPATH . 'wp-content/plugins/legacy-plugin/old-file.php on line 27' . PHP_EOL;
            $deprecatedError .= 'Stack trace:' . PHP_EOL;
            $deprecatedError .= '#0 ' . ABSPATH . 'wp-content/plugins/legacy-plugin/includes/functions.php(45): legacy_create_callback()' . PHP_EOL;
            $deprecatedError .= '#1 ' . ABSPATH . 'wp-content/plugins/legacy-plugin/legacy-plugin.php(89): legacy_init_hooks()' . PHP_EOL;
            $deprecatedError .= '#2 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): LegacyPlugin->initialize()' . PHP_EOL;
            $deprecatedError .= '#3 ' . ABSPATH . 'wp-includes/plugin.php(517): WP_Hook->do_action(Array)' . PHP_EOL;
            $this->writeTestLog($timestamp, $deprecatedError);

            // Parse error with stack trace
            $parseError = 'PHP Parse error: syntax error, unexpected \'}\'  in ' . ABSPATH . 'wp-content/plugins/broken-plugin/broken-file.php on line 65' . PHP_EOL;
            $parseError .= 'Stack trace:' . PHP_EOL;
            $parseError .= '#0 ' . ABSPATH . 'wp-content/plugins/broken-plugin/broken-plugin.php(25): include()' . PHP_EOL;
            $parseError .= '#1 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): BrokenPlugin->initialize()' . PHP_EOL;
            $parseError .= '#2 ' . ABSPATH . 'wp-includes/plugin.php(517): WP_Hook->do_action(Array)' . PHP_EOL;
            $this->writeTestLog($timestamp, $parseError);

            // Fatal error with detailed stack trace
            $fatalError = 'PHP Fatal error: Uncaught Error: Call to undefined function nonexistent_function() in ' . ABSPATH . 'wp-content/plugins/example-plugin/example.php:78' . PHP_EOL;
            $fatalError .= 'Stack trace:' . PHP_EOL;
            $fatalError .= '#0 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): example_plugin_function()' . PHP_EOL;
            $fatalError .= '#1 ' . ABSPATH . 'wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters()' . PHP_EOL;
            $fatalError .= '#2 ' . ABSPATH . 'wp-includes/plugin.php(517): WP_Hook->do_action()' . PHP_EOL;
            $fatalError .= '#3 ' . ABSPATH . 'wp-settings.php(617): do_action(\'init\')' . PHP_EOL;
            $fatalError .= '#4 ' . ABSPATH . 'wp-config.php(96): require_once(\'' . ABSPATH . 'wp-settings.php\')' . PHP_EOL;
            $fatalError .= '#5 ' . ABSPATH . 'wp-load.php(50): require_once(\'' . ABSPATH . 'wp-config.php\')' . PHP_EOL;
            $fatalError .= '#6 ' . ABSPATH . 'wp-blog-header.php(13): require_once(\'' . ABSPATH . 'wp-load.php\')' . PHP_EOL;
            $fatalError .= '#7 ' . ABSPATH . 'index.php(17): require(\'' . ABSPATH . 'wp-blog-header.php\')' . PHP_EOL;
            $fatalError .= '#8 {main}' . PHP_EOL;
            $fatalError .= '  thrown in ' . ABSPATH . 'wp-content/plugins/example-plugin/example.php on line 78';
            $this->writeTestLog($timestamp, $fatalError);

            // Database error with backtrace
            $dbError = 'WordPress database error Table \'wp_options\' doesn\'t exist for query SELECT option_name, option_value FROM wp_options' . PHP_EOL;
            $dbError .= 'Stack trace:' . PHP_EOL;
            $dbError .= '#0 ' . ABSPATH . 'wp-includes/wp-db.php(2187): wpdb->query()' . PHP_EOL;
            $dbError .= '#1 ' . ABSPATH . 'wp-includes/option.php(118): wpdb->get_results()' . PHP_EOL;
            $dbError .= '#2 ' . ABSPATH . 'wp-includes/option.php(54): get_alloptions()' . PHP_EOL;
            $dbError .= '#3 ' . ABSPATH . 'wp-content/plugins/debug-log-config-tool/app/Controllers/ConfigController.php(156): get_option()' . PHP_EOL;
            $this->writeTestLog($timestamp, $dbError);

            // Custom backtrace example with variable dump
            $backtraceExample = $this->generateBacktraceExample();
            $this->writeTestLog($timestamp, '[DEBUG] Custom backtrace example: Testing a function with detailed backtrace' . "\n" . $backtraceExample);

            // AJAX error example
            $ajaxError = 'PHP Notice: Undefined index: action in ' . ABSPATH . 'wp-admin/admin-ajax.php on line 135' . PHP_EOL;
            $ajaxError .= 'Stack trace:' . PHP_EOL;
            $ajaxError .= '#0 ' . ABSPATH . 'wp-admin/admin-ajax.php(135): wp_ajax_nopriv_()' . PHP_EOL;
            $ajaxError .= '#1 {main}' . PHP_EOL;
            $this->writeTestLog($timestamp, $ajaxError);

            // REST API error
            $restError = 'PHP Warning: Cannot modify header information - headers already sent in ' . ABSPATH . 'wp-includes/rest-api.php on line 427' . PHP_EOL;
            $restError .= 'Stack trace:' . PHP_EOL;
            $restError .= '#0 ' . ABSPATH . 'wp-includes/rest-api/class-wp-rest-server.php(1241): header()' . PHP_EOL;
            $restError .= '#1 ' . ABSPATH . 'wp-includes/rest-api/class-wp-rest-server.php(1241): WP_REST_Server->send_header()' . PHP_EOL;
            $restError .= '#2 ' . ABSPATH . 'wp-includes/rest-api/class-wp-rest-server.php(3812): WP_REST_Server->serve_request()' . PHP_EOL;
            $restError .= '#3 ' . ABSPATH . 'wp-includes/rest-api.php(427): rest_api_loaded()' . PHP_EOL;
            $restError .= '#4 ' . ABSPATH . 'wp-includes/class-wp-hook.php(324): rest_api_init()' . PHP_EOL;
            $this->writeTestLog($timestamp, $restError);

            wp_send_json_success([
                'message' => 'Test logs generated successfully with stack traces',
                'success' => true,
                'log_path' => $this->logFilePath,
                'file_exists' => file_exists($this->logFilePath),
                'file_size' => filesize($this->logFilePath)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'success' => false
            ]);
        }
    }

    /**
     * Helper method to write a test log entry
     *
     * @param string $timestamp The timestamp for the log entry
     * @param string $message The log message
     */
    private function writeTestLog($timestamp, $message)
    {
        $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);
    }

    /**
     * Generate a sample backtrace for demonstration purposes
     *
     * @return string Formatted backtrace string
     */
    private function generateBacktraceExample()
    {
        // Simulate a function call stack
        $backtrace = "Stack trace:\n";
        $backtrace .= "#0 " . ABSPATH . "wp-content/plugins/example-plugin/includes/class-example.php(123): ExamplePlugin\\Core\\API->process_request(Array)\n";
        $backtrace .= "#1 " . ABSPATH . "wp-content/plugins/example-plugin/includes/class-api.php(45): ExamplePlugin\\Core->handle_endpoint('users/profile')\n";
        $backtrace .= "#2 " . ABSPATH . "wp-includes/class-wp-hook.php(324): ExamplePlugin\\API->register_routes()\n";
        $backtrace .= "#3 " . ABSPATH . "wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters(NULL, Array)\n";
        $backtrace .= "#4 " . ABSPATH . "wp-includes/plugin.php(517): WP_Hook->do_action(Array)\n";
        $backtrace .= "#5 " . ABSPATH . "wp-includes/rest-api.php(458): do_action('rest_api_init')\n";
        $backtrace .= "#6 " . ABSPATH . "wp-includes/rest-api.php(368): rest_api_loaded()\n";
        $backtrace .= "#7 " . ABSPATH . "wp-includes/class-wp-hook.php(324): rest_api_init('1')\n";
        $backtrace .= "#8 " . ABSPATH . "wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters(NULL, Array)\n";
        $backtrace .= "#9 " . ABSPATH . "wp-includes/plugin.php(517): WP_Hook->do_action(Array)\n";
        $backtrace .= "#10 " . ABSPATH . "wp-includes/load.php(1165): do_action('init')\n";
        $backtrace .= "#11 " . ABSPATH . "wp-settings.php(512): wp_loaded()\n";
        $backtrace .= "#12 " . ABSPATH . "wp-config.php(96): require_once('" . ABSPATH . "wp-settings.php')\n";
        $backtrace .= "#13 " . ABSPATH . "wp-load.php(50): require_once('" . ABSPATH . "wp-config.php')\n";
        $backtrace .= "#14 " . ABSPATH . "index.php(17): require('" . ABSPATH . "wp-load.php')\n";
        $backtrace .= "#15 {main}\n";

        // Add some variable dump information that might be useful in debugging
        $backtrace .= "\nVariable dump:\n";
        $backtrace .= "\$_REQUEST = " . json_encode(['page_id' => 123, 'action' => 'view', 'user_id' => 456]) . "\n";
        $backtrace .= "\$current_user_id = 456\n";
        $backtrace .= "\$api_response = " . json_encode(['status' => 'error', 'code' => 403, 'message' => 'Permission denied']) . "\n";

        return $backtrace;
    }
}
