<?php

namespace App\Support;

use App\Exceptions\InvalidCallException;
use League\Container\Container as DiContainer;
use League\Container\ReflectionContainer;
use League\Route\RouteCollection;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application Container
 *
 * Light weight app container for lux demo
 * Registers services and routes request
 *
 * @package App\Support
 */
class Container
{
    /**
     * @var DiContainer
     */
    private $dependency;

    /**
     * Application root path
     *
     * @var string
     */
    private $pathRoot;

    /**
     * Application Container constructor.
     *
     * @param DiContainer $di
     * @param             $path
     */
    public function __construct(DiContainer $di, $path)
    {
        // auto wire our calls
        $di->delegate(
            new ReflectionContainer
        );
        $this->dependency = $di;
        $this->pathRoot = rtrim($path, '/').'/';
    }

    /**
     * Run the http application
     *
     * @param string $routePath the path to the routes file
     */
    public function run($routePath)
    {
        $route = $this->getRouteCollection($routePath);

        $response = $this->dispatchRoute($route);

        $this->get('emitter')->emit($response);
    }

    /**
     * Forward any unrecognised method calls to the dependency injector.
     *
     * @param $method
     * @param $params
     * @return mixed
     * @throws InvalidCallException
     */
    public function __call($method, $params)
    {
        if (!method_exists($this->dependency, $method)) {
            throw new InvalidCallException(sprintf('"%s()" method does not exist in "%s"', $method, get_class($this)));
        }

        return call_user_func_array([$this->dependency, $method], $params);
    }

    /**
     * @param ResponseInterface|string|array $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function handleResponseTypes($response)
    {
        // string
        if (is_string($response)) {
            return (new DiactorosFactory)->createResponse(new Response($response));
        }

        // array
        if (is_array($response)) {
            return (new DiactorosFactory)->createResponse(
                new Response(json_encode($response), 200, ['content-type' => 'application/json'])
            );
        }

        // catch unhandled responses
        if (!($response instanceof ResponseInterface)) {
            throw new \RuntimeException("Route response is unsupported");
        }
        return $response;
    }

    /**
     * @param string $path
     * @return RouteCollection
     */
    private function getRouteCollection($path): RouteCollection
    {
        $route = new RouteCollection($this->dependency);

        // load the
        require $this->pathRoot.$path;

        return $route;
    }

    /**
     * @param RouteCollection $route
     * @return ResponseInterface
     */
    private function dispatchRoute($route):ResponseInterface
    {
        $psr7 = new DiactorosFactory();

        // execute the route
        $response = $route->dispatch(
            $psr7->createRequest($this->get('request')),
            $psr7->createResponse($this->get('response'))
        );

        return $this->handleResponseTypes($response);
    }
}