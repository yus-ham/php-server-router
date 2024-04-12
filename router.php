<?php
// @link https://github.com/yus-ham/php-server-router

// env vars
// ALT_SCRIPT=index.php,__app.html,etc...   comma separated

namespace Yusham\PhpServerRouter
{
    $_SERVER['SERVER_SOFTWARE'] = $_SERVER['SERVER_SOFTWARE'] .' | Apache conf enabled';


    class Config
    {
        const debug = false;
        const protected_paths = '~/\.git(\/.*)?$~';
        const show_files = true;
    }


    class Router
    {
        private static $docRoot;
        private static $prevPathInfo;
        private static $requestURI;
        private static $rewriteURI;
        private static $pathInfo = '';
        private static $parsedHtaccess = [];

        private static $type2Exts = [
            'text/html' => 'htm,html',
            'text/css' => 'css',
            'text/javascript' => 'js,mjs',
            'image/svg+xml' => 'svg',
            'image/' => 'png,gif,jpg,jpeg,webp',
            'audio/' => 'mp3',
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

            if (empty($_SERVER['QUERY_STRING'])) {
                $_SERVER['QUERY_STRING'] = "";
            }

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
            $currentUri = self::getFilePath(self::$requestURI);
            error_log("REQUEST_URI = $currentUri");

            $_SERVER['QUERY_STRING'] === 'phpinfo()' && die(phpinfo());

            if (self::isProtected($currentUri)) {
                http_response_code(403);
                self::showError('HTTP/1.1 403 Forbidden');
            }

            self::$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
            $path = self::$docRoot . $currentUri;

            if (is_dir($path)) {
                if (substr($currentUri, -1) !== '/') {
                    exit(header("Location: $currentUri/"));
                }
                return self::serveDir($path, $currentUri);
            }

            if (is_file($path)) {
                if (self::isDot('php', $path)) {
                    return self::serveScript($path, dirname($path));
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
            foreach (self::getScripts() as $script) {
                $script = "$dir/$script";
                if (is_file($script)) {
                    if (self::serveHtaccess($script, $dir, $currentUri) === null) {
                        continue;
                    }
                    self::$pathInfo = preg_replace(':^/'.preg_quote($script).':', '', self::$pathInfo);
                    return self::serveScript($script, $dir);
                }
            }
        }

        private static $redirectNum = 0;

        protected static function serveHtaccess($file, $dir, $currentUri)
        {
            if (!self::isDot('htaccess', $file)) {
                return false;
            }
            if (in_array($file, self::$parsedHtaccess)) {
                return;
            }
            self::$parsedHtaccess[] = $file;
            $stopParsing = false;
            $lines = file($file);

            foreach ($lines as $line) {
                @list($command, $args) = explode(' ', trim($line), 2);

                if ($command === '#phps-ignore') {
                    self::runPhpsIgnore($args);
                }
            }

            foreach ($lines as $line) {
                @list($command, $args) = explode(' ', trim($line), 2);

                if (strpos($command, 'Rewrite') !== 0) {
                    continue;
                }

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
                        if ($args[1] === '!-d' && is_dir(self::$docRoot . self::$requestURI)) {
                            return;
                        }
                        if ($args[1] === '!-f' && is_file(self::$docRoot . self::$requestURI)) {
                            return;
                        }
                    }
                }
                if ($command === 'RewriteRule') {
                    if (self::$redirectNum === 5) {
                        return;
                    }
                    $newURI = preg_replace(':' . ($args[0] === '.' ? '.+' : $args[0]) . ':', $args[1], ltrim(self::$pathInfo, '/'));
                    self::$prevPathInfo = substr(self::$requestURI, strlen($currentUri));
                    self::$requestURI = $currentUri . '/' . $newURI;
                    self::$pathInfo = '';
                    error_log("\n\n ==============================\nRedirected to: ". self::$requestURI ."\n");
                    self::$redirectNum++;
                    return self::serveURI();
                }
            }
        }

        protected static function runPhpsIgnore($path)
        {
            if (preg_match('#'.preg_quote(trim($path)).'#', self::$pathInfo)) {
                die(!http_response_code(404));
            }
        }

