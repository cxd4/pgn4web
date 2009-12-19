<?php

/*
 *  pgn4web javascript chessboard
 *  copyright (C) 2009 Paolo Casaschi
 *  see README file and http://pgn4web.casaschi.net
 *  for credits, license and more details
 */

// error_reporting(E_ERROR | E_PARSE);

$tmpDir = "viewer";

get_pgn();
print_header();
print_form();
check_tmpDir();
print_chessboard();
print_footer();


function get_pgn() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  $fileUploadLimit = "4M";

  $pgnText = $_REQUEST["pgnText"];
  if (!$pgnText) { $pgnText = $_REQUEST["pt"]; }
  $pgnUrl = $_REQUEST["pgnUrl"];
  if (!$pgnUrl) { $pgnUrl = $_REQUEST["pu"]; }

  if ($pgnText) {
    $pgnStatus = "PGN from direct user input";
    $pgnBoxText = $pgnText = str_replace("\\\"", "\"", $pgnText);
    return TRUE;
  } else if ($pgnUrl) {
    $pgnStatus = "PGN from URL <a href='" . $pgnUrl . "'>" . $pgnUrl . "</a>";
    $isPgn = preg_match("/\.pgn$/i",$pgnUrl);
    $isZip = preg_match("/\.zip$/i",$pgnUrl);
    if ($isZip) {
      $tempZipName = tempnam($tmpDir, "pgn4webViewer");
      if (copy($pgnUrl,$tempZipName)) {
        $pgnSource = $tempZipName;
      } else {
	$pgnStatus = "Failed to get remote zipfile";
        if ($tempZipName) { unlink($tempZipName); }
        return FALSE;   
      }
    } else {
      $pgnSource = $pgnUrl;
    }
  } elseif ($_FILES['pgnFile']['error'] === UPLOAD_ERR_OK) {
    $pgnFileName = $_FILES['pgnFile']['name'];
    $pgnStatus = "PGN from user file " . $pgnFileName;
    $pgnFileSize = $_FILES['userfile']['size'];
    if ($pgnFileSize > 4194304) {
      $pgnStatus = "Uploaded file exceeds " . $fileUploadLimit . " limit";
      return FALSE;
    } else { 
      $isPgn = preg_match("/\.pgn$/i",$pgnFileName);
      $isZip = preg_match("/\.zip$/i",$pgnFileName);
      $pgnSource = $_FILES['pgnFile']['tmp_name'];
    }
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_INI_SIZE | UPLOAD_ERR_FORM_SIZE)) {
    $pgnStatus = "Uploaded file exceeds " . $fileUploadLimit . " limit";
    return FALSE;
  } elseif ($_FILES['pgnFile']['error'] === (UPLOAD_ERR_PARTIAL | UPLOAD_ERR_NO_FILE | UPLOAD_ERR_NO_TMP_DIR | UPLOAD_ERR_CANT_WRITE | UPLOAD_ERR_EXTENSION)) {
    $pgnStatus = "Error uploading PGN data";
    return FALSE;
  } else {
    $pgnStatus = "Please provide PGN data";
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
          if ($tempZipName) { unlink($tempZipName); }
          return FALSE;
        }
      }
      zip_close($pgnZip);
      if ($tempZipName) { unlink($tempZipName); }
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
    $pgnText = file_get_contents($pgnSource);
    if (!$pgnText) {
      $pgnStatus = "Failed reading PGN data";
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

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  $tmpDirHandle = opendir($tmpDir);
  while($entryName = readdir($tmpDirHandle)) {
    if (($entryName !== ".") & ($entryName !== "..") & ($entryName !== "index.html")) {
      $unexpectedFiles = $unexpectedFiles . " " . $entryName;
    }
  }
  closedir($tmpDirHandle);

  if ($unexpectedFiles) {
    $pgnDebugInfo = $pgnDebugInfo . "temporary directory " . $tmpDir . " not empty:" . $unexpectedFiles; 
  }
}

function print_header() {

  print <<<END

<html>

<head>

<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"> 

<title>pgn4web PGN viewer</title> 

<style type="text/css">

body
{color: black; background: white; font-family: sans-serif; padding: 20px;}
 
a:link, a:visited, a:hover, a:active
{ color: black; text-decoration: none; }

</style>

</head>

<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr>
<td align="left" valign="middle"> 
<h1 style="font-family: sans-serif; color: red;"><a style="color: red;" href=.>pgn4web</a> PGN viewer</h1> 
</td>
<td align="right" valign="middle">
<a href=.><img src=pawns.png border=0></a>
</td>
</tr></tbody></table>

<div style="height: 1em;">&nbsp;</div>

END;
}

function get_latest_twic_url() {
  $givenTwicNumber = 765;
  $epochTimeOfGivenTwic = 1246921199; # Mon July 6th, 23:59:59 GMT
  $twicNum = $givenTwicNumber + floor((time() - $epochTimeOfGivenTwic) / (60 * 60 * 24 * 7));
  return "http://www.chesscenter.com/twic/zips/twic" . $twicNum . "g.zip";
}

function get_latest_nic_url() {
  $givenNicYear = 2009;
  $givenNicIssue = 1;
  $epochTimeOfGivenNic = 1232585999; # Jan 21st, 23:59:59 GMT
  $nicYear = $givenNicYear + floor((time() - $epochTimeOfGivenNic) / (60 * 60 * 24 * 365.25));
  $nicIssue = 1 + floor((time() - ($epochTimeOfGivenNic + ($nicYear - $givenNicYear) * (60 * 60 * 24 * 365.25))) / (60 * 60 * 24 * 365.25 / 8));
  return "http://www.newinchess.com/Magazine/GameFiles/mag_" . $nicYear . "_" . $nicIssue . "_pgn.zip";
}

