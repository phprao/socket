<?php
/**
 * 客户端
 *
 * 非阻塞式 + 同步 + IO多路复用器epoll
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/../lib/socketClientBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class socketClientEpoll extends socketClientBase
{
    public $event;

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

    public function eventOnRead($fd, $what, $arg)
    {
        $buf = socketHelper::read($fd, $this->len);
        if ($buf) {
            echo 'fd: ' . (int)$fd . PHP_EOL;
            print_r($buf . PHP_EOL);
        }

        /**
         * !!! 必须调用 EPOLL_CTL_DEL 来将当前fd(比如7fd)从"fd池"中移除。
         */
        $this->event->free();
        /**
         * 主动关闭连接，否则只能等到manager对象释放继而此对象被释放继而关闭连接。
         */
        $this->__destruct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }
}

class epollManager
{
    public $dests = [];
    public $eventBase;

    public function __construct(array $dests)
    {
        $this->dests = $dests;
        $this->action();
    }

    public function action()
    {
        $this->eventBase = new EventBase();

        echo '当前系统上Libevent支持的IO多路复用器：' . PHP_EOL;
        print_r(Event::getSupportedMethods());
        echo '正在使用的是：' . $this->eventBase->getMethod() . PHP_EOL;

        foreach ($this->dests as $k => $v) {
            $socketClient = (new socketClientEpoll())->connectTo($v[0], $v[1]);
            socketHelper::send($socketClient->socket, 'name-' . $k);
            $event = new Event(
                $this->eventBase,
                $socketClient->socket,
                Event::READ | Event::PERSIST, // 虽然作为客户端，但是PERSIST还是要的，有可能对方需要分多次来发送数据
                [$socketClient, 'eventOnRead']
            );

            $event->add();
            $socketClient->event = $event;
        }

        /**
         * 当"fd池"为空，就会停止loop
         */
        $this->eventBase->loop();
    }
}

/*************************************************************************************/

try {

    $dests = [
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
    ];

    new epollManager($dests);

} catch (\Exception $e) {
    die($e->getMessage());
}