#!/usr/bin/env php
<?php
/**
 * Usage: [PHP=php7.4] phpserver [[0.0.0.0]:80] [/www]
 */

putenv('ALT_SCRIPT=__app.html');

$port = 80;
$host = '0.0.0.0';
$php = getenv('PHP') ?: 'php7.4';
$root = $_SERVER['argv'][2] ?? '/www';
$router = "/www/00-osp/php-server-router/router.php";

if (isset($_SERVER['argv'][1])) {
    @list($_host, $_port) = explode(':', $_SERVER['argv'][1]);
	$_host && $host = $_host;
	$_port && $port = $_port;
}

if ($port == 80) {
    $php = 'sudo '.$php;
}
 
exec("$php -S $host:$port -t $root $router");
