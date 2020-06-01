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
        if(is_int($fd)){
            $no = $fd;
        }else{
            $no = (int)$fd;
        }
        self::$connetion[$no] = $connection;

        return true;
    }

    public static function del($fd)
    {
        if(is_int($fd)){
            $no = $fd;
        }else{
            $no = (int)$fd;
        }
        unset(self::$connetion[$no]);

        return true;
    }

    public static function get($fd)
    {
        if(is_int($fd)){
            $no = $fd;
        }else{
            $no = (int)$fd;
        }
        return self::$connetion[$no];
    }

    public static function getAll()
    {
        return self::$connetion;
    }
}