/*
 * bs|liki by Robert Lillack.
 * 
 * ©2005-2007 burningsoda.com
 */

var baseURI;
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
var eUploadButton;
var eViewContent;
var eEditContent;
var eEditForm;
var eStatusLine;
var eRecentChanges;
var eRecentVisits;
var eUploadIFrame;
var haveMSXML;
var switchEditModeOnSuccessfulLoad;

var lastContentEvent;
//var lastContentCursorPosition;

function clickUploadButton() {
  // attention! variables may not be initialized!
  if (!eUploadIFrame.style.display || eUploadIFrame.style.display == 'none') {
    eUploadIFrame.setAttribute('src', mainURI+'?action=uploadform');
    eUploadIFrame.style.display = 'block';
  } else {
    eUploadIFrame.style.display = 'none';
    eUploadIFrame.setAttribute('src', 'about:blank');
  }
}

function doUpload() {
  //eEditContent = window.parent.document.getElementById('content');
  document.getElementById('uploadform').submit();
  document.getElementById('uploadform').style.display = 'none';
}

function uploadSuccess(picurl) {
  // attention! all vars are not initialized.
  eUploadIFrame = window.parent.document.getElementById('uploadiframe');
  if (!eUploadIFrame) return;
  clickUploadButton();
  eEditContent = window.parent.document.getElementById('content');
  var cursorpos = eEditContent.selectionStart;
  var contentBefore = eEditContent.value.substring(0, cursorpos);
  var contentAfter = eEditContent.value.substring(cursorpos);
  //alert('picurl: ' + picurl + '\ncursorpos: ' +cursorpos);
  eEditContent.value = contentBefore + '\n' + picurl + '\n' + contentAfter;
  eEditContent.selectionStart = cursorpos + picurl.length + 1;
  eEditContent.selectionEnd = cursorpos + picurl.length + 1;
  eEditContent.focus();
}

/*function handleContentEvent(name) {
  //document.getElementById('uploadform').elements['filedroptarget'].value = 'Content-Event: ' +name;
  var eIFrame = document.getElementById('uploadiframe').contentDocument;

  switch (name) {
    case 'mouseout':
      pageContent = eEditContent.value;
      lastContentCursorPosition = eEditContent.selectionStart;
      eIFrame.getElementById('uploadform').elements['filedroptarget'].value = 'cursor: ' +lastContentCursorPosition;
      break;
    case 'mouseover':
      if (lastContentEvent == 'mouseout' && pageContent != eEditContent.value) {
        var endPos = eEditContent.selectionStart;
        var beginPos = pageContent.length - eEditContent.value.substring(endPos).length;
        var filename = eEditContent.value.substring(beginPos, endPos);
        eEditContent.value = pageContent;
        eEditContent.selectionStart = beginPos;
        eEditContent.selectionEnd = beginPos;
        eEditContent.focus();
        //pageContent = eEditContent.value;
        //alert('SOMETHING HAS BEEN DROPPED: '+filename);
        eIFrame.getElementById('uploadform').elements['picfile'].setAttribute('value', filename);
        eIFrame.getElementById('uploadform').elements['cursorpos'].setAttribute('value', beginPos);
      }
      break;
    default:
  }
  lastContentEvent = name;
}

function fileDropped() {
  var uploadForm = document.getElementById('uploadform');
  var inputField = uploadForm.elements['filedroptarget'];
  var fileField = uploadForm.elements['picfile'];

  if (fileField.value == inputField.value) return;
  fileField.value = inputField.value;
  uploadForm.submit();

  //alert('dateiname: '+uploadForm.elements['filedroptarget'].value);
}*/

function updateFromHeaderData(req) {
  try {
    updateTimestamps(eRecentChanges, req.getResponseHeader('X-LIKI-RecentChanges'));
  } catch(e) {}
  try {
    updateTimestamps(eRecentVisits, req.getResponseHeader('X-LIKI-RecentVisits'));
  } catch(e) {}
}

