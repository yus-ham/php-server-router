<?php
// @link https://github.com/sup-ham/php-server-router

// env vars
// ALT_SCRIPT=index.php,__app.html,etc...   comma separated

class Config
{
  const debug = true;
  const protected_paths = '~/\.git(\/.*)?$|/nbproject~';
  const show_files = true;
}


class Router
{
  private static $docRoot;
  private static $pathInfo;
  private static $prevPathInfo;
  private static $requestURI;
  private static $rewriteURI;
  private static $parsedHtaccess = [];

  private static $type2Exts = [
    'text/html' => 'htm,html',
    'text/css' => 'css',
    'text/javascript' => 'js,mjs',
    'image/svg+xml' => 'svg',
    'image/' => 'png,gif,jpg,jpeg,webp',
    'video/' => 'mp4,webm',
    'application/json' => 'json,map',
    'application/' => 'pdf',
    'font/' => 'woff,woff2',
  ];

  // default = index.php,index.html
  // @see [getScripts()]
  public static $scripts;

  public static function setup()
  {
    $port = ($_SERVER['SERVER_PORT'] != '80') ? ":$_SERVER[SERVER_PORT]" : "";
    $_SERVER['SERVER_ADDR'] = "$_SERVER[SERVER_NAME]$port";
    self::setRequestURI();
  }

  public static function run()
  {
    self::setup();
    self::maybeFileRequest();
    return self::serveURI();
  }

  protected static function serveURI()
  {
    constant('Config::debug') && error_log(__METHOD__);
    $currentUri = self::getFilePath(self::$requestURI);
    error_log("REQUEST_URI = $currentUri");

    if (self::isProtected($currentUri)) {
      http_response_code(403);
      self::showError('HTTP/1.1 403 Forbidden');
    }

    self::$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $path = self::$docRoot . $currentUri;
    constant('Config::debug') && error_log(print_r(['DOCUMENT_ROOT' => self::$docRoot, 'pathInfo' => self::$pathInfo], 1));

    if (is_dir($path)) {
      return self::serveDir($path, $currentUri);
    }
    if (is_file($path)) {
      if (self::isDot('php', $path)) {
        return self::serveScript($path, dirname($path), '');
      }
      self::readFile($path);
    }

    $i = 0;
    do {
      self::$pathInfo = '/'. basename($currentUri) . self::$pathInfo;
      $currentUri = rtrim(str_replace('\\', '/', dirname($currentUri)), '/');
      $dir = self::$docRoot . $currentUri;

      if (false === self::serveIndex($dir, $currentUri)) {
        return;
      }
    } while (($i++ < 20) && ($currentUri && $currentUri !== '/'));
  }

  protected static function getScripts()
  {
    if (!self::$scripts) {
      self::$scripts = array('index.php', 'index.html');

      if ($altScripts = getenv('ALT_SCRIPT')) {
        self::$scripts = array_merge(explode(',', $altScripts), self::$scripts);
      }

      array_unshift(self::$scripts, '.htaccess');
    }
    return self::$scripts;
  }

  protected static function serveIndex($dir, $currentUri)
  {
    constant('Config::debug') && error_log(print_r([__METHOD__, '$dir' => $dir, '$currentUri' => $currentUri, '$pathInfo' => self::$pathInfo],1));
    foreach (self::getScripts() as $script) {
      $script = $dir . '/' . $script;
      constant('Config::debug') && error_log("trying script: $script");
      if (is_file($script)) {
        constant('Config::debug') && error_log("Script found!");
        if (self::serveHtaccess($script, $dir, $currentUri) === null) {
          continue;
        }
        self::$pathInfo = preg_replace(':^/'.$script.':', '', self::$pathInfo);
        return self::serveScript($script, $dir, $currentUri);
      }
    }
  }

