#!/usr/bin/php
<?php
    /*
        Copyright (C) 2019, Boris Stelmakh

        This program is free software: you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation, either version 3 of the License, or
        (at your option) any later version.

        This program is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with this program.  If not, see <https://www.gnu.org/licenses/>.
    */

    $processes = exec('pgrep -c run-mafia.php');

    if(!is_numeric($processes))
        exit('Bad number ' . var_export($processes, true));

    if((int)$processes >= 2)
        exit('Script is already running' . PHP_EOL);

    require_once(__DIR__ . '/vendor/autoload.php');

    define('ROOT', __DIR__);
    define('EXEC_MAIN_FILE', __FILE__);

    require_once(__DIR__ . '/config.php');

    try {
        Mafia\MainHandler::run();
    } catch (Exception $e) {
        Mafia\MainHandler::getVK()->messages()->send(VK_TOKEN, ['user_id' => ADMIN_ID, 'message' => (string)$e, 'random_id' => time()]);
    }

    register_shutdown_function(function() {
        \Mafia\MainHandler::restart();
    });
