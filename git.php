<?php
$config_file = false;
if (is_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php')){
	$config_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
}

if (!$config_file){
	die('Config file does not exist');
}

$CONFIG = require($config_file);

if (!isset($CONFIG['secret']) || (!isset($CONFIG['branch']) || !$CONFIG['branch']) || (!isset($CONFIG['document_root']) || !$CONFIG['document_root'])){
	die('Config is not filled');
}

$headers = getallheaders();
$payload = file_get_contents('php://input');

$data = false;
if (!isset($headers['X-Hub-Signature']) || null == ($data = json_decode($payload))){
	return;
}

list($algoritm, $hash) = explode('=', $headers['X-Hub-Signature'], 2);

$payloadHash = hash_hmac($algoritm, $payload, $CONFIG['secret']);

if ($hash !== $payloadHash){
	//header('HTTP/1.1 403 Forbidden', true, 403);
	die('Access denied!');
}

if (!isset($data->action) || 'published' !== $data->action){
	die('Action «' . $data->action . '» is not allowed');
}
if (!isset($data->release->target_commitish) || $CONFIG['branch'] !== $data->release->target_commitish){
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

$cmd = 'cd ' . $CONFIG['document_root'] . ' && git pull origin ' . $CONFIG['branch'];
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

	$cmd = 'git push origin ' . $CONFIG['branch'] . ':refs/heads/' . $h;
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";

	/*$cmd = 'git reset --hard HEAD && git pull origin ' . $CONFIG['branch'];
	$tmp = array();
	$result = exec($cmd . ' 2>&1', $tmp, $return_code);
	echo '$ ' . $cmd . "\n";
	echo trim(implode("\n", $tmp)) . "\n";*/
}

if (isset($CONFIG['chmod']) && is_array($CONFIG['chmod'])){
	foreach ($CONFIG['chmod'] as $file => $mod){
		$cmd = 'chmod ' . $file . ' ' . $mod;
		$tmp = array();
		echo '$ ' . $cmd . "\n";
		$result = exec($cmd, $tmp, $return_code);
		echo trim(implode("\n", $tmp)) . "\n";
	}
}

echo "Done.\n";
?>
