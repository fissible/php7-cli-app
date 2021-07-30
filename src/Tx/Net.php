<?php declare(strict_types=1);

namespace PhpCli\Tx;

use PhpCli\Filesystem\File;
use PhpCli\Tx\Request;
use PhpCli\Tx\Response;

class Net
{
    /**
     * The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
     */
    public static int $connectTimeout;

    public static string $cookie;

    /**
     * Set true to follow any "Location: " header that the server sends as part of the HTTP header.
     */
    public static bool $followRedirects = true;

    /**
     * 
     */
    public static bool $freshConnection = false;

    /**
     * Setting to -1 allows inifinite redirects, and 0 refuses all redirects.
     */
    public static int $maxRedirects = 20;

    /**
     * Set true to return the transfer as a string of the return value of curl_exec() instead of outputting it directly.
     */
    public static bool $returnTransfer = true;

    /**
     * The maximum number of seconds to allow cURL functions to execute.
     */
    public static int $timeout;

    public static string $userAgent;

    // public function __construct() new Network?

    public static function delete(string $url, array $headers = [], $options = []): Response
    {
        return static::request('DELETE', $url, $headers, $options)->delete();
    }

    public static function get(string $url, array $query = [], array $headers = [], $options = []): Response
    {
        return static::request('GET', $url, $headers, $options)->get($query);
    }

    public static function patch(string $url, array $body = [], array $headers = [], array $files = [], $options = []): Response
    {
        return static::request('PATCH', $url, $headers, $options)->patch($body, $files);
    }

    public static function post(string $url, array $body = [], array $headers = [], array $files = [], $options = []): Response
    {
        return static::request('POST', $url, $headers, $options)->post($body, $files);
    }

    public static function put(string $url, array $body = [], array $headers = [], array $files = [], $options = []): Response
    {
        return static::request('PUT', $url, $headers, $options)->put($body, $files);
    }

    /**
     * Make a network request.
     * 
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return Request
     */
    public static function request(string $method = 'GET', string $url, array $headers = [], array $options = []): Request
    {
        $Request = new Request($url, $headers, $options, $method);

        if (isset(static::$connectTimeout)) {
            $Request->setOption(CURLOPT_CONNECTTIMEOUT, static::$connectTimeout);
        }
        
        if (isset(static::$cookie)) {
            $Request->setOption(CURLOPT_COOKIE, static::$cookie);
        }

        if (static::$freshConnection) {
            $Request->setOption(CURLOPT_FRESH_CONNECT, true);
            $Request->setOption(CURLOPT_FORBID_REUSE, true);
        }

        if (static::$returnTransfer) {
            $Request->setOption(CURLOPT_RETURNTRANSFER, true);
        }

        if (isset(static::$timeout)) {
            $Request->setOption(CURLOPT_TIMEOUT, static::$timeout);
        }

        if (isset(static::$userAgent)) {
            $Request->setOption(CURLOPT_USERAGENT, static::$userAgent);
        }

        if (static::$followRedirects) {
            $Request->setOption(CURLOPT_FOLLOWLOCATION, true);
        }

        $Request->setOption(CURLOPT_MAXREDIRS, static::$maxRedirects);

        return $Request;
    }
}