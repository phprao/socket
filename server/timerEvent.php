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

class timerBase
{
    public $eventBase;
    protected static $_timerId = 1;

    public function __construct()
    {
        $this->eventBase = new EventBase();
    }

    public function start()
    {
        if (self::$_timerId == 1) {
            die("empty timer pool" . PHP_EOL);
        }
        $this->eventBase->loop();
    }

    public function addTimer(float $timeout, callable $cb, bool $persist = true)
    {
        $timer           = new timer($this->eventBase);
        $timer->_timerId = self::$_timerId;
        $timer->persist  = $persist;
        $timer->callback = $cb;
        connectionManager::add(self::$_timerId, $timer);
        $timer->timer->addTimer($timeout);

        self::$_timerId++;

        return $timer->_timerId;
    }

    public function removeTimer(int $timerId)
    {
        return connectionManager::del($timerId);
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

class timer
{
    public $timer;
    public $eventBase;
    public $_timerId;
    public $callback;
    public $persist = true;

    public function __construct($eventBase)
    {
        $this->eventBase = $eventBase;
        $this->timer     = new Event($this->eventBase, -1, Event::TIMEOUT | Event::PERSIST, [$this, 'callbackOnTimer']);
    }

    public function callbackOnTimer()
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
        connectionManager::del($this->_timerId);
        $this->timer->free();
    }

    public function __destruct()
    {
        $this->deleteConn();
    }
}

$timerBase = new timerBase();

$timerBase->addTimer(1, function ($timer) {
    echo time(), '-', 1, PHP_EOL;
}, false);

$timerBase->addTimer(3, function ($timer) {
    echo time(), '-', 2, PHP_EOL;
    sleep(3);
});

$timerBase->addTimer(7, function ($timer) {
    echo time(), '-', 3, PHP_EOL;
});

$timerBase->addTimer(8, function ($timer) {
    echo time(), '-', 4, PHP_EOL;
});

$timerBase->addTimer(10, function ($timer) {
    echo time(), '-', 5, PHP_EOL;
});

$timerBase->start();
