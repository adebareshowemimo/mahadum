<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(assets/|images/|logo\.svg$|public-pages\.js$|notification-sw\.js$)~', $path)) {
    return false;
}
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
require dirname(__DIR__).'/public/index.php';
