<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

error_reporting(E_ERROR | E_PARSE);

$tmpDir = "viewer";
$fileUploadLimitBytes = 4194304;
$fileUploadLimitText = round(($fileUploadLimitBytes / 1048576), 0) . "MB";

if (!get_pgn()) { $pgnText = NULL; }
print_header();
print_form();
check_tmpDir();
print_chessboard();
print_footer();


function get_pgn() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;
  global $fileUploadLimitText, $fileUploadLimitBytes;

  $pgnDebugInfo = $_REQUEST["debug"];

  $pgnText = $_REQUEST["pgnText"];
  if (!$pgnText) { $pgnText = $_REQUEST["pgnTextbox"]; }
  if (!$pgnText) { $pgnText = $_REQUEST["pt"]; }
  $pgnUrl = $_REQUEST["pgnUrl"];
  if (!$pgnUrl) { $pgnUrl = $_REQUEST["pu"]; }

  if ($pgnText) {
    $pgnStatus = "PGN from direct user input";
    $pgnTextbox = $pgnText = str_replace("\\\"", "\"", $pgnText);

    $pgnText = preg_replace("/\[/", "\n\n[", $pgnText);
    $pgnText = preg_replace("/\]/", "]\n\n", $pgnText);
    $pgnText = preg_replace("/([012\*])(\s*)(\[)/", "$1\n\n$3", $pgnText);
    $pgnText = preg_replace("/\]\s*\[/", "]\n[", $pgnText);
    $pgnText = preg_replace("/^\s*\[/", "[", $pgnText);
    $pgnText = preg_replace("/\n[\s*\n]+/", "\n\n", $pgnText);
    
    $pgnTextbox = $pgnText;

    return TRUE;
  } else if ($pgnUrl) {
    $pgnStatus = "PGN from URL <a href='" . $pgnUrl . "'>" . $pgnUrl . "</a>";
    $isPgn = preg_match("/\.pgn$/i",$pgnUrl);
    $isZip = preg_match("/\.zip$/i",$pgnUrl);
    if ($isZip) {
      $tempZipName = tempnam($tmpDir, "pgn4webViewer");
      $pgnUrlHandle = fopen($pgnUrl, "rb");
      $tempZipHandle = fopen($tempZipName, "wb");
      $copiedBytes = stream_copy_to_stream($pgnUrlHandle, $tempZipHandle, $fileUploadLimitBytes + 1, 0);
      fclose($pgnUrlHandle);
      fclose($tempZipHandle);
      if (($copiedBytes > 0) & ($copiedBytes <= $fileUploadLimitBytes)) {
        $pgnSource = $tempZipName;
      } else {
	$pgnStatus = "Failed to get remote zipfile (file not found, file exceeds " . $fileUploadLimitText . " limit or server error)";
        if (($tempZipName) & (file_exists($tempZipName))) { unlink($tempZipName); }
        return FALSE;
      }
    } else {
      $pgnSource = $pgnUrl;
    }
  } elseif ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
    $pgnFileName = $_FILES['pgnFile']['name'];
    $pgnStatus = "PGN from user file " . $pgnFileName;
    $pgnFileSize = $_FILES['userfile']['size'];
    if ($pgnFileSize > $fileUploadLimitBytes) {
      $pgnStatus = "Uploaded file exceeds " . $fileUploadLimitText . " limit";
      return FALSE;
    } else { 
      $isPgn = preg_match("/\.pgn$/i",$pgnFileName);
      $isZip = preg_match("/\.zip$/i",$pgnFileName);
      $pgnSource = $_FILES['pgnFile']['tmp_name'];
    }
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_INI_SIZE | UPLOAD_ERR_FORM_SIZE)) {
    $pgnStatus = "Uploaded file exceeds " . $fileUploadLimitText . " limit";
    return FALSE;
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_PARTIAL | UPLOAD_ERR_NO_FILE | UPLOAD_ERR_NO_TMP_DIR | UPLOAD_ERR_CANT_WRITE | UPLOAD_ERR_EXTENSION)) {
    $pgnStatus = "Error uploading PGN data (file not found or server error)";
    return FALSE;
  } else {
    $pgnStatus = "Please provide PGN data (files must be smaller than " . $fileUploadLimitText . ")";
    return FALSE;
  }

  if ($isZip) {
    $pgnZip = zip_open($pgnSource);
    if (is_resource($pgnZip)) {
      while (is_resource($zipEntry = zip_read($pgnZip))) {
	if (zip_entry_open($pgnZip, $zipEntry)) {
	  if (preg_match("/\.pgn$/i",zip_entry_name($zipEntry))) {
	    $pgnText = $pgnText . zip_entry_read($zipEntry, zip_entry_filesize($zipEntry)) . "\n\n\n";
          }
          zip_entry_close($zipEntry);
	} else {
          $pgnStatus = "Failed reading zipfile content";
          zip_close($pgnZip);
          if (($tempZipName) & (file_exists($tempZipName))) { unlink($tempZipName); }
          return FALSE;
        }
      }
      zip_close($pgnZip);
      if (($tempZipName) & (file_exists($tempZipName))) { unlink($tempZipName); }
      if (!$pgnText) {
        $pgnStatus = "No PGN data found in zipfile";
        return FALSE;
      } else {
        return TRUE;
      }
    } else {
      $pgnStatus = "Failed opening zipfile";
      return FALSE;
    }
  }

  if($isPgn) {
    $pgnText = file_get_contents($pgnSource, NULL, NULL, 0, $fileUploadLimitBytes + 1);
    if (!$pgnText) {
      $pgnStatus = "Failed reading PGN data (server error)";
      return FALSE;
    }
    if ((strlen($pgnText) == 0) | (strlen($pgnText) > $fileUploadLimitBytes)) {
      $pgnStatus = "Failed reading PGN data (file exceeds " . $fileUploadLimitText . " limit or server error)";
      return FALSE;
    }
    return TRUE;
  } 

  if($pgnSource) {
    $pgnStatus = "Only PGN and ZIP (zipped pgn) files are supported";
    return FALSE;
  }

  return TRUE;
}

