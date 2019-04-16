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

        try {
            $request = $this->container->get(IServerRequest::class);
            $router = $this->container->get(IRouteDispatcher::class);

            $accessMethods = $request->getHeader('Access-Control-Request-Method');
            $accessHeaders = $request->getHeader('Access-Control-Request-Headers');
            if (strtoupper($request->getMethod()) === 'OPTIONS' && count($accessMethods) > 0) {
                $cors = false;
                foreach ($accessMethods as $accessMethod) {
                    if ($router->dispatch($accessMethod, $request->getUri(), false) === true) {
                        $cors = true;
                        $response = $this->container->get(IResponse::class)
                            ->withHeader('Access-Control-Allow-Methods', $accessMethod)
                            ->withHeader('Access-Control-Allow-Headers', implode(', ', $accessHeaders))
                            ->withStatus(IResponse::STATUS_OK);
                    }
                }
                if ($cors === false) {
                    $response = $this->container->get(IResponse::class)->withStatus(IResponse::STATUS_METHOD_NOT_ALLOWED);
                }
            } else {
                $router->dispatch(
                    $request->getMethod(),
                    $request->getUri()
                );
                $stack = new Stack(
                    $this->container->make(Controller::class),
                    $this->container->get(IMiddlewareCollection::class)
                );
                $response = $stack->handle($request);
            }

        } catch (NotFoundException $e404) {
            $response = $this->container->get(IResponse::class)->withErrors(IResponse::STATUS_NOT_FOUND, [
                '404 Page Not Found',
            ]);
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
