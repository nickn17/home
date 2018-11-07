<?php

/**
 * Home automation engine
 *
 * @author NiCK.n17 <nick.n17@gmail.com>
 * @copyright (c) 2018, NiCK.n17
 * @license https://github.com/nickn17/home/blob/master/LICENSE
 */

namespace Home;

use PDO;

/**
 * Db library
 */
class Database {

    protected $hasActiveTransaction = 0;
    private $pdo;
    private $tables;
    public $connected = false;
    public $result;
    public $bufferedQueries = false; // Default, note unbuffered does not support count and seeking results

    /**
     * Connect to database
     */
    function connect($dsn = "", $user = "", $password = "") {
        global $site;
        $socket = "";
        if ($dsn == "") {
            if (defined('DB_SOCKET') && DB_SOCKET != "") {
                $socket = "unix_socket=" . DB_SOCKET . ";";
            }
            $host = "";
            if (DB_HOST != "") {
                $host = "host=" . DB_HOST . ";";
            }
            if (defined('DB_PORT') && DB_PORT != "") {
                $host .= "port=" . DB_PORT . ";";
            }
            $dsn = "mysql:${host}${socket}dbname=" . DB_NAME;
        }
        if ($user == "") {
            $user = DB_USER;
        }
        if ($password == "") {
            $password = DB_PASSWORD;
        }
        try {
            $this->pdo = @new PDO($dsn, $user, $password, array(PDO::ATTR_PERSISTENT => true, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            $this->connected = true;
            if (!$this->bufferedQueries) {
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            }
        } catch (PDOException $ex) {
            $this->connected = false;
            die("<!DOCTYPE html><html lang='sk'><head><meta charset='UTF-8'><style type='text/css'> body { font-family: verdana; background: gray; color: white; } h2 { margin-top: 0px; } #Frame { border: 2px solid white; border-radius: 16px; margin:128px; padding: 32px; } </style></head><body><div id='Frame'><h1>Údržba $site[FullDomain]</h1>Momentálne prebieha údržba databázy servera. Skúste nás navštíviť neskôr.<p>Admin $site[AdminMail] $site[AdminPhone]</p></div></body></html>");
        }
    }

    /**
     * Disconnect from database
     */
    function disconnect() {
        if ($this->connected) {
            $this->pdo = null;
            $this->connected = false;
        }
    }

    /**
     * Execute query = prepare + execute
     */
    function query($query, $parameters = null) {
        global $dbTablePrefix, $site;
        $this->result = false;
        if ($this->connected) {
            $query = str_replace("{*}", $dbTablePrefix, $query);
            $this->result = $this->pdo->prepare($query);
            $this->result->execute($parameters);
            if ($this->result->errorCode() != 0) {
                $err = $this->result->errorInfo();
                print_r($err);
                echo $query;
                die('Invalid query: ' . $this->result->errorCode()); //, ' . $err[2], "QUERY: " . $query);
            }
        }
        return $this->result;
    }

    /**
     * Query and fetch result to array
     */
    function fetchAll($query, $parameters = array()) {

        $stmt = $this->query($query, $parameters);
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $arr;
    }

    /**
     * Query and fetch one row
     */
    function fetchRow($query, $parameters = array()) {

        global $dbTablePrefix, $site;
        if ($this->connected) {
            $query = str_replace("{*}", $dbTablePrefix, $query);
            $this->result = $this->pdo->prepare($query);
            $this->result->execute($parameters);
            if ($this->result->errorCode() != 0) {
                $err = $this->result->errorInfo();
                die('Invalid query: ' . $this->result->errorCode()); //, ' . $err[2], "QUERY: " . $query);
            }
            $array = $this->result->fetch(PDO::FETCH_ASSOC);
            $this->result->closeCursor();
            return $array;
        }
    }

    /**
     * Check if transaction is active
     */
    function hasActiveTransaction() {
        return $this->hasActiveTransaction > 0;
    }

    /**
     * Begin transaction block
     */
    function begin() {
        if ($this->connected) {
            if ($this->hasActiveTransaction == 0) {
                $this->pdo->beginTransaction();
                $this->hasActiveTransaction++;
            }
            return true;
        }
    }

    /**
     * Commit transaction
     */
    function commit() {
        if ($this->connected) {
            if ($this->hasActiveTransaction > 0) {
                $this->pdo->commit();
            }
            $this->hasActiveTransaction--;
        }
    }

    /**
     * Rollback transaction
     */
    function Rollback() {
        if ($this->connected) {
            $this->pdo->rollback();
            $this->hasActiveTransaction = false;
        }
    }

    /**
     * Get last generated ID over autoincrement
     */
    function getLastInsertID() {
        if ($this->connected) {
            return $this->pdo->lastInsertId();
        } else
            return false;
    }

}
