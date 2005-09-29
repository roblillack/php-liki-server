<?php
require('bs_utils.php');

$content = false;
$filename = 'data';

if (bs_request('action') == 'load') {
  header("Content-type: text/plain; charset=UTF-8");
  @readfile($filename);
  exit;
} elseif (bs_request('action') == 'edit') {
  $content = htmlspecialchars(bs_request('content', false));
  trigger_error("content: ".$content);
  $handle = false;
  for ($t = 0; $handle === false && $t < 10; $t++) {
    trigger_error("try #".$t);
    $handle = fopen($filename, 'w');
    usleep(10000*$t);
  }
  if ($handle) {
    fputs($handle, $content);
    fclose($handle);
  } else {
    trigger_error("aaaah. could not get handle!");
  }
}

header("Content-type: text/html; charset=UTF-8");
echo XML_HEADER;
echo XHTML_11_HEADER;
?>
 <head>
  <title>liki &mdash; the LIve wiKI</title>
  <style type="text/css">
  h1 {
    font-style: normal;
    color: #777;
  }
  h1 em {
    font-style: normal;
    color: black;
  }
  textarea {
    background-color: black;
    color: white;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid red;
    overflow: clip;
  }
  #status {
    background-color: yellow;
    color: black;
  }
  </style>
  <script type="text/javascript">

var oldContent = false;

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

function setStatus(text) {
  var status = document.getElementById("status");
  status.innerHTML = text;
}

function textChanged() {
  setStatus("changed.");
}

function setReady() {
  oldContent = document.getElementById("content").value;
  setTimeout("setStatus('Ready.')", 500);
  setTimeout("timedOut()", 3000);
}

function saveChanges() {
  setStatus("saving....");
  var req = createRequest();
  var contentElement = document.getElementById("content");
  req.open("POST", ".", false);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  req.send("action=edit&content="+contentElement.value);
  if (req.readyState == 4) { // completed
    if (req.status == 200) { // HTTP OK
      setStatus("Saved.");
    } else {
      setStatus("Server said: "+req.statusText);
    }
  } else {
    setStatus("Error contacting server....");
  }
  setReady();
  /*setTimeout("setStatus('Ready.')", 1000);
  isChanged = false;
  setTimeout("timedOut()", 1000);*/
}

function loadChanges() {
  setStatus("checking for changes....");
  var req = createRequest();
  var contentElement = document.getElementById("content");
  req.open("POST", "http://sickbox/modules/liki/", false);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  req.send("action=load");
  if (req.readyState == 4) { // completed
    if (req.status == 200) { // HTTP OK
      contentElement.value = req.responseText;
      setStatus("Loaded.");
    } else {
      setStatus("Server said: "+req.statusText);
    }
  } else {
    setStatus("Error contacting server....");
  }
  setReady();
}
 
function timedOut() {
  if (oldContent != document.getElementById("content").value) {
    saveChanges();
  } else {
    loadChanges();
  }
}
  </script>
 </head>
 <body onLoad="setReady()">
  <h1>liki &mdash; the <em>li</em>ve wi<em>ki</em></h1>
  <form action="." method="post">
   <textarea cols='80' rows='25' name='content' id="content"><?php if ($content === false) { @readfile($filename); } else { echo($content); } ?></textarea>
   <input type="hidden" name="action" value="edit" />
  </form>
  <div id='status'>Loading...</div>
 </body>
</html>
