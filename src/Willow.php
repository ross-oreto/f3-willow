<?php

namespace Oreto\F3Willow;

use Base;
use Composer\InstalledVersions;
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

    public static string $PATH_PARAMS = "PARAMS";
    public static string $COOKIES = "COOKIE";
    public static string $QUERY_PARAMS = "GET";
    public static string $POST_BODY = "BODY";
    public static string $REQUEST = "REQUEST";
    public static string $SESSION = "SESSION";
    public static string $FILES = "FILES";
    public static string $SERVER = "SERVER";
    public static string $ENV = "ENV";
    public static string $FORM_DATA = "POST";

    protected static Logger $logger;
    protected static Router $router;
    protected static Base $f3;

    /**
     * Get the application mode/environment
     * dev,stage, prod mode
     * @return string
     */
    public static function getMode(): string {
        return self::get("mode", "dev");
    }

    /**
     * Check the mode/environment of the application
     * Is the application running in dev,stage, or prod mode
     * @param string $mode The mode dev, stage, prod or any string
     * @return bool True if mode matches false otherwise
     */
    public static function isMode(string $mode): bool {
        return self::getMode() === $mode;
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
        $message = self::get(self::get("PREFIX", "DICT.").$key, $key, $args);
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
        $logName = self::get("logName", "app.log");
        $logName = $logName == null ? "app.log ": $logName;
        self::$logger = new Logger(Willow::class);
        $level = match (self::get("DEBUG", 3)) {
            0 => Logger::ERROR,
            1 => Logger::NOTICE,
            2 => Logger::INFO,
            default => Logger::DEBUG
        };
        $stream = new StreamHandler(self::get('LOGS', "../logs/").$logName, $level, true);
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

    public static function get(string $name, mixed $defaultValue = null, string|array $args = NULL): mixed {
        $val = self::$f3->get($name, $args);
        return $val === null || $val === ''
            ? $defaultValue
            : $val;
    }

    // convenience methods to use f3 set
    public static function set(string $name, mixed $val, int $ttl=0): void {
        self::$f3->set($name, $val, $ttl);
    }
    public static function setAll(array $vars, string $prefix='', int $ttl=0): void {
        self::$f3->mset($vars, $prefix, $ttl);
    }

    /**
     * Define routes for this Willow
     * @return Routes
     */
    protected abstract static function routes(): Routes;

    protected Logger $log;

    // string
    protected static \Closure $intval;
    protected static \Closure $floatval;
    protected static \Closure $boolval;
    protected static \Closure $trim;
    protected static \Closure $strtotime;
    protected static \Closure $dateObject;
    protected static \Closure $datetimeObject;
    protected static \Closure $str_split;
    protected static \Closure $explode;
    protected static \Closure $json_decode;

    // int|float
    protected static \Closure $date;
    protected static \Closure $round;
    protected static \Closure $round1;
    protected static \Closure $round2;
    protected static \Closure $round3;
    protected static \Closure $round4;

    public function __construct() {
        $this->log = self::$logger->withName($this->logName());

        // anonymous string functions
        self::$intval = static function(string|float|int $s): int|null { return is_numeric($s) ? intval($s): null; };
        self::$floatval = static function(string|float|int $s): float|null { return is_numeric($s) ? floatval($s): null; };
        self::$boolval = static function(string|float|int $s): bool {
            return !(strcasecmp($s, 'false') == 0
                    || strcasecmp($s, 'no') == 0
                    || strcasecmp($s, 'n') == 0
                    || strcasecmp($s, 'not') == 0
                    || strcasecmp($s, 'invalid') == 0
                    || strcasecmp($s, 'incorrect') == 0) && boolval($s);
        };
        self::$trim = static function(string $s): string { return trim($s); };
        self::$strtotime = static function(string $s): ?int {
            $t = strtotime($s);
            return $t === false ? null : $t;
        };
        self::$dateObject = static function($s): ?\DateTime {
            $d = \DateTime::createFromFormat("m-d-Y", $s);
            return $d === false ? null : $d;
        };
        self::$datetimeObject = static function($s): ?\DateTime {
            $d = \DateTime::createFromFormat("m-d-Y H:i:s", $s);
            return $d === false ? null : $d;
        };
        self::$str_split = static function(string $s): array { return str_split($s); };
        self::$explode = static function(string $s): array { return explode(',', $s); };
        self::$json_decode = static function(string $s): \stdClass|array|null {
            $json = json_decode($s);
            return $json === false ? null : $json;
        };

        // anonymous number functions
        self::$date= static function($i): string { return date("Y-m-d H:i:s", $i); };
        self::$round = static function(float|int $i): float { return round($i); };
        self::$round1 = static function(float|int $i): float { return round($i, 1); };
        self::$round2 = static function(float|int $i): float { return round($i, 2); };
        self::$round3 = static function(float|int $i): float { return round($i, 3); };
        self::$round4 = static function(float|int $i): float { return round($i, 4); };
    }

    /**
     * Provide easy override to define a separate logger.
     * @return string
     */
    protected function logName(): string {
       return get_class($this);
    }

    /**
     * Bind value to hive key
     * @param string $name
     * @param $val mixed
     * @param $ttl int
     * @return mixed
     */
    protected function put(string $name, mixed $val, int $ttl=0): Willow {
        Willow::set($name, $val, $ttl);
        return $this;
    }

    /**
     * Multi-variable assignment using associative array
     * @param $vars array
     * @param $prefix string
     * @param $ttl int
     * @return Willow
     */
    protected function putAll(array $vars, string $prefix='', int $ttl=0): Willow {
        Willow::setAll($vars, $prefix, $ttl);
        return $this;
    }

    /**
     * Get a value by name from a global bucket and pipe the result through a series of 0...n functions
     * $this->getFrom(self::$GET, "id", 0);
     * @param string $bucket The PHP global: COOKIE, GET, POST, REQUEST, SESSION, FILES, SERVER, ENV
     * @param string $name The name of the variable
     * @param mixed|null $defaultValue Return this value if name doesn't exist
     * @param callable ...$pipe An array of anonymous functions which should be of the form f(x)=>y | f1(x)=>y | ...
     * @return mixed
     */
    protected function getFrom(string $bucket, string $name, mixed $defaultValue = null, callable ...$pipe): mixed {
        $v = self::get("$bucket.$name", $defaultValue);
        foreach ($pipe as $f) {
            if ($v == null) break;
            $v = $f($v);
            if ($v === null) {
                $v = $defaultValue;
                break;
            }
        }
        return $v;
    }

    /**
     * Get the url path parameter or the query parameter name if the url parameter doesn't exist
     * @param string $name The name of the variable
     * @param mixed|null $defaultValue Return this value if name doesn't exist
     * @param callable ...$pipe An array of anonymous functions which should be of the form f(x)=>y | f1(x)=>y | ...
     * @return mixed The parameter value
     */
    protected function param(string $name, mixed $defaultValue = null, callable ...$pipe): mixed {
        return $this->pathParam($name, $this->queryParam($name, $defaultValue, ...$pipe), ...$pipe);
    }
    /**
     * Get the URL path parameter by name
     * @param string $name The name of the variable
     * @param mixed|null $defaultValue Return this value if name doesn't exist
     * @param callable ...$pipe An array of anonymous functions which should be of the form f(x)=>y | f1(x)=>y | ...
     * @return mixed The parameter value
     */
    protected function pathParam(string $name, mixed $defaultValue = null, callable ...$pipe): mixed {
        return $this->getFrom(self::$PATH_PARAMS, $name, $defaultValue, ...$pipe);
    }
    /**
     * Get the URL query parameter by name
     * @param string $name The name of the variable
     * @param mixed|null $defaultValue Return this value if name doesn't exist
     * @param callable ...$pipe An array of anonymous functions which should be of the form f(x)=>y | f1(x)=>y | ...
     * @return mixed The parameter value
     */
    protected function queryParam(string $name, mixed $defaultValue = null, callable ...$pipe): mixed {
       return $this->getFrom(self::$QUERY_PARAMS, $name, $defaultValue, ...$pipe);
    }
    /**
     * Returns a map of name value paris of all path and query parameters from the request.
     * @return array
     */
    protected function getParamMap(): array {
        return array_merge(self::$f3->get(self::$PATH_PARAMS), self::$f3->get(self::$QUERY_PARAMS));
    }

    protected function getBodyAsString(): string {
       return self::$f3->get(self::$POST_BODY);
    }
    protected function getBodyAsJson(): \stdClass|array|NULL {
        return json_decode($this->getBodyAsString());
    }

    /**
     * @param string $name The name of the form field
     * @param mixed|null $defaultValue Return this value if name doesn't exist
     * @param callable ...$pipe An array of anonymous functions which should be of the form f(x)=>y | f1(x)=>y | ...
     * @return mixed
     */
    protected function getFormParam(string $name, mixed $defaultValue = null, callable ...$pipe): mixed {
        return $this->getFrom(self::$FORM_DATA, $name, $defaultValue, ...$pipe);
    }
    protected function getFormData(): array {
        return self::$f3->get(self::$FORM_DATA);
    }

    protected function session(string $name, mixed $defaultValue = null): mixed {
        $value = $this->getFrom(self::$SESSION, $name);
        if ($value === null) {
            $value = $defaultValue;
        }
        $this->setSession($name, $value);
        return $value;
    }
    protected function setSession(string $name, mixed $value): Willow {
       return $this->put("Session.$name", $value);
    }

    /**
     * the framework looks for a method in this class named beforeRoute().
     * If present, F3 runs the code contained in the beforeRoute() event handler
     * before transferring control to the method specified in the route
     * @param Base $f3 Fat-Free object
     */
    function beforeRoute(Base $f3) {
        $this->langParam();
    }

    /**
     * Parse the language/locale from URL parameter 'lang'
     * If lang param doesn't exist fallback to the session and if that doesn't exist, fallback to the framework default
     * @return string|null The value of lang URL parameter
     */
    protected function langParam(): ?string {
        $lang = $this->param("lang", $this->session("lang"));
        if ($lang) {
            Willow::set('LANGUAGE', $lang);
        }
        return $lang;
    }

    /**
     * @param string $view View to render inside the main template
     * @param bool $template If true render view inside a template, otherwise just the specified view
     * @return string The rendered template
     */
    protected function render(string $view, bool $template = true): string {
        $viewKey = str_replace('/', '.', $view);
        self::$f3->set('view', $viewKey);
        self::$f3->set('title', self::dict($viewKey.'.title'));
        $ext = self::$f3->get('ext');
        if ($template) {
            self::$f3->set('content',"$view$ext");
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
        echo $this->render("home");
    }

    public function info(Base $f3) {
        echo json_encode(array('php' => phpversion()
        , 'mode' => self::getMode()
        , 'fat-free' => InstalledVersions::getVersion('bcosca/fatfree-core')
        , 'version' => InstalledVersions::getRootPackage()['name']));
    }
}