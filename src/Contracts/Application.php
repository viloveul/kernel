<?php

namespace Viloveul\Kernel\Contracts;

use Closure;
use Viloveul\Console\Contracts\Console;

interface Application
{
    public function console(): Console;

    public function lastInfo(): array;

    public function middleware($middleware): void;

    public function serve(): void;

    public function terminate(int $status = 0): void;

    public function uses(Closure $handler): void;
}
