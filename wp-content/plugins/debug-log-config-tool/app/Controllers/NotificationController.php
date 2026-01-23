<?php

namespace DebugLogConfigTool\Controllers;

class NotificationController
{
    public $notificationEmail = 'dlct_log_notification_email';
        public $notificationStatus = 'dlct_log_notification_email_schedule';
    
    public function boot()
    {
        add_action('dlct_daily_email_check', array($this, 'maybeSendEmail'));
    }
    
    public function scheduleCron()
    {
        if (!wp_next_scheduled('dlct_daily_email_check')) {
            wp_schedule_event(time(), 'daily', 'dlct_daily_email_check');
        }
    }
    
    public function deactivate()
    {
        wp_clear_scheduled_hook('dlct_daily_email_check');
    }
    
    public function maybeSendEmail()
    {
        $notification_status = get_option($this->notificationStatus) == 'true' || get_option($this->notificationStatus) == 'yes';
        if (!$notification_status == 'yes') {
            return;
        }
        $logData = (new LogController())->loadLogs();
        if (!empty($logData['logs'])) {
            $this->sendEmail($logData['logs']);
        }
    }
    
    public function sendEmail($lastLog)
    {
        $to = get_option($this->notificationEmail) ? get_option($this->notificationEmail) : get_option('admin_email');
        if (empty($to)) {
            return;
        }
        $subject = get_bloginfo('name') . ': Notification from Debug Log Config Plugin';
        $body = 'A new debug log has been recorded in your site <br>';
        $table = $this->getTableStyle();
        $table .= '<table class="dlct-log-table">';
        foreach ($lastLog as $row) {
            $table .= '<tr>';
            foreach ($row as $cell) {
                $table .= '<td>' . $cell . '</td>';
            }
            $table .= '</tr>';
        }
        $table .= '</table>';
        $body .= $table;
        $body = apply_filters('dlct_email_body', $body);
        
        $headers = array('Content-Type: text/html; charset=UTF-8', 'From: '.get_bloginfo('name').' <support@example.com>');
        
        wp_mail($to, $subject, $body, $headers);
    }
    
    public function getNotificationEmail()
    {
        Helper::verifyRequest();
        $notification_email = get_option($this->notificationEmail);
        if (!$notification_email) {
            $notification_email = get_option('admin_email');
        }
        $notification_status =  get_option($this->notificationStatus) == 'yes';
        wp_send_json_success([
            'email'  => $notification_email,
            'status' => $notification_status,
        ]);
    }
    
    public function updateNotificationEmail()
    {
        Helper::verifyRequest();
        $notification_email = sanitize_text_field($_REQUEST['email']);
        $notification_status = sanitize_text_field($_REQUEST['status']) == 'true' ? 'yes' : 'no';
        if (!$notification_email) {
            $notification_email = get_option('admin_email');
        }
        update_option($this->notificationEmail, $notification_email, false);
        update_option($this->notificationStatus, $notification_status, false);
        wp_send_json_success([
            'message' => 'Notification Settings Updated!',
            'success' => true
        ]);
    }
    
    private function getTableStyle()
    {
        return '<style>
          .dlct-log-table {
            border: solid 2px #DDEEEE;
            border-collapse: collapse;
            border-spacing: 0;
            font: normal 14px Roboto, sans-serif;
          }
        
          .dlct-log-table thead th {
            background-color: #DDEFEF;
            border: solid 1px #DDEEEE;
            color: #336B6B;
            padding: 10px;
            text-align: left;
            text-shadow: 1px 1px 1px #fff;
          }
        
          .dlct-log-table tbody td {
            border: solid 1px #DDEEEE;
            color: #333;
            padding: 10px;
            text-shadow: 1px 1px 1px #fff;
          }
        </style>';
    }
}
