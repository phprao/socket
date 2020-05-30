<?php
/**
 * 服务端
 *
 * 非阻塞式 + 同步 + IO多路复用器epoll
 *
 * Event的callback的参数是什么呢？ 参考官方手册 https://www.php.net/manual/zh/event.callbacks.php
 */

set_time_limit(0);

require_once __DIR__ . '/../lib/connectionManager.php';
require_once __DIR__ . '/../lib/socketServerBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class socketServerEpoll extends socketServerBase
{
    public $eventBase;

    public function __construct($host, $port)
    {
        parent::__construct($host, $port);
    }

    public function start()
    {
        $this->eventBase = new EventBase();

        echo '当前系统上Libevent支持的IO多路复用器：' . PHP_EOL;
        print_r(Event::getSupportedMethods());
        echo '正在使用的是：' . $this->eventBase->getMethod() . PHP_EOL;

        $event = new Event(
            $this->eventBase,
            $this->serverSocket,
            Event::READ | Event::PERSIST,
            [$this, 'callbackOnConnect']
        );
        $event->add();
        $this->eventBase->loop();
    }

    /**
     * @param $fd    mixed      发生事件的fd，此处就是监听fd，就是$this->serverSocket
     * @param $what  int        事件类型，此处为2
     * @param $arg   mixed      实例化Event的最后一个参数
     */
    public function callbackOnConnect($fd, $what, $arg)
    {
        //var_dump($fd); var_dump($this->serverSocket);

        $conn = socket_accept($this->serverSocket);
        if ($conn === false) {
            echo socket_strerror(socket_last_error()) . PHP_EOL;
        } else {
            $connection = new connection($conn, $this->eventBase, $this->events);
            connectionManager::add((int)$conn, $connection);
            $connection->event->add();
            if (isset($this->events['connect']) && $conn) {
                call_user_func($this->events['connect'], $this, $conn);
            }
        }
    }

    public function __destruct()
    {
        parent::__destruct();
        $allConnection = connectionManager::getAll();
        foreach ($allConnection as &$c) {
            $c = NULL;
        }
    }
}

class connection
{
    public $len = 8129;
    public $events = [];
    public $event;
    public $eventBase;
    public $fd;

    public function __construct($fd, $eventBase, $events)
    {
        socket_set_nonblock($fd);
        $this->eventBase = $eventBase;
        $this->fd        = $fd;
        $this->events    = $events;
        $this->addEvent();
    }

    public function addEvent()
    {
        // 为每个连接创建读事件
        $this->event = new Event(
            $this->eventBase,
            $this->fd,
            Event::READ | Event::PERSIST,
            [$this, 'callbackOnRead']
        );

        // ！！！如果放在此处的话，那么效果就是先 EPOLL_CTL_ADD 然后被 EPOLL_CTL_DEL，所以要放在外面
        // $this->event->add();
    }

    /**
     * @param $fd    mixed      发生事件的fd，此处就是连接fd，此fd与客户端通讯
     * @param $what  int        事件类型，此处为2 -> Event::READ
     * @param $arg   mixed      实例化Event的最后一个参数
     */
    public function callbackOnRead($fd, $what, $arg)
    {
        //var_dump($fd); var_dump($this->fd);

        $buf = socketHelper::read($fd, $this->len);
        if ($buf === false) {
            $this->__destruct();
        } elseif ($buf === '') {
            $this->__destruct();
        } else {
            if (isset($this->events['receive'])) {
                call_user_func($this->events['receive'], $this, $buf);
            }
        }
    }

    public function deleteConn()
    {
        @socket_shutdown($this->fd);
        @socket_close($this->fd);
        connectionManager::del($this->fd);

        /**
         * free() 包含了del()并释放了分配给当前Event的资源
         * del() 会将注册在应用程序的事件移除并发起系统调用 EPOLL_CTL_DEL
         *
         * !!! 这是必须的。
         */
        $this->event->free();
        if (isset($this->events['close'])) {
            call_user_func($this->events['close'], $this);
        }
    }

    public function __destruct()
    {
        $this->deleteConn();
    }
}

$socketServer = new socketServerEpoll('0.0.0.0', 8888);

$socketServer->on('connect', function ($socketServer, $fd) {
    echo 'connect in...' . (int)$fd . PHP_EOL;
});

$socketServer->on('receive', function ($connection, $data) {
    echo 'receive data : ' . $data . ' from ' . (int)$connection->fd . PHP_EOL;

    $re = socketHelper::httpSend($connection->fd, 'I am server...' . PHP_EOL);
    if ($re) {
        echo 'response to ' . (int)$connection->fd . PHP_EOL;
    } else {
        $connection->deleteConn();
    }

    // 模拟Http服务器
    $connection->deleteConn();
});

$socketServer->on('close', function ($connection) {
    echo 'client close...' . (int)$connection->fd . PHP_EOL;
});

// 启动服务器
$socketServer->start();