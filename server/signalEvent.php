<?php
/**
 * +----------------------------------------------------------
 * date: 2020/5/31 0031 10:26
 * +----------------------------------------------------------
 * author: Raoxiaoya
 * +----------------------------------------------------------
 * describe:
 * +----------------------------------------------------------
 */


require_once __DIR__ . '/../lib/connectionManager.php';

class signalBase
{
    public $eventBase;
    protected static $_signalId = 1;

    public function __construct()
    {
        $this->eventBase = new EventBase();
    }

    public function start()
    {
        if (self::$_signalId == 1) {
            die("empty signal pool" . PHP_EOL);
        }
        $this->eventBase->loop();
    }

    public function addSignal(int $signo, callable $cb, bool $persist = true)
    {
        $signal            = new signal($this->eventBase, $signo);
        $signal->_signalId = self::$_signalId;
        $signal->persist   = $persist;
        $signal->callback  = $cb;
        connectionManager::add(self::$_signalId, $signal);
        $signal->signal->addTimer();

        self::$_signalId++;

        return $signal->_signalId;
    }

    public function removeTimer(int $signalId)
    {
        return connectionManager::del($signalId);
    }

    public function removeAllTimer()
    {
        $allConnection = connectionManager::getAll();
        foreach ($allConnection as &$c) {
            $c = NULL;
        }

        return true;
    }

    public function __destruct()
    {
        $this->removeAllTimer();
    }
}

class signal
{
    public $signal;
    public $eventBase;
    public $_signalId;
    public $callback;
    public $signo;
    public $persist = true;

    public function __construct($eventBase, int $signo)
    {
        $this->eventBase = $eventBase;
        $this->signo     = $signo;
        $this->signal    = new Event($this->eventBase, $this->signo, Event::SIGNAL | Event::PERSIST, [$this, 'callbackOnSignal']);
    }

    public function callbackOnSignal($signo)
    {
        try {
            if (!$this->persist) {
                $this->__destruct();
            }


            ($this->callback)($this);
        } catch (Exception $e) {
            echo $e->getMessage(), PHP_EOL;
        }
    }

    public function deleteConn()
    {
        connectionManager::del($this->_signalId);
        $this->signal->free();
    }

    public function __destruct()
    {
        $this->deleteConn();
    }
}

$signalBase = new signalBase();

$signalBase->addSignal(SIGTERM, function ($signal) {
    echo '收到信息：', $signal->signo, PHP_EOL;
}, false);

$signalBase->addSignal(SIGPWR, function ($signal) {
    echo '收到信息：', $signal->signo, PHP_EOL;
});

//$signalBase->addSignal(SIGKILL, function ($signal) {
//    echo '收到信息：', $signal->signo, PHP_EOL;
//});

$signalBase->addSignal(SIGUSR1, function ($signal) {
    echo '收到信息：', $signal->signo, PHP_EOL;
});

$signalBase->addSignal(SIGINT, function ($signal) {
    echo '收到信息：', $signal->signo, PHP_EOL;
});

$signalBase->start();
