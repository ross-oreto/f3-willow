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
           ->GET("params", "/params/@id")->handler('params')
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

    function params(Base $f3): void {
        $id = $this->pathParam("id", "0", self::$intval);
        $this->put("id", $id);
        $this->put("a", $this->queryParam("a", 0, self::$intval));
        $this->put("b", $this->queryParam("b", "NaN", self::$intval));
        $this->put("n", $this->queryParam("n", true, self::$boolval));
        $this->put("date", $this->queryParam("date", new \DateTime("now"), self::$datetimeObject));
        echo $id;
    }
}