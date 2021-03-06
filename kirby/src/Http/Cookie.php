<?php

namespace Kirby\Http;

use Kirby\Toolkit\Str;

/**
 * This class makes cookie handling easy
 *
 * @package   Kirby Http
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      http://getkirby.com
 * @copyright Bastian Allgeier
 * @license   MIT
 */
class Cookie
{

    /**
     * Key to use for cookie signing
     * @var string
     */
    public static $key = 'KirbyHttpCookieKey';

    /**
     * Set a new cookie
     *
     * <code>
     *
     * cookie::set('mycookie', 'hello', ['lifetime' => 60]);
     * // expires in 1 hour
     *
     * </code>
     *
     * @param  string  $key       The name of the cookie
     * @param  string  $value     The cookie content
     * @param  array   $options   Array of options:
     *                            lifetime, path, domain, secure, httpOnly
     * @return boolean            true: cookie was created,
     *                            false: cookie creation failed
     */
    public static function set(string $key, string $value, array $options = []): bool
    {
        // extract options
        $lifetime = $options['lifetime'] ?? 0;
        $path     = $options['path']     ?? '/';
        $domain   = $options['domain']   ?? null;
        $secure   = $options['secure']   ?? false;
        $httpOnly = $options['httpOnly'] ?? true;

        // add an HMAC signature of the value
        $value = static::hmac($value) . '+' . $value;

        // store that thing in the cookie global
        $_COOKIE[$key] = $value;

        // store the cookie
        return setcookie($key, $value, static::lifetime($lifetime), $path, $domain, $secure, $httpOnly);
    }

    /**
     * Calculates the lifetime for a cookie
     *
     * @param  int $minutes Number of minutes or timestamp
     * @return int
     */
    public static function lifetime(int $minutes): int
    {
        if ($minutes > 1000000000) {
            // absolute timestamp
            return $minutes;
        } elseif ($minutes > 0) {
            // minutes from now
            return time() + ($minutes * 60);
        } else {
            return 0;
        }
    }

    /**
     * Stores a cookie forever
     *
     * <code>
     *
     * cookie::forever('mycookie', 'hello');
     * // never expires
     *
     * </code>
     *
     * @param  string  $key       The name of the cookie
     * @param  string  $value     The cookie content
     * @param  array   $options   Array of options:
     *                            path, domain, secure, httpOnly
     * @return boolean            true: cookie was created,
     *                            false: cookie creation failed
     */
    public static function forever(string $key, string $value, array $options = []): bool
    {
        $options['lifetime'] = 253402214400; // 9999-12-31
        return static::set($key, $value, $options);
    }

    /**
     * Get a cookie value
     *
     * <code>
     *
     * cookie::get('mycookie', 'peter');
     * // sample output: 'hello' or if the cookie is not set 'peter'
     *
     * </code>
     *
     * @param  string|null  $key     The name of the cookie
     * @param  string|null  $default The default value, which should be returned
     *                               if the cookie has not been found
     * @return mixed                 The found value
     */
    public static function get(string $key = null, string $default = null)
    {
        if ($key === null) {
            return $_COOKIE;
        }
        $value = $_COOKIE[$key] ?? null;
        return empty($value) ? $default : static::parse($value);
    }

    /**
     * Checks if a cookie exists
     *
     * @param  string  $key
     * @return boolean
     */
    public static function exists(string $key): bool
    {
        return static::get($key) !== null;
    }

    /**
     * Creates a HMAC for the cookie value
     * Used as a cookie signature to prevent easy tampering with cookie data
     *
     * @param  string $value
     * @return string
     */
    protected static function hmac(string $value): string
    {
        return hash_hmac('sha1', $value, static::$key);
    }

    /**
     * Parses the hashed value from a cookie
     * and tries to extract the value
     *
     * @param  string $string
     * @return mixed
     */
    protected static function parse(string $string)
    {
        // if no hash-value separator is present, we can't parse the value
        if (strpos($string, '+') === false) {
            return null;
        }

        // extract hash and value
        $hash  = Str::before($string, '+');
        $value = Str::after($string, '+');

        // if the hash or the value is missing at all return null
        // $value can be an empty string, $hash can't be!
        if (!is_string($hash) || $hash === '' || !is_string($value)) {
            return null;
        }

        // compare the extracted hash with the hashed value
        // don't accept value if the hash is invalid
        if (hash_equals(static::hmac($value), $hash) !== true) {
            return null;
        }

        return $value;
    }

    /**
     * Remove a cookie
     *
     * <code>
     *
     * cookie::remove('mycookie');
     * // mycookie is now gone
     *
     * </code>
     *
     * @param  string  $key The name of the cookie
     * @return boolean      true: the cookie has been removed,
     *                      false: the cookie could not be removed
     */
    public static function remove(string $key): bool
    {
        if (isset($_COOKIE[$key])) {
            unset($_COOKIE[$key]);
            return setcookie($key, '', 1, '/') && setcookie($key, false);
        }

        return false;
    }
}
