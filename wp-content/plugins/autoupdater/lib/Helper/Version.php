<?php
defined('AUTOUPDATER_LIB') or die;


/**
 * There are equivalents of those methods in au-queue
 * (lib/utils/version.ts).
 * Any change in these methods should be included in au-queue's equivalent.
 */
class AutoUpdater_Helper_Version
{
    public static function format($version, $length = 3, $parts_length = 3, $shorten = false)
    {
        // Match only sets of digits separated by a full stop
        $regex = '/^([0-9]+(?:\.[0-9]+)*)([^.]+.*)?$/i';
        $version_match = array();
        preg_match($regex, $version, $version_match);
        if (!$version_match || empty($version_match[1])) {
            return $version;
        }

        $parts = explode('.', $version_match[1]);
        $parts = array_map(function ($item) use ($length) {
            return str_pad($item, $length, '0', STR_PAD_LEFT);
        }, $parts);

        if ($shorten) {
            $parts = array_slice($parts, 0, $parts_length);
        } else {
            while (count($parts) < $parts_length) {
                array_push($parts, str_pad('', $length, '0'));
            }
        }

        // Remove all special characters from version suffix except for hyphen and underscore (-_)
        $suffix = (empty($version_match[2]) ? '' : preg_replace('/[^a-zA-Z0-9.\-_]/', '', $version_match[2]));

        return implode('.', $parts) . $suffix;
    }

    public static function filterHTML($string)
    {
        return utf8_encode(trim(wp_strip_all_tags(html_entity_decode($string, ENT_QUOTES, 'UTF-8'))));
    }

    public static function fix($version)
    {
        $version = strtolower(self::filterHTML($version));
        // Remove all special characters except for dot, hyphen and underscore (.-_)
        $version = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $version);
        $version = substr($version, 0, 20);

        if ($version[0] === 'v') {
            $version = substr($version, 1, 20);
        }

        if (!preg_match('/\d/', $version)) {
            $version = '0.0.0';
        }

        return $version;
    }

    public static function fixAndFormat($version)
    {
        return self::format(self::fix($version));
    }
}
