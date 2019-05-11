<?php

use Viloveul\Kernel\Application;

require_once __DIR__ . '/vendor/autoload.php';

class Abc implements Psr\Http\Server\MiddlewareInterface
{
    /**
     * @param  Psr\Http\Message\ServerRequestInterface $request
     * @param  Psr\Http\Server\RequestHandlerInterface $next
     * @return mixed
     */
    public function process(
        Psr\Http\Message\ServerRequestInterface $request,
        Psr\Http\Server\RequestHandlerInterface $next
    ): Psr\Http\Message\ResponseInterface {
        // replace request for next handler
        return $next->handle($request->withUri(Zend\Diactoros\UriFactory::createUri('/jos')));
    }
}

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

$app->middleware(Abc::class);

$app->uses(function (Viloveul\Router\Contracts\Collection $router) {
    $router->add(
        new Viloveul\Router\Route('POST /', function (Viloveul\Http\Contracts\ServerRequest $request) {
        })
    )->setName('dor');
});

$app->serve();

$app->terminate();
