<?php

namespace Viloveul\Kernel;

use Closure;
use Exception;
use Viloveul\Http\Response;
use Viloveul\Console\Console;
use Viloveul\Middleware\Stack;
use Viloveul\Kernel\Controller;
use Viloveul\Router\NotFoundException;
use Viloveul\Http\Contracts\Response as IResponse;
use Viloveul\Router\Collection as RouteCollection;
use Viloveul\Router\Dispatcher as RouteDispatcher;
use Viloveul\Console\Contracts\Console as IConsole;
use Viloveul\Container\Contracts\Container as IContainer;
use Viloveul\Http\Server\RequestFactory as RequestFactory;
use Viloveul\Kernel\Contracts\Application as IApplication;
use Viloveul\Middleware\Collection as MiddlewareCollection;
use Viloveul\Http\Contracts\ServerRequest as IServerRequest;
use Viloveul\Router\Contracts\Collection as IRouteCollection;
use Viloveul\Router\Contracts\Dispatcher as IRouteDispatcher;
use Viloveul\Config\Contracts\Configuration as IConfiguration;
use Viloveul\Middleware\Contracts\Collection as IMiddlewareCollection;

abstract class Application implements IApplication
{
    /**
     * @var mixed
     */
    protected $container = null;

    /**
     * @param  IContainer     $container
     * @param  IConfiguration $config
     * @return mixed
     */
    public function __construct(IContainer $container, IConfiguration $config)
    {
        $this->container = $container;

        $this->container->set(IResponse::class, Response::class);
        $this->container->set(IRouteCollection::class, RouteCollection::class);
        $this->container->set(IMiddlewareCollection::class, MiddlewareCollection::class);
        $this->container->set(IConfiguration::class, function () use ($config) {
            return $config;
        });
        $this->container->set(IServerRequest::class, function () {
            return RequestFactory::fromGlobals();
        });
        $this->container->set(IRouteDispatcher::class, function (IConfiguration $config, IRouteCollection $routes) {
            $router = new RouteDispatcher($routes);
            $router->setBase($config->get('basepath') ?: '/');
            return $router;
        });

        // invoke method initialize if exists
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    /**
     * @return mixed
     */
    public function console(): IConsole
    {
        if ($this->container->has(IConsole::class)) {
            $console = $this->container->get(IConsole::class);
        } else {
            $console = $this->container->make(Console::class);
            $console->boot();
        }
        return $console;
    }

    /**
     * @param $middleware
     */
    public function middleware($middleware): void
    {
        if (is_array($middleware) && !is_callable($middleware)) {
            foreach ($middleware as $value) {
                $this->container->get(IMiddlewareCollection::class)->add($value);
            }
        } else {
            $this->middleware([$middleware]);
        }
    }

    /**
     * @return mixed
     */
    public function serve(): void
    {
        $request = $this->container->get(IServerRequest::class);
        try {
            $this->container->get(IRouteDispatcher::class)->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath()
            );
            $stack = new Stack(
                $this->container->make(Controller::class),
                $this->container->get(IMiddlewareCollection::class)
            );
            $response = $stack->handle($request);

        } catch (NotFoundException $e404) {
            if (strtoupper($request->getMethod()) === 'OPTIONS') {
                $response = $this->container->get(IResponse::class)
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, HEAD')
                    ->withHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
                    ->withHeader('Access-Control-Request-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization')
                    ->withHeader('Access-Control-Request-Method', 'GET, POST, PUT, PATCH, DELETE, HEAD')
                    ->withStatus(IResponse::STATUS_OK);
            } else {
                $response = $this->container->get(IResponse::class)->withErrors(IResponse::STATUS_NOT_FOUND, [
                    '404 Page Not Found',
                ]);
            }

        } catch (Exception $e) {
            $response = $this->container->get(IResponse::class)->withErrors(IResponse::STATUS_PRECONDITION_FAILED, [
                $e->getMessage(),
            ]);
        }
        $response->send();
    }

    /**
     * @param int $status
     */
    public function terminate(int $status = 0): void
    {
        exit($status);
    }

    /**
     * @param Closure $handler
     */
    public function uses(Closure $handler): void
    {
        $this->container->invoke($handler);
    }
}
