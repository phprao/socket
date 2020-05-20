<?php
/**
 * 客户端
 *
 * 阻塞式 + 同步
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/socketClientBase.php';

class socketClientBIO extends socketClientBase
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

        return $this;
    }
}

/*************************************************************************************/

try {
    $socketClient = new socketClientBIO();
    $socketClient->connectTo('127.0.0.1', 8888);
    $re = $socketClient->send('name');
var_dump($re);
    $data = $socketClient->read();
    print_r($data . PHP_EOL);
} catch (\Exception $e) {
    die($e->getMessage());
}


