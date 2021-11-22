<?php

namespace Oreto\F3Willow\Routing;

use JetBrains\PhpStorm\Pure;

class Router {
    public static string $GET = "GET";
    public static string $POST = "POST";
    public static string $PUT = "PUT";
    public static string $DELETE = "DELETE";

    /**
     * @param Routes[] $routes
     * @return Router
     */
    #[Pure] public static function of(array $routes): Router {
        return new Router($routes);
    }

    /* @var $routes array<string, Route> */
    protected array $routes = [];

    /* @var $controllerRoutes array<string, Routes> */
    protected array $controllerRoutes = [];

    /**
     * @param Routes[] $routes
     */
    #[Pure] protected function __construct(array $routes) {
        foreach ($routes as $classRoutes) {
            $this->controllerRoutes[$classRoutes->getClass()] = $classRoutes;
            /** @var Route $route */
            foreach ($classRoutes->getRoutes() as $route) {
                $this->routes[$route->getName()] = $route;
            }
        }
    }

    /**
     * @param string|null $class
     * @return Route[]
     */
    public function getRoutes(?string $class = null): array {
       return $class == null ? array_values($this->routes) : ($this->controllerRoutes[$class])->getRoutes();
    }

    /**
     * @param string $name
     * @return Route|null
     */
    public function getRoute(string $name): ?Route {
       return $this->routes[$name];
    }
}