        protected static function serveDir($dir, $currentUri)
        {
            $dir = rtrim($dir, '/');
            if (false === self::serveIndex($dir, $currentUri)) {
                return;
            }
            http_response_code(404);
            if (Config::show_files) {
                exit(self::showFiles($dir));
            }
        }

        protected static function serveScript($script, $dir)
        {
            // PHP Built-in server fails to serve path that contains dot
            $hasDotInDir = strpos($dir, '.') !== false;
            if (!$hasDotInDir && self::isDot('php', $script) && self::$prevPathInfo === null) {
                return false;
            }

            if (self::$pathInfo !== null or self::$prevPathInfo !== null) {
                if (self::$prevPathInfo) {
                    $_SERVER['SCRIPT_NAME'] = str_replace(self::$prevPathInfo, '', $_SERVER['SCRIPT_NAME']);
                }
                $baseURI = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                $_SERVER['SCRIPT_NAME'] = ($baseURI === '/' ? '' : $baseURI) . substr($script, strlen($dir));
            } else {
                $_SERVER['SCRIPT_NAME'] .= $script;
            }

            $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
            self::includeScript($script);
        }

        protected static function includeScript($script)
        {
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
            return self::$requestURI = str_replace('//', '/', self::getFilePath($_SERVER['REQUEST_URI']));
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
                @filemtime("$dir/$file");
                if (is_dir("$dir/$file")) {
                    @$_dirs[] = $file;
                } else {
                    @$_files[] = $file;
                }
            }

            $reqUri === '/' OR empty($reqUri) OR print("<tr><td>[&plus;] <a href='$reqUri..'>../</a></td><td></td><td></td></tr>\n");

            foreach ((array) @$_dirs as $item) {
                $link = "$reqUri$item/";
                echo "<tr><td>[&plus;] <a href='$link'>$item/</a></td><td></td><td></td></tr>\n";
            }

            foreach ((array) @$_files as $file) {
                $link = "$reqUri$file";
                if (is_file("$dir/$file")) {
                    $bytes = filesize("$dir/$file");
                    echo "<tr><td>[&bull;] <a href='$link'>$file</a></td><td><span class=filesize>$bytes</span></td>";
                } else {
                    echo "<tr><td>[&bull;] <s>$file</s></td><td><span class=filesize>0</span></td>";
                }
                echo "<td><a href='?view=$link'>view</a></td></tr>\n";
            }
            echo "</table>";

            echo "<script src='/?~/global.js'></script></body></html>";
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

            header('expires: '. date(DATE_RFC7231, time() + $maxAge = 31536000));
            header('cache-control: max-age='. $maxAge);
            readfile($file);
            exit();
        }

        protected static function maybeFileRequest()
        {
            foreach ($_GET as $key => $v) {
                if (strpos($key, '~/')) {
                    $file = substr($key, 1);
                    break;
                }
            }

            if (empty($file)) {
                return;
            }

            if ($file === '/global.js') {
                header('Content-Type: application/javascript');
                header('Cache-Control: public, max-age=' . strtotime('6 month'));
                die("// @link http://stackoverflow.com/a/20463021\n"
                   ."fileSizeIEC = (a,b,c,d,e) => (b=Math,c=b.log,d=1024,e=c(a)/c(d)|0,a/b.pow(d,e)).toFixed(2) +' '+(e?'KMGTPEZY'[--e]+'iB':'Bytes')\n"
                   ."document.querySelectorAll('.filesize').forEach((e) => e.innerHTML = fileSizeIEC(e.innerHTML))");
            }

            $ext = pathinfo($file, PATHINFO_EXTENSION);

            $players['mp4'] = fn () => include 'plyr.php';
            $players['js'] = fn () => self::readFile(__DIR__ . '/' . $file, $ext);
            $players['css'] = $players['js'];

            $player = $players[$ext] ?? fn () => 'no viewer for ' . $file;
            die($player());
        }

        public static function isDot($ext, $file)
        {
            return pathinfo($file, PATHINFO_EXTENSION) === $ext;
        }
    }
    return !!Router::run();
}
