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

$b = new bsLikiBackend();

if (bs_request('action') == 'htmlload') {
  $p = $b->getPage($page);
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
  $p = $b->getPage($page);
  header('Content-type: text/plain; charset=UTF-8');
  echo($p['timestamp']);
  exit;
} elseif (bs_request('action') == 'freelock') {
  if ($key && $b->freePage($page, $key)) {
    header("HTTP/1.1 204 lock released");
  } else {
    header("HTTP/1.1 403 could not release lock");
  }
  $b->closeConnection();
  exit;
} elseif (bs_request('action') == 'requestlock') {
  $newkey = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time());
  if ($b->lockPage($page, $newkey)/*&& $_SERVER['REMOTE_ADDR'] == '195.158.172.112'*/) {
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
  if ($key && $b->updatePage($page, $key, str_replace("\r", "", bs_request('content', false)))) {
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
  if ($key && $b->updatePage($page, $key, bs_request('content', false))
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

header("Content-type: text/html; charset=UTF-8");
echo XML_HEADER;
echo XHTML_11_HEADER;
?>
<html>
 <head>
  <title>liki &mdash; the LIve wiKI</title>
  <script type="text/javascript" src="bs_liki.js"></script>
  <link rel="stylesheet" type="text/css" href="liki.css" />
  <link rel="icon" href="favicon.ico" type="image/ico" />
  <link rel="Shortcut Icon" type="image/x-icon" href="<?=(bs_baseurl());?>/favicon.ico" />
 </head>
 <body id="mainbody" onLoad="initLiki('<?=(bs_url(false));?>', 5000)">
  <a href="<?=(bs_baseurl().'/frontpage');?>" id="likititle"><em>liki</em> &mdash; the <em>li</em>ve wi<em>ki</em></a>
  <div id="toolbar">
   <a id="editchecker" href="#" class="readmode" onClick="switchEditMode()">edit</a>
   <!-- | <a href="http://ruckelfotze.de/">ruckelfotze</a> -->
  </div>
  <form id="contenteditor" action="." method="post" accept-charset="UTF-8">
   <div><textarea rows="10" cols="10" name='content' id="content"></textarea></div>
  </form>
  <div id='viewcontent'></div>
  <div id='status'></div>
  <!--<div id='changer'></div>-->
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
