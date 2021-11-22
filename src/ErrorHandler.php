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
            404 => self::handle404($f3),
            default => self::handle500($f3),
        };
    }

    static function handle404(Base $f3): string {
        $ext = $f3->get('ext');
        self::logError($f3->get(self::$ERROR_PREFIX), false);
        self::clearBuffer();
        return self::isJson($f3)
            ? self::errorJson($f3->get(self::$ERROR_PREFIX), false)
            : \Template::instance()->render("_404$ext");
    }

    static function handle500(Base $f3): string {
        $ext = $f3->get('ext');
        self::logError($f3->get(self::$ERROR_PREFIX));
        self::clearBuffer();
        return self::isJson($f3)
            ? self::errorJson($f3->get(self::$ERROR_PREFIX), $f3->get("DEBUG") > 0)
            : \Template::instance()->render("_500$ext");
    }

    protected static function errorJson(array $error, bool $trace = true): string {
        header("Content-Type: application/json".Psr7::APPLICATION_JSON, true, $error['code']);
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