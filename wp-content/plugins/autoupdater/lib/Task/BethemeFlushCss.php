<?php

defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_BethemeFlushCss extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        AutoUpdater_Loader::loadClass('Helper_Cookie');
        require_once ABSPATH . WPINC . '/pluggable.php';
        AutoUpdater_Authentication::getInstance()->logInAsAdmin();

        $uid  = get_current_user_id();

        $theme = wp_get_theme();
        if ( strtolower($theme->name) != 'betheme' && strtolower($theme->parent_theme) != 'betheme' ) {
            return array(
                'success' => true,
                'message' => 'Betheme is not active, skipping flushing CSS.',
            );
        }

        $cookie = AutoUpdater_Helper_Cookie::createLoggedInCookie($uid, true);

        $request = new AutoUpdater_Request(
            'POST',
            admin_url('admin-ajax.php'),
            array(),
            http_build_query(array(
                'mfn-builder-nonce' => wp_create_nonce('mfn-builder-nonce'),
                'action' => 'mfn_regenerate_css',
            )),
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Cookie' => $cookie,
            )
        );

        $response = $request->send();

        if ($response->code == 200) {
            return array(
                'success' => true,
                'message' => 'Betheme CSS cache flushed successfully.',
            );
        } 

        $failure_message = 'Failed to flush Betheme CSS cache. HTTP Code: ' . $response->code;
    
        AutoUpdater_Log::error($failure_message . ' Request body: ' . print_r($response->body, true));

        return array(
            'success' => false,
            'message' => $failure_message,
        );
    }
}
