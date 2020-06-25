<?php
// @link https://gist.github.com/sup-ham/5b132ce4547b43aa8b2d

// env vars
// ALT_SCRIPT=index.php,__app.html,etc...   comma separated

class Config
{
  const protected_paths = '~/\.git(\/.*)?$|/nbproject~';
  const show_files = true;
}


class Router {

  // default = index.php,index.html
  // @see [getScripts()]
  static $scripts;

  public static function run() {
    if (self::isJsRequest()) {
      exit(self::sendJs());
    }

    $reqUrl = self::getRequestUrl();
    error_log("[router] REQUEST_URI = $reqUrl");

    if (self::isProtected($reqUrl)) {
      http_response_code(403);
      self::showError('HTTP/1.1 403 Forbidden');
    }

    $webroot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $path = $webroot . $reqUrl;

    if (is_dir($path)) {
      self::serveDir($path);
    }

    if (!is_file($path)) {
      $i = 0;
      do {
        $reqUrl = str_replace('\\', '/', dirname($reqUrl));
        $reqUrl = rtrim($reqUrl, '/');
        $dir = $webroot . $reqUrl;
        self::serveIndex($dir);
      } while (($i++ < 20) && ($reqUrl && $reqUrl !== '/'));
    }
  }

  protected static function getScripts() {
    if (!self::$scripts) {
      self::$scripts = array('index.php', 'index.html');

      if ($altScripts = getenv('ALT_SCRIPT')) {
        self::$scripts = array_merge(explode(',', $altScripts), self::$scripts);
      }
    }
    return self::$scripts;
  }

  protected static function serveIndex($dir) {
    foreach (self::getScripts() as $script) {
      $script = $dir .'/'. $script;
      error_log("[router] trying script: $script");
      if (is_file($script)) {
        self::serveScript($script, $dir);
      }
    }
  }

  protected static function serveDir($dir) {
    self::serveIndex($dir);
    http_response_code(404);
    if (Config::show_files) {
      exit(self::showFiles($dir));
    }
  }

  protected static function serveScript($script, $dir) {
    error_log("[router] SCRIPT_FILENAME = $script");

    // PHP fails to serve path that contains dot
    $hasDot = strpos($dir, '.') !== false;
    $isPHP = pathinfo($script, PATHINFO_EXTENSION) === 'php';
    if (!$hasDot && $isPHP) {
      return;
    }
    chdir($dir);
    $_SERVER['SCRIPT_NAME'] .= $script;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    include $_SERVER['SCRIPT_FILENAME'] = $script;
    exit();
  }

  protected static function getRequestUrl() {
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

    $reqUrl = self::getRequestUrl();

    foreach ($files as $file) {
      if ($file === '.') {
        continue;
      }
      $link = "$reqUrl/$file/";
      if (is_dir("$dir/$file")) {
        echo "<div class=row>[&bull;] <a href='$link'>$file/</a></div>\n";
      } else {
        @$_files[] = $file;
      }
    }

    foreach ((array) @$_files as $file) {
      $link = "$reqUrl/$file";
      $bytes = filesize($dir . '/' . $file);
      echo "[&bull;] <a href='$link'>$file</a> (<span class=filesize>$bytes</span>)<br/>\n";
    }

    $time = filemtime(__FILE__);
    echo "<script src=/?$time.js></script></body></html>";
  }

  protected static function showError($message) {
    $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
                <title>$message</title><body>
                <p><code>>> $_SERVER[REQUEST_METHOD] " . htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) . " $_SERVER[SERVER_PROTOCOL]</code></p>
                <p><code><< $message</code></p></body>";
    exit($template);
  }

  protected static function isProtected($path) {
    $regex = Config::protected_paths;
    if (preg_match($regex, $path)) {
      return true;
    }
  }

  protected static function isJsRequest() {
    return $_SERVER['REQUEST_URI'] === "/?".filemtime(__FILE__).".js";
  }

  protected static function sendJs() {
    header('Content-Type: application/javascript');
    header('Cache-Control: public, max-age='. strtotime('6 month'));
    echo "// link: http://stackoverflow.com/a/20463021"
       . "\nfileSizeIEC = (a,b,c,d,e) => (b=Math,c=b.log,d=1024,e=c(a)/c(d)|0,a/b.pow(d,e)).toFixed(2) +' '+(e?'KMGTPEZY'[--e]+'iB':'Bytes')"
       . "\ndocument.querySelectorAll('.filesize').forEach((e) => e.innerHTML = fileSizeIEC(e.innerHTML))";
  }
}

return !!Router::run();