function print_form() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  $latest_twic_url = get_latest_twic_url();
  $latest_nic_url  = get_latest_nic_url();

  print <<<END

<script type="text/javascript">
  function disableShortcutKeysAndStoreStatus() {}
  function restoreShortcutKeysStatus() {}
  
  function setPgnUrl(newPgnUrl) {
    if (!newPgnUrl) { newPgnUrl = ""; }
    document.getElementById("urlFormText").value = newPgnUrl;
    return false;
  }

</script>

<table width="100%" cellspacing=0 cellpadding=3 border=0><tbody>
<form id="textForm" action="$PHP_SELF#view" method="POST">
<tr>
<td colspan=5 width="100%">
<textarea id="pgnFormText" name="pgnText" rows=6 style="width:100%; font-size: 80%;" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();">$pgnBoxText</textarea>
</td>
</tr>
<tr>
<td colspan=4 width="100%">
<input id="pgnFormSubmitButton" type="submit" value="show games from PGN text box" style="width:100%;">
</td>
<td>
<input id="pgnFormClearButton" type="button" value="clear" onClick="document.getElementById('pgnFormText').value='';">
</td>
</tr>
</form>

<form id="urlForm" action="$PHP_SELF#view" method="POST">
<tr>
<td>
<input id="urlFormSubmitButton" type="submit" value="show games from PGN (or zipped PGN) URL">
</td>
<td width="100%">
<input id="urlFormText" name="pgnUrl" type="text" value="$pgnUrl" style="width:100%" onFocus="disableShortcutKeysAndStoreStatus();" onBlur="restoreShortcutKeysStatus();">
</td>
<td>
<input id="urlFormTwicButton" type="button" value="latest TWIC" onClick="setPgnUrl('$latest_twic_url');" title="this button saves you the time to download and then upload the latest PGN from The Week In Chess, please show your support for TWIC visiting the TWIC website http://www.chess.co.uk/twic/twic.html">
</td>
<td>
<input id="urlFormNicButton" type="button" value="latest NIC" onClick="setPgnUrl('$latest_nic_url');" title="this button saves you the time to download and then upload the latest PGN from New In Chess, please show your support for NIC visiting the NIC website http://www.newinchess.com">
</td>
<td>
<input id="urlFormClearButton" type="button" value="clear" onClick="setPgnUrl();">
</td>
</tr>
</form>

<form id="uploadForm" enctype="multipart/form-data" action="$PHP_SELF#view" method="POST">
<tr>
<td>
<input id="uploadFormSubmitButton" type="submit" value="show games from PGN (or zipped PGN) file" style="width:100%">
</td>
<td colspan=3 width="100%">
<input type="hidden" name="MAX_FILE_SIZE" value="4194304">
<input id="uploadFormFile" name="pgnFile" type="file" style="width:100%">
</td>
</tr>
</form>

</tbody></table>

<div>&nbsp;</div>

END;
}

function print_chessboard() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  print <<<END

<hr>
<a name="view"><div style="font-weight: bold; margin-top: 2em; margin-bottom: 2em;">$pgnStatus</div></a>

END;

  if (!$pgnText) { return; }

  print <<<END

<style type="text/css">

.boardTable {
  border-style: double;
  border-color: black;
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
}

.moveOn {
  background: yellow;
}

.comment,
.nag {
  color: gray;
}

.label {
  color: gray;
}

</style>

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
  SetAutoplayDelay(1000);
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

      <div style="text-align: right; color: #aaaaaa; font-size: 66%">
      ply:<span id=currPly>0</span>/<span id=numPly>0</span> 
      game:<span id=currGm>0</span>/<span id=numGm>0</span> 
      </div>
      
    </td>
  <tr valign=top>
    <td valign=top align=center width=50%>
      <span id="GameBoard"></span> 
      <p></p>
      <div id="GameButtons"></div> 
    </td>
    <td valign=top align=left width=50%>

      <span class="label">Site:</span> <span style="white-space: nowrap;" id="GameSite"></span> 
      <br>
      <span class="label">Event:</span> <span style="white-space: nowrap;" id="GameEvent"></span> 
      <br>
      <span class="label">Round:</span> <span id="GameRound"></span> 
      <p></p>
      <span class="label">Date:</span> <span id="GameDate"></span> 
      <p></p>

      <span class="label">White:</span> <span style="white-space: nowrap;" id="GameWhite"></span> 
      <br>
      <span class="label">Black:</span> <span style="white-space: nowrap;" id="GameBlack"></span> 
      <p></p>
      <span class="label">Result:</span> <span id="GameResult"></span> 
      <p></p>
      <span class="label">Side to move:</span> <span id="GameSideToMove"></span> 
      <br>

      <span class="label">Last move:</span> <span class="move"><span id="GameLastMove"></span></span> 
      <br>
      <span class="label">Next move:</span> <span class="move"><span id="GameNextMove"></span></span> 
      <p></p>
      <span class="label">Move comment:</span><br><span id="GameLastComment"></span> 
    </td>
  </tr>
  <tr>

    <td colspan=2>
      <div style="margin-top: 2em; margin-bottom: 1em; text-align: justify;" id="GameText"></div>
    </td>
  </tr>
</table>

END;
}

function print_footer() {

  global $pgnText, $pgnBoxText, $pgnUrl, $pgnFileName, $pgnFileSize, $pgnStatus, $tmpDir, $pgnDebugInfo;

  print <<<END

<hr>
<div style="color: red; font-weight: bold; margin-top: 1em; margin-bottom: 1em;">$pgnDebugInfo</div>

</body>

</html>

END;
}

?>
