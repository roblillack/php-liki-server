<?php
require('bsLikiBackend.class.php');

class bsLiki {
  var $backend = false;
  var $baseUrl = false;
  var $activePage = false;
  var $specialPage = false;
  var $legacyMode = false;
  var $key = false;
  var $passwordProtected = false;
  var $username = '';
  var $password = '';
  var $dataDir = 'data';
  var $maximalPictureWidth = false;
  var $likiTitle = 'The Liki';

  function sendHeaders() {
    header("Content-type: text/html; charset=UTF-8");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    echo('<'.'?'.'xml version="1.0" encoding="utf-8" '.'?'.">\n");
    echo("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" ".
         "\"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n");
  }

  function quit() {
    if ($this->backend !== false) {
      $this->backend->closeConnection();
    }
    exit;
  }

  /**
   * returns global environment variables in a way
   * compatible with different php versions.
   */
  function getRequest($varname, $slashed = true) {
    $v = '';

    if (isset($_GET[$varname])) {
      $v = $_GET[$varname];
    } elseif (isset($_POST[$varname])) {
      $v = $_POST[$varname];
    }

    if (get_magic_quotes_gpc() == 0) {
      if ($slashed)
        $v = addslashes($v);
    } else {
      if (!$slashed)
        $v = stripslashes($v);
    }

    return $v;
  }

  function sendRecentChangesHeader() {
    $str = "";
    foreach ($this->backend->getRecentChanges(10) as $p) {
      $str .= $p['name']."/".$p['howlongago'].",";
    }
    header('X-LIKI-RecentChanges: '.rawurlencode(substr($str,0,strlen($str)-1)));
  
    $str = "";
    foreach ($this->backend->getRecentVisits(10) as $p) {
      $str .= $p['name']."/".$p['howlongago'].",";
    }
    header('X-LIKI-RecentVisits: '.rawurlencode(substr($str,0,strlen($str)-1)));
  }

  function isLegitPageName($str) {
    $followers = 0;
    for ($i = 0; $i < strlen($str); $i++) {
      $value = ord($str[$i]);
      // check for valid utf-8
      if ($value >= 240) {
        if ($followers > 0) return false; else $followers = 3;
      } elseif ($value >= 224) {
        if ($followers > 0) return false; else $followers = 2;
      } elseif ($value >= 192) {
        if ($followers > 0) return false; else $followers = 1;
      } elseif ($value >= 128) {
        if ($followers < 1) return false; else $followers--;
      } elseif ($value < 33 || strstr("\"'[]%", chr($value)))
        // control/unwanted characters
        return false;
    }
    return true;
  }