function check_tmpDir() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;
  global $fileUploadLimitText, $fileUploadLimitBytes;

  $tmpDirHandle = opendir($tmpDir);
  while($entryName = readdir($tmpDirHandle)) {
    if (($entryName !== ".") & ($entryName !== "..") & ($entryName !== "index.html")) {
      if ((time() - filemtime($tmpDir . "/" . $entryName)) > 3600) { 
        $unexpectedFiles = $unexpectedFiles . " " . $entryName;
      }
    }
  }
  closedir($tmpDirHandle);

  if ($unexpectedFiles) {
    $pgnDebugInfo = $pgnDebugInfo . "message for sysadmin: clean temporary directory " . $tmpDir . ":" . $unexpectedFiles; 
  }
}

function print_header() {

  print <<<END

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web PGN viewer</title> 

<style type="text/css">

body {
  color: black;
  background: white; 
  font-family: 'pgn4web Liberation Sans', sans-serif;
  line-height: 1.3em;
  padding: 20px;
}

a:link, a:visited, a:hover, a:active { 
  color: black; 
  text-decoration: none;
}

.formControl {
  font-size: smaller;
}

</style>

</head>

<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td align="left" valign="middle"> 
<h1 name="top" style="font-family: sans-serif; color: red;"><a style="color: red;" href=.>pgn4web</a> PGN viewer</h1> 
</td>
<td align="right" valign="middle">
<a href=.><img src=pawns.png border=0></a>
</td>
</tr></tbody></table>

<div style="height: 1em;">&nbsp;</div>

END;
}


function print_form() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;
  global $fileUploadLimitText, $fileUploadLimitBytes;

  $thisScript = $_SERVER['SCRIPT_NAME'];

  print <<<END

