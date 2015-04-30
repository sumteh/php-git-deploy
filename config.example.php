<?php
return array(
	'secret' => 'SUPER_SECRET_PASSWORD' //Секретный пароль, передаваемый при обращении к скрипту
	'branch' => 'master', //Ветка, из которой будут забираться обновления
	'document_root' => '/PATH/TO/SITE/ROOT/', //Корневая директория сайта
	'chmod' => array( //Изменения режима доступа к файлам после импорта данных из GIT
		'/PATH/TO/FI.LE' => 'MODE' // example: '/home/user/web/site.ru/public_html/index.php' => '0755'
	)
);
?>