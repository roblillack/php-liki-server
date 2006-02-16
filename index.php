<?php
require('_classes/bs_utils.php');
require('bs_likibackend.php');

$page = bs_request('page', false);
if ($page == "") {
  header('Location: '.bs_url().'frontpage');
  exit;
};

$key = bs_request('key', false);
if (strlen($key) != 32) {
  //$key = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time());
  $key = false;
}

$specialpages = array('index', 'search', 'timeindex');
if (in_array(strtolower(bs_request('page', false)), $specialpages)) {
  $havespecialpage = true;
} else {
  $havespecialpage = false;
}

if (bs_request('legacymode') == 'true') {
  $legacyMode = true;
} else {
  $legacyMode = false;
}

$b = new bsLikiBackend();

function recentChangesHeader() {
  global $b;
  //$r = $b->getRecentChanges(10);
  $str = "";
  foreach ($b->getRecentChanges(10) as $p) {
    $str .= $p['name']."/".$p['howlongago'].",";
  }
  header('X-LIKI-RecentChanges: '.rawurlencode(substr($str,0,strlen($str)-1)));
}

function getPage($page) {
  global $b;
  if (strtolower($page) == 'index') {
    $index = "# Liki Pages sorted alphabetically\nswitch to [[TimeIndex]].\n";
    $list = $b->getPageList();
    foreach ($list as $i)
      $index .= "- [[$i]]\n";
    $p = array('content' => $index,
               'timestamp' => '1');
  } elseif (strtolower($page) == 'timeindex') {
    $index = "# Liki Pages sorted by modification time\nswitch to alphabetical [[Index]].\n";
    $list = $b->getRecentChanges(false);
    foreach ($list as $i)
      $index .= "- [[${i['name']}]] (${i['howlongago']})\n";
    $p = array('content' => $index,
               'timestamp' => time());
  } elseif (strtolower($page) == 'search') {
    if (bs_request('q') != '') {
      $index = "# Search for \"".htmlentities(bs_request('q', false))."\"\n";
      $list = $b->getPagesContaining(bs_request('q', false));
      if ($list !== false) foreach ($list as $i) $index .= "- [[$i]]\n";
      $p = array('content' => $index,
                 'timestamp' => '1');
    } else {
      $p = array('content' => '# Search',
                 'timestamp' => '1');
    }
  } elseif (strtolower($page) == 'pictureindex') {
    if (bs_request('q') != '') {
      $index = "# picture index\n";
      $list = $b->getPagesContaining(bs_url(true));
      if ($list !== false) foreach ($list as $i) $index .= "- [[$i]]\n";
      $p = array('content' => $index,
                 'timestamp' => '1');
    } else {
      $p = array('content' => '# Search',
                 'timestamp' => '1');
    }
  } else {
    $p = $b->getPage($page);
    if ($p === false) {
      $p = array('content'   => "# Error loading page $page",
                 'timestamp' => '1');
    }
  }

  return $p;
}

