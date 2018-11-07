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
 * Web panel
 */
class DashboardController {

    /**
     * Show dashboard panel
     */
    function indexAction() {

        // Connect to db
        $db = new \Home\Database();
        $db->connect();

        $dashboard = \Home\SharedCache::getCache('homeDashboard');
        //print_r($dashboard);

        echo "<html>
    <head>
        <meta http-equiv='refresh' content='1' />
    </head>
    <body>";
        echo $dashboard['rendered'] . "<br>";

        // Find neighbour motion a calculate last state
        foreach ($dashboard['sensors'] as &$sensor) {
            if ($sensor['type'] != "M" || !isset($sensor['room_id']) || $sensor['value'] != 0)
                continue;
            if (!isset($dashboard['room'][$sensor['room_id']]) || !isset($dashboard['room'][$sensor['room_id']]['linked_to']))
                continue;
            $neighbourMove = false;
            foreach ($dashboard['sensors'] as $neighbourSensor) {
                if ($neighbourSensor['type'] != "M")
                    continue;
                foreach ($dashboard['room'][$sensor['room_id']]['linked_to'] as $room_to) {
                    if (isset($neighbourSensor['room_id']) && $neighbourSensor['room_id'] == $room_to && $neighbourSensor['modified'] >= $sensor['modified']) {
                        $neighbourMove = true;
                    }
                }
            }
            if (!$neighbourMove)
                $sensor['value'] = 2;
        }

        echo "<ul>";
        foreach ($dashboard['sensors'] as $sensor) {
            if ($sensor['type'] == "M" || $sensor['type'] == "F")
                echo "<li style='" . ($sensor['value'] ? "font-weight: bold; color: " . ($sensor['value'] == 2 ? "orange" : "green" ) . ";" : "") . "'>" . $sensor['name'] . " " . $sensor['value'] . " | <strong>" . (isset($sensor['idle']) ? floor($sensor['idle'] / 60) . "min." : "") . "</strong> | " . substr($sensor['modified'], 10) . "</li>";
        }
        echo "</ul>";

        // Network
        $rows = $db->fetchAll("select name, ip_addr, alive from device order by ip_addr");
        echo "<ul>";
        foreach ($rows as $row) {
            echo "<li style='color: " . ($row['alive'] ? "green" : "red") . "'>$row[name] $row[ip_addr]</li>";
        }
        echo "</ul>";

        echo "</body></html>";
    }

}
