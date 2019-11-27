<?php

namespace Mafia;

/**
 * Языковые строки
 * @todo Смысл этого класса :}
 * @package Mafia
 */
class Strings
{
    /**
     * Список строк и переводов
     * @var array
     */
    static $table = [
        'civ' => 'гражданский',
        'maf' => 'мафиози',
        'cop' => 'офицер',
        'doc' => 'доктор'
    ];

    /**
     * Получить перевод строки
     * @param string $var
     * @return string
     */
    public static function get(string $var): string
    {
        return isset(self::$table[$var]) ? self::$table[$var] : $var;
    }
}