if (bs_request('action') == 'uploadform') {
  $pageURI = bs_url(true)."/$page";
?>
<html>
 <head>
  <title>liki</title>
  <link rel="stylesheet" type="text/css" href="<?=(bs_baseurl());?>/liki.css" />
  <script type="text/javascript" src="<?=(bs_baseurl());?>/bs_liki.js"></script>
 </head>
 <body id="uploadbody">
  <form id="uploadform" action="<?=$pageURI;?>" method="post" enctype="multipart/form-data">
   <input type="hidden" name="action" value="uploadpic" />
   <input type="file" name="userfile" value="" onchange="doUpload();" />
  </form>
 </body>
</html>
<?php
  exit;
} elseif (bs_request('action') == 'uploadpic') {
  $result = 'Failure';
  $url = '';
  if (isset($_FILES['userfile']) && !empty($_FILES['userfile']['tmp_name'])) {
    $tmp = $_FILES['userfile']['tmp_name'];
    $newname = time().'-'.preg_replace('/[^0-9a-zA-Z_\-\.]/', '_', bs_request('page').'--'.$_FILES['userfile']['name']);
    //trigger_error('old: '.$_FILES['userfile']['tmp_name']."new: $bs_classpath/../pix/$newname");
    if (@move_uploaded_file($_FILES['userfile']['tmp_name'], "$bs_classpath/../pix/$newname") === true) {
      $result = 'Success';
      $url = bs_url(true)."/pix/$newname";
    }
  }
?>
<html>
 <head>
  <title>liki</title>
  <link rel="stylesheet" type="text/css" href="<?=(bs_baseurl());?>/liki.css" />
  <script type="text/javascript" src="<?=(bs_baseurl());?>/bs_liki.js"></script>
 </head>
 <body id="uploadbody" onload="uploadSuccess('<?=$url;?>');">
  <h1><?=$result;?></h1>
 </body>
</html>
<?php
  exit;
} elseif (bs_request('action') == 'htmlload') {
  //header('X-LIKI-RecentChanges: '.rawurlencode($b->getLastChanges(6)));
  recentChangesHeader();
  $p = getPage($page);
  header('X-LIKI-Timestamp: '.$p['timestamp']);
  header('Content-type: text/html; charset=UTF-8');
  // this is just a fix for a safari bug.
  // the client MUST kill this line!
  echo("<"."?xml version=\"1.0\" encoding=\"utf-8\"?".">\n");
  echo($p['content']);
  exit;
} elseif (bs_request('action') == 'plainload') {
  $p = $b->getPage($page);
  header('Content-type: text/plain; charset=UTF-8');
  echo($p['content']);
  exit;
} elseif (bs_request('action') == 'timestamp') {
  recentChangesHeader();
  //header('X-LIKI-RecentChanges: '.rawurlencode($b->getLastChanges(6)));
  header('Content-type: text/plain; charset=UTF-8');
  // special pages are _live_
  if ($havespecialpage) {
    echo time();
  } else {
    $p = $b->getPage($page);
    echo($p['timestamp']);
  }
  exit;
} elseif (bs_request('action') == 'freelock') {
  if (!$havespecialpage && $key && $b->freePage($page, $key)) {
    header("HTTP/1.1 204 lock released");
  } else {
    header("HTTP/1.1 403 could not release lock");
  }
  $b->closeConnection();
  exit;
} elseif (bs_request('action') == 'requestlock') {
  $newkey = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time());
  if (!$havespecialpage /*&& $_SERVER['REMOTE_ADDR'] == '195.158.172.153'*/ && $b->lockPage($page, $newkey)) {
    //header("HTTP/1.1 204 lock acquired");
    header('Content-type: text/plain; charset=UTF-8');
    echo($newkey);
  } else {
    header("HTTP/1.1 403 could not acquire lock");
  }
  $b->closeConnection();
  exit;
} elseif (bs_request('action') == 'load') {
  header("Content-type: text/xml; charset=UTF-8");
  $p = $b->getPage($page);
  //$c = explode("\n", $p['content']);
  //$c = str_replace("\n", "]]><![CDATA[", $p['content']);
  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  echo "<liki>\n";
  // NO newlines after the CDATAs because of browserbug in opera!
  echo " <content>";
  //echo bs_xmlencode($p['content']);
  //foreach ($p['content'] as $row) {
    echo str_replace(array('&', '<', '>', ' ', "\n", "\r"),
                       array('&amp;', '&lt;', '&gt;', '<s/>', "<n/>", ""),
                       $p['content']);
  //row."\n")."\n";
  //echo "<![CDATA[$row]]>";
  echo "</content>\n";
  echo " <timestamp>".$p['timestamp']."</timestamp>\n";
  echo "</liki>\n";
  $b->closeConnection();
  exit;
} elseif (bs_request('action') == 'edit') {
  if (!$havespecialpage && $key && $b->updatePage($page, $key, str_replace("\r", "", bs_request('content', false)))) {
    header('Content-type: text/plain; charset=UTF-8');
    echo("ok\n");
    // impossible because konqueror BROWSER BUG:
    //header("HTTP/1.1 204 saved");
  } else {
    header("HTTP/1.1 403 liki is locked");
  }
  $b->closeConnection();
  exit;
} elseif (bs_request('action') == 'saveandfree') {
  if (!$havespecialpage && $key && $b->updatePage($page, $key, bs_request('content', false))
      && $b->freePage($page, $key)) {
    header('Content-type: text/plain; charset=UTF-8');
    echo("ok\n");
    // impossible because konqueror BROWSER BUG:
    //header("HTTP/1.1 204 saved and lock released");
  } else {
    header("HTTP/1.1 403 saving/releasing not possible");
  }
  $b->closeConnection();
  exit;
}

