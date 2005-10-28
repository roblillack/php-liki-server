var mainURI;
var timeout;
var editMode;
var interval;
var lockKey;
var oldContent;

function setEditMode(onoff) {
  var contentElement = document.getElementById("content");
  var view = document.getElementById('viewcontent');
  var checker = document.getElementById("editchecker");
  var body = document.getElementById("mainbody");

  if (onoff == false) {
    contentElement.style.visibility = 'hidden';
    view.style.display = 'block';
    checker.style.backgroundColor = 'white';
    checker.style.color = 'black';
    body.style.overflow = 'auto';
    setStatus('Ready.');
  } else {
    contentElement.style.visibility = 'visible';
    view.style.display = 'none';
    checker.style.backgroundColor = 'red';
    checker.style.color = 'white';
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
    req.open("POST", mainURI, true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.send("action=requestlock");
    setStatus('trying to acquire lock');
  } else {
    setStatus("saving and freeing lock....");
    var req = createRequest();
    var contentElement = document.getElementById("content");
    oldContent = contentElement.value;
    document.getElementById("viewcontent").innerHTML = oldContent;
    req.onreadystatechange = createFreeLockHandler(req);
    req.open("POST", mainURI, true);
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
      if (req.status == 200) {
        lockKey = req.responseText;
        alert('key: _'+lockKey+"_");
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
          // text = req.responseXML.documentElement.getElementsByTagName('content')[0].firstChild.nodeValue;
          /* weil konqueror ein newlines selbst aus [CDATA] rausfiltert(!), kriegt
           * jede zeile auf dem server einen neuen textknoten.... */
          var text = '';
          var a = req.responseXML.documentElement.getElementsByTagName('content')[0].childNodes;
          for (var i = 0; i < a.length; i++) text = text + a[i].nodeValue + "\r\n";

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

function initLiki(u, t) {
  timeout = t;
  mainURI = u;
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
  req.open("POST", mainURI, true);
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
    req.open("POST", mainURI, true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
    req.send("action=load");
  }
}

