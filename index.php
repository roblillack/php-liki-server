<?php
require('_classes/bs_utils.php');
require('bs_likibackend.php');

$key = bs_request('key', false);
if (strlen($key) != 32) {
  //$key = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time());
  $key = false;
}

$page = 'testpage';

$b = new bsLikiBackend();

if (bs_request('action') == 'freelock') {
  if ($key && $b->freePage($page, $key)) {
    header("HTTP/1.1 204 lock released");
  } else {
    header("HTTP/1.1 403 could not release lock");
  }
  exit;
} elseif (bs_request('action') == 'requestlock') {
  $newkey = md5($HTTP_SERVER_VARS['REMOTE_ADDR'].time());
  if ($b->lockPage($page, $newkey)) {
    //header("HTTP/1.1 204 lock acquired");
    header('Content-type: text/plain; charset=UTF-8');
    echo($newkey);
  } else {
    header("HTTP/1.1 403 could not acquire lock");
  }
  exit;
} elseif (bs_request('action') == 'load') {
  header("Content-type: application/xml; charset=UTF-8");
  $p = $b->getPage($page);
  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  echo "<liki>\n";
  echo "<content><![CDATA[".$p['content']."]]></content>\n";
  echo "<timestamp>".$p['timestamp']."</timestamp>\n";
  echo "</liki>\n";
  exit;
} elseif (bs_request('action') == 'edit') {
  if ($key && $b->updatePage($page, $key, bs_request('content', false))) {
    header("HTTP/1.1 204 saved");
  } else {
    header("HTTP/1.1 403 liki is locked");
  }
  exit;
} elseif (bs_request('action') == 'saveandfree') {
  if ($key && $b->updatePage($page, $key, bs_request('content', false))
      && $b->freePage($page, $key)) {
    header("HTTP/1.1 204 saved and lock released");
  } else {
    header("HTTP/1.1 403 saving/releasing not possible");
  }
  exit;
}

header("Content-type: text/html; charset=UTF-8");
echo XML_HEADER;
echo XHTML_11_HEADER;
?>
 <head>
  <title>liki &mdash; the LIve wiKI</title>
  <script language="JavaScript" type="text/javascript" src="html2xhtml.js"></script>
  <script language="JavaScript" type="text/javascript" src="richtext.js"></script>
  <style type="text/css">
  body {
    background-color: #eee;
    text-align: center;
    /*overflow: hidden;*/
    margin: 0;
  }

  body>h1 {
    position: absolute;
    top: 2px;
    left: 5px;
    font-style: normal;
    color: #c00;
    z-index:50;
    font-family: sans-serif;
    font-size: 16px;
    margin: 0;
  }
  h1 em {
    font-style: normal;
    color: black;
  }
  #editchecker {
    display: block;
    position: absolute;
    top: 0px;
    right: 0px;
    z-index: 20;
    height: 12px;
    padding: 0;
    margin: 0;
    overflow: hidden;
    font-size: 10px;
    font-family: sans-serif;
    padding-left: 2px;
    padding-right: 2px;
  }
  #editchecker:hover {
    cursor: pointer;
    background: black;
    color: white;
    text-decoration: none;
  }
  #status {
    position: absolute;
    width: 100%;
    height: 12px;
    background-color: white;
    left: 0;
    top: 0;
    z-index: 10;
    overflow: hidden;

    color: #777;
    font-size: 10px;
    font-family: sans-serif;
  }
  #content, #viewcontent {
    text-align: left;
    color: #333;
    font-family: sans-serif;
    font-size: 12px;
    /*overflow: scroll;*/
    padding: 0px;
    border: 0;
  }

  #viewcontent {
    margin: 25px;
    margin-top: 50px;
  }

  #content {
    background-color: white;
    position: absolute;
    top: 12px;
    left: 0px;
    right: 0px;
    bottom: 0px;
    z-index: 1;
    margin: 10px;
    visibility: hidden;
  }
  </style>
  <script type="text/javascript">

var timeout = 5000;
var editMode;
var interval;
var lockKey;
var oldContent;

//initRTE("rte/images/", "", "", true);

function setEditMode(onoff) {
  var contentElement = document.getElementById("content");
  var view = document.getElementById('viewcontent');
  var checker = document.getElementById("editchecker");
  var body = document.getElementById("mainbody");

  if (onoff == false) {
    contentElement.style.visibility = 'hidden';
    view.style.display = 'block';
    //contentElement.style.backgroundColor = '#eee';
    //contentElement.readOnly = true;
    checker.style.backgroundColor = 'white';
    checker.style.color = 'black';
    body.style.overflow = 'auto';
    setStatus('Ready.');
  } else {
    contentElement.style.visibility = 'visible';
    view.style.display = 'none';
    //contentElement.style.backgroundColor = 'white';
    //contentElement.readOnly = false;
    checker.style.backgroundColor = 'red';
    checker.style.color = 'white';
    //checker.checked = true;
    body.style.overflow = 'hidden';
    setStatus('Editing...');
  }
  editMode = onoff;
}

