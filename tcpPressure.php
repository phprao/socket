<?php
/**
 * ----------------------------------------------------------
 * date: 2019/9/9 10:06
 * ----------------------------------------------------------
 * author: Raoxiaoya
 * ----------------------------------------------------------
 * describe: tcp 压力测试-多进程
 * ----------------------------------------------------------
 */

/**
 * 虽然多线性能更好，但是考虑到多线程需要安装扩展，所以选择多进程。
 * 适用于Linux
 */

require_once './socketClientBIO.php';

class TcpPressure{
    protected $clientNum;
    protected $requestNum;
    protected $clientRequestNum;
    protected $host;
    protected $port;
    protected $keepAlive = false;
    protected $allowParam = ['-c', '-n', '-h', '-p'];

    public function __construct($params = [])
    {
        try{
            $this->checkParams($params);
        }catch(Exception $e){
            die($e->getMessage() . PHP_EOL);
        }
    }

    public function checkParams($params){
        if(empty($params)){
            throw new Exception('参数错误');
        }

        if($params[1] == '--help'){
            $this->showNotice();
            return true;
        }

        $param = [];
        foreach ($params as $val){
            if($val == '-k'){
                $this->keepAlive = true;
                continue;
            }

            if(preg_match('/^(-\S)(.*)$/', $val, $matche)){
                if(isset($matche[1]) && isset($matche[2])){
                    $param[$matche[1]] = $matche[2];
                }
            }
        }

        $this->setParams($param);
    }

    public function setParams($param){
        if(!empty($param)){
            foreach($param as $k => $v){
                switch ($k){
                    case '-c':
                        $this->clientNum = (int)$v;
                        break;
                    case '-n':
                        $this->requestNum = (int)$v;
                        break;
                    case '-h':
                        $this->host = $v;
                        break;
                    case '-p':
                        $this->port = $v;
                        break;
                }
            }
        }

        if(!$this->clientNum || !$this->requestNum || !$this->host || !$this->port){
            throw new Exception('参数错误');
        }

        $this->clientRequestNum = ceil($this->requestNum / $this->clientNum);
    }

    public function showNotice(){
        $msg = "-c\tint: client_num".PHP_EOL;
        $msg .= "-n\tint: request_num".PHP_EOL;
        $msg .= "-k\tif you want to keep-alive, please use this flag".PHP_EOL;

        throw new Exception($msg);
    }

    public function start(){
        $start = microtime(true);

        for ($j = 1; $j <= $this->clientNum; $j++) {
            $pid = pcntl_fork();
            if ($pid == 0) {

                $client = null;
                for ($i = 0; $i < $this->clientRequestNum; $i++) {

                    if($this->keepAlive){
                        if(is_null($client)){
                            $socketClient = new socketClient($this->host, $this->port);
                            $client = $socketClient;
                        }else{
                            $socketClient = $client;
                        }
                    }else{
                        $socketClient = new socketClient($this->host, $this->port);
                    }

                    $socketClient->send('name', function ($socketClient, $data) {

                    });
                }
                exit();
            }
        }

        // 等待子进程执行完毕，避免僵尸进程
        $k = 0;
        while ($k < $this->clientNum) {
            $nPID    = pcntl_wait($nStatus, WNOHANG);
            if ($nPID > 0) {
                ++$k;
            }
        }

        $end = microtime(true);

        echo 'QPS: '. (int)($this->requestNum / ($end - $start));
        echo PHP_EOL;
    }
}

$p = new TcpPressure($argv);
$p->start();

// php tcpPressure.php -c10 -n1000 -h127.0.0.1 -p8888 -k