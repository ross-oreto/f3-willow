<?php

namespace Oreto\F3Willow\Routing;

class NamedRoute {
    public function __construct(
        private Route $route
        , private Routes $routes
    ) {}

    public function handler(string $action): NamedRoute {
        $this->route->handler($this->routes->getClass(), $action);
        return $this;
    }
    public function staticHandler(string $action): NamedRoute {
        $this->route->staticHandler($this->routes->getClass(), $action);
        return $this;
    }
    /**
     * Set the handler directly. Useful for things like:
     * $f3->route('GET /public/@controller/@action','@controller->@action');
     * @param string $handler
     * @return $this
     */
    function dynamicHandler(string $handler): NamedRoute {
        $this->route->dynamicHandler($handler);
        return $this;
    }
    function callback(callable $callback): NamedRoute {
        $this->route->callback($callback);
        return $this;
    }

    public function ttl(int $seconds): NamedRoute {
        $this->route->ttl($seconds);
        return $this;
    }
    public function kbps(int $kbps): NamedRoute {
        $this->route->kbps($kbps);
        return $this;
    }
    public function ajax(): NamedRoute {
        $this->route->ajax();
        return $this;
    }
    public function cli(): NamedRoute {
        $this->route->cli();
        return $this;
    }
    public function sync(): NamedRoute {
        $this->route->sync();
        return $this;
    }

    // keep same contract as Routes for fluent API
    public function route(string $method, string $name, string $pattern): NamedRoute {
       return $this->routes->route($method, $name, $pattern);
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

    public function getRoute(): Route {
        return $this->route;
    }
    public function build(): Routes {
        return $this->routes;
    }
}