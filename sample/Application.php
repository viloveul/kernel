<?php

namespace ViloveulKernelSample;

use Viloveul\Config\Configuration;
use Viloveul\Container\ContainerFactory;
use Viloveul\Kernel\Application as Kernel;

class Application extends Kernel
{
    public function __construct()
    {
        parent::__construct(ContainerFactory::instance(), new Configuration([]));
    }
}
