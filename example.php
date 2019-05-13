<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new ViloveulKernelSample\Application();

$app->uses(function (Viloveul\Router\Contracts\Collection $router) {
    $router->add(
        new Viloveul\Router\Route('GET /', [ViloveulKernelSample\MyController::class, 'index'])
    );
});

$app->serve();

$app->terminate();
