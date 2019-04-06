<?php

namespace Viloveul\Kernel\Contracts;

use Closure;
use Viloveul\Console\Contracts\Console;

interface Application
{
    public function console(): Console;

    public function middleware($middleware): void;

    public function serve(): void;

    public function terminate(bool $exit = false, int $status = 0): void;

    public function uses(Closure $handler): void;
}
