<?php
//echo '<pre>';

class Config {
  const ERROR_403_TEMPLATE = __DIR__ . '/403.php';
  const ERROR_404_TEMPLATE = __DIR__ . '/404.php';
  const PROTECTED_PATH_REGEXES = ['~/\.git(\/.*)?$|/nbproject~'];
  const LIST_FILES = true;
}

class Router {

  public static function run() {
    self::isJsRequest() && self::sendJs();
    $path = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) . self::getRequestPath();

    if (is_dir($path)) {
      $path .= '/index.php';

      // Spesial dot handling
      // PHP treat not exist on path containing dots
      if (strpos(self::getRequestPath(), '.') !== false && file_exists($path)) {
        include $path;
        exit;
      }
    }

    if (self::isProtected($path)) {
      header('HTTP/1.1 403 Forbidden');
      self::showError(403, 'Forbidden', Config::ERROR_403_TEMPLATE);
    }

    if (!file_exists($path)) {
      header('HTTP/1.1 404 Not Found');
      $isIndex = preg_match('~/index(\.\w+)?/?$~', $path);

      if ($isIndex && is_dir(dirname($path)) && Config::LIST_FILES) {
        exit(self::showFiles(dirname($path)));
      }
      return false;
    }
  }

  protected static function getRequestPath() {
    $exploded = explode('?', $_SERVER['REQUEST_URI'], 2);
    return rtrim($exploded[0], '/');
  }

  protected static function showFiles($dir) {
    header('Content-Type: text/html');
    $files = array_merge((array) @scandir($dir), []);
    sort($files);
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">
              <style>body{font: normal 1.4em/1.4em monospace}
              a{text-decoration:none} a:hover{background:#B8C7FF}</style></head><body>';

    foreach ($files as $file) {
      if ($file === '.') {
        continue;
      }
      $link = self::getRequestPath() . "/$file/";
      if (is_dir("$dir/$file")) {
        echo "<div class=row>[&bull;] <a href='$link'>$file/</a></div>\n";
      } else {
        @$_files[] = $file;
      }
    }

    foreach ((array) @$_files as $file) {
      $link = self::getRequestPath() . '/' . $file;
      $bytes = filesize($dir . '/' . $file);
      echo "[&bull;] <a href='$link'>$file</a> (<span name=data-bytes>$bytes</span>)<br/>\n";
    }
    $time = filemtime(__FILE__);
    echo "<script src=/?$time.js></script></body></html>";
  }

  protected static function showError($code, $reason, string $templateFIle = null) {
    if (file_exists($templateFIle)) {
      return include($templateFIle);
    }
    $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
                <title>$code $reason</title><body>
                <p><code>>> $_SERVER[REQUEST_METHOD] " . htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) . " $_SERVER[SERVER_PROTOCOL]</code></p>
                <p><code><< $_SERVER[SERVER_PROTOCOL] $code $reason</code></p></body>";
    exit($template);
  }

  protected static function isProtected($path) {
    foreach ((array) Config::PROTECTED_PATH_REGEXES as $regex) {
      if (preg_match($regex, $path)) {
        return true;
      }
    }
  }

  protected static function isJsRequest() {
    return $_SERVER['REQUEST_URI'] === "/?".filemtime(__FILE__).".js";
  }

  protected static function sendJs() {
    header('Content-Type: application/javascript');
    exit(<<<_JS_
// link: http://stackoverflow.com/a/20463021
function fileSizeIEC(a,b,c,d,e){
 return (b=Math,c=b.log,d=1024,e=c(a)/c(d)|0,a/b.pow(d,e)).toFixed(2)
 +' '+(e?'KMGTPEZY'[--e]+'iB':'Bytes')
}

e=document.getElementsByName('data-bytes')
for(i=0;i<e.length;i++) {
    e[i].innerHTML = fileSizeIEC(e[i].innerHTML)
}
_JS_
    );
  }
}

return !!Router::run();
