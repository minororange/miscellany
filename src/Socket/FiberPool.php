<?php

namespace Minororange\AsyncSocket\Socket;

class FiberPool
{
    /**
     * @var FiberNode[]
     */
    protected array $await = [];

    /**
     * @var static
     */
    protected static $instance;

    protected function __construct()
    {
    }

    public static function getInstance(): FiberPool
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function run()
    {

        while ($fiberNode = array_shift($this->await)) {
            echo $fiberNode->getName() . " resume\n";
            $fiberNode->getFiber()->resume();
        }
    }


    public function enqueue($name = '')
    {
        if ($name) echo "{$name} await\n";
        $this->await[] = new FiberNode(\Fiber::getCurrent(), $name);
        \Fiber::suspend();
    }
}