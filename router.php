<?php
//echo '<pre>';

class Config
{
  const index_scripts = ['index.php','index.html'];
  const protected_paths = '~/\.git(\/.*)?$|/nbproject~';
  const show_files = true;
}


class Router {

  public static function run() {
    if (self::isJsRequest()) {
      exit(self::sendJs());
    }

    $reqPath = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) . self::getRequestPath();

    if (is_dir($reqPath)) {
      self::serveDir($reqPath);
    }

    if (self::isProtected($reqPath)) {
      header('HTTP/1.1 403 Forbidden');
      self::showError(403, 'Forbidden');
    }
  }

  protected static function serveDir($dir) {
    foreach (Config::index_scripts as $script) {
      if (is_file("$dir/$script")) {
        return self::serveScript($dir, $script);
      }
    }
    if (Config::show_files) {
      exit(self::showFiles($dir));
    }
  }

  protected static function serveScript($dir, $script) {
    // PHP fails to serve path that contains dot
    $hasDot = strpos(self::getRequestPath(), '.') !== false;
    if (!$hasDot) {
      return;
    }
    chdir($dir);
    $_SERVER['SCRIPT_NAME'] .= "/$script";
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    include $_SERVER['SCRIPT_FILENAME'] = "$dir/$script";
    exit();
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
      echo "[&bull;] <a href='$link'>$file</a> (<span class=filesize>$bytes</span>)<br/>\n";
    }
    $time = filemtime(__FILE__);
    echo "<script src=/?$time.js></script></body></html>";
  }

  protected static function showError($code, $reason) {
    $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
                <title>$code $reason</title><body>
                <p><code>>> $_SERVER[REQUEST_METHOD] " . htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) . " $_SERVER[SERVER_PROTOCOL]</code></p>
                <p><code><< $_SERVER[SERVER_PROTOCOL] $code $reason</code></p></body>";
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
