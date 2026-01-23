<?php

class AutoUpdater_Helper_Cookie
{
    /**
     * Creates cookies for the account. This is function similar to wordpress wp_set_auth_cookie
     * https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/
     * The most important difference are last two lines
     *
     * @param int    $user_id  User ID
     * @param bool   $remember Whether to remember the user
     * @param string $token    Optional. User's session token to use for this cookie
     * 
     * @return string logged_in cookie value
     */
    public static function createLoggedInCookie($user_id, $remember = false, $token = '') 
    {
        if ($remember) {
            $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember);

            $expire = $expiration + (12 * HOUR_IN_SECONDS);
        } else {
            $expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember);
            $expire     = 0;
        }

        if ('' === $token) {
            $manager = WP_Session_Tokens::get_instance($user_id);
            $token   = $manager->create($expiration);
        }

        $logged_in_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);

        if (!apply_filters('send_auth_cookies', true, $expire, $expiration, $user_id, $scheme, $token)) {
            return '';
        }

        //this code is a way to bypass cookie setup - require to generate nonce
        $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;

        return LOGGED_IN_COOKIE . '=' . $logged_in_cookie;
    }
}
