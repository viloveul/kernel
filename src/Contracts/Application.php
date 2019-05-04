<?php

namespace Viloveul\Kernel\Contracts;

use Closure;
use Throwable;
use Viloveul\Console\Contracts\Console;

interface Application
{
    /**
     * @param Throwable $e
     */
    public function catchThrowable(Throwable $e): void;

    public function console(): Console;

    public function middleware($middleware): void;

    public function serve(): void;

    public function terminate(int $status = 0): void;

    public function uses(Closure $handler): void;
}
