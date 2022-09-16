<?php

namespace Minororange\AsyncSocket\Socket;

class FiberNode
{

    private \Fiber $fiber;
    private string $name;

    public function __construct(\Fiber $fiber, string $name)
    {
        $this->fiber = $fiber;
        $this->name = $name;
    }

    /**
     * @return \Fiber
     */
    public function getFiber(): \Fiber
    {
        return $this->fiber;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}