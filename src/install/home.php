<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

if (!defined("APPLICATION_PATH"))
    define("APPLICATION_PATH", realpath(dirname(__FILE__) . "/") . "/");

$brain = new \Home\Home();
$brain->start();

