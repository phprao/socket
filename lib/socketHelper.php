<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/21 0021 12:05
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

class socketHelper
{
    public static function getClientInfo($socket)
    {
        // 获取客户端信息
        socket_getpeername($socket, $addr, $port);
        return ['ip'=>$addr, 'port'=>$port];
    }

    public static function read($socket, $len = 8129)
    {
        $getMsg = '';

        do {
            $out = socket_read($socket, $len);
            if ($out === false) {
                return false;
            }
            $getMsg .= $out;
            if (strlen($out) < $len) {
                break;
            }
        } while (true);

        return $getMsg;
    }

    public static function send($socket, $data)
    {
        if (is_resource($socket)) {
            if (@socket_write($socket, $data, strlen($data)) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function httpSend($socket, $data)
    {
        $data = self::formatHttp($data);
        if (is_resource($socket)) {
            if (@socket_write($socket, $data, strlen($data)) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function formatHttp($data)
    {
        /**
         * HTTP/1.1 200 OK
         * Date: Fri, 01 May 2020 12:00:57 GMT
         * Connection: close
         * X-Powered-By: PHP/7.2.27
         * Content-type: text/html; charset=UTF-8
         */
        $ret = "HTTP/1.1 200 OK\r\n";
        $ret .= "Date: " . gmdate("D, d M Y H:i:s", time()) . " GMT\r\n";
        $ret .= "Connection: close\r\n";
        $ret .= "Content-type: text/html; charset=UTF-8\r\n";
        $ret .= "Content-Length: " . strlen($data) . "\r\n\r\n";

        $ret .= $data;

        return $ret;
    }

    public static function close($socket){
        @socket_close($socket);
    }
}