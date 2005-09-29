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
  header("Content-type: text/plain; charset=UTF-8");
  exit;
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
  #status {
    color: #777;
    font-size: 9px;
    font-family: sans-serif;
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

function createSaveHandler(req) {
  return function() {
    if (req.readyState == 4 && req.status == 200) {
      setStatus('Saved.');
    }
  }
}

function createLoadHandler(req) {
  return function() {
    var contentElement = document.getElementById("content");
    if (req.readyState == 4 && req.status == 200) {
      if (req.responseText != contentElement.value) {
        contentElement.value = req.responseText;
        setStatus("Loaded.");
      } else {
        setStatus("No changes.");
      }
    }
  }
}
 
function setStatus(text) {
  var status = document.getElementById("status");
  status.innerHTML = text;
}

function setReady() {
  oldContent = document.getElementById("content").value;
  setTimeout("setStatus('Ready.')", 500);
  setTimeout("timedOut()", 3000);
}

function saveChanges() {
  setStatus("Saving....");
  var req = createRequest();
  var contentElement = document.getElementById("content");
  req.onreadystatechange = createSaveHandler(req);
  req.open("POST", "<?php echo(bs_url()); ?>", true);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  req.send("action=edit&content="+contentElement.value);
  setReady();
}

function loadChanges() {
  setStatus("checking for changes....");
  var req = createRequest();
  req.onreadystatechange = createLoadHandler(req);
  req.open("POST", "<?php echo(bs_url()); ?>", true);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  req.send("action=load");
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
  <h1><em>liki</em> &mdash; the <em>li</em>ve wi<em>ki</em></h1>
  <form action="." method="post">
   <textarea cols='80' rows='25' name='content' id="content"><?php if ($content === false) { @readfile($filename); } else { echo($content); } ?></textarea>
   <input type="hidden" name="action" value="edit" />
  </form>
  <div id='status'>Loading...</div>
 </body>
</html>