$baseURI = bs_url(true);
$mainURI = "$baseURI/$page";
$params_readonly = $havespecialpage ? 'true' : 'false';
if (strtolower($page) == 'search') {
  $params_query = "'".bs_request('q', true)."'";
  $searchfield = $params_query;
} else {
  $params_query = 'false';
  $searchfield = '"search..."';
}
$params = "'$mainURI', 5000, $params_readonly, $params_query";

header("Content-type: text/html; charset=UTF-8");
bs_header_nocache();
echo XML_HEADER;
echo XHTML_11_HEADER;
?>
<html>
 <head>
  <title>liki &mdash; the LIve wiKI: <?=$page;?></title>
  <?php if (!$legacyMode) { ?><script type="text/javascript" src="<?=(bs_baseurl());?>/bs_liki.js"></script><?php } ?>
  <link rel="stylesheet" type="text/css" href="<?=(bs_baseurl());?>/liki.css" />
  <link rel="icon" href="favicon.ico" type="image/ico" />
  <link rel="Shortcut Icon" type="image/x-icon" href="<?=(bs_baseurl());?>/favicon.ico" />
 </head>
 <body id="mainbody" onLoad="initLiki(<?=$params?>)">
  <a accessKey="f" href="<?=(bs_baseurl().'/frontpage');?>" id="likititle">the <em>#burningsoda</em> liki</a>
  <div id="toolbar">
   <span id="uploadbutton"><a accessKey="u" href="javascript:clickUploadButton()"><u>u</u>pload</a> |</span>
   <a id="editchecker" href="javascript:switchEditMode()" class="readmode">...</a>
  </div>
<?php if (!$legacyMode) { ?>
  <form id="contenteditor" action="." method="post" accept-charset="UTF-8">
   <div><textarea rows="10" cols="10" name='content' id="content"></textarea></div>
  </form>
<?php } ?>
  <div id="navibar">
   <form id="searchform" action="<?=$baseURI;?>/search" method="get"><input accessKey="s" onClick="clearSearchField();" id="searchfield" type="text" value=<?=$searchfield?> name="q" /></form>
   <a accessKey="i" href="<?=(bs_baseurl().'/INDEX');?>"><u>i</u>ndex</a>,
   recently changed: <span id="recentchanges"></span>
  </div>
  <div <?php if ($legacyMode) { echo 'style="visibility: visible;"'; } ?> id='viewcontent'><?php
if ($legacyMode) {
  $p = getPage($page);
  echo "<h1>$page</h1>\n";
  echo "<pre>".htmlspecialchars($p['content'])."</pre>\n";
}
  ?></div>
  <div id='status'></div>
  <iframe id="uploadiframe" src="<?=$baseURI;?>/<?=$page;?>?action=uploadform" />
 </body>
</html>
<?php

$b->closeConnection();
exit;

/*function enter_critical_section() {
  global $sem;
  $semkey = ftok(__FILE__, "1");
  $sem = @sem_get($semkey, 1);
  if ($sem === false) return false;
  if (@sem_acquire($sem) === false) return false;
  return true;
}

function leave_critical_section() {
  global $sem;
  if (@sem_release($sem) === false) return false;
  return true;
  //@sem_remove($sem);
}
 
function liki_is_locked() {
  global $filename;
  $lockname = $filename.'.lock';
  if (is_readable($lockname)) {
    if (filemtime($lockname) + 60 > time()) {
      $lockip = trim(implode('', file($lockfile)));
      if ($lockip == $HTTP_SERVER_VARS['REMOTE_ADDR'])
        return 2; // locked by user
      else
        return 1; // locked by someone else
    } else {
      unlink($lockname);
    }
  }
  return 0;
}

function lock_liki() {
  global $filename, $HTTP_SERVER_VARS;
  $lockname = $filename.'.lock';
  $handle = false;
  $handle = fopen($lockname, 'w');
  if ($handle) {
    fputs($handle, $HTTP_SERVER_VARS['REMOTE_ADDR']);
    fclose($handle);
  } 
  return true;
}

function free_liki() {
  global $filename, $HTTP_SERVER_VARS;
  $lockname = $filename.'.lock';
  if (file_exists($lockname)) {
    unlink($lockname);
  }
  return true;
}*/
?>
