<?php

require('bsLiki.class.php');
$l = new bsLiki();

$mainURI = $l->baseUrl."/".$l->activePage;
$params_readonly = $l->specialPage ? 'true' : 'false';
if (strtolower($l->activePage) == 'search') {
  $params_query = "'".$l->getRequest('q', true)."'";
  $searchfield = $l->getRequest('q', true);
} else {
  $params_query = 'false';
  $searchfield = 'search...';
}
$params = "'$mainURI', 5000, $params_readonly, $params_query";

$title = 'the <em>#burningsoda</em> liki';

$l->sendHeaders();
?>
<html>
 <head>
  <title>liki: <?php echo $l->activePage;?></title>
  <?php if (!$l->legacyMode) { ?><script type="text/javascript" src="<?php echo $l->baseUrl;?>/bs_liki.js"></script><?php } ?>
  <link rel="stylesheet" type="text/css" href="<?php echo $l->baseUrl;?>/liki.css" />
  <link rel="icon" href="favicon.ico" type="image/ico" />
  <link rel="Shortcut Icon" type="image/x-icon" href="<?php echo $l->baseUrl;?>/favicon.ico" />
 </head>
 <body id="mainbody"<?php if (!$l->legacyMode) { ?> onLoad="initLiki(<?=$params?>)"<?php } ?>>
  <a accessKey="f" href="<?php echo $l->baseUrl;?>/frontpage" id="likititle"><?=$title?></a>
<?php if (!$l->legacyMode) { ?>
  <div id="toolbar">
   <?php if (session_id()) { ?><a id="logoutbutton" accessKey="o" href="<?php echo $l->baseUrl;?>/?logout">log <u>o</u>ut</a> |<?php } ?>
   <span id="uploadbutton"><a accessKey="p" href="javascript:clickUploadButton()">insert <u>p</u>icture</a> |</span>
   <a id="editchecker" href="javascript:switchEditMode()" class="readmode">...</a>
  </div>
  <form id="contenteditor" action="." method="post" accept-charset="UTF-8">
   <div><textarea rows="10" cols="10" name='content' id="content"></textarea></div>
  </form>
  <div id="visitbar">
   <a href="javascript:toggleVisits();" accessKey="v">recent <u>v</u>isits</a>:
   <span id="recentvisits"></span>
  </div>
  <div id="navibar">
   <form id="searchform" action="<?php echo $l->baseUrl;?>/search" method="get"><input accessKey="s" onClick="clearSearchField();" id="searchfield" type="text" value="<?=$searchfield?>" name="q" /></form>
   <a accessKey="i" href="<?php echo $l->baseUrl;?>/INDEX"><u>i</u>ndex</a>,
   recently changed: <span id="recentchanges"></span>
  </div>
  <div id='viewcontent'></div>
  <div id='status'></div>
  <iframe id="uploadiframe" src="<?php echo $l->baseUrl;?>/<?php echo $l->activePage;?>?action=uploadform" />
<?php
} else {
  echo '<div style="visibility: visible;" id="viewcontent">';
  $p = $l->getFormattedPage($l->activePage);
  echo $p['content'];
  echo "</div>\n";
}
?>
 </body>
</html>
<?php

$l->backend->closeConnection();
exit;

?>
