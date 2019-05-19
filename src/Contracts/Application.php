<?php

namespace Viloveul\Kernel\Contracts;

use Closure;
use Viloveul\Console\Contracts\Console;

interface Application
{
    public function console(): Console;

    /**
     * @param $middleware
     */
    public function middleware($middleware): void;

    public function serve(): void;

    /**
     * @param int $status
     */
    public function terminate(int $status): void;

    /**
     * @param Closure $handler
     */
    public function uses(Closure $handler): void;
}
