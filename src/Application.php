<?php

namespace Viloveul\Kernel;

use Closure;
use Exception;
use Viloveul\Http\Response;
use Viloveul\Console\Console;
use Viloveul\Middleware\Stack;
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

class Application implements IApplication
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

        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    /**
     * @return mixed
     */
    public function console(): IConsole
    {
        $console = $this->container->make(Console::class);
        $console->boot();
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
            $uri = $request->getUri();
            $router->dispatch($request->getMethod(), $uri->getPath());
            $route = $router->routed();
            $stack = new Stack(
                $this->makeController(
                    $route->getHandler(),
                    $route->getParams()
                ),
                $this->container->get(IMiddlewareCollection::class)
            );
            $response = $stack->handle($request);
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
     * @param bool     $exit
     * @param falseint $status
     */
    public function terminate(bool $exit = false, int $status = 0): void
    {
        if (true === $exit) {
            exit($status);
        }
    }

    /**
     * @param Closure $handler
     */
    public function uses(Closure $handler): void
    {
        $this->container->invoke($handler);
    }

    /**
     * @return mixed
     */
    protected function makeController($handler, $params)
    {
        return function (IServerRequest $request) use ($handler, $params) {
            if (is_callable($handler) && !is_scalar($handler)) {
                if (is_array($handler) && !is_object($handler[0])) {
                    $result = $this->container->invoke([
                        $this->container->make($handler[0]), $handler[1],
                    ], $params);
                } else {
                    $result = $this->container->invoke($handler, $params);
                }
            } else {
                if (is_scalar($handler) && strpos($handler, '::') === false && is_callable($handler)) {
                    $result = $this->container->invoke($handler, $params);
                } else {
                    if (is_scalar($handler)) {
                        $parts = explode('::', $handler);
                    } else {
                        $parts = (array) $handler;
                    }
                    $class = array_shift($parts);
                    $action = isset($parts[0]) ? $parts[0] : 'handle';
                    $object = is_string($class) ? $this->container->make($class) : $class;
                    $result = $this->container->invoke([$object, $action], $params);
                }
            }
            if ($result instanceof IResponse) {
                return $result;
            } else {
                return $this->container->get(IResponse::class)
                    ->setStatus(IResponse::STATUS_OK)
                    ->withPayload([
                        'data' => $result,
                    ]);
            }
        };
    }
}
