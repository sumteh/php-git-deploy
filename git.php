<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('log_errors', true);

$config_file = false;
if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php')){
	$config_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
}

if (!$config_file){
	throw new \Exception('Config file does not exist');
}

$CONFIG = require($config_file);

if (!isset($CONFIG['secret']) || empty($CONFIG['branch']) || empty($CONFIG['document_root'])){
	throw new \Exception('Config is not filled');
}

$CONFIG['document_root'] = rtrim($CONFIG['document_root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

ignore_user_abort(true);
set_time_limit(0);

$headers = getallheaders();
$payload = file_get_contents('php://input');

$data = false;
if (!isset($headers['X-Hub-Signature']) || null == ($data = json_decode($payload))){
	return;
}

if (!empty($CONFIG['actions'])){
	$actionCorrect = false;
	foreach ($CONFIG['actions'] as $action){
		list($event, $action) = explode('.', $action);

		if (
			(isset($headers['X-Github-Event']) && $headers['X-Github-Event'] == $event)
			&& (isset($data->action) && $data->action == $action)
		){
			$actionCorrect = true;
			break;
		}
	}

	if (!$actionCorrect){
		throw new \Exception('Action is not correct!');
		return;
	}
}

list($algoritm, $hash) = explode('=', $headers['X-Hub-Signature'], 2);

$payloadHash = hash_hmac($algoritm, $payload, $CONFIG['secret']);

if ($hash !== $payloadHash){
	//header('HTTP/1.1 403 Forbidden', true, 403);
	throw new \Exception('Access denied!');
}

if (!isset($data->action)){
	throw new \Exception('Action is not isset');
} elseif ('published' !== $data->action){
	throw new \Exception('Action «' . $data->action . '» is not allowed');
}
if (!isset($data->release->target_commitish)){
	throw new \Exception('target_commitish not is set');
} elseif ($CONFIG['branch'] !== $data->release->target_commitish){
	throw new \Exception('target_commitish «' . $data->release->target_commitish . '» is bad');
}

$git_path = trim(shell_exec('which git'));
if ('' == $git_path){
	throw new \Exception('«git» command is not available');
} else {
	$cmd = sprintf('%s --version', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
	$tmp = array();
	echo '$ ' . $cmd . "\n";
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo trim(implode("\n", $tmp)) . "\n";
}

if (!is_dir($CONFIG['document_root'] . '.git')){
	throw new \Exception('Repository in «' . $CONFIG['document_root'] . '» does not exist');
}

//echo 'Release: ' . $data->release->tag_name . "\n";

$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" config --get "user.name"', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
$tmp = array();
echo '$ ' . $cmd . "\n";
$result = exec($cmd . " 2>&1", $tmp, $return_code);
if (0 === $return_code){
	echo trim(implode("\n", $tmp)) . "\n";
} else {
	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" config user.name "%s"', $git_path, $CONFIG['document_root'], $CONFIG['document_root'], 'PHP Git deploy');
	$tmp = array();
	echo '$ ' . $cmd . "\n";
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo trim(implode("\n", $tmp)) . "\n";
}

$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" fetch --all', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
$tmp = array();
echo '$ ' . $cmd . "\n";
$result = exec($cmd . " 2>&1", $tmp, $return_code);
echo trim(implode("\n", $tmp)) . "\n";


$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" diff master --exit-code --ignore-submodules', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
$tmp = array();
echo '$ ' . $cmd . "\n";
$result = exec($cmd . " 2>&1", $tmp, $return_code);
echo trim(implode("\n", $tmp)) . "\n";

if (0 === $return_code){
	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" pull --verbose --no-edit origin %s', $git_path, $CONFIG['document_root'], $CONFIG['document_root'], $CONFIG['branch']);
	$tmp = array();
	echo '$ ' . $cmd . "\n";
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo trim(implode("\n", $tmp)) . "\n";


	$cmd = sprintf('cd %s && %s --git-dir="%s.git" --work-tree="%s" submodule update --init --recursive', $CONFIG['document_root'], $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
	$tmp = array();
	echo '$ ' . $cmd . "\n";
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo trim(implode("\n", $tmp)) . "\n";
} else {
	date_default_timezone_set('Asia/Yekaterinburg');

	$h = 'conflict-' . strftime('%Y%m%d%H%M%S', time());

	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" add .', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
	$tmp = array();
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" commit -a -m "%s"', $git_path, $CONFIG['document_root'], $CONFIG['document_root'], $h);
	$tmp = array();
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" push origin %s:refs/heads/%s', $git_path, $CONFIG['document_root'], $CONFIG['document_root'], $CONFIG['branch'], $h);
	$tmp = array();
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	/*$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" reset --hard HEAD', $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
	$tmp = array();
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	$cmd = sprintf('%s --git-dir="%s.git" --work-tree="%s" pull origin ' . $CONFIG['branch'], $git_path, $CONFIG['document_root'], $CONFIG['document_root']);
	$tmp = array();
	$result = exec($cmd . " 2>&1", $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";*/
}

if (isset($CONFIG['chmod']) && is_array($CONFIG['chmod'])){
	foreach ($CONFIG['chmod'] as $file => $mod){
		$cmd = sprintf('chmod %s %s', $mod, $file);
		$tmp = array();
		echo '$ ' . $cmd . "\n";
		$result = exec($cmd . " 2>&1", $tmp, $return_code);
		echo trim(implode("\n", $tmp)) . "\n";
	}
}

echo "Done.\n";
?>
