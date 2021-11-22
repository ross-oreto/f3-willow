<?php

namespace Oreto\F3Willow\Routing;

class Route {
    private ?string $type = null;
    private string $handler;
    public int $ttl = 0;
    public int $kbps = 0;
    /** @var callback */
    private $callback = null;

    public function __construct(
        private string $method
        , private string $name
        , private string $pattern
    ) {}

    public function getMethod(): string {
        return $this->method;
    }
    public function getName(): string {
        return $this->name;
    }
    public function getPattern(): string {
        return $this->pattern;
    }
    public function getHandler(): string|callable {
       return $this->callback == null ? $this->handler : $this->callback;
    }
    public function getType(): string {
        return $this->type == null ? '' : " [$this->type]";
    }

    /**
     * Set the string reference to the class function handler used for the controller route
     * example: Oreto\F3Willow\App->index
     * @param string $class  Class name such as App::class
     * @param string $action Name of the controller function
     * @return Route         Reference to this route
     */
    function handler(string $class, string $action): Route {
        $this->handler = $class."->$action";
        return $this;
    }
    /**
     * Same as handler, builds the handler using :: static syntax.
     */
    function staticHandler(string $class, string $action): Route {
        $this->handler = $class."::$action";
        return $this;
    }
    /**
     * Set the handler directly. Useful for things like:
     * $f3->route('GET /public/@controller/@action','@controller->@action');
     * @param string $handler
     * @return $this
     */
    function dynamicHandler(string $handler): Route {
        $this->handler = $handler;
        return $this;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    function callback(callable $callback): Route {
        $this->callback = $callback;
        return $this;
    }

    public function ttl(int $seconds): Route {
        $this->ttl = $seconds;
        return $this;
    }
    public function kbps(int $kbps): Route {
        $this->kbps = $kbps;
        return $this;
    }

    public function ajax(): Route {
        $this->type = 'ajax';
        return $this;
    }
    public function cli(): Route {
        $this->type = 'cli';
        return $this;
    }
    public function sync(): Route {
        $this->type = 'sync';
        return $this;
    }
}