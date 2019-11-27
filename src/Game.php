<?php

namespace Mafia;

use Mike4ip\MongoSimple;

/**
 * Главный обработчик игровой логики
 * @package Mafia
 */
class Game
{
    /**
     * @var array
     */
    protected static $active_games = [];

    /**
     * @var MongoSimple
     */
    protected static $db;

    /**
     * Not used, just for mongo cursor
     * @see Game::load()
     * @var string
     */
    public $_id = 'null';

    /**
     * @var string
     */
    public $id = 'null';

    /**
     * @var int
     */
    public $peer = -1;

    /**
     * @var bool
     */
    public $active = false;

    /**
     * @var bool
     */
    public $init = false;

    /**
     * @var array
     */
    public $members = [];

    /**
     * @var bool
     */
    public $day = true;

    /**
     * @var array
     */
    public $checked = [];

    /**
     * @var array
     */
    public $killed = [];

    /**
     * @var array
     */
    public $votes = [];

    /**
     * @var array
     */
    public $heals = [];

    /**
     * @var array
     */
    public $roles = [];

    /**
     * @var bool
     */
    public $first_day = true;

    /**
     * @var array
     */
    public $warns = [];

    /**
     * @var int
     */
    public $change_time = -1;

    /**
     * @return MongoSimple
     */
    public static function db(): MongoSimple
    {
        if(!(self::$db instanceof MongoSimple))
            self::$db = new MongoSimple(MONGODB_URL, 'mafia_game');

        return self::$db;
    }

    /**
     * @param string $game_id
     * @return Game
     * @throws \Exception
     */
    public static function i(string $game_id): Game
    {
        if(isset(self::$active_games[$game_id]))
            return self::$active_games[$game_id];

        if(self::db()->count('games', ['_id' => $game_id])) {
            $game = self::load($game_id);
            self::$active_games[$game_id] = $game;
            print("Loaded game {$game_id}\n");
            return $game;
        }

        print("Created game {$game_id}\n");
        $game = new Game($game_id);
        self::$active_games[$game_id] = $game;
        return $game;
    }

    /**
     * @param int $uuid
     * @return Game
     * @throws \Exception
     * @todo multi-chat gaming
     */
    public static function getGameForPlayer(int $uuid): Game
    {
        foreach(self::$active_games as $game_id => $game)
            return $game;

        throw new \Exception("Game for {$uuid} not found");
    }

    /**
     * Game constructor.
     * @param string $game_id
     * @param bool $autosave
     */
    public function __construct(string $game_id, bool $autosave = true)
    {
        $this->id = $game_id;

        if($autosave == true)
            $this->save();
    }

    /**
     * @return array
     */
    public function countRoles(): array
    {
        $return = [];

        foreach($this->roles as $uuid => $role) {
            if(isset($return[$role]))
                $return[$role]++;
            else
                $return[$role] = 1;
        }

        return $return;
    }

    /**
     * @param int $uid
     * @return bool
     */
    public function warn(int $uid): bool
    {
        if(!isset($this->roles[$uid]))
            return false;

        if(isset($this->warns[$uid]))
            $this->warns[$uid]++;
        else
            $this->warns[$uid] = 1;

        return true;
    }

    /**
     * @param string $game_id
     * @return Game
     * @throws \Exception
     */
    public static function load(string $game_id): Game
    {
        if(!self::db()->count('games', ['_id' => $game_id]))
            throw new \Exception('No game '.$game_id.' in saved');

        $data = (array)self::db()->findOne('games', ['_id' => $game_id]);
        $game = new Game($game_id, false);

        foreach($data as $k => $v) {
            if(is_iterable($v) || $v instanceof \Traversable)
                $v = (array)$v;

            $game->{$k} = $v;
        }

        return $game;
    }

    public function save(): void
    {
        $to_save = (array)get_object_vars($this);
        $to_save['_id'] = $this->id;
        self::db()->upsert('games', ['_id' => $this->id], $to_save);
    }
}