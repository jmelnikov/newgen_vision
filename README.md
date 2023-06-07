# Установка и запуск проекта

1. Для запуска проекта нужен установленный в системе Docker
2. В папке с проектом перейти в папку `docker` и запустить из командной строки `docker-compose build`
3. После сборки проекта запустить `docker-compose up` (можно добавить ключ `-d` чтобы не привязывать терминал к запущенным контейнерам)
4. После запуска контейнеров, к консоли поочерёдно выполнить команды
    - `docker exec -it newgen-php-fpm bash` (для подключения к терминалу контейнера)
    - `composer install` (для установки зависимостей)
    - `php bin/console doctrine:migrations:migrate` (для выполнения миграций)

# Настройки проекта
Единственная настройка проекта -- это подключение к БД, находится в файле `.env` в директории `www`.

Если запускать через docker-compose, то дополнительных настроек не потребуется. Только установка зависимостей и выполнение миграций. 

# Использование проекта
В директории с проектом (по-умолчанию это `/var/www` в Docker) запустить скрипт `php bin/console parser:artist`, в качестве параметра передать ссылку на исполнителя или его ID с ключом `-i` или `--id`

### Примеры, которые будут работать
- `php bin/console parser:artist https://music.yandex.ru/artist/36800`
- `php bin/console parser:artist https://music.yandex.ru/artist/36800/tracks`
- `php bin/console parser:artist 36800 -i`
- `php bin/console parser:artist 36800 --id`

### Примеры, которые не будут работать
- `php bin/console parser:artist https://music.yandex.ru/artist/36800 -i`
- `php bin/console parser:artist https://music.yandex.ru/artist/36800 --id`
- `php bin/console parser:artist https://music.yandex.ru/artist/36800/tracks -i`
- `php bin/console parser:artist 36800`

# Дополнение
Чтобы не натыкаться на бан от Яндекса каждый раз, можно использовать кэширование.
Запуск сбора одной и тоже информации об одном артисте не имеет смысла чаще раза в несколько дней, а то и недель, так как новые песни выходят не каждый день, как правило.
