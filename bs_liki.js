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
var pageIsReadOnly;
var pageQuery;
// pagecontent (unformatiert)
var pageContent;
var oldContent;
var transmitting;
var lastTimestamp;
// DOM nodes:
var eEditButton;
var eViewContent;
var eEditContent;
var eEditForm;
var eStatusLine;
var eRecentChanges;

function dumpVars() {
  alert("VARIABLES:\n" +
        "mainURI: " + mainURI + "\n");
}

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
  what = decodeURIComponent(what);
  var c = "";
  var changes = what.split(" ");
  var baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  for (var i = 0; i < changes.length; i++) {
    if (i > 0 && i < changes.length) c += " |"
    var data = changes[i].split("/");
    c += " <a href=\""+baseURI+"/"+data[0]+"\">"+data[0]+"</a> <span class=\"time\">"+data[1]+"</span>";
  }
  eRecentChanges.innerHTML=c;
}

function formatContent(input) {
  var preamble = ""
  input = preamble+input;
  var baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  // links
  // input = input.replace(/(^|\s+)(http\:\/\/[^\s\"\']+)(\s+|$)/g, "$1<a href=\"$2\">$2</a>$3");
  // linie
  input = input.replace(/[\r\n]\ *\-{3,}\ *[\r\n]/g, '<bs:p><hr/></bs:p>');
  // sonderzeichen
  input = input.replace(/([^-]|[\r\n])--([^-]|[\r\n])/g, '$1&ndash;$2');
  input = input.replace(/([^-]|[\r\n])---([^-]|[\r\n])/g, '$1&mdash;$2');
  // _hervorgehoben_, *fett*, -durchgestrichen-
  input = input.replace(/([\s\W])_([\S][\S\ ]*?[\S])_([\s\W])/g, '$1<em>$2</em>$3');
  input = input.replace(/([\s\W])\*([\S][\S\ ]*?[\S])\*([\s\W])/g, '$1<strong>$2</strong>$3');
  input = input.replace(/([\s\W])-([\S][\S\ ]*?[\S])-([\s\W])/g, '$1<s>$2</s>$3');
  // liki-seiten
  input = input.replace(/\[\[([a-zA-Z0-9\-äöüßÄÖÜ]+)\]\]/g, '<a href="'+baseURI+'/$1">$1</a>');
  // listen
  input = input.replace(/^\-\ +([^\n](\n\ +[^\s]|[^\n])+)$/gm, "<bs:p><ul><li>$1</li></ul></bs:p>");
  // header
  input = input.replace(/^\#\ +([^\n]+)$/gm, '<bs:p><h1>$1</h1></bs:p>');
  // blockquotes
  input = input.replace(/^\"\ +([^\n](\n\ +[^\s]|[^\n])+)$/gm, '<bs:p><blockquote>$1</blockquote></bs:p>');
  // center
  input = input.replace(/^\|\ +([^\n](\n\ +[^\s]|[^\n])+)$/gm, '<bs:p><p style=\"text-align:center\">$1</p></bs:p>');
  // bilder
  input = input.replace(/^\s*(http\:\/\/[^\s\"\']+\.(gif|jpg|jpeg|png))\s*$/gm,
                        "<bs:p><img class=\"centerpic\" src=\"$1\" alt=\"\" /></bs:p>");
  // damit sind KEINE breaks mehr nach paragraphen vorhanden:
  input = input.replace(/<\/bs\:p>\s*/g, "\n");
  input = input.replace(/\s*<bs\:p>/g, "\n");
  // forcierte breaks
  input = input.replace(/\ \/\/\ *[\r\n]/g, "<br />");
  // absaetze
  input = input.replace(/\n\s*\n/g, "<br /><br />\n");
  return input;
}

function setEditMode(onoff) {
  var body = document.getElementById("mainbody");

  if (onoff == false) {
    eEditForm.style.visibility = 'hidden';
    eViewContent.style.display = 'block';
    eEditButton.setAttribute('class', 'readmode');
    eEditButton.setAttribute('accessKey', 'e');
    eEditButton.innerHTML = '<u>e</u>dit';
    body.style.overflow = 'auto';
    setStatus('Ready.');
  } else {
    eEditForm.style.visibility = 'visible';
    eViewContent.style.display = 'none';
    eEditButton.setAttribute('class', 'editmode');
    eEditButton.setAttribute('accessKey', 'q');
    eEditButton.innerHTML = 'save &amp; <u>q</u>uit';
    body.style.overflow = 'hidden';
    setStatus('Editing...');
    eEditContent.focus();
  }
  editMode = onoff;
}

function switchEditMode() {
  eEditButton.setAttribute('class', 'transmitting');

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
    oldContent = eEditContent.value;
    eViewContent.innerHTML = formatContent(oldContent);
    req.onreadystatechange = createFreeLockHandler(req);
    req.open("POST", mainURI, true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=utf-8");
    req.send("action=saveandfree&key="+lockKey+"&content="+encodeURIComponent(eEditContent.value));
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
      if (req.status && req.status == 200) {
        lockKey = req.responseText;
        setEditMode(true);
      } else {
        lockKey = false;
        setEditMode(false);
        setStatus('Page is locked.');
        //shakeElement('viewcontent');
      }
    }
  }
}

function createFreeLockHandler(req) {
  return function() {
    if (req.readyState == 4) {
      if (req.status && req.status == 200) {
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
      if (req.status && req.status == 200) {
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
          // update the recently changes pages:
          try { setRecentChanges(req.getResponseHeader('X-LIKI-RecentChanges')); } catch(e) {}
          //var text = extractContent(req.responseXML);
          var text = req.responseText;
          // KHTML BUG:
          pageContent = text.slice(text.indexOf("\n") + 1);
          eEditContent.value = pageContent;
          lastTimestamp = req.getResponseHeader('X-LIKI-Timestamp');
          eViewContent.innerHTML = formatContent(pageContent);
          //lastTimestamp = req.responseXML.getElementsByTagName('timestamp')[0].firstChild.nodeValue;
          setStatus("Loaded.");
          eEditButton.focus();
        }
      }
      transmitting = false;
    }
  }
}
 
function setStatus(text) {
  eStatusLine.innerHTML = text;
}

function initLiki(u, t, readonly, query) {
  timeout = t;
  mainURI = u;
  interval = false;
  lockKey = false;
  pageIsReadOnly = readonly;
  pageQuery = query;
  lastTimestamp = 0;
  transmitting = false;

  // init the elements
  eEditButton = document.getElementById('editchecker');
  eViewContent = document.getElementById('viewcontent');
  eEditForm = document.getElementById('contenteditor');
  eEditContent = document.getElementById('content');
  eStatusLine = document.getElementById('status');
  eRecentChanges = document.getElementById('recentchanges');

  setEditMode(false);
  transmitChanges();

  if (pageIsReadOnly) {
    eEditButton.style.display = 'none';
  }
  //dumpVars();
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
    // page has never been loaded:
    if (lastTimestamp == 0) {
      setStatus("Loading page....");
      var req = createRequest();
      req.onreadystatechange = createLoadHandler(req);
      var qstr = "";
      if (pageQuery != false) {
        // TODO: URI decoding, etc.
        qstr = "&q=" + pageQuery;
      }
      req.open("GET", mainURI+"?action=htmlload" + qstr, true);
      req.send("");
    } else {
      setStatus("Checking for changes....");
      var req = createRequest();
      req.onreadystatechange = createTimestampHandler(req);
      req.open("GET", mainURI+"?action=timestamp", true);
      req.send("");
    }
  }
}


function clearSearchField() {
  document.getElementById('searchfield').value = '';
}


