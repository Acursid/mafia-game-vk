<?php

namespace Mafia;

use VK\CallbackApi\LongPoll\VKCallbackApiLongPollExecutor;
use VK\CallbackApi\VKCallbackApiHandler;
use VK\Client\Enums\VKLanguage;
use VK\Client\VKApiClient;

class MainHandler extends VKCallbackApiHandler
{
    /**
     * @var VKApiClient
     */
    protected static $vk_api;

    /**
     * Запускает все нужные обработчики и слушает long polling
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     * @throws \VK\CallbackApi\LongPoll\Exceptions\VKLongPollServerTsException
     */
    public static function run(): void
    {
        $vk = self::getVK();
        $handler = new MainHandler();
        $executor = new VKCallbackApiLongPollExecutor($vk, VK_TOKEN, GROUP_ID, $handler, 10);

        while(true) {
            $executor->listen();
            $handler->handleGameMessage(['ping' => true], GROUP_ID);
        }
    }

    /**
     * Обработчик новых сообщений. Определяет, чем его обрабатывать
     * @param int $gid
     * @param string|null $secret
     * @param array $object
     * @throws \Exception
     */
    public function messageNew(int $gid = -1, string $secret = null, array $object = []): void
    {
        if($gid !== GROUP_ID)
            return;

        if($object['out'] == 1)
            return;
        elseif($object['peer_id'] === GAME_CHAT) {
            $this->handleGameMessage($object, $gid);
            return;
        } elseif($object['peer_id'] < 2000000000) {
            $this->handlePM($object);
            return;
        } else
            return;
    }

