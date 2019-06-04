<?php

namespace Viloveul\Kernel;

use Closure;
use Throwable;
use Viloveul\Http\Response;
use Viloveul\Kernel\Monitor;
use Viloveul\Console\Console;
use Viloveul\Kernel\Resolver;
use Viloveul\Middleware\Stack;
use Viloveul\Log\Contracts\Logger;
use Viloveul\Router\NotFoundException;
use Viloveul\Http\Contracts\Response as IResponse;
use Viloveul\Kernel\Contracts\Monitor as IMonitor;
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
    private $container = null;

    /**
     * @param  IContainer     $container
     * @param  IConfiguration $config
     * @return mixed
     */
    public function __construct(IContainer $container, IConfiguration $config)
    {
        $start = defined('VILOVEUL_START') ? VILOVEUL_START : microtime(true);
        $this->container = $container;

        $this->container->set(IResponse::class, Response::class);
        $this->container->set(IRouteCollection::class, RouteCollection::class);
        $this->container->set(IMiddlewareCollection::class, MiddlewareCollection::class);
        $this->container->set(IMonitor::class, function () use ($start) {
            return new Monitor($start);
        });
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
        try {
            $request = $this->container->get(IServerRequest::class);
            $router = $this->container->get(IRouteDispatcher::class);

            $method = $request->getMethod();
            $uri = $request->getUri();

            $accessMethods = $request->getHeader('Access-Control-Request-Method');

            if (strtoupper($method) === 'OPTIONS' && count($accessMethods) > 0) {
                $cors = false;
                foreach ($accessMethods as $accessMethod) {
                    if ($router->dispatch($accessMethod, $uri, false) === true) {
                        $response = $this->container->get(IResponse::class)
                            ->withHeader('Access-Control-Allow-Methods', $accessMethod)
                            ->withStatus(IResponse::STATUS_NO_CONTENT);
                        if ($accessHeaders = $request->getHeader('Access-Control-Request-Headers')) {
                            $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $accessHeaders));
                        }
                        $cors = true;
                        break;
                    }
                }
                if ($cors === false) {
                    $response = $this->container->get(IResponse::class)->withErrors(IResponse::STATUS_METHOD_NOT_ALLOWED, [
                        sprintf('%s method to target %s is not allowed.', $method, $uri->getPath()),
                    ]);
                }
            } else {
                $router->dispatch($method, $uri);
                $this->container->get(IMiddlewareCollection::class)->map(function ($middleware) {
                    if (is_string($middleware)) {
                        if ($this->container->has($middleware)) {
                            return $this->container->get($middleware);
                        } else {
                            return $this->container->make($middleware);
                        }
                    } else {
                        return $middleware;
                    }
                });
                $stack = new Stack(
                    $this->container->make(Resolver::class),
                    $this->container->get(IMiddlewareCollection::class)
                );
                $response = $stack->handle($request);
            }

        } catch (Throwable $e) {
            if ($e instanceof NotFoundException) {
                $status = IResponse::STATUS_NOT_FOUND;
                $message = '404 Page Not Found';
            } else {
                $status = IResponse::STATUS_BAD_GATEWAY;
                $message = $e->getMessage();
            }
            if ($this->container->has(Logger::class)) {
                $this->container->get(Logger::class)->handleException($e);
            }
            $response = $this->container->get(IResponse::class)->withErrors($status, [$message]);
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
