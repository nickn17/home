<?php

/**
 * Home automation engine
 *
 * @author NiCK.n17 <nick.n17@gmail.com>
 * @copyright (c) 2018, NiCK.n17
 * @license https://github.com/nickn17/home/blob/master/LICENSE
 */

namespace Home\Scan;

use SHMCache\Cache;

/**
 * Network scan
 */
class Network {

    /**
     * Scan network segnemt
     */
    function scanSegment($networkId, $segment) {

        // Connect to db
        $db = new \Home\Database();
        $db->connect();

        // Existing records
        $rows = $db->fetchAll("select device_id, mac_addr, ip_addr, alive, alive_changed, response, now() as now from device where network_id = :network_id", array("network_id" => $networkId));
        $devices['byMAC'] = array();
        $devices['byIP'] = array();
        $devices['byID'] = array();
        $devicesByIP = array();
        $lastTimestamp = date("Y-m-d H:i:s");
        foreach ($rows as $row) {
            if ($row['mac_addr'] !== "")
                $devices['byMAC'][$row['mac_addr']] = $row;
            if ($row['ip_addr'] !== "")
                $devices['byIP'][$row['ip_addr']] = $row;
            $devices['byID'][$row['device_id']] = $row;
            $lastTimestamp = $row['now'];
        }

        $struct['host'] = array();
        /*        exec('nmap -n -sn ' . $segment . ' --disable-arp-ping', $output, $returnVal);
          $lastAddr = "";
          foreach ($output as $row) {
          if (strpos($row, "Nmap scan report for") !== false)
          $lastAddr = trim(substr($row, strlen("Nmap scan report for")));
          if (strpos($row, "Host is up") !== false) {
          $response = substr($row, 12);
          $response = substr($response, 0, strpos($response, ' '));
          $struct['host'][$lastAddr]['alive'] = 1;
          $struct['host'][$lastAddr]['response'] = round($response * 1000);
          }
          } */

        exec('fping -agqe ' . $segment, $output, $returnVal);
        $lastAddr = "";
        foreach ($output as $row) {
            $addr = substr($row, 0, strpos($row, " "));
            $response = substr($row, strpos($row, "(") + 1);
            $response = substr($response, 0, strpos($response, ' '));
            $struct['host'][$addr]['alive'] = 1;
            $struct['host'][$addr]['response'] = round($response);
        }

        exec('arp -n -a', $output, $returnVal);
        foreach ($output as $row) {
            if (strpos($row, "incomplete") !== false)
                continue;
            $ele = explode(' ', $row);
            if (count($ele) == 7) {
                $ip = substr($ele[1], 1, -1);
                if (isset($struct['host'][$ip]))
                    $struct['host'][$ip]['mac'] = $ele[3];
            }
        }

        // Save data do db
        foreach ($struct['host'] as $ip => $item) {
            $device_id = null;
            if (!isset($item['mac']))
                $item['mac'] = "";
            if (isset($devices['byMAC'][$item['mac']]))
                $device_id = $devices['byMAC'][$item['mac']]['device_id'];
            else
            if (isset($devices['byIP'][$ip]))
                $device_id = $devices['byIP'][$ip]['device_id'];
            $sqlParams = array("network_id" => $networkId, "mac_addr" => $item['mac'], "ip_addr" => $ip, "alive" => $item['alive'], "response" => $item['response']);
            if (empty($device_id)) {
                // Insert
                $db->query("insert into device(network_id, mac_addr, ip_addr, alive, response) values (:network_id, :mac_addr, :ip_addr, :alive, :response)", $sqlParams);
            } else {
                // Update
                $device = $devices['byID'][$device_id];
                $alive_changed = ($device['alive'] != $item['alive'] ||
                        (strtotime($lastTimestamp) - strtotime($device['alive_changed'])) > 900);
                $sqlParams['alive_changed'] = ($alive_changed ? $lastTimestamp : $device['alive_changed']);
                $sqlParams['device_id'] = $device['device_id'];
                print_r($sqlParams);
                $db->query("update device set network_id = :network_id, ip_addr = :ip_addr, alive = :alive, response = :response, alive_changed = :alive_changed, mac_addr = :mac_addr, modified = CURRENT_TIMESTAMP where device_id = :device_id limit 1", $sqlParams);

                // Write history
                $db->query("insert into device_history(device_id, duration, alive, response, created)
                  values(:device_id, :duration, :alive, :response, :created) ON DUPLICATE KEY UPDATE
                  duration = :duration, alive = :alive, response = :response", array("device_id" => $device['device_id'], "duration" => (strtotime($lastTimestamp) - strtotime($device['alive_changed'])),
                  "alive" => $device['alive'], "response" => $device['response'], "created" => $device['alive_changed']));
            }
        }

        // Set all other in network_id as inactive
        $db->query("update device set alive = 0 where modified < :lastTimestamp and network_id = :network_id", array('lastTimestamp' => $lastTimestamp, "network_id" => $networkId));

        \Home\SharedCache::saveCache('homeDataNetwork', $struct);
    }

}
