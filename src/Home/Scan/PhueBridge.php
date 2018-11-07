<?php

/**
 * Home automation engine
 *
 * @author NiCK.n17 <nick.n17@gmail.com>
 * @copyright (c) 2018, NiCK.n17
 * @license https://github.com/nickn17/home/blob/master/LICENSE
 */

namespace Home\Scan;

use Home\Database\Sensor;

/**
 * Phue Bridge scan
 */
class PhueBridge {

    const TYPE_UNKNOWN = '-';
    const TYPE_LIGHT = 'T';
    const TYPE_MOTION = 'M';
    const TYPE_TEMPERATURE = 'L';
    const TYPE_LIGHT_LEVEL = 'B';
    const TYPE_SWITCH = 'S';

    private $bridgeIP;
    private $bridgeToken;

    /**
     * Scan bridge sensors
     */
    function scan($ip, $token) {

        // Sensors
        $this->bridgeIP = $ip;
        $this->bridgeDeviceID = 6;
        $this->bridgeToken = $token;
        $data = array();

        // Fill last state
        $this->sensors = new Sensor($this->bridgeDeviceID);
        foreach ($this->sensors->getData() as $row) {
            $data['sensors'][$row['uuid']] = array(
                "name" => $row['name'],
                "uuid" => $row['uuid'],
                "room_id" => $row['room_id'],
                "type" => $row['type'],
                "value" => intval($row['value']),
                "modified" => $row['value_changed']
            );
        }
        if (!isset($data['sensors']['NOBODY_AT_HOME'])) {
            $data['sensors']['NOBODY_AT_HOME'] = array(
                "name" => 'Nobody at home', "uuid" => 'NOBODY_AT_HOME',
                "type" => 'F', "value" => 0, "prepare_value" => 0, "modified" => "");
        }

        // Never ending loop
        while (1) {

            // Get sensors data
            $newData = $this->getData();
            foreach ($newData['sensors'] as $uuid => $params) {
                if (!isset($data['sensors'][$uuid])) {
                    $data['sensors'][$uuid] = $params;
                }

                $current = &$data['sensors'][$uuid];
                if ($current["type"] == PhueBridge::TYPE_MOTION) {
                    if (!$current['value'] && $params['value']) {
                        $current['tmp_first_move'] = $current['modified'] = date('Y-m-d H:i:s');
                        $current['idle'] = 0;
                        $data['sensors']['NOBODY_AT_HOME']['prepare_value'] = 0;
                        $data['sensors']['NOBODY_AT_HOME']['modified'] = date('Y-m-d H:i:s');
                    } else
                    if ($current['value'] && !$params['value']) {
                        $current['modified'] = date('Y-m-d H:i:s', time() - 11);
                        $data['sensors']['NOBODY_AT_HOME']['prepare_value'] = (strpos($params['name'], 'Entryway') !== false ? 1 : 0);
                        $data['sensors']['NOBODY_AT_HOME']['modified'] = date('Y-m-d H:i:s');
                    } else
                    if ($current['value'] && $params['value']) {
                        if ($current['modified'] < date('Y-m-d H:i:s', time() - 11)) {
                            $current['modified'] = date('Y-m-d H:i:s');
                        }
                    } else if (!$current['value']) {
                        $current['idle'] = time() - strtotime($current['modified']);
                    }
                } else {
                    if ($current['value'] != $params['value']) {
                        $current['modified'] = date('Y-m-d H:i:s', time() - 11);
                    }
                }

                $current["value"] = $params["value"];
                $this->sensors->update($current);
            }

            // Nobody at home detection
            if (isset($data['sensors']['NOBODY_AT_HOME']['prepare_value'])) {
                if (!$data['sensors']['NOBODY_AT_HOME']['value'] && $data['sensors']['NOBODY_AT_HOME']['prepare_value'] &&
                        (time() - strtotime($data['sensors']['NOBODY_AT_HOME']['modified'])) > 60) {
                    $data['sensors']['NOBODY_AT_HOME']['value'] = $data['sensors']['NOBODY_AT_HOME']['prepare_value'];
                    $this->sensors->update($data['sensors']['NOBODY_AT_HOME']);
                } else
                if ($data['sensors']['NOBODY_AT_HOME']['value'] && !$data['sensors']['NOBODY_AT_HOME']['prepare_value']) {
                    $data['sensors']['NOBODY_AT_HOME']['value'] = $data['sensors']['NOBODY_AT_HOME']['prepare_value'];
                    $this->sensors->update($data['sensors']['NOBODY_AT_HOME']);
                }
            }

            // Transfer to home brain
            $data['rendered'] = date('Y-m-d H:i:s');
            \Home\SharedCache::saveCache('homeDataPhilipsBridge', $data);

            // Tick
            time_sleep_until(microtime(true) + 0.5);
            echo ".";
        }
    }

    /**
     * Get data from bridge
     */
    private function getData() {

        $client = new \Phue\Client($this->bridgeIP, $this->bridgeToken);
        $struct['sensors'] = array();
        $modified = date("Y-m-d H:i:s");

        foreach ($client->getLights() as $light) {

            $uuid = $light->getUniqueId();
            $struct['sensors'][$uuid] = array(
                "uuid" => $uuid,
                "type" => PhueBridge::TYPE_LIGHT,
                "name" => $light->getName() . " " . $light->getModel()->getName(),
                "value" => ($light->isOn() ? 1 : 0),
                "modified" => $modified
            );
        }

        foreach ($client->getSensors() as $sensor) {

            // Skip sensors without uuid
            $uuid = $sensor->getUniqueId();
            if ($uuid == "" || strpos($uuid, ":") === false || strpos($uuid, "-") === false)
                continue;

            $type = $sensor->getType();
            $struct['sensors'][$uuid] = array(
                "uuid" => $uuid,
                "name" => $sensor->getName(),
                "modified" => $modified
            );

            foreach ($sensor->getState() as $key => $value) {
                if ($type == 'ZLLSwitch' && $key == "buttonevent") {
                    $struct['sensors'][$uuid]["type"] = PhueBridge::TYPE_SWITCH;
                    $struct['sensors'][$uuid]["value"] = $value;
                }
                if ($type == 'ZLLPresence' && $key == "presence") {
                    $struct['sensors'][$uuid]["type"] = PhueBridge::TYPE_MOTION;
                    $struct['sensors'][$uuid]["value"] = ($value ? 1 : 0);
                }
                if ($type == 'ZLLTemperature' && $key == "temperature") {
                    $struct['sensors'][$uuid]["type"] = PhueBridge::TYPE_TEMPERATURE;
                    $struct['sensors'][$uuid]["value"] = $value;
                }
                if ($type == 'ZLLLightLevel' && $key == "lightlevel") {
                    $struct['sensors'][$uuid]["type"] = PhueBridge::TYPE_LIGHT_LEVEL;
                    $struct['sensors'][$uuid]["value"] = $value;
                }
            }
        }

        return $struct;
    }

}
