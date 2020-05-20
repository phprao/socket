<?php
/**
 * 客户端
 *
 * 非阻塞式 + 同步 + IO多路复用器epoll
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/socketClientBase.php';

class socketClientEpoll extends socketClientBase
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

    public function eventOnRead()
    {
        return function (){
            echo 'callback'.PHP_EOL;
            $buf = $this->read();
            if ($buf) {
                echo 'fd: ' . (int)$this->socket . PHP_EOL;
                print_r($buf . PHP_EOL);
            }
        };
    }
}

/*************************************************************************************/

try {
    // 还有问题 todo

    $clients      = [];
    $dests        = [
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
    ];

    $events = [];

    $eventBase   = new EventBase();

    echo '当前系统上Libevent支持的IO多路复用器：' . PHP_EOL;
    print_r(Event::getSupportedMethods());
    echo '正在使用的是：' . $eventBase->getMethod() . PHP_EOL;

    foreach($dests as $k => $v){
        $socketClient = (new socketClientEpoll())->connectTo($v[0], $v[1]);
        $socketClient->send('name-'.$k);
        $event = new Event($eventBase, $socketClient->socket, Event::READ | Event::PERSIST, function (){
            echo 'callback'.PHP_EOL;
        });
        $event->add();

        $events[(int)$socketClient->socket] = $event;
    }

    $eventBase->loop();
} catch (\Exception $e) {
    die($e->getMessage());
}