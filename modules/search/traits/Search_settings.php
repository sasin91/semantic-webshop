<?php
/**
 * Read a setting from config/search.php (environment variables win).
 * Shared by controllers and models; loaded once per request.
 */
trait Search_settings {

    function _setting(string $key): mixed {
        static $settings = null;
        $settings ??= require APPPATH . 'config/search.php';

        if (!array_key_exists($key, $settings)) {
            throw new Exception("Unknown search setting: {$key}");
        }

        return $settings[$key];
    }
}
