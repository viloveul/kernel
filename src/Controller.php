<?php

namespace Viloveul\Kernel;

use Viloveul\Container\ContainerAwareTrait;
use Viloveul\Router\Contracts\Route as IRoute;
use Viloveul\Http\Contracts\Response as IResponse;
use Viloveul\Http\Contracts\ServerRequest as IServerRequest;
use Viloveul\Router\Contracts\Dispatcher as IRouteDispatcher;
use Viloveul\Container\Contracts\ContainerAware as IContainerAware;

class Controller implements IContainerAware
{
    use ContainerAwareTrait;

    /**
     * @var mixed
     */
    protected $response;

    /**
     * @var mixed
     */
    protected $route;

    /**
     * @param IResponse        $response
     * @param IRouteDispatcher $router
     */
    public function __construct(
        IResponse $response,
        IRouteDispatcher $router
    ) {
        $this->response = $response;
        $this->route = $router->routed();
    }

    /**
     * @param  IServerRequest $request
     * @return mixed
     */
    public function __invoke(IServerRequest $request)
    {
        return $this->process($request);
    }

    /**
     * @return mixed
     */
    public function process(IServerRequest $request)
    {
        $handler = $this->route->getHandler();
        $params = $this->route->getParams();

        if (is_callable($handler) && !is_scalar($handler)) {
            $result = $this->useCallback($handler, $params);
        } else {
            $result = $this->makeCallback($handler, $params);
        }
        if ($result instanceof IResponse) {
            return $result;
        } else {
            return $this->response->setStatus(IResponse::STATUS_OK)->withPayload([
                'data' => $result,
            ]);
        }
    }

    /**
     * @param  $handler
     * @param  array      $params
     * @return mixed
     */
    protected function makeCallback($handler, array $params)
    {
        if (is_scalar($handler) && strpos($handler, '::') === false && is_callable($handler)) {
            $result = $this->getContainer()->invoke($handler, $params);
        } else {
            if (is_scalar($handler)) {
                $parts = explode('::', $handler);
            } else {
                $parts = (array) $handler;
            }
            $class = array_shift($parts);
            $action = isset($parts[0]) ? $parts[0] : 'handle';
            $object = is_string($class) ? $this->getContainer()->make($class) : $class;
            $result = $this->getContainer()->invoke([$object, $action], $params);
        }
        return $result;
    }

    /**
     * @param  $handler
     * @param  array      $params
     * @return mixed
     */
    protected function useCallback(callable $handler, array $params)
    {
        if (is_array($handler) && !is_object($handler[0])) {
            $object = $this->getContainer()->make($handler[0]);
            $result = $this->getContainer()->invoke([$object, $handler[1]], $params);
        } else {
            $result = $this->getContainer()->invoke($handler, $params);
        }
        return $result;
    }
}