    /**
     * Обработчик лички сообщества
     * @param array $message
     * @throws \Exception
     */
    public function handlePM(array $message)
    {
        $from = $message['from_id'];
        $text = $message['text'];
        $cmd = explode(' ', $text);
        $game = Game::getGameForPlayer($from);

        switch(mb_strtolower($cmd[0]))
        {
            case 'проверить':
                if($game->active != true || $game->day != false || $game->roles[$from] !== 'cop' || isset($game->checked[$from]))
                    return;

                if(isset($message['fwd_messages']) && isset($message['fwd_messages'][0])) {
                    $fwd = $message['fwd_messages'][0];
                    $uuid = $fwd['from_id'];

                    if(!isset($game->roles[$uuid])) {
                        $this->answer($message, 'Этот персонаж с вами не играет');
                        return;
                    }

                    if(random_int(0, 5) == 2) {
                        $this->answer($message, '😔 Проверка провалилась');
                        $this->broadcast($game->peer, '🚔 Один из граждан ловко избежал проверки от полиции');
                    } else {
                        $uinfo = static::getUserInfo($uuid);
                        $this->answer($message, '🔦 ' . $uinfo['first_name'] . ' - ' . Strings::get($game->roles[$uuid]));
                        $this->broadcast($uuid, '🚔 Полиция заехала к вам с проверкой!');
                        $this->broadcast($game->peer, '🚔 Полиция наведалась к одному из граждан');
                        $game->checked[$from] = true;
                    }
                } else {
                    $this->answer($message, 'Перешли сообщение того, кого хочешь проверить (и тоже напиши команду `проверить`)');
                }
                return;

            case 'рестарт':
                if($from != ADMIN_ID)
                    return;

                $game->save();
                $this->answer($message, '💾 Игра сохранена. Перезапускаюсь...');
                static::restart();
                return;

            case 'лечить':
                if($game->active != true || $game->day != false || $game->roles[$from] !== 'doc' || isset($game->heals[$from]))
                    return;

                if(isset($message['fwd_messages']) && isset($message['fwd_messages'][0])) {
                    $fwd = $message['fwd_messages'][0];
                    $uuid = $fwd['from_id'];

                    if($from === $uuid) {
                        $this->answer($message, 'Самолечение может быть вредно для вашего здоровья (С)');
                        return;
                    }

                    if(!isset($game->roles[$uuid])) {
                        $this->answer($message, 'Этот персонаж с вами не играет');
                        return;
                    }

                    $uinfo = static::getUserInfo($uuid);
                    $this->answer($message, '🚑 ' . $uinfo['first_name'] . ' будет вылечен');
                    $this->broadcast($uuid, '🚑 Доктор посетил вас. Ничего не болит? 😏');
                    $this->broadcast($game->peer, '🚑 Доктор выехал на вызов');
                    $game->heals[$from] = $uuid;
                } else {
                    $this->answer($message, 'Перешли сообщение того, кого хочешь вылечить (и тоже напиши команду `лечить`)');
                }
                return;

            case 'убить':
                if($game->active != true || $game->day != false || $game->roles[$from] !== 'maf')
                    return;

                if(isset($message['fwd_messages']) && isset($message['fwd_messages'][0])) {
                    $fwd = $message['fwd_messages'][0];
                    $kill_id = $fwd['from_id'];

                    if(!isset($game->roles[$kill_id])) {
                        $this->answer($message, 'Этот персонаж с вами не играет');
                        return;
                    }

                    if(random_int(0, 5) == 2) {
                        $this->answer($message, '😔 Убийство провалилось');
                        $this->broadcast($game->peer, '🚀 Один из граждан чудом избежал смерти от руки мафиози!');
                    } else {
                        $kill_info = static::getUserInfo($kill_id);
                        $this->answer($message, $kill_info['first_name'] . ' труп 😌');
                        $this->broadcast($game->peer, '🔫 Мафиози выбрал жертву. Кто-то не доживёт до утра...');
                        $game->killed[$from] = $kill_id;
                    }
                } else {
                    $this->answer($message, 'Перешли сообщение того, кого хочешь убить (и тоже напиши команду `убить`)');
                }
                return;

            case 'роль':
                if($game->active != true || !isset($game->roles[$from]))
                    return;

                $this->answer($message, '🤫 Ты -- ' . Strings::get($game->roles[$from]));
                $this->broadcast($game->peer, '⛪️ Неизвестный сегодня исповедовался в церкви');
                return;

            case 'суицид':
                if($game->active != true || !isset($game->roles[$from]))
                    return;

                $role = Strings::get($game->roles[$from]);
                $label = self::getUserLabel($from, true);
                unset($game->roles[$from]);

                $this->broadcast($game->peer, "🛀 {$label} ({$role}) накладывает на себя руки...");
                return;

            default:
                if(mb_strtolower($cmd[0]) === 'начать') {
                    $this->answer($message, "✌🏻 Привет!\n\nПока игра тестируется в закрытом режиме. Ожидай!");
                    return;
                }

                if(!isset($game->roles[$from]))
                    return;

                if($game->day == false && $game->roles[$from] === 'maf') {
                    $this->answer($message, 'Перешли сообщение того, кого хочешь убить, и напиши команду `убить` 😏');
                    return;
                }

                $instructions = "🧸 Привет! В личных сообщениях со мной работают такие команды:\n\n";
                $instructions .= "🔪 `убить` - убивает игрока. Только для мафии и только ночью\n";
                $instructions .= "🛡 `проверить` - узнать роль. Только для офицера и только ночью\n";
                $instructions .= "💉 `суицид` - выйти из игры\n";
                $instructions .= "❓ `роль` - напомнить, кто ты такой\n";
                $instructions .= "💊 `лечить` - вылечить игрока. Только для доктора и только ночью";
                $this->answer($message, $instructions);
            return;
        }
    }

    /**
     * Делает полный рестарт процесса с nohup
     */
    public static function restart(): void
    {
        exec("nohup ".EXEC_MAIN_FILE." > /dev/null 2>&1 &");
        exit;
    }

