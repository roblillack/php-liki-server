/*
 * bs|liki by Robert Lillack.
 * 
 * ©2005-2006 burningsoda.com
 */

var mainURI;
var timeout;
var editMode;
var interval;
var lockKey;
// pagecontent (unformatiert)
var pageContent;
var oldContent;
var transmitting;
var lastTimestamp;

/*function shakeElement(id, width) {
  var e = document.getElementById(id);

  if (width % 2) {
    e.style.left = e.style.left - 10;
  } else {
    alert('bla!');
    e.style.left = e.style.left + width*2 + 1;
  }
}*/

function setRecentChanges(what) {
  if (!what) return;
  var c = "recent Changes: ";
  var changes = what.split(" ");
  var baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  for (var i = 0; i < changes.length; i++) {
    if (i > 0 && i < changes.length) c += " |"
    c += " <a href=\""+baseURI+"/"+changes[i]+"\">"+changes[i]+"</a>";
  }
  document.getElementById("recentchanges").innerHTML=c;
}

function formatContent(input) {
  var preamble = ""
  input = preamble+input;
  var baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  // linie
  input = input.replace(/^-{1,}\s*\n/gm, "<hr/>\n");
  // sonderzeichen
  input = input.replace(/([^-]|\n)--([^-]|\n)/g, '$1&ndash;$2');
  input = input.replace(/([^-]|\n)---([^-]|\n)/g, '$1&mdash;$2');
  // _hervorgehoben_, *fett*, -durchgestrichen-
  input = input.replace(/([\s\W])_([\S][\S\ ]*?[\S])_([\s\W])/g, '$1<em>$2</em>$3');
  input = input.replace(/([\s\W])\*([\S][\S\ ]*?[\S])\*([\s\W])/g, '$1<strong>$2</strong>$3');
  input = input.replace(/([\s\W])-([\S][\S\ ]*?[\S])-([\s\W])/g, '$1<s>$2</s>$3');
  // liki-seiten
  input = input.replace(/\[\[([a-zA-Z0-9\-äöüßÄÖÜ]+)\]\]/g, '<a href="'+baseURI+'/$1">$1</a>');
  // listen
  input = input.replace(/^-\ +([^\n]+(\n\ +[^\s\n]|[^\n])+)[\s\n]*/gm, "<ul><li>$1</li></ul>\n");
  // header
  input = input.replace(/^#\ +([^\n]+)[\s]*/gm, '<h1>$1</h1>\n');
  // alle verbleibenden leerzeilen
  input = input.replace(/\n\s*\n/g, "\n<br/><br/>\n");
  return input;
}

function setEditMode(onoff) {
  var contentElement = document.getElementById("contenteditor");
  var view = document.getElementById('viewcontent');
  var checker = document.getElementById("editchecker");
  var body = document.getElementById("mainbody");

  if (onoff == false) {
    contentElement.style.visibility = 'hidden';
    view.style.display = 'block';
    checker.setAttribute('class', 'readmode');
    checker.innerHTML = 'edit';
    body.style.overflow = 'auto';
    setStatus('Ready.');
  } else {
    contentElement.style.visibility = 'visible';
    view.style.display = 'none';
    checker.setAttribute('class', 'editmode');
    checker.innerHTML = 'save &amp; quit';
    body.style.overflow = 'hidden';
    setStatus('Editing...');
  }
  editMode = onoff;
}

function switchEditMode() {
  document.getElementById("editchecker").setAttribute('class', 'transmitting');

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
    document.getElementById("viewcontent").innerHTML = formatContent(oldContent);
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
        setEditMode(true);
      } else {
        lockKey = false;
        setEditMode(false);
        setStatus('LIKI is locked by another user.');
        //shakeElement('viewcontent');
      }
    }
  }
}

function createFreeLockHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status == 200) {
        lockKey = false;
        setEditMode(false);
        setStatus('Saved & Happy.');
      } else {
        setEditMode(true);
        setStatus('Could not save!');
      }
    }
  }
}

function createSaveHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status == 200) {
        setStatus('Saved');
      } else {
        setEditMode(false);
      }
      transmitting = false;
    }
  }
}

function extractContent(xmldoc) {
  var content = '';
  var nodearray;
  var i;
  var row;
  
  nodearray = xmldoc/*.documentElement*/.getElementsByTagName('content');
  if (nodearray.length != 1) return '';
  nodearray = nodearray[0].childNodes;
  
  for (i = 0; i < nodearray.length; i++) {
    if (nodearray[i].nodeName == 's') content += " ";
    else if (nodearray[i].nodeName == 'n') content += "\n";
    else if (nodearray[i].nodeType == 3) {
      row = nodearray[i].data.replace("\n", "").replace("\r", "");
      content = content + row;
    }
  }

  return content;
}

function createTimestampHandler(req) {
  return function() {
    if (req.readyState == 4 &&
        req.status == 200) {
      // update the recently changes pages regardless of the timestamp:
      try { setRecentChanges(req.getResponseHeader('X-LIKI-RecentChanges')); } catch(e) {}
      // so, time to update?
      if (req.responseText > lastTimestamp) {
        setStatus("Loading changes...");
        var r = createRequest();
        r.onreadystatechange = createLoadHandler(r/*, req.responseText*/);
        //r.open("GET", mainURI+"?action=plainload", true);
        r.open("GET", mainURI+"?action=htmlload", true);
        //r.open("GET", mainURI+"?action=load", true);
        r.send("");
        return;
      }
    }
    setStatus("No changes.");
    transmitting = false;
  }
}

function createLoadHandler(req/*, ts*/) {
  return function() {
    if (req.readyState == 4) {
      if (editMode == false) {
        if (req.status == 200) {
          try { setRecentChanges(req.getResponseHeader('X-LIKI-RecentChanges')); } catch(e) {}
          //var text = extractContent(req.responseXML);
          var text = req.responseText;
          // KHTML BUG:
          pageContent = text.slice(text.indexOf("\n") + 2);
          var contentElement = document.getElementById("content");
          contentElement.value = pageContent;
          lastTimestamp = req.getResponseHeader('X-LIKI-Timestamp');
          document.getElementById("viewcontent").innerHTML = formatContent(pageContent);
          //lastTimestamp = req.responseXML.getElementsByTagName('timestamp')[0].firstChild.nodeValue;
          setStatus("Loaded.");
        }
      }
      transmitting = false;
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
  lastTimestamp = 0;
  transmitting = false;
  setEditMode(false);
  transmitChanges();
  interval = setInterval('transmitChanges()', timeout);
}

function saveChanges() {
  setStatus("Saving....");
  var req = createRequest();
  var contentElement = document.getElementById("content");
  oldContent = contentElement.value;
  document.getElementById("viewcontent").innerHTML = formatContent(oldContent);
  req.onreadystatechange = createSaveHandler(req);
  req.open("POST", mainURI, true);
  req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
  req.send("action=edit&key="+lockKey+"&content="+encodeURIComponent(contentElement.value));
}

function transmitChanges() {
  // try to avoid doubling transmits on sloooow connections
  if (transmitting) {
    return;
  }
  transmitting = true;
  if (editMode == true) {
    saveChanges();
  } else {
    setStatus("Checking for changes....");
    var req = createRequest();
    req.onreadystatechange = createTimestampHandler(req);
    req.open("GET", mainURI+"?action=timestamp", true);
    req.send("");
  }
}

