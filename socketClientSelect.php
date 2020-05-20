<?php
/**
 * 客户端
 *
 * 非阻塞式 + 同步 + IO多路复用器select
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/socketClientBase.php';

class socketClientSelect extends socketClientBase
{
    public function __construct($port = null)
    {
        parent::__construct($port);
    }

    public function connectTo($host, $port)
    {
        $result = socket_connect($this->socket, $host, $port);
        if ($result == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        // 设置非阻塞
        $this->setNonBlocking();

        return $this;
    }
}

/*************************************************************************************/

try {

    $clients      = [];
    $readFdsInit  = [];
    $readFds      = [];
    $writeFds     = null;
    $exceptionFds = null;
    $dests        = [
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
    ];

    $setting = [
        // 允许等待连接的请求数
        'backlog' => 128,

        /**
         * 每次select阻塞等待多少秒，获取在这段时间内的状态变化；
         *
         * 0表示不等待，获取这个时刻的状态变化；
         * NULL表示阻塞等待直到有返回。
         */
        'timeout' => null,
    ];

    foreach ($dests as $k => $v) {
        $socketClient = (new socketClientSelect())->connectTo($v[0], $v[1]);
        $len = $socketClient->send('name');
        var_dump($len);
        $clients[(int)$socketClient->socket] = $socketClient;
        $readFdsInit[(int)$socketClient->socket] = $socketClient->socket;
    }

    while (!empty($clients)) {
        $readFds = $readFdsInit;
        $ret     = socket_select($readFds, $writeFds, $exceptionFds, $setting['timeout']);
        if ($ret < 1 || empty($readFds)) {
            continue;
        }

        foreach ($readFds as $fd) {
            $obj = $clients[(int)$fd];
            $buf = $obj->read();
            if ($buf) {
                echo 'fd: ' . (int)$fd . PHP_EOL;
                print_r($buf . PHP_EOL);
            }

            unset($clients[(int)$fd]);
            unset($readFdsInit[(int)$fd]);
        }
    }

} catch (\Exception $e) {
    die($e->getMessage());
}