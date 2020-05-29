<?php
/**
 * 服务端
 *
 * 非阻塞式 + 同步 + IO多路复用器epoll
 *
 * 这是官网的demo改造的
 * https://www.php.net/manual/zh/eventlistener.construct.php
 *
 * 用到的类
 *  EventBase：epoll_create, epoll_wait
 *  EventListener：建立服务端并监听，设置客户端连接的回调
 *  EventBufferEvent：封装了Libevent's buffer event，对于连接的读写操作。
 *  Event：事件
 *  EventUtil：助手类
 * EventBuffer：buffered IO
 */

require_once __DIR__ . '/../lib/connectionManager.php';
require_once __DIR__ . '/../lib/socketHelper.php';

class MyListenerConnection
{
    public $bev;
    public $base;
    public $fd;

    public function __construct($base, $fd)
    {
        $this->base = $base;
        $this->fd   = $fd;
        /**
         * 创建BufferEvent对象
         * 此对象内置了读写事件处理器，但并没有添加到事件循环队列中
         * 同时该对象分别创建input/outpu对象【内置创建】主要用于数据读写【接收和发送】
         */
        $this->bev = new EventBufferEvent($base, $this->fd, EventBufferEvent::OPT_CLOSE_ON_FREE);

        /**
         * 设置回调
         * 1、readcb：读就绪事件发生后，内置的读事件处理器运行读取数据，然后会调用此函数。
         * 2、writecb：内置的写事件处理器将数据发送出去后会调用此函数。
         * 3、eventcb：特殊情况下会触发的事件，比如主动客户端断开。
         */
        $this->bev->setCallbacks(
            [$this, "readEventCallback"],
            [$this, "writeEventCallback"],
            [$this, "echoEventCallback"],
            NULL
        );

        //将内置的写事件处理器添加到事件循环队列中，并且向内核事件表注册读就绪事件
        if (!$this->bev->enable(Event::READ)) {
            echo "Failed to enable READ\n";
            return;
        }
    }

    public function readEventCallback($bev, $ctx)
    {
        // echo 服务器响应
        //$bev->output->addBuffer($bev->input);

        // http 服务器响应
        $response = socketHelper::formatHttp('I am server...' . PHP_EOL);
        $buffer   = new EventBuffer();
        $buffer->add($response);
        $bev->output->addBuffer($buffer);
    }

    public function writeEventCallback($bev, $ctx)
    {
        // 模拟http服务
        $this->__destruct();
    }

    /**
     * 事件回调
     * 1、客户端主动断开会有READ事件，但是读到的为空，会触发 EventBufferEvent::EOF
     */
    public function echoEventCallback($bev, $events, $ctx)
    {
        if ($events & EventBufferEvent::ERROR) {
            echo "Error from bufferevent\n";
        }

        if ($events & (EventBufferEvent::EOF | EventBufferEvent::ERROR)) {
            $this->__destruct();
        }
    }

    public function __destruct()
    {
        @socket_shutdown($this->fd);
        @socket_close($this->fd);
        connectionManager::del($this->fd);
        $this->bev->free();
    }
}

class MyListener
{
    public $base;
    public $listener;
    public $socket;

    public function __construct($port)
    {
        /**
         * 创建event_base对象
         * 内置了I/O事件处理器池和信号事件处理器池
         * 同时也内置的定时时间堆
         */
        $this->base = new EventBase();
        if (!$this->base) {
            echo "Couldn't open event base";
            exit(1);
        }

        /* 或者
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!socket_bind($this->socket, '0.0.0.0', $port)) {
                echo "Unable to bind socket\n";
                exit(1);
            }
            $this->listener = new EventListener($this->base,
                array($this, "acceptConnCallback"), $this->base,
                EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE,
                -1, $this->socket
            );
         */

        /**
         * 创建socket并监听，同时将此socket的读就绪事件注册到【经过I/O复用函数即事件多路分发器EventDemultiplexer管理】
         * 此socket 内置了监听事件处理器，客户端连接后，会调用此事件处理器，然后再运行用户设置的回调函数acceptConnCallBack函数
         * EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE 标志位
         * EventListener::OPT_CLOSE_ON_FREE 如果EventListener对象被释放了会关闭低层连接socket
         * EventListener::OPT_REUSEABLE  端口复用
         *
         * backlog：等待accept的连接的个数。
         */
        $this->listener = new EventListener(
            $this->base,
            [$this, "acceptConnCallback"],
            $this->base,
            EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE,
            -1,
            "0.0.0.0:$port"
        );

        if (!$this->listener) {
            echo "Couldn't create listener";
            exit(1);
        }

        /**
         * 设置此socket事件处理器的错误回调
         * 函数原型标记错误，应该是  callable $cb
         */
        $this->listener->setErrorCallback([$this, "acceptErrorCallback"]);
    }

    /**
     * 连接建立后回调
     *
     * @param $listener EventListener   监听器
     * @param $fd       mixed           连接资源
     * @param $address  array           客户端地址信息
     * @param $ctx      mixed           传递的参数
     */
    public function acceptConnCallback($listener, $fd, $address, $ctx)
    {
        $connection = new MyListenerConnection($this->base, $fd);
        connectionManager::add((int)$fd, $connection);
    }

    /**
     * accept操作出现异常的回调
     *
     * @param $listener EventListener   监听器
     * @param $ctx      mixed           传递的参数
     */
    public function acceptErrorCallback($listener, $ctx)
    {
        fprintf(
            STDERR,
            "Got an error %d (%s) on the listener. " . "Shutting down.\n",
            EventUtil::getLastSocketErrno(),
            EventUtil::getLastSocketError()
        );

        // 经过多少秒之后停止事件循环loop
        $this->base->exit(NULL);
    }

    public function dispatch()
    {
        /**
         * 内置了event_base_loop进行循环处理
         * 主要是调用如epoll的epoll_wait函数进行监听
         * 当任意I/O产生了就绪事件则会通知此进程
         * 此进程将会遍历就绪的I/O事件读取文件描述符
         * 并从I/O事件处理器池读取对应的事件处理器队列
         * 再将事件处理器插入到请求队列中
         * 两从请求队列中获取到事件并循环一一处理
         * 从而运行指定的回调函数
         *
         * 启动事件循环，等同于不传参数的loop()
         */
        $this->base->dispatch();
    }

    /**
     * 释放资源
     */
    public function __destruct()
    {
        $allConnection = connectionManager::getAll();
        foreach ($allConnection as &$c) {
            $c = NULL;
        }
    }
}

$port = 8888;

if ($argc > 1) {
    $port = (int)$argv[1];
}
if ($port <= 0 || $port > 65535) {
    exit("Invalid port");
}

$l = new MyListener($port);

$l->dispatch();