  function sendRSSFeed() {
    header('Content-type: text/xml; charset=UTF-8');
    echo '<' . '?' .'xml version="1.0" encoding="UTF-8"' . '?' . '>' . "\n";
    echo '<rss version="2.0">' . "\n";
    echo " <channel>\n";
    echo "  <title>Changelog for &#x201c;{$this->likiTitle}&#x201d;</title>\n";
    echo "  <link>".htmlspecialchars($this->baseUrl, ENT_QUOTES)."</link>\n";
    echo "  <description>An automatic log of changes to the Liki.</description>\n";
    //echo "  <language>$lang</language>\n";
    //echo "  <copyright>$copyright</copyright>\n";
    //echo "  <pubDate>$pubDate</pubDate>\n";
    if ($log = $this->backend->getDetailedChangeLog(30)) foreach ($log as $e) {
      $changelog = "";
      $linesDeleted = 0;
      $linesInserted = 0;
      $linediff = diff(explode("\n", $e['content_before']), explode("\n", $e['content_after']));
      foreach ($linediff as $number => $changeset) {
        if (!is_array($changeset)) continue;
        $before = !empty($changeset['d']) ? str_replace("\n", "&#x23ce;<br />\n", htmlspecialchars(implode("\n", $changeset['d']))) : "";
        $after = !empty($changeset['i']) ? str_replace("\n", "&#x23ce;<br />\n", htmlspecialchars(implode("\n", $changeset['i']))) : "";
        $linesDeleted += count($changeset['d']);
        $linesInserted += count($changeset['i']);
        $paragraph = "";
        foreach (diff(explode(" ", $before), explode(" ", $after)) as $d) {
          if (is_array($d)) {
            $paragraph .= !empty($d['d']) ? "<s style='background-color:#fdd;'>" . implode(' ', $d['d']) . "</s> " : '';
            $paragraph .= !empty($d['i']) ? "<b style='background-color:#dfd;'>" . implode(' ', $d['i']) . "</b> " : '';
          } else {
            $paragraph .= $d . ' ';
          }
        }
        if (trim($paragraph) != "") {
          for ($i = 3; $i >= 1; $i--) {
            if (array_key_exists($number - $i, $linediff) && !is_array($linediff[$number - $i])) {
              $paragraph = htmlspecialchars($linediff[$number-$i])."<br />$paragraph";
            }
          }
          for ($i = 1; $i <= 3; $i++) {
            if (array_key_exists($number + $i, $linediff) && !is_array($linediff[$number+$i])) {
              $paragraph .= "<br />" . htmlspecialchars($linediff[$number+$i]);
            }
          }
          $changelog .= "<p>$paragraph</p>\n";
        }
      }
      if ($changelog) {
        echo "  <item>\n";
        echo "   <title>" . htmlspecialchars($e['name']. " (-$linesDeleted +$linesInserted)") . "</title>\n";
        echo "   <description><![CDATA[$changelog]]></description>\n";
        //echo "   <author>Anonymous</author>\n";
        echo "   <guid isPermaLink='false'>" . md5($e['timestamp_start'].$e['timestamp_end'].$e['name']) . "</guid>\n";
        echo "   <link>" . htmlspecialchars($this->baseUrl.'/'.urlencode($e['name'])) . "</link>\n";
        echo "   <pubDate>" . date("r", $e['timestamp_end']) . "</pubDate>\n";
        echo "  </item>\n";
      }
    }
    echo ' </channel>' . "\n";
    echo '</rss>' . "\n";
  }


  function createIndexPage() {
    $index = "# Liki Pages sorted alphabetically\n".
             "switch to [TimeIndex] or [PictureIndex].\n";
    $list = $this->backend->getPageList();
    foreach ($list as $i)
      $index .= "- [$i]\n";
    return $index;
  }

  function createTimeIndexPage() {
    $index = "# Liki Pages sorted by modification time\n".
             "switch to alphabetical [[Index]].\n";
    $list = $this->backend->getRecentChanges(false);
    foreach ($list as $i)
      $index .= "- [${i['name']}] (${i['howlongago']})\n";
    return $index;
  }

  function createSearchPage($search) {
    $index = "# Search for \"".htmlentities($search)."\"\n";
    $list = $this->backend->getPageNamesContaining($search);
    if ($list !== false)
      foreach ($list as $i)
        $index .= "- [$i]\n";
    return $index;
  }

  function createPictureIndex($deleted = false) {
    /** @todo delete old pics ...*/
    if (!is_dir($this->dataDir)) {
      return "# Data Directory does not exist.";
    }
    $dirhandle = opendir($this->dataDir);
    if ($dirhandle === false) {
      return "# Data Directory could not be opened.";
    }
    $pagelist = $this->backend->getPagesContaining($this->baseUrl);
    $indexContent = "# Pages containung uploaded pictures\n".
                    "switch to [Index normal index].\n";
    while ($filename = readdir($dirhandle)) {
      if ($filename != "." && $filename != "..") {
        if (is_file($this->dataDir."/$filename")) {
          $pagesShowingThisPic = array();
          foreach ($pagelist as $i) {
            if (strpos($i['content'], $filename) !== false) {
               $pagesShowingThisPic[] = $i['name'];
            }
          }
          if ((count($pagesShowingThisPic) > 0 && !$deleted)
              || (count($pagesShowingThisPic) === 0 && $deleted)) {
            $indexContent .= "\n".$this->baseUrl."/".$this->dataDir."/$filename\n";
            foreach ($pagesShowingThisPic as $k => $v)
              $indexContent .= ($k == 0 ? "| " : ", ") . "[$v]";
            $indexContent .= "\n----\n";
          }
        }
      }
    }
    closedir($dirhandle);
    return $indexContent;
  }

