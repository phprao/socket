<?php
/**
 * ----------------------------------------------------------
 * date: 2019/9/6 11:45
 * ----------------------------------------------------------
 * author: Raoxiaoya
 * ----------------------------------------------------------
 * describe: socket_select 非阻塞服务器
 * ----------------------------------------------------------
 */

set_time_limit(0);

class socketServerSelect
{
    protected $errorCode;
    protected $errorMsg;
    protected $len = 8129;// 每次读取数据的字节数
    protected $events = [];
    protected $serverSocket;
    protected $settings = [];
    protected $clients = [];// 连接的客户端fd

    /**
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return mixed
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    public function __construct($host, $port)
    {
        $this->initSettings();
        $ret = $this->createServer($host, $port);
        if(!$ret){
            exit($this->getErrorMsg() . PHP_EOL);
        }
    }

    protected function initSettings()
    {
        $this->settings = [
            'backlog' => 128,// 允许等待连接的请求数
            'timeout' => 3,// 每次select阻塞等待多少秒，获取在这段时间内的状态变化；0表示不等待，获取这个时刻的状态变化
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
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) == FALSE) {
            $this->setError(1000, socket_strerror(socket_last_error()));
            return false;
        } else {
            $this->serverSocket = $sock;
        }

        // 2. 绑定
        if (socket_bind($this->serverSocket, $host, $port) == FALSE) {
            $this->setError(1001, socket_strerror(socket_last_error()));
            return false;
        }

        // 3. 监听
        if (socket_listen($this->serverSocket, $this->settings['backlog']) == FALSE) {
            $this->setError(1002, socket_strerror(socket_last_error()));
            return false;
        }

        return true;
    }

    protected function setError($code, $msg)
    {
        $this->errorCode = $code;
        $this->errorMsg  = $msg;
    }

    public function start()
    {
        // 4、轮训执行系统调用socket_select
        do {
            $readFds      = array_merge($this->clients, array($this->serverSocket));// 可读监听数组，将主socket加入，用以处理客户端连接
            $writeFds     = null;// 可写监听数组
            $exceptionFds = null;// 异常监听数组

            $ret = socket_select($readFds, $writeFds, $exceptionFds, $this->settings['timeout']);

            if ($ret < 1 || empty($readFds)) {
                continue;
            }

            // 处理异常的状态
            if (!empty($exceptionFds)) {
                foreach ($exceptionFds as $efd) {
                    $this->deleteConn((int)$efd);
                }
            }

            // 处理可读的状态
            if (in_array($this->serverSocket, $readFds)) {
                // 处理新客户端的连接，如果并发连接该如何？todo
                $conn = socket_accept($this->serverSocket);
                if ($conn === FALSE) {
                    $this->setError(1003, socket_strerror(socket_last_error()));
                } else {
                    $no                 = (int)$conn;
                    $this->clients[$no] = $conn;
                }
                // 处理完就该移除了
                $keyOfThis = array_search($this->serverSocket, $readFds);
                unset($readFds[$keyOfThis]);

                if (isset($this->events['connect']) && $conn) {
                    call_user_func($this->events['connect'], $this, $no);
                }
            }

            // 遍历剩余的可读fd，读取信息
            if (!empty($readFds)) {
                foreach ($readFds as $fd) {
                    if (is_resource($fd)) {
                        // 读取客户端全部信息
                        $buf = socket_read($fd, $this->len);
                        if ($buf === false) {
                            $this->deleteConn((int)$fd);
                        } elseif ($buf) {
                            if (isset($this->events['receive'])) {
                                call_user_func($this->events['receive'], $this, (int)$fd, $buf);
                            }
                        }else{
                            // 勉强定义为客户端断开
                            $this->deleteConn((int)$fd);
                        }
                    }else{
                        $this->deleteConn((int)$fd);
                    }
                }
            }

            echo PHP_EOL . '----------------' . PHP_EOL;

        } while (true);
    }

    public function send($socketId, $data)
    {
        if (isset($this->clients[$socketId]) && is_resource($this->clients[$socketId])) {
            if (@socket_write($this->clients[$socketId], $data, strlen($data))) {
                return true;
            }
        }

        $this->deleteConn($socketId);
    }

    protected function deleteConn($socketId)
    {
        @socket_shutdown($this->clients[$socketId]);
        socket_close($this->clients[$socketId]);
        unset($this->clients[$socketId]);
        if (isset($this->events['close'])) {
            call_user_func($this->events['close'], $this, $socketId);
        }
    }

    public function __destruct()
    {
        socket_close($this->serverSocket);
    }
}

$socketServer = new socketServerSelect('127.0.0.1', 8888);

$socketServer->on('connect', function ($socketServer, $socketId) {
    echo '[read] ';
    echo 'connect in...' . $socketId . PHP_EOL;
});

$socketServer->on('receive', function ($socketServer, $socketId, $data) {
    echo '[read] ';
    echo 'receive data : ' . $data . ' from ' . $socketId . PHP_EOL;

    $socketServer->send($socketId, 'I am server...');
});

$socketServer->on('close', function ($socketServer, $socketId) {
    echo 'client close...' . $socketId . PHP_EOL;
});

$socketServer->start();