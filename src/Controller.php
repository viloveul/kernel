<?php

namespace Viloveul\Kernel;

use Viloveul\Container\ContainerAwareTrait;
use Viloveul\Kernel\Contracts\Controller as IController;

abstract class Controller implements IController
{
    use ContainerAwareTrait;
}
