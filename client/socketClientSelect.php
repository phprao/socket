<?php
/**
 * 客户端
 *
 * 非阻塞式 + 同步 + IO多路复用器select
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/../lib/socketClientBase.php';
require_once __DIR__ . '/../lib/socketHelper.php';

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

class selectManager
{
    public $dests = [];
    public $clients = [];
    public $fds = [];

    public $setting = [
        // 允许等待连接的请求数
        'backlog' => 128,

        /**
         * 每次select阻塞等待多少秒，获取在这段时间内的状态变化；
         *
         * 0表示不等待，获取这个时刻的状态变化，CPU占用率将暴增，禁止使用；
         * NULL表示阻塞等待直到有返回，建议使用。
         */
        'timeout' => null,
    ];

    public function __construct(array $dests)
    {
        $this->dests = $dests;
        $this->action();
    }

    public function action()
    {
        foreach ($this->dests as $k => $v) {
            $socketClient = (new socketClientSelect())->connectTo($v[0], $v[1]);
            socketHelper::send($socketClient->socket, 'name');
            $this->clients[(int)$socketClient->socket] = $socketClient; // 否则$socketClient就会被释放
            $this->fds[(int)$socketClient->socket] = $socketClient->socket;
        }

        while (!empty($this->fds)) {
            $writeFds     = null;
            $exceptionFds = null;
            $readFds      = $this->fds;

            $ret = socket_select($readFds, $writeFds, $exceptionFds, $this->setting['timeout']);
            if ($ret < 1 || empty($readFds)) {
                continue;
            }

            foreach ($readFds as $fd) {
                $buf = socketHelper::read($fd);
                if ($buf) {
                    echo 'fd: ' . (int)$fd . PHP_EOL;
                    print_r($buf . PHP_EOL);
                }

                unset($this->fds[(int)$fd]);
                socketHelper::close($fd);
            }
        }
    }
}

/*************************************************************************************/

try {

    $dests = [
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
    ];

    new selectManager($dests);

} catch (\Exception $e) {
    die($e->getMessage());
}