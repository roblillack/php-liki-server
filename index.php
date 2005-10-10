<?php
require('bs_utils.php');

/*

      anforderung lock
            |
            |
         ist schon
         gelockt?   --- ja ---> ablehnung -----> client liest regelmäßig.
            |                                          -+
            |                                        -- ---
          nein                                     --  +   -+
            |                                    --    |    +-+
            |                                  --      |      |
       server generiert                                |
       eindeutigen key                                 |
       und speichert ihn                               +
       in blbla.lock                                   |
       und schickt key an                              |
       client.                                         |
           |                                           |
           |                                          ++
     client sendet change                             |
     messages an den server                           ++
     der ändert NUR, wenn                              |
     der richtige key dabei                            |
     ist.                                              |
           |                                           ++
           |                                            |
      client sendet unlock                             -+
      aufforderung mit key -----------------------------

*/

$content = false;
$filename = 'data';

if (bs_request('action') == 'freelock') {
  if (liki_is_locked() == 2) {
    free_liki();
    header("HTTP/1.1 204 lock released");
    exit;
  } else {
    header("HTTP/1.1 403 could not release lock");
    exit;
  }
} elseif (bs_request('action') == 'requestlock') {
  if (liki_is_locked() == 1) {
    header("HTTP/1.1 403 could not acquire lock");
    exit;
  } else {
    lock_liki();
    header("HTTP/1.1 204 lock acquired");
    exit;
  }
} elseif (bs_request('action') == 'load') {
  header("Content-type: text/plain; charset=UTF-8");
  @readfile($filename);
  exit;
} elseif (bs_request('action') == 'edit') {
  if (liki_is_locked() == 2) {
    $content = htmlspecialchars(bs_request('content', false));
    //trigger_error("content: ".$content);
    $handle = false;
    for ($t = 0; $handle === false && $t < 10; $t++) {
      //trigger_error("try #".$t);
      $handle = fopen($filename, 'w');
      usleep(10000*$t);
    }
    if ($handle) {
      fputs($handle, $content);
      fclose($handle);
    } else {
      //trigger_error("aaaah. could not get handle!");
    }
    lock_liki();
    header("HTTP/1.1 204 saved");
    exit;
  } else {
    header("HTTP/1.1 403 liki is locked");
    exit;
  }
}

header("Content-type: text/html; charset=UTF-8");
echo XML_HEADER;
echo XHTML_11_HEADER;
?>
 <head>
  <title>liki &mdash; the LIve wiKI</title>
  <style type="text/css">
  body {
    background-color: #ddd;
    text-align: center;
  }

  h1 {
    margin-bottom: 0px;
    margin-top: 30px;
    font-style: normal;
    color: #777;
  }
  h1 em {
    font-style: normal;
    color: black;
  }
  textarea {
    background-color: white;
    color: black;
    font-family: monospace;
    font-size: 12px;
    font-weight: bold;
    border: 1px solid #ccc;
    overflow: clip;
  }
  form p {
    font-family: sans-serif;
    font-size: 20px;
    margin: 0;
  }
  #cbEdit {
    border: 1px solid #ccc;
    width: 20px;
    height: 20px;
  }
  #status {
    color: #777;
    font-size: 9px;
    font-family: sans-serif;
  }
  </style>
  <script type="text/javascript">

// whether or not, we are editing (ie. have a lock)
var editMode = false;
// the key to change data on the server
var lockKey = '';
// timeout between loadings/savings
var timeout = 5000;
var interval = false;

var oldContent = false;

function setEditMode(onoff) {
  var contentElement = document.getElementById("content");
  var checker = document.getElementById("cbEdit");

  if (onoff == false) {
    contentElement.style.backgroundColor = '#eee';
    contentElement.readOnly = true;
    checker.checked = false;
    setStatus('Ready.');
  } else {
    contentElement.style.backgroundColor = 'white';
    contentElement.readOnly = false;
    checker.checked = true;
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
  } else {
    // save all changes and give up lock
    transmitChanges();
    // create lock request
    var req = createRequest();
    req.onreadystatechange = createFreeLockHandler(req);
    req.open("POST", "<?php echo(bs_url()); ?>", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.send("action=freelock");
    setStatus('freeing lock');
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
      if (req.status == 204) {
        lockKey = req.responseText;
        setEditMode(true);
      } else {
        lockKey = '';
        setEditMode(false);
      }
    }
  }
}

function createFreeLockHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status == 204) {
        lockKey = '';
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
          if (req.responseText != contentElement.value) {
            contentElement.value = req.responseText;
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
  setEditMode(false);
  interval = setInterval('transmitChanges()', timeout);
}

function transmitChanges() {
  if (editMode == true) {
    setStatus("Saving....");
    var req = createRequest();
    var contentElement = document.getElementById("content");
    oldContent = contentElement.value;
    req.onreadystatechange = createSaveHandler(req);
    req.open("POST", "<?php echo(bs_url()); ?>", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.send("action=edit&content="+encodeURI(contentElement.value));
  } else {
    setStatus("Checking for changes....");
    var req = createRequest();
    req.onreadystatechange = createLoadHandler(req);
    req.open("GET", "<?php echo(bs_url().'/'.$filename);?>", true);
    req.send("");
  }
}
  </script>
 </head>
 <body onLoad="onLoad()">
  <h1><em>liki</em> &mdash; the <em>li</em>ve wi<em>ki</em></h1>
  <form action="." method="post" accept-charset="UTF-8">
   <p>edit: <input type="checkbox" id="cbEdit" onClick="switchEditMode()" /></p>
   <textarea cols='80' rows='25' name='content' id="content"></textarea>
   <input type="hidden" name="action" value="edit" />
  </form>
  <div id='status'></div>
  <div id='changer'></div>
 </body>
</html>
<?php

function liki_is_locked() {
  global $filename;
  $semkey = ftok(__FILE__, "1");
  $sem = @sem_get($semkey, 1);
  //if ($sem === false) return true;
  //if (sem_acquire($sem) === false) return true;
  @sem_acquire($sem);
  $lockname = $filename.'.lock';
  if (is_readable($lockname)) {
    if (filemtime($lockname) + 60 > time()) {
      $lockip = trim(implode('', file($lockfile)));
      @sem_release($sem);
      @sem_remove($sem);
      if ($lockip == $HTTP_SERVER_VARS['REMOTE_ADDR'])
        return 2; // locked by user
      else
        return 1; // locked by someone else
    } else {
      unlink($lockname);
    }
  }
  @sem_release($sem);
  @sem_remove($sem);
  return 0;
}

function lock_liki() {
  global $filename, $HTTP_SERVER_VARS;
  $semkey = ftok(__FILE__, "1");
  $sem = @sem_get($semkey, 1);
  //if ($sem === false) return false;
  //if (sem_acquire($sem) === false) return false;
  @sem_acquire($sem);
  $lockname = $filename.'.lock';
  $handle = false;
  $handle = fopen($lockname, 'w');
  if ($handle) {
    fputs($handle, $HTTP_SERVER_VARS['REMOTE_ADDR']);
    fclose($handle);
  } 
  @sem_release($sem);
  @sem_remove($sem);
  return true;
}

function free_liki() {
  global $filename, $HTTP_SERVER_VARS;
  $semkey = ftok(__FILE__, "1");
  $sem = @sem_get($semkey, 1);
  //if ($sem === false) return false;
  //if (sem_acquire($sem) === false) return false;
  @sem_acquire($sem);
  $lockname = $filename.'.lock';
  if (file_exists($lockname)) {
    unlink($lockname);
  }
  @sem_release($sem);
  @sem_remove($sem);
  return true;
}

