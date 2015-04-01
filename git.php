<?php
$config_file = false;
if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php')){
	$config_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
}

if (!$config_file){
	die('Config file does not exist');
}

require($config_file);

if (!defined('GIT_SECRET') || !defined('GIT_BRANCH')){
	die('Config constants is not defined');
}

$headers = getallheaders();
$payload = file_get_contents('php://input');

$data = false;
if (!isset($headers['X-Hub-Signature']) || null == ($data = json_decode($payload))){
	return;
}

list($algoritm, $hash) = explode('=', $headers['X-Hub-Signature'], 2);

$payloadHash = hash_hmac($algoritm, $payload, GIT_SECRET);

if ($hash !== $payloadHash){
	//header('HTTP/1.1 403 Forbidden', true, 403);
	die('Access denied!');
}

if (!isset($data->action) || 'published' !== $data->action){
	die('Action «' . $data->action . '» is not allowed');
}
if (!isset($data->release->target_commitish) || GIT_BRANCH !== $data->release->target_commitish){
	die('target_commitish «' . $data->release->target_commitish . '» is bad');
}

$path = trim(shell_exec('which git'));
if ('' == $path){
	die('«git» command is not available');
} else {
	$version = explode("\n", shell_exec('git --version'));
	echo $path . ': ' . $version[0] . "\n";
}

//echo 'Release: ' . $data->release->tag_name . "\n";

$cmd = 'cd ' . dirname(__FILE__) . '../ && git pull origin ' . GIT_BRANCH;
$tmp = array();
echo '$ ' . $cmd . "\n";
$result = exec($cmd, $tmp, $return_code);
echo trim(implode("\n", $tmp)) . "\n";

if (0 !== $return_code){
	$h = 'conflict-' . strftime('%Y%m%d%H%M%S', time());

	$cmd = 'git add .';
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	$cmd = 'git commit -a -m "' . $h . '"';
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	$cmd = 'git push origin ' . GIT_BRANCH . ':refs/heads/' . $h;
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	/*$cmd = 'git reset --hard HEAD && git pull origin ' . GIT_BRANCH;
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";*/
}

echo "Done.\n";
?>
