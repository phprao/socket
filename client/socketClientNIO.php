<?php
/**
 * 客户端
 *
 * 非阻塞式 + 同步
 */

error_reporting(E_ALL);
set_time_limit(0);
require_once __DIR__ . '/../lib/socketClientBase.php';

class socketClientNIO extends socketClientBase
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

    $clients = [];
    $dests = [
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
        ['127.0.0.1', 8888],
    ];

    foreach($dests as $k => $v){
        $socketClient = (new socketClientNIO())->connectTo($v[0], $v[1]);
        $socketClient->send('name');
        $clients[$k] = $socketClient;
    }

    while(!empty($clients)){
        foreach ($clients as $k => $v){
            $data = $v->read();
            if($data === ''){
                unset($clients[$k]);
            }elseif($data === false){
                continue;
            }else{
                print_r($data . PHP_EOL);
                unset($clients[$k]);
            }
        }
    }

} catch (\Exception $e) {
    die($e->getMessage());
}