    /**
     * Обработчик игровых сообщений или ping-сигнала. Самый главный код здесь
     * @param array $message
     * @param int $gid
     * @throws \Exception
     */
    public function handleGameMessage(array $message, int $gid)
    {
        // если это просто сигнал ping - делаем вид, что это сообщение от никого
        if(count($message) <= 1)
            $message = ['peer_id' => GAME_CHAT, 'from_id' => -1, 'text' => ''];

        $is_ping = isset($message['ping']);
        $skip_warn = false;
        $peer = $message['peer_id'];
        $from = (int)$message['from_id'];
        $text = $message['text'];
        $game = Game::i($gid);
        //$cmd = explode(' ', $text);

        if($game->peer == -1)
            $game->peer = $peer;

        $game->save(); // сохраняем игру в базу или файл

        // если админ хочет сменить время суток
        if($text === 'день' && $from == ADMIN_ID) {
            $game->change_time = 0;
        }

        // админ хочет прервать игру
        if($text === 'аборт' && $from == ADMIN_ID) {
            $game->active = false;
            $game->init = false;
            $game->roles = [];
            $this->broadcast($game->peer, 'Игра прервана, данные очищены 🧹');
            return;
        }

        // если игра полностью активна
        if($game->active == true)
        {
            // надо менять время суток?
            if($game->change_time < time()) {
                $game->first_day = false;
                $new_day = !$game->day;
                $game->day = $new_day;

                if($new_day == true) {
                    $send_text = "⏰ Доброе утро!\n\n";

                    if(!count($game->killed)) {
                        $send_text .= "Хорошие новости -- ночью все остались живы! Однако не время расслабляться!\n";
                        $send_text .= "Перед нами важная задача -- выяснить, кто же здесь мафиози.\n";
                    } else {
                        $votes = array_values((array)$game->killed);
                        $values = array_count_values($votes);
                        arsort($values);
                        $killed = array_keys($values)[0];
                        $label = static::getUserLabel($killed, true);
                        $survive = false;

                        foreach($game->heals as $doctor => $patient) {
                            if ($patient == $killed)
                                $survive = true;
                        }

                        if($survive == true) {
                            $send_text .= "👨‍⚕️ Стараниями доктора чудом выжил {$label}, которого чуть не убила мафия!\n";
                            $send_text .= "Нужно побыстрее узнать, кто же здесь мафиози.\n";
                        } else {
                            $role = Strings::get($game->roles[$killed]);
                            $send_text .= "⚰️ Этой ночью зверски убили {$label} ({$role})\n";
                            unset($game->roles[$killed]);
                            $send_text .= "Теперь жизненно важно выяснить, кто же здесь мафиози.\n";
                        }
                    }

                    $game->killed = [];
                    $game->checked = [];
                    $game->heals = [];
                    $send_text .= "Общайтесь с другими игроками и высказывайте догадки\n\n";
                    $send_text .= "Когда будете готовы проголосовать, перешлите/ответьте на сообщение подозреваемого ";
                    $send_text .= 'и введите команду `голос`. Только не сглазьте!';
                    $game->change_time = time() + 120;
                } else {
                    if(count($game->votes)) {
                        $votes = array_values((array)$game->votes);
                        $values = array_count_values($votes);
                        arsort($values);

                        if(isset(array_keys($values)[0]) && array_values($values)[0] > 1) {
                            $popular = array_keys($values)[0];

                            if(isset($game->roles[$popular])) {
                                $label = static::getUserLabel($popular, true);
                                $role = $game->roles[$popular];
                                $role_text = Strings::get($role);
                                $txt = "⚖️ В результате самосуда казнён {$label} ({$role_text}). ";

                                if ($role === 'maf')
                                    $txt .= 'Ещё на один шаг ближе к чистому городу 🛡';
                                else
                                    $txt .= 'В этот раз мы ошиблись. Следует быть аккуратнее.';

                                $this->answer($message, $txt);
                                unset($game->roles[$popular]);
                            }
                        }
                    }

                    $game->votes = [];
                    $send_text = "💤 Наступила ночь, жители засыпают...\n";
                    $send_text .= "Тем временем коварная мафия вышла на охоту...\n";
                    $send_text .= "Мафиози могут выполнить свой 'святой долг' в личных сообщениях со мной";
                    $skip_warn = true;
                    $game->change_time = time() + 85;
                }

                $this->answer($message, $send_text);
            }

            // если сообщение прозвучало ночью
            if(!$is_ping && !$skip_warn && isset($game->roles[$from]) && !isset($new_day) && $game->day == false) {
                $label = static::getUserLabel($from);
                $this->answer($message, "😠 {$label}, нельзя разговаривать ночью! Больше не шали, а то исключим из игры");
                $game->warn($from);
            }

            // в первый день активной игры всё, что ниже этого, не обрабатываем
            if($game->active == true && $game->first_day == true)
                return;

            if(!$is_ping && isset($game->warns[$from]) && $game->warns[$from] >= 3) {
                $label = static::getUserLabel($from, true);
                $this->answer($message, "😡 Всё, шляпа, допрыгался!\n{$label} исключён из этой игры. Причина: 3 предупреждения.");
                unset($game->roles[$from]);
                unset($game->warns[$from]);
            }

            $count = $game->countRoles();

            if(!isset($count['maf']) || $count['maf'] <= 0 || ($from == ADMIN_ID && $text === 'победа civ')) {
                $mafs = [];
                $txt = '🧮 Поздравляем, все мафиозы побеждены! ';
                $txt .= "Это была нелёгкая победа, но да, город наконец чист!\n";
                $txt .= 'Поздравляем: ';

                foreach($game->roles as $uuid => $role) {
                    if ($role !== 'maf') {
                        $mafs[] = $uuid;
                        $txt .= static::getUserLabel($uuid, true) . ', ';
                    }
                }

                Rating::applyWinners($mafs);
                $txt .= "ну и утешительно машем ручкой тем, кто проиграл. Вы всё равно бились достойно!";
                $this->broadcast($game->peer, $txt);
                $game->active = $game->init = false;
                $game->members = [];
                $game->roles = [];
            } elseif($count['maf'] >= count($game->roles)-$count['maf'] || ($from == ADMIN_ID && $text === 'победа maf')) {
                $mafs = [];
                $txt = '⚔️ Поздравляем, теперь городом заправляет мафия... ';
                $txt .= "К сожалению для выживших граждан, их ждёт о-о-очень нелёгкая жизнь\n";
                $txt .= 'Новые хозяева города: ';

                foreach($game->roles as $uuid => $role) {
                    if($role === 'maf') {
                        $mafs[] = $uuid;
                        $txt .= static::getUserLabel($uuid, true) . ', ';
                    }
                }

                Rating::applyWinners($mafs);
                $txt .= "ну и всё. Удачного порабощения! 🔫";
                $this->broadcast($game->peer, $txt);
                $game->active = $game->init = false;
                $game->members = [];
                $game->roles = [];
            }
        }

        if($text === 'рейтинг')
        {
            $rating = Rating::getScoreTable();
            $txt = "⭐️ ТОП ИГРОКОВ\n";
            $idx = 0;

            foreach($rating as $el) {
                if(!isset($el['wins']) || $el['wins'] < 1)
                    continue;

                $idx++;
                $label = self::getUserLabel($el['uid']);
                $place = Rating::getPlaceBadge($idx);
                $txt .= "{$place} {$label} (побед: {$el['wins']}) \n";
            }

            if($idx == 0)
                $txt .= "Пока пусто 😔";

            $this->answer($message, $txt);
            return;
        }

        // основные команды
        if(!$is_ping && ($game->active != true || isset($game->roles[$from]))) {
            switch (mb_strtolower($text)) {
                case 'пинг':
                    $this->answer($message, "ПОНГ");
                    return;

                case 'новая игра':
                    if ($game->active == true || $game->init == true) {
                        $this->answer($message, 'Игра уже запущена 😮');
                        return;
                    }

                    $game->active = false;
                    $game->init = true;
                    $game->members = [];
                    $initiator = static::getUserInfo($from);

                    $this->answer($message,
                        "🧐 {$initiator['first_name']} инициировал новую игру \n" .
                        "Чтобы присоединиться, напишите `играю` "
                    );
                    return;

                case 'живые':
                    if ($game->active != true)
                        return;

                    $str = "👨‍👩‍👧‍👦 Живые игроки: \n";

                    foreach($game->roles as $mem => $role)
                        $str .= "👉🏻 ".self::getUserLabel($mem)."\n";

                    $this->answer($message, $str);
                    return;

                case 'голос':
                    if($game->active != true || $game->day != true || $game->first_day == true || !isset($game->roles[$from]))
                        return;

                    if(isset($message['reply_message']))
                        $msg = $message['reply_message'];
                    elseif(isset($message['fwd_messages'][0]))
                        $msg = $message['fwd_messages'][0];
                    else {
                        $this->answer($message, '🤔 Перешлите/ответьте на сообщение подозреваемого');
                        return;
                    }

                    $suspect = $msg['from_id'];

                    if(!isset($game->roles[$suspect]))
                        return;

                    if($suspect < 0)
                        return;

                    $game->votes[$from] = $suspect;
                    $label = static::getUserLabel($from);
                    $sus_label = static::getUserLabel($suspect);
                    $this->answer($message, "📝 {$label} голосует против {$sus_label}");
                    return;

                case 'test':
                    $this->answer($message, var_export($message, true));
                    return;

                case 'выйти':
                    if ($game->active == true)
                        return;

                    if ($game->init != true) {
                        $this->answer($message, '😛 Нет активного набора');
                        return;
                    }

                    if (!in_array((int)$from, $game->members)) {
                        $this->answer($message, '🤔 Ты не записывался');
                        return;
                    }

                    unset($game->members[array_search($from, $game->members)]);
                    $this->answer($message, '🤔👌🏻 Исключён из партии');
                    return;

                case 'играю':
                    if ($game->active == true)
                        return;

                    if ($game->init != true) {
                        $this->answer($message, '😛 Нет активного набора');
                        return;
                    }

                    if (in_array((int)$from, $game->members)) {
                        $this->answer($message, '🤔 Ты уже записан');
                        return;
                    }

                    $new_member = static::getUserInfo($from);
                    $game->members[] = (int)$from;
                    $this->answer($message, '🔌 Добро пожаловать в игру, товарищ ' . $new_member['last_name']);
                    return;

                case 'набор':
                    if ($game->init == false || $game->active == true)
                        return;

                    $str = "📝 На игру уже записались: \n";

                    foreach($game->members as $mem)
                        $str .= "👉🏻 ".self::getUserLabel($mem)."\n";

                    $this->answer($message, $str);
                    return;

                case 'поехали':
                    if ($game->init == false || $game->active == true)
                        return;

                    $game->members = (array)$game->members;

                    if (count($game->members) < 5) {
                        $this->answer($message, '🔌🔍 Маловато игроков. Нужно набрать хотя 5');
                        return;
                    }

                    // дальше следует плохое распределение ролей :(

                    shuffle($game->members);
                    $mafs = [];
                    $roles = [];
                    $roles_available = ['maf', 'cop'];

                    if(count($game->members) > 7)
                        $roles_available[] = 'maf';
                    if(count($game->members) > 8)
                        $roles_available[] = 'cop';
                    if(count($game->members) > 9)
                        $roles_available[] = 'doc';
                    if(count($game->members) > 10)
                        $roles_available[] = 'maf';

                    foreach (($mem = $game->members) as $uuid) {
                        if(isset($roles[$uuid]))
                            continue;

                        $role = array_pop($roles_available);

                        if ($role != null)
                            $roles[$uuid] = $role;
                        else
                            $roles[$uuid] = 'civ';

                        if($roles[$uuid] === 'maf')
                            $mafs[$uuid] = static::getUserLabel($uuid);
                    }

                    $mafs_string = implode(', ', array_values($mafs));

                    foreach($mafs as $uuid => $label) {
                        try {
                            $this->broadcast($uuid, "📝 {$label}, ты мафиози! Твои коллеги: {$mafs_string}. Лучше их не убивать. Наверное...");
                        } catch (\Exception $e) {}
                    }

                    foreach($roles as $uuid => $role) {
                        if($role === 'maf')
                            continue;

                        try {
                            $label = self::getUserLabel($uuid);
                            $this->broadcast($uuid, "📝 {$label}, твоя роль -- " . Strings::get($role));
                        } catch (\Exception $e) {}
                    }

                    shuffle($game->members);
                    $game->roles = $roles;
                    $game->init = false;
                    $game->active = true;
                    $game->warns = $game->votes = $game->killed = [];
                    $game->day = true;
                    $game->first_day = true;
                    $message_text = "✅ Игра начинается!\n\n";
                    $message_text .= 'Положение дел: ';

                    foreach ($game->countRoles() as $role => $cnt) {
                        $message_text .= $cnt . ' ' . Strings::get($role) . ', ';
                    }

                    $message_text .= 'для победы гражданских нужно казнить всех мафиози. ';
                    $message_text .= 'Сейчас первый день, голосовать ещё нельзя. Знакомьтесь, общайтесь, ожидаем ночь 🚬';
                    $message_text .= "\n\n(чтобы узнать команды и прочее, напишите мне в личные сообщения)";
                    $this->answer($message, $message_text);
                    $game->change_time = time() + 40;
                    return;
            }
        }
    }

