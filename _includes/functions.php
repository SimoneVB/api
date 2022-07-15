<?php
    //  Sets custom error handler
    function customErrorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }
        $errorNum = $errno;                     // sets last Error
        $errorDesc = htmlspecialchars($errstr);
        $errorLine = $errline;
        $errorFile = $errfile;
        return true;                            // prevent PHP internal error handler execution
    }
    function noErrors() {
      // enables custom error handler
      $errHandler = set_error_handler("customErrorHandler");
    }

    // allow cors all origins
    function allowCors() {
        header('Access-Control-Allow-Origin: *');
    }

    //  functions class
    class functions {
        public $name;
        public $url;
        public $protocol;
        public $port;
        public $currentUrl;
        public $path;
        public $params;
        public $queryString;
        public $phpVersion;
        public $isGoogleBot;

        #region Constructors
        function __construct() {
            // constructor
            $this->name = strtolower($_SERVER['SERVER_NAME']);              // sets domain name, url and port
            $this->url = sprintf(
                "%s://%s%s/",
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                $_SERVER['SERVER_NAME'],
                ($_SERVER['SERVER_PORT'] == 80) ? '' : ':' . $_SERVER['SERVER_PORT']
            );
            $this->protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';
            $this->port = $_SERVER['SERVER_PORT'];

            // current url
            $this->currentUrl = sprintf(
                "%s://%s%s%s",
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                $_SERVER['SERVER_NAME'],
                ($_SERVER['SERVER_PORT'] == 80) ? '' : ':' . $_SERVER['SERVER_PORT'],
                $_SERVER['REQUEST_URI']
            );

            // current queryStirng
            $uri = $_SERVER['REQUEST_URI'];
            $url_components = parse_url($uri);
            $this->queryString = "";
            if (isset($url_components['query'])) $this->queryString = $url_components['query'];

            // current params
            $uri = $_SERVER['REQUEST_URI'];
            $url_components = parse_url($uri);
            if (isset($url_components['query']))
                $this->params = explode("&", $url_components['query']);
            else
                $this->params = [];

            // current path(s)
            $uri = $_SERVER['REQUEST_URI'];
            if ($uri[0] == "/") $uri = substr($uri, 1);         // removes first and last back-slash
            if (substr($uri, -1) == "/") $uri = substr($uri, 0, strlen($uri) - 1);
            if ($this->queryString !== "") $uri = str_replace("?" . $this->queryString, "", $uri);      // removes querystring
            if (substr($uri, -1) == "/") $uri = substr($uri, 0, strlen($uri) - 1);
            $this->path = explode("/", $uri);   // splits

            // detect GoogleBot
            $this->isGoogleBot = false;
            if(strstr(strtolower($_SERVER['HTTP_USER_AGENT']), "googlebot")) $this->isGoogleBot = true; // detect GoogleBot (see: https://www.evemilano.com/user-agent-google/)
            // PHP
            $this->phpVersion = phpversion();
        }
        #endregion Constructors

        #region Get/Post Functions
        function getPostJSON() {
          // gets JSON input from POST, and decodes it into an object we can navigate
          if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
          return json_decode(file_get_contents('php://input'), false);
        }
        function getAllPost()  {  
            // returns all post fields as array
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return [];
            return $_POST;
        }
        function getAllParams() {
            // returns all query-string parameters as array
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') return [];
            $urlCmp = parse_url($_SERVER['REQUEST_URI']);
            parse_str($urlCmp['query'], $retVal);
            return $retVal;
        }
        function getParam($paramName) {
            // returns a specific Url param
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') return '';
            $retVal = "";
            $paramName = trim(strtolower($paramName));
            if ($paramName == "") return "";
            $xuri = $_SERVER['REQUEST_URI'];
            $url_components = parse_url($xuri);
            if (isset($url_components['query']))
            {
                parse_str($url_components['query'], $xparams);
                if (isset($xparams[$paramName])) $retVal = $xparams[$paramName];
            }
            return $retVal;
        }
        #endregion Get/Post Functions

        #region Server Functions
        function serverIP() {
          return $_SERVER['SERVER_ADDR'];
        }
        function isLocalhost() {
          return ($this->serverIP() == '::1');
        }
        function getHeader($header) {
          // returns a specific header
          $header = trim($header);
          $headers = apache_request_headers();
          if (isset($headers[$header])) return($headers[$header]);
          return '';
        }
        #endregion Server Functions

        #region Client Functions
        function redirect($path) {
            // redirects to path
            $path = trim($path);
            if ($path != "") {      // removes initial / from path
                if (substr($path, 0, 1) == "/") $path = (substr($path, 1));
            }
            header("Location: " . "http://". $_SERVER['SERVER_NAME'] . ($this->port == 80 ? "" : ":" . $this->port) . "/" . $path, true, 301);
        }
        function noCache() {
            // no-cache
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
        function clientIP() {
          return $_SERVER['REMOTE_ADDR'];
        }
        function sendError($statusCode, $statusDescription, $asJSON = false) {
          // sends an error to the client
          header('HTTP/1.1 ' . $statusCode . ' ' . $statusDescription);

          if ($asJSON) {
            // sends error as JSON
            $res = new stdClass();
            $res->status=$statusCode;
            $res->error=$statusDescription;
            $this->printJSON($res);
          } else {
            // sends error as text
            echo $statusDescription;
          }
          exit;
        }
        function sendJSONResponse($data) {
          // sends a JSON as an api response
          $res = new stdClass();
          $res->status=200;
          $res->result='OK';
          $res->data = $data;
          $this->printJSON($res, true);
          exit;
        }
        #endregion Client Functions

        #region Cookies Functions
        function setCookie($cookieName, $value, $expirationDays) {
            // writes a cookie
            // setcookie(name, value, expire, path, domain, secure, httponly);
            $cookieName = trim($cookieName);
            if ($cookieName == "") return false;

            // calculates expiration time (min 1")
            $expS = 1;
            if (!is_numeric($expirationDays)) $expirationDays = 0;
            if ($expirationDays < 0) $expirationDays = 0;
            if ($expirationDays > 0) $expS = $expirationDays * 24*60*60;
            $exp = time() + $expS;

            // path (as default)
            $path = "/";

            setcookie($cookieName, $value, $exp, $path);
            return true;
        }
        function getCookie($cookieName) {
            // reads a cookie
            $retVal = "";
            $cookieName = trim($cookieName);
            if ($cookieName == "") return "";
            try {
                $retVal = $_COOKIE[$cookieName];
            }
            catch(Exception $var) {
                $retVal = "";
            }
            return $retVal;
        }
        function getAllCookie() {
            // returns all cookies
            return $_COOKIE;
        }
        #endregion Cookies Functions

        #region File System Functions
        function readTextFile($fileName) {
            // readTextFile
            $retVal = "";
            $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder

            if (file_exists($fileName)) {
                $inFile = fopen($fileName, "r") or die ('File Not Exist!!');
                $lnFile = filesize($fileName);
                if ($lnFile == 0) $lnFile = 1;
                $retVal = fread($inFile, $lnFile);
                fclose($inFile);
            }
            return $retVal;
        }
        function writeTextFile($fileName, $content) {
            // writeTextFile
            $retVal = "";
            $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder
            $inFile = fopen($fileName, "w") or die ('File Not Exist!!');
            $retVal = fwrite($inFile, $content);
            fclose($inFile);
            return $retVal;
        }
        function appendTextFile($fileName, $content) {
            // appendTextFile
            $retVal = "";
            $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder
            $inFile = fopen($fileName, "a") or die ('File Not Exist!!');
            $retVal = fwrite($inFile, $content . "\n");
            fclose($inFile);
            return $retVal;
        }
        function csvToJson($fileName, $separator) {
          // csvToJson
          $retVal = "";
          $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder

          if (file_exists($fileName)) {
            if (!($fp = fopen($fileName, 'r'))) {
              die('File Not Exist!!');
            }

            // read csv headers
            $key = fgetcsv($fp, '1024', $separator);

            // parse csv rows into array
            $json = array();
              while ($row = fgetcsv($fp, '1024', $separator)) {
              $json[] = array_combine($key, $row);
            }

            // release file handle
            fclose($fp);
            $retVal = json_encode($json);
          }
          return $retVal;
        }
        function fileExists($fileName) {
          // returns true if the specified file exists
          $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder
          return file_exists($fileName);
        }
        function deleteFile($fileName) {
          // deletes a file
          if (!$this->fileExists($fileName)) return false;
          $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;    // points to the root folder
          return unlink($fileName);
        }
        function renameFile($fileName, $newFileName) {
          // renames/moves a file
          if (!$this->fileExists($fileName)) return false;
          $fileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $fileName;          // points to the root folder
          $newFileName = $_SERVER['DOCUMENT_ROOT'] . "/" . $newFileName;
          return rename($fileName, $newFileName);
        }
        function listFiles($folder) {
          // lists files into the specified folder, except . and ..
          /* usage:
            $files = $fun->listFiles("orders/incoming/");
            foreach ($files as &$file) {
              print('<br>' . $file);
            }
          */
          $folder = $_SERVER['DOCUMENT_ROOT'] . "/" . $folder;        // points to the root folder
          $files = scandir($folder);

          $ret = [];
          foreach ($files as &$file) {
            if (($file != '.') && ($file != '..')) {
              array_push($ret, $file);
            }
          }
          return $ret;
        }
        #endregion File System Functions

        #region Mail Functions
        function sendMail($sender, $smtp, $receiverEMail, $subject, $body, $html) {
          // sends an email
          /*
            usage:

              $sender = new stdClass();
              $sender->name = 'Orders SmartISBN';
              $sender->email = 'orders@smartisbn.com';

              $smtp = new stdClass();
              $smtp->host = 'mail.smartisbn.com';
              $smtp->port = 25;
              $smtp->user = 'orders@smartisbn.com';
              $smtp->password = '<password>';

              if ($fun->sendMail($sender, $smtp, "info@smartisbn.com", "prova php", "prova da php")) {
                echo 'ok';
              } else {
                echo 'errore';
              }
          */

          // normalizes/validates sender
          $senderName = trim($sender->name);
          $senderMail = strtolower(trim($sender->email));
          if ($senderMail == '') return false;
          if ($senderName == '') $senderName = $senderMail;

          // normalizes/validates SMTP
          $serverName = strtolower(trim($smtp->host ));
          $serverPort = $smtp->port;
          $serverUser = strtolower(trim($smtp->user));
          $serverPassword = $smtp->password;

          if ($serverName == '') return false;
          if ($serverPort <= 0) $serverPort = 25;
          if ($serverUser == '') $serverUser = $senderMail;
          if ($serverPassword == '') return false;

          // normalizes/validate receiver, subject and body
          $receiverEMail = strtolower(trim($receiverEMail));
          if ($receiverEMail == '') return false;
          $subject = trim($subject);
          $body = trim($body);

          // creates the header
          $headers = 'From: '. $senderName. ' <'. $senderMail .'>';

          // if is html mail
          if ($html) {
            $headers .= 'MIME-Version: 1.0n';
            $headers .= 'Content-Type: text/html; charset="iso-8859-1"n';
            $headers .= 'Content-Transfer-Encoding: 7bitnn';
          }

          ini_set("SMTP", $serverName);
          ini_set("smtp_port ", $serverPort);
          ini_set("sendmail_from", $serverUser);
          ini_set("password ", $serverPassword);
          return (mail($receiverEMail, $subject, $body, $headers));
        }
        #endregion Mail Functions

        #region Images
        function imageUrlToBase64($url) {
          // returns an image base64 from an image url
          return 'data:image/png;base64,' . base64_encode(file_get_contents($url));
        }
        #endregion Images

        #region JSON
        function isJson($string) {
          // returns true if $string is JSON
           json_decode($string);
           return json_last_error() === JSON_ERROR_NONE;
        }
        #endregion JSON
      
        #region Utility Functions
        function shortityNumber($num, $asFloat, &$sign, &$addText) {
          /*  esegue lo shortify di un numero
                es: 12      =>      +10,2
                    154     =>      +150
                    1751    =>      +1,7k
                    21415   =>      +21,4k
                    3.541.213   =>  +3,5MLN
              se asFloat=false, lo shortify arrotonda all'intero
                es: 12      =>      +10
                    154     =>      +150
                    1751    =>      +1k
                    21415   =>      +21k
                    3.541.213   =>  +3MLN

              restituisce il valore calcolato
              restituisce in addText, la parte testuale del valore
          */

          $ret = 0;
          $addText = '';
          $sign = '';

          if (intval($num) == 0) return 0;          // zero
          $sign = (abs($num) == $num) ? '+' : '<';  // detects sign and abs num
          $num = abs(intval($num));

          // MLN
          if ($num > 1000000) {
            $ret = $num / 1000000;
            if (!$asFloat)
              $ret = round($ret, 0);
            else
              $ret = round($ret, 1);
            $addText = 'MLN';
            $num = -1;
          }

          // K
          if ($num > 1000) {
            $ret = $num / 1000;
            if (!$asFloat)
              $ret = round($ret, 0);
            else
              $ret = round($ret, 1);
            $addText = 'K';
            $num = -1;
          }

          // > 100
          if ($num > 100) {
            $ret = $num / 100;
            $ret = round($ret, 1);
            $ret = ($ret * 100);
            $addText = '';
            $num = -1;
          }

          // > 10
          if ($num > 10) {
            $ret = $num / 10;
            $ret = round($ret, 1);
            $ret = ($ret * 10);
            $addText = '';
            $num = -1;
          }

          // > 0
          if ($num > 0) {
            $ret = $num;
            $addText = '';
            $num = -1;
          }
          //$ret = str_replace('.', ',', $ret);
          return $ret;
        }
        function printWords($word, $value) {
            // prints a line
            $word = trim($word);

            $eol = "<br>";
            print("<b>" . $word . "</b>: ");

            if (is_array($value)) {
                // prints an array
                $value = json_encode($value);           // prints array as JSON
                print_r($value);
                print($eol);
                return true;
            }

            if (is_object($value)) {
                // prints object
                $value = json_encode($value);
                print($value . $eol);
                return true;
            }

            if (is_bool($value)) {
                // prints boolean
                $value = $value ? "true" : "false";
                print($value. $eol);
                return true;
            }

            // prints string
            $value = trim($value);
            print($value . $eol);
            return true;
        }
        function printLine($value) {
            // prints a line
            $eol = "<br>";

            if (is_array($value)) {
                // prints an array
                $value = json_encode($value);           // prints array as JSON
                print_r($value);
                print($eol);
                return true;
            }

            if (is_object($value)) {
                // prints object
                $value = json_encode($value);
                print($value . $eol);
                return true;
            }

            if (is_bool($value)) {
                // prints boolean
                $value = $value ? "true" : "false";
                print($value. $eol);
                return true;
            }

            // prints string
            $value = trim($value);
            print($value . $eol);
            return true;
        }
        function printJSON($value, $beautify = false) {
          // prints a JSON
          if ($beautify)
            print(json_encode($value, JSON_PRETTY_PRINT));
          else
            print(json_encode($value));
        }
        function printRemLine($value) {
            // prints a line
            $eol = "\n";

            if (is_array($value)) {
                // prints an array
                print("<!--" . $eol);
                $value = json_encode($value);           // prints array as JSON
                print_r($value);
                print($eol);
                print("-->" . $eol);
                return true;
            }

            if (is_object($value)) {
                // prints object
                $value = json_encode($value);
                print("<!--" . $eol);
                print($value . $eol);
                print("-->" . $eol);
                return true;
            }

            if (is_bool($value)) {
                // prints boolean
                $value = $value ? "true" : "false";
                print("<!--" . $eol);
                print($value. $eol);
                print("-->" . $eol);
                return true;
            }

            // prints string
            $value = trim($value);
            print("<!--" . $eol);
            print($value . $eol);
            print("-->" . $eol);
            return true;
        }
        function printRemWords($word, $value) {
            // prints a line
            $word = trim($word);

            $eol = "\n";
            print("<!--" . $eol);
            print(strtoupper($word) . "\n");

            if (is_array($value)) {
                // prints an array
                $value = json_encode($value);           // prints array as JSON
                print_r($value);
                print($eol);
                print("-->" . $eol);
                return true;
            }

            if (is_object($value)) {
                // prints object
                $value = json_encode($value);
                print($value . $eol);
                print("-->" . $eol);
                return true;
            }

            if (is_bool($value)) {
                // prints boolean
                $value = $value ? "true" : "false";
                print($value. $eol);
                print("-->" . $eol);
                return true;
            }

            // prints string
            $value = trim($value);
            print($value . $eol);
            print("-->" . $eol);
            return true;
        }
        #endregion Utility Functions
    }
?>
