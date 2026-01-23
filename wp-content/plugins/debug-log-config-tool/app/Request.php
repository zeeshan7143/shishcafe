<?php
namespace DebugLogConfigTool;
class Request
{
    /**
     * Fetch the request URI.
     *
     * @return string
     */
    public static function ajaxRoute()
    {
        return isset($_REQUEST['route']) ? sanitize_text_field($_REQUEST['route']) : false;
    }
    
    /**
     * Fetch the request method.
     *
     * @return string
     */
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}