    /**
     * Отправить сообщение в $peer_id
     * @param int $peer_id
     * @param string $text
     * @throws \Exception
     */
    public function broadcast(int $peer_id, string $text): void
    {
        self::getVK()->messages()->send(VK_TOKEN, [
            'peer_id' => $peer_id,
            'random_id' => time() . random_int(0, 255),
            'message' => $text
        ]);
    }

    /**
     * Ответить на сообщение
     * @param array $to_message
     * @param string $text
     * @return mixed
     * @throws \Exception
     */
    public function answer(array $to_message, string $text)
    {
        $obj = [
            'peer_id' => $to_message['peer_id'],
            'random_id' => time() . random_int(0, 255),
            'message' => $text
        ];

        if(isset($to_message['id']))
            $obj['reply_to'] = $to_message['id'];

        return self::getVK()->messages()->send(VK_TOKEN, $obj);
    }

    /**
     * Узнать инфу о юзере
     * @param int $uid
     * @return array
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public static function getUserInfo(int $uid): array
    {
        $info = self::getVK()->users()->get(VK_TOKEN, ['user_id' => $uid]);

        if(isset($info[0]))
            return $info[0];

        return ['first_name' => 'DELETED', 'last_name' => 'DELETED'];
    }

    /**
     * Получить пригодную для употребления строку с именем юзера и ссылкой на страницу
     * @param int $uid
     * @param bool $push
     * @return string
     * @throws \VK\Exceptions\VKApiException
     * @throws \VK\Exceptions\VKClientException
     */
    public static function getUserLabel(int $uid, bool $push = false): string
    {
        $info = self::getUserInfo($uid);
        $name = $info['first_name'] . ' ' . $info['last_name'];

        if($uid < 0)
            return $info['first_name'];

        if($push == true)
            $label = "*id{$uid} ({$name})";
        else
            $label = $name;

        return $label;
    }

    /**
     * Получить экземпляр VK API
     * @return VKApiClient
     */
    public static function getVK(): VKApiClient
    {
        if(is_object(self::$vk_api))
            return self::$vk_api;

        return self::$vk_api = (
            new VKApiClient('5.101', VKLanguage::RUSSIAN)
        );
    }
}