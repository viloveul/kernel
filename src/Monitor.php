<?php

namespace Viloveul\Kernel;

use Viloveul\Kernel\Contracts\Monitor as IMonitor;

class Monitor implements IMonitor
{
    /**
     * @var mixed
     */
    private $start;

    /**
     * @param int $start
     */
    public function __construct(int $start)
    {
        $this->start = $start;
    }

    public function elapsedTime()
    {
        return (microtime(true) - $this->start);
    }

    public function memoryUsage()
    {
        return memory_get_usage();
    }
}