function updateTimestamps(element, what) {
  if (!what) return;
  try {
    what = decodeURIComponent(what);
  } catch(e) {
    if (e.name == "URIError") {
      setStatus('Malformed timestamp list received.');
    }
    return;
  }
  var c = "";
  //alert(what);
  var changes = what.split(",");
  //var baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  for (var i = 0; i < changes.length; i++) {
    if (i > 0 && i < changes.length) c += " |"
    var data = changes[i].split("/");
    c += " <a href=\""+encodeURI(baseURI+"/"+data[0])+"\">"+formatXMLchars(data[0])+"</a> <span class=\"time\">"+data[1]+"</span>";
  }
  element.innerHTML=c;
}

function toggleVisits() {
  var eVisitBar = document.getElementById("visitbar");
  if (eVisitBar.style.display == 'block') {
    eVisitBar.style.display = 'none';
  } else {
    eVisitBar.style.display = 'block';
  }
}


function formatContent(input) {
  /* make shure everything that tries to be a paragraph _really_
   * is delimited by at least one empty line. */
  // one line paragraphs (i.e. section headings)
  input = input.replace(/(^|\n)([\#\*])\ ([^\n]+)/g, '$1\n$2 $3\n');
  // multiline paragraphs
  input = input.replace(/(^|\n)(([\-\+\"\'\;\|\!]|\[(\ |X|\*)\]|\((\ |X|\*)\))\ ([^\n](\n\ [^\n]|[^\n])+))/g, '\n$1$2\n');
  // lines
  input = input.replace(/(^|\n)---+\ *\n/g, '$1---\n\n');
  // now, split it up.
  var p = input.replace(/^\s*/,'').split(/\n\s*\n/);
  input = "";
  var insidesection = false;
  
  for (var i = 0; i < p.length; i++) {
    var type = getParagraphType(p[i]);
    var content = cleanParagraph(p[i]);
    
    if (type == '*' || type == '#') {
      if (insidesection) {
        input += "</span>\n";
        insidesection = false;
      }
    } else {
      if (!insidesection) {
        input += "<span class='section'>\n";
        insidesection = true;
      }
    }

    switch (type) {
      case '':
        // normal text paragraphs
        input += "<p>" + formatParagraph(content) + "</p>\n";
        break;
      case '-': case '+':
        // lists
        if (i == 0 || getParagraphType(p[i-1]) != type) {
          if (type == '-') input += "<ul>\n";
          else input += "<ol>\n";
        }
        input += " <li>" + formatParagraph(content) + "</li>\n";
        if (i == p.length - 1 || getParagraphType(p[i+1]) != type) {
          if (type == '-') input += "</ul>\n";
          else input += "</ol>\n";
        }
        break;
      case '#':
        input += "\n\n<h1>" + formatParagraph(content) + "</h1>\n";
        break;
      case '*':
        input += "\n\n<h2>" + formatParagraph(content) + "</h2>\n";
        break;
      case '"':
        input += "<blockquote>" + formatParagraph(content) + "</blockquote>\n";
        break;
      case '\'':
        input += "<blockquote class=\"comment\">"
                 + formatParagraph(content) + "</blockquote>\n";
        break;
      case ';':
        input += "<pre>" + formatCodeParagraph(content) + "</pre>\n";
        break;
      case '|':
        input += '<p style="text-align: center;">' + formatParagraph(content) + '</p>\n';
        break;
      case 'check':
      case 'check-checked':
        var prev = getParagraphType(p[i-1]);
        var next = getParagraphType(p[i+1]);
        if (i == 0 || (prev != 'check' && prev != 'check-checked')) {
          input += "<ul class='check'>\n";
        }
        input += " <li class=";
        if (type == 'check-checked') input += '"checked"';
        else input += '"unchecked"';
        input += ">" + formatParagraph(content) + "</li>\n";
        if (i == p.length - 1 || (next != 'check' && next != 'check-checked')) {
          input += "</ul>\n";
        }
        break;
      case 'radio':
      case 'radio-checked':
        var prev = getParagraphType(p[i-1]);
        var next = getParagraphType(p[i+1]);
        if (i == 0 || (prev != 'radio' && prev != 'radio-checked')) {
          input += "<ul class='radio'>\n";
        }
        input += " <li class=";
        if (type == 'radio-checked') input += '"checked"';
        else input += '"unchecked"';
        input += ">" + formatParagraph(content) + "</li>\n";
        if (i == p.length - 1 || (next != 'radio' && next != 'radio-checked')) {
          input += "</ul>\n";
        }
        break;
      case 'imagelink':
        matches = content.match(/^\[(\S+)\s+(\S+)\]\s*$/)
        input += '<a href="' + matches[1] + '"><img src="' + matches[2] + '" alt="" class="centerpic" /></a>\n';
        break;
      case 'image':
        input += '<img src="' + content + '" alt="" class="centerpic" />\n';
        break;
      case 'music':
        input += '<p><a href="' + content + '" class="music">' +
                 content.match(/^.*\/[0-9]+-.+--([^\s\"\'\/]+\.([a-z0-9]+))\s*$/i)[1] +
                 '</a></p>\n';
        break;
      case 'video':
        input += '<p><a href="' + content + '" class="video">' +
                 content.match(/^.*\/[0-9]+-.+--([^\s\"\'\/]+\.([a-z0-9]+))\s*$/i)[1] +
                 '</a></p>\n';
        break;
      case 'line':
        input += '<hr />\n';
        break;
      case '!':
        input += content;
        break;
      default:
        input += "<br/><b>Type: ["+type+"]</b>";
        input += "<pre style='border: 1px solid red;'>"+content+"</pre>";
    }
  }
  return input;
}

function getParagraphType(p) {
  if (p.match(/^[\#\*\-\+\"\'\;\|\!] /)) return p.charAt(0);
  else if (p.match(/^---+\s*$/)) return 'line';
  else if (p.match(/^\[ \] .*$/i)) return 'check';
  else if (p.match(/^\( \) .*$/i)) return 'radio';
  else if (p.match(/^\[(X|\*)\] .*$/i)) return 'check-checked';
  else if (p.match(/^\((X|\*)\) .*$/i)) return 'radio-checked';
  else if (p.match(/^\[http\:\/\/[^\s\"\']+\s+http\:\/\/[^\s\"\']+\.(bmp|gif|jpg|jpeg|png)\]\s*$/i)) return 'imagelink';
  else if (p.match(/^http\:\/\/[^\s\"\']+\.(bmp|gif|jpg|jpeg|png)\s*$/i)) return 'image';
  else if (p.match(/^http\:\/\/[^\s\"\']+\.(mp3|ogg|aac|mpc|wma)\s*$/i)) return 'music';
  else if (p.match(/^http\:\/\/[^\s\"\']+\.(avi|mpg|wmv|mov|asf|flv)\s*$/i)) return 'video';
  else return '';
}

function cleanParagraph(p) {
  // clean the leading marker and/or spaces (for code)
  return p.replace(/(^|\n)\ \ ([^\n]+)/g, '$1$2').replace(/^([\#\*\-\+\"\'\;\|\!]|\[(\ |X|x|\*)\]|\((\ |X|x|\*)\)) /, '');
}

function formatXMLchars(input) {
  // xml special characters
  input = input.replace(/&/g, '&amp;');
  input = input.replace(/</g, '&lt;');
  input = input.replace(/>/g, '&gt;');
  return input;
}

function formatCodeParagraph(c) {
  c = formatXMLchars(c);
  c = c.replace(/^~$/gm, '');
  return c;
}

/*function formatSpan(p) {
  var spanTypes = new Array(
    // startchar, endchar, regexp, replace
    new Array(
      /([\w]+|#[0-9a-f]{3}([0-9a-f]{3})?)/
...later */

function formatParagraph(p) {
  p = formatXMLchars(p);
  // some symbols
  p = p.replace(/(^|[^-])--(?=([^-]|$))/g, '$1&ndash;');
  p = p.replace(/(^|[^-])---(?=([^-]|$))/g, '$1&mdash;');
  // _emphasized_, *bold*, -striked-, `code`
  p = p.replace(/(\W|^)\*(\S[\S\ ]*?\S)\*(?=(\W|$))/g, '$1<strong>$2</strong>');
  p = p.replace(/(\W|^)\-(\S[\S\ ]*?\S)\-(?=(\W|$))/g, '$1<s>$2</s>');
  p = p.replace(/(\W|^)\_(\S[\S\ ]*?\S)\_(?=(\W|$))/g, '$1<em>$2</em>');
  p = p.replace(/(\W|^)\`(\S[\S\ ]*?\S)\`(?=(\W|$))/g, '$1<code>$2</code>');
  // externe links
  p = p.replace(/([\s]|^)(http\:\/\/[^\s\"\'\(\)\[\]\{\}]+)(?=(\s|$))/g, '$1<a class="external" href="$2">$2</a>');
  // externe links (mit text)
  p = p.replace(/\[(http\:\/\/[^\s\"\'\(\)\[\]\{\}]+)\]/g, '<a class="external" href="$1">$1</a>');
  p = p.replace(/\[(http\:\/\/[^\s\"\'\(\)\[\]\{\}]+)\s+([^\[\]]+)\]/g, '<a class="external" href="$1">$2</a>');
  // liki-seiten
  p = p.replace(/\[\[?([^\'\"\]\[\%\s\/\\]+)\]?\]/g,
                '<a class="internal" href="' + baseURI + '/$1">$1</a>');
  // liki-seiten (mit text)
  p = p.replace(/(^|[^\\])\[\[?([^\'\"\]\[\%\s\/\\]+)\ ([\S][\S\ ]*?[\S]+)\]?\]/g,
                '$1<a class="internal" href="' + baseURI + '/$2">$3</a>');
  // colors
  p = p.replace(/\{(aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|purple|red|silver|teal|white|yellow|#[0-9a-f]{3}([0-9a-f]{3})?)\ (.*?[^\\])\}/gi,
                '<span style="color:$1;">$3</span>');
  // embedded images
  p = p.replace(/{img(&gt;|R)\s+(http\:\/\/[^\s\"\'}]+\.(bmp|gif|jpg|jpeg|png))\s*}/gi,
                '<img src="$2" style="float:right;" />');
  p = p.replace(/{img\s+(http\:\/\/[^\s\"\'}]+\.(bmp|gif|jpg|jpeg|png))\s*}/gi,
                '<img src="$1" />');
  p = p.replace(/{img(&lt;|L)\s+(http\:\/\/[^\s\"\'}]+\.(bmp|gif|jpg|jpeg|png))\s*}/gi,
                '<img src="$2" style="float:left;" />');

  // forced line breaks
  p = p.replace(/\ \/\/\ *[\r\n]/g, "<br />");
  // escaping
  p = p.replace(/\\(.)/g, '$1');

  return p;
}

function setEditMode(onoff) {
  var body = document.getElementById("mainbody");

  if (onoff == false) {
    eEditForm.style.visibility = 'hidden';
    eViewContent.style.display = 'block';
    eEditButton.setAttribute('class', 'readmode');
    eEditButton.setAttribute('accessKey', 'e');
    eEditButton.innerHTML = '<u>e</u>dit';
    eUploadButton.style.display = 'none';
    body.style.overflow = 'auto';
    setStatus('Ready.');
  } else {
    eEditForm.style.visibility = 'visible';
    eViewContent.style.display = 'none';
    eEditButton.setAttribute('class', 'editmode');
    eEditButton.setAttribute('accessKey', 'q');
    eEditButton.innerHTML = 'save &amp; <u>q</u>uit';
    eUploadButton.style.display = 'inline';
    body.style.overflow = 'hidden';
    setStatus('Editing...');
    eEditContent.focus();
  }
  editMode = onoff;
}

function switchEditMode() {
  eEditButton.setAttribute('class', 'transmitting');

  if (editMode == false) {
  	if (transmitting) {
  		switchEditModeOnSuccessfulLoad = true;
  		return;
  	}
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
  haveMSXML = true;
  if (window.XMLHttpRequest) {
    req = new XMLHttpRequest();
    haveMSXML = false;
    return req;
  } else if (window.ActiveXObject) {
    try {
      req = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e1) {
      try {
        req = new ActiveXObject("Microsoft.XMLHTTP");
      } catch (e2) {
        alert('Could not create XMLHTTPRequest.');
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
        //alert('got save-status of '+req.status+'. ignoring :)');
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
      updateFromHeaderData(req);
      // so, time to update?
      if (req.responseText > lastTimestamp) {
        setStatus("Loading changes...");
        var r = createRequest();
        r.onreadystatechange = createLoadHandler(r);
        var qstr = "";
        if (pageQuery != false) {
          // TODO: URI decoding, etc.
          qstr = "&q=" + pageQuery;
        }
        r.open("POST", mainURI+"?action=load" + qstr, true);
        if (haveMSXML) r.send(); else r.send("");
        return;
      }
    }
    setStatus("No changes.");
    transmitting = false;
  }
}

function getPageScroll() {
  var yScroll;
  if (self.pageYOffset) {
    yScroll = self.pageYOffset;
  } else if (document.documentElement && document.documentElement.scrollTop) { // Explorer 6 Strict
    yScroll = document.documentElement.scrollTop;
  } else if (document.body) {  // all other Explorers
    yScroll = document.body.scrollTop;
  }
  return yScroll;
}

function createLoadHandler(req/*, ts*/) {
  return function() {
    if (req.readyState == 4) {
      if (editMode == false) {
        if (req.status == 200) {
          // update the recently changes pages:
          updateFromHeaderData(req);
          //var text = extractContent(req.responseXML);
          var text = req.responseText;
          // KHTML BUG:
          pageContent = text.slice(text.indexOf("\n") + 1);
          //pageContent = text;
          eEditContent.value = pageContent;
          lastTimestamp = req.getResponseHeader('X-LIKI-Timestamp');
          var scroll1 = getPageScroll();
          eViewContent.innerHTML = formatContent(pageContent);
          //var scroll2 = getPageScroll();
          // TODO: scrollpos.
          //lastTimestamp = req.responseXML.getElementsByTagName('timestamp')[0].firstChild.nodeValue;
          setStatus("Loaded.");
          /*setStatus("scroll: " + scroll1 + " / " + scroll2);*/
          if (switchEditModeOnSuccessfulLoad) {
          	transmitting = false;
          	switchEditModeOnSuccessfulLoad = false;
          	switchEditMode();
          }
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
  haveMSXML = false;
  switchEditModeOnSuccessfulLoad = false;

  baseURI = mainURI.replace(/^(.*)\/.*\/?$/, '$1');
  
  // init the elements
  eEditButton = document.getElementById('editchecker');
  eUploadButton = document.getElementById('uploadbutton');
  eViewContent = document.getElementById('viewcontent');
  eEditForm = document.getElementById('contenteditor');
  eEditContent = document.getElementById('content');
  eStatusLine = document.getElementById('status');
  eRecentChanges = document.getElementById('recentchanges');
  eRecentVisits = document.getElementById('recentvisits');
  eUploadIFrame = document.getElementById('uploadiframe');
  
  setEditMode(false);
  transmitChanges();

  if (pageIsReadOnly) {
    eEditButton.style.display = 'none';
  } else {
    // search-as-you-type doesn't work in konqueror without this one:
    eEditButton.focus();
  }
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
      try {
        if (haveMSXML) req.open("POST", mainURI+ "?action=load" + qstr, true);
        else req.open("GET", mainURI + "?action=load" + qstr, true);
      } catch(e) {
        alert('could not open request: '+e.description);
        setStatus('Loading Page aborted.');
        return false;
      }
      //mainURI+"?action=load" + qstr, true);
      try {
        if (haveMSXML) req.send();
        else req.send(null);
      } catch(e) {
        alert('could not send request: '+e.description);
        setStatus('Loading Page aborted.');
        return false;
      }
    } else {
      setStatus("Checking for changes....");
      var req = createRequest();
      req.onreadystatechange = createTimestampHandler(req);
      try {
        if (haveMSXML) req.open("POST", mainURI+ "?action=timestamp", true);
        else req.open("GET", mainURI + "?action=timestamp", true);
      } catch(e) {
        alert('could not open request: '+e.description);
        setStatus('Loading Page aborted.');
        return false;
      }
      if (haveMSXML) req.send();
      else req.send(null);
    }
  }
}


function clearSearchField() {
  document.getElementById('searchfield').value = '';
}