  function getPage($page) {
    if (strtolower($page) == 'index') {
      $p = array('content' => $this->createIndexPage(),
                 'timestamp_change' => time());
    } elseif (strtolower($page) == 'timeindex') {
      $p = array('content' => $this->createTimeIndexPage(),
                 'timestamp_change' => time());
    } elseif (strtolower($page) == 'search') {
       $p = array('content' => $this->createSearchPage($this->getRequest("q", false)),
                  'timestamp_change' => time());
    } elseif (strtolower($page) == 'pictureindex') {
       $p = array('content' => $this->createPictureIndex(),'timestamp_change' => time());
    } elseif (strtolower($page) == 'deletedpictures') {
      $p = array('content' => $this->createPictureIndex(true), 'timestamp_change' => time());
    } else {
      $p = $this->backend->getPage($page);
      if ($p === false) {
        $p = array('content'   => "# Error loading page $page",
                   'timestamp_change' => '1');
      }
    }

    return $p;
  }

  function getParagraphType($content) {
    if (preg_match('/^[\#\*\-\+\"\'\;\|\!] /', $content)) return $content[0];
    else if (preg_match('/^---+\s*$/', $content)) return 'line';
    else if (preg_match('/^http\:\/\/[^\s\"\']+\.(bmp|gif|jpg|jpeg|png)\s*$/i', $content)) return 'image';
    else if (preg_match('/^http\:\/\/[^\s\"\']+\.(mp3|ogg|aac|mpc|wma)\s*$/i', $content)) return 'music';
    else if (preg_match('/^http\:\/\/[^\s\"\']+\.(avi|mpg|wmv|mov|asf|flv)\s*$/i', $content)) return 'video';
    else return '';
  }

  function cleanParagraph($content) {
    $content = preg_replace('/(^|\n)\ \ ([^\n]+)/', '$1$2', $content);
    $content = preg_replace('/^[\#\*\-\+\"\'\;\|\!] /', '', $content);
    return $content;
  }

  function formatParagraph($content) {
    $p = htmlspecialchars($content);
    // some symbols
    $p = preg_replace('/(^|[^-])--(?=([^-]|$))/', '$1&ndash;', $p);
    $p = preg_replace('/(^|[^-])---(?=([^-]|$))/', '$1&mdash;', $p);
    // _emphasized_, *bold*, -striked-
    $p = preg_replace('/(\W|^)\*(\S[\S\ ]*?\S)\*(?=(\W|$))/', '$1<strong>$2</strong>', $p);
    $p = preg_replace('/(\W|^)\-(\S[\S\ ]*?\S)\-(?=(\W|$))/', '$1<s>$2</s>', $p);
    $p = preg_replace('/(\W|^)\_(\S[\S\ ]*?\S)\_(?=(\W|$))/', '$1<em>$2</em>', $p);
    // externe links
    $p = preg_replace('/([\s]|^)(http\:\/\/[^\s\"\'\(\)\[\]\{\}]+)(?=(\s|$))/', '$1<a class="external" href="$2">$2</a>', $p);
    // externe links (mit text)
    $p = preg_replace('/\[(http\:\/\/[\S]+)\]/', '<a class="external" href="$1">$1</a>', $p);
    $p = preg_replace('/\[(http\:\/\/[\S]+)\ ([\S][\S\ ]*?[\S]+)\]/', '<a class="external" href="$1">$2</a>', $p);
    // liki-seiten
    $p = preg_replace('/\[\[?([^\'\"\]\[\%\s\/\\\\]+)\]?\]/', '<a class="internal" href="' . $this->baseUrl . '/$1">$1</a>', $p);
    // liki-seiten (mit text)
    $p = preg_replace('/(^|[^\\\\])\[\[?([^\'\"\]\[\%\s\/\\\\]+)\ ([\S][\S\ ]*?[\S]+)\]?\]/', '$1<a class="internal" href="' . $this->baseUrl . '/$2">$3</a>', $p);
    // colors
    $p = preg_replace('/\{(aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|purple|red|silver|teal|white|yellow|#[0-9a-f]{3}([0-9a-f]{3})?)\ (.*?[^\\\\])\}/i', '<span style="color:$1;">$3</span>', $p);
    // forced line breaks
    $p = preg_replace('/\ \/\/\ *[\r\n]/', "<br />", $p);
    // escaping
    $p = preg_replace('/\\\\(.)/', '$1', $p);

    return $p;
  }

