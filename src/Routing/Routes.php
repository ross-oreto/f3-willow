<?php

namespace Oreto\F3Willow\Routing;

use JetBrains\PhpStorm\Pure;

class Routes {
    #[Pure] public static function create(string $class): Routes {
        return new Routes($class);
    }

    /* @var $routes array<string, Route> */
    protected array $routes = [];

    public function __construct(
        private string $class
    ) {}

    public function getClass(): string {
        return $this->class;
    }

    /**
     * @return array<string, Route>
     */
    public function getRoutes(): array {
        return $this->routes;
    }

    public function route(string $method, string $name, string $pattern): NamedRoute {
        $current = new Route($method, $name, $pattern);
        $this->routes[$name] = $current;
        return new NamedRoute($current, $this);
    }
    public function GET(string $name, string $pattern): NamedRoute {
        return $this->route(Router::$GET, $name, $pattern);
    }
    public function POST(string $name, string $pattern): NamedRoute {
        return $this->route(Router::$POST, $name, $pattern);
    }
    public function PUT(string $name, string $pattern): NamedRoute {
        return $this->route(Router::$PUT, $name, $pattern);
    }
    public function DELETE(string $name, string $pattern): NamedRoute {
        return $this->route(Router::$DELETE, $name, $pattern);
    }
}