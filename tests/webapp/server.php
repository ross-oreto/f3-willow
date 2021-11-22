<?php
// emulates .htaccess by only allowing index.php entry point or serve assets
if (preg_match("/assets\//", $_SERVER["REQUEST_URI"])) {
    // allow serving assets
    return false;
} else {
    // funnel everything else to index.php
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    include __DIR__.$_SERVER['SCRIPT_NAME'];
}