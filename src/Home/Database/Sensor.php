<?php

/**
 * Home automation engine
 *
 * @author NiCK.n17 <nick.n17@gmail.com>
 * @copyright (c) 2018, NiCK.n17
 * @license https://github.com/nickn17/home/blob/master/LICENSE
 */

namespace Home\Database;

/**
 * Sensor in Db
 */
class Sensor {

    private $db;
    private $parentDeviceID;
    private $sensors;
    private $sensorsUUIDTable;

    /**
     * Constructor
     */
    function __construct($device_id) {

        $this->parentDeviceID = $device_id;

        // Connect to db
        $this->db = new \Home\Database();
        $this->db->connect();

        $rows = $this->db->fetchAll("Select sensor_id, device_id, room_id, type, uuid, name, value, value_changed from sensor where device_id = :device_id", array("device_id" => $device_id));
        $this->sensors = array();
        $this->sensorsUUIDTable = array();
        foreach ($rows as $row) {
            $this->sensors[$row['sensor_id']] = $row;
            $this->sensorsUUIDTable[$row['uuid']] = $row['sensor_id'];
        }
    }

    /**
     * Update sensor data
     */
    function update($params) {

        $sensor_id = null;
        if (isset($this->sensorsUUIDTable[$params['uuid']])) {
            $sensor_id = $this->sensorsUUIDTable[$params['uuid']];
        }

        $sqlParams = array(
            "device_id" => $this->parentDeviceID,
            "uuid" => $params['uuid'],
            "type" => $params['type'],
            "value" => $params['value'],
            "value_changed" => $params['modified']);

        if (empty($sensor_id)) {
            // Insert
            $sqlParams['name'] = $params['name'];
            $this->db->query("insert into sensor(device_id, uuid, type, name, value, value_changed)
                    values (:device_id, :uuid, :type, :name, :value, :value_changed)", $sqlParams);
            $sensor_id = $this->db->getLastInsertID();
            $sqlParams['sensor_id'] = $sensor_id;
            $this->sensors[intval($sensor_id)] = $sqlParams;
            $this->sensorsUUIDTable[$sqlParams['uuid']] = intval($sensor_id);
        } else {
            // Update
            $sensor = &$this->sensors[$sensor_id];
            if (($sensor['value'] != $sqlParams['value'] || (strtotime($sqlParams['value_changed']) - strtotime($sensor['value_changed'])) > 900)) {
                $sqlParams['sensor_id'] = $sensor['sensor_id'];
                $this->db->query("update sensor set
                        device_id = :device_id, uuid = :uuid, type = :type, value = :value, value_changed = :value_changed, modified = CURRENT_TIMESTAMP
                        where sensor_id = :sensor_id", $sqlParams);

                // Write history
                $this->db->query("insert into sensor_history(sensor_id, duration, value, created)
                      values(:sensor_id, :duration, :value, :created) ON DUPLICATE KEY UPDATE
                      duration = :duration, value = :value", array("sensor_id" => $sensor['sensor_id'],
                    "duration" => (strtotime($sqlParams['value_changed']) - strtotime($sensor['value_changed'])),
                    "value" => $sensor['value'], "created" => $sensor['value_changed']));

                $sensor['value_changed'] = $sqlParams['value_changed'];
                $sensor['value'] = $sqlParams['value'];
            }
        }
    }

    /**
     * Get all sensors data
     */
    function getData() {
        return $this->sensors;
    }

}
