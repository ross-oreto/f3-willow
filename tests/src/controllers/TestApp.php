<?php

namespace Oreto\F3Willow\Test\controllers;

use Base;
use Exception;
use Oreto\F3Willow\Routing\Routes;
use Oreto\F3Willow\Willow;

class TestApp extends Willow {
    static string $TEST1_RESPONSE = 'test1 response';

    static function routes(): Routes {
       return Routes::create(self::class)
           ->GET("home", "/")->handler('index')
           ->GET("test1", "/test1")->handler('test1')
           ->GET("server_error", "/server-error")->handler('serverError')
           ->build();
    }

    public function index(Base $f3) {
        echo "index";
    }

    function test1(Base $f3) {
        echo self::$TEST1_RESPONSE;
    }

    /**
     * @throws Exception
     */
    function serverError(Base $f3): void {
        throw new Exception('server error!');
    }
}