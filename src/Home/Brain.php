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
 * Main cron service
 */
class Brain {

    /**
     * Constructor
     */
    function __construct() {

        $addr = "0.0.0.0";
        $port = 7777;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Failed: socket_create()");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
        socket_bind($this->master, $addr, $port) or die("Failed: socket_bind()");
        socket_listen($this->master, 20) or die("Failed: socket_listen()");
    }

    /**
     * Start AI
     */
    function start() {

        echo "HOME daemon started \n";

        // Dashboard
        $dashboard = array();

        $db = new \Home\Database();
        $db->connect();
        $rows = $db->fetchAll("SELECT room_id, name FROM `room` order by order_no");
        foreach ($rows as $row)
            $dashboard['room'][$row['room_id']] = $row;
        $rows = $db->fetchAll("SELECT bidirectional, room_from, room_to FROM room_link");
        foreach ($rows as $row) {
            $dashboard['room'][$row['room_from']]['linked_to'][$row['room_to']] = $row['room_to'];
            if ($row['bidirectional'] == 1)
                $dashboard['room'][$row['room_to']]['linked_to'][$row['room_from']] = $row['room_from'];
        }
        $db->disconnect();

        //exec("nohup /usr/bin/php -f scan_phue_bridge.php > /dev/null 2>&1 &");
        // Never ending loop
        while (true) {
            $sensors = SharedCache::getCache('homeDataPhilipsBridge');
            //SharedCache::removeCache('homeDataPhilipsBridge');
            $dashboard['sensors'] = $sensors['sensors'];
            $sensors = SharedCache::getCache('homeDataNetwork');
            //SharedCache::removeCache('homeDataNetwork');
            $dashboard['host'] = $sensors['host'];

            // Serialize dashboard
            $dashboard['dashboard'] = 1;
            $dashboard['rendered'] = date('Y-m-d H:i:s');
            \Home\SharedCache::saveCache('homeDashboard', $dashboard);

            // Tick
            time_sleep_until(microtime(true) + 1);
            echo ".";
        }
    }

}
