<?php

namespace Oreto\F3Willow;

use Base;
use JetBrains\PhpStorm\Pure;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Oreto\F3Willow\Routing\Router;
use Oreto\F3Willow\Routing\Routes;

/**
 * Willow acts as a base controller for other concrete controllers.
 */
abstract class Willow {
    public static string $ASSETS_PATH = "/assets";

    protected static Logger $logger;
    protected static Router $router;
    protected static Base $f3;

    /**
     * Check the mode/environment of the application
     * Is the application running in dev,stage, or prod mode
     * @param string $mode The mode dev, stage, prod or any string
     * @return bool True if mode matches false otherwise
     */
    public static function isMode(string $mode): bool {
        return self::$f3->get("mode") === $mode;
    }
    public static function isDev(): bool {
        return self::isMode("dev");
    }
    public static function isProd(): bool {
        return self::isMode("prod");
    }
    public static function isStage(): bool {
        return self::isMode("stage");
    }
    public static function isTest(): bool {
        return self::isMode("test");
    }

    /**
     * @return bool True if the application is deployed to a host.
     */
    public static function isDeployed(): bool {
        return self::isStage() || self::isProd();
    }

    /**
     * @param string $name Name of the asset
     * @param bool $dist If true, look for the minified distributed version of the asset
     * Always look for distributed js/css asset when deployed
     * @return string Path to the web asset
     */
    public static function asset(string $name, bool $dist = false): string {
        return $dist || (self::isDeployed() && (str_ends_with($name, "js") || str_ends_with($name, "css")))
            ? self::$f3->get("BASE").self::$ASSETS_PATH."/dist"."/$name"
            : self::$f3->get("BASE").self::$ASSETS_PATH."/$name";
    }

    /**
     * Lookup the i18n key in the dictionary
     * @param string $key The dictionary key
     * @param string|array|null $args Arguments passed for substitution in the message
     * @return string The resolved message
     */
    public static function dict(string $key, string|array $args = NULL): string {
        $message = self::$f3->get(self::$f3->get("PREFIX").$key, $args);
        return $message == null ? $key : $message;
    }

    /**
     * Initialize Willow
     * Load config files, add extra functions, register error handlers, define routes etc.
     * Should be the first call in index.php after acquiring the Base::instance() $f3 object
     * @param Base $f3 Fat-Free object
     * @param Routes[] $routes
     * @param string|null $config Name of the config file
     * @return Base The base f3 object
     */
    public static function equip(Base $f3, array $routes, ?string $config = null): Base {
        self::$f3 = $f3;
        self::configure($f3, $config == null ? "../config/config.ini" : $config);
        self::initLogger();

        // mode functions in view templates
        $f3->set("isDev", function () { return self::isDev(); });
        $f3->set("isStage", function () { return self::isStage(); });
        $f3->set("isProd", function () { return self::isProd(); });
        $f3->set("isDeployed", function () { return self::isDeployed(); });

        // add the asset function for use in view templates
        $f3->set("asset", function (string $name) { return Willow::asset($name); });

        self::setErrorHandler();
        self::initRouter($routes);

        return $f3;
    }

    public static function getRouter(): Router {
        return self::$router;
    }
    public static function getLogger(): Logger {
        return self::$logger;
    }

    /**
     * Configure framework by reading config files from /config/
     * @param Base $f3 Fat-Free object
     * @param string $name Name of the config file
     * @return bool True if the file exists and is read, false otherwise
     */
    protected static function configure(Base $f3, string $name): bool {
        if (file_exists($name)) {
            // allow variable substitutions in config file
            $f3->config($name, true);
            return true;
        }
        return false;
    }

    /**
     * @param Routes[] $routes
     */
    protected static function initRouter(array $routes): void {
        self::$router = Router::of($routes);
        foreach (self::$router->getRoutes() as $route) {
            $method = $route->getMethod();
            $name = $route->getName();
            $pattern = $route->getPattern();
            $type = $route->getType();
            self::$f3->route("$method @$name: $pattern$type"
                , $route->getHandler()
                , $route->ttl
                , $route->kbps);
        }
    }