  function formatCodeParagraph($content) {
    return $content;
  }

  function getFormattedPage($page) {
    $page = $this->getPage($page);
    $p = $page['content'];

    // one line paragraphs (i.e. section headings)
    $p = preg_replace('/(^|\n)([\#\*])\ ([^\n]+)/', "$1\n$2 $3\n", $p);
    // multiline paragraphs
    $p = preg_replace('/(^|\n)([\-\+\"\'\;\|\!]\ ([^\n](\n\ [^\n]|[^\n])+))/', "\n$1$2\n", $p);
    // lines
    $p = preg_replace('/(^|\n)---+\ *\n/', "$1---\n\n", $p);

    $p = preg_replace('/^\s*/', '', $p);
    $paragraphs = preg_split('/\n\s*\n/', $p);
    $output = "";
    for ($i = 0; $i < count($paragraphs); $i++) {
      $p = $paragraphs[$i];
      $type = $this->getParagraphType($p);
      $content = $this->cleanParagraph($p);
      switch ($type) {
        case '':
          // normal text paragraphs
          $output .= "<p>" . $this->formatParagraph($content) . "</p>\n";
          break;
        case '-': case '+':
          // lists
          if ($i == 0 || $this->getParagraphType($paragraphs[$i-1]) != $type) {
            if ($type == '-') $output .= "<ul>\n";
            else $output .= "<ol>\n";
          }
          $output .= " <li>" . $this->formatParagraph($content) . "</li>\n";
          if ($i == count($paragraphs) - 1 || $this->getParagraphType($paragraphs[$i+1]) != $type) {
            if ($type == '-') $output .= "</ul>\n";
            else $output .= "</ol>\n";
          }
          break;
        case '#':
          $output .= "\n\n<h1>" . $this->formatParagraph($content) . "</h1>\n";
          break;
        case '*':
          $output .= "\n\n<h2>" . $this->formatParagraph($content) . "</h2>\n";
          break;
        case '"':
          $output .= "<blockquote>" . $this->formatParagraph($content) . "</blockquote>\n";
          break;
        case '\'':
          $output .= "<blockquote class=\"comment\">"
            . $this->formatParagraph($content) . "</blockquote>\n";
          break;
        case ';':
          $output .= "<pre>" . $this->formatCodeParagraph($content) . "</pre>\n";
          break;
        case '|':
          $output .= '<p style="text-align: center;">' . $this->formatParagraph($content) . "</p>\n";
          break;
        case 'image':
          $output .= '<img src="' . $content . '" alt="" class="centerpic" />'."\n";
          break;
        case 'music':
          $matches = preg_match('/^.*\/[0-9]+-.+--([^\s\"\'\/]+\.([a-z0-9]+))\s*$/i', $content);
          $output .= '<p><a href="' . $content . '" class="music">' . $matches[1] . "</a></p>\n";
          break;
        case 'video':
          $matches = preg_match('/^.*\/[0-9]+-.+--([^\s\"\'\/]+\.([a-z0-9]+))\s*$/i', $content);
          $output .= '<p><a href="' . $content . '" class="video">' .  $matches[1] . "</a></p>\n";
          break;
        case 'line':
          $output .= "<hr />\n";
          break;
        case '!':
          $output .= $content;
          break;
        default:
          $output .= "<br/><b>Type: [".$type."]</b>";
          $output .= "<pre style='border: 1px solid red;'>".$content."</pre>";
      }
    }

    $page['content'] = $output;
    return $page;
  }

