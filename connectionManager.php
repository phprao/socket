<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/20 0020 18:10
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe: 连接管理
 * +----------------------------------------------------------
 */

class connectionManager
{
    private static $connetion = [];

    public static function add($fd, $connection)
    {
        self::$connetion[(int)$fd] = $connection;
    }

    public static function del($fd)
    {
        unset(self::$connetion[(int)$fd]);
    }

    public static function get($fd)
    {
        return self::$connetion[(int)$fd];
    }

    public static function getAll()
    {
        return self::$connetion;
    }
}