  protected static function serveHtaccess($file, $dir, $currentUri)
  {
    if (!self::isDot('htaccess', $file)) {
      return false;
    }
    if (in_array($file, self::$parsedHtaccess)) {
      return;
    }
    self::$parsedHtaccess[] = $file;
    constant('Config::debug') && error_log(print_r([__METHOD__.' '.__LINE__, '$file' => $file, '$dir' => $dir, '$currentUri' => $currentUri, '$pathInfo' => self::$pathInfo], 1));
    $stopParsing = false;

    foreach (file($file) as $line) {
      if ($stopParsing) {
        return;
      }
      @list($command, $args) = explode(' ', trim($line), 2);

      if (!$command or strpos($command, 'Rewrite') === false) {
        continue;
      }

      constant('Config::debug') && error_log(__METHOD__ . ' ' . print_r(['$command' => $command, '$args' => $args], 1));
      $args = preg_split('/ +/', trim($args));
      if ($command === 'RewriteEngine' && strtolower($args[0]) === 'on') {
        self::$rewriteURI = true;
        continue;
      }
      if (!self::$rewriteURI) {
        throw new \Exception('Rewrite engine is off');
      }
      if ($command === 'RewriteCond') {
        if ($args[0] === '%{REQUEST_FILENAME}') {
          constant('Config::debug') && error_log('Test file: '. self::$docRoot . self::$requestURI);
          if ($args[1] === '!-d' && is_dir(self::$docRoot . self::$requestURI)) {
            constant('Config::debug') && error_log('Fail on test');
            return;
          }
          if ($args[1] === '!-f' && is_file(self::$docRoot . self::$requestURI)) {
            constant('Config::debug') && error_log('Fail on test');
            return;
          }
        }
      }
      if ($command === 'RewriteRule') {
        $newURI = preg_replace(':' . $args[0] . ':', $args[1], ltrim(self::$pathInfo, '/'));
        self::$prevPathInfo = substr(self::$requestURI, strlen($currentUri));
        self::$requestURI = $currentUri . '/' . $newURI;
        self::$pathInfo = null;
        return self::serveURI();
      }
    }
    return true;
  }

  protected static function serveDir($dir, $currentUri)
  {
    constant('Config::debug') && error_log(__METHOD__ . ' ' . print_r(func_get_args(), 1));
    $dir = rtrim($dir, '/');
    if (false === self::serveIndex($dir, $currentUri)) {
      return;
    }
    http_response_code(404);
    if (Config::show_files) {
      exit(self::showFiles($dir));
    }
  }

  protected static function serveScript($script, $dir, $currentUri)
  {
    // constant('Config::debug') && error_log(__METHOD__ . ' ' . print_r(compact('script','dir','currentUri'), 1));
    // constant('Config::debug') && error_log(print_r($_SERVER, 1));
    // constant('Config::debug') && error_log(print_r(['pathInfo' => self::$pathInfo, 'prevPath' => self::$prevPathInfo], 1));
    error_log(__METHOD__ . " SCRIPT_FILENAME = $script");

    // PHP Built-in server fails to serve path that contains dot
    $hasDotInDir = strpos($dir, '.') !== false;
    if (!$hasDotInDir && self::isDot('php', $script) && self::$prevPathInfo === null) {
      constant('Config::debug') && error_log('procesed by php!');
      return false;
    }

    if (self::$pathInfo !== null OR self::$prevPathInfo !== null) {
      $_SERVER['SCRIPT_NAME'] = substr($script, strlen(self::$docRoot));
    } else {
      $_SERVER['SCRIPT_NAME'] .= $script;
    }

    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    self::includeScript($script);
  }

  protected static function includeScript($script)
  {
    constant('Config::debug') && error_log("Include script: $script");
    chdir(dirname($script));
    include $_SERVER['SCRIPT_FILENAME'] = $script;
    exit();
  }

  protected static function getFilePath($url)
  {
    return explode('?', $url)[0];
  }

  protected static function setRequestURI()
  {
    return self::$requestURI = rtrim(self::getFilePath($_SERVER['REQUEST_URI']), '/');
  }

