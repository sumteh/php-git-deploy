# PHP Git deploy

Автоматическое развертывание кода из Git на сервер с помощью PHP.

## Использование
 * Добавьте конфиг-файл `uses/config/git.php` или `git-config.php` с содержимым:
```php
<?php
define('GIT_SECRET', 'q1w2e3r4t5');
define('GIT_BRANCH', 'develop');
?>
```
 * Настройте репозиторий на GitHub для вызова этого скрипта после обновления кода в нем.
