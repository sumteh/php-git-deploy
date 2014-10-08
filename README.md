# PHP Git deploy

Автоматическое развертывание кода из Git на сервер с помощью PHP.

## Использование
 * Добавьте конфиг-файл `uses/config/git.php` или `git-config.php` с содержимым:
```php
<?php
define('GIT_SECRET', 'q1w2e3r4t5'); //Секретный пароль, передаваемый при обращении к скрипту
define('GIT_BRANCH', 'master'); //Ветка, из которой будут забираться обновления
?>
```
 * Настройте репозиторий на GitHub для вызова этого скрипта после обновления кода в нем.
