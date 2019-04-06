<?php

use Viloveul\Kernel\Application;

require_once __DIR__ . '/vendor/autoload.php';

$app = new class extends Application
{
    public function __construct()
    {
        parent::__construct(
            Viloveul\Container\ContainerFactory::instance(),
            new Viloveul\Config\Configuration([])
        );
    }
};

$app->uses(function (Viloveul\Router\Contracts\Collection $route) {
    $route->add(
        'home',
        new Viloveul\Router\Route('GET /example.php', function () {
            return 'Hello';
        })
    );
});

$app->serve();

$app->terminate();
