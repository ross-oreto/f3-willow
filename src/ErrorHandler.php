<?php

namespace Oreto\F3Willow;

use Base;
use Oreto\F3Willow\Psr7\Psr7;

class ErrorHandler {
    static string $ERROR_PREFIX  = 'ERROR';

    private static function clearBuffer() {
        while (ob_get_level())
            ob_end_clean();
    }

    protected static function isJson(Base $f3): bool {
        return Willow::isJsonRequest($f3)
            || Willow::isAjax($f3)
            || Willow::isApi($f3);
    }

    static function handle(Base $f3) {
        echo match ($f3->get(self::$ERROR_PREFIX.".code")) {
            400 => self::handleStatusCode($f3, 400, false),
            401 => self::handleStatusCode($f3, 401, false),
            403 => self::handleStatusCode($f3, 403, false),
            404 => self::handleStatusCode($f3, 404, false),
            default => self::handleStatusCode($f3, 500, Willow::get("DEBUG", 3) > 0),
        };
    }

    /**
     * Get the extension of the view layer, .html, .htm, .xhtml, etc.
     * @return string
     */
    protected static function getViewExt(): string {
        return Willow::get('ext', '.htm');
    }

    static protected function handleStatusCode(Base $f3, int $statusCode, bool $trace): string {
        self::logError($f3->get(self::$ERROR_PREFIX));
        self::clearBuffer();
        return self::isJson($f3)
            ? self::errorJson($f3->get(self::$ERROR_PREFIX), $trace)
            : \Template::instance()->render(Willow::get("page.$statusCode", "_$statusCode")
                .self::getViewExt());
    }

    protected static function errorJson(array $error, bool $trace = true): string {
        header("Content-Type: ".Psr7::APPLICATION_JSON, true, $error['code']);
        if (!$trace)
            unset($error['trace']);
        return json_encode($error);
    }

    /**
     * log error to a source
     * @param array $error The error array object
    `ERROR.code` int - the HTTP status error code (`404`, `500`, etc.)
    `ERROR.status` string - a brief description of the HTTP status code. e.g. `'Not Found'`
    `ERROR.text` string - error context
    `ERROR.trace` string - stack trace stored in an `array()`
    `ERROR.level` int - error reporting level (`E_WARNING`, `E_STRICT`, etc.)
     * @param bool $trace If true add the stack trace to the message, otherwise omit.
     */
    static function logError(array $error, bool $trace = true): void {
        $stackTrace = $trace ? ". trace: ".$error['trace'] : '';
        $message = $error['code'].": ".$error['status']." - ".$error['text'].$stackTrace;
        Willow::getLogger()->error($message);
    }
}