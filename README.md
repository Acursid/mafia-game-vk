## Вместо дисклеймера

Код **ужасный**. Это можно использовать только для
учёбы или пары игр, но не более. Тут всё держится
на ООП, которое на самом деле недоделанное ООП,
а вся инфа только в массивах и JSON-файле.
**Для боя это не годится**, а новую версию я написать
не успел, так уж вышло.

# Чат-мафия

Этот самодельный скрипт был выполнен на коленке
в результате 8-часового хакатона. Просто поиграться.

Оригинальная игра была здесь: https://vk.com/mafiacb

Там же сохранились статьи и описания ролей. Пригодится.

## Настройка

Вся конфигурация - в config.php.

Потребуется выполнить *composer install*, чтобы
установить зависимости вроде VK API SDK.

Желательно запускать бота на сервере. Нужно добавить
*run-mafia.php* в CRON на выполнение **ежеминутно**.

При запуске этого скрипта он сам определит, работать
или свернуться (если уже запущен другой процесс),
так что не надо никаких daemon, forever и прочего.

## Что дальше

Добавьте бота в беседу, настройте всё в конфиге как
описано выше, запустите и радуйтесь жизни.

В игровом чате напишите "новая игра". Если бот
инициировал новую игру - ура, вы победили. 
В случае ошибки он напишет в личку админу, ID
которого тоже указывается в конфиге.

## Обратная связь

Пишите всё, что нужно, в Issues. Быстрый фидбэк
не обещаю, т.к. скрипт неактуален