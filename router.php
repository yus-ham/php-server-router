<?php
//echo '<pre>';

$CONFIG = [
    'error_403' => __DIR__.'/403.php',
    'error_404' => __DIR__.'/404.php',

    'indexes' => true,

    // protected urls
    'protected' => ['~/\.git(\/.*)?$|/nbproject~'],
];

function loadScript() {
    global $CONFIG;

    $path = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']).getRequestPath();

    if (is_dir($path)) {
        $path .= '/index.php';

        // Spesial handling dot
        if (strpos(getRequestPath(), '.') !== false
        && file_exists($path)) {
            include $path;
            exit;
        }
    }

    if (!file_exists($path)) {
        header('HTTP/1.1 404 Not Found');
        $isIndex = preg_match('~/index(\.\w+)?/?$~', $path);

        if ($isIndex && is_dir(dirname($path)) && @$CONFIG['indexes']) {
            showFiles(dirname($path));
            exit;
        }

        return false;
    }

    if (isProtected($path)) {
        header('HTTP/1.1 403 Forbidden');
        showError(403);
    }
}

function getRequestPath() {
    $exploded = explode('?', $_SERVER['REQUEST_URI'], 2);
    return rtrim($exploded[0], '/');
}

function showFiles($dir) {
    global $CONFIG;

    $files = array_merge((array) @scandir($dir), []);
    sort($files);
    echo '<html><meta name="viewport" content="width=device-width, initial-scale=1">
          <style>body{font: normal 1.4em/1.4em monospace}
          a{text-decoration:none} a:hover{background:#B8C7FF}</style>';

    foreach ($files as $file) {
        if ($file === '.') continue;

        $link = getRequestPath()."/$file/";
        if (is_dir("$dir/$file")) {
            echo "<div class=row>[&bull;] <a href='$link'>$file/</a></div>\n";
        } else {
            @$_files[] = $file;
        }
    }

    foreach ((array) @$_files as $file) {
        $link = getRequestPath().'/'.$file;
        $bytes = filesize($dir.'/'.$file);
        echo "[&bull;] <a href='$link'>$file</a> (<span name=data-bytes>$bytes</span>)<br/>\n";
    }
    echo '<script src="//cdn.rawgit.com/supalpuket/2c7dba19f7b47163eb2f270076ace3b8/raw/7b1870df8adb214dbe611e5e0e10abb6678e2f3e/abc.xyz.js"></script>';
}

function showError($code=404) {
    global $CONFIG;
    $message = [
        403 => '403 Forbidden',
        404 => '404 Not Found',
    ];

    if (file_exists(@$CONFIG['error_'.$code])) {
        include($CONFIG['error_'.$code]);
    } else {
        $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
            <title>$message[$code]</title><style>
            body.errordoc {font: normal 1.2em monospace; background-color: #fcfcfc; color: #333333; margin: 0; padding:0; }
            body.errordoc {font: normal 1.2em monospace; background-color: #fcfcfc; color: #333333; margin: 0; padding:0; }
            body.errordoc h1 { font-size:1.5em; background-color: #9999cc; min-height:2em; line-height:2em; border-bottom: 1px inset black; margin: 0; }
            body.errordoc h1, body.errordoc p { padding-left: 10px}
            body.errordoc code { background-color: #ddd; padding:0 5px}
            </style><body class='errordoc'><h1>$message[$code]</h1>
            <p>Request: <code>$_SERVER[SERVER_PROTOCOL] $_SERVER[REQUEST_METHOD] ". htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) ."</code></p></body>";
        exit($template);
    }
}

function isProtected($path) {
    global $CONFIG;

    foreach ((array) @$CONFIG['protected'] as $regex) {
        if (preg_match($regex, $path)) return true;
    }
}

return !!loadScript();
