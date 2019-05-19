<?php

namespace Viloveul\Kernel\Contracts;

interface Monitor
{
    public function elapsedTime();

    public function memoryUsage();
}
