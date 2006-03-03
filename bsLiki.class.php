<?php
require('bsLikiBackend.class.php');

class bsLiki {
  var $backend = false;
  var $baseUrl = false;
  var $activePage = false;
  var $specialPage = false;
  var $legacyMode = false;
  var $key = false;
  var $dataDir = 'data';

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
    global $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_SERVER_VARS;
    $v = '';

    if (isset($HTTP_GET_VARS[$varname])) {
      $v = $HTTP_GET_VARS[$varname];
    } elseif (isset($HTTP_POST_VARS[$varname])) {
      $v = $HTTP_POST_VARS[$varname];
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
                 'timestamp_changed' => time());
    } elseif (strtolower($page) == 'timeindex') {
      $p = array('content' => $this->createTimeIndexPage(),
                 'timestamp_changed' => time());
    } elseif (strtolower($page) == 'search') {
       $p = array('content' => $this->createSearchPage($this->getRequest("q", false)),
                  'timestamp_changed' => time());
    } elseif (strtolower($page) == 'pictureindex') {
       $p = array('content' => $this->createPictureIndex(),'timestamp_changed' => time());
    } elseif (strtolower($page) == 'deletedpictures') {
      $p = array('content' => $this->createPictureIndex(true), 'timestamp_changed' => time());
    } else {
      $p = $this->backend->getPage($page);
      if ($p === false) {
        $p = array('content'   => "# Error loading page $page",
                   'timestamp_changed' => '1');
      }
    }

    return $p;
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
   <input type="file" name="userfile" value="" onchange="doUpload();" />
  </form>
 </body>
</html>
<?php
    $this->quit();
  }

  function handleFileUpload() {
    /** @todo check the contents. */
    $result = 'Failure';
    $url = '';
    if (isset($_FILES['userfile']) && !empty($_FILES['userfile']['tmp_name'])) {
      $tmp = $_FILES['userfile']['tmp_name'];
      $newname = time().'-'.preg_replace('/[^0-9a-zA-Z_\-\.]/', '_', $this->getRequest('page').'--'.$_FILES['userfile']['name']);
      if (@move_uploaded_file($_FILES['userfile']['tmp_name'], $this->dataDir."/$newname") === true) {
        $result = 'Success';
        $url = $this->baseUrl."/".$this->dataDir."/$newname";
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
 <body id="uploadbody" onload="uploadSuccess('<?=$url;?>');">
  <h1><?=$result;?></h1>
 </body>
</html>
<?php
    $this->quit();
  }
  
  function bsLiki() {
    require("config.php");
    if ($this->baseUrl === false) {
      die('No baseUrl configured.');
    }

    $this->activePage = $this->getRequest('page', false);
    /** @todo: check for legit utf-8 */
    if ($this->activePage == "") {
      header('Location: '.$this->baseUrl.'/frontpage');
      $this->quit();
    };

    $this->key = $this->getRequest('key', false);
    if (strlen($this->key) != 32) $this->key = false;

    $specialpages = array('index', 'search', 'timeindex', 'pictureindex', 'deletedpictures');
    if (in_array(strtolower($this->activePage), $specialpages)) {
      $this->specialPage = true;
    }

    if ($this->getRequest('legacymode') == 'true') {
      $this->legacyMode = true;
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
      header('X-LIKI-Timestamp: '.$p['timestamp_changed']);
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
        $t = $this->getTimestamp();
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
      $newkey = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time().rand());
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
?>