<script type="text/javascript">

  function disableShortcutKeysAndStoreStatus() {}
  function restoreShortcutKeysStatus() {}
  function start_pgn4web() {}
  
  function setPgnUrl(newPgnUrl) {
    if (!newPgnUrl) { newPgnUrl = ""; }
    document.getElementById("urlFormText").value = newPgnUrl;
    return false;
  }

  function checkPgnUrl() {
    theObject = document.getElementById("urlFormText");
    if (theObject === null) { return false; }
    return (theObject.value !== "");
  }

  function checkPgnFile() {
    theObject = document.getElementById("uploadFormFile");
    if (theObject === null) { return false; }
    return (theObject.value !== "");
  }

  function checkPgnFormTextSize() {
    document.getElementById("pgnFormButton").title = "PGN text box size is " + document.getElementById("pgnFormText").value.length;
    if (document.getElementById("pgnFormText").value.length == 1) {
      document.getElementById("pgnFormButton").title += " char";
    } else {
      document.getElementById("pgnFormButton").title += " chars";
    }
  }

  function loadPgnFromForm() {
    theObjectPgnFormText = document.getElementById('pgnFormText');
    if (theObjectPgnFormText === null) { return; }
    if (theObjectPgnFormText.value === "") { return; }

    theObjectPgnText = document.getElementById('pgnText');
    if (theObjectPgnText === null) { return; }

    theObjectPgnText.value = theObjectPgnFormText.value;

    theObjectPgnText.value = theObjectPgnText.value.replace(/\\[/g,'\\n\\n[');
    theObjectPgnText.value = theObjectPgnText.value.replace(/\\]/g,']\\n\\n');
    theObjectPgnText.value = theObjectPgnText.value.replace(/([012\\*])(\\s*)(\\[)/g,'\$1\\n\\n\$3');
    theObjectPgnText.value = theObjectPgnText.value.replace(/\\]\\s*\\[/g,']\\n[');
    theObjectPgnText.value = theObjectPgnText.value.replace(/^\\s*\\[/g,'[');
    theObjectPgnText.value = theObjectPgnText.value.replace(/\\n[\\s*\\n]+/g,'\\n\\n');

    document.getElementById('pgnStatus').innerHTML = "PGN from direct user input";
    document.getElementById('uploadFormFile').value = "";
    document.getElementById('urlFormText').value = "";

    firstStart = true;
    start_pgn4web();
    if (window.location.hash == "view") { window.location.reload(); }   
    else {window.location.hash = "view"; }  
 
    return;
  }

  function urlFormSelectChange() {
    theObject = document.getElementById("urlFormSelect");
    if (theObject === null) { return; }
  
    switch (theObject.value) {
      case "twic":
        givenTwicNumber = 765;
        epochTimeOfGivenTwic = 1246921199; // Mon July 6th, 23:59:59 GMT
        nowDate = new Date();
        epochTimeNow = nowDate.getTime() / 1000;
        twicNum = givenTwicNumber + Math.floor((epochTimeNow - epochTimeOfGivenTwic) / (60 * 60 * 24 * 7))
	document.getElementById("urlFormText").value = "http://www.chesscenter.com/twic/zips/twic" + twicNum + "g.zip";;
        theObject.value = "header";
      break;

      case "nic":
	givenNicYear = 2009;
        givenNicIssue = 1;
        epochTimeOfGivenNic = 1232585999; // Jan 21st, 23:59:59 GMT
        nowDate = new Date();
	epochTimeNow = nowDate.getTime() / 1000;
        nicYear = givenNicYear + Math.floor((epochTimeNow - epochTimeOfGivenNic) / (60 * 60 * 24 * 365.25));
        nicIssue = 1 + Math.floor((epochTimeNow - (epochTimeOfGivenNic + (nicYear - givenNicYear) * (60 * 60 * 24 * 365.25))) / (60 * 60 * 24 * 365.25 / 8));
        document.getElementById("urlFormText").value = "http://www.newinchess.com/Magazine/GameFiles/mag_" + nicYear + "_" + nicIssue + "_pgn.zip";
        theObject.value = "header";
      break;

      default:
        document.getElementById("urlFormText").value = "";
        theObject.value = "header";
      break;
    }
  }

</script>

<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody>

<form id="uploadForm" action="$thisScript" enctype="multipart/form-data" method="POST">
  <tr>
    <td align="left" valign="top">
      <input id="uploadFormSubmitButton" type="submit" class="formControl" value="show games from PGN (or zipped PGN) file" style="width:100%" title="PGN and ZIP files must be smaller than $fileUploadLimitText" onClick="return checkPgnFile();">
    </td>
    <td colspan=2 width="100%" align="left" valign="top">
      <input type="hidden" name="MAX_FILE_SIZE" value="$fileUploadLimitBytes">
      <input id="uploadFormFile" name="pgnFile" type="file" class="formControl" style="width:100%" title="PGN and ZIP files must be smaller than $fileUploadLimitText">
    </td>
  </tr>
</form>

<form id="urlForm" action="$thisScript" method="POST">
  <tr>
    <td align="left" valign="top">
      <input id="urlFormSubmitButton" type="submit" class="formControl" value="show games from PGN (or zipped PGN) URL" title="PGN and ZIP files must be smaller than $fileUploadLimitText" onClick="return checkPgnUrl();">
    </td>
    <td width="100%" align="left" valign="top">
      <input id="urlFormText" name="pgnUrl" type="text" class="formControl" value="" style="width:100%" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" title="PGN and ZIP files must be smaller than $fileUploadLimitText">
    </td>
    <td align="right" valign="top">
      <select id="urlFormSelect" class="formControl" title="preset the URL saving the time for downloading locally and then uploading the latest PGN from The Week In Chess or New In Chess; please note the URL of the latest issue of the online chess magazines is estimated and might occasionally need manual adjustment; please show your support to the online chess magazines visiting the TWIC website http://www.chess.co.uk/twic/twic.html and the NIC website http://www.newinchess.com" onChange="urlFormSelectChange();">
      <option value="header">preset URL</option>
      <option value="twic">latest TWIC</option>
      <option value="nic">latest NIC</option>
      <option value="clear">clear URL</option>
      </select>
    </td>
  </tr>
</form>

<form id="textForm">
  <tr>
    <td align="left" valign="top">
      <input id="pgnFormButton" type="button" class="formControl" value="show games from PGN text box" style="width:100%;" onClick="loadPgnFromForm();">
    </td>
    <td colspan=2 rowspan=2 width="100%" align="right" valign="bottom">
      <textarea id="pgnFormText" class="formControl" name="pgnTextbox" rows=4 style="width:100%;" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();" onChange="checkPgnFormTextSize();">$pgnTextbox</textarea>
    </td>
  </tr>
</form>

  <tr>
  <td align="left" valign="bottom">
    <input id="clearButton" type="button" class="formControl" value="clear form" onClick="document.getElementById('uploadFormFile').value = document.getElementById('urlFormText').value = document.getElementById('pgnFormText').value = '';" title="clear all input boxes, your inputs will be lost">
  </td>
  </tr>

</tbody></table>

END;
}

function print_chessboard() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;
  global $fileUploadLimitText, $fileUploadLimitBytes;

  print <<<END

<table width=100% cellpadding=0 cellspacing=0 border=0><tr><td valign=top align=left>
<a name="view"><div id="pgnStatus" style="font-weight: bold; padding-top: 3em; padding-bottom: 3em;">$pgnStatus</div></a>
</td><td valign=top align=right>
<div style="padding-top: 1em;">
&nbsp;
<a href="#moves" style="color: gray; font-size: 66%;">moves</a>
&nbsp;
<a href="#view" style="color: gray; font-size: 66%;">board</a>
&nbsp;
<a href="#top" style="color: gray; font-size: 66%;">form</a>
</div>
</tr></table>

<link href="$toolRoot/fonts/pgn4web-fonts.css" type="text/css" rel="stylesheet"></link>
<style type="text/css">

.boardTable {
  border-style: double;
  border-color: #a0a0a0;
  border-width: 3;
}

.pieceImage {
  width: 38;
  height: 38;
}

.whiteSquare,
.blackSquare,
.highlightWhiteSquare,
.highlightBlackSquare {
  width: 42;
  height: 42;
  border-style: solid;
  border-width: 2;
}

.whiteSquare,
.highlightWhiteSquare {
  border-color: #ede8d5;
  background: #ede8d5;
}

.blackSquare,
.highlightBlackSquare {
  border-color: #cfcbb3;
  background: #cfcbb3;
}

.highlightWhiteSquare,
.highlightBlackSquare {
  border-color: yellow;
  border-style: solid;
}

.selectControl {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 100% !important;
}

.buttonControl {
/* a "width" attribute here must use the !important flag to override default settings */
}

.buttonControlSpace {
/* a "width" attribute here must use the !important flag to override default settings */
}

.searchPgnButton {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 20% !important;
}

.searchPgnExpression {
/* a "width" attribute here must use the !important flag to override default settings */
  width: 80% !important;
}

.move,
.moveOn {
  color: black;
  font-weight: normal;
  text-decoration: none;   
  font-family: 'pgn4web ChessSansUsual', 'pgn4web Liberation Sans', sans-serif;
  line-height: 1.3em;
}

.moveOn {
  background: yellow;
}

.comment {
  color: gray;
  font-family: 'pgn4web Liberation Sans', sans-serif;
  line-height: 1.3em;
}

.label {
  color: gray;
  line-height: 1.3em;
}

</style>

<link rel="shortcut icon" href="pawn.ico"></link>

<script src="pgn4web.js" type="text/javascript"></script>
<script type="text/javascript">
  SetImagePath("merida/38"); 
  SetImageType("png");
  SetHighlightOption(true); 
  SetCommentsIntoMoveText(true);
  SetCommentsOnSeparateLines(true);
  SetInitialGame(1); 
  SetInitialHalfmove(0);
  SetGameSelectorOptions(" Event         Site          Rd  White            Black            Res  Date", true, 12, 12, 2, 15, 15, 3, 10);
  SetAutostartAutoplay(false);
  SetAutoplayDelay(2000);
  SetShortcutKeysEnabled(true);

  function customFunctionOnPgnTextLoad() { document.getElementById('numGm').innerHTML = numberOfGames; }
  function customFunctionOnPgnGameLoad() {
    document.getElementById('currGm').innerHTML = currentGame+1;
    document.getElementById('numPly').innerHTML = PlyNumber;
  }
  function customFunctionOnMove() { document.getElementById('currPly').innerHTML = CurrentPly; }
</script>

<!-- paste your PGN below and make sure you dont specify an external source with SetPgnUrl() -->
<form style="display: inline"><textarea style="display:none" id="pgnText">

$pgnText

</textarea></form>
<!-- paste your PGN above and make sure you dont specify an external source with SetPgnUrl() -->

<table width=100% cellspacing=0 cellpadding=5>
  <tr valign=bottom>
    <td align="center" colspan=2>

      <div id="GameSelector"></div>

      <div id="GameSearch"></div>

      <div style="padding-top: 1em;">&nbsp;</div>

    </td>
  </tr>
  <tr valign=top>
    <td valign=top align=center width=50%>
      <span id="GameBoard"></span> 
      <p></p>
      <div id="GameButtons"></div> 
    </td>
    <td valign=top align=left width=50%>

      <span class="label">Date:</span> <span id="GameDate"></span> 
      <br>
      <span class="label">Site:</span> <span style="white-space: nowrap;" id="GameSite"></span> 
      <br>
      <span class="label">Event:</span> <span style="white-space: nowrap;" id="GameEvent"></span> 
      <br>
      <span class="label">Round:</span> <span id="GameRound"></span> 
      <p></p>

      <span class="label">White:</span> <span style="white-space: nowrap;" id="GameWhite"></span> 
      <br>
      <span class="label">Black:</span> <span style="white-space: nowrap;" id="GameBlack"></span> 
      <br>
      <span class="label">Result:</span> <span id="GameResult"></span> 
      <p></p>

      <span class="label">game:</span> <span id=currGm>0</span> / <span id=numGm>0</span>
      <br>
      <span class="label">ply:</span> <span id=currPly>0</span> / <span id=numPly>0</span>
      <br>
      <span class="label">Side to move:</span> <span id="GameSideToMove"></span> 
      <br>
      <span class="label">Last move:</span> <span class="move"><span id="GameLastMove"></span></span> 
      <br>
      <span class="label">Next move:</span> <span class="move"><span id="GameNextMove"></span></span> 
      <p></p>

      <span class="label">Move comment:</span><br><span id="GameLastComment"></span> 

    </td>
  </tr>
</table>

<table width=100% cellpadding=0 cellspacing=0 border=0><tr><td valign=bottom align=right>
&nbsp;
<a name="moves" href="#moves" style="color: gray; font-size: 66%;">moves</a>
&nbsp;
<a href="#view" style="color: gray; font-size: 66%;">board</a>
&nbsp;
<a href="#top" style="color: gray; font-size: 66%;">form</a>
</tr></table>

<table width=100% cellspacing=0 cellpadding=5>
  <tr>
    <td colspan=2>
      <div style="padding-top: 2em; padding-bottom: 1em; text-align: justify;" id="GameText"></div>
    </td>
  </tr>
</table>

END;
}

function print_footer() {

  global $pgnText, $pgnTextbox, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;
  global $fileUploadLimitText, $fileUploadLimitBytes;


  if ($pgnText) { $hashStatement = "window.location.hash = 'view';"; }
  else { $hashStatement = ""; }

  print <<<END

<div>&nbsp;</div>
<table width=100% cellpadding=0 cellspacing=0 border=0><tr><td valign=bottom align=left>
<div style="color: gray; margin-top: 1em; margin-bottom: 1em;">$pgnDebugInfo</div>
</td><td valign=bottom align=right>
&nbsp;
<a href="#moves" style="color: gray; font-size: 66%;">moves</a>
&nbsp;
<a href="#view" style="color: gray; font-size: 66%;">board</a>
&nbsp;
<a href="#top" style="color: gray; font-size: 66%;">form</a>
</tr></table>

<script type="text/javascript">

function new_start_pgn4web() {
  setPgnUrl("$pgnUrl");
  checkPgnFormTextSize();
  start_pgn4web();
  $hashStatement
}

window.onload = new_start_pgn4web;

</script>


<! start of google analytics code -->

<! end of google analytics code -->


</body>

</html>

END;
}

?>
