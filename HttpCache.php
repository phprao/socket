<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/1 0001 20:35
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

class HttpCache
{
    public static $codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    );

    /**
     * @var HttpCache
     */
    public static $instance             = null;
    public static $header               = array();
    public static $sessionPath          = '';
    public static $sessionName          = '';
    public static $sessionGcProbability = 1;
    public static $sessionGcDivisor     = 1000;
    public static $sessionGcMaxLifeTime = 1440;
    public $sessionStarted = false;
    public $sessionFile = '';

    public static function init()
    {
        if (!self::$sessionName) {
            self::$sessionName = ini_get('session.name');
        }

        if (!self::$sessionPath) {
            self::$sessionPath = @session_save_path();
        }

        if (!self::$sessionPath || strpos(self::$sessionPath, 'tcp://') === 0) {
            self::$sessionPath = sys_get_temp_dir();
        }

        if ($gc_probability = ini_get('session.gc_probability')) {
            self::$sessionGcProbability = $gc_probability;
        }

        if ($gc_divisor = ini_get('session.gc_divisor')) {
            self::$sessionGcDivisor = $gc_divisor;
        }

        if ($gc_max_life_time = ini_get('session.gc_maxlifetime')) {
            self::$sessionGcMaxLifeTime = $gc_max_life_time;
        }
    }
}