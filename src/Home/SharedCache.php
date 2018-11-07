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
 * Interprocess shared cache - via files
 */
class SharedCache {

    /**
     * Get data from cache
     */
    static function getCache($key) {

        return json_decode(file_get_contents(APPLICATION_PATH . 'tmp/' . $key), true);
        /* $fp = @fopen('/tmp/' . $key, "r");
          if (empty($fp))
          return null;
          $data = fread($fp, filesize('/tmp/' . $key));
          fclose($fp);
          return json_decode($data, true); */
    }

    /**
     * Write data to cache
     */
    static function saveCache($key, $data) {

        $fp = fopen(APPLICATION_PATH . 'tmp/' . $key, 'w');
        fwrite($fp, json_encode($data));
        fclose($fp);
    }

    /**
     * Remove cache
     */
    static function removeCache($key) {

        @unlink(APPLICATION_PATH . 'tmp/' . $key);
    }

}
