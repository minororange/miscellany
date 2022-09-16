<?php

namespace Minororange\AsyncSocket\Tools;

class Timer
{
    public array $timers = [];

    private static $instance;

    public static function start($key)
    {
        static::getInstance()->doStart($key);
    }

    public static function end($key)
    {
        static::getInstance()->doEnd($key);
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function doStart($key)
    {
        $this->timers[$key] = $this->getMill();

        echo sprintf('%s started,began time:[%s]s', $key, $this->timers[$key]);
        echo PHP_EOL;
    }

    public function doEnd($key)
    {
        $start = $this->timers[$key];

        echo sprintf('%s completed,time usage:[%s]s', $key, round($this->getMill() - $start,4));
        echo PHP_EOL;
    }

    public function getMill()
    {
        list($usec, $sec) = explode(" ", microtime());
        return round(((float)$usec + (float)$sec),4);
    }
}