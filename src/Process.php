<?php

namespace Mafia;

/**
 * Работа с игровым процессом, чтобы не надо было ручками
 * @package Mafia
 */
class Process
{
    /**
     * Перезапустить процесс игры в режиме nohup
     */
    public static function restart(): void
    {
        exec("nohup ".EXEC_MAIN_FILE." > /dev/null 2>&1 &");
        exit;
    }
}