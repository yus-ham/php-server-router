<?php

//define('ERR_403', __DIR__.'/403.php');
//define('ERR_404', __DIR__.'/404.php');

function loadScript($file) {
    if (!file_exists($file)) {
        $index = getIndex();

        if (file_exists($index)) {
            include $index;
            exit;
        }

        if (file_exists(@ERR_404)) {
            include ERR_404;
            exit;
        }
    }

    return false;
}

function getScript() {
    $script = $_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'];

    return $script;
}

function getIndex() {
    $dir = $_SERVER['SCRIPT_NAME'];

    while (!is_dir($_SERVER['DOCUMENT_ROOT'].$dir)) {
        $dir = dirname($dir);
    }

    $_SERVER['SCRIPT_NAME'] = rtrim($dir, '\\/').'/index.php';

    return getScript();
}

return loadScript(getScript());
//////////////////////////////////////////////////////////////////////////////////////////////////////
?>

<?php

error_reporting(0);
function build_url($parts, $encode = 1) {
    if ($encode) {
        if (isset($parts['user'])) $parts['user']     = rawurlencode($parts['user']);
        if (isset($parts['pass'])) $parts['pass']     = rawurlencode($parts['pass']);
        if (isset($parts['host']) &&
            !preg_match('!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host'])) $parts['host']     = rawurlencode($parts['host']);
        if (!empty($parts['path'])) $parts['path']     = preg_replace('!%2F!ui', '/',
                                                                      rawurlencode($parts['path']));
        if (isset($parts['query'])) $parts['query']    = rawurlencode($parts['query']);
        if (isset($parts['fragment'])) $parts['fragment'] = rawurlencode($parts['fragment']);
    }

    $url = '';

    !empty($parts[scheme]) && ($url .= "$parts[scheme]:");

    if (isset($parts['host'])) {
        $url .= '//';

        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) $url .= ':' . $parts['pass'];
            $url .= '@';
        }

        if (preg_match('!^[\da-f]*:[\da-f.:]+$!ui', $parts['host'])) $url .= '[' . $parts['host'] . ']'; // IPv6
        else $url .= $parts['host'];             // IPv4 or name
        if (isset($parts['port'])) $url .= ':' . $parts['port'];
        if (!empty($parts['path']) && $parts['path'][0] != '/') $url .= '/';
    }
    !empty($parts[path]) && ($url .= "$parts[path]");
    isset($parts[query]) && ($url .= "?$parts[query]");
    isset($parts[fragment]) && ( $url .= "#$parts[fragment]");

    return $url;
}

//////////////////////////////////////////////////////////////////////////////////////////////////

$root = $_SERVER[DOCUMENT_ROOT];
$uri  = $_SERVER[REQUEST_URI];
$url  = (object) parse_url($_SERVER["REQUEST_URI"]);
$ext  = pathinfo($path, PATHINFO_EXTENSION);
$exts = array("php", "jpg", "jpeg", "gif", "css");

if (in_array($ext, $exts)) {
    # let the server handle the request as-is
    # return false;
}


if (is_dir($root . $url->path) && substr($url->path, -1) !== '/') {
    header("location:{$url->path}/" . ($url->query ? '?' : '') . $url->query);
}