function switchEditMode() {
  if (editMode == false) {
    // create lock request
    var req = createRequest();
    req.onreadystatechange = createRequestLockHandler(req);
    req.open("POST", "<?php echo(bs_url()); ?>", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.send("action=requestlock");
    setStatus('trying to acquire lock');
    //setEditMode(true);
    //saveChanges();
  } else {
    setStatus("saving and freeing lock....");
    var req = createRequest();
    var contentElement = document.getElementById("content");
    oldContent = contentElement.value;
    document.getElementById("viewcontent").innerHTML = oldContent;
    req.onreadystatechange = createFreeLockHandler(req);
    req.open("POST", "<?php echo(bs_url()); ?>", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
    req.send("action=saveandfree&key="+lockKey+"&content="+encodeURIComponent(contentElement.value));
  }
}

function createRequest() {
  var req = false;
  if (window.XMLHttpRequest) {
    req = new XMLHttpRequest();
  } else if (window.ActiveXObject) {
    try {
      req = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e1) {
      try {
        req = new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e2) {
        // bla.
      }
    }
  }
  return req;
}

function createRequestLockHandler(req) {
  return function() {
    if (req.readyState == 4) {
      //alert("requestLock status: "+req.status);
      if (req.status == 200) {
        lockKey = req.responseText;
        setEditMode(true);
      } else {
        lockKey = false;
        setEditMode(false);
      }
    }
  }
}

function createFreeLockHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status == 204) {
        lockKey = false;
        setEditMode(false);
      } else {
        setEditMode(true);
      }
    }
  }
}

function createSaveHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status == 204) {
        setStatus("Saved.");
        if (editMode == false) {
          setEditMode(true);
        }
      } else {
        setEditMode(false);
      }
    }
  }
}

function createLoadHandler(req) {
  return function() {
    var contentElement = document.getElementById("content");
    if (req.readyState == 4) {
      if (editMode == false) {
        if (req.status == 200) {
          //text = req.responseText;
          text = req.responseXML.documentElement.getElementsByTagName('content')[0].firstChild.nodeValue;
          if (text != contentElement.value) {
            contentElement.value = text;
            document.getElementById("viewcontent").innerHTML = text;
            setStatus("Loaded.");
          } else {
            setStatus("No changes.");
          }
        }
      }
    }
  }
}
 
function setStatus(text) {
  var status = document.getElementById("status");
  status.innerHTML = text;
}

function onLoad() {
  interval = false;
  lockKey = false;
  oldContent = false;
  setEditMode(false);
  transmitChanges();
  interval = setInterval('transmitChanges()', timeout);
}

function saveChanges() {
  setStatus("Saving....");
  var req = createRequest();
  var contentElement = document.getElementById("content");
  oldContent = contentElement.value;
  document.getElementById("viewcontent").innerHTML = oldContent;
  req.onreadystatechange = createSaveHandler(req);
  req.open("POST", "<?php echo(bs_url()); ?>", true);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
  req.send("action=edit&key="+lockKey+"&content="+encodeURIComponent(contentElement.value));
}

function transmitChanges() {
  if (editMode == true) {
    saveChanges();
  } else {
    setStatus("Checking for changes....");
    var req = createRequest();
    req.onreadystatechange = createLoadHandler(req);
    req.open("POST", "<?php echo(bs_url());?>", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
    req.send("action=load");
  }
}
  </script>
 </head>
 <body id="mainbody" onLoad="onLoad()">
  <h1><em>liki</em> &mdash; the <em>li</em>ve wi<em>ki</em></h1>
  <a id="editchecker" onClick="switchEditMode()">edit</a>
  <!--<script language="JavaScript" type="text/javascript">-->
  <!--
  // Usage: writeRichText(fieldname, html, width, height, buttons, readOnly)
  writeRichText('content', '', 520, 200, false, false);
  // -->
  <!--</script>-->
  <form id="contenteditor" action="." method="post" accept-charset="UTF-8">
   <textarea name='content' id="content"></textarea>
  </form>
  <div id='viewcontent'></div>
  <div id='status'></div>
  <div id='changer'></div>
 </body>
</html>
<?php

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
