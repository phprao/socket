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

class socketServerEpoll
{
    public $events = [];
    public $serverSocket;
    public $settings = [];
    public $eventBase;

    public function __construct($host, $port)
    {
        $this->initSettings();
        $this->createServer($host, $port);
    }

    protected function initSettings()
    {
        $this->settings = [
            // 允许等待连接的请求数
            'backlog' => 128,

            /**
             * 每次select阻塞等待多少秒，获取在这段时间内的状态变化；
             * 0表示不等待，获取这个时刻的状态变化；
             * NULL表示阻塞等待直到有返回。
             */
            'timeout' => 3,
        ];
    }

    public function set($settings)
    {
        $this->settings = $settings;
    }

    public function on($event, $callback)
    {
        $this->events[$event] = $callback;
    }

    protected function createServer($host, $port)
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

        // 将服务的socket设置为非阻塞
        socket_set_nonblock($this->serverSocket);

        // 设置端口重用，否则每次重启要等待1-2分钟
        socket_get_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR);

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == false) {
            throw new \Exception(socket_strerror(socket_last_error()), socket_last_error());
        }

        return true;
    }

    public function start()
    {
        $this->eventBase   = new EventBase();

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
     * @param $fd    resource   发生事件的fd，此处就是监听fd
     * @param $what  int        事件类型，此处为2
     * @param $arg   mixed      实例化Event的最后一个参数
     */
    public function callbackOnConnect($fd, $what, $arg)
    {
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

    // 获取客户端信息
    public function getClientInfo($fd)
    {
        socket_getpeername($fd, $addr, $port);
        return ['ip' => $addr, 'port' => $port];
    }

    public function __destruct()
    {
        @socket_close($this->serverSocket);
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
        $this->fd = $fd;
        $this->events = $events;
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
     * @param $fd    resource   发生事件的fd，此处就是客户端fd
     * @param $what  int        事件类型，此处为2
     * @param $arg   mixed      实例化Event的最后一个参数
     */
    public function callbackOnRead($fd, $what, $arg)
    {
        $buf = $this->read($this->len);
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

    public function read($len)
    {
        $getMsg = '';

        do {
            $out = socket_read($this->fd, $len);
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

    public function send($data)
    {
        if (@socket_write($this->fd, $data, strlen($data)) !== false) {
            return true;
        }

        return false;
    }

    public function formatHttp($data)
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

    public function deleteConn()
    {
        @socket_shutdown($this->fd);
        @socket_close($this->fd);
        connectionManager::del((int)$this->fd);

        /**
         * free() 包含了del()并释放了分配给当前Event的资源
         * del() 会发起系统调用 EPOLL_CTL_DEL
         *
         * !!! 必须调用 EPOLL_CTL_DEL 来将当前fd(比如7fd)从"fd池"中移除。
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
    echo '[read] ';
    echo 'receive data : ' . $data . ' from ' . (int)$connection->fd . PHP_EOL;

    $msg = $connection->formatHttp('I am server...');
    $re  = $connection->send($msg);
    if ($re) {
        echo 'response to ' . (int)$connection->fd . PHP_EOL;
    } else {
        $connection->deleteConn();
    }

    // 模拟Http服务器
    //$socketServer->deleteConn($socketId);
});

$socketServer->on('close', function ($connection) {
    echo 'client close...' . (int)$connection->fd . PHP_EOL;
});

// 启动服务器
$socketServer->start();