<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);

if (!defined("APPLICATION_PATH"))
    define("APPLICATION_PATH", realpath(dirname(__FILE__) . "/") . "/");

$brain = new \Home\Application();
$brain->run();