  function sendUploadForm() {
    $this->sendHeaders();
?>
<html>
 <head>
  <title>liki</title>
  <link rel="stylesheet" type="text/css" href="<?php echo $this->baseUrl;?>/liki.css" />
  <script type="text/javascript" src="<?php echo $this->baseUrl;?>/bs_liki.js"></script>
 </head>
 <body id="uploadbody">
  <form id="uploadform" action="<?php echo $this->baseUrl.'/'.$this->activePage;?>" method="post" enctype="multipart/form-data">
   <input type="hidden" name="action" value="uploadpic" />
   <input type="file" name="userfile" value="" onchange="doUpload();" size="48"/>
  </form>
 </body>
</html>
<?php
    $this->quit();
  }

  function resizePicture($fullname, $basename = false) {
    if (!function_exists("imagetypes")) {
      return false;
    }

    if (!$basename) {
      $basename = md5(time().$fullname);
    }
    if (!file_exists($fullname) || !is_readable($fullname)) {
      return false;
    }

    $origtype = false;
    if (ImageTypes() & IMG_JPG) {
      $original = ImageCreateFromJpeg($fullname);
      $origtype = IMG_JPG;
    }
    if (!$original && ImageTypes() & IMG_GIF) {
      $original = ImageCreateFromGif($fullname);
      $origtype = IMG_GIF;
    }
    if (!$original && ImageTypes() & IMG_PNG) {
      $original = ImageCreateFromPng($fullname);
      $origtype = IMG_PNG;
    }

    if (!$original) {
      return false;
    }
    $ow = ImageSX($original);
    $oh = ImageSY($original);
    $w = $this->maximalPictureWidth;
    if ($ow < $w) {
      /* We recreate smaller pictures, too, to prevent clients from attacks */
      $w = $ow;
    }
    $h = ($oh * $w) / $ow;
    $copy = ImageCreateTrueColor($w, $h);
    ImageCopyResampled($copy, $original, 0, 0, 0, 0, $w, $h, $ow, $oh);
    @unlink($fullname);
    
    $name = false;
    if ($origtype == IMG_PNG) {
      $name = $basename.'.png';
      ImagePNG($copy, $name);
    } elseif ($origtype == IMG_GIF) {
      $name = $basename.'.gif';
      ImageGIF($copy, $name);
    } else {
      $name = $basename.'.jpg';
      ImageJPEG($copy, $name);
    }
    return $name;
  }

