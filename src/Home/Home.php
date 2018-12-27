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
class Home {

    /**
     * Start AI
     */
    function start() {

        echo "HOME engine started \n";

        $server = new \vakata\websocket\Server('ws://0.0.0.0:22222');
        $server->onMessage(function ($sender, $message, $server) {
            echo $message."\n";
            /*foreach ($server->getClients() as $client) {
                if ((int) $sender['socket'] !== (int) $client['socket']) {
                    $server->send($client['socket'], $message);
                }
            }*/
        });
        
        $server->run();
    }

}
