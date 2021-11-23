<?php

use Oreto\F3Willow\Test\controllers\TestApp;
use Oreto\F3Willow\Willow;

require '../../vendor/autoload.php';
$f3 = \Base::instance();
Willow::equip($f3, [TestApp::routes()])->run();