  function handleFileUpload() {
    $result = 'Failure';
    $url = '';
    if (isset($_FILES['userfile']) && !empty($_FILES['userfile']['tmp_name'])) {
      $tmp = $_FILES['userfile']['tmp_name'];
      $dot = strrpos($_FILES['userfile']['name'], '.');
      if ($dot < 1) {
        $ext = '';
      } else {
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower(substr($_FILES['userfile']['name'], $dot + 1)));
      }
      $newname = $this->dataDir.'/'.md5(time().$_FILES['userfile']['name']);
      if ($this->maximalPictureWidth === false) {
        /* clients may be attacked through modified picture files! */
        if (move_uploaded_file($_FILES['userfile']['tmp_name'], "$newname.$ext") === true) {
          $result = 'Success';
          $url = $this->baseUrl."/$newname.$ext";
        }
      } else {
        if (is_uploaded_file($_FILES['userfile']['tmp_name'])) {
          $newname = $this->resizePicture($_FILES['userfile']['tmp_name'], $newname);
          if ($newname !== false ) {
            $result = 'Success';
            $url = $this->baseUrl."/$newname";
          }
        }
      }
    }
    $this->sendHeaders();
?>
<html>
 <head>
  <title>liki</title>
  <link rel="stylesheet" type="text/css" href="<?php echo $this->baseUrl;?>/liki.css" />
  <script type="text/javascript" src="<?php echo $this->baseUrl;?>/bs_liki.js"></script>
 </head>
 <body id="uploadbody" <?php if ($result === 'Success') echo("onLoad=\"uploadSuccess('$url');\""); ?>>
  <h1><?=$result;?></h1>
 </body>
</html>
<?php
    $this->quit();
  }
  
  function sendLoginPanel($text) {
    header("Content-type: text/html; charset=UTF-8");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");

    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n";
    echo "<html>\n";
    echo " <head>\n";
    echo "  <title>Liki Login</title>\n";
    echo "  <link rel=\"stylesheet\" type=\"text/css\" href=\"".$this->baseUrl."/liki.css\" />\n";
    /*echo "  <meta name=\"description\" content=\"bs|area51.\" />\n";
    echo "  <meta name=\"author\" content=\"robert lillack &lt;rob@burningsoda.com&gt;\" />\n";
    echo "  <meta name=\"publisher\" content=\"burningsoda.com, leipzig, germany\" />\n";
    echo "  <meta name=\"copyright\" content=\"burningsoda.com\" />\n";*/
    echo "  <meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\" />\n";
    echo " </head>\n";
    echo " <body class='loginpanel'>\n";
    echo "  <div class='loginpanel'>\n";
    echo "   <h1>".$text."</h1>\n";
    //echo "   <h2>".$_SESSION['loggedin']."</h2>\n";
    //echo "   <h2>".session_id()."</h2>\n";
    echo "   <form method='POST' action='".$this->baseUrl."/' >\n";
    echo "    <p>Username:</p>\n";
    echo "    <div><input type='text' name='username' value='' /></div>\n";
    echo "    <p>Password:</p>\n";
    echo "    <div><input type='password' name='password' value='' /></div>\n";
    echo "    <div><input id='submitbutton' type='submit' value='Login' /></div>\n";
    echo "   </form>\n";
    echo "  </div>\n";
    echo " </body>\n";
    echo "</html>\n";
    $this->quit();
  }
  
  function bsLiki() {
    require("config.php");
    if ($this->baseUrl === false) {
      die('No baseUrl configured.');
    }
    
    if ($this->passwordProtected === true) {
      // ok, secure this one
      
      if (empty($this->username) || strlen($this->password) != 32) {
        die('User/Password not correctly configured.');
      }
      
      session_name('LIKISESSION');
      session_start();
      if (!isset($_SESSION['loggedin'])) {
        $_SESSION['loggedin'] = 'no';
      }
      
      if ($_SESSION['loggedin'] == 'no') {
        // has no valid session
        
        if (isset($_GET['action'])) {
          // is an asynchronous request
          header("HTTP/1.0 401 Unauthorized");
          print("<html><h1>Access denied.</h1></html>\n");
          $this->quit();
        } else {
          // is a "interactive" request
          if (isset($_POST['username']) && isset($_POST['password'])) {
            // got username/password
            if ($_POST['username'] === $this->username &&
                md5($_POST['password']) === $this->password) {
              // combination is right
              $_SESSION['loggedin'] = 'yes';
              session_write_close();
              header('Location: '.$this->baseUrl);
              $this->quit();
            } else {
              // combination is wrong
              header("HTTP/1.0 401 Unauthorized");
              $this->sendLoginPanel("Wrong password, dude.");
            }
          } else {
            // no username/password given
            $this->sendLoginPanel("Please Login");
          }
        }
      }
      
      // valid session found
      if (isset($_GET['logout'])) {
        // user wants to end session
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
          setcookie(session_name(), '', time()-42000, '/');
        }
        session_destroy();
        header('Location: '.$this->baseUrl);
        $this->quit();
      }
    }

    if ($this->getRequest('action') == 'feed') {
      $this->backend = new bsLikiBackend();
      $this->sendRSSFeed();
      exit;
    }
    
    $this->activePage = $this->getRequest('page', false);
    if (!$this->isLegitPageName($this->activePage) || $this->activePage == "") {
      header('Location: '.$this->baseUrl.'/frontpage');
      $this->quit();
    };

    $this->key = $this->getRequest('key', false);
    if (strlen($this->key) != 32) $this->key = false;

    $specialpages = array('index', 'search', 'timeindex', 'pictureindex', 'deletedpictures');
    if (in_array(strtolower($this->activePage), $specialpages)) {
      $this->specialPage = true;
    }

    if ($this->getRequest('legacymode') == 'true' ||
        $this->getRequest('legacymode') == 'false') {
      $this->legacyMode = ($this->getRequest('legacymode') == 'true') ? true : false;
    } else {
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Gecko') !== false ||
          strpos($_SERVER['HTTP_USER_AGENT'], 'KHTML') !== false ||
          strpos($_SERVER['HTTP_USER_AGENT'], 'Konqueror') !== false ||
          strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false) {
        $this->legacyMode = false;
      } else {
        $this->legacyMode = true;
      }
    }

    $this->backend = new bsLikiBackend();

    if ($this->getRequest('action') == 'uploadform') {
      $this->sendUploadForm();
    } elseif ($this->getRequest('action') == 'uploadpic') {
      $this->handleFileUpload();
    } elseif ($this->getRequest('action') == 'load') {
      $this->sendRecentChangesHeader();
      $p = $this->getPage($this->activePage);
      if (!$this->specialPage)
        $this->backend->visitPage($this->activePage);
      header('X-LIKI-Timestamp: '.$p['timestamp_change']);
      header('Content-type: text/html; charset=UTF-8');
      // this is just a fix for a safari/konqueror bug.
      // the client MUST kill this line!
      echo("<"."?xml version=\"1.0\" encoding=\"utf-8\"?".">\n");
      echo($p['content']);
      $this->quit();
    } elseif ($this->getRequest('action') == 'timestamp') {
      $this->sendRecentChangesHeader();
      header('Content-type: text/plain; charset=UTF-8');
      // special pages are _live_
      if ($this->specialPage) {
        $t = time();
      } else {
        $t = $this->backend->getTimestamp($this->activePage);
        if ($t === false) {
          $t = 1;
        }
      }
      echo $t;
      $this->quit();
    } elseif ($this->getRequest('action') == 'freelock') {
      if (!$this->specialPage && $this->key && $this->backend->freePage($this->activePage, $this->key)) {
        header("HTTP/1.1 204 lock released");
      } else {
        header("HTTP/1.1 403 could not release lock");
      }
      $this->quit();
    } elseif ($this->getRequest('action') == 'requestlock') {
      $newkey = md5($_SERVER['REMOTE_ADDR'].time().rand());
      if (!$this->specialPage && $this->backend->lockPage($this->activePage, $newkey)) {
        //header("HTTP/1.1 204 lock acquired");
        header('Content-type: text/plain; charset=UTF-8');
        echo($newkey);
      } else {
        header("HTTP/1.1 403 could not acquire lock");
      }
      $this->quit();
    } elseif ($this->getRequest('action') == 'edit') {
      if (!$this->specialPage && $this->key &&
          $this->backend->updatePage($this->activePage, $this->key,
                                     str_replace("\r", "", $this->getRequest('content', false)))) {
        header('Content-type: text/plain; charset=UTF-8');
        echo("ok\n");
        // impossible because konqueror BROWSER BUG:
        //header("HTTP/1.1 204 saved");
      } else {
        header("HTTP/1.1 403 liki is locked");
      }
      $this->quit();
    } elseif ($this->getRequest('action') == 'saveandfree') {
      if (!$this->specialPage && $this->key &&
          $this->backend->updatePage($this->activePage, $this->key,
                                     $this->getRequest('content', false)) &&
          $this->backend->freePage($this->activePage, $this->key)) {
        header('Content-type: text/plain; charset=UTF-8');
        echo("ok\n");
        // impossible because konqueror BROWSER BUG:
        //header("HTTP/1.1 204 saved and lock released");
      } else {
        header("HTTP/1.1 403 saving/releasing not possible");
      }
      $this->quit();
    }
  }
}

function diff($old, $new){
  /* Paul's Simple Diff Algorithm v 0.1
     (C) Paul Butler 2007 <http://www.paulbutler.org/>
     May be used and distributed under the zlib/libpng license. */
  foreach($old as $oindex => $ovalue){
    $nkeys = array_keys($new, $ovalue);
    foreach($nkeys as $nindex){
      $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
        $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
      if($matrix[$oindex][$nindex] > $maxlen){
        $maxlen = $matrix[$oindex][$nindex];
        $omax = $oindex + 1 - $maxlen;
        $nmax = $nindex + 1 - $maxlen;
      }
    }       
  }
  if($maxlen == 0) return array(array('d'=>$old, 'i'=>$new));
  return array_merge(
      diff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
      array_slice($new, $nmax, $maxlen),
      diff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
} 

?>