    protected static function setErrorHandler() {
        self::$f3->set('ONERROR','Oreto\F3Willow\ErrorHandler::handle');
    }

    /**
     * Setup the app logger, which is the basis for other controller loggers.
     */
    protected static function initLogger(): void {
        $logName = self::$f3->get("logName");
        $logName = $logName == null ? "app.log ": $logName;
        self::$logger = new Logger(Willow::class);
        $level = match (self::$f3->get("DEBUG")) {
            0 => Logger::ERROR,
            1 => Logger::NOTICE,
            2 => Logger::INFO,
            default => Logger::DEBUG
        };
        $stream = new StreamHandler(self::$f3->get('LOGS').$logName, $level, true);
        $formatter = new LineFormatter(null, null, true, true);
        $stream->setFormatter($formatter);
        self::$logger->pushHandler($stream);
    }

    /**
     * @param Base $f3
     * @return bool TRUE if an XML HTTP request is detected, FALSE otherwise.
     * Default value: Result of the expression $headers['X-Requested-With']=='XMLHttpRequest'
     */
    public static function isAjax(Base $f3): bool {
        return $f3->get("AJAX") == true;
    }

    /**
     * Check if the call is hitting a defined api
     * @param Base $f3
     * @return bool True if an api uri is defined and the current request is on that subdomain
     */
    public static function isApi(Base $f3): bool {
        $api = $f3->get("api.path");
        return $api != null && str_starts_with($f3->get("URI"), $api);
    }

    /**
     * Get specified header value from request
     * @param Base $f3
     * @param string $header Header name
     * @return string|null Header value as string or null if header name doesn't exist
     */
    public static function requestHeader(Base $f3, string $header): string|null {
        return $f3->get("HEADERS.$header");
    }

    /**
     * Determines if the string s contains a value
     * @param string|null $s The string to check
     * @param string $value The value to search for
     * @return bool True if s contains value, false otherwise
     */
    public static function strContains(string|null $s, string $value): bool {
        return !($s == null) && str_contains($s, $value);
    }

    /**
     * Determine if the request produces or consumes json.
     * @param Base $f3
     * @return bool True if the Accept or Content-Type headers are of type json
     */
    public static function isJsonRequest(Base $f3): bool {
        return self::strContains(self::requestHeader($f3, "Accept"), "json")
            || self::strContains(self::requestHeader($f3, "Content-Type"), "json");
    }

    /**
     * Define routes for this Willow
     * @return Routes
     */
    protected abstract static function routes(): Routes;

    protected Logger $log;

    #[Pure] public function __construct() {
        $this->log = self::$logger->withName($this->logName());
    }

    /**
     * Provide easy override to define a separate logger.
     * @return string
     */
    protected function logName(): string {
       return get_class($this);
    }

    /**
     * the framework looks for a method in this class named beforeRoute().
     * If present, F3 runs the code contained in the beforeRoute() event handler
     * before transferring control to the method specified in the route
     * @param Base $f3 Fat-Free object
     */
    function beforeRoute(Base $f3) {
    }

    /**
     * @param string $view View to render inside the main template
     * @param Base $f3 Fat-Free object
     * @param bool $template If true render view inside a template, otherwise just the specified view
     * @return string The rendered template
     */
    protected function render(string $view, Base $f3, bool $template = true): string {
        $viewKey = str_replace('/', '.', $view);
        $f3->set('view', $viewKey);
        $f3->set('title', self::dict($viewKey.'.title'));
        $ext = $f3->get('ext');
        if ($template) {
            $f3->set('content',"$view$ext");
            return \Template::instance()->render("/template$ext");
        } else {
            return \Template::instance()->render("/$view$ext");
        }
    }

    /**
     * Default index page looks for home.htm
     * @param Base $f3 Fat-Free object
     */
    public function index(Base $f3) {
        echo $this->render("home", $f3);
    }
}