  protected static function showFiles($dir)
  {
    header('Content-Type: text/html');
    $files = array_merge((array) @scandir($dir), []);
    sort($files);
    echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">
              <style>body{font: normal 1.4em/1.4em monospace}
              a{text-decoration:none} a:hover{background:#B8C7FF}</style></head><body>';

    $reqUri = self::$requestURI;

    echo "<table>";
    $_dirs = [];
    $_files = [];
    foreach ($files as $file) {
      if ($file === '.' or $file === '..') {
        continue;
      }
      $link = "$reqUri/$file/";
      filemtime("$dir/$file");
      if (is_dir("$dir/$file")) {
        @$_dirs[] = $file;
      } else {
        @$_files[] = $file;
      }
    }

    $cmp = function($a, $b) use($dir) {
      $a = filemtime("$dir/$a");
      $b = filemtime("$dir/$b");
      return $a < $b ? -1 : ($a === $b ? 0 : 1);
    };

    usort($_dirs, $cmp);
    usort($_files, $cmp);

    echo "<tr><td>[&plus;] <a href='$reqUri/..'>../</a></td><td></td><td></td></tr>\n";

    foreach ((array) @$_dirs as $item) {
      $link = "$reqUri/$item";
      echo "<tr><td>[&plus;] <a href='$link'>$item/</a></td><td></td><td></td></tr>\n";
    }

    foreach ((array) @$_files as $file) {
      $link = "$reqUri/$file";
      if (is_file("$dir/$file")) {
        $bytes = filesize("$dir/$file");
        echo "<tr><td>[&bull;] <a href='$link'>$file</a></td><td><span class=filesize>$bytes</span></td>";
      } else {
        echo "<tr><td>[&bull;] <s>$file</s></td><td><span class=filesize>0</span></td>";
      }
      echo "<td><a href='?view=$link'>view</a></td></tr>\n";
    }
    echo "</table>";

    $time = filemtime(__FILE__);
    echo "<script src='/?$time.js'></script></body></html>";
  }

  protected static function showError($message)
  {
    $template = "<html><meta name='viewport' content='width=device-width, initial-scale=1'>
                <title>$message</title><body>
                <p><code>>> $_SERVER[REQUEST_METHOD] " . htmlspecialchars(urldecode($_SERVER['REQUEST_URI'])) . " $_SERVER[SERVER_PROTOCOL]</code></p>
                <p><code><< $message</code></p></body>";
    exit($template);
  }

  protected static function isProtected($path)
  {
    $regex = Config::protected_paths;
    if (preg_match($regex, $path)) {
      return true;
    }
  }

  protected static function readFile($file, $ext = null)
  {
    $ext = $ext ?: strtolower(pathinfo($file, PATHINFO_EXTENSION));

    foreach (self::$type2Exts as $type => $exts) {
      $exts = explode(',', $exts);
      if (in_array($ext, $exts)) {
        header('content-type: ' . ($type[-1] === '/' ? $type . $ext : $type));
        $setMime = true;
        break;
      }
    }
    
    if (empty($setMime)) {
      header('content-type: application/octet-stream');
    }

    readfile($file);
    exit();
  }

  protected static function maybeFileRequest()
  {
    if ($file = $_GET['view']  ?? null) {
      $ext = pathinfo($file, PATHINFO_EXTENSION);

      $players['mp4'] = fn () => include 'plyr.php';

      $players['js'] = fn() => self::readFile(__DIR__ . '/' . $file, $ext);
      $players['css'] = $players['js'];

      if ($player = $players[$ext] ?? null) {
        $player();
        die;
      }

      die('no viewer for ' . $file);
    }

    if ($_SERVER['REQUEST_URI'] !== '/?' . filemtime(__FILE__) . '.js') {
      return;
    }

    header('Content-Type: application/javascript');
    header('Cache-Control: public, max-age=' . strtotime('6 month'));
    echo "// link: http://stackoverflow.com/a/20463021"
      . "\nfileSizeIEC = (a,b,c,d,e) => (b=Math,c=b.log,d=1024,e=c(a)/c(d)|0,a/b.pow(d,e)).toFixed(2) +' '+(e?'KMGTPEZY'[--e]+'iB':'Bytes')"
      . "\ndocument.querySelectorAll('.filesize').forEach((e) => e.innerHTML = fileSizeIEC(e.innerHTML))";
    die;
  }

  public static function isDot($ext, $file)
  {
    return pathinfo($file, PATHINFO_EXTENSION) === $ext;
  }
}

return !!Router::run();
