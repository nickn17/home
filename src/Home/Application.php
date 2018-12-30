<?php

/**
 * Home automation engine
 *
 * @author NiCK.n17 <nick.n17@gmail.com>
 * @copyright (c) 2018, NiCK.n17
 * @license https://github.com/nickn17/home/blob/master/LICENSE
 */

namespace Home;

/**
 * Application router
 */
class Application {

    /**
     * Run web app
     */
    function run() {

        $filePath = APPLICATION_PATH . "vendor/nickn17/home/src/Home";
        $moduleName = ucfirst(ltrim($_SERVER['REQUEST_URI'], "/")) . "Controller";
        $fileName = $filePath . "/" . $moduleName . ".php";
        if (file_exists($fileName)) {
            $moduleClass = '\\Home\\' . $moduleName;
            $module = new $moduleClass();
            $module->indexAction();
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "HTTP/1.0 404 Not Found";
        }
        
        exit();
    }
}
