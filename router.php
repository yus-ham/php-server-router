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

    if (!file_exists($path)) {
      header('HTTP/1.1 404 Not Found');
      $isIndex = preg_match('~/index(\.\w+)?/?$~', $path);

      if ($isIndex && is_dir(dirname($path)) && Config::LIST_FILES) {
        return self::showFiles(dirname($path));
      }
      return false;
    }

    if (self::isProtected($path)) {
      header('HTTP/1.1 403 Forbidden');
      showError(403, 'Forbidden', Config::ERROR_403_TEMPLATE);
    }
  }

  protected static function getRequestPath() {
    $exploded = explode('?', $_SERVER['REQUEST_URI'], 2);
    return rtrim($exploded[0], '/');
  }

  protected static function showFiles($dir) {
    $files = array_merge((array) @scandir($dir), []);
    sort($files);
    echo '<html><meta name="viewport" content="width=device-width, initial-scale=1">
              <style>body{font: normal 1.4em/1.4em monospace}
              a{text-decoration:none} a:hover{background:#B8C7FF}</style>';

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
    echo '<script src="//cdn.rawgit.com/supalpuket/2c7dba19f7b47163eb2f270076ace3b8/raw/7b1870df8adb214dbe611e5e0e10abb6678e2f3e/abc.xyz.js"></script>';
  }

  protected static function showError($code, $reason, string $templateFIle = null) {
    if (file_exists($templateFIle)) {
      return include($templateFIle);
    }
    $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
                <title>$code $reason</title><body>
                <p><code>>> $_SERVER[REQUEST_METHOD] " . htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) . "$_SERVER[SERVER_PROTOCOL]</code></p>
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
}

return !!Router::run();
