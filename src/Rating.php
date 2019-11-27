<?php

namespace Mafia;

use MongoDB\Driver\Cursor;

/**
 * Рейтинг победителей
 * @package Mafia
 */
class Rating
{
    /**
     * @param array $ids_list
     * @return int
     */
    public static function applyWinners(array $ids_list): int
    {
        $applied = 0;

        foreach($ids_list as $id) {
            if(!is_numeric($id))
                continue;

            $find = Game::db()->findOne('rating', ['uid' => $id]);

            if(is_object($find)) {
                $find = (array)$find;
                $find['wins'] = isset($find['wins']) ? $find['wins']+1 : 1;
                Game::db()->upsert('rating', ['uid' => $id], $find);
                $applied++;
            } else {
                Game::db()->insert('rating', ['uid' => $id, 'wins' => 1]);
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * @return Cursor
     */
    public static function getScoreTable(): Cursor
    {
        return Game::db()->find('rating', ['wins' => ['$gte' => 1]], ['sort' => ['wins' => -1]]);
    }

    /**
     * @param int $idx
     * @return string
     */
    public static function getPlaceBadge(int $idx): string
    {
        switch($idx)
        {
            case 1:
               return '1️⃣';
            case 2:
               return '2️⃣';
            case 3:
               return '3️⃣';
            case '4':
                return '4️⃣';
            case 5:
                return '5️⃣';
            case 6:
                return '6️⃣';
            case 7:
                return '7️⃣';
            case 8:
                return '8️⃣';
            case 9:
                return '9️⃣';
            case 10:
                return '🔟';
            default:
               return $idx;
        }
    }
}