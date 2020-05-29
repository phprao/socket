<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/21 0021 8:56
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */

class socketServerBase
{
    public $len = 8129;// 每次读取数据的字节数
    public $events = [];
    public $serverSocket;
    public $settings = [];
    public $clients = [];// 连接的客户端fd

    public function __construct($host, $port)
    {
        $this->initSettings();
        $this->createServer($host, $port);
    }

    public function createServer($host, $port)
    {
        // 1. 创建
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        } else {
            $this->serverSocket = $sock;
        }

        // 2. 绑定
        if (socket_bind($this->serverSocket, $host, $port) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        // 设置端口重用，否则每次重启要等待1-2分钟
        socket_get_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR);

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return true;
    }

    public function on($event, $callback)
    {
        $this->events[$event] = $callback;
    }

    public function set($settings)
    {
        $this->settings = $settings;
    }

    public function initSettings()
    {
        $this->settings = [
            // 允许等待连接的请求数
            'backlog' => 128,
            /**
             * 每次select阻塞等待多少秒，获取在这段时间内的状态变化；
             *
             * 0表示不等待，获取这个时刻的状态变化，CPU占用率将暴增，禁止使用；
             * NULL表示阻塞等待直到有返回，建议使用。
             */
            'timeout' => NULL,
        ];
    }

    public function deleteConn($socket)
    {
        @socket_shutdown($socket);
        @socket_close($socket);
        unset($this->clients[(int)$socket]);
        if (isset($this->events['close'])) {
            call_user_func($this->events['close'], $this, $socket);
        }
    }

    public function __destruct()
    {
        @socket_close($this->serverSocket);

    }
}