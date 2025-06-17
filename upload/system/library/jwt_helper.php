<?php
/**
 * JWT Helper wrapper around firebase/php-jwt v5.x for PHP 5.6.
 * Usage:
 *   JwtHelper::encode($payload, $secret);
 *   JwtHelper::decode($jwt, $secret);
 *
 * Make sure firebase/php-jwt is installed in your OpenCart system folder (via composer) OR include the library manually.
 */
class JwtHelper {
    public static function encode($payload, $secret) {
        if (!class_exists('\Firebase\JWT\JWT')) {
            require_once DIR_SYSTEM . 'library/vendor/autoload.php';
        }
        return \Firebase\JWT\JWT::encode($payload, $secret);
    }

    public static function decode($jwt, $secret) {
        if (!class_exists('\Firebase\JWT\JWT')) {
            require_once DIR_SYSTEM . 'library/vendor/autoload.php';
        }
        try {
            return \Firebase\JWT\JWT::decode($jwt, $secret, array('HS256'));
        } catch (Exception $e) {
            return false;
        }